<?php
require_once '../includes/auth_check.php';
require_once '../db_config.php';

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    }
}
?>