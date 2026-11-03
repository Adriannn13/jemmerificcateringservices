<?php
session_start();
require_once '../db_config.php';

$role = strtolower($_SESSION['role'] ?? '');
$staff_perms = $_SESSION['permissions'] ?? [];
$can_edit_menu = ($role === 'admin' || ($role === 'staff' && in_array('menu_catalog', $staff_perms)));

if (isset($_GET['id']) && $can_edit_menu) {
    $id = $conn->real_escape_string($_GET['id']);
    
    // Optional: Get image path to delete the physical file from server
    $res = $conn->query("SELECT image_path FROM menu_items WHERE item_id='$id'");
    $item = $res->fetch_assoc();
    if($item && !empty($item['image_path']) && file_exists("../" . $item['image_path'])) {
        unlink("../" . $item['image_path']);
    }

    if ($conn->query("DELETE FROM menu_items WHERE item_id='$id'")) {
        header("Location: ../inventory.php?success=deleted");
    } else {
        header("Location: ../inventory.php?error=delete_failed");
    }
}