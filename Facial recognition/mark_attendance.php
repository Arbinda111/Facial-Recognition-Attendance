<?php
include 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $student_id = $_POST['student_id'];
    $session_id = $_POST['session_id'];
    $class_id = $_POST['class_id'];
    $status = $_POST['status'];
    $date = date('Y-m-d');

    $sql = "INSERT INTO attendance (student_id, session_id, class_id, date, status) 
            VALUES ('$student_id', '$session_id', '$class_id', '$date', '$status')";

    if ($conn->query($sql) === TRUE) {
        echo "Attendance marked!";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>
