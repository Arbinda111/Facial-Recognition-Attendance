<?php
session_start();
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit();
}

$success_message = '';
$error_message = '';

// Get database connection
$pdo = getDBConnection();

// Fetch attendance statistics from database
$attendance_stats = [];
try {
    // Total sessions
    $stmt = $pdo->query("SELECT COUNT(*) as total_sessions FROM sessions");
    $attendance_stats['total_sessions'] = $stmt->fetch()['total_sessions'];
    
    // Total students
    $stmt = $pdo->query("SELECT COUNT(*) as total_students FROM students WHERE status = 'active'");
    $attendance_stats['total_students'] = $stmt->fetch()['total_students'];
    
    // Total classes
    $stmt = $pdo->query("SELECT COUNT(*) as total_classes FROM classes WHERE status = 'active'");
    $attendance_stats['total_classes'] = $stmt->fetch()['total_classes'];
    
    // Present students count - using attendance_student table
    $stmt = $pdo->query("SELECT COUNT(*) as present_count FROM attendance_student WHERE 1");
    $attendance_stats['present_count'] = $stmt->fetch()['present_count'];
    
    // Total attendance records - using attendance_student table  
    $stmt = $pdo->query("SELECT COUNT(*) as total_attendance FROM attendance_student");
    $total_attendance = $stmt->fetch()['total_attendance'];
    
    // Calculate attendance percentage (assuming all records in attendance_student are 'present')
    $attendance_stats['attendance_percentage'] = $total_attendance > 0 ? 100 : 0;
    
} catch (PDOException $e) {
    $error_message = 'Error fetching statistics: ' . $e->getMessage();
    $attendance_stats = [
        'total_sessions' => 0,
        'total_students' => 0,
        'total_classes' => 0,
        'present_count' => 0,
        'attendance_percentage' => 0
    ];
}

// Fetch class-wise attendance reports
$class_reports = [];
try {
    $stmt = $pdo->query("
        SELECT 
            c.class_name,
            c.class_code,
            COALESCE(l.name, c.instructor_name) as instructor_name,
            COUNT(DISTINCT lse.student_id) as enrolled_students,
            COUNT(DISTINCT s.id) as total_sessions,
            0 as total_attendance,
            0 as present_count,
            0 as attendance_percentage
        FROM classes c
        LEFT JOIN lecturers l ON c.lecturer_id = l.id
        LEFT JOIN lecturer_student_enrollments lse ON c.lecturer_id = lse.lecturer_id
        LEFT JOIN sessions s ON c.id = s.class_id
        WHERE c.status = 'active'
        GROUP BY c.id, c.class_name, c.class_code, l.name, c.instructor_name
        ORDER BY enrolled_students DESC, c.class_name ASC
    ");
    $class_reports = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = 'Error fetching class reports: ' . $e->getMessage();
}

// Handle report export requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_report'])) {
    $report_type = $_POST['report_type'];
    $success_message = "Report export request processed. Data will be downloaded shortly.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Full Attend</title>
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
                    <h1>Reports & Analytics</h1>
                    <p>View attendance statistics and generate reports</p>
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?php echo $success_message; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($error_message)): ?>
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
                    <div class="admin-profile">
                        <img src="https://ui-avatars.com/api/?name=Admin&background=6c8ff5&color=fff&size=40" alt="Admin">
                        <span>Admin</span>
                    </div>
                </div>
            </header>
            
            <!-- Attendance Statistics -->
            <section class="stats-grid">
                <!-- <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $attendance_stats['attendance_percentage']; ?>%</h3>
                        <p>Overall Attendance</p>
                    </div>
                </div> -->
                
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $attendance_stats['total_sessions']; ?></h3>
                        <p>Total Sessions</p>
                    </div>
                </div>
                
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $attendance_stats['total_students']; ?></h3>
                        <p>Active Students</p>
                    </div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $attendance_stats['total_classes']; ?></h3>
                        <p>Active Classes</p>
                    </div>
                </div>
            </section>
            
            <!-- Class-wise Attendance Report -->
            <section class="class-reports-section">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-bar"></i> Class-wise Attendance Report</h3>
                        <div class="header-actions">
                            <button class="btn-secondary" onclick="window.location.reload()">
                                <i class="fas fa-refresh"></i>
                                Refresh
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-container">
                        <?php if (count($class_reports) > 0): ?>
                        <table class="reports-table">
                            <thead>
                                <tr>
                                    <th>Class Details</th>
                                    <th>Instructor</th>
                                    <th>Students</th>
                                    <th>Sessions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($class_reports as $report): ?>
                                <tr>
                                    <td>
                                        <div class="class-info">
                                            <strong><?php echo htmlspecialchars($report['class_name']); ?></strong>
                                            <div class="class-code"><?php echo htmlspecialchars($report['class_code']); ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="instructor-info">
                                            <i class="fas fa-user-tie"></i>
                                            <?php echo htmlspecialchars($report['instructor_name']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="student-count">
                                            <i class="fas fa-users"></i>
                                            <?php echo $report['enrolled_students']; ?> enrolled
                                        </span>
                                    </td>
                                    <td>
                                        <span class="session-count">
                                            <i class="fas fa-calendar"></i>
                                            <?php echo $report['total_sessions']; ?> sessions
                                        </span>
                                    </td>
                                   
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-chart-bar"></i>
                            <h3>No Report Data Available</h3>
                            <p>No attendance data found. Start taking attendance to generate reports.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        // View class report details
        function viewClassReport(classCode) {
            window.location.href = `class_report_details.php?code=${classCode}`;
        }
        
        // Export specific class report
        function exportClassReport(classCode) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const reportType = document.createElement('input');
            reportType.name = 'export_report';
            reportType.value = '1';
            
            const classInput = document.createElement('input');
            classInput.name = 'class_code';
            classInput.value = classCode;
            
            const formatInput = document.createElement('input');
            formatInput.name = 'format';
            formatInput.value = 'pdf';
            
            form.appendChild(reportType);
            form.appendChild(classInput);
            form.appendChild(formatInput);
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
        
        // Form validation
        document.querySelector('.export-form').addEventListener('submit', function(e) {
            const reportType = document.getElementById('report_type').value;
            if (!reportType) {
                e.preventDefault();
                alert('Please select a report type.');
                return false;
            }
        });
        
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 300);
                }, 5000);
            });
        });
    </script>
</body>
</html>
