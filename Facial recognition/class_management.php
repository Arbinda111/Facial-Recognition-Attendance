<?php
require 'db_connect.php';
require 'auth.php';
require_login();

$msg = "";

// Add class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['class_name'])) {
    $class_name = trim($_POST['class_name']);
    $description = trim($_POST['description'] ?? "");

    if ($class_name !== "") {
        $stmt = $conn->prepare("INSERT INTO classes (class_name, description) VALUES (?,?)");
        $stmt->bind_param("ss", $class_name, $description);
        $stmt->execute();
        $msg = "Class added.";
    }
}

// Delete class
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM classes WHERE class_id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $msg = "Class deleted.";
}

// Fetch list
$list = $conn->query("SELECT class_id, class_name, description, created_at FROM classes ORDER BY class_id DESC");
?>
<!DOCTYPE html>
<html>
<head><title>Class Management</title></head>
<body>
<h2>Class Management</h2>
<p><a href="admin_dashboard.php">← Back to Dashboard</a></p>
<?php if($msg): ?><p style="color:green;"><?php echo htmlspecialchars($msg); ?></p><?php endif; ?>

<h3>Add Class</h3>
<form method="POST" action="class_management.php">
  <label>Class Name</label><br>
  <input name="class_name" required><br><br>
  <label>Description</label><br>
  <textarea name="description"></textarea><br><br>
  <button type="submit">Add Class</button>
</form>

<h3>Existing Classes</h3>
<table border="1" cellpadding="6">
  <tr><th>ID</th><th>Name</th><th>Description</th><th>Created</th><th>Action</th></tr>
  <?php while($row = $list->fetch_assoc()): ?>
    <tr>
      <td><?php echo $row['class_id']; ?></td>
      <td><?php echo htmlspecialchars($row['class_name']); ?></td>
      <td><?php echo nl2br(htmlspecialchars($row['description'])); ?></td>
      <td><?php echo $row['created_at']; ?></td>
      <td><a href="class_management.php?delete=<?php echo $row['class_id']; ?>" onclick="return confirm('Delete this class?');">Delete</a></td>
    </tr>
  <?php endwhile; ?>
</table>
</body>
</html>
