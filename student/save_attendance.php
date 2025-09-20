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
    
    // Use provided session_id if available, otherwise find an active session
    $session_id = null;
    if (isset($input['session_id']) && $input['session_id']) {
        // Verify the provided session_id is valid and accessible to this student
        $stmt = $pdo->prepare("
            SELECT s.id 
            FROM sessions s 
            JOIN classes c ON s.class_id = c.id 
            JOIN lecturer_student_enrollments lse ON (
                (c.id = lse.class_id AND c.subject_id = lse.subject_id) OR
                (lse.class_id = 0 AND c.subject_id = lse.subject_id AND c.lecturer_id = lse.lecturer_id)
            )
            WHERE s.id = ? 
            AND lse.student_id = ?
            AND s.status IN ('scheduled', 'ongoing')
        ");
        $stmt->execute([$input['session_id'], $student_db_id]);
        $session = $stmt->fetch();
        if ($session) {
            $session_id = $session['id'];
        }
    }
    
    // If no valid session_id provided or found, find an active session for today
    if (!$session_id) {
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("
            SELECT s.id 
            FROM sessions s 
            JOIN classes c ON s.class_id = c.id 
            JOIN lecturer_student_enrollments lse ON (
                (c.id = lse.class_id AND c.subject_id = lse.subject_id) OR
                (lse.class_id = 0 AND c.subject_id = lse.subject_id AND c.lecturer_id = lse.lecturer_id)
            )
            WHERE lse.student_id = ? 
            AND s.session_date = ? 
            AND s.status IN ('scheduled', 'ongoing')
            ORDER BY s.start_time ASC 
            LIMIT 1
        ");
        $stmt->execute([$student_db_id, $today]);
        $session = $stmt->fetch();
        
        if ($session) {
            $session_id = $session['id'];
        }
    }
    
    // If still no session found, create a general one or return error
    if (!$session_id) {
        // For now, return an error - we could create a general session instead
        http_response_code(404);
        echo json_encode(['error' => 'No active session found for attendance marking']);
        exit();
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
