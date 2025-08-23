<?php
session_start();
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit();
}

// Get database connection
$pdo = getDBConnection();

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

// Fetch sessions from database for the current week
$sessions = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            s.*,
            c.class_name,
            c.class_code,
            DATE_FORMAT(s.session_date, '%W') as day_name,
            TIME_FORMAT(s.start_time, '%H:%i') as start_time_formatted,
            TIME_FORMAT(s.end_time, '%H:%i') as end_time_formatted,
            CONCAT(TIME_FORMAT(s.start_time, '%H:%i'), '-', TIME_FORMAT(s.end_time, '%H:%i')) as time_slot
        FROM sessions s
        JOIN classes c ON s.class_id = c.id
        WHERE s.session_date BETWEEN ? AND ?
        AND s.status != 'cancelled'
        ORDER BY s.session_date, s.start_time
    ");
    $stmt->execute([$week_start, $week_end]);
    $sessions = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = 'Error fetching sessions: ' . $e->getMessage();
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
        'subject' => $session['session_name'],
        'class_name' => $session['class_name'],
        'class_code' => $session['class_code'],
        'location' => $session['location'],
        'session_type' => $session['session_type'],
        'status' => $session['status'],
        'start_time' => $session['start_time_formatted'],
        'end_time' => $session['end_time_formatted']
    ];
    
    // Collect unique time slots
    if (!in_array($time_slot, $time_slots)) {
        $time_slots[] = $time_slot;
    }
}

// Sort time slots
sort($time_slots);

// If no sessions found, create default time slots
if (empty($time_slots)) {
    $time_slots = ['09:00-10:30', '11:00-12:30', '14:00-15:30', '16:00-17:30'];
}

