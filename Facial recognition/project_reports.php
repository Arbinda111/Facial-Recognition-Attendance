<?php
require 'db_connect.php';
require 'auth.php';
require_login();

$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $title = trim($_POST['title'] ?? "");
    $description = trim($_POST['description'] ?? "");
    if ($title === "") {
        $msg = "Title is required.";
    } else {
        $dir = __DIR__ . "/uploads";
        if (!is_dir($dir)) { mkdir($dir, 0777, true); }

        $name = basename($_FILES['file']['name']);
        $target = $dir . "/" . $name;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
            $relPath = "uploads/" . $name;
            $stmt = $conn->prepare("INSERT INTO project_reports (title, description, file_path) VALUES (?,?,?)");
            $stmt->bind_param("sss", $title, $description, $relPath);
            $stmt->execute();
            $msg = "Report uploaded.";
        } else {
            $msg = "Upload failed.";
        }
    }
}

$list = $conn->query("SELECT * FROM project_reports ORDER BY report_id DESC");
?>
<!DOCTYPE html>
<html>
<head><title>Project Reports</title></head>
<body>
<h2>Project Reports</h2>
<p><a href="admin_dashboard.php">← Back to Dashboard</a></p>
<?php if($msg): ?><p style="color:green;"><?php echo htmlspecialchars($msg); ?></p><?php endif; ?>

<h3>Upload New Report</h3>
<form method="POST" action="project_reports.php" enctype="multipart/form-data">
  <label>Title</label><br>
  <input name="title" required><br><br>
  <label>Description</label><br>
  <textarea name="description"></textarea><br><br>
  <label>Select File</label><br>
  <input type="file" name="file" required><br><br>
  <button type="submit">Upload</button>
</form>

<h3>Existing Reports</h3>
<table border="1" cellpadding="6">
  <tr><th>ID</th><th>Title</th><th>Description</th><th>File</th><th>Uploaded</th></tr>
  <?php while($r = $list->fetch_assoc()): ?>
  <tr>
    <td><?php echo $r['report_id']; ?></td>
    <td><?php echo htmlspecialchars($r['title']); ?></td>
    <td><?php echo nl2br(htmlspecialchars($r['description'])); ?></td>
    <td><a href="<?php echo htmlspecialchars($r['file_path']); ?>" target="_blank">Download</a></td>
    <td><?php echo $r['uploaded_at']; ?></td>
  </tr>
  <?php endwhile; ?>
</table>
</body>
</html>
