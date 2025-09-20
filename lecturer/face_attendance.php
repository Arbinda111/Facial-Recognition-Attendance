<?php
session_start();
require_once '../config/database.php';

// Check if lecturer is logged in
if (!isset($_SESSION['lecturer_logged_in']) || $_SESSION['lecturer_logged_in'] !== true) {
    header('Location: lecturer_login.php');
    exit();
}

$lecturer_id = $_SESSION['lecturer_id'];
$lecturer_name = $_SESSION['lecturer_name'];

// Get current time and date for session detection
$current_time_24hr = date('H:i:s');
$current_time_12hr = date('g:i A');
$current_datetime = date('Y-m-d H:i:s');
$today = date('Y-m-d');

// Debug: Log current time information
error_log("DEBUG - Current time info:");
error_log("  24hr format: $current_time_24hr");
error_log("  12hr format: $current_time_12hr");
error_log("  Full datetime: $current_datetime");
error_log("  Today: $today");

// Fetch sessions for this lecturer
$sessions = [];
$selected_session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : null;
$pdo = getDBConnection();

// Step 1: First, end any sessions that have passed their end time
// Convert current time to ensure proper comparison
$current_timestamp = time();
$current_time_for_comparison = date('H:i:s');

$end_expired_stmt = $pdo->prepare("
    UPDATE sessions s
    JOIN classes c ON s.class_id = c.id 
    SET s.status = 'completed'
    WHERE c.lecturer_id = ?
    AND s.session_date = ?
    AND s.status IN ('ongoing', 'active')
    AND TIME(s.end_time) < ?
");
$end_expired_stmt->execute([$lecturer_id, $today, $current_time_for_comparison]);
$ended_sessions = $end_expired_stmt->rowCount();

// Also check and end sessions using more explicit time comparison
$explicit_end_stmt = $pdo->prepare("
    UPDATE sessions s
    JOIN classes c ON s.class_id = c.id 
    SET s.status = 'completed'
    WHERE c.lecturer_id = ?
    AND s.session_date = ?
    AND s.status IN ('ongoing', 'active')
    AND CONCAT(s.session_date, ' ', s.end_time) < NOW()
");
$explicit_end_stmt->execute([$lecturer_id, $today]);
$additional_ended = $explicit_end_stmt->rowCount();

$total_ended = $ended_sessions + $additional_ended;

// Debug: Log ended sessions
if ($total_ended > 0) {
    error_log("DEBUG - Ended $total_ended expired sessions at $current_time_24hr");
}

// Step 2: Check for currently active session (within time range and not expired)
$active_session = null;

// Get all sessions for today with detailed time analysis
$all_sessions_stmt = $pdo->prepare("
    SELECT s.id, s.session_name, s.session_date, s.start_time, s.end_time, c.class_name, s.status,
           c.lecturer_id as session_lecturer_id,
           TIME(s.start_time) as start_time_only, 
           TIME(s.end_time) as end_time_only,
           DATE_FORMAT(s.start_time, '%l:%i %p') as start_12hr,
           DATE_FORMAT(s.end_time, '%l:%i %p') as end_12hr,
           CASE 
               WHEN TIME(s.start_time) <= TIME(?) AND TIME(s.end_time) >= TIME(?) THEN 'ACTIVE_TIME'
               WHEN TIME(s.start_time) > TIME(?) THEN 'FUTURE' 
               WHEN TIME(s.end_time) < TIME(?) THEN 'EXPIRED'
               ELSE 'UNKNOWN'
           END as time_status
    FROM sessions s 
    JOIN classes c ON s.class_id = c.id 
    WHERE c.lecturer_id = ? AND s.session_date = ?
    ORDER BY s.start_time ASC
");
$all_sessions_stmt->execute([
    $current_time_24hr, $current_time_24hr,  // for ACTIVE_TIME check
    $current_time_24hr,                      // for FUTURE check
    $current_time_24hr,                      // for EXPIRED check
    $lecturer_id, $today
]);
$all_sessions = $all_sessions_stmt->fetchAll();

// Debug: Log all sessions and their time status
error_log("DEBUG - All sessions for today:");
foreach ($all_sessions as $debug_sess) {
    error_log("  Session: {$debug_sess['session_name']} | Status: {$debug_sess['status']} | Time Status: {$debug_sess['time_status']}");
    error_log("    Start: {$debug_sess['start_time']} (TIME: {$debug_sess['start_time_only']})");
    error_log("    End: {$debug_sess['end_time']} (TIME: {$debug_sess['end_time_only']})");
    error_log("    Current time: $current_time_24hr");
    error_log("    Time comparison: {$debug_sess['start_time_only']} <= $current_time_24hr && {$debug_sess['end_time_only']} >= $current_time_24hr");
    
    // Manual time comparison for debugging
    $start_seconds = strtotime("1970-01-01 " . $debug_sess['start_time_only']);
    $end_seconds = strtotime("1970-01-01 " . $debug_sess['end_time_only']);
    $current_seconds = strtotime("1970-01-01 " . $current_time_24hr);
    
    $is_active_manual = ($start_seconds <= $current_seconds && $end_seconds >= $current_seconds);
    error_log("    Manual check result: " . ($is_active_manual ? "ACTIVE" : "NOT ACTIVE"));
}

foreach ($all_sessions as $sess) {
    // Manual PHP-based time check in addition to SQL check
    $start_seconds = strtotime("1970-01-01 " . $sess['start_time_only']);
    $end_seconds = strtotime("1970-01-01 " . $sess['end_time_only']);
    $current_seconds = strtotime("1970-01-01 " . $current_time_24hr);
    
    $is_active_manual = ($start_seconds <= $current_seconds && $end_seconds >= $current_seconds);
    $is_valid_status = in_array($sess['status'], ['scheduled', 'ongoing', 'active', null, '', 'pending']);
    
    // Use either SQL time_status or manual PHP check
    if (($sess['time_status'] === 'ACTIVE_TIME' || $is_active_manual) && $is_valid_status) {
        $active_session = $sess;
        
        // Update session status to ongoing if it's not already
        if ($sess['status'] !== 'ongoing') {
            $update_stmt = $pdo->prepare("UPDATE sessions SET status = 'ongoing' WHERE id = ?");
            $update_stmt->execute([$sess['id']]);
            $active_session['status'] = 'ongoing';
            error_log("DEBUG - Updated session {$sess['session_name']} from status '{$sess['status']}' to 'ongoing'" . ($is_active_manual && $sess['time_status'] !== 'ACTIVE_TIME' ? " (via PHP manual check)" : ""));
        }
        break; // Use the first active session found
    }
    
    // Force end any sessions that should be expired
    if ($sess['time_status'] === 'EXPIRED' && in_array($sess['status'], ['ongoing', 'active'])) {
        $force_end_stmt = $pdo->prepare("UPDATE sessions SET status = 'completed' WHERE id = ?");
        $force_end_stmt->execute([$sess['id']]);
        error_log("DEBUG - Force ended expired session: " . $sess['session_name'] . " (ended at " . $sess['end_time'] . ")");
    }
}

// Additional fallback check for sessions that might be missed by TIME() comparison
if (!$active_session) {
    error_log("DEBUG - No active session found with TIME() comparison, trying fallback approach...");
    
    // Try a different approach with datetime comparison
    $fallback_stmt = $pdo->prepare("
        SELECT s.id, s.session_name, s.session_date, s.start_time, s.end_time, c.class_name, s.status,
               c.lecturer_id as session_lecturer_id,
               TIME(s.start_time) as start_time_only, 
               TIME(s.end_time) as end_time_only,
               DATE_FORMAT(s.start_time, '%l:%i %p') as start_12hr,
               DATE_FORMAT(s.end_time, '%l:%i %p') as end_12hr
        FROM sessions s 
        JOIN classes c ON s.class_id = c.id 
        WHERE c.lecturer_id = ? 
        AND s.session_date = ? 
        AND s.status NOT IN ('completed', 'cancelled')
        AND (
            (CONCAT(s.session_date, ' ', TIME(s.start_time)) <= NOW() 
             AND CONCAT(s.session_date, ' ', TIME(s.end_time)) >= NOW())
            OR 
            (TIME(s.start_time) <= ? AND TIME(s.end_time) >= ?)
        )
        ORDER BY s.start_time ASC
        LIMIT 1
    ");
    
    $fallback_stmt->execute([$lecturer_id, $today, $current_time_24hr, $current_time_24hr]);
    $fallback_session = $fallback_stmt->fetch();
    
    if ($fallback_session) {
        $active_session = $fallback_session;
        
        // Update session status to ongoing
        if ($fallback_session['status'] !== 'ongoing') {
            $update_stmt = $pdo->prepare("UPDATE sessions SET status = 'ongoing' WHERE id = ?");
            $update_stmt->execute([$fallback_session['id']]);
            $active_session['status'] = 'ongoing';
            error_log("DEBUG - Fallback found and activated session: {$fallback_session['session_name']}");
        }
    }
}

// Debug: Log active session detection with detailed time comparison
if ($active_session) {
    error_log("DEBUG - Active session found: " . $active_session['session_name']);
    error_log("DEBUG - Session times: " . $active_session['start_time'] . " to " . $active_session['end_time']);
    error_log("DEBUG - Current time: $current_time_24hr");
} else {
    error_log("DEBUG - No active session found. Current time: $current_time_24hr");
    
    // Additional check: Force end any remaining ongoing sessions that are expired
    $cleanup_stmt = $pdo->prepare("
        SELECT s.id, s.session_name, s.end_time, TIME(s.end_time) as end_time_only
        FROM sessions s 
        JOIN classes c ON s.class_id = c.id 
        WHERE c.lecturer_id = ? 
        AND s.session_date = ? 
        AND s.status IN ('ongoing', 'active')
    ");
    $cleanup_stmt->execute([$lecturer_id, $today]);
    $ongoing_sessions = $cleanup_stmt->fetchAll();
    
    foreach ($ongoing_sessions as $ongoing) {
        if ($ongoing['end_time_only'] < $current_time_24hr) {
            $force_cleanup_stmt = $pdo->prepare("UPDATE sessions SET status = 'completed' WHERE id = ?");
            $force_cleanup_stmt->execute([$ongoing['id']]);
            error_log("DEBUG - Force cleanup expired session: {$ongoing['session_name']} (ended at {$ongoing['end_time']})");
        }
    }
}

// Fetch all sessions for today (for manual selection when no active session)
$stmt = $pdo->prepare("
    SELECT s.id, s.session_name, s.session_date, s.start_time, s.end_time, c.class_name, s.status
    FROM sessions s 
    JOIN classes c ON s.class_id = c.id 
    WHERE c.lecturer_id = ? 
    AND s.session_date = ? 
    AND s.status NOT IN ('completed', 'cancelled')
    ORDER BY s.start_time ASC
");
$stmt->execute([$lecturer_id, $today]);
$sessions = $stmt->fetchAll();

// Debug: Log all sessions regardless of status
error_log("DEBUG - All sessions for manual selection:");
foreach ($sessions as $debug_manual_sess) {
    error_log("  Manual Session: {$debug_manual_sess['session_name']} | Status: {$debug_manual_sess['status']}");
    error_log("    Time: {$debug_manual_sess['start_time']} - {$debug_manual_sess['end_time']}");
}

// Handle session start
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_session']) && $selected_session_id) {
    $update = $pdo->prepare("UPDATE sessions SET status = 'ongoing' WHERE id = ?");
    $update->execute([$selected_session_id]);
    
    // Refresh active session after manual start
    $active_stmt = $pdo->prepare("
        SELECT s.id, s.session_name, s.session_date, s.start_time, s.end_time, c.class_name 
        FROM sessions s 
        JOIN classes c ON s.class_id = c.id 
        WHERE s.id = ? AND c.lecturer_id = ?
    ");
    $active_stmt->execute([$selected_session_id, $lecturer_id]);
    $active_session = $active_stmt->fetch();
}

// Check if there is an ongoing session for this lecturer (legacy check for already started sessions)
$ongoing_session = null;
$ongoing_stmt = $pdo->prepare("SELECT s.id, s.session_name, s.session_date, s.start_time, s.end_time, c.class_name FROM sessions s JOIN classes c ON s.class_id = c.id WHERE c.lecturer_id = ? AND s.status = 'ongoing' AND s.session_date = CURDATE() ORDER BY s.start_time ASC LIMIT 1");
$ongoing_stmt->execute([$lecturer_id]);
$ongoing_session = $ongoing_stmt->fetch();

// Use active session or ongoing session (prefer active session)
$current_session = $active_session ?: $ongoing_session;

// Handle AJAX request to check for active sessions
if (isset($_GET['check_active_session']) && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    // Re-run the active session detection logic
    $check_time = date('H:i:s');
    
    $ajax_active_stmt = $pdo->prepare("
        SELECT s.id, s.session_name, s.session_date, s.start_time, s.end_time, c.class_name, s.status,
               c.lecturer_id as session_lecturer_id,
               TIME(s.start_time) as start_time_only, 
               TIME(s.end_time) as end_time_only,
               CASE 
                   WHEN TIME(s.start_time) <= TIME(?) AND TIME(s.end_time) >= TIME(?) THEN 'ACTIVE_TIME'
                   WHEN TIME(s.start_time) > TIME(?) THEN 'FUTURE' 
                   WHEN TIME(s.end_time) < TIME(?) THEN 'EXPIRED'
                   ELSE 'UNKNOWN'
               END as time_status
        FROM sessions s 
        JOIN classes c ON s.class_id = c.id 
        WHERE c.lecturer_id = ? AND s.session_date = ?
        ORDER BY s.start_time ASC
    ");
    
    $ajax_active_stmt->execute([
        $check_time, $check_time,  // for ACTIVE_TIME check
        $check_time,               // for FUTURE check
        $check_time,               // for EXPIRED check
        $lecturer_id, $today
    ]);
    
    $ajax_sessions = $ajax_active_stmt->fetchAll();
    $ajax_active_session = null;
    
    foreach ($ajax_sessions as $sess) {
        // Manual PHP-based time check in addition to SQL check
        $start_seconds = strtotime("1970-01-01 " . $sess['start_time_only']);
        $end_seconds = strtotime("1970-01-01 " . $sess['end_time_only']);
        $current_seconds = strtotime("1970-01-01 " . $check_time);
        
        $is_active_manual = ($start_seconds <= $current_seconds && $end_seconds >= $current_seconds);
        $is_valid_status = in_array($sess['status'], ['scheduled', 'ongoing', 'active', null, '', 'pending']);
        
        // Use either SQL time_status or manual PHP check
        if (($sess['time_status'] === 'ACTIVE_TIME' || $is_active_manual) && $is_valid_status) {
            $ajax_active_session = $sess;
            
            // Update session status to ongoing if it's not already
            if ($sess['status'] !== 'ongoing') {
                $ajax_update_stmt = $pdo->prepare("UPDATE sessions SET status = 'ongoing' WHERE id = ?");
                $ajax_update_stmt->execute([$sess['id']]);
                $ajax_active_session['status'] = 'ongoing';
                error_log("DEBUG - AJAX Updated session {$sess['session_name']} from status '{$sess['status']}' to 'ongoing'" . ($is_active_manual && $sess['time_status'] !== 'ACTIVE_TIME' ? " (via PHP manual check)" : ""));
            }
            break;
        }
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    if ($ajax_active_session) {
        echo json_encode([
            'has_active_session' => true,
            'session_id' => $ajax_active_session['id'],
            'session_name' => $ajax_active_session['session_name'],
            'class_name' => $ajax_active_session['class_name'],
            'start_time' => $ajax_active_session['start_time'],
            'end_time' => $ajax_active_session['end_time']
        ]);
    } else {
        echo json_encode(['has_active_session' => false]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Face Attendance - Lecturer Panel</title>
    <link rel="stylesheet" href="lecturer_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .attendance-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .attendance-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .header-section {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            color: white;
        }
        
        .mode-toggle {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .toggle-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-weight: 600;
        }
        
        .toggle-label input {
            margin-right: 10px;
        }
        
        .capture-section {
            margin: 30px 0;
        }
        
        .start-capture {
            text-align: center;
            padding: 40px;
            border: 2px dashed #ddd;
            border-radius: 15px;
            background: #f8f9fa;
        }
        
        .start-camera-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin: 10px;
            transition: transform 0.3s;
        }
        
        .start-camera-btn:hover {
            transform: translateY(-2px);
        }
        
        .camera-section {
            text-align: center;
            padding: 20px;
            border-radius: 15px;
            background: #f8f9fa;
        }
        
        .webcam-container {
            position: relative;
            display: inline-block;
            border-radius: 15px;
            overflow: hidden;
            margin: 20px 0;
        }
        
        .webcam {
            border-radius: 15px;
            max-width: 100%;
            height: auto;
        }
        
        .face-guide {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            pointer-events: none;
        }
        
        .face-oval {
            width: 200px;
            height: 250px;
            border: 3px solid rgba(255,255,255,0.8);
            border-radius: 50%;
            box-shadow: 0 0 20px rgba(0,0,0,0.3);
            animation: pulse-border 2s infinite;
        }
        
        @keyframes pulse-border {
            0% { border-color: rgba(255,255,255,0.8); }
            50% { border-color: rgba(102,126,234,0.8); }
            100% { border-color: rgba(255,255,255,0.8); }
        }
        
        .auto-capture-controls {
            margin: 20px 0;
        }
        
        .auto-status {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin: 15px 0;
            border: 1px solid #e9ecef;
        }
        
        .auto-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
        }
        
        .pulse-dot {
            width: 12px;
            height: 12px;
            background: #28a745;
            border-radius: 50%;
            margin-right: 10px;
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .auto-starting {
            animation: pulse 1s infinite;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%) !important;
            color: white !important;
        }
        
        .countdown-indicator {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #28a745;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .camera-controls {
            margin: 20px 0;
        }
        
        .capture-btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin: 10px;
            transition: transform 0.3s;
        }
        
        .capture-btn:hover {
            transform: translateY(-2px);
        }
        
        .cancel-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin: 10px;
            transition: transform 0.3s;
        }
        
        .cancel-btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            margin: 10px;
        }
        
        .message {
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            font-weight: 600;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .message.info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .attendance-success, .test-results {
            background: white;
            padding: 25px;
            border-radius: 15px;
            border: 1px solid #e9ecef;
            margin: 20px 0;
        }
        
        .student-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 15px 0;
        }
        
        .next-student-actions {
            animation: slideInUp 0.5s ease-out;
        }
        
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .next-student-actions button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <h2>LECTURER PANEL</h2>
            <nav>
                <a href="lecturer_dashboard.php">Dashboard</a>
                <a href="face_attendance.php" class="active">Face Attendance</a>
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
            <div class="attendance-container">
                <div class="attendance-card">
                    <div class="header-section">
                        <h2><i class="fas fa-camera"></i> Student Face Attendance</h2>
                        <p>Take attendance using facial recognition for all students</p>
                        <p><strong>Lecturer:</strong> <?php echo htmlspecialchars($lecturer_name); ?></p>
                        
                        <!-- Active Session Display -->
                        <?php if ($active_session): ?>
                            <div class="message success">
                                <i class="fas fa-clock"></i>
                                <strong>Active Session Now:</strong> <?php echo htmlspecialchars($active_session['session_name']); ?> 
                                (<?php echo htmlspecialchars($active_session['class_name']); ?>)
                                <br>
                                <small>
                                    <i class="fas fa-calendar"></i> <?php echo date('M d, Y'); ?> | 
                                    <i class="fas fa-clock"></i> <?php echo date('g:i A', strtotime($active_session['start_time'])) . ' - ' . date('g:i A', strtotime($active_session['end_time'])); ?>
                                </small>
                            </div>
                        <?php elseif ($ongoing_session): ?>
                            <div class="message success">Ongoing Session: <strong><?php echo htmlspecialchars($ongoing_session['session_name']); ?></strong> (<?php echo htmlspecialchars($ongoing_session['class_name']); ?>) <?php echo date('g:i A', strtotime($ongoing_session['start_time'])) . ' - ' . date('g:i A', strtotime($ongoing_session['end_time'])); ?></div>
                        <?php else: ?>
                            <!-- Manual Session Selection -->
                            <div class="message info">
                                <i class="fas fa-info-circle"></i>
                                <strong>No Active Session:</strong> No session is currently scheduled for this time.
                                <br>
                                <small>You can manually start any session from today's schedule below.</small>
                                <div id="sessionCheckStatus" style="margin-top: 10px; font-size: 12px; color: #666;">
                                    <i class="fas fa-clock"></i> Checking for active sessions...
                                </div>
                            </div>
                            
                            <form method="post" style="margin: 20px 0;">
                                <label for="session_id"><strong>Select Session to Start:</strong></label>
                                <select name="session_id" id="session_id" required style="padding: 8px; border-radius: 6px; margin-right: 10px;">
                                    <option value="">--Select Session--</option>
                                    <?php foreach ($sessions as $session): ?>
                                        <option value="<?php echo $session['id']; ?>">
                                            <?php echo htmlspecialchars($session['session_name']) . ' (' . htmlspecialchars($session['class_name']) . ') ' . date('g:i A', strtotime($session['start_time'])) . ' - ' . date('g:i A', strtotime($session['end_time'])); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="start_session" class="start-camera-btn"><i class="fas fa-play"></i> Start Session</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <?php if ($current_session): ?>
                        <!-- Hidden test mode toggle - functionality preserved for backend -->
                        <div class="mode-toggle" style="display: none;">
                        <label class="toggle-label">
                            <input type="checkbox" id="testMode">
                            <span class="toggle-slider"></span>
                            Test Mode (Detailed Analysis)
                        </label>
                    </div>
                    
                    <div class="capture-section">
                        <?php if ($current_session): ?>
                            <!-- Skip start button interface - go directly to camera for active/ongoing session -->
                            <div id="startCapture" class="start-capture" style="display: none;">
                                <!-- Hidden - will be bypassed for active/ongoing sessions -->
                            </div>
                            
                            <!-- Camera section - shown directly for active/ongoing session -->
                            <div id="autoCaptureSection" class="camera-section">
                                <div class="camera-instructions">
                                    <h3><i class="fas fa-magic"></i> Live Attendance - Current Session</h3>
                                    <p>Taking attendance for your current session</p>
                                    <div style="background: #d4edda; padding: 15px; border-radius: 8px; margin: 15px 0; border: 1px solid #c3e6cb;">
                                        <strong><i class="fas fa-clock"></i> Current Session:</strong> <?php echo htmlspecialchars($current_session['session_name']); ?> 
                                        (<?php echo htmlspecialchars($current_session['class_name']); ?>)<br>
                                        <small><i class="fas fa-calendar"></i> <?php echo date('M d, Y'); ?> | 
                                        <i class="fas fa-clock"></i> <?php echo date('g:i A', strtotime($current_session['start_time'])) . ' - ' . date('g:i A', strtotime($current_session['end_time'])); ?></small>
                                    </div>
                                </div>

                                <div class="webcam-container">
                                    <video id="autoWebcam" class="webcam" autoplay playsinline></video>
                                    <div class="face-guide">
                                        <div class="face-oval"></div>
                                    </div>
                                </div>

                                <div class="auto-capture-controls">
                                    <div class="auto-status">
                                        <div class="auto-indicator">
                                            <div class="pulse-dot"></div>
                                            <span>Auto-capturing...</span>
                                        </div>
                                        <div class="auto-message">
                                            <div id="autoMessage">📹 Initializing camera for live attendance...</div>
                                        </div>
                                    </div>
                                    <button onclick="stopAutoCapture()" class="btn-danger">
                                        <i class="fas fa-stop"></i> Stop Attendance
                                    </button>
                                    <button onclick="endCurrentSession()" class="btn-warning" style="margin-left: 10px;">
                                        <i class="fas fa-times-circle"></i> End Session
                                    </button>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Manual camera for when no session is active -->
                            <div id="startCapture" class="start-capture">
                                <h3><i class="fas fa-camera"></i> Ready to Take Attendance?</h3>
                                <p class="instruction">Start the attendance system for students</p>
                                <button id="startBtn" onclick="startAutoCapture()" class="start-camera-btn" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                                    <i class="fas fa-magic"></i> Start Auto-Attendance
                                </button>
                                <div id="attendanceInstructions" style="margin-top: 15px; padding: 15px; background: #e8f4f8; border-radius: 10px; font-size: 14px;">
                                    <i class="fas fa-info-circle"></i> <strong>How it works:</strong><br>
                                    • Click "Start Auto-Attendance" to begin<br>
                                    • System will automatically detect and confirm one student<br>
                                    • Auto-attendance stops after each confirmation<br>
                                    • Click the button again for the next student
                                </div>
                            </div>
                            
                            <!-- Camera section - hidden initially for manual mode -->
                            <div id="autoCaptureSection" class="camera-section" style="display: none;">
                                <div class="camera-instructions">
                                    <h3><i class="fas fa-magic"></i> Auto-Attendance Active</h3>
                                    <p>System is automatically capturing and recognizing students</p>
                                </div>

                                <div class="webcam-container">
                                    <video id="autoWebcam" class="webcam" autoplay playsinline></video>
                                    <div class="face-guide">
                                        <div class="face-oval"></div>
                                    </div>
                                </div>

                                <div class="auto-capture-controls">
                                    <div class="auto-status">
                                        <div class="auto-indicator">
                                            <div class="pulse-dot"></div>
                                            <span>Auto-capturing...</span>
                                        </div>
                                        <div class="auto-message">
                                            <div id="autoMessage">🚀 Starting attendance system...</div>
                                        </div>
                                    </div>
                                    <button onclick="stopAutoCapture()" class="btn-danger">
                                        <i class="fas fa-stop"></i> Stop Attendance
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div id="messageArea"></div>
                    <div id="resultArea"></div>
                </div>
            </div>
    </main>
    </div>

    <script>
        let stream = null;
        let isAutoCapturing = false;
        let autoInterval = null;
        let captureCount = 0;
        let selectedSessionId = null;
        
        // Current session data from PHP
        const activeSession = <?php echo json_encode($active_session); ?>;
        const currentSession = <?php echo json_encode($current_session); ?>;
        
        // Debug: Log session and time information
        console.log('Session detection debug:');
        console.log('- Current PHP time:', '<?php echo $current_time_24hr; ?>');
        console.log('- Today:', '<?php echo $today; ?>');
        console.log('- Active session:', activeSession);
        console.log('- Current session:', currentSession);
        
        // Set up periodic session check to handle session transitions
        let sessionCheckInterval = null;
        let newSessionCheckInterval = null;
        
        function checkSessionStatus() {
            // Only check if we're in an active session
            if (currentSession) {
                const now = new Date();
                const currentTime = now.getHours().toString().padStart(2, '0') + ':' + 
                                  now.getMinutes().toString().padStart(2, '0') + ':' + 
                                  now.getSeconds().toString().padStart(2, '0');
                
                console.log('Checking session status at:', currentTime, 'Session ends at:', currentSession.end_time);
                
                // Check if current session should have ended
                if (currentSession.end_time) {
                    // Extract time part from end_time (handles both "22:00:00" and "2023-09-19 22:00:00" formats)
                    let endTimeStr = currentSession.end_time;
                    if (endTimeStr.includes(' ')) {
                        endTimeStr = endTimeStr.split(' ')[1];
                    }
                    
                    // Convert to comparable format
                    const endTime = endTimeStr.substring(0, 8); // Get HH:MM:SS part
                    
                    console.log('Comparing:', currentTime, '>', endTime);
                    
                    if (currentTime > endTime) {
                        console.log('Session expired at', endTime, ', current time is', currentTime, '- refreshing page...');
                        // Force end the session before refreshing
                        fetch('end_session.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ session_id: currentSession.id })
                        }).then(() => {
                            location.reload();
                        }).catch(() => {
                            location.reload(); // Refresh anyway
                        });
                    }
                }
            }
        }
        
        // Function to check for newly active sessions when no current session exists
        function checkForNewActiveSessions() {
            // Only check if there's no current session active
            if (!currentSession) {
                console.log('Checking for newly active sessions...');
                
                const statusElement = document.getElementById('sessionCheckStatus');
                if (statusElement) {
                    statusElement.innerHTML = '<i class="fas fa-sync fa-spin"></i> Checking for active sessions...';
                }
                
                fetch(window.location.href + '?check_active_session=1', {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.has_active_session) {
                        console.log('New active session detected:', data.session_name);
                        if (statusElement) {
                            statusElement.innerHTML = '<i class="fas fa-check-circle" style="color: green;"></i> Active session found! Loading...';
                        }
                        console.log('Refreshing page to load active session...');
                        location.reload();
                    } else {
                        if (statusElement) {
                            const now = new Date();
                            statusElement.innerHTML = `<i class="fas fa-clock"></i> Last checked: ${now.toLocaleTimeString()} - No active sessions`;
                        }
                    }
                })
                .catch(error => {
                    console.error('Error checking for active sessions:', error);
                    if (statusElement) {
                        statusElement.innerHTML = '<i class="fas fa-exclamation-triangle" style="color: orange;"></i> Error checking sessions';
                    }
                });
            }
        }
        
        // Start session monitoring every 10 seconds for more responsive ending
        if (currentSession) {
            sessionCheckInterval = setInterval(checkSessionStatus, 10000);
        } else {
            // If no current session, check for newly active sessions every 15 seconds
            newSessionCheckInterval = setInterval(checkForNewActiveSessions, 15000);
            
            // Also do an immediate check after 5 seconds in case a session just became active
            setTimeout(checkForNewActiveSessions, 5000);
        }
        
        // Auto-start camera when page loads if there's an active session
        document.addEventListener('DOMContentLoaded', function() {
            if (currentSession) {
                // Directly show camera interface for current session (skip start button completely)
                console.log('Current session detected - directly opening camera:', currentSession.session_name);
                
                // Show camera section immediately and hide start section
                const startCaptureDiv = document.getElementById('startCapture');
                const autoCaptureSection = document.getElementById('autoCaptureSection');
                
                if (startCaptureDiv) {
                    startCaptureDiv.style.display = 'none'; // Hide start interface completely
                }
                if (autoCaptureSection) {
                    autoCaptureSection.style.display = 'block'; // Show camera directly
                }
                
                // Start camera immediately for current session
                setTimeout(function() {
                    startDirectCamera();
                }, 500); // Small delay just to let the page fully load
            } else {
                console.log('No current session - showing manual interface');
            }
        });

        async function startDirectCamera() {
            try {
                console.log('Starting camera directly for current session...');
                
                // Update status message
                const autoMessage = document.getElementById('autoMessage');
                if (autoMessage) {
                    autoMessage.innerHTML = '📹 Initializing camera for current session...';
                }
                
                // Access camera directly
                const stream = await navigator.mediaDevices.getUserMedia({ 
                    video: { width: 640, height: 480 } 
                });
                
                const video = document.getElementById('autoWebcam');
                if (video) {
                    video.srcObject = stream;
                    
                    video.onloadedmetadata = () => {
                        console.log('Camera ready for direct attendance');
                        if (autoMessage) {
                            autoMessage.innerHTML = '✅ Camera ready - Position students to mark attendance for <strong>' + currentSession.session_name + '</strong>';
                        }
                        
                        // Start the auto-capture functionality
                        startAutoCaptureProcess();
                    };
                }
                
            } catch (error) {
                console.error('Error accessing camera:', error);
                const autoMessage = document.getElementById('autoMessage');
                if (autoMessage) {
                    autoMessage.innerHTML = '❌ Camera access failed. Please allow camera permissions and refresh the page.';
                }
            }
        }

        function startAutoCaptureProcess() {
            if (!isAutoCapturing) {
                isAutoCapturing = true;
                captureCount = 0;
                console.log('Starting auto-capture process for current session');
                
                // Set session for attendance
                selectedSessionId = currentSession.id;
                
                // Start auto-capture every 3 seconds (same as manual mode)
                autoInterval = setInterval(autoCapture, 3000);
                
                // Update the status to show it's ready
                const autoMessage = document.getElementById('autoMessage');
                if (autoMessage) {
                    autoMessage.innerHTML = `✅ Camera ready for <strong>${currentSession.session_name}</strong> - System will auto-detect students...`;
                }
            }
        }

        async function startAutoCapture() {
            try {
                // Reset and disable start button when starting
                const startBtn = document.getElementById('startBtn');
                if (startBtn) {
                    startBtn.innerHTML = '<i class="fas fa-magic"></i> Camera Starting...';
                    startBtn.disabled = true;
                }
                
                stream = await navigator.mediaDevices.getUserMedia({ 
                    video: { 
                        width: 640, 
                        height: 480, 
                        facingMode: 'user' 
                    } 
                });
                
                document.getElementById('startCapture').style.display = 'none';
                document.getElementById('autoCaptureSection').style.display = 'block';
                document.getElementById('autoWebcam').srcObject = stream;
                
                isAutoCapturing = true;
                captureCount = 0;
                
                // Show immediate ready message for auto-started sessions
                if (activeSession) {
                    document.getElementById('autoMessage').textContent = 
                        `✅ Camera ready for ${activeSession.session_name} - System will auto-detect students...`;
                } else {
                    document.getElementById('autoMessage').textContent = '🚀 Starting attendance system...';
                }
                
                // Start auto-capture every 3 seconds
                autoInterval = setInterval(autoCapture, 3000);
                
            } catch (error) {
                alert('Failed to access camera: ' + error.message);
                const startBtn = document.getElementById('startBtn');
                if (startBtn) { 
                    startBtn.disabled = false; 
                    startBtn.innerHTML = '<i class="fas fa-magic"></i> Start Auto-Attendance'; 
                }
            }
        }

        function stopAutoCapture(skipShowStart = false) {
            if (autoInterval) {
                clearInterval(autoInterval);
                autoInterval = null;
            }
            
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
            
            isAutoCapturing = false;
            
            if (!skipShowStart) {
                document.getElementById('autoCaptureSection').style.display = 'none';
                document.getElementById('startCapture').style.display = 'block';
                
                // Update the start button text for next student and enable it
                const startBtn = document.getElementById('startBtn');
                if (startBtn) { startBtn.innerHTML = '<i class="fas fa-magic"></i> Start Attendance for Next Student'; startBtn.disabled = false; }
                
                // Reset auto message
                document.getElementById('autoMessage').textContent = '⏹️ Ready for next student attendance.';
            }
        }

        // Function to pause auto-capture (stops interval but keeps camera running)
        function pauseAutoCapture() {
            if (autoInterval) {
                clearInterval(autoInterval);
                autoInterval = null;
            }
            isAutoCapturing = false;
            console.log('Auto-capture paused - camera still running for next student');
        }

        // Function to resume auto-capture with existing camera stream
        function resumeAutoCapture() {
            if (!isAutoCapturing) {
                isAutoCapturing = true;
                captureCount = 0;
                console.log('Resuming auto-capture with existing camera...');
                
                // Update status message
                const autoMessage = document.getElementById('autoMessage');
                if (autoMessage) {
                    autoMessage.innerHTML = currentSession ? 
                        `✅ Camera ready for <strong>${currentSession.session_name}</strong> - System will auto-detect students...` :
                        '🚀 Starting attendance system...';
                }
                
                // Start auto-capture every 3 seconds
                autoInterval = setInterval(autoCapture, 3000);
            }
        }

        async function autoCapture() {
            if (!isAutoCapturing) return;
            
            const video = document.getElementById('autoWebcam');
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0);
            
            const imageData = canvas.toDataURL('image/jpeg');
            captureCount++;
            
            document.getElementById('autoMessage').textContent = 
                `🔄 Capture ${captureCount} - Checking for students...`;
            
            try {
                const blob = dataURLtoBlob(imageData);
                const formData = new FormData();
                formData.append('photo', blob, `attendance-${Date.now()}.jpg`);
                formData.append('lecturer_id', '<?php echo $lecturer_id; ?>');
                formData.append('is_test', document.getElementById('testMode').checked ? '1' : '0');
                
                const response = await fetch('http://localhost:8000/api/mark-attendance/', {
                    method: 'POST',
                    body: formData
                });
                

                const result = await response.json();
                // Debug: log API response so developer can inspect shape in browser console
                console.log('mark-attendance API response:', result);

                // Normalize possible payload shapes: { student_id, student_name, confidence, timestamp, message }
                let payload = result;
                if (result && result.data) payload = result.data;

                // Some APIs return fields under different keys; try a few common ones
                const student = {
                    student_id: payload && (payload.student_id || payload.id || payload.s_id || payload.studentId) ,
                    student_name: payload && (payload.student_name || payload.name || payload.studentName),
                    confidence: payload && (payload.confidence || payload.score || payload.conf),
                    timestamp: payload && (payload.timestamp || payload.time || payload.t)
                };

                const messageText = (payload && payload.message) || (result && result.message) || '';

                const hasStudentData = student.student_id || student.student_name || (/marked|already/i.test(messageText));

                if (hasStudentData) {
                    // Fill fallback values
                    if (!student.timestamp) student.timestamp = new Date().toISOString();
                    if (!student.confidence) student.confidence = 0;

                    // Prepare a unified result object
                    const unified = {
                        student_id: student.student_id || '',
                        student_name: student.student_name || 'Unknown Student',
                        confidence: parseFloat(student.confidence) || 0,
                        timestamp: student.timestamp,
                        message: messageText
                    };

                    // Validate if student is enrolled under current lecturer
                    console.log('Starting student validation for:', unified.student_id);
                    const isValidStudent = await validateStudentEnrollment(unified.student_id);
                    console.log('Validation result:', isValidStudent);
                    
                    if (isValidStudent.valid) {
                        // Student is enrolled under current lecturer - show success
                        document.getElementById('autoMessage').textContent = 
                            `✅ ${unified.message || 'Attendance processed for ' + isValidStudent.student_name}`;

                        // Update unified object with validated student name
                        unified.student_name = isValidStudent.student_name;
                        unified.internal_student_id = isValidStudent.internal_student_id;

                        showAttendanceResult(unified);

                        if (!document.getElementById('testMode').checked && unified.student_id) {
                            saveAttendanceToDatabase(unified);
                        }

                        // Pause auto-capture when student is detected (don't stop camera)
                        pauseAutoCapture();
                        
                        // Update status to show attendance completed
                        const autoMessage = document.getElementById('autoMessage');
                        if (autoMessage) {
                            autoMessage.innerHTML = `✅ Attendance recorded for <strong>${isValidStudent.student_name}</strong> - Use buttons below to continue`;
                        }
                    } else {
                        // Student not enrolled under current lecturer - don't show success
                        document.getElementById('autoMessage').textContent = 
                            `❌ Capture ${captureCount}: Student not enrolled in your classes - Continuing...`;
                        console.log('Student validation failed:', isValidStudent.reason);
                    }
                } else {
                    document.getElementById('autoMessage').textContent = `❌ Capture ${captureCount}: ${result.error || result.message || 'No student detected'} - Continuing...`;
                }
            } catch (error) {
                document.getElementById('autoMessage').textContent = 
                    `❌ Capture ${captureCount}: Network error - Continuing...`;
            }
        }
        
        // Manual session ending function
        async function endCurrentSession() {
            if (currentSession && confirm('Are you sure you want to end the current session? This will stop attendance taking.')) {
                try {
                    const response = await fetch('end_session.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ session_id: currentSession.id })
                    });
                    
                    const result = await response.json();
                    if (result.success) {
                        alert('Session ended successfully: ' + result.session_name);
                        location.reload();
                    } else {
                        alert('Failed to end session: ' + result.message);
                    }
                } catch (error) {
                    alert('Error ending session: ' + error.message);
                }
            }
        }

        async function validateStudentEnrollment(studentId) {
            try {
                console.log('Validating student enrollment for student_id:', studentId);
                
                const response = await fetch('validate_student_enrollment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        student_id: studentId,
                        lecturer_id: '<?php echo $lecturer_id; ?>'
                    })
                });

                if (!response.ok) {
                    console.error('Failed to validate student enrollment');
                    return { valid: false, reason: 'Network error' };
                }

                const result = await response.json();
                console.log('Student validation result:', result);
                
                return result;
                
            } catch (error) {
                console.error('Error validating student enrollment:', error);
                return { valid: false, reason: 'Validation error: ' + error.message };
            }
        }

        function dataURLtoBlob(dataURL) {
            const arr = dataURL.split(',');
            const mime = arr[0].match(/:(.*?);/)[1];
            const bstr = atob(arr[1]);
            let n = bstr.length;
            const u8arr = new Uint8Array(n);
            while (n--) {
                u8arr[n] = bstr.charCodeAt(n);
            }
            return new Blob([u8arr], { type: mime });
        }

        function showAttendanceResult(result) {
            const resultArea = document.getElementById('resultArea');
            const timestamp = new Date(result.timestamp).toLocaleString();
            
            // Determine the status message
            let statusMessage = 'Attendance Marked';
            let statusIcon = 'check-circle';
            let statusColor = '#28a745';
            
            if (result.message && result.message.includes('already marked')) {
                statusMessage = 'Already Marked Today';
                statusIcon = 'info-circle';
                statusColor = '#17a2b8';
            }
            
            const resultHtml = `
                <div class="attendance-success">
                    <h3><i class="fas fa-${statusIcon}" style="color: ${statusColor};"></i> ${statusMessage}</h3>
                    <div class="student-info">
                        <p><strong>Student:</strong> ${result.student_name}</p>
                        <p><strong>Student ID:</strong> ${result.student_id}</p>
                        <p><strong>Confidence:</strong> ${(result.confidence * 100).toFixed(2)}%</p>
                        <p><strong>Time:</strong> ${timestamp}</p>
                        ${result.message ? `<p><strong>Status:</strong> ${result.message}</p>` : ''}
                    </div>
                    <div class="next-student-actions" style="margin-top: 20px; text-align: center; padding: 15px; background: #f8f9fa; border-radius: 10px;">
                        <p style="margin-bottom: 15px; font-weight: 600; color: #495057;">
                            <i class="fas fa-users"></i> Ready for next student?
                        </p>
                       
                        <button onclick="clearAllResults()" class="cancel-btn" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); margin-right: 10px;">
                            <i class="fas fa-broom"></i> Take Attendance for Next Student
                        </button>
                    </div>
                </div>
            `;
            
            resultArea.innerHTML = resultHtml + resultArea.innerHTML;
        }

        async function saveAttendanceToDatabase(result) {
            try {
                const attendanceData = {
                    student_id: result.internal_student_id || result.student_id, // Use internal ID if available
                    enrollment_student_id: result.student_id, // Keep original enrollment number for reference
                    confidence: result.confidence,
                    timestamp: result.timestamp,
                    method: 'face_recognition',
                    lecturer_id: '<?php echo $lecturer_id; ?>'
                };
                
                // Include session ID if there's a current session
                if (currentSession && currentSession.id) {
                    attendanceData.session_id = currentSession.id;
                    console.log('Saving attendance for session:', currentSession.session_name);
                }
                
                console.log('Saving attendance data:', attendanceData);
                
                const response = await fetch('save_attendance.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(attendanceData)
                });
                
                if (!response.ok) {
                    console.error('Failed to save attendance to database');
                } else {
                    const saveResult = await response.json();
                    console.log('Attendance saved successfully:', saveResult);
                }
            } catch (error) {
                console.error('Error saving attendance:', error);
            }
        }

        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }
            if (autoInterval) {
                clearInterval(autoInterval);
            }
            if (sessionCheckInterval) {
                clearInterval(sessionCheckInterval);
            }
            if (newSessionCheckInterval) {
                clearInterval(newSessionCheckInterval);
            }
        });

        // Function to retake attendance for next student
        function retakeAttendanceForNextStudent() {
            console.log('Starting attendance for next student...');
            
            // Hide any error messages and clear status
            const messageArea = document.getElementById('messageArea');
            if (messageArea) messageArea.innerHTML = '';
            
            // Check if camera is still running (video element has a stream)
            const video = document.getElementById('autoWebcam');
            const hasActiveStream = video && video.srcObject && video.srcObject.active;
            
            if (hasActiveStream) {
                // Camera is still running, just resume auto-capture
                console.log('Camera stream active, resuming auto-capture...');
                resumeAutoCapture();
            } else {
                // Camera not running, need to restart
                console.log('No active camera stream, restarting camera...');
                
                // First, stop any current capture without showing start section
                stopAutoCapture(true);
                
                // Small delay to ensure camera is properly stopped
                setTimeout(function() {
                    // If there's a current session, start camera directly
                    if (currentSession) {
                        // Show camera section immediately
                        const startCaptureDiv = document.getElementById('startCapture');
                        const autoCaptureSection = document.getElementById('autoCaptureSection');
                        
                        if (startCaptureDiv) {
                            startCaptureDiv.style.display = 'none';
                        }
                        if (autoCaptureSection) {
                            autoCaptureSection.style.display = 'block';
                        }
                        
                        // Start camera immediately
                        setTimeout(function() {
                            startDirectCamera();
                        }, 300);
                    } else {
                        // No current session, use manual mode
                        startAutoCapture();
                    }
                }, 500);
            }
        }

        // Function to clear all attendance results
        function clearAllResults() {
            const resultArea = document.getElementById('resultArea');
            if (resultArea) {
                resultArea.innerHTML = '';
            }
            
            const messageArea = document.getElementById('messageArea');
            if (messageArea) {
                messageArea.innerHTML = '';
            }
            
            console.log('All results cleared');
        }

        // Add toggle slider styles
        const style = document.createElement('style');
        style.textContent = `
            .toggle-slider {
                position: relative;
                display: inline-block;
                width: 50px;
                height: 24px;
                background-color: #ccc;
                border-radius: 24px;
                margin-right: 10px;
                transition: 0.3s;
            }
            
            .toggle-slider::before {
                content: '';
                position: absolute;
                width: 20px;
                height: 20px;
                border-radius: 50%;
                background-color: white;
                top: 2px;
                left: 2px;
                transition: 0.3s;
            }
            
            input:checked + .toggle-slider {
                background-color: #667eea;
            }
            
            input:checked + .toggle-slider::before {
                transform: translateX(26px);
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