// Get unique classes for legend
$unique_classes = [];
foreach ($sessions as $session) {
    $class_key = $session['class_code'];
    if (!isset($unique_classes[$class_key])) {
        $unique_classes[$class_key] = [
            'name' => $session['class_name'],
            'code' => $session['class_code']
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timetable - Full Attend</title>
    <link rel="stylesheet" href="admin_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="admin-main">
            <header class="dashboard-header">
                <div class="header-left">
                    <h1>Timetable Management</h1>
                    <p>Weekly class schedule overview and management</p>
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="header-right">
                    <div class="date-info">
                        <i class="fas fa-calendar"></i>
                        <span><?php echo date('l, F j, Y'); ?></span>
                    </div>
                    
                </div>
            </header>
            
            <!-- Controls -->
            <section class="timetable-controls">
                <div class="controls-container">
                    <div class="week-selector">
                        <button class="btn-icon" onclick="changeWeek(-1)">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <span class="week-display">
                            Week of <?php echo date('M j, Y', strtotime($week_start)); ?> - 
                            <?php echo date('M j, Y', strtotime($week_end)); ?>
                        </span>
                        <button class="btn-icon" onclick="changeWeek(1)">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    
                </div>
            </section>
            
            <!-- Timetable Grid -->
            <section class="timetable-section">
                <div class="card">
                    <div class="timetable-grid">
                        <!-- Header Row -->
                        <div class="timetable-header">
                            <div class="time-slot-header">
                                <i class="fas fa-clock"></i>
                                Time
                            </div>
                            <?php foreach ($days as $day): ?>
                                <div class="day-header <?php echo $day['is_today'] ? 'today' : ''; ?>">
                                    <div class="day-name"><?php echo $day['name']; ?></div>
                                    <div class="day-date"><?php echo $day['display']; ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Time Slots -->
                        <?php if (count($time_slots) > 0): ?>
                            <?php foreach ($time_slots as $time): ?>
                                <div class="timetable-row">
                                    <div class="time-slot">
                                        <div class="time-display"><?php echo $time; ?></div>
                                    </div>
                                    <?php foreach ($days as $day): ?>
                                        <div class="schedule-cell" data-day="<?php echo $day['name']; ?>" data-time="<?php echo $time; ?>">
                                            <?php 
                                            $class_found = false;
                                            if (isset($timetable[$day['name']])) {
                                                foreach ($timetable[$day['name']] as $class) {
                                                    if ($class['time'] === $time) {
                                                        $class_found = true;
                                                        $status_class = strtolower($class['status']);
                                                        $type_class = strtolower($class['session_type']);
                                                        echo '<div class="class-block ' . $status_class . ' ' . $type_class . '" onclick="viewSession(' . $class['id'] . ')">';
                                                        echo '<div class="class-subject">' . htmlspecialchars($class['subject']) . '</div>';
                                                        echo '<div class="class-details">';
                                                        echo '<span class="class-code">' . htmlspecialchars($class['class_code']) . '</span>';
                                                        echo '<span class="class-location">' . htmlspecialchars($class['location']) . '</span>';
                                                        echo '</div>';
                                                        echo '<div class="class-time">';
                                                        echo '<i class="fas fa-clock"></i>';
                                                        echo htmlspecialchars($class['start_time']) . '-' . htmlspecialchars($class['end_time']);
                                                        echo '</div>';
                                                        if ($class['session_type'] !== 'lecture') {
                                                            echo '<div class="session-type-badge">' . ucfirst($class['session_type']) . '</div>';
                                                        }
                                                        echo '</div>';
                                                        break;
                                                    }
                                                }
                                            }
                                            if (!$class_found) {
                                                echo '<div class="empty-slot" onclick="addSession(\'' . $day['date'] . '\', \'' . $time . '\')">';
                                                echo '<i class="fas fa-plus"></i>';
                                                echo '<span>Add Session</span>';
                                                echo '</div>';
                                            }
                                            ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-timetable">
                                <div class="empty-state">
                                    <i class="fas fa-calendar-times"></i>
                                    <h3>No Sessions Scheduled</h3>
                                    <p>No sessions found for this week. Start by adding your first session.</p>
                                    <a href="add_session.php" class="btn-primary">
                                        <i class="fas fa-plus"></i>
                                        Add First Session
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
            
            <!-- Statistics and Legend -->
            <div class="content-grid">
                <!-- Statistics -->
                <section class="timetable-stats">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-bar"></i> Week Statistics</h3>
                        </div>
                        <div class="stats-grid">
                            <div class="stat-item">
                                <div class="stat-icon">
                                    <i class="fas fa-calendar-day"></i>
                                </div>
                                <div class="stat-info">
                                    <h4><?php echo count($sessions); ?></h4>
                                    <p>Total Sessions</p>
                                </div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-icon">
                                    <i class="fas fa-book"></i>
                                </div>
                                <div class="stat-info">
                                    <h4><?php echo count($unique_classes); ?></h4>
                                    <p>Active Classes</p>
                                </div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="stat-info">
                                    <h4><?php echo count($time_slots); ?></h4>
                                    <p>Time Slots</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Legend -->
                <section class="timetable-legend">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-info-circle"></i> Legend</h3>
                        </div>
                        <div class="legend-content">
                            <?php if (count($unique_classes) > 0): ?>
                                <div class="legend-grid">
                                    <?php 
                                    $colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'];
                                    $color_index = 0;
                                    foreach ($unique_classes as $class): 
                                        $color = $colors[$color_index % count($colors)];
                                        $color_index++;
                                    ?>
                                        <div class="legend-item">
                                            <div class="legend-color" style="background-color: <?php echo $color; ?>"></div>
                                            <div class="legend-text">
                                                <strong><?php echo htmlspecialchars($class['name']); ?></strong>
                                                <small><?php echo htmlspecialchars($class['code']); ?></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-legend">
                                    <p>No classes scheduled for this week.</p>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Session Type Legend -->
                            <div class="type-legend">
                                <h4>Session Types</h4>
                                <div class="type-grid">
                                    <div class="type-item">
                                        <div class="type-badge lecture">Lecture</div>
                                    </div>
                                    <div class="type-item">
                                        <div class="type-badge lab">Lab</div>
                                    </div>
                                    <div class="type-item">
                                        <div class="type-badge tutorial">Tutorial</div>
                                    </div>
                                    <div class="type-item">
                                        <div class="type-badge exam">Exam</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
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
            window.location.href = 'session_details.php?id=' + sessionId;
        }
        
        // Add new session
        function addSession(date, timeSlot) {
            const params = new URLSearchParams({
                date: date,
                time: timeSlot
            });
            window.location.href = 'add_session.php?' + params.toString();
        }
        
        // Export timetable
        function exportTimetable() {
            const week = new URLSearchParams(window.location.search).get('week') || '<?php echo date('Y-m-d'); ?>';
            window.open('export_timetable.php?week=' + week + '&format=pdf', '_blank');
        }
        
        // Initialize tooltips and interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to class blocks
            const classBlocks = document.querySelectorAll('.class-block');
            classBlocks.forEach(block => {
                block.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.02)';
                    this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
                });
                
                block.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                    this.style.boxShadow = '';
                });
            });
            
            // Add hover effects to empty slots
            const emptySlots = document.querySelectorAll('.empty-slot');
            emptySlots.forEach(slot => {
                slot.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f3f4f6';
                    this.style.borderColor = '#6366f1';
                });
                
                slot.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                    this.style.borderColor = '';
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
        });
    </script>
</body>
</html>
