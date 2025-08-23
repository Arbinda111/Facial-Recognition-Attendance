<?php
session_start();
if (isset($_SESSION['student_logged_in']) && $_SESSION['student_logged_in'] === true) {
    header('Location: student_dashboard.php');
    exit();
} else {
    header('Location: student_login.php');
    exit();
}
