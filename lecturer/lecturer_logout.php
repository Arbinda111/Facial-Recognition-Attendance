<?php
session_start();

// Destroy all session data
session_destroy();

// Redirect to lecturer login
header('Location: lecturer_login.php');
exit();
?>
