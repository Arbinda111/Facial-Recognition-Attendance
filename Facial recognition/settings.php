<?php
require 'db_connect.php';
require 'auth.php';
require_login();

$admin_id = current_admin_id();
$msg = "";

// Load current admin
$stmt = $conn->prepare("SELECT username, full_name, email FROM admins WHERE admin_id=?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

// Update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? "");
    $email     = trim($_POST['email'] ?? "");
    $stmt = $conn->prepare("UPDATE admins SET full_name=?, email=? WHERE admin_id=?");
    $stmt->bind_param("ssi", $full_name, $email, $admin_id);
    $stmt->execute();
    $msg = "Profile updated.";
    // reload
    header("Location: settings.php?msg=" . urlencode($msg));
    exit();
}

// Change password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $old = $_POST['old_password'] ?? "";
    $new = $_POST['new_password'] ?? "";
    $confirm = $_POST['confirm_password'] ?? "";

    if ($new !== $confirm) {
        $msg = "New passwords do not match.";
    } else {
        // fetch hash
        $stmt = $conn->prepare("SELECT password FROM admins WHERE admin_id=?");
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $hash = $stmt->get_result()->fetch_assoc()['password'];
        if (!password_verify($old, $hash)) {
            $msg = "Old password is incorrect.";
        } else {
            $newhash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admins SET password=? WHERE admin_id=?");
            $stmt->bind_param("si", $newhash, $admin_id);
            $stmt->execute();
            $msg = "Password changed.";
            header("Location: settings.php?msg=" . urlencode($msg));
            exit();
        }
    }
}

if (isset($_GET['msg'])) $msg = $_GET['msg'];
?>
<!DOCTYPE html>
<html>
<head><title>Settings</title></head>
<body>
<h2>Settings</h2>
<p><a href="admin_dashboard.php">← Back to Dashboard</a></p>
<?php if($msg): ?><p style="color:green;"><?php echo htmlspecialchars($msg); ?></p><?php endif; ?>

<h3>Profile</h3>
<form method="POST" action="settings.php">
  <input type="hidden" name="update_profile" value="1">
  <label>Username (read-only)</label><br>
  <input value="<?php echo htmlspecialchars($admin['username']); ?>" disabled><br><br>

  <label>Full Name</label><br>
  <input name="full_name" value="<?php echo htmlspecialchars($admin['full_name']); ?>"><br><br>

  <label>Email</label><br>
  <input type="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>"><br><br>

  <button type="submit">Save Profile</button>
</form>

<h3>Change Password</h3>
<form method="POST" action="settings.php">
  <input type="hidden" name="change_password" value="1">
  <label>Old Password</label><br>
  <input type="password" name="old_password" required><br><br>
  <label>New Password</label><br>
  <input type="password" name="new_password" required><br><br>
  <label>Confirm New Password</label><br>
  <input type="password" name="confirm_password" required><br><br>
  <button type="submit">Change Password</button>
</form>
</body>
</html>
