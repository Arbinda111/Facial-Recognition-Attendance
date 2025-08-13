<?php
require 'db_connect.php';
require 'auth.php';
require_login();

$counts = [
  'students' => 0,
  'classes'  => 0,
  'reports'  => 0,
  'today_attendance' => 0
];

$counts['students'] = (int)$conn->query("SELECT COUNT(*) c FROM students")->fetch_assoc()['c'];
$counts['classes']  = (int)$conn->query("SELECT COUNT(*) c FROM classes")->fetch_assoc()['c'];
$counts['reports']  = (int)$conn->query("SELECT COUNT(*) c FROM project_reports")->fetch_assoc()['c'];

$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT COUNT(*) c FROM attendance WHERE date=?");
$stmt->bind_param("s", $today);
$stmt->execute();
$counts['today_attendance'] = (int)$stmt->get_result()->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html>
<head><title>Admin Dashboard</title></head>
<body>
  <h1>Admin Dashboard</h1>
  <p><a href="logout.php">Logout</a> | <a href="settings.php">Settings</a></p>
  <ul>
    <li>Total Students: <?php echo $counts['students']; ?></li>
    <li>Total Classes: <?php echo $counts['classes']; ?></li>
    <li>Total Project Reports: <?php echo $counts['reports']; ?></li>
    <li>Today’s Attendance Entries: <?php echo $counts['today_attendance']; ?></li>
  </ul>

  <h3>Quick Links</h3>
  <ul>
    <li><a href="student_directory.php">Student Directory</a></li>
    <li><a href="class_management.php">Class Management</a></li>
    <li><a href="timetable_add.php">Add Timetable</a></li>
    <li><a href="project_reports.php">Project Reports</a></li>
  </ul>
</body>
</html>
