<?php
require 'db_connect.php';
require 'auth.php';
require_login();

// fetch classes for dropdown
$classes = $conn->query("SELECT class_id, class_name FROM classes ORDER BY class_name ASC");

// handle submit
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_id   = (int)($_POST['class_id'] ?? 0);
    $subject    = trim($_POST['subject'] ?? "");
    $day        = trim($_POST['day_of_week'] ?? "");
    $start_time = $_POST['start_time'] ?? "";
    $end_time   = $_POST['end_time'] ?? "";

    if ($class_id && $subject !== "" && $day !== "" && $start_time && $end_time) {
        $stmt = $conn->prepare("INSERT INTO timetable (class_id, subject, day_of_week, start_time, end_time) VALUES (?,?,?,?,?)");
        $stmt->bind_param("issss", $class_id, $subject, $day, $start_time, $end_time);
        $stmt->execute();
        $msg = "Timetable entry added.";
    } else {
        $msg = "Please fill all fields.";
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Add Timetable</title></head>
<body>
<h2>Add Timetable</h2>
<p><a href="admin_dashboard.php">← Back to Dashboard</a></p>
<?php if($msg): ?><p style="color:green;"><?php echo htmlspecialchars($msg); ?></p><?php endif; ?>

<form method="POST" action="timetable_add.php">
  <label>Class</label><br>
  <select name="class_id" required>
    <option value="">-- Select Class --</option>
    <?php while($c = $classes->fetch_assoc()): ?>
      <option value="<?php echo $c['class_id']; ?>"><?php echo htmlspecialchars($c['class_name']); ?></option>
    <?php endwhile; ?>
  </select><br><br>

  <label>Subject</label><br>
  <input name="subject" required><br><br>

  <label>Day of Week</label><br>
  <input name="day_of_week" placeholder="e.g. Monday" required><br><br>

  <label>Start Time</label><br>
  <input type="time" name="start_time" required><br><br>

  <label>End Time</label><br>
  <input type="time" name="end_time" required><br><br>

  <button type="submit">Add</button>
</form>
</body>
</html>
