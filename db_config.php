<?php
// db_config.php - Hostinger & Localhost Database Connection
require_once __DIR__ . '/includes/env_loader.php';

$host     = getenv('DB_HOST') ?: "localhost";
$db_name  = getenv('DB_NAME') ?: "jemmerific_db";
$username = getenv('DB_USER') ?: "root";
$password = (getenv('DB_PASS') !== false && getenv('DB_PASS') !== null) ? getenv('DB_PASS') : "";

// Using MySQLi with error reporting enabled
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $username, $password, $db_name);
    $conn->set_charset("utf8mb4");

    // Auto-ensure notifications, messages, and staff schema (run once per user session)
    if (session_status() === PHP_SESSION_ACTIVE && empty($_SESSION['db_schema_checked'])) {
        try {
            $conn->query("ALTER TABLE `notifications` MODIFY `notif_id` INT(11) NOT NULL AUTO_INCREMENT");
        } catch (Throwable $t) {}

        try {
            $conn->query("ALTER TABLE `messages` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT");
        } catch (Throwable $t) {}

        try {
            $conn->query("ALTER TABLE `messages` ADD INDEX `idx_booking_msg_id` (`booking_id`, `id`)");
        } catch (Throwable $t) {}

        try {
            $conn->query("ALTER TABLE `messages` ADD INDEX `idx_receiver_unread` (`receiver_id`, `is_read`)");
        } catch (Throwable $t) {}

        try {
            $conn->query("ALTER TABLE `payments` MODIFY `payment_method` VARCHAR(50) DEFAULT NULL");
        } catch (Throwable $t) {}

        try {
            $conn->query("ALTER TABLE `booking_items` ADD COLUMN `dietary_notes` TEXT NULL");
        } catch (Throwable $t) {}

        // Ensure users table supports staff role, phone, plain_password, permissions, last_active, is_online
        try {
            $conn->query("ALTER TABLE `users` MODIFY `role` VARCHAR(50) DEFAULT 'client'");
        } catch (Throwable $t) {}

        try {
            $conn->query("ALTER TABLE `users` ADD COLUMN `phone` VARCHAR(30) NULL DEFAULT NULL AFTER `email`");
        } catch (Throwable $t) {}

        try {
            $conn->query("ALTER TABLE `users` ADD COLUMN `plain_password` VARCHAR(255) NULL DEFAULT NULL AFTER `password`");
        } catch (Throwable $t) {}

        try {
            $conn->query("ALTER TABLE `users` ADD COLUMN `permissions` TEXT NULL DEFAULT NULL AFTER `role`");
        } catch (Throwable $t) {}

        try {
            $conn->query("ALTER TABLE `users` ADD COLUMN `last_active` DATETIME NULL DEFAULT NULL");
        } catch (Throwable $t) {}

        try {
            $conn->query("ALTER TABLE `users` ADD COLUMN `is_online` TINYINT(1) NOT NULL DEFAULT 0");
        } catch (Throwable $t) {}

        $_SESSION['db_schema_checked'] = true;
    }
} catch (mysqli_sql_exception $e) {
    error_log($e->getMessage());
    $is_api = (strpos($_SERVER['REQUEST_URI'] ?? '', 'auth/') !== false)
           || (strpos($_SERVER['REQUEST_URI'] ?? '', '_api') !== false)
           || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
    if ($is_api) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error',
            'success' => false,
            'message' => 'Database connection failed: ' . $e->getMessage(),
            'error' => 'Database connection failed',
            'reply' => 'Database connection error. Please verify your database credentials.',
            'conversations' => [],
            'bookings' => [],
            'messages' => []
        ]);
        exit;
    }
    exit('Error connecting to database. Please check your database credentials in db_config.php.'); 
}
?>
