<?php
session_start();
require_once '../db_config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['item_name']);
    $cat_id = intval($_POST['category_id']);
    $price = floatval($_POST['price']);
    $description = trim($_POST['description']);
    $recipe = trim($_POST['recipe']);
    
    $image_db_path = "";
    
    // Handle File Upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "../uploads/";
        
        // Ensure directory exists
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $extension = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
        $filename = uniqid('dish_') . "." . $extension;
        $target_file = $target_dir . $filename;
        
        // Move file to folder
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            // Save the path relative to the root for easy access in <img> tags
            $image_db_path = "uploads/" . $filename; 
        }
    }

    $stmt = $conn->prepare("INSERT INTO menu_items (item_name, category_id, price, description, recipe, image_path) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sidsss", $name, $cat_id, $price, $description, $recipe, $image_db_path);

    if ($stmt->execute()) {
        header("Location: ../inventory.php?msg=success");
    } else {
        die("Critical Database Error: " . $conn->error);
    }
}
?>