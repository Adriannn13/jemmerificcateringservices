<?php
// auth/toggle_availability.php - Toggle Menu Item Availability
session_start();
require_once '../db_config.php';

// Security Check: Admin or staff with menu_catalog permission
$role = strtolower($_SESSION['role'] ?? '');
$staff_perms = $_SESSION['permissions'] ?? [];
$can_edit_menu = ($role === 'admin' || ($role === 'staff' && in_array('menu_catalog', $staff_perms)));

if (!isset($_SESSION['logged_in']) || !$can_edit_menu) {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

if (isset($_GET['id']) && isset($_GET['status'])) {
    $id = intval($_GET['id']);
    $status = intval($_GET['status']);

    $stmt = $conn->prepare("UPDATE menu_items SET is_available = ? WHERE item_id = ?");
    $stmt->bind_param("ii", $status, $id);

    if ($stmt->execute()) {
        header("Location: ../inventory.php?msg=status_updated");
    } else {
        header("Location: ../inventory.php?error=update_failed");
    }
    $stmt->close();
} else {
    header("Location: ../inventory.php");
}
?>

