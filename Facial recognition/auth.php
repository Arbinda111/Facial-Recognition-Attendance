<?php
// auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
function require_login() {
    if (!isset($_SESSION['admin_id'])) {
        header("Location: admin_login.php?msg=please+login");
        exit();
    }
}
function current_admin_id() {
    return $_SESSION['admin_id'] ?? null;
}
