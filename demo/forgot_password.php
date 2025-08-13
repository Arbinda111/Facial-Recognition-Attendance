<?php
require 'db_connect.php';
require 'auth.php';

// Step 1: ask for username; Step 2: let set new password (no email OTP in this simple flow)
$msg = "";
$stage = "ask_username";
$username = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['username'])) {
        $username = trim($_POST['username']);
        $stmt = $conn->prepare("SELECT admin_id FROM admins WHERE username=?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        if ($row = $stmt->get_result()->fetch_assoc()) {
            $_SESSION['reset_admin_id'] = $row['admin_id'];
            $stage = "set_password";
        } else {
            $msg = "Username not found.";
        }
    } elseif (isset($_POST['new_password']) && isset($_SESSION['reset_admin_id'])) {
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];
        if ($new !== $confirm) {
            $msg = "Passwords do not match.";
            $stage = "set_password";
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admins SET password=? WHERE admin_id=?");
            $stmt->bind_param("si", $hash, $_SESSION['reset_admin_id']);
            $stmt->execute();
            unset($_SESSION['reset_admin_id']);
            header("Location: admin_login.php?msg=password+reset");
            exit();
        }
    }
} else {
    unset($_SESSION['reset_admin_id']);
}
?>
<!DOCTYPE html>
<html>
<head><title>Forgot Password</title></head>
<body>
<h2>Forgot Password</h2>
<?php if($msg): ?><p style="color:red;"><?php echo htmlspecialchars($msg); ?></p><?php endif; ?>

<?php if($stage === "ask_username"): ?>
<form method="POST" action="forgot_password.php">
  <label>Enter your username</label><br>
  <input name="username" required><br><br>
  <button type="submit">Next</button>
</form>
<?php else: ?>
<form method="POST" action="forgot_password.php">
  <label>New Password</label><br>
  <input type="password" name="new_password" required><br><br>
  <label>Confirm New Password</label><br>
  <input type="password" name="confirm_password" required><br><br>
  <button type="submit">Reset Password</button>
</form>
<?php endif; ?>

<p><a href="admin_login.php">Back to Login</a></p>
</body>
</html>
