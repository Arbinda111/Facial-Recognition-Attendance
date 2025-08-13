<?php
include 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $class_name = $_POST['class_name'];
    $description = $_POST['description'];

    $sql = "INSERT INTO classes (class_name, description) 
            VALUES ('$class_name', '$description')";

    if ($conn->query($sql) === TRUE) {
        echo "Class added successfully!";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>
