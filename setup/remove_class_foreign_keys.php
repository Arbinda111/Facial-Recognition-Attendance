<?php
require_once '../config/database.php';

$conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$sql = "
DROP TABLE IF EXISTS classes;
CREATE TABLE classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(100) NOT NULL,
    class_code VARCHAR(20) UNIQUE NOT NULL,
    description TEXT,
    instructor_name VARCHAR(100),
    semester VARCHAR(20),
    academic_year VARCHAR(10),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
";

if ($conn->multi_query($sql)) {
    echo "Classes table recreated without foreign key constraints.";
} else {
    echo "Error: " . $conn->error;
}
$conn->close();
?>
