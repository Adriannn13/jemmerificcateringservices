<?php
require_once '../includes/auth_check.php';
require_once '../db_config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_id = intval($_POST['booking_id']);
    $selected_package = $_POST['selected_package'] ?? null;
    $items = $_POST['items'] ?? [];
    $grand_total = 0;

    // Fetch Booking Details
    $b_stmt = $conn->prepare("SELECT pax_count, event_type FROM bookings WHERE booking_id = ? AND user_id = ?");
    $user_id = $_SESSION['user_id'];
    $b_stmt->bind_param("ii", $booking_id, $user_id);
    $b_stmt->execute();
    $booking = $b_stmt->get_result()->fetch_assoc();

    if (!$booking) {
        echo json_encode(['status' => 'error', 'message' => 'Booking not found']);
        exit();
    }

    $pax_count = $booking['pax_count'];
    $conn->begin_transaction();

    try {
        // Calculate Package Cost
        if ($selected_package) {
            $p_stmt = $conn->prepare("SELECT package_name FROM packages WHERE package_id = ?");
            $p_stmt->bind_param("s", $selected_package);
            $p_stmt->execute();
            $pkg_data = $p_stmt->get_result()->fetch_assoc();

            if ($pkg_data) {
                $t_stmt = $conn->prepare("SELECT package_price FROM package_pricing_tiers WHERE package_id = ? AND pax_threshold >= ? ORDER BY pax_threshold ASC LIMIT 1");
                $t_stmt->bind_param("si", $selected_package, $pax_count);
                $t_stmt->execute();
                $tier_res = $t_stmt->get_result()->fetch_assoc();
                
                if (!$tier_res) {
                    $tier_res = $conn->query("SELECT package_price FROM package_pricing_tiers WHERE package_id = '$selected_package' ORDER BY pax_threshold DESC LIMIT 1")->fetch_assoc();
                }

                $grand_total += (float)($tier_res['package_price'] ?? 0);

                $base_event_type = preg_replace('/\s\(Package\s[A-D]\)/', '', $booking['event_type']);
                $new_event_type = $base_event_type . " (" . $pkg_data['package_name'] . ")";
                $up_pkg = $conn->prepare("UPDATE bookings SET event_type = ? WHERE booking_id = ?");
                $up_pkg->bind_param("si", $new_event_type, $booking_id);
                $up_pkg->execute();
            }
        }

        // Clear and Update Items
        $del_stmt = $conn->prepare("DELETE FROM booking_items WHERE booking_id = ?");
        $del_stmt->bind_param("i", $booking_id);
        $del_stmt->execute();

        $compiled_dietary = [];
        foreach ($items as $item_id => $details) {
            $qty = intval($details['qty']);
            $price = floatval($details['price']);
            $diet_note = trim($details['dietary_notes'] ?? '');

            if ($qty > 0) {
                $subtotal = $qty * $price;
                $grand_total += $subtotal;

                $ins_stmt = $conn->prepare("INSERT INTO booking_items (booking_id, item_id, quantity, unit_price, subtotal, dietary_notes) VALUES (?, ?, ?, ?, ?, ?)");
                $ins_stmt->bind_param("iiidds", $booking_id, $item_id, $qty, $price, $subtotal, $diet_note);
                $ins_stmt->execute();

                if (!empty($diet_note)) {
                    $d_res = $conn->query("SELECT item_name FROM menu_items WHERE item_id = " . intval($item_id));
                    $d_name = ($d_row = $d_res->fetch_assoc()) ? $d_row['item_name'] : "Item #$item_id";
                    $compiled_dietary[] = "• $d_name: $diet_note";
                }
            }
        }

        $all_dietary_str = implode("\n", $compiled_dietary);
        if (!empty($all_dietary_str)) {
            $up_notes = $conn->prepare("UPDATE bookings SET special_requests = ? WHERE booking_id = ?");
            $up_notes->bind_param("si", $all_dietary_str, $booking_id);
            $up_notes->execute();

            $check_diet = $conn->prepare("SELECT restriction_id FROM dietary_restrictions WHERE booking_id = ?");
            $check_diet->bind_param("i", $booking_id);
            $check_diet->execute();
            if ($check_diet->get_result()->num_rows > 0) {
                $stmt_diet = $conn->prepare("UPDATE dietary_restrictions SET restriction_type = ? WHERE booking_id = ?");
                $stmt_diet->bind_param("si", $all_dietary_str, $booking_id);
            } else {
                $stmt_diet = $conn->prepare("INSERT INTO dietary_restrictions (booking_id, restriction_type) VALUES (?, ?)");
                $stmt_diet->bind_param("is", $booking_id, $all_dietary_str);
            }
            $stmt_diet->execute();
        }

        // Update Total
        $up_stmt = $conn->prepare("UPDATE bookings SET total_amount = ? WHERE booking_id = ?");
        $up_stmt->bind_param("di", $grand_total, $booking_id);
        $up_stmt->execute();

        $conn->commit();
        echo json_encode(['status' => 'success', 'total' => $grand_total, 'time' => date('H:i')]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>