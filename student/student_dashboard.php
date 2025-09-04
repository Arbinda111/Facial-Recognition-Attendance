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

// Total attendance records
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance WHERE student_id = ?");
$stmt->execute([$student_id]);
$total_records = $stmt->fetch()['count'];

// Today's attendance
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance WHERE student_id = ? AND DATE(attendance_time) = ?");
$stmt->execute([$student_id, $today]);
$today_attendance = $stmt->fetch()['count'];

// This week's attendance
$week_start = date('Y-m-d', strtotime('monday this week'));
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance WHERE student_id = ? AND DATE(attendance_time) >= ?");
$stmt->execute([$student_id, $week_start]);
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

// Recent attendance records
$stmt = $pdo->prepare("
    SELECT DATE(attendance_time) as date, TIME(attendance_time) as time 
    FROM attendance 
    WHERE student_id = ? 
    ORDER BY attendance_time DESC 
    LIMIT 5
");
$stmt->execute([$student_id]);
$recent_attendance = $stmt->fetchAll();
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
          <h3><?php echo htmlspecialchars($student_name); ?> (ID: <?php echo htmlspecialchars($_SESSION['student_db_id']); ?>)</h3>
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
</body>
</html>