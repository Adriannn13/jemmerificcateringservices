<?php
session_start();
require_once '../db_config.php';

// Security: Only admins and authorized staff can update status
$role = strtolower($_SESSION['role'] ?? '');
$staff_perms = $_SESSION['permissions'] ?? [];
$can_manage_bookings = ($role === 'admin' || ($role === 'staff' && in_array('booking_requests', $staff_perms)));

if (!isset($_SESSION['logged_in']) || !$can_manage_bookings) {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'])) {
    $booking_id = intval($_POST['booking_id']);
    $new_status = $_POST['status'] ?? null;
    $pay_status = $_POST['payment_status'] ?? null;

    // Get user data and current booking financials
    $u_stmt = $conn->prepare("SELECT b.user_id, b.total_amount, b.payment_status, b.event_name, u.email, u.full_name FROM bookings b JOIN users u ON b.user_id = u.user_id WHERE b.booking_id = ?");
    $u_stmt->bind_param("i", $booking_id);
    $u_stmt->execute();
    $user_data = $u_stmt->get_result()->fetch_assoc();
    $user_id = $user_data['user_id'];
    $current_pay_status = $user_data['payment_status'];
    $booking_total = $user_data['total_amount'];
    $client_email = $user_data['email'];
    $client_name = $user_data['full_name'];
    $event_name = htmlspecialchars($user_data['event_name'] ?? 'Catering Event');
    $ref_str = "#BK-" . str_pad($booking_id, 4, '0', STR_PAD_LEFT);

    $conn->begin_transaction();

    try {
        $notif_msg = "";

        // If status update is requested
        if ($new_status === 'cancelled') {
            header("Location: ../admin_schedule.php?error=use_cancel_dialog");
            exit();
        }

        if ($new_status) {
            $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE booking_id = ?");
            $stmt->bind_param("si", $new_status, $booking_id);
            $stmt->execute();
            
            $notif_msgs = [
                'confirmed' => "Booking Confirmed! Your booking '$event_name' ($ref_str) has been verified and confirmed.",
                'on_progress' => "Event In Progress! Our catering team is preparing '$event_name' ($ref_str).",
                'completed' => "Event Completed! Thank you for choosing JEMmerific for your event '$event_name' ($ref_str). We hope you and your guests enjoyed the celebration!"
            ];
            $notif_msg = $notif_msgs[$new_status] ?? "Status updated for '$event_name' ($ref_str);";
            
            if ($new_status === 'confirmed') {
                $conn->query("UPDATE payments SET status = 'Verified' WHERE booking_id = $booking_id");
                $pay_stmt = $conn->prepare("UPDATE bookings SET payment_status = 'partially_paid' WHERE booking_id = ?");
                $pay_stmt->bind_param("i", $booking_id);
                $pay_stmt->execute();
                $pay_stmt->close();
            }
            if ($new_status === 'completed') {
                $conn->query("UPDATE payments SET status = 'Verified' WHERE booking_id = $booking_id");
                $pay_stmt = $conn->prepare("UPDATE bookings SET payment_status = 'fully_paid' WHERE booking_id = ?");
                $pay_stmt->bind_param("i", $booking_id);
                $pay_stmt->execute();
                $pay_stmt->close();
            }
        }

        // If manual "Mark as Paid" is clicked - for in-person full payments, also complete booking
        if ($pay_status === 'fully_paid') {
            $conn->query("UPDATE bookings SET payment_status = 'fully_paid', status = 'completed' WHERE booking_id = $booking_id");
            $conn->query("UPDATE payments SET status = 'Verified' WHERE booking_id = $booking_id");
            $notif_msg = "Event Completed! Booking '$event_name' ($ref_str) is marked complete & fully paid. Thank you for choosing JEMmerific Catering!";
        }

        // Send Notification
        if (!empty($notif_msg)) {
            $n_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $n_stmt->bind_param("is", $user_id, $notif_msg);
            $n_stmt->execute();

            // Send Automated Email Notification
            $subject = "Booking Update - JEMmerific Catering (#BK-" . str_pad($booking_id, 4, '0', STR_PAD_LEFT) . ")";
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: JEMmerific Catering <noreply@jemmerific.com>" . "\r\n";

            $email_body = "
                <div style='font-family: sans-serif; max-width: 600px; margin: auto; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
                    <h2 style='color: #7c3aed;'>Hello $client_name,</h2>
                    <p style='font-size: 16px; color: #333; line-height: 1.6;'>$notif_msg</p>
                    <p style='font-size: 14px; color: #666;'>You can view your updated status and download your receipt directly from your dashboard.</p>
                    <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                    <p style='font-size: 12px; color: #999; text-align: center;'>This is an automated message. Please do not reply.</p>
                </div>
            ";
            
            @mail($client_email, $subject, $email_body, $headers);
        }

        $conn->commit();
        header("Location: ../admin_schedule.php?msg=status_updated");
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: ../admin_schedule.php?error=update_failed");
    }
    $stmt->close();
} else {
    header("Location: ../admin_schedule.php");
}
$conn->close();
?>