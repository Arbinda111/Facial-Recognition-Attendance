<?php
// populate_lecturer_student_enrollments.php
require_once '../config/database.php';

// Get all student enrollments
$enrollments = $pdo->query("SELECT student_id, class_id FROM student_enrollments WHERE status = 'enrolled'")->fetchAll();

$count = 0;
foreach ($enrollments as $enrollment) {
    $class_id = $enrollment['class_id'];
    $student_id = $enrollment['student_id'];
    // Get subject_id and lecturer_id from classes
    $class_stmt = $pdo->prepare("SELECT subject_id, lecturer_id FROM classes WHERE id = ?");
    $class_stmt->execute([$class_id]);
    $class = $class_stmt->fetch();
    if ($class && $class['lecturer_id'] && $class['subject_id']) {
        // Insert into lecturer_student_enrollments if not exists
        $check_stmt = $pdo->prepare("SELECT id FROM lecturer_student_enrollments WHERE lecturer_id = ? AND student_id = ? AND class_id = ? AND subject_id = ?");
        $check_stmt->execute([$class['lecturer_id'], $student_id, $class_id, $class['subject_id']]);
        if (!$check_stmt->fetch()) {
            $insert_stmt = $pdo->prepare("INSERT INTO lecturer_student_enrollments (lecturer_id, student_id, class_id, subject_id) VALUES (?, ?, ?, ?)");
            $insert_stmt->execute([$class['lecturer_id'], $student_id, $class_id, $class['subject_id']]);
            $count++;
        }
    }
}

if ($count > 0) {
    echo "<h2>Successfully populated $count rows in lecturer_student_enrollments.</h2>";
} else {
    echo "<h2>No new rows were added. Table is up to date or no valid enrollments found.</h2>";
}
?>
