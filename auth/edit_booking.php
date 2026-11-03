<?php
require_once '../includes/admin_auth_check.php';
require_once '../db_config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $booking_id = intval($_POST['booking_id']);
    $pax_count = intval($_POST['pax_count']);
    $venue = trim($_POST['venue_location']);
    
    // Recalculate tables (standard logic: 1 table per 10 guests)
    $table_count = ceil($pax_count / 10);

    // Get user_id for notification
    $u_stmt = $conn->prepare("SELECT user_id FROM bookings WHERE booking_id = ?");
    $u_stmt->bind_param("i", $booking_id);
    $u_stmt->execute();
    $booking_data = $u_stmt->get_result()->fetch_assoc();
    
    if (!$booking_data) {
        header("Location: ../admin_schedule.php?error=not_found");
        exit();
    }
    
    $user_id = $booking_data['user_id'];

    $conn->begin_transaction();

    try {
        // Update Booking logistics
        $stmt = $conn->prepare("UPDATE bookings SET pax_count = ?, venue_location = ?, table_count = ? WHERE booking_id = ?");
        $stmt->bind_param("isii", $pax_count, $venue, $table_count, $booking_id);
        $stmt->execute();

        // Notify the client about logistics update
        $notif_msg = "Logistics Update: Your booking #BK-" . str_pad($booking_id, 4, '0', STR_PAD_LEFT) . " logistics (Pax/Venue) have been modified by the admin.";
        $n_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $n_stmt->bind_param("is", $user_id, $notif_msg);
        $n_stmt->execute();

        $conn->commit();
        header("Location: ../admin_schedule.php?msg=updated");
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: ../admin_schedule.php?error=failed");
    }
} else {
    header("Location: ../admin_schedule.php");
}
?>