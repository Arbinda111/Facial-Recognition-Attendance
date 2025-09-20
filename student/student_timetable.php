<?php
session_start();
require_once '../config/database.php';

// Check if student is logged in
if (!isset($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true) {
    header('Location: student_login.php');
    exit();
}

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];
$student_email = $_SESSION['student_email'];

// Get database connection
$pdo = getDBConnection();

// Get the actual database ID of the current student
$student_db_id = null;
try {
    $stmt = $pdo->prepare("SELECT id FROM students WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $student_record = $stmt->fetch();
    if ($student_record) {
        $student_db_id = $student_record['id'];
    } else {
        $error_message = 'Student not found in database';
    }
} catch (PDOException $e) {
    $error_message = 'Error fetching student ID: ' . $e->getMessage();
}

// Handle week navigation
$current_week = isset($_GET['week']) ? $_GET['week'] : date('Y-m-d');
$week_start = date('Y-m-d', strtotime('monday this week', strtotime($current_week)));
$week_end = date('Y-m-d', strtotime('sunday this week', strtotime($current_week)));

// Generate days for the current week
$days = [];
for ($i = 0; $i < 5; $i++) { // Monday to Friday
    $date = date('Y-m-d', strtotime($week_start . " +$i days"));
    $days[] = [
        'date' => $date,
        'name' => date('l', strtotime($date)),
        'display' => date('M j', strtotime($date)),
        'is_today' => $date === date('Y-m-d')
    ];
}

// Fetch sessions for this student based on lecturer_student_enrollments for the current week
$sessions = [];
$debug_query_info = [];

if ($student_db_id) {
    try {
        // Debug: Check what enrollments exist for this student
        $debug_stmt = $pdo->prepare("SELECT * FROM lecturer_student_enrollments WHERE student_id = ?");
        $debug_stmt->execute([$student_db_id]);
        $enrollments = $debug_stmt->fetchAll();
        $debug_query_info['enrollments_found'] = count($enrollments);
        $debug_query_info['enrollment_details'] = $enrollments;
        
        // First, try to get sessions from lecturer_student_enrollments table (primary method)
        $stmt = $pdo->prepare("
            SELECT 
                s.*,
                c.class_name,
                c.class_code,
                sub.subject_name,
                sub.subject_code,
                l.name as lecturer_name,
                lse.student_id as enrolled_student_db_id,
                st.student_id as student_identifier,
                st.id as student_table_id,
                DATE_FORMAT(s.session_date, '%W') as day_name,
                TIME_FORMAT(s.start_time, '%H:%i') as start_time_formatted,
                TIME_FORMAT(s.end_time, '%H:%i') as end_time_formatted,
                CONCAT(TIME_FORMAT(s.start_time, '%H:%i'), '-', TIME_FORMAT(s.end_time, '%H:%i')) as time_slot
            FROM sessions s
            JOIN classes c ON s.class_id = c.id
            JOIN lecturer_student_enrollments lse ON (
                (c.id = lse.class_id AND c.subject_id = lse.subject_id) OR
                (lse.class_id = 0 AND c.subject_id = lse.subject_id AND c.lecturer_id = lse.lecturer_id)
            )
            JOIN subjects sub ON lse.subject_id = sub.id
            JOIN lecturers l ON lse.lecturer_id = l.id
            JOIN students st ON lse.student_id = st.id
            WHERE s.session_date BETWEEN ? AND ?
            AND s.status != 'cancelled'
            AND lse.student_id = ?
            AND c.id > 0
            ORDER BY s.session_date, s.start_time
        ");
        $stmt->execute([$week_start, $week_end, $student_db_id]);
        $sessions = $stmt->fetchAll();
        $debug_query_info['primary_method'] = 'lecturer_student_enrollments';
        $debug_query_info['sessions_from_primary'] = count($sessions);
        
        // If still no sessions found, try a broader approach - match by subject and lecturer
        if (empty($sessions) && !empty($enrollments)) {
            $debug_query_info['trying_broader_search'] = true;
            
            // Build a query to find sessions for the same subject and lecturer combinations
            $placeholders = [];
            $params = [$week_start, $week_end];
            
            foreach ($enrollments as $enrollment) {
                $placeholders[] = "(c.subject_id = ? AND c.lecturer_id = ?)";
                $params[] = $enrollment['subject_id'];
                $params[] = $enrollment['lecturer_id'];
            }
            
            if (!empty($placeholders)) {
                $where_clause = implode(' OR ', $placeholders);
                
                $broader_stmt = $pdo->prepare("
                    SELECT 
                        s.*,
                        c.class_name,
                        c.class_code,
                        sub.subject_name,
                        sub.subject_code,
                        l.name as lecturer_name,
                        ? as enrolled_student_db_id,
                        st.student_id as student_identifier,
                        st.id as student_table_id,
                        DATE_FORMAT(s.session_date, '%W') as day_name,
                        TIME_FORMAT(s.start_time, '%H:%i') as start_time_formatted,
                        TIME_FORMAT(s.end_time, '%H:%i') as end_time_formatted,
                        CONCAT(TIME_FORMAT(s.start_time, '%H:%i'), '-', TIME_FORMAT(s.end_time, '%H:%i')) as time_slot
                    FROM sessions s
                    JOIN classes c ON s.class_id = c.id
                    JOIN subjects sub ON c.subject_id = sub.id
                    JOIN lecturers l ON c.lecturer_id = l.id
                    CROSS JOIN students st
                    WHERE s.session_date BETWEEN ? AND ?
                    AND s.status != 'cancelled'
                    AND c.id > 0
                    AND st.id = ?
                    AND ($where_clause)
                    ORDER BY s.session_date, s.start_time
                ");
                
                $broader_params = array_merge([$student_db_id], $params, [$student_db_id]);
                $broader_stmt->execute($broader_params);
                $sessions = $broader_stmt->fetchAll();
                $debug_query_info['sessions_from_broader'] = count($sessions);
            }
        }
        
        // If no sessions found with lecturer_student_enrollments, fallback to student_enrollments
        if (empty($sessions)) {
            // Debug: Check student_enrollments as well
            $debug_stmt2 = $pdo->prepare("SELECT * FROM student_enrollments WHERE student_id = ?");
            $debug_stmt2->execute([$student_db_id]);
            $fallback_enrollments = $debug_stmt2->fetchAll();
            $debug_query_info['fallback_enrollments_found'] = count($fallback_enrollments);
            
            $stmt = $pdo->prepare("
                SELECT 
                    s.*,
                    c.class_name,
                    c.class_code,
                    sub.subject_name,
                    sub.subject_code,
                    l.name as lecturer_name,
                    se.student_id as enrolled_student_db_id,
                    st.student_id as student_identifier,
                    st.id as student_table_id,
                    DATE_FORMAT(s.session_date, '%W') as day_name,
                    TIME_FORMAT(s.start_time, '%H:%i') as start_time_formatted,
                    TIME_FORMAT(s.end_time, '%H:%i') as end_time_formatted,
                    CONCAT(TIME_FORMAT(s.start_time, '%H:%i'), '-', TIME_FORMAT(s.end_time, '%H:%i')) as time_slot
                FROM sessions s
                JOIN classes c ON s.class_id = c.id
                JOIN student_enrollments se ON c.id = se.class_id
                LEFT JOIN subjects sub ON c.subject_id = sub.id
                LEFT JOIN lecturers l ON c.lecturer_id = l.id
                JOIN students st ON se.student_id = st.id
                WHERE s.session_date BETWEEN ? AND ?
                AND s.status != 'cancelled'
                AND se.student_id = ?
                AND se.status = 'enrolled'
                AND c.id > 0
                ORDER BY s.session_date, s.start_time
            ");
            $stmt->execute([$week_start, $week_end, $student_db_id]);
            $sessions = $stmt->fetchAll();
            $debug_query_info['used_fallback'] = true;
            $debug_query_info['sessions_from_fallback'] = count($sessions);
        } else {
            $debug_query_info['used_fallback'] = false;
        }
    } catch (PDOException $e) {
        $error_message = 'Error fetching sessions: ' . $e->getMessage();
        $debug_query_info['error'] = $e->getMessage();
    }
}

// Organize sessions by day and time
$timetable = [];
$time_slots = [];

foreach ($sessions as $session) {
    $day = $session['day_name'];
    $time_slot = $session['time_slot'];
    
    if (!isset($timetable[$day])) {
        $timetable[$day] = [];
    }
    
    $timetable[$day][] = [
        'id' => $session['id'],
        'time' => $time_slot,
        'subject' => $session['subject_name'] ? $session['subject_name'] : $session['session_name'],
        'subject_code' => $session['subject_code'],
        'class_name' => $session['class_name'],
        'class_code' => $session['class_code'],
        'location' => $session['location'],
        'session_type' => $session['session_type'],
        'status' => $session['status'],
        'lecturer_name' => $session['lecturer_name'],
        'start_time' => $session['start_time_formatted'],
        'end_time' => $session['end_time_formatted'],
        'enrolled_student_db_id' => isset($session['enrolled_student_db_id']) ? $session['enrolled_student_db_id'] : 'N/A',
        'student_identifier' => isset($session['student_identifier']) ? $session['student_identifier'] : 'N/A',
        'student_table_id' => isset($session['student_table_id']) ? $session['student_table_id'] : 'N/A'
    ];
    
    // Collect unique time slots
    if (!in_array($time_slot, $time_slots)) {
        $time_slots[] = $time_slot;
    }
}

// Sort time slots
sort($time_slots);

// If no sessions found, create default time slots for display
if (empty($time_slots)) {
    $time_slots = ['09:00-10:30', '11:00-12:30', '13:00-14:30', '14:00-15:30', '16:00-17:30'];
}

// Get unique subjects for statistics
$unique_subjects = [];
foreach ($sessions as $session) {
    $subject_key = $session['subject_code'] ? $session['subject_code'] : $session['class_code'];
    if (!isset($unique_subjects[$subject_key])) {
        $unique_subjects[$subject_key] = [
            'name' => $session['subject_name'] ? $session['subject_name'] : $session['session_name'],
            'code' => $subject_key
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Student Timetable - FullAttend</title>
  <link rel="stylesheet" href="student_styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="student-portal">
  <div class="dashboard">
    <!-- Enhanced Sidebar -->
    <aside class="sidebar">
      <div class="sidebar-header">
        <div class="student-avatar">
          <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($student_name); ?>&background=667eea&color=fff&size=60" alt="Student Avatar">
        </div>
        <div class="student-info">
          <h3><?php echo htmlspecialchars($student_name); ?></h3>
          <p class="student-id">ID: <?php echo htmlspecialchars($_SESSION['student_id']); ?></p>
          <span class="status-badge online">Online</span>
        </div>
      </div>
      
      <nav class="sidebar-nav">
        <a href="student_dashboard.php" class="nav-item">
          <i class="fas fa-chart-pie"></i>
          <span>Dashboard</span>
        </a>
        <a href="my_attendance.php" class="nav-item">
          <i class="fas fa-calendar-check"></i>
          <span>My Attendance</span>
        </a>
        <a href="student_timetable.php" class="nav-item active">
          <i class="fas fa-calendar-alt"></i>
          <span>Timetable</span>
        </a>
        <a href="settings.php" class="nav-item">
          <i class="fas fa-cog"></i>
          <span>Settings</span>
        </a>
        <div class="nav-divider"></div>
        <a href="student_logout.php" class="nav-item logout">
          <i class="fas fa-sign-out-alt"></i>
          <span>Logout</span>
        </a>
      </nav>
      
      <div class="sidebar-footer">
        <div class="logos">
          <img src="../images/cihe_logo.png" alt="CIHE Logo" class="logo">
          <img src="../images/fullattend_logo.png" alt="FullAttend Logo" class="logo">
        </div>
      </div>
    </aside>

    <!-- Main Dashboard Content -->
    <main class="dashboard-content">
      <!-- Header Section -->
      <header class="dashboard-header">
        <div class="header-content">
          <div class="welcome-section">
            <h1>My Timetable</h1>
            <p class="subtitle">Your weekly class schedule</p>
           
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error" style="background: #fee; border: 1px solid #fcc; color: #c33; padding: 10px; border-radius: 5px; margin-top: 10px;">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
          </div>
          <div class="header-actions">
            <div class="date-info">
              <i class="fas fa-calendar"></i>
              <span><?php echo date('l, F j, Y'); ?></span>
            </div>
            
          </div>
        </div>
      </header>

      <!-- Timetable Section -->
      <section class="dashboard-card">
        <div class="card-header">
          <h2><i class="fas fa-calendar-week"></i> Weekly Schedule</h2>
          <div class="week-navigation" style="margin-left: 20px;">
              <button onclick="changeWeek(-1)" style="background: #667eea; color: white; border: none; padding: 8px 12px; border-radius: 5px; cursor: pointer; margin-right: 10px;">
                <i class="fas fa-chevron-left"></i>
              </button>
              <span style="font-weight: 500;">
                Week of <?php echo date('M j', strtotime($week_start)); ?> - <?php echo date('M j, Y', strtotime($week_end)); ?>
              </span>
              <button onclick="changeWeek(1)" style="background: #667eea; color: white; border: none; padding: 8px 12px; border-radius: 5px; cursor: pointer; margin-left: 10px;">
                <i class="fas fa-chevron-right"></i>
              </button>
            </div>
        </div>

        <!-- Calendar grid -->
        <div class="timetable-container">
          <?php if (count($time_slots) > 0): ?>
            <table class="timetable-grid">
              <thead>
                <tr>
                  <th class="time-column">Time</th>
                  <?php foreach ($days as $day): ?>
                    <th class="<?php echo $day['is_today'] ? 'today' : ''; ?>">
                      <?php echo $day['name']; ?>
                      <br><small><?php echo $day['display']; ?></small>
                    </th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($time_slots as $time): ?>
                  <tr>
                    <td class="time-slot"><strong><?php echo $time; ?></strong></td>
                    <?php foreach ($days as $day): ?>
                      <td class="schedule-cell">
                        <?php 
                        $class_found = false;
                        if (isset($timetable[$day['name']])) {
                            foreach ($timetable[$day['name']] as $class) {
                                if ($class['time'] === $time) {
                                    $class_found = true;
                                    $type_class = strtolower($class['session_type']);
                                    echo '<div class="tt-session ' . $type_class . '" onclick="viewSession(' . $class['id'] . ')">';
                                    echo '<div class="tt-title">' . htmlspecialchars($class['subject']) . '</div>';
                                    echo '<div class="muted tt-meta">';
                                    echo htmlspecialchars($class['class_code']);
                                    if ($class['location']) {
                                        echo ' • ' . htmlspecialchars($class['location']);
                                    }
                                    echo '</div>';
                                    if ($class['lecturer_name']) {
                                        echo '<div class="tt-lecturer" style="font-size: 0.8em; color: #666; margin-top: 2px;">';
                                        echo htmlspecialchars($class['lecturer_name']);
                                        echo '</div>';
                                    }
                                   
                                    if ($class['session_type'] !== 'lecture') {
                                        echo '<div class="session-type-badge" style="font-size: 0.7em; background: #e3f2fd; color: #1976d2; padding: 2px 6px; border-radius: 3px; margin-top: 2px; display: inline-block;">';
                                        echo ucfirst($class['session_type']);
                                        echo '</div>';
                                    }
                                    echo '</div>';
                                    break;
                                }
                            }
                        }
                        if (!$class_found) {
                            echo '<div class="empty-slot" style="color: #999; font-style: italic; text-align: center; padding: 10px;">No Class</div>';
                        }
                        ?>
                      </td>
                    <?php endforeach; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <div class="empty-timetable" style="text-align: center; padding: 40px; color: #666;">
              <i class="fas fa-calendar-times" style="font-size: 3em; margin-bottom: 20px; opacity: 0.5;"></i>
              <h3>No Classes Scheduled</h3>
              <p>You don't have any classes scheduled for this week.</p>
              <p>Contact your academic advisor if you think this is an error.</p>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <!-- Legend -->
      <div class="legend" style="justify-content:flex-start; margin-top:10px;">
        <div class="legend-item"><span class="status-dot" style="background: #667eea; width: 12px; height: 12px; border-radius: 50%; display: inline-block; margin-right: 8px;"></span> Lecture</div>
        <div class="legend-item" style="margin-left: 20px;"><span class="status-dot" style="background: #48bb78; width: 12px; height: 12px; border-radius: 50%; display: inline-block; margin-right: 8px;"></span> Tutorial</div>
        <div class="legend-item" style="margin-left: 20px;"><span class="status-dot" style="background: #ed8936; width: 12px; height: 12px; border-radius: 50%; display: inline-block; margin-right: 8px;"></span> Lab</div>
        <div class="legend-item" style="margin-left: 20px;"><span class="status-dot" style="background: #e53e3e; width: 12px; height: 12px; border-radius: 50%; display: inline-block; margin-right: 8px;"></span> Exam</div>
      </div>
    </main>
  </div>

  <script>
    // Week navigation
    function changeWeek(direction) {
        const currentWeek = new URLSearchParams(window.location.search).get('week') || '<?php echo date('Y-m-d'); ?>';
        const currentDate = new Date(currentWeek);
        currentDate.setDate(currentDate.getDate() + (direction * 7));
        
        const newWeek = currentDate.toISOString().split('T')[0];
        window.location.href = '?week=' + newWeek;
    }
    
    // View session details
    function viewSession(sessionId) {
        // You can implement session details view here
        // For now, just show an alert with session info
        alert('Session ID: ' + sessionId );
    }
    
    // Initialize interactions
    document.addEventListener('DOMContentLoaded', function() {
        // Add hover effects to session blocks
        const sessionBlocks = document.querySelectorAll('.tt-session');
        sessionBlocks.forEach(block => {
            block.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.02)';
                this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
                this.style.cursor = 'pointer';
            });
            
            block.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
                this.style.boxShadow = '';
            });
        });
        
        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowLeft') {
                changeWeek(-1);
            } else if (e.key === 'ArrowRight') {
                changeWeek(1);
            }
        });
        
        // Add styles for session types
        const style = document.createElement('style');
        style.textContent = `
            .tt-session.lecture {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
            }
            .tt-session.tutorial {
                background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
                color: white;
            }
            .tt-session.lab {
                background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
                color: white;
            }
            .tt-session.exam {
                background: linear-gradient(135deg, #e53e3e 0%, #c53030 100%);
                color: white;
            }
            .tt-session {
                padding: 8px 12px;
                border-radius: 8px;
                margin: 2px 0;
                transition: all 0.3s ease;
                cursor: pointer;
            }
            .tt-title {
                font-weight: 600;
                font-size: 0.9em;
                margin-bottom: 4px;
            }
            .tt-meta {
                font-size: 0.8em;
                opacity: 0.9;
            }
            .tt-lecturer {
                font-size: 0.7em;
                opacity: 0.8;
                margin-top: 2px;
            }
            .empty-slot {
                padding: 20px;
                text-align: center;
                color: #ccc;
                font-style: italic;
            }
            .schedule-cell {
                vertical-align: top;
                padding: 5px;
                border: 1px solid #eee;
                min-height: 60px;
            }
            .time-slot {
                font-weight: 600;
                padding: 10px;
                background: #f8f9fa;
                text-align: center;
                vertical-align: middle;
            }
            .timetable-grid th.today {
                background: #e3f2fd;
                color: #1976d2;
                font-weight: 600;
            }
        `;
        document.head.appendChild(style);
    });
  </script>
</body>
</html>
