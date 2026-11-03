<?php
require_once 'includes/auth_check.php';
require_once 'db_config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $booking_id = intval($_POST['booking_id']);
    $method = $_POST['method'];
    $account_name = trim($_POST['account_name']);
    $ref_no = trim($_POST['ref_no']);
    $mobile_number = trim($_POST['mobile_number']);
    
    $screenshot_path = "";

    // 1. Handle File Upload
    if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === 0) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_ext = strtolower(pathinfo($_FILES["screenshot"]["name"], PATHINFO_EXTENSION));
        $filename = "pay_" . $booking_id . "_" . time() . "." . $file_ext;
        $target_file = $target_dir . $filename;

        if (move_uploaded_file($_FILES["screenshot"]["tmp_name"], $target_file)) {
            $screenshot_path = $filename;
        }
    }

    // 2. Fetch booking total and event name to compute amount being paid
    $b_fetch = $conn->prepare("SELECT total_amount, payment_status, event_name FROM bookings WHERE booking_id = ? AND user_id = ?");
    $b_fetch->bind_param("ii", $booking_id, $user_id);
    $b_fetch->execute();
    $b_data = $b_fetch->get_result()->fetch_assoc();
    $full_total = (float)($b_data['total_amount'] ?? 0);
    $event_name = htmlspecialchars($b_data['event_name'] ?? 'Catering Event');
    $ref_str = '#BK-' . str_pad($booking_id, 4, '0', STR_PAD_LEFT);

    // 3. Determine payment type and amount
    $payment_type = $_POST['payment_type'] ?? 'downpayment';
    $amount_paid  = ($payment_type === 'full') ? $full_total : min($full_total, 10000.00);

    // 4. Insert into Payments Table
    try {
        $stmt = $conn->prepare("INSERT INTO payments (booking_id, user_id, payment_method, account_name, account_number, reference_no, screenshot_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')");
        $stmt->bind_param("iisssss", $booking_id, $user_id, $method, $account_name, $mobile_number, $ref_no, $screenshot_path);
        
        if ($stmt->execute()) {
            // 5. Update booking payment_status and payment_method correctly
            $new_status = ($payment_type === 'full') ? 'fully_paid' : 'partially_paid';
            $u_stmt = $conn->prepare("UPDATE bookings SET payment_status = ?, payment_method = ? WHERE booking_id = ?");
            $u_stmt->bind_param("ssi", $new_status, $method, $booking_id);
            $u_stmt->execute();
            
            // 6. Send Short Message Notification to Customer
            $pay_label = ($payment_type === 'full') ? 'Full Payment' : 'Downpayment';
            $cust_msg = "Booking Placed! Your reservation for '$event_name' ($ref_str) has been submitted successfully with $pay_label via $method. Awaiting admin confirmation.";
            try {
                $n_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, 0)");
                if ($n_stmt) {
                    $n_stmt->bind_param("is", $user_id, $cust_msg);
                    $n_stmt->execute();
                }
            } catch (Throwable $t) {
                error_log("Customer notification insert notice: " . $t->getMessage());
            }

            // 7. Send Notification to Admin Users
            try {
                $client_name = $_SESSION['full_name'] ?? 'Customer';
                $adm_msg = "New Booking: $client_name submitted '$event_name' ($ref_str) with $pay_label via $method. Please verify payment.";
                $adm_res = $conn->query("SELECT user_id FROM users WHERE role = 'admin'");
                if ($adm_res) {
                    $adm_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, 0)");
                    while ($adm_row = $adm_res->fetch_assoc()) {
                        $aid = intval($adm_row['user_id']);
                        $adm_stmt->bind_param("is", $aid, $adm_msg);
                        $adm_stmt->execute();
                    }
                }
            } catch (Throwable $t) {
                error_log("Admin notification insert notice: " . $t->getMessage());
            }
            
            echo json_encode(['status' => 'success', 'message' => 'Payment submitted successfully!']);
            exit();
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error occurred.']);
            exit();
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit();
    }
} else {
    header("Location: dashboard.php");
    exit();
}
?>