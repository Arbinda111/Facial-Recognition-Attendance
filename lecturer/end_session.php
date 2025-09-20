<?php
session_start();
require_once '../config/database.php';

// Check if lecturer is logged in
if (!isset($_SESSION['lecturer_logged_in']) || $_SESSION['lecturer_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$session_id = $input['session_id'] ?? '';
$lecturer_id = $_SESSION['lecturer_id'];

if (empty($session_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing session ID']);
    exit();
}

try {
    $pdo = getDBConnection();
    
    // Verify the session belongs to the current lecturer
    $verify_stmt = $pdo->prepare("
        SELECT s.id, s.session_name, s.status 
        FROM sessions s 
        JOIN classes c ON s.class_id = c.id 
        WHERE s.id = ? AND c.lecturer_id = ?
    ");
    $verify_stmt->execute([$session_id, $lecturer_id]);
    $session = $verify_stmt->fetch();
    
    if (!$session) {
        echo json_encode(['success' => false, 'message' => 'Session not found or unauthorized']);
        exit();
    }
    
    // End the session
    $end_stmt = $pdo->prepare("UPDATE sessions SET status = 'completed' WHERE id = ?");
    $result = $end_stmt->execute([$session_id]);
    
    if ($result) {
        error_log("DEBUG - Manually ended session: {$session['session_name']} (ID: $session_id)");
        echo json_encode([
            'success' => true, 
            'message' => 'Session ended successfully',
            'session_name' => $session['session_name']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to end session']);
    }
    
} catch (Exception $e) {
    error_log("Error ending session: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>