<?php
session_start();
require_once '../config/database.php';

// Check if lecturer is logged in
if (!isset($_SESSION['lecturer_logged_in']) || $_SESSION['lecturer_logged_in'] !== true) {
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

if (!$input || !isset($input['student_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit();
}

$student_id = $input['student_id'];
$confidence = $input['confidence'] ?? 0;
$timestamp = $input['timestamp'] ?? date('Y-m-d H:i:s');
$method = $input['method'] ?? 'face_recognition';
$lecturer_id = $input['lecturer_id'] ?? $_SESSION['lecturer_id'];
$session_id = $input['session_id'] ?? null;

try {
    // Get student's internal ID
    $stmt = $pdo->prepare("SELECT id FROM students WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    if (!$student) {
        http_response_code(404);
        echo json_encode(['error' => 'Student not found']);
        exit();
    }
    
    $student_internal_id = $student['id'];
    
    // Check if attendance already marked today
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT id FROM attendance WHERE student_id = ? AND DATE(attendance_time) = ?");
    $stmt->execute([$student_internal_id, $today]);
    
    if ($stmt->fetch()) {
        echo json_encode([
            'success' => true,
            'message' => 'Attendance already marked for today',
            'already_marked' => true
        ]);
        exit();
    }
    
    // Use provided session_id or find/create a session
    if ($session_id) {
        // Verify the provided session_id belongs to this lecturer
        $stmt = $pdo->prepare("SELECT s.id FROM sessions s JOIN classes c ON s.class_id = c.id WHERE s.id = ? AND c.lecturer_id = ?");
        $stmt->execute([$session_id, $lecturer_id]);
        if (!$stmt->fetch()) {
            $session_id = null; // Invalid session, fall back to default
        }
    }
    
    if (!$session_id) {
        // Create a default session if none exists for today
        $stmt = $pdo->prepare("SELECT id FROM sessions WHERE session_date = ? LIMIT 1");
        $stmt->execute([$today]);
        $session = $stmt->fetch();
        
        if (!$session) {
            // Create default session
            $stmt = $pdo->prepare("INSERT INTO sessions (session_name, class_id, session_date, start_time, end_time, created_by) VALUES (?, 1, ?, '09:00:00', '17:00:00', ?)");
            $stmt->execute(['Daily Attendance - ' . $today, $today, $lecturer_id]);
            $session_id = $pdo->lastInsertId();
        } else {
            $session_id = $session['id'];
        }
    }
    
    // Save attendance
    $stmt = $pdo->prepare("INSERT INTO attendance (student_id, session_id, attendance_time, status, confidence, method) VALUES (?, ?, ?, 'present', ?, ?)");
    $stmt->execute([$student_internal_id, $session_id, $timestamp, $confidence, $method]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Attendance saved successfully',
        'attendance_id' => $pdo->lastInsertId()
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
