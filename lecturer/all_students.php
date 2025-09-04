<?php
session_start();
require_once '../config/database.php';

// Check if lecturer is logged in
if (!isset($_SESSION['lecturer_logged_in']) || $_SESSION['lecturer_logged_in'] !== true) {
    header('Location: lecturer_login.php');
    exit();
}

// Get all students

// Get lecturer ID from session

$lecturer_id = $_SESSION['lecturer_id'];

// Get all class IDs taught by this lecturer 
//django tesko manual rakhya ho sab ma url kahi set garya xaina malai djang
// Step 1: Get subject IDs assigned to this lecturer

// Get students enrolled under this lecturer using lecturer_student_enrollments

// Get subject IDs assigned to this lecturer

$students = [];
$lecturer_id = $_SESSION['lecturer_id'];
$enroll_stmt = $pdo->prepare("SELECT s.student_id, s.name, s.email, s.face_encoding, s.status, s.created_at FROM lecturer_student_enrollments lse JOIN students s ON lse.student_id = s.id WHERE lse.lecturer_id = ? ORDER BY s.name ASC");
$enroll_stmt->execute([$lecturer_id]);
$students = $enroll_stmt->fetchAll();

// Get statistics
$total_students = count($students);
$registered_faces = 0;
$active_students = 0;

foreach ($students as $student) {
    if (!empty($student['face_encoding'])) {
        $registered_faces++;
    }
    if ($student['status'] === 'active') {
        $active_students++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Students - Lecturer Panel</title>
    <link rel="stylesheet" href="lecturer_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <h2>LECTURER PANEL</h2>
            <nav>
                <a href="lecturer_dashboard.php">Dashboard</a>
                <a href="face_attendance.php">Face Attendance</a>
                <a href="all_students.php" class="active">All Students</a>
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
                <h1><i class="fas fa-users"></i> All Students</h1>
                <p>View and manage all registered students</p>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid" style="margin-bottom: 30px;">
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $total_students; ?></h3>
                        <p>Total Students</p>
                    </div>
                </div>

                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $active_students; ?></h3>
                        <p>Active Students</p>
                    </div>
                </div>

                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-camera"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $registered_faces; ?></h3>
                        <p>Face Registered</p>
                    </div>
                </div>
            </div>

            <!-- Students Table -->
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-id-card"></i> Student ID</th>
                            <th><i class="fas fa-user"></i> Name</th>
                            <th><i class="fas fa-envelope"></i> Email</th>
                            <!-- <th><i class="fas fa-camera"></i> Face Status</th> -->
                            <th><i class="fas fa-info-circle"></i> Status</th>
                            <th><i class="fas fa-calendar"></i> Registered</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: #666;">
                                    <i class="fas fa-users" style="font-size: 48px; margin-bottom: 15px; display: block;"></i>
                                    No students registered yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($student['student_id']); ?></strong>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center;">
                                            <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; margin-right: 12px;">
                                                <?php echo strtoupper(substr($student['name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div style="font-weight: 600;"><?php echo htmlspecialchars($student['name']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                   
                                    <td>
                                        <span class="status-badge <?php echo $student['status']; ?>">
                                            <?php echo ucfirst($student['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($student['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Quick Actions -->
            <div style="margin-top: 30px; text-align: center;">
                <a href="face_attendance.php" class="btn btn-primary" style="margin-right: 10px;">
                    <i class="fas fa-camera"></i> Take Attendance
                </a>
                <a href="lecturer_dashboard.php" class="btn btn-success">
                    <i class="fas fa-tachometer-alt"></i> Back to Dashboard
                </a>
            </div>
        </main>
    </div>
</body>
</html>
