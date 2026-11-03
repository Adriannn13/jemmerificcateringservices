<?php
// auth_check.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    // If no user_id is found in session, redirect to login
    header("Location: login.php?error=unauthorized");
    exit();
}
?>