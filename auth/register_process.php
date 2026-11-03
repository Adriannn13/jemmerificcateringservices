<?php
// auth/register_process.php - Secure Account Registration Handler
session_start();
require_once '../db_config.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // 1. Rate Limiting Check (Max 5 attempts in 15 minutes)
    if (!isset($_SESSION['reg_attempts'])) {
        $_SESSION['reg_attempts'] = 0;
        $_SESSION['reg_first_attempt_time'] = time();
    }

    if (time() - $_SESSION['reg_first_attempt_time'] > 900) {
        // Reset throttle window after 15 mins
        $_SESSION['reg_attempts'] = 0;
        $_SESSION['reg_first_attempt_time'] = time();
    }

    $_SESSION['reg_attempts']++;

    if ($_SESSION['reg_attempts'] > 5) {
        header("Location: ../register.php?error=rate_limit");
        exit();
    }

    // 2. CSRF Token Verification
    $submitted_csrf = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted_csrf)) {
        header("Location: ../register.php?error=invalid_csrf");
        exit();
    }

    // 3. Collect and Sanitize Inputs
    $full_name = trim(strip_tags($_POST['full_name'] ?? ''));
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    // 4. Input Length & Format Validation
    if (strlen($full_name) < 2) {
        header("Location: ../register.php?error=name_short");
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: ../register.php?error=invalid_email");
        exit();
    }

    // 5. Password Confirmation Matching
    if ($pass !== $confirm_pass) {
        header("Location: ../register.php?error=pass_mismatch");
        exit();
    }

    // 6. Security Password Strength Validation (Server-side Enforcement)
    // - At least 8 characters
    // - At least one uppercase letter
    // - At least one lowercase letter
    // - At least one digit
    // - At least one special character
    $is_length_ok  = strlen($pass) >= 8;
    $has_uppercase = preg_match('/[A-Z]/', $pass);
    $has_lowercase = preg_match('/[a-z]/', $pass);
    $has_digit     = preg_match('/[0-9]/', $pass);
    $has_special   = preg_match('/[!@#$%^&*(),.?":{}|<>_\-\=\+\[\]]/', $pass);

    if (!$is_length_ok || !$has_uppercase || !$has_lowercase || !$has_digit || !$has_special) {
        header("Location: ../register.php?error=pass_weak");
        exit();
    }

    // 7. Check if Email Already Registered
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE LOWER(email) = LOWER(?)");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        header("Location: ../register.php?error=email_taken");
        exit();
    }

    // 8. Secure Hash & Insert into Database
    $hashed_password = password_hash($pass, PASSWORD_DEFAULT);
    $insert = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'customer')");
    $insert->bind_param("sss", $full_name, $email, $hashed_password);
    
    if ($insert->execute()) {
        // Reset throttle on successful registration
        unset($_SESSION['reg_attempts']);
        unset($_SESSION['reg_first_attempt_time']);
        
        // Regenerate CSRF token for next session
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        header("Location: ../login.php?success=registered");
        exit();
    } else {
        header("Location: ../register.php?error=server_error");
        exit();
    }
}
?>