<?php
// db_connect.php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$servername = "localhost";
$username   = "root";   // XAMPP default
$password   = "";       // XAMPP default
$dbname     = "facial_recognition";

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die("Database connection failed.");
}
