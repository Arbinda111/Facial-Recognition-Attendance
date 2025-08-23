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
          <div class="timetable-controls">
            <span class="current-week">Mon 12 – Fri 16 May 2025</span>
          </div>
        </div>

        <!-- Calendar grid -->
        <div class="timetable-container">
          <table class="timetable-grid">
            <thead>
              <tr>
                <th class="time-column">Time</th>
                <th>Monday</th>
                <th>Tuesday</th>
              <th>Wednesday</th>
              <th>Thursday</th>
              <th>Friday</th>
            </tr>
          </thead>
          <tbody>
            <!-- 09:00 -->
            <tr>
              <td><strong>09:00–10:00</strong></td>
              <td>
                <div class="tt-session">
                  <div class="tt-title">Project 1</div>
                  <div class="muted tt-meta">Class 101 • Room A1</div>
                </div>
              </td>
              <td></td>
              <td>
                <div class="tt-session">
                  <div class="tt-title">Algorithms & DS</div>
                  <div class="muted tt-meta">Class 104 • Room B2</div>
                </div>
              </td>
              <td></td>
              <td>
                <div class="tt-session">
                  <div class="tt-title">Business Ethics</div>
                  <div class="muted tt-meta">Class 103 • Room C3</div>
                </div>
              </td>
            </tr>

            <!-- 10:00 -->
            <tr>
              <td><strong>10:00–11:00</strong></td>
              <td>
                <div class="tt-session">
                  <div class="tt-title">Mobile Application</div>
                  <div class="muted tt-meta">Class 102 • Lab L1</div>
                </div>
              </td>
              <td>
                <div class="tt-session">
                  <div class="tt-title">Project 1</div>
                  <div class="muted tt-meta">Class 101 • Room A1</div>
                </div>
              </td>
              <td></td>
              <td>
                <div class="tt-session">
                  <div class="tt-title">Tutorial</div>
                  <div class="muted tt-meta">Class 101 • Room T2</div>
                </div>
              </td>
              <td></td>
            </tr>

            <!-- 11:00 -->
            <tr>
              <td><strong>11:00–12:00</strong></td>
              <td></td>
              <td>
                <div class="tt-session">
                  <div class="tt-title">Algorithms & DS</div>
                  <div class="muted tt-meta">Class 104 • Room B2</div>
                </div>
              </td>
              <td></td>
              <td></td>
              <td>
                <div class="tt-session">
                  <div class="tt-title">Project 1 – Lab</div>
                  <div class="muted tt-meta">Class 101 • Lab L1</div>
                </div>
              </td>
            </tr>

            <!-- 13:00 -->
            <tr>
              <td><strong>13:00–14:00</strong></td>
              <td>
                <div class="tt-session">
                  <div class="tt-title">Business Ethics</div>
                  <div class="muted tt-meta">Class 103 • Room C3</div>
                </div>
              </td>
              <td></td>
              <td>
                <div class="tt-session">
                  <div class="tt-title">Mobile Application</div>
                  <div class="muted tt-meta">Class 102 • Lab L1</div>
                </div>
              </td>
              <td></td>
              <td></td>
            </tr>

            <!-- 14:00 -->
            <tr>
              <td><strong>14:00–15:00</strong></td>
              <td></td>
              <td>
                <div class="tt-session">
                  <div class="tt-title">Consultation</div>
                  <div class="muted tt-meta">Class 101 • Office</div>
                </div>
              </td>
              <td></td>
              <td>
                <div class="tt-session">
                  <div class="tt-title">Algorithms & DS – Lab</div>
                  <div class="muted tt-meta">Class 104 • Lab L2</div>
                </div>
              </td>
              <td></td>
            </tr>
          </tbody>
        </table>
      </section>

      <!-- Legend -->
      <div class="legend" style="justify-content:flex-start; margin-top:10px;">
        <div class="legend-item"><span class="status-dot present"></span> Lecture</div>
        <div class="legend-item"><span class="status-dot late"></span> Tutorial</div>
        <div class="legend-item"><span class="status-dot absent"></span> Lab</div>
      </div>
    </main>
  </div>
</body>
</html>
