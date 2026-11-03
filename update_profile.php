<?php
// update_profile.php - Customer Account Profile & Password Processor
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=unauthorized");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Fetch existing user data
    $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        header("Location: settings.php?msg=user_not_found");
        exit();
    }

    // 1. Check if user is attempting to change password
    if (!empty($new_password)) {
        if (empty($current_password)) {
            header("Location: settings.php?msg=current_required");
            exit();
        }

        // Verify current password (allow plain match fallback if legacy unhashed)
        $password_matches = password_verify($current_password, $user['password']) || ($current_password === $user['password']);
        if (!$password_matches) {
            header("Location: settings.php?msg=invalid_current");
            exit();
        }

        if (strlen($new_password) < 6) {
            header("Location: settings.php?msg=pass_short");
            exit();
        }

        if ($new_password !== $confirm_password) {
            header("Location: settings.php?msg=pass_mismatch");
            exit();
        }

        // Hash new password securely
        $hashed_pass = password_hash($new_password, PASSWORD_DEFAULT);

        $up_stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, password = ? WHERE user_id = ?");
        $up_stmt->bind_param("sssi", $full_name, $email, $hashed_pass, $user_id);
        
        if ($up_stmt->execute()) {
            $_SESSION['full_name'] = $full_name;
            header("Location: settings.php?msg=pass_success");
            exit();
        } else {
            header("Location: settings.php?msg=error");
            exit();
        }
    } else {
        // 2. Only updating Profile details
        $up_stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ? WHERE user_id = ?");
        $up_stmt->bind_param("ssi", $full_name, $email, $user_id);

        if ($up_stmt->execute()) {
            $_SESSION['full_name'] = $full_name;
            header("Location: settings.php?msg=profile_success");
            exit();
        } else {
            header("Location: settings.php?msg=error");
            exit();
        }
    }
}