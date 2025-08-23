<?php
session_start();
require_once '../config/database.php';

// Check if admin is already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin_dashboard.php');
    exit();
}

$error_message = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    try {
        // Database authentication
        $stmt = $pdo->prepare("SELECT id, username, email, password, full_name FROM admin WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $admin = $stmt->fetch();
        
        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['admin_full_name'] = $admin['full_name'];
            header('Location: admin_dashboard.php');
            exit();
        } else {
            $error_message = 'Invalid username or password';
        }
    } catch (PDOException $e) {
        $error_message = 'Database error. Please try again.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Full Attend</title>
    <link rel="stylesheet" href="admin_styles.css">
</head>
<body class="login-page">
    <div class="container">
        <a class="back-button" href="../index.php">&larr; Back to Main Login</a>
        <div class="login-box">
            <div class="login-logos">
                <img src="../images/cihe_logo.png" alt="CIHE Logo">
                <img src="../images/fullattend_logo.png" alt="FullAttend Logo">
            </div>
            <h2>Admin Login</h2>
            
            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="input-group">
                    <input type="text" name="username" placeholder="Username" required>
                </div>
                <div class="input-group">
                    <input type="password" name="password" placeholder="Password" required>
                </div>
                <button type="submit" class="login-button">LOG IN</button>
            </form>
            <div class="forgot-password">
                <a href="forgot_password.php">Forgotten Password?</a>
            </div>
        </div>
    </div>
</body>
</html>
