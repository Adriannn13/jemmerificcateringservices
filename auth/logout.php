<?php
// auth/logout.php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Mark user as offline in database before clearing session
if (!empty($_SESSION['user_id'])) {
    $uid = intval($_SESSION['user_id']);
    try {
        require_once '../db_config.php';
        $conn->query("UPDATE users SET is_online = 0 WHERE user_id = $uid");
    } catch (Throwable $e) {}
}

// Clear all session variables
$_SESSION = [];

// Delete the session cookie completely from the browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session data on server
@session_destroy();
if (function_exists('session_write_close')) {
    @session_write_close();
}

// Redirect to login page with a success message
header("Location: ../login.php?success=logged_out");
exit();
?>
