<?php
require_once '../config/database.php';

$conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Find the constraint name for class_id foreign key in student_enrollments
$result = $conn->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'student_enrollments' AND COLUMN_NAME = 'class_id' AND REFERENCED_TABLE_NAME = 'classes'");
if ($row = $result->fetch_assoc()) {
    $fk_name = $row['CONSTRAINT_NAME'];
    $sql = "ALTER TABLE student_enrollments DROP FOREIGN KEY `$fk_name`;";
    if ($conn->query($sql)) {
        echo "Foreign key '$fk_name' removed from student_enrollments table.";
    } else {
        echo "Error removing foreign key: " . $conn->error;
    }
} else {
    echo "Foreign key for class_id not found in student_enrollments table.";
}
$conn->close();
?>
