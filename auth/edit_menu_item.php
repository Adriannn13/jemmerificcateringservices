<?php
session_start();
require_once '../db_config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $id = $_POST['item_id'];
    $name = $conn->real_escape_string($_POST['item_name']);
    $cat = $_POST['category_id'];
    $price = $_POST['price'];
    $desc = $conn->real_escape_string($_POST['description']);
    $recipe = $conn->real_escape_string($_POST['recipe']);

    // Handle Image Upload if provided
    $img_sql = "";
    if (!empty($_FILES['image']['name'])) {
        $target_dir = "../uploads/menu/";
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
        
        $file_ext = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
        $file_name = time() . "_" . uniqid() . "." . $file_ext;
        $target_file = $target_dir . $file_name;
        $db_path = "uploads/menu/" . $file_name;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $img_sql = ", image_path='$db_path'";
        }
    }

    $sql = "UPDATE menu_items SET 
            item_name='$name', 
            category_id='$cat', 
            price='$price', 
            description='$desc', 
            recipe='$recipe' 
            $img_sql 
            WHERE item_id='$id'";

    if ($conn->query($sql)) {
        header("Location: ../inventory.php?success=updated");
    } else {
        header("Location: ../inventory.php?error=failed");
    }
}