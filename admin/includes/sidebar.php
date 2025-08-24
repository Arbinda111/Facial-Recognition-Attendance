<?php
// Get current page name for active navigation highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Enhanced Admin Sidebar -->
<aside class="admin-sidebar">
    <div class="sidebar-header">
        <div class="admin-logo">
            <i class="fas fa-user-shield"></i>
            <h2>FULL ATTEND</h2>
        </div>
        <div class="admin-info">
            <div class="admin-avatar">
                <?php echo strtoupper(substr($_SESSION['admin_username'] ?? 'A', 0, 2)); ?>
            </div>
            <div class="admin-details">
                <h4><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Administrator'); ?></h4>
                <p>System Administrator</p>
                <span class="admin-status">Online</span>
            </div>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <a href="admin_dashboard.php" class="nav-item <?php echo ($current_page == 'admin_dashboard.php') ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        <a href="student_directory.php" class="nav-item <?php echo ($current_page == 'student_directory.php') ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Student Directory</span>
        </a>
        <a href="add_student.php" class="nav-item <?php echo ($current_page == 'add_student.php') ? 'active' : ''; ?>">
            <i class="fas fa-user-plus"></i>
            <span>Add Student</span>
        </a>
        <a href="add_lecturer.php" class="nav-item <?php echo ($current_page == 'add_lecturer.php') ? 'active' : ''; ?>">
            <i class="fas fa-chalkboard-teacher"></i>
            <span>Add Lecturer</span>
        </a>
        <a href="class_management.php" class="nav-item <?php echo ($current_page == 'class_management.php') ? 'active' : ''; ?>">
            <i class="fas fa-graduation-cap"></i>
            <span>Class Management</span>
        </a>
        <a href="add_session.php" class="nav-item <?php echo ($current_page == 'add_session.php' || $current_page == 'add_session_new.php') ? 'active' : ''; ?>">
            <i class="fas fa-calendar-plus"></i>
            <span>Add Session</span>
        </a>
        <a href="timetable.php" class="nav-item <?php echo ($current_page == 'timetable.php') ? 'active' : ''; ?>">
            <i class="fas fa-table"></i>
            <span>Timetable</span>
        </a>
        <a href="reports.php" class="nav-item <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>
            <span>Reports</span>
        </a>
        <a href="student_face_registration.php" class="nav-item <?php echo ($current_page == 'student_face_registration.php') ? 'active' : ''; ?>">
            <i class="fas fa-camera"></i>
            <span>Face Registration</span>
        </a>
        <a href="admin_settings.php" class="nav-item <?php echo ($current_page == 'admin_settings.php') ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
        </a>
        <a href="logout.php" class="nav-item logout" style="color: #000000;">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
        <div style="justify-content: center; display: flex; gap: 15px; padding: 15px; background: rgba(255, 255, 255, 0.05); border-radius: 12px;">
            <img src="../images/cihe_logo.png" alt="CIHE" class="footer-logo">
            <img src="../images/fullattend_logo.png" alt="FullAttend" class="footer-logo">
            </div>
    </nav>
   
</aside>
