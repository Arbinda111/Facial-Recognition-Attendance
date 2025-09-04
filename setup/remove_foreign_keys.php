<?php
require_once '../config/database.php';

$conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$sql = "
DROP TABLE IF EXISTS attendance;
CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    student_id INT NOT NULL,
    attendance_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('present', 'absent', 'late') DEFAULT 'present',
    method ENUM('face_recognition', 'manual', 'qr_code') DEFAULT 'face_recognition',
    marked_by INT NULL,
    confidence_score DECIMAL(5,4) NULL,
    notes TEXT,
    UNIQUE KEY unique_attendance (session_id, student_id)
);

DROP TABLE IF EXISTS face_recognition_logs;
CREATE TABLE face_recognition_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    session_id INT,
    recognition_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confidence_score DECIMAL(5,4),
    status ENUM('success', 'failed', 'low_confidence') DEFAULT 'success',
    image_path VARCHAR(255)
);
";

if ($conn->multi_query($sql)) {
    echo "Foreign keys removed and tables recreated successfully.";
} else {
    echo "Error: " . $conn->error;
}
$conn->close();
?>
