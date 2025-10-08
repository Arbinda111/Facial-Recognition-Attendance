<?php
session_start();
require_once('../config/database.php');

// Check if lecturer is logged in
if (!isset($_SESSION['lecturer_logged_in']) || $_SESSION['lecturer_logged_in'] !== true) {
    header('Location: lecturer_login.php');
    exit();
}

// Get lecturer id
$lecturer_id = $_SESSION['lecturer_id'];
$timetable_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$session = null;

if ($timetable_id > 0) {
    try {
        $pdo = getDBConnection();
        
        // Fetch session details including class and subject information
        $stmt = $pdo->prepare("
            SELECT 
                s.*,
                c.class_name,
                c.class_code,
                TIME_FORMAT(s.start_time, '%h:%i %p') as formatted_start_time,
                TIME_FORMAT(s.end_time, '%h:%i %p') as formatted_end_time,
                COUNT(DISTINCT se.student_id) as enrolled_students
            FROM sessions s
            JOIN classes c ON s.class_id = c.id
            LEFT JOIN student_enrollments se ON c.id = se.class_id AND se.status = 'enrolled'
            WHERE s.id = :timetable_id 
            AND c.lecturer_id = :lecturer_id
            GROUP BY s.id, c.class_name, c.class_code, s.start_time, s.end_time
        ");
        
        $stmt->execute([
            ':timetable_id' => $timetable_id,
            ':lecturer_id' => $lecturer_id
        ]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($session) {
            // Get attendance statistics
            $attendance_stmt = $pdo->prepare("
                SELECT 
                    COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
                    COUNT(*) as total_attendance
                FROM attendance 
                WHERE timetable_id = :timetable_id
            ");
            $attendance_stmt->execute([':timetable_id' => $timetable_id]);
            $attendance_stats = $attendance_stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $error_message = 'Error fetching session: ' . $e->getMessage();
    }
}

// Redirect if session not found
if (!$session) {
    header('Location: lecturer_timetable.php');
    exit();
}

$students = [];
$debug_info = [];

try {
    // WORKAROUND QUERY: Try to match by student_id string instead of ID
    $query = "
        SELECT DISTINCT
            ast.id as attendance_student_id,
            ast.name,
            ast.student_id as display_student_id,
            CASE 
                WHEN aar.status IS NOT NULL THEN aar.status
                ELSE 'absent'
            END as attendance_status,
            aar.timestamp
        FROM lecturer_student_enrollments lse
        JOIN students s ON lse.student_id = s.id
        JOIN attendance_student ast ON s.student_id = ast.student_id
        LEFT JOIN attendance_attendancerecord aar ON (
            aar.student_id = ast.id
            AND DATE(aar.timestamp) = DATE(:session_date)
            AND aar.timestamp = (
                SELECT MAX(aar2.timestamp) 
                FROM attendance_attendancerecord aar2 
                WHERE aar2.student_id = ast.id 
                AND DATE(aar2.timestamp) = DATE(:session_date)
            )
        )
        WHERE lse.lecturer_id = :lecturer_id
        ORDER BY ast.name
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':lecturer_id' => $lecturer_id,
        ':session_date' => $session['session_date']
    ]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $debug_info['approach1_count'] = count($students);
    
    // If still no students, try direct approach with attendance_student table
    if (empty($students)) {
        $query_direct = "
            SELECT DISTINCT
                ast.id as attendance_student_id,
                ast.name,
                ast.student_id as display_student_id,
                CASE 
                    WHEN aar.status IS NOT NULL THEN aar.status
                    ELSE 'absent'
                END as attendance_status,
                aar.timestamp
            FROM attendance_student ast
            LEFT JOIN attendance_attendancerecord aar ON (
                aar.student_id = ast.id
                AND DATE(aar.timestamp) = DATE(:session_date)
                AND aar.timestamp = (
                    SELECT MAX(aar2.timestamp) 
                    FROM attendance_attendancerecord aar2 
                    WHERE aar2.student_id = ast.id 
                    AND DATE(aar2.timestamp) = DATE(:session_date)
                )
            )
            ORDER BY ast.name
        ";
        
        $stmt = $pdo->prepare($query_direct);
        $stmt->execute([':session_date' => $session['session_date']]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $debug_info['approach2_count'] = count($students);
    }
    
    // Debug information
    $debug_info['students_found'] = count($students);
    $debug_info['session_date'] = $session['session_date'];
    $debug_info['lecturer_id'] = $lecturer_id;
    
    // Check what enrollments exist for this lecturer
    $debug_enrollments = $pdo->prepare("
        SELECT lse.id, lse.student_id, s.student_id as main_student_id, s.name as main_name, ast.name as att_name, ast.student_id as att_student_id
        FROM lecturer_student_enrollments lse
        LEFT JOIN students s ON lse.student_id = s.id
        LEFT JOIN attendance_student ast ON s.student_id = ast.student_id
        WHERE lse.lecturer_id = :lecturer_id
    ");
    $debug_enrollments->execute([':lecturer_id' => $lecturer_id]);
    $enrollments_data = $debug_enrollments->fetchAll(PDO::FETCH_ASSOC);
    $debug_info['enrollments'] = $enrollments_data;
    
    // Check attendance records for session date
    $debug_attendance = $pdo->prepare("
        SELECT aar.student_id, ast.name, aar.status, aar.timestamp
        FROM attendance_attendancerecord aar
        LEFT JOIN attendance_student ast ON aar.student_id = ast.id
        WHERE DATE(aar.timestamp) = DATE(:session_date)
        ORDER BY aar.timestamp DESC
    ");
    $debug_attendance->execute([':session_date' => $session['session_date']]);
    $attendance_data = $debug_attendance->fetchAll(PDO::FETCH_ASSOC);
    $debug_info['attendance_records'] = $attendance_data;
    
    // Additional debug: Check all tables for reference
    $debug_tables = [
        'students_count' => "SELECT COUNT(*) as count FROM students",
        'attendance_student_count' => "SELECT COUNT(*) as count FROM attendance_student",
        'lecturer_enrollments_count' => "SELECT COUNT(*) as count FROM lecturer_student_enrollments WHERE lecturer_id = :lecturer_id"
    ];
    
    foreach ($debug_tables as $key => $query) {
        $stmt = $pdo->prepare($query);
        if ($key === 'lecturer_enrollments_count') {
            $stmt->execute([':lecturer_id' => $lecturer_id]);
        } else {
            $stmt->execute();
        }
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $debug_info[$key] = $result['count'];
    }

} catch (PDOException $e) {
    $debug_info['error'] = $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Details - Full Attend</title>
    <link rel="stylesheet" href="lecturer_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .dashboard { display: flex; min-height: 100vh; }
        .main-content { flex: 1; padding: 20px; background: #f3f4f6; }
        .dashboard-header { margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: flex-start; }
        .header-left h1 { margin: 0; font-size: 1.8rem; color: #1f2937; }
        .header-left p { margin: 0.5rem 0; color: #6b7280; }
        .btn-secondary { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; background: #fff; border: 1px solid #d1d5db; border-radius: 0.375rem; color: #374151; text-decoration: none; font-size: 0.875rem; margin-bottom: 1rem; }
        .btn-secondary:hover { background: #f9fafb; }
        .content-grid { display: grid; gap: 1.5rem; }
        .card { background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; }
        .card-header { padding: 1.25rem; background: #f8fafc; border-bottom: 1px solid #e5e7eb; }
        .card-header h3 { margin: 0; font-size: 1.25rem; color: #1f2937; display: flex; align-items: center; gap: 0.5rem; }
        .session-details { padding: 1.5rem; }
        .detail-item { display: flex; justify-content: space-between; align-items: center; padding: 1rem; border-bottom: 1px solid #e5e7eb; background: white; }
        .detail-item:last-child { border-bottom: none; }
        .detail-item label { font-weight: 600; color: #374151; font-size: 0.95rem; }
        .detail-item span { color: #6b7280; }
        .status-badge { padding: 0.375rem 1rem; border-radius: 9999px; font-size: 0.875rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.025em; }
        .status-badge.active { background-color: #34d399; color: white; }
        .status-badge.pending { background-color: #fbbf24; color: white; }
        .students-list { padding: 1rem; overflow-x: auto; }
        .students-table { width: 100%; border-collapse: collapse; margin-top: 1rem; background: white; }
        .students-table th, .students-table td { padding: 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .students-table th { background: #f8fafc; font-weight: 600; color: #374151; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; }
        .students-table tr:hover { background: #f8fafc; }
        .status-pill { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; display: inline-block; }
        .status-pill.present { background: #34d399; color: white; }
        .status-pill.absent { background: #ef4444; color: white; }
        .status-pill.late { background: #f59e0b; color: white; }
        .sidebar { width: 250px; background: #e1cfcf; padding: 1.5rem; color: white; }
        .sidebar h2 { font-size: 1.25rem; margin-bottom: 2rem; padding-bottom: 0.5rem; color:black; border-bottom: 1px solid rgba(0, 0, 0, 0.1); }
        .debug-info { background: #fef3cd; border: 1px solid #d4a574; padding: 1rem; margin: 1rem 0; border-radius: 4px; font-size: 0.875rem; }
        .debug-info h4 { margin: 0 0 0.5rem 0; color: #92400e; }
        .debug-section { margin: 0.5rem 0; padding: 0.5rem; background: #f0f9ff; border: 1px solid #bfdbfe; border-radius: 4px; font-size: 0.8rem; }
        .nav a { display: block; padding: 0.75rem 1rem; color: rgba(255,255,255,0.8); text-decoration: none; border-radius: 4px; margin-bottom: 0.5rem; transition: all 0.2s; }
        .nav a:hover, .nav a.active { background: rgba(255,255,255,0.1); color: white; }
        .logos { margin-top: 2rem; }
        .logos img { width: 100%; max-width: 120px; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="dashboard">
        <aside class="sidebar">
            <h2>LECTURER PANEL</h2>
            <nav class="nav">
                <a href="lecturer_dashboard.php">Dashboard</a>
                <a href="face_attendance.php">Face Attendance</a>
                <a href="all_students.php">All Students</a>
                <a href="lecturer_timetable.php" class="active">Timetable</a>
                <a href="lecturer_logout.php">Logout</a>
            </nav>
            <div class="logos">
                <img src="../images/cihe_logo.png" alt="CIHE Logo">
                <img src="../images/fullattend_logo.png" alt="FullAttend Logo">
            </div>
        </aside>
        
        <div class="main-content">
            <header class="dashboard-header">
                <div class="header-left">
                    <a href="lecturer_timetable.php" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Timetable
                    </a>
                    <h1><?php echo htmlspecialchars($session['session_name']); ?></h1>
                    <p><?php echo htmlspecialchars($session['class_name']); ?> 
                       (<?php echo htmlspecialchars($session['class_code']); ?>)</p>
                </div>
            </header>

            

            <div class="content-grid">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-info-circle"></i> Session Information</h3>
                    </div>
                    <div class="session-details">
                        <div class="detail-item">
                            <label><i class="fas fa-tag"></i> Session Name:</label>
                            <span><?php echo htmlspecialchars($session['session_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <label><i class="fas fa-graduation-cap"></i> Class:</label>
                            <span><?php echo htmlspecialchars($session['class_name']); ?> 
                                  (<?php echo htmlspecialchars($session['class_code']); ?>)</span>
                        </div>
                        <div class="detail-item">
                            <label><i class="fas fa-calendar-alt"></i> Date:</label>
                            <span><?php echo date('l, F j, Y', strtotime($session['session_date'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <label><i class="fas fa-clock"></i> Time:</label>
                            <span><?php echo $session['formatted_start_time']; ?> - <?php echo $session['formatted_end_time']; ?></span>
                        </div>
                        <div class="detail-item">
                            <label><i class="fas fa-map-marker-alt"></i> Location:</label>
                            <span><?php echo htmlspecialchars($session['location'] ?? 'Not specified'); ?></span>
                        </div>
                        <!-- <div class="detail-item">
                            <label><i class="fas fa-info-circle"></i> Status:</label>
                            <span class="status-badge <?php echo strtolower($session['status']); ?>">
                                <?php echo ucfirst($session['status']); ?>
                            </span>
                        </div> -->
                    </div>

                    <!-- Students List -->
                    <div class="card-header">
                        <h3><i class="fas fa-users"></i> Student Attendance List</h3>
                    </div>
                    <div class="students-list">
                        <?php if (empty($students)): ?>
                            <div style="padding: 2rem; text-align: center; color: #6b7280;">
                                <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                                <h3>No students found</h3>
                                <p>Unable to find students for this session.</p>
                                <p><small>Check the debug information above to identify the issue with data relationships.</small></p>
                            </div>
                        <?php else: ?>
                        <table class="students-table">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-id-card"></i> Student ID</th>
                                    <th><i class="fas fa-user"></i> Name</th>
                                    <th><i class="fas fa-check-circle"></i> Status</th>
                                    <th><i class="fas fa-clock"></i> Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($student['display_student_id']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                                        <td>
                                            <span class="status-pill <?php echo strtolower($student['attendance_status']); ?>">
                                                <?php echo ucfirst($student['attendance_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($student['timestamp']): ?>
                                                <?php echo date('h:i A', strtotime($student['timestamp'])); ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto refresh the page every 30 seconds to keep attendance status updated
        setTimeout(function() {
            window.location.reload();
        }, 30000);
    </script>
</body>
</html>