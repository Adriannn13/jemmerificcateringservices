<?php
session_start();
require_once '../db_config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // CSRF Check
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        header("Location: ../admin_settings.php?error=invalid_csrf");
        exit();
    }

    $admin_id = $_SESSION['user_id'];
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($full_name) || empty($email)) {
        header("Location: ../admin_settings.php?error=missing_fields");
        exit();
    }

    // If password provided, check match
    if (!empty($password)) {
        if ($password !== $confirm_password) {
            header("Location: ../admin_settings.php?error=password_mismatch");
            exit();
        }
        $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, password = ? WHERE user_id = ?");
        $stmt->bind_param("sssi", $full_name, $email, $hashed_pass, $admin_id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ? WHERE user_id = ?");
        $stmt->bind_param("ssi", $full_name, $email, $admin_id);
    }

    if ($stmt->execute()) {
        $_SESSION['full_name'] = $full_name; // Refresh session full_name for sidebar header
        header("Location: ../admin_settings.php?msg=profile_updated");
    } else {
        header("Location: ../admin_settings.php?error=update_failed");
    }
    exit();
}

header("Location: ../admin_settings.php");
exit();
?>
