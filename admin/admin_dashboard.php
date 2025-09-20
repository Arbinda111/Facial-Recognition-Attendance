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

// Get real-time dashboard data
try {
    // Total students count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM students WHERE status = 'active'");
    $total_students = $stmt->fetch()['total'] ?? 0;
    
    // Today's attendance - include both attendance tables
    $today = date('Y-m-d');
    
    // Get attendance from traditional attendance table
    $stmt = $pdo->prepare("SELECT 
        COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.student_id END) as present_today,
        COUNT(DISTINCT CASE WHEN a.status = 'absent' THEN a.student_id END) as absent_today
        FROM attendance a 
        WHERE DATE(a.attendance_time) = ?");
    $stmt->execute([$today]);
    $attendance_data = $stmt->fetch();
    $present_traditional = $attendance_data['present_today'] ?? 0;
    $absent_traditional = $attendance_data['absent_today'] ?? 0;
    
    // Get attendance from face recognition table
    $stmt = $pdo->prepare("SELECT 
        COUNT(DISTINCT CASE WHEN ar.status = 'Present' THEN ar.student_id END) as present_today,
        COUNT(DISTINCT CASE WHEN ar.status = 'Absent' THEN ar.student_id END) as absent_today
        FROM attendance_attendancerecord ar 
        WHERE DATE(ar.timestamp) = ?");
    $stmt->execute([$today]);
    $face_attendance_data = $stmt->fetch();
    $present_face = $face_attendance_data['present_today'] ?? 0;
    $absent_face = $face_attendance_data['absent_today'] ?? 0;
    
    // Combine both sources (avoiding double counting by using DISTINCT student_id)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT student_id) as present_count
        FROM (
            SELECT student_id FROM attendance 
            WHERE DATE(attendance_time) = ? AND status = 'present'
            UNION
            SELECT student_id FROM attendance_attendancerecord 
            WHERE DATE(timestamp) = ? AND status = 'Present'
        ) as combined_present
    ");
    $stmt->execute([$today, $today]);
    $present_today = $stmt->fetch()['present_count'] ?? 0;
    
    // Active sessions count - sessions running RIGHT NOW
    $current_time = date('H:i:s');
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as active 
        FROM sessions 
        WHERE session_date = ? 
        AND start_time <= ? 
        AND end_time >= ? 
        AND status IN ('scheduled', 'ongoing')
    ");
    $stmt->execute([$today, $current_time, $current_time]);
    $active_sessions = $stmt->fetch()['active'] ?? 0;
    
    // Total classes count (using classes table instead of lecturers)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM classes WHERE status = 'active'");
    $total_classes = $stmt->fetch()['total'] ?? 0;
    
    // Calculate attendance rate based on total students
    $attendance_rate = ($total_students > 0) 
        ? round(($present_today / $total_students) * 100, 1) 
        : 0;
    
    // Calculate absent students as those not present today
    $absent_today = $total_students - $present_today;
    
    // Recent activities - simplified queries
    $recent_activities = [];
    
    // Get recent students
    $stmt = $pdo->query("SELECT 'Student Registration' as action, CONCAT('New student: ', name, ' (', student_id, ')') as details, created_at as time FROM students ORDER BY created_at DESC LIMIT 3");
    $student_activities = $stmt->fetchAll();
    
    // Get recent sessions
    $stmt = $pdo->query("SELECT 'Session Created' as action, CONCAT('Session: ', session_name) as details, created_at as time FROM sessions ORDER BY created_at DESC LIMIT 3");
    $session_activities = $stmt->fetchAll();
    
    // Get recent face recognition attendance
    $stmt = $pdo->prepare("
        SELECT 
            'Face Attendance' as action, 
            CONCAT('Student ID ', ar.student_id, ' marked ', ar.status, ' via face recognition') as details, 
            ar.timestamp as time 
        FROM attendance_attendancerecord ar 
        JOIN students s ON ar.student_id = s.id
        WHERE DATE(ar.timestamp) = ?
        ORDER BY ar.timestamp DESC 
        LIMIT 2
    ");
    $stmt->execute([$today]);
    $face_attendance_activities = $stmt->fetchAll();
    
    // Merge activities
    $recent_activities = array_merge($student_activities, $session_activities, $face_attendance_activities);
    
    // Sort by time
    usort($recent_activities, function($a, $b) {
        return strtotime($b['time']) - strtotime($a['time']);
    });
    
    // Limit to 6 activities
    $recent_activities = array_slice($recent_activities, 0, 6);
    
    // Class-wise statistics - simplified query
    $stmt = $pdo->query("
        SELECT 
            c.class_name as name,
            c.class_code,
            COUNT(DISTINCT se.student_id) as students,
            0 as present,
            0 as absent,
            0 as percentage
        FROM classes c
        LEFT JOIN student_enrollments se ON c.id = se.class_id AND se.status = 'enrolled'
        WHERE c.status = 'active'
        GROUP BY c.id, c.class_name, c.class_code
        ORDER BY c.class_name
        LIMIT 5
    ");
    $classes = $stmt->fetchAll();
    
    // For each class, get today's attendance if available
    foreach ($classes as &$class) {
        $class['percentage'] = 85; // Default percentage for display
    }
    
} catch (PDOException $e) {
    // Fallback to sample data if database error
    $total_students = 0;
    $present_today = 0;
    $absent_today = 0;
    $active_sessions = 0;
    $total_classes = 0;
    $attendance_rate = 0;
    $recent_activities = [];
    $classes = [];
    $error_message = "Database connection error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Full Attend</title>
    <link rel="stylesheet" href="admin_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="admin-main">
            <!-- Header -->
            <header class="dashboard-header">
                <div class="header-left">
                    <h1>Dashboard Overview</h1>
                    <p>Welcome back, <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Administrator'); ?>!</p>
                    
                </div>
                <div class="header-right">
                    <div class="date-info">
                        <i class="fas fa-calendar"></i>
                        <span><?php echo date('l, F j, Y'); ?></span>
                    </div>
                    
                </div>
            </header>
            
            <!-- Statistics Cards -->
            <section class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($total_students); ?></h3>
                        <p>Total Students</p>
                        <div class="stat-trend">
                            <i class="fas fa-arrow-up"></i>
                            <span>Active</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($present_today); ?></h3>
                        <p>Present Today</p>
                        <div class="stat-trend">
                            <i class="fas fa-percentage"></i>
                            <span><?php echo $attendance_rate; ?>%</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-user-times"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($absent_today); ?></h3>
                        <p>Absent Today</p>
                        <div class="stat-trend">
                            <i class="fas fa-calendar-day"></i>
                            <span>Today</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card secondary">
                    <div class="stat-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($total_classes); ?></h3>
                        <p>Total Classes</p>
                        <div class="stat-trend">
                            <i class="fas fa-book"></i>
                            <span>Active</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card purple">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $attendance_rate; ?>%</h3>
                        <p>Attendance Rate</p>
                        <div class="stat-trend">
                            <i class="fas fa-trending-up"></i>
                            <span>Today</span>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- Main Content Grid -->
            <div class="content-grid">
                <!-- Class Overview
                <section class="dashboard-card class-overview">
                    <div class="card-header">
                        <h3>Class Overview</h3>
                        <a href="class_management.php" class="btn-link">View All</a>
                    </div>
                    <div class="class-list">
                        <?php if (empty($classes)): ?>
                            <div class="empty-state">
                                <i class="fas fa-graduation-cap"></i>
                                <p>No active classes found</p>
                                <a href="add_session.php" class="btn btn-primary">Add Session</a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($classes as $class): ?>
                            <div class="class-item">
                                <div class="class-info">
                                    <h4><?php echo htmlspecialchars($class['name']); ?></h4>
                                    <p><?php echo $class['students']; ?> students enrolled</p>
                                </div>
                                <div class="attendance-info">
                                    <div class="attendance-circle">
                                        <svg class="circle-progress" viewBox="0 0 36 36">
                                            <circle cx="18" cy="18" r="16" fill="none" stroke="#e2e8f0" stroke-width="3"/>
                                            <circle cx="18" cy="18" r="16" fill="none" stroke="#10b981" stroke-width="3"
                                                    stroke-dasharray="<?php echo $class['percentage']; ?>, 100" 
                                                    stroke-dashoffset="25" stroke-linecap="round"/>
                                        </svg>
                                        <span class="percentage"><?php echo $class['percentage']; ?>%</span>
                                    </div>
                                    <div class="attendance-details">
                                        <span class="present"><?php echo $class['present']; ?> Present</span>
                                        <span class="absent"><?php echo $class['absent']; ?> Absent</span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section> -->

                            <!-- Quick Actions -->
            <section class="quick-actions-section">
                <h3>Quick Actions</h3>
                <div class="action-grid">
                    <a href="add_student.php" class="action-card">
                        <i class="fas fa-user-plus"></i>
                        <span>Add Student</span>
                    </a>
                    <a href="add_lecturer.php" class="action-card">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <span>Add Lecturer</span>
                    </a>
                    <a href="add_session.php" class="action-card">
                        <i class="fas fa-calendar-plus"></i>
                        <span>Start Session</span>
                    </a>
                    <a href="reports.php" class="action-card">
                        <i class="fas fa-download"></i>
                        <span>Generate Report</span>
                    </a>
                </div>
            </section>
                
                <!-- Recent Activities -->
                <section class="dashboard-card recent-activities">
                    <div class="card-header">
                        <h3>Recent Activities</h3>
                        <span class="card-subtitle">Latest system activities</span>
                    </div>
                    <div class="activity-list">
                        <?php if (empty($recent_activities)): ?>
                            <div class="empty-state">
                                <i class="fas fa-clock"></i>
                                <p>No recent activities</p>
                            </div>
                        <?php else: ?>
                            <?php foreach (array_slice($recent_activities, 0, 6) as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-circle"></i>
                                </div>
                                <div class="activity-content">
                                    <h4><?php echo htmlspecialchars($activity['action']); ?></h4>
                                    <p><?php echo htmlspecialchars($activity['details']); ?></p>
                                    <span class="time"><?php echo date('g:i A', strtotime($activity['time'])); ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
            
            <!-- Quick Actions
            <section class="quick-actions-section">
                <h3>Quick Actions</h3>
                <div class="action-grid">
                    <a href="add_student.php" class="action-card">
                        <i class="fas fa-user-plus"></i>
                        <span>Add Student</span>
                    </a>
                    <a href="add_lecturer.php" class="action-card">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <span>Add Lecturer</span>
                    </a>
                    <a href="add_session.php" class="action-card">
                        <i class="fas fa-calendar-plus"></i>
                        <span>Start Session</span>
                    </a>
                    <a href="reports.php" class="action-card">
                        <i class="fas fa-download"></i>
                        <span>Generate Report</span>
                    </a>
                </div>
            </section> -->
        </main>
    </div>
</body>
</html>
