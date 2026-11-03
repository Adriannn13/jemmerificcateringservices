<?php
require_once '../includes/auth_check.php';
require_once '../db_config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $booking_id  = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
    $event_name  = trim($_POST['event_name'] ?? '');
    $event_type  = $_POST['event_type'] ?? 'Others';
    $event_date     = $_POST['event_date'] ?? null;
    $event_time     = $_POST['event_time'] ?? null;
    $event_end_time = !empty($_POST['event_end_time']) ? $_POST['event_end_time'] : null;
    $pax_count      = intval($_POST['pax_count'] ?? 0);
    $venue          = trim($_POST['venue_location'] ?? '');
    $venue_address  = trim($_POST['venue_address'] ?? $venue);
    
    $table_count = ceil($pax_count / 10);

    // Validate date availability
    if (!empty($event_date)) {
        $v_stmt = $conn->prepare("SELECT status, max_events, (SELECT COUNT(*) FROM bookings WHERE status IN ('confirmed', 'on_progress', 'completed') AND event_date = ?) as confirmed_cnt FROM availability_schedule WHERE schedule_date = ?");
        $v_stmt->bind_param("ss", $event_date, $event_date);
        $v_stmt->execute();
        $v_row = $v_stmt->get_result()->fetch_assoc();

        if ($v_row) {
            $status = $v_row['status'];
            $max_events = intval($v_row['max_events']);
            $confirmed_cnt = intval($v_row['confirmed_cnt']);

            if ($status === 'Closed' || $status === 'Holiday' || ($max_events > 0 && $confirmed_cnt >= $max_events)) {
                echo json_encode(['status' => 'error', 'message' => 'Selected date is unavailable. Please choose another date.']);
                exit();
            }
        } else {
            $day_of_week = date('N', strtotime($event_date));
            if ($day_of_week == 6 || $day_of_week == 7) {
                echo json_encode(['status' => 'error', 'message' => 'Selected date is closed on weekends. Please choose an available date.']);
                exit();
            }
        }
    }

    try {
        if ($booking_id > 0) {
            // Preserve package suffix if customer already picked package in Step 2
            $prev_stmt = $conn->prepare("SELECT event_type FROM bookings WHERE booking_id = ? AND user_id = ?");
            $prev_stmt->bind_param("ii", $booking_id, $user_id);
            $prev_stmt->execute();
            $prev_row = $prev_stmt->get_result()->fetch_assoc();
            if ($prev_row && preg_match('/(\(Package\s[A-D]\))/', $prev_row['event_type'], $pkg_matches)) {
                if (strpos($event_type, 'Package') === false) {
                    $event_type = trim($event_type) . " " . $pkg_matches[1];
                }
            }

            // Update existing record
            $stmt = $conn->prepare("UPDATE bookings SET event_name = ?, event_type = ?, event_date = ?, event_time = ?, event_end_time = ?, pax_count = ?, table_count = ?, venue_location = ?, venue_address = ? WHERE booking_id = ? AND user_id = ?");
            $stmt->bind_param("sssssiissii", $event_name, $event_type, $event_date, $event_time, $event_end_time, $pax_count, $table_count, $venue, $venue_address, $booking_id, $user_id);
            $stmt->execute();
        } else {
            // Create new record
            $stmt = $conn->prepare("INSERT INTO bookings (user_id, event_name, event_type, event_date, event_time, event_end_time, pax_count, table_count, venue_location, venue_address, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->bind_param("isssssiiss", $user_id, $event_name, $event_type, $event_date, $event_time, $event_end_time, $pax_count, $table_count, $venue, $venue_address);
            $stmt->execute();
            $booking_id = $conn->insert_id;
        }
        
        echo json_encode(['status' => 'success', 'booking_id' => $booking_id, 'time' => date('H:i')]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>