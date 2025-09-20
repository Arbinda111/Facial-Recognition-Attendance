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

// Get student statistics
$stats = [];

// Get the actual student database ID first
$stmt = $pdo->prepare("SELECT id, face_encoding FROM students WHERE student_id = ?");
$stmt->execute([$student_id]);
$student_record = $stmt->fetch();
$student_db_id = $student_record ? $student_record['id'] : null;
$face_encoding_status = $student_record ? $student_record['face_encoding'] : null;

// Debug: Log the student mapping
error_log("Student Dashboard - Session student_id: " . $student_id);
error_log("Student Dashboard - Database student DB ID: " . ($student_db_id ?? 'null'));

// Verify the student exists in attendance_student table using string student_id
if ($student_id) {
    $verify_stmt = $pdo->prepare("SELECT COUNT(*) as count, MIN(name) as student_name FROM attendance_student WHERE student_id = ?");
    $verify_stmt->execute([$student_id]);
    $verify_result = $verify_stmt->fetch();
    error_log("Student Dashboard - Records in attendance_student for student_id '" . $student_id . "': " . $verify_result['count'] . ", Name: " . ($verify_result['student_name'] ?? 'null'));
}

// Determine face registration status
$face_registration_status = 'Not Registered';
$face_status_class = 'pending';
$face_status_icon = 'fas fa-exclamation-triangle';

if (!empty($face_encoding_status)) {
    if ($face_encoding_status === 'registered' || strlen($face_encoding_status) > 10) {
        $face_registration_status = 'Registered';
        $face_status_class = 'registered';
        $face_status_icon = 'fas fa-check-circle';
    } else {
        $face_registration_status = 'Pending Registration';
        $face_status_class = 'pending';
        $face_status_icon = 'fas fa-clock';
    }
}

// Store in session for future use
if ($student_db_id) {
    $_SESSION['student_db_id'] = $student_db_id;
}

// Total attendance records from attendance_student table using string student_id
$total_records = 0;
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance_student WHERE student_id = ?");
$stmt->execute([$student_id]);
$total_records = $stmt->fetch()['count'];

// Today's attendance from attendance_student table using string student_id
$today_attendance = 0;
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count FROM attendance_student 
    WHERE student_id = ?
");
$stmt->execute([$student_id]);
$today_attendance = $stmt->fetch()['count'];

// This week's attendance from attendance_student table using string student_id
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_attendance = 0;
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count FROM attendance_student 
    WHERE student_id = ?
");
$stmt->execute([$student_id]);
$week_attendance = $stmt->fetch()['count'];

// Calculate attendance percentage (assuming 5 days a week)
$days_in_week = max(1, (strtotime('now') - strtotime($week_start)) / (60 * 60 * 24) + 1);
$attendance_percentage = $days_in_week > 0 ? round(($week_attendance / min($days_in_week, 5)) * 100) : 0;

// Number of enrolled subjects - Use student PK id from session
$enrolled_subjects = 0;
if (!empty($_SESSION['student_db_id'])) {
    $student_pk_id = $_SESSION['student_db_id'];
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT subject_id) as subject_count FROM lecturer_student_enrollments WHERE student_id = ?");
    $stmt->execute([$student_pk_id]);
    $enrolled_subjects = $stmt->fetch()['subject_count'];
}

