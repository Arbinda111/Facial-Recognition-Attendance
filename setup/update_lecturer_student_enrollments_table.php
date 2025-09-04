<?php
// update_lecturer_student_enrollments_table.php
require_once '../config/database.php';

$sql = "CREATE TABLE IF NOT EXISTS lecturer_student_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lecturer_id INT NOT NULL,
    student_id INT NOT NULL,
    class_id INT NOT NULL,
    subject_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_lecturer_student_class (lecturer_id, student_id, class_id, subject_id)
)";

try {
    $pdo->exec($sql);
    echo '<h2>lecturer_student_enrollments table created or already exists.</h2>';
} catch (PDOException $e) {
    echo '<h2>Error creating table:</h2>';
    echo '<pre>' . $e->getMessage() . '</pre>';
}
?>
