<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once '../db_config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit();
}

$booking_id = intval($_POST['booking_id'] ?? 0);
$cancel_reason = trim($_POST['cancel_reason'] ?? '');
$refund_amount = floatval($_POST['refund_amount'] ?? 0);
$payment_status = $_POST['payment_status'] ?? '';

if ($booking_id <= 0 || empty($cancel_reason)) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit();
}

$conn->begin_transaction();

try {
    // Fetch booking and user info
    $stmt = $conn->prepare("SELECT b.total_amount, b.payment_status, b.user_id, u.email, u.full_name 
                            FROM bookings b 
                            JOIN users u ON b.user_id = u.user_id 
                            WHERE b.booking_id = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    
    if (!$booking) {
        throw new Exception('Booking not found');
    }

    $user_id = $booking['user_id'];
    $client_email = $booking['email'];
    $client_name = $booking['full_name'];

    // Update booking
    $u_stmt = $conn->prepare("UPDATE bookings SET 
                              status = 'cancelled', 
                              payment_status = 'refunded',
                              cancel_reason = ?, 
                              refund_amount = ? 
                              WHERE booking_id = ?");
    $u_stmt->bind_param("sdi", $cancel_reason, $refund_amount, $booking_id);
    $u_stmt->execute();

    // Update payments
    $conn->query("UPDATE payments SET status = 'Refunded' WHERE booking_id = $booking_id");

    // Notification message
    $bk_num = str_pad($booking_id, 4, '0', STR_PAD_LEFT);
    $notif_msg = "Booking #BK-{$bk_num} has been cancelled. Reason: {$cancel_reason}. ";
    if ($refund_amount > 0) {
        $notif_msg .= "Downpayment of ₱" . number_format($refund_amount, 2) . " refunded.";
    } else {
        $notif_msg .= "No refund applicable.";
    }

    // Insert notification
    $n_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    $n_stmt->bind_param("is", $user_id, $notif_msg);
    $n_stmt->execute();

    // Email
    $subject = "Booking Cancelled - JEMmerific (#BK-{$bk_num})";
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: JEMmerific <noreply@jemmerific.com>\r\n";

    $email_body = "
        <div style='font-family: sans-serif; max-width: 600px; margin: auto; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
            <h2 style='color: #ef4444;'>Hello {$client_name},</h2>
            <p style='font-size: 16px; color: #333; line-height: 1.6;'>Your booking #BK-{$bk_num} has been cancelled.</p>
            <p><strong>Reason:</strong> " . htmlspecialchars($cancel_reason) . "</p>";
    
    if ($refund_amount > 0) {
        $email_body .= "<p><strong>Refunded Amount:</strong> ₱" . number_format($refund_amount, 2) . "</p>";
    }
    
    $email_body .= "
            <p>Please contact us if you have questions.</p>
            <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
            <p style='font-size: 12px; color: #999; text-align: center;'>Automated message. Do not reply.</p>
        </div>
    ";

    @mail($client_email, $subject, $email_body, $headers);

    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Booking cancelled successfully. Client notified.'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>
