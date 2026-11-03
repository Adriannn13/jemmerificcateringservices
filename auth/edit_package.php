<?php
session_start();
require_once '../db_config.php';

// Security: Only admins can edit package configurations
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['package_id'];
    $price = floatval($_POST['price_per_pax']);
    $limit = intval($_POST['main_course_limit']);
    $pasta_limit = intval($_POST['pasta_limit']);
    $veggies_limit = intval($_POST['veggies_limit']);
    $dessert_limit = intval($_POST['dessert_limit']);
    $description = trim($_POST['description']);
    $inclusions = trim($_POST['inclusions']);

    $stmt = $conn->prepare("UPDATE packages SET price_per_pax = ?, main_course_limit = ?, pasta_limit = ?, veggies_limit = ?, dessert_limit = ?, description = ?, inclusions = ? WHERE package_id = ?");
    $stmt->bind_param("diiiisss", $price, $limit, $pasta_limit, $veggies_limit, $dessert_limit, $description, $inclusions, $id);

    if ($stmt->execute()) {
        header("Location: ../inventory.php?msg=success");
    } else {
        header("Location: ../inventory.php?error=update_failed");
    }
    $stmt->close();
} else {
    header("Location: ../inventory.php");
}