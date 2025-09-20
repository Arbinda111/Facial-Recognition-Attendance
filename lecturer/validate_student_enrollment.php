<?php
session_start();
require_once '../config/database.php';

// Check if lecturer is logged in
if (!isset($_SESSION['lecturer_logged_in']) || $_SESSION['lecturer_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['valid' => false, 'reason' => 'Unauthorized']);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$student_id = $input['student_id'] ?? '';
$lecturer_id = $input['lecturer_id'] ?? '';

if (empty($student_id) || empty($lecturer_id)) {
    echo json_encode(['valid' => false, 'reason' => 'Missing required parameters']);
    exit();
}

try {
    $pdo = getDBConnection();
    
    // Debug logging
    error_log("DEBUG - Student validation request: student_id=$student_id, lecturer_id=$lecturer_id");
    
    // Step 1: Check if student_id exists in attendance_student table (face recognition data)
    $attendance_stmt = $pdo->prepare("SELECT name FROM attendance_student WHERE student_id = ? LIMIT 1");
    $attendance_stmt->execute([$student_id]);
    $attendance_record = $attendance_stmt->fetch();
    
    if (!$attendance_record) {
        error_log("DEBUG - Student not found in attendance_student table: $student_id");
        echo json_encode([
            'valid' => false, 
            'reason' => 'Student not found in face recognition database',
            'student_id' => $student_id
        ]);
        exit();
    }
    
    // Step 2: Find student record in students table using student_id
    $student_stmt = $pdo->prepare("SELECT id, name FROM students WHERE student_id = ? LIMIT 1");
    $student_stmt->execute([$student_id]);
    $student_record = $student_stmt->fetch();
    
    if (!$student_record) {
        error_log("DEBUG - Student not found in students table: $student_id");
        echo json_encode([
            'valid' => false, 
            'reason' => 'Student not found in students database',
            'student_id' => $student_id
        ]);
        exit();
    }
    
    error_log("DEBUG - Found student: ID={$student_record['id']}, Name={$student_record['name']}");
    
    // Step 3: Check if student is enrolled under the current lecturer
    $enrollment_stmt = $pdo->prepare("
        SELECT lse.id, lse.assigned_at, s.name as student_name, lse.class_id, lse.subject_id
        FROM lecturer_student_enrollments lse 
        JOIN students s ON lse.student_id = s.id 
        WHERE lse.student_id = ? AND lse.lecturer_id = ? 
        LIMIT 1
    ");
    $enrollment_stmt->execute([$student_record['id'], $lecturer_id]);
    $enrollment_record = $enrollment_stmt->fetch();
    
    if (!$enrollment_record) {
        error_log("DEBUG - Student not enrolled under lecturer: student_internal_id={$student_record['id']}, lecturer_id=$lecturer_id");
        
        // Additional debug: Check what enrollments exist for this student
        $debug_stmt = $pdo->prepare("SELECT lecturer_id FROM lecturer_student_enrollments WHERE student_id = ?");
        $debug_stmt->execute([$student_record['id']]);
        $existing_enrollments = $debug_stmt->fetchAll();
        $lecturer_list = implode(', ', array_column($existing_enrollments, 'lecturer_id'));
        error_log("DEBUG - Student is enrolled under lecturers: $lecturer_list");
        
        echo json_encode([
            'valid' => false, 
            'reason' => 'Student not enrolled under current lecturer',
            'student_id' => $student_id,
            'internal_student_id' => $student_record['id'],
            'enrolled_under_lecturers' => $lecturer_list
        ]);
        exit();
    }
    
    error_log("DEBUG - Student validation successful: {$enrollment_record['student_name']} enrolled under lecturer $lecturer_id");
    
    // All validations passed - student is valid
    echo json_encode([
        'valid' => true,
        'student_id' => $student_id,
        'internal_student_id' => $student_record['id'],
        'student_name' => $enrollment_record['student_name'],
        'enrollment_date' => $enrollment_record['assigned_at'],
        'class_id' => $enrollment_record['class_id'],
        'subject_id' => $enrollment_record['subject_id'],
        'reason' => 'Student validated successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Student validation error: " . $e->getMessage());
    echo json_encode([
        'valid' => false,
        'reason' => 'Database error during validation'
    ]);
}
?>