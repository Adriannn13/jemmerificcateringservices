<?php
// auth/notification_api.php - Real-Time Notification Polling API for Customer Portal
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once '../db_config.php';
require_once '../includes/booking_completion_helper.php';

$user_id = intval($_SESSION['user_id']);
$action = $_GET['action'] ?? $_POST['action'] ?? 'check_unread';

if ($action === 'check_unread') {
    // Auto-check and complete finished events for this user
    checkAndCompleteFinishedBookings($conn, $user_id);
    // Unread count
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $unread_count = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);

    // Latest notification
    $stmt_latest = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt_latest->bind_param("i", $user_id);
    $stmt_latest->execute();
    $latest = $stmt_latest->get_result()->fetch_assoc();

    echo json_encode([
        'success' => true,
        'unread_count' => $unread_count,
        'latest_id' => $latest['notif_id'] ?? 0,
        'latest_message' => $latest['message'] ?? '',
        'latest_time' => isset($latest['created_at']) ? date('h:i A, M d', strtotime($latest['created_at'])) : ''
    ]);
    exit();
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
?>
