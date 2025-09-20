<?php
session_start();
require_once '../config/database.php';

// Check if lecturer is logged in
if (!isset($_SESSION['lecturer_logged_in']) || $_SESSION['lecturer_logged_in'] !== true) {
    die('Not logged in');
}

$lecturer_id = $_SESSION['lecturer_id'];
$current_time_24hr = date('H:i:s');
$today = date('Y-m-d');

echo "<h2>Debug Sessions for Lecturer ID: $lecturer_id</h2>";
echo "<p>Current time: $current_time_24hr</p>";
echo "<p>Today: $today</p>";

$pdo = getDBConnection();

// Get all sessions for this lecturer today
$stmt = $pdo->prepare("
    SELECT s.id, s.session_name, s.session_date, s.start_time, s.end_time, c.class_name, s.status,
           TIME(s.start_time) as start_time_only, 
           TIME(s.end_time) as end_time_only
    FROM sessions s 
    JOIN classes c ON s.class_id = c.id 
    WHERE c.lecturer_id = ? AND s.session_date = ?
    ORDER BY s.start_time ASC
");
$stmt->execute([$lecturer_id, $today]);
$sessions = $stmt->fetchAll();

echo "<h3>All Sessions Today:</h3>";
echo "<table border='1'>";
echo "<tr><th>ID</th><th>Name</th><th>Status</th><th>Start Time</th><th>End Time</th><th>Should Be Active?</th></tr>";

foreach ($sessions as $sess) {
    $start_seconds = strtotime("1970-01-01 " . $sess['start_time_only']);
    $end_seconds = strtotime("1970-01-01 " . $sess['end_time_only']);
    $current_seconds = strtotime("1970-01-01 " . $current_time_24hr);
    
    $is_active = ($start_seconds <= $current_seconds && $end_seconds >= $current_seconds);
    
    echo "<tr>";
    echo "<td>{$sess['id']}</td>";
    echo "<td>{$sess['session_name']}</td>";
    echo "<td>{$sess['status']}</td>";
    echo "<td>{$sess['start_time']} ({$sess['start_time_only']})</td>";
    echo "<td>{$sess['end_time']} ({$sess['end_time_only']})</td>";
    echo "<td>" . ($is_active ? "YES" : "NO") . "</td>";
    echo "</tr>";
}

echo "</table>";

// Test updating session status
if (isset($_GET['activate']) && $_GET['activate']) {
    $session_id = intval($_GET['activate']);
    $update_stmt = $pdo->prepare("UPDATE sessions SET status = 'ongoing' WHERE id = ?");
    $result = $update_stmt->execute([$session_id]);
    echo "<p>Updated session $session_id status to 'ongoing'. Result: " . ($result ? "Success" : "Failed") . "</p>";
    echo "<a href='debug_sessions.php'>Refresh</a>";
}

echo "<br><br>";
foreach ($sessions as $sess) {
    echo "<a href='?activate={$sess['id']}'>Activate Session: {$sess['session_name']}</a><br>";
}
?>