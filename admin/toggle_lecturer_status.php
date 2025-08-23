<?php
session_start();
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['lecturer_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing lecturer ID']);
    exit();
}

$lecturer_id = $input['lecturer_id'];

try {
    // Get current status
    $stmt = $pdo->prepare("SELECT status FROM lecturers WHERE id = ?");
    $stmt->execute([$lecturer_id]);
    $lecturer = $stmt->fetch();
    
    if (!$lecturer) {
        http_response_code(404);
        echo json_encode(['error' => 'Lecturer not found']);
        exit();
    }
    
    // Toggle status
    $new_status = $lecturer['status'] === 'active' ? 'inactive' : 'active';
    
    $stmt = $pdo->prepare("UPDATE lecturers SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $lecturer_id]);
    
    echo json_encode([
        'success' => true,
        'new_status' => $new_status,
        'message' => 'Lecturer status updated successfully'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
