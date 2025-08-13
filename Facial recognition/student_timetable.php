<?php
include 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $class_id = $_POST['class_id'];
    $subject = $_POST['subject'];
    $day_of_week = $_POST['day_of_week'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];

    $sql = "INSERT INTO timetable (class_id, subject, day_of_week, start_time, end_time) 
            VALUES ('$class_id', '$subject', '$day_of_week', '$start_time', '$end_time')";

    if ($conn->query($sql) === TRUE) {
        echo "Timetable entry added successfully!";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>
