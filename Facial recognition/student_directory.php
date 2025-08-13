<?php
require 'db_connect.php';
require 'auth.php';
require_login();

$q = trim($_GET['q'] ?? "");
if ($q !== "") {
    $like = "%" . $q . "%";
    $stmt = $conn->prepare("SELECT * FROM students 
        WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? 
        ORDER BY student_id DESC");
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $students = $stmt->get_result();
} else {
    $students = $conn->query("SELECT * FROM students ORDER BY student_id DESC");
}
?>
<!DOCTYPE html>
<html>
<head><title>Student Directory</title></head>
<body>
<h2>Student Directory</h2>
<p><a href="admin_dashboard.php">← Back to Dashboard</a></p>

<form method="GET" action="student_directory.php">
  <input name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search name or email">
  <button type="submit">Search</button>
</form>

<table border="1" cellpadding="6">
  <tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Created</th></tr>
  <?php while($s = $students->fetch_assoc()): ?>
  <tr>
    <td><?php echo $s['student_id']; ?></td>
    <td><?php echo htmlspecialchars($s['first_name']." ".$s['last_name']); ?></td>
    <td><?php echo htmlspecialchars($s['email']); ?></td>
    <td><?php echo htmlspecialchars($s['phone']); ?></td>
    <td><?php echo $s['created_at']; ?></td>
  </tr>
  <?php endwhile; ?>
</table>
</body>
</html>
