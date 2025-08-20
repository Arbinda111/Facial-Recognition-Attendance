<?php
session_start();
require_once __DIR__.'/db_connect.php'; // your PDO connection

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: admin_login.html'); exit;
}

$username = trim($_POST['username'] ?? '');
$password = (string)($_POST['password'] ?? '');

if ($username === '' || $password === '') {
  exit('Missing username or password');
}

$stmt = $conn->prepare('SELECT admin_id, password FROM admins WHERE username = ?');
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
  exit('Invalid username or password');
}

$_SESSION['admin_id'] = $user['admin_id'];
header('Location: admin_dashboard.html'); // this file exists in your project
exit;
