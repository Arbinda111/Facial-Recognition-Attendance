<?php
include 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['file'])) {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $file_name = $_FILES['file']['name'];
    $file_tmp = $_FILES['file']['tmp_name'];

    $upload_dir = "uploads/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_path = $upload_dir . basename($file_name);
    move_uploaded_file($file_tmp, $file_path);

    $sql = "INSERT INTO project_reports (title, description, file_path) 
            VALUES ('$title', '$description', '$file_path')";

    if ($conn->query($sql) === TRUE) {
        echo "Project report uploaded successfully!";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>
