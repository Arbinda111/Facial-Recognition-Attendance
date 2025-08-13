<?php
// create_admin.php  -- run once, then delete for security
require 'db_connect.php';

$username  = "admin";
$password  = password_hash("admin123", PASSWORD_DEFAULT);
$full_name = "Administrator";
$email     = "admin@example.com";

$stmt = $conn->prepare("INSERT INTO admins (username, password, full_name, email) VALUES (?,?,?,?)");
$stmt->bind_param("ssss", $username, $password, $full_name, $email);
$stmt->execute();

echo "Admin created: $username / admin123 (please delete create_admin.php)";
