<?php
include 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $session_name = $_POST['session_name'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];

    $sql = "INSERT INTO sessions (session_name, start_date, end_date) 
            VALUES ('$session_name', '$start_date', '$end_date')";

    if ($conn->query($sql) === TRUE) {
        echo "Session added successfully!";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>
