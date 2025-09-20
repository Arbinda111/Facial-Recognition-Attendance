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
$lecturer_id = $_SESSION['lecturer_id'];

// Debug: Ensure we have a valid lecturer_id
if (!$lecturer_id) {
    error_log("Warning: No lecturer_id found in session");
    $lecturer_id = 0; // Fallback to prevent SQL errors
}

// Total students enrolled under this lecturer
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM lecturer_student_enrollments lse 
    JOIN students s ON lse.student_id = s.id 
    WHERE lse.lecturer_id = ? AND s.status = 'active'
");
$stmt->execute([$lecturer_id]);
$stats['total_students'] = $stmt->fetch()['count'];

// Today's attendance from attendance_student table for students under this lecturer
// Join with students table to match student_id strings, then filter by lecturer enrollment
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT att.student_id) as count 
    FROM attendance_student att
    JOIN students s ON s.student_id = att.student_id
    JOIN lecturer_student_enrollments lse ON lse.student_id = s.id
    WHERE lse.lecturer_id = ?
    AND s.status = 'active'
");
$stmt->execute([$lecturer_id]);
$stats['today_attendance'] = $stmt->fetch()['count'];

// Debug: Log attendance query results
error_log("Lecturer Dashboard - Lecturer ID: " . $lecturer_id);
error_log("Lecturer Dashboard - Today's attendance count: " . $stats['today_attendance']);

// Debug: Show which students have attendance records under this lecturer
$debug_stmt = $pdo->prepare("
    SELECT DISTINCT att.student_id, att.name, s.student_id as students_table_id
    FROM attendance_student att
    JOIN students s ON s.student_id = att.student_id
    JOIN lecturer_student_enrollments lse ON lse.student_id = s.id
    WHERE lse.lecturer_id = ?
    AND s.status = 'active'
    LIMIT 5
");
$debug_stmt->execute([$lecturer_id]);
$debug_results = $debug_stmt->fetchAll();
foreach ($debug_results as $debug_row) {
    error_log("Lecturer Dashboard - Student with attendance: " . $debug_row['student_id'] . " (Name: " . $debug_row['name'] . ", Students table ID: " . $debug_row['students_table_id'] . ")");
}

// Students with face registered under this lecturer
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM lecturer_student_enrollments lse 
    JOIN students s ON lse.student_id = s.id 
    WHERE lse.lecturer_id = ? 
    AND s.face_encoding IS NOT NULL 
    AND s.face_encoding != ''
");
$stmt->execute([$lecturer_id]);
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
                        <small style="color: #6c757d; font-size: 12px;">Enrolled in your classes</small>
                    </div>
                </div>

                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['today_attendance']; ?></h3>
                        <p>Today's Attendance</p>
                        <small style="color: #6c757d; font-size: 12px;">Students marked present today</small>
                    </div>
                </div>

                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-camera"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['face_registered']; ?></h3>
                        <p>Face Registered</p>
                        <small style="color: #6c757d; font-size: 12px;">Ready for face recognition</small>
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