// Recent attendance records from attendance_student table for the logged-in student using string student_id
$recent_attendance = [];
$stmt = $pdo->prepare("
    SELECT student_id, name, 'face_recognition' as source
    FROM attendance_student 
    WHERE student_id = ?
    ORDER BY name DESC
    LIMIT 5
");
$stmt->execute([$student_id]);
$recent_attendance = $stmt->fetchAll();

// Debug: Log the query results
error_log("Student Dashboard - Using student_id string: " . $student_id);
error_log("Student Dashboard - Found " . count($recent_attendance) . " attendance records");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Dashboard - FullAttend</title>
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
          <h3><?php echo htmlspecialchars($student_name); ?> </h3>
          <!-- (ID: <?php echo htmlspecialchars($_SESSION['student_db_id']); ?>) -->
          <p class="student-id">ID: <?php echo htmlspecialchars($_SESSION['student_id']); ?></p>
          <span class="status-badge online">Online</span>
        </div>
      </div>
      
      <nav class="sidebar-nav">
        <a href="student_dashboard.php" class="nav-item active">
          <i class="fas fa-chart-pie"></i>
          <span>Dashboard</span>
        </a>
        <a href="my_attendance.php" class="nav-item">
          <i class="fas fa-calendar-check"></i>
          <span>My Attendance</span>
        </a>
        <a href="student_timetable.php" class="nav-item">
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
        <div class="nav-divider"></div>
        <div style="display: flex; justify-content: center; align-items: center; gap: 10px;">
          <img src="../images/cihe_logo.png" height="50px" alt="CIHE Logo" class="logo">
          <img src="../images/fullattend_logo.png" height="50px" alt="FullAttend Logo" class="logo">
        </div>
      </nav>
    </aside>

    <!-- Main Dashboard Content -->
    <main class="dashboard-content">
      <!-- Header Section -->
      <header class="dashboard-header">
        <div class="header-content">
          <div class="welcome-section">
            <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $student_name)[0]); ?>! 👋</h1>
            <p class="subtitle">Here's your attendance overview</p>
          </div>
          <div class="header-actions">
            <div class="date-info">
              <i class="fas fa-calendar"></i>
              <span><?php echo date('l, F j, Y'); ?></span>
            </div>
          </div>
        </div>
      </header>

      <!-- Quick Stats Grid -->
      <section class="stats-grid">
        <div class="stat-card attendance-rate">
          <div class="stat-icon">
            <i class="fas fa-chart-line"></i>
          </div>
          <div class="stat-content">
            <h3>Weekly Attendance</h3>
            <div class="stat-value"><?php echo $attendance_percentage; ?>%</div>
            <div class="stat-trend <?php echo $attendance_percentage >= 80 ? 'positive' : 'urgent'; ?>">
              <i class="fas fa-<?php echo $attendance_percentage >= 80 ? 'arrow-up' : 'exclamation-triangle'; ?>"></i>
              <span><?php echo $week_attendance; ?> days this week</span>
            </div>
          </div>
          <div class="stat-progress">
            <div class="progress-bar" style="--progress: <?php echo $attendance_percentage; ?>%"></div>
          </div>
        </div>

        <div class="stat-card classes-today">
          <div class="stat-icon">
            <i class="fas fa-calendar-day"></i>
          </div>
          <div class="stat-content">
            <h3>Today's Attendance</h3>
            <div class="stat-value"><?php echo $today_attendance; ?></div>
            <div class="stat-trend <?php echo $today_attendance > 0 ? 'positive' : ''; ?>">
              <span><?php echo $today_attendance > 0 ? 'Marked present' : 'Not marked yet'; ?></span>
            </div>
          </div>
        </div>

        <div class="stat-card total-records">
          <div class="stat-icon">
            <i class="fas fa-clipboard-check"></i>
          </div>
          <div class="stat-content">
            <h3>Total Records</h3>
            <div class="stat-value"><?php echo $total_records; ?></div>
            <div class="stat-trend">
              <span>Attendance records</span>
            </div>
          </div>
        </div>

        <div class="stat-card enrolled-subjects">
          <div class="stat-icon">
            <i class="fas fa-book-open"></i>
          </div>
          <div class="stat-content">
            <h3>Enrolled Subjects</h3>
            <div class="stat-value"><?php echo $enrolled_subjects; ?></div>
            <div class="stat-trend">
              <span>Subjects you are enrolled in</span>
            </div>
          </div>
        </div>
        
      </section>

      <!-- Quick Actions -->
      <section class="dashboard-card actions-card">
        <div class="card-header">
          <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
        </div>
        <div class="quick-actions">
          <a href="my_attendance.php" class="action-btn primary">
            <i class="fas fa-list"></i>
            <span>View Full Attendance</span>
          </a>
          <a href="settings.php" class="action-btn secondary">
            <i class="fas fa-cog"></i>
            <span>Account Settings</span>
          </a>
        </div>
      </section>

      <!-- Recent Attendance Records -->
      <?php if (!empty($recent_attendance)): ?>
      <section class="dashboard-card recent-attendance">
        <div class="card-header">
          <h2><i class="fas fa-history"></i> Recent Attendance</h2>
          <span class="card-subtitle">Your latest attendance records from face recognition</span>
        </div>
        <div class="attendance-list">
          <?php foreach ($recent_attendance as $record): ?>
          <div class="attendance-item">
            <div class="attendance-student">
              <i class="fas fa-user"></i>
              <span><?php echo htmlspecialchars($record['name'] ?? 'Unknown'); ?></span>
            </div>
            <div class="attendance-id">
              <i class="fas fa-id-card"></i>
              <span>ID: <?php echo htmlspecialchars($record['student_id'] ?? ''); ?></span>
            </div>
            <div class="attendance-status present">
              <i class="fas fa-check-circle"></i>
              <span>Present</span>
            </div>
            <div class="attendance-method">
              <i class="fas fa-camera"></i>
              <span>Face Recognition</span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </section>
      <?php else: ?>
      <section class="dashboard-card recent-attendance">
        <div class="card-header">
          <h2><i class="fas fa-history"></i> Recent Attendance</h2>
          <span class="card-subtitle">Your latest attendance records</span>
        </div>
        <div class="no-attendance-records">
          <i class="fas fa-calendar-times" style="font-size: 48px; color: #ccc; margin-bottom: 20px;"></i>
          <h3>No Attendance Records Found</h3>
          <p>You don't have any attendance records yet. Your attendance will appear here once you mark attendance using face recognition.</p>
          <p><strong>Student ID:</strong> <?php echo htmlspecialchars($student_id); ?> | <strong>Database ID:</strong> <?php echo htmlspecialchars($student_db_id ?? 'Not found'); ?></p>
          <p><em>Note: We're looking for records in attendance_student table with student_id = "<?php echo htmlspecialchars($student_id); ?>"</em></p>
        </div>
      </section>
      <?php endif; ?>
    </main>
  </div>

  <script>
    // Add some interactive functionality
    document.addEventListener('DOMContentLoaded', function() {
      // Animate progress bars
      const progressBars = document.querySelectorAll('.progress-bar');
      progressBars.forEach(bar => {
        const progress = getComputedStyle(bar).getPropertyValue('--progress');
        bar.style.setProperty('--progress', '0%');
        setTimeout(() => {
          bar.style.setProperty('--progress', progress);
        }, 500);
      });
      
      // Add hover effects to cards
      const cards = document.querySelectorAll('.dashboard-card, .stat-card');
      cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
          this.style.transform = 'translateY(-2px)';
        });
        card.addEventListener('mouseleave', function() {
          this.style.transform = 'translateY(0)';
        });
      });
    });
  </script>

  <style>
    .stat-icon.registered {
      background: linear-gradient(135deg, #10b981, #059669);
    }
    
    .stat-icon.pending {
      background: linear-gradient(135deg, #f59e0b, #d97706);
    }
    
    .stat-value.status-registered {
      color: #10b981;
    }
    
    .stat-value.status-pending {
      color: #f59e0b;
    }
    
    .face-registration .stat-trend a {
      text-decoration: none;
      font-weight: 600;
    }
    
    .face-registration .stat-trend a:hover {
      text-decoration: underline;
    }
  </style>

  <style>
    .recent-attendance .attendance-list {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    
    .attendance-item {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr 1fr;
      gap: 15px;
      padding: 15px;
      background: #f8f9fa;
      border-radius: 8px;
      align-items: center;
      transition: all 0.3s ease;
    }
    
    .attendance-item:hover {
      background: #e9ecef;
      transform: translateX(5px);
    }
    
    .attendance-student, .attendance-id, .attendance-status, .attendance-method {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.9em;
    }
    
    .attendance-status.present {
      color: #28a745;
    }
    
    .attendance-status.absent {
      color: #dc3545;
    }
    
    .attendance-method {
      color: #6c757d;
      font-size: 0.8em;
    }
    
    .no-attendance-records {
      text-align: center;
      padding: 40px 20px;
      color: #6c757d;
    }
    
    .no-attendance-records h3 {
      color: #495057;
      margin-bottom: 10px;
    }
    
    .no-attendance-records p {
      margin: 10px 0;
      line-height: 1.5;
    }
    
    @media (max-width: 768px) {
      .attendance-item {
        grid-template-columns: 1fr;
        gap: 8px;
      }
    }
  </style>
</body>
</html>