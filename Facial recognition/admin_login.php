<?php
// admin_login.php
session_start();
require_once __DIR__ . '/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: admin_login.html'); exit;
}

$username = trim($_POST['username'] ?? '');
$password = (string)($_POST['password'] ?? '');

if ($username === '' || $password === '') {
  exit('Missing username or password');
}

$stmt = $conn->prepare('SELECT admin_id, username, password FROM admins WHERE username = ? LIMIT 1');
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
  // Tip: you can redirect back with a query param like ?err=1 and show a banner
  exit('Invalid username or password');
}

// success
$_SESSION['admin_id'] = $user['admin_id'];
$_SESSION['admin_username'] = $user['username'];

// go to the protected dashboard
header('Location: admin_dashboard.php'); 
exit;
