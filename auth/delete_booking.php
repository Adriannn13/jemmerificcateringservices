<?php
session_start();
require_once '../db_config.php';

if (!isset($_SESSION['user_id']) && (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin')) {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

$booking_id = intval($_GET['booking_id'] ?? $_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'] ?? 0;
$role = $_SESSION['role'] ?? 'customer';

if ($booking_id > 0) {
    // Check permission: Admin can delete any booking; Customer can delete their own pending/draft booking.
    if ($role === 'admin') {
        $stmt = $conn->prepare("SELECT booking_id FROM bookings WHERE booking_id = ?");
        $stmt->bind_param("i", $booking_id);
    } else {
        $stmt = $conn->prepare("SELECT booking_id FROM bookings WHERE booking_id = ? AND user_id = ? AND status IN ('pending', 'draft')");
        $stmt->bind_param("ii", $booking_id, $user_id);
    }
    
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        $conn->begin_transaction();
        try {
            $conn->query("DELETE FROM booking_items WHERE booking_id = $booking_id");
            $conn->query("DELETE FROM dietary_restrictions WHERE booking_id = $booking_id");
            $conn->query("DELETE FROM payments WHERE booking_id = $booking_id");
            $conn->query("DELETE FROM messages WHERE booking_id = $booking_id");
            $conn->query("DELETE FROM ai_event_plans WHERE booking_id = $booking_id");
            $conn->query("DELETE FROM notifications WHERE message LIKE '%#$booking_id%'");
            $conn->query("DELETE FROM bookings WHERE booking_id = $booking_id");
            $conn->commit();

            $redirect = ($role === 'admin') ? "../admin_schedule.php?msg=deleted" : "../transactions.php?msg=deleted";
            if (isset($_SERVER['HTTP_REFERER'])) {
                $ref = $_SERVER['HTTP_REFERER'];
                if (strpos($ref, 'admin_dashboard.php') !== false) {
                    $redirect = "../admin_dashboard.php?msg=deleted";
                } elseif (strpos($ref, 'dashboard.php') !== false) {
                    $redirect = "../dashboard.php?msg=deleted";
                } elseif (strpos($ref, 'transactions.php') !== false) {
                    $redirect = "../transactions.php?msg=deleted";
                } elseif (strpos($ref, 'admin_requests.php') !== false) {
                    $redirect = "../admin_requests.php?msg=deleted";
                } elseif (strpos($ref, 'admin_schedule.php') !== false) {
                    $redirect = "../admin_schedule.php?msg=deleted";
                }
            }
            header("Location: " . $redirect);
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $redirect = ($role === 'admin') ? "../admin_schedule.php?error=delete_failed" : "../transactions.php?error=delete_failed";
            header("Location: " . $redirect);
            exit();
        }
    } else {
        $redirect = ($role === 'admin') ? "../admin_schedule.php?error=not_found" : "../transactions.php?error=unauthorized";
        header("Location: " . $redirect);
        exit();
    }
}

header("Location: ../transactions.php");
exit();
?>