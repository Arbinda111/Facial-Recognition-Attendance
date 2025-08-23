<?php
session_start();

// Destroy all session data
session_destroy();

// Redirect to student login page
header('Location: student_login.php');
exit();
?>
