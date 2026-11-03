<?php
// process_booking.php
require_once 'includes/auth_check.php';
require_once 'db_config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Collect and Clean Data
    $booking_id     = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
    $user_id        = $_SESSION['user_id'];
    $event_name     = trim($_POST['event_name']);
    $event_type     = $_POST['event_type'] ?? 'Others';
    $event_date     = $_POST['event_date'];
    $event_time     = $_POST['event_time'];
    $event_end_time = !empty($_POST['event_end_time']) ? $_POST['event_end_time'] : null;
    $pax_count      = intval($_POST['pax_count']);
    $venue          = trim($_POST['venue_location']);
    $venue_address  = trim($_POST['venue_address'] ?? $venue);
    $action         = $_POST['action'] ?? 'save_and_continue'; // Default action

    // 2. Server-side Pax-to-Table Computation (Safety Check)
    $table_count = ceil($pax_count / 10);

    // 3. Start a Transaction (Ensures booking info saves reliably)
    $conn->begin_transaction();

    try {
        if ($booking_id > 0) {
            // Preserve existing package suffix in event_type if customer already selected a package in Step 2
            $prev_stmt = $conn->prepare("SELECT event_type FROM bookings WHERE booking_id = ? AND user_id = ?");
            $prev_stmt->bind_param("ii", $booking_id, $user_id);
            $prev_stmt->execute();
            $prev_row = $prev_stmt->get_result()->fetch_assoc();
            if ($prev_row && preg_match('/(\(Package\s[A-D]\))/', $prev_row['event_type'], $pkg_matches)) {
                if (strpos($event_type, 'Package') === false) {
                    $event_type = trim($event_type) . " " . $pkg_matches[1];
                }
            }

            // Update existing booking info
            $stmt = $conn->prepare("UPDATE bookings SET event_name = ?, event_type = ?, event_date = ?, event_time = ?, event_end_time = ?, pax_count = ?, table_count = ?, venue_location = ?, venue_address = ? WHERE booking_id = ? AND user_id = ?");
            $stmt->bind_param("sssssiissii", $event_name, $event_type, $event_date, $event_time, $event_end_time, $pax_count, $table_count, $venue, $venue_address, $booking_id, $user_id);
            $stmt->execute();
        } else {
            // Insert new booking info
            $stmt = $conn->prepare("INSERT INTO bookings (user_id, event_name, event_type, event_date, event_time, event_end_time, pax_count, table_count, venue_location, venue_address, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->bind_param("isssssiiss", $user_id, $event_name, $event_type, $event_date, $event_time, $event_end_time, $pax_count, $table_count, $venue, $venue_address);
            $stmt->execute();
            $booking_id = $conn->insert_id; 
        }

        // Commit changes to database
        $conn->commit();

        if ($action === 'save_draft') {
            $ref_str = '#BK-' . str_pad($booking_id, 4, '0', STR_PAD_LEFT);
            $draft_msg = "Draft Saved: Event details for '$event_name' ($ref_str) have been saved. You can continue booking anytime.";
            try {
                $n_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, 0)");
                if ($n_stmt) {
                    $n_stmt->bind_param("is", $user_id, $draft_msg);
                    $n_stmt->execute();
                }
            } catch (Throwable $t) {}
            // Redirect to dashboard with a success message for draft
            header("Location: dashboard.php?msg=draft_saved");
        } else {
            // Redirect to Step 2: Menu Selection
            header("Location: menu.php?booking_id=" . $booking_id);
        }
        exit();

    } catch (Exception $e) {
        // If anything fails, undo all changes
        $conn->rollback();
        header("Location: dashboard.php?error=booking_failed");
        exit();
    }
}
?>