<?php
// auth/staff_heartbeat.php - Lightweight activity ping for real-time online status monitoring
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    echo json_encode(['status' => 'unauthenticated', 'online' => false]);
    exit();
}

require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../includes/staff_helper.php';

$user_id = intval($_SESSION['user_id']);
update_user_activity($conn, $user_id);

echo json_encode([
    'status'  => 'ok',
    'online'  => true,
    'user_id' => $user_id,
    'time'    => date('Y-m-d H:i:s')
]);

