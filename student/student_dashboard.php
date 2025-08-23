<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Dashboard - FullAttend</title>
  <link rel="stylesheet" href="../styles.css">
  <link rel="stylesheet" href="student.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="student-portal">
  <div class="dashboard">
    <!-- Enhanced Sidebar -->
    <aside class="sidebar">
      <div class="sidebar-header">
        <div class="student-avatar">
          <img src="https://ui-avatars.com/api/?name=John+Doe&background=6c8ff5&color=fff&size=60" alt="Student Avatar">
        </div>
        <div class="student-info">
          <h3>John Doe</h3>
          <p class="student-id">ID: CIHE240369</p>
          <span class="status-badge online">Online</span>
        </div>
      </div>
      
      <nav class="sidebar-nav">
        <a href="student_dashboard.php" class="nav-item active">
          <i class="fas fa-chart-pie"></i>
          <span>Dashboard</span>
        </a>
        <a href="face_registration.php" class="nav-item">
          <i class="fas fa-user-plus"></i>
          <span>Face Registration</span>
        </a>
        <a href="mark_attendance.php" class="nav-item">
          <i class="fas fa-camera"></i>
          <span>Face Attendance</span>
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
            <h1>Good Morning, John! 👋</h1>
            <p class="subtitle">Here's what's happening with your studies</p>
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
            <h3>Overall Attendance</h3>
            <div class="stat-value">92%</div>
            <div class="stat-trend positive">
              <i class="fas fa-arrow-up"></i>
              <span>+2% from last month</span>
            </div>
          </div>
          <div class="stat-progress">
            <div class="progress-bar" style="--progress: 92%"></div>
          </div>
        </div>

        <div class="stat-card classes-today">
          <div class="stat-icon">
            <i class="fas fa-book-open"></i>
          </div>
          <div class="stat-content">
            <h3>Classes Today</h3>
            <div class="stat-value">4</div>
            <div class="stat-trend">
              <span>2 completed, 2 upcoming</span>
            </div>
          </div>
        </div>

        <div class="stat-card assignments-due">
          <div class="stat-icon">
            <i class="fas fa-tasks"></i>
          </div>
          <div class="stat-content">
            <h3>Assignments Due</h3>
            <div class="stat-value">2</div>
            <div class="stat-trend urgent">
              <i class="fas fa-exclamation-triangle"></i>
              <span>Due this week</span>
            </div>
          </div>
        </div>

        <div class="stat-card gpa">
          <div class="stat-icon">
            <i class="fas fa-trophy"></i>
          </div>
          <div class="stat-content">
            <h3>Current GPA</h3>
            <div class="stat-value">3.8</div>
            <div class="stat-trend positive">
              <i class="fas fa-star"></i>
              <span>Excellent performance</span>
            </div>
          </div>
        </div>
      </section>

      <!-- Main Content Grid -->
      <div class="dashboard-grid">
        <!-- Today's Schedule -->
        <section class="dashboard-card schedule-card">
          <div class="card-header">
            <h2><i class="fas fa-clock"></i> Today's Schedule</h2>
            <a href="student_timetable.php" class="view-all-btn">View All</a>
          </div>
          <div class="schedule-list">
            <div class="schedule-item current">
              <div class="time-slot">
                <span class="time">09:00 - 10:30</span>
                <span class="status live">Live</span>
              </div>
              <div class="class-info">
                <h4>Mobile Application Development</h4>
                <p>Room: Lab L1 • Instructor: Dr. Smith</p>
              </div>
              <button class="join-btn">
                <i class="fas fa-video"></i>
                Join
              </button>
            </div>
            
            <div class="schedule-item upcoming">
              <div class="time-slot">
                <span class="time">11:00 - 12:30</span>
                <span class="status upcoming">Next</span>
              </div>
              <div class="class-info">
                <h4>Business Ethics</h4>
                <p>Room: C3 • Instructor: Prof. Johnson</p>
              </div>
              <div class="countdown">
                <i class="fas fa-clock"></i>
                <span>Starts in 30 min</span>
              </div>
            </div>
            
            <div class="schedule-item">
              <div class="time-slot">
                <span class="time">14:00 - 15:30</span>
                <span class="status scheduled">Scheduled</span>
              </div>
              <div class="class-info">
                <h4>Algorithms & Data Structures</h4>
                <p>Room: B2 • Instructor: Dr. Lee</p>
              </div>
            </div>
          </div>
        </section>

        <!-- Recent Attendance -->
        <section class="dashboard-card attendance-card">
          <div class="card-header">
            <h2><i class="fas fa-calendar-check"></i> Recent Attendance</h2>
            <a href="my_attendance.php" class="view-all-btn">View Details</a>
          </div>
          <div class="attendance-summary">
            <div class="attendance-chart">
              <div class="chart-container">
                <div class="chart-circle" style="--percentage: 92;">
                  <span class="chart-percentage">92%</span>
                </div>
              </div>
              <div class="chart-legend">
                <div class="legend-item">
                  <span class="dot present"></span>
                  <span>Present (56)</span>
                </div>
                <div class="legend-item">
                  <span class="dot late"></span>
                  <span>Late (4)</span>
                </div>
                <div class="legend-item">
                  <span class="dot absent"></span>
                  <span>Absent (3)</span>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- Quick Actions -->
       <!-- <section class="dashboard-card actions-card">
          <div class="card-header">
            <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
          </div>
          <div class="quick-actions">
            <button class="action-btn primary">
              <i class="fas fa-qrcode"></i>
              <span>Scan QR Code</span>
            </button>
            <button class="action-btn secondary">
              <i class="fas fa-download"></i>
              <span>Download Report</span>
            </button>
            <button class="action-btn secondary">
              <i class="fas fa-calendar-plus"></i>
              <span>Request Leave</span>
            </button>
            <button class="action-btn secondary">
              <i class="fas fa-envelope"></i>
              <span>Contact Support</span>
            </button>
          </div>
        </section>
-->
        <!-- Announcements -->
       <!-- <section class="dashboard-card announcements-card">
          <div class="card-header">
            <h2><i class="fas fa-bullhorn"></i> Latest Announcements</h2>
          </div>
          <div class="announcements-list">
            <div class="announcement-item">
              <div class="announcement-icon important">
                <i class="fas fa-exclamation-circle"></i>
              </div>
              <div class="announcement-content">
                <h4>Mid-term Examinations</h4>
                <p>Mid-term exams will begin from next Monday. Please check your exam schedule.</p>
                <span class="announcement-time">2 hours ago</span>
              </div>
            </div>
            <div class="announcement-item">
              <div class="announcement-icon info">
                <i class="fas fa-info-circle"></i>
              </div>
              <div class="announcement-content">
                <h4>Library Hours Extended</h4>
                <p>Library will remain open until 10 PM during exam period.</p>
                <span class="announcement-time">1 day ago</span>
              </div>
            </div>
            <div class="announcement-item">
              <div class="announcement-icon success">
                <i class="fas fa-check-circle"></i>
              </div>
              <div class="announcement-content">
                <h4>New Learning Platform</h4>
                <p>Access to new online learning resources is now available.</p>
                <span class="announcement-time">3 days ago</span>
              </div>
            </div>
          </div>
        </section>-->
      </div>
    </main>
  </div>

  <script>
    // Add some interactive functionality
    document.addEventListener('DOMContentLoaded', function() {
      // Update time every minute
      function updateTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US', { 
          hour12: true, 
          hour: '2-digit', 
          minute: '2-digit' 
        });
        document.querySelector('.current-time')?.textContent = timeString;
      }
      
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
