<?php
// auth/login_process.php
session_start();
require_once '../db_config.php';
require_once '../includes/staff_helper.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $pass = $_POST['password'];

    $stmt = $conn->prepare("SELECT user_id, full_name, password, role, permissions FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && (password_verify($pass, $user['password']) || $pass === $user['password'])) {
        
        $_SESSION['user_id']   = $user['user_id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['logged_in'] = true;

        $role = strtolower($user['role'] ?? '');

        // Load permissions if role is staff
        if ($role === 'staff') {
            $perms = json_decode($user['permissions'] ?? '[]', true);
            $_SESSION['permissions'] = is_array($perms) ? $perms : [];
        }

        // Record activity & online status
        update_user_activity($conn, $user['user_id']);

        // Redirect based on role
        if ($role === 'admin') {
            header("Location: ../admin_dashboard.php");
        } elseif ($role === 'staff') {
            header("Location: ../staff_dashboard.php");
        } else {
            header("Location: ../client_home.php");
        }
        exit();
    } else {
        header("Location: ../login.php?error=invalid_credentials");
        exit();
    }
}
?>
