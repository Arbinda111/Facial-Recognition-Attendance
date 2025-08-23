<?php
session_start();
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit();
}

$session_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$session = null;

if ($session_id > 0) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT 
                s.*,
                c.class_name,
                c.class_code,
                c.instructor_name,
                COUNT(DISTINCT se.student_id) as enrolled_students
            FROM sessions s
            JOIN classes c ON s.class_id = c.id
            LEFT JOIN student_enrollments se ON c.id = se.class_id AND se.status = 'enrolled'
            WHERE s.id = ?
            GROUP BY s.id
        ");
        $stmt->execute([$session_id]);
        $session = $stmt->fetch();
    } catch (PDOException $e) {
        $error_message = 'Error fetching session: ' . $e->getMessage();
    }
}

if (!$session) {
    header('Location: timetable.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Details - <?php echo htmlspecialchars($session['session_name']); ?></title>
    <link rel="stylesheet" href="admin_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="admin-main">
            <header class="dashboard-header">
                <div class="header-left">
                    <a href="timetable.php" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Timetable
                    </a>
                    <h1><?php echo htmlspecialchars($session['session_name']); ?></h1>
                    <p><?php echo htmlspecialchars($session['class_name']); ?> (<?php echo htmlspecialchars($session['class_code']); ?>)</p>
                </div>
                <div class="header-right">
                    <div class="admin-profile">
                        <img src="https://ui-avatars.com/api/?name=Admin&background=6c8ff5&color=fff&size=40" alt="Admin">
                        <span>Admin</span>
                    </div>
                </div>
            </header>

            <div class="content-grid">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-info-circle"></i> Session Information</h3>
                    </div>
                    <div class="session-details">
                        <div class="detail-item">
                            <label>Date:</label>
                            <span><?php echo date('l, F j, Y', strtotime($session['session_date'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <label>Time:</label>
                            <span><?php echo date('g:i A', strtotime($session['start_time'])); ?> - <?php echo date('g:i A', strtotime($session['end_time'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <label>Location:</label>
                            <span><?php echo htmlspecialchars($session['location']); ?></span>
                        </div>
                        <div class="detail-item">
                            <label>Type:</label>
                            <span class="type-badge <?php echo $session['session_type']; ?>"><?php echo ucfirst($session['session_type']); ?></span>
                        </div>
                        <div class="detail-item">
                            <label>Status:</label>
                            <span class="status-badge <?php echo $session['status']; ?>"><?php echo ucfirst($session['status']); ?></span>
                        </div>
                        <div class="detail-item">
                            <label>Instructor:</label>
                            <span><?php echo htmlspecialchars($session['instructor_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <label>Enrolled Students:</label>
                            <span><?php echo $session['enrolled_students']; ?> students</span>
                        </div>
                    </div>
                    <div class="session-actions">
                        <a href="edit_session.php?id=<?php echo $session['id']; ?>" class="btn-primary">
                            <i class="fas fa-edit"></i>
                            Edit Session
                        </a>
                        <a href="attendance.php?session_id=<?php echo $session['id']; ?>" class="btn-success">
                            <i class="fas fa-user-check"></i>
                            Take Attendance
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <style>
        .session-details {
            padding: 1.5rem;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .detail-item:last-child {
            border-bottom: none;
        }
        
        .detail-item label {
            font-weight: 600;
            color: #374151;
        }
        
        .session-actions {
            padding: 1.5rem;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 1rem;
        }
    </style>
</body>
</html>
