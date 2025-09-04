<?php
session_start();
require_once '../config/database.php';

// Check if lecturer is logged in
if (!isset($_SESSION['lecturer_logged_in']) || $_SESSION['lecturer_logged_in'] !== true) {
    header('Location: lecturer_login.php');
    exit();
}

// Get dashboard statistics
$stats = [];

// Total students
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM students WHERE status = 'active'");
$stmt->execute();
$stats['total_students'] = $stmt->fetch()['count'];

// Today's attendance
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT student_id) as count FROM attendance WHERE DATE(attendance_time) = ?");
$stmt->execute([$today]);
$stats['today_attendance'] = $stmt->fetch()['count'];

// Students with face registered
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM students WHERE face_encoding IS NOT NULL AND face_encoding != ''");
$stmt->execute();
$stats['face_registered'] = $stmt->fetch()['count'];

// Today's date info
$stats['today_date'] = date('l, F j, Y');
$stats['current_time'] = date('g:i A');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Dashboard - FullAttend</title>
    <link rel="stylesheet" href="lecturer_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <h2>LECTURER PANEL</h2>
            <nav>
                <a href="lecturer_dashboard.php" class="active">Dashboard</a>
                <a href="face_attendance.php">Face Attendance</a>
                <a href="all_students.php">All Students</a>
                <a href="lecturer_timetable.php">Timetable</a>
                <a href="lecturer_logout.php">Logout</a>
            </nav>
            <div class="logos">
                <img src="../images/cihe_logo.png" alt="CIHE Logo">
                <img src="../images/fullattend_logo.png" alt="FullAttend Logo">
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="dashboard-header">
                <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($_SESSION['lecturer_name']); ?>!</p>
            </div>

            <!-- Date & Time Card -->
            <div class="date-time-card">
                <div class="date-info">
                    <h3><?php echo $stats['today_date']; ?></h3>
                    <p class="current-time"><?php echo $stats['current_time']; ?></p>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['total_students']; ?></h3>
                        <p>Total Students</p>
                    </div>
                </div>

                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['today_attendance']; ?></h3>
                        <p>Today's Attendance</p>
                    </div>
                </div>

                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-camera"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['face_registered']; ?></h3>
                        <p>Face Registered</p>
                    </div>
                </div>

                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['total_students'] > 0 ? round(($stats['today_attendance'] / $stats['total_students']) * 100) : 0; ?>%</h3>
                        <p>Attendance Rate</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
                <div class="action-cards">
                    <a href="face_attendance.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-camera"></i>
                        </div>
                        <h3>Take Attendance</h3>
                        <p>Start face recognition attendance</p>
                    </a>

                    <a href="all_students.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3>View Students</h3>
                        <p>See all registered students</p>
                    </a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
