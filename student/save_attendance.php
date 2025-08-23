<?php
session_start();
require_once '../config/database.php';

// Check if student is logged in
if (!isset($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true) {
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

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit();
}

$required_fields = ['student_id', 'confidence', 'timestamp', 'method'];
foreach ($required_fields as $field) {
    if (!isset($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit();
    }
}

try {
    // Get student database ID
    $stmt = $pdo->prepare("SELECT id FROM students WHERE student_id = ?");
    $stmt->execute([$input['student_id']]);
    $student = $stmt->fetch();
    
    if (!$student) {
        http_response_code(404);
        echo json_encode(['error' => 'Student not found']);
        exit();
    }
    
    $student_db_id = $student['id'];
    
    // Find an active session for today (simplified - you might want to make this more specific)
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT s.id 
        FROM sessions s 
        JOIN classes c ON s.class_id = c.id 
        JOIN student_enrollments se ON c.id = se.class_id 
        WHERE se.student_id = ? 
        AND s.session_date = ? 
        AND s.status IN ('scheduled', 'ongoing')
        ORDER BY s.start_time ASC 
        LIMIT 1
    ");
    $stmt->execute([$student_db_id, $today]);
    $session = $stmt->fetch();
    
    if (!$session) {
        // Create a general attendance session if none exists
        $stmt = $pdo->prepare("
            INSERT INTO sessions (session_name, class_id, session_date, start_time, end_time, session_type, status, created_by) 
            VALUES (?, 1, ?, '09:00:00', '17:00:00', 'lecture', 'ongoing', 1)
        ");
        $session_name = "General Attendance - " . date('Y-m-d');
        $stmt->execute([$session_name, $today]);
        $session_id = $pdo->lastInsertId();
    } else {
        $session_id = $session['id'];
    }
    
    // Check if attendance already marked for this session
    $stmt = $pdo->prepare("SELECT id FROM attendance WHERE session_id = ? AND student_id = ?");
    $stmt->execute([$session_id, $student_db_id]);
    $existing_attendance = $stmt->fetch();
    
    if ($existing_attendance) {
        // Update existing attendance
        $stmt = $pdo->prepare("
            UPDATE attendance 
            SET attendance_time = ?, confidence_score = ?, method = ?, notes = ?
            WHERE id = ?
        ");
        $notes = "Updated via face recognition with " . round($input['confidence'] * 100, 2) . "% confidence";
        $stmt->execute([
            date('Y-m-d H:i:s', strtotime($input['timestamp'])),
            $input['confidence'],
            $input['method'],
            $notes,
            $existing_attendance['id']
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Attendance updated successfully',
            'attendance_id' => $existing_attendance['id']
        ]);
    } else {
        // Insert new attendance record
        $stmt = $pdo->prepare("
            INSERT INTO attendance (session_id, student_id, attendance_time, status, method, confidence_score, notes) 
            VALUES (?, ?, ?, 'present', ?, ?, ?)
        ");
        $notes = "Marked via face recognition with " . round($input['confidence'] * 100, 2) . "% confidence";
        $stmt->execute([
            $session_id,
            $student_db_id,
            date('Y-m-d H:i:s', strtotime($input['timestamp'])),
            $input['method'],
            $input['confidence'],
            $notes
        ]);
        
        $attendance_id = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Attendance marked successfully',
            'attendance_id' => $attendance_id
        ]);
    }
    
    // Log the face recognition attempt
    $stmt = $pdo->prepare("
        INSERT INTO face_recognition_logs (student_id, session_id, confidence_score, status) 
        VALUES (?, ?, ?, 'success')
    ");
    $stmt->execute([$student_db_id, $session_id, $input['confidence']]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
