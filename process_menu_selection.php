<?php
require_once 'includes/auth_check.php';
require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_id = intval($_POST['booking_id']);
    $selected_package = $_POST['selected_package'] ?? null;
    $items = $_POST['items'] ?? [];
    $action = $_POST['action'] ?? 'save_and_summary'; // Default action
    $grand_total = 0;

    // Fetch Booking Details to get Pax Count
    $b_stmt = $conn->prepare("SELECT pax_count, event_type FROM bookings WHERE booking_id = ?");
    $b_stmt->bind_param("i", $booking_id);
    $b_stmt->execute();
    $booking = $b_stmt->get_result()->fetch_assoc();

    if (!$booking) {
        header("Location: dashboard.php?error=booking_not_found");
        exit();
    }

    $pax_count = $booking['pax_count'];

    // Start transaction to ensure data integrity
    $conn->begin_transaction();

    try {
        // Calculate Package Cost if selected
        $limits = [1 => 999, 2 => 999, 3 => 999, 4 => 999, 5 => 999];
        $limits = [1 => 999, 2 => 999, 4 => 999, 5 => 999];
        
        if ($selected_package) {
            $p_stmt = $conn->prepare("SELECT * FROM packages WHERE package_id = ?");
            $p_stmt->bind_param("s", $selected_package);
            $p_stmt->execute();
            $pkg_data = $p_stmt->get_result()->fetch_assoc();

            if ($pkg_data) {
                // Look up tiered pricing
                $t_stmt = $conn->prepare("SELECT package_price FROM package_pricing_tiers WHERE package_id = ? AND pax_threshold >= ? ORDER BY pax_threshold ASC LIMIT 1");
                $t_stmt->bind_param("si", $selected_package, $pax_count);
                $t_stmt->execute();
                $tier_res = $t_stmt->get_result()->fetch_assoc();
                
                // Fallback to highest tier if pax_count exceeds highest threshold
                if (!$tier_res) {
                    $tier_res = $conn->query("SELECT package_price FROM package_pricing_tiers WHERE package_id = '$selected_package' ORDER BY pax_threshold DESC LIMIT 1")->fetch_assoc();
                }

                $package_total = (float)($tier_res['package_price'] ?? 0);
                $grand_total += $package_total;
                $limits[1] = intval($pkg_data['main_course_limit']);
                $limits[2] = intval($pkg_data['pasta_limit']);
                $limits[4] = intval($pkg_data['veggies_limit']);
                $limits[3] = intval($pkg_data['soup_limit']);
                $limits[5] = intval($pkg_data['dessert_limit']);

                // Clean existing package info from event_type to prevent duplication
                $base_event_type = preg_replace('/\s\(Package\s[A-D]\)/', '', $booking['event_type']);
                $new_event_type = $base_event_type . " (" . $pkg_data['package_name'] . ")";
                $up_pkg = $conn->prepare("UPDATE bookings SET event_type = ? WHERE booking_id = ?");
                $up_pkg->bind_param("si", $new_event_type, $booking_id);
                $up_pkg->execute();
            }
        }

        // Clear previous selections for this booking if any
        $delete_query = "DELETE FROM booking_items WHERE booking_id = ?";
        $del_stmt = $conn->prepare($delete_query);
        $del_stmt->bind_param("i", $booking_id);
        $del_stmt->execute();

        $selected_item_ids = array_keys(array_filter($items, function($v) { return intval($v['qty']) > 0; }));
        $counts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        $counts = [1 => 0, 2 => 0, 4 => 0, 5 => 0];

        if (!empty($selected_item_ids)) {
            $placeholders = implode(',', array_fill(0, count($selected_item_ids), '?'));
            $cat_res = $conn->query("SELECT category_id, COUNT(*) as count FROM menu_items WHERE item_id IN (" . implode(',', array_map('intval', $selected_item_ids)) . ") AND category_id IN (1, 2, 3, 4, 5) GROUP BY category_id");
            $cat_res = $conn->query("SELECT category_id, COUNT(*) as count FROM menu_items WHERE item_id IN (" . implode(',', array_map('intval', $selected_item_ids)) . ") AND category_id IN (1, 2, 4, 5) GROUP BY category_id");
            while ($c_row = $cat_res->fetch_assoc()) {
                $counts[$c_row['category_id']] = $c_row['count'];
            }
        }

        // Strict Validation: Reject if restricted items are selected without a base package
        if (($counts[1] > 0 || $counts[2] > 0 || $counts[4] > 0 || $counts[5] > 0) && !$selected_package) {
            throw new Exception("Security/Business Logic Violation: A base package is required for these selections.");
        }

        if ($selected_package) {
            if ($counts[1] > $limits[1]) throw new Exception("Main course limit exceeded ({$counts[1]}/{$limits[1]}).");
            if ($counts[2] > $limits[2]) throw new Exception("Pasta limit exceeded or not included ({$counts[2]}/{$limits[2]}).");
            if ($counts[4] > $limits[4]) throw new Exception("Veggies limit exceeded or not included ({$counts[4]}/{$limits[4]}).");
            if ($counts[5] > $limits[5]) throw new Exception("Dessert limit exceeded or not included ({$counts[5]}/{$limits[5]}).");
        }

        $compiled_dietary = [];
        foreach ($items as $item_id => $details) {
            $qty = intval($details['qty']);
            $price = floatval($details['price']);
            $diet_note = trim($details['dietary_notes'] ?? '');

            if ($qty > 0) {
                $subtotal = $qty * $price;
                $grand_total += $subtotal;

                // Insert into booking_items table (Breakdown table)
                $ins_query = "INSERT INTO booking_items (booking_id, item_id, quantity, unit_price, subtotal, dietary_notes) VALUES (?, ?, ?, ?, ?, ?)";
                $ins_stmt = $conn->prepare($ins_query);
                $ins_stmt->bind_param("iiidds", $booking_id, $item_id, $qty, $price, $subtotal, $diet_note);
                $ins_stmt->execute();

                if (!empty($diet_note)) {
                    $d_res = $conn->query("SELECT item_name FROM menu_items WHERE item_id = " . intval($item_id));
                    $d_name = ($d_row = $d_res->fetch_assoc()) ? $d_row['item_name'] : "Item #$item_id";
                    $compiled_dietary[] = "• $d_name: $diet_note";
                }
            }
        }

        // Save compiled dietary notes into bookings and dietary_restrictions
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

        // Update the main bookings table with the new Total Amount
        $update_booking = "UPDATE bookings SET total_amount = ? WHERE booking_id = ?";
        $up_stmt = $conn->prepare($update_booking);
        $up_stmt->bind_param("di", $grand_total, $booking_id);
        $up_stmt->execute();

        $conn->commit();
        if ($action === 'save_draft') {
            header("Location: dashboard.php?msg=draft_saved");
        } else {
            header("Location: summary.php?booking_id=" . $booking_id);
        }
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        die("Error processing selection: " . $e->getMessage());
    }
}