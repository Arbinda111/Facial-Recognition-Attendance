<?php
require_once '../config/database.php';

$conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Find the constraint name for class_id foreign key in sessions
$result = $conn->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'sessions' AND COLUMN_NAME = 'class_id' AND REFERENCED_TABLE_NAME = 'classes'");
if ($row = $result->fetch_assoc()) {
    $fk_name = $row['CONSTRAINT_NAME'];
    $sql = "ALTER TABLE sessions DROP FOREIGN KEY `$fk_name`;";
    if ($conn->query($sql)) {
        echo "Foreign key '$fk_name' removed from sessions table.";
    } else {
        echo "Error removing foreign key: " . $conn->error;
    }
} else {
    echo "Foreign key for class_id not found in sessions table.";
}
$conn->close();
?>
