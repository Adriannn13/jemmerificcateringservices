<?php
// auth/availability_api.php - Central Availability Calendar API Engine
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/auto_migrate_availability.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'fetch_month':
        $year = intval($_GET['year'] ?? date('Y'));
        $month = intval($_GET['month'] ?? date('n'));
        if ($month < 1) $month = 1;
        if ($month > 12) $month = 12;
        if ($year < 2020 || $year > 2050) $year = intval(date('Y'));

        $start_date = sprintf('%04d-%02d-01', $year, $month);
        $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $end_date = sprintf('%04d-%02d-%02d', $year, $month, $days_in_month);

        // 1. Fetch custom availability configurations
        $avail_rows = [];
        $a_stmt = $conn->prepare("SELECT * FROM availability_schedule WHERE schedule_date BETWEEN ? AND ?");
        $a_stmt->bind_param("ss", $start_date, $end_date);
        $a_stmt->execute();
        $a_res = $a_stmt->get_result();
        while ($row = $a_res->fetch_assoc()) {
            $avail_rows[$row['schedule_date']] = $row;
        }

        // 2. Fetch approved booking counts per date (only approved bookings count toward capacity)
        $booking_counts = [];
        $b_stmt = $conn->prepare("SELECT event_date, COUNT(*) as cnt FROM bookings WHERE status IN ('confirmed', 'on_progress', 'completed') AND event_date BETWEEN ? AND ? GROUP BY event_date");
        $b_stmt->bind_param("ss", $start_date, $end_date);
        $b_stmt->execute();
        $b_res = $b_stmt->get_result();
        while ($brow = $b_res->fetch_assoc()) {
            $booking_counts[$brow['event_date']] = intval($brow['cnt']);
        }

        // 3. Build comprehensive month schedule map
        $schedule = [];
        $stats = [
            'available_days' => 0,
            'fully_booked'   => 0,
            'closed'         => 0,
            'upcoming_events'=> 0
        ];

        $today = date('Y-m-d');

        for ($d = 1; $d <= $days_in_month; $d++) {
            $current_date = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $day_of_week = date('N', strtotime($current_date)); // 1 (Mon) to 7 (Sun)
            $is_weekend = ($day_of_week == 6 || $day_of_week == 7);

            $confirmed_cnt = $booking_counts[$current_date] ?? 0;
            if ($current_date >= $today) {
                $stats['upcoming_events'] += $confirmed_cnt;
            }

            if (isset($avail_rows[$current_date])) {
                $cfg = $avail_rows[$current_date];
                $max_events = intval($cfg['max_events']);
                $status = $cfg['status'];
                $reason = $cfg['reason'] ?? '';
                $notes = $cfg['notes'] ?? '';
            } else {
                // Default schedule rules: Weekdays open (Max 3), Weekends closed
                if ($is_weekend) {
                    $status = 'Closed';
                    $max_events = 0;
                    $reason = 'Weekend Closure';
                    $notes = '';
                } else {
                    $status = 'Available';
                    $max_events = 3;
                    $reason = 'Regular Operations';
                    $notes = '';
                }
            }

            // Automatic capacity recalculation if not manually Closed or Holiday
            if ($status !== 'Closed' && $status !== 'Holiday') {
                if ($max_events > 0 && $confirmed_cnt >= $max_events) {
                    $status = 'Fully Booked';
                } elseif ($max_events > 0 && ($max_events - $confirmed_cnt) <= 1 && $status !== 'Fully Booked') {
                    $status = 'Limited Slots';
                }
            }

            $remaining_slots = max(0, $max_events - $confirmed_cnt);

            // Accumulate statistics
            if ($status === 'Available' || $status === 'Limited Slots') {
                $stats['available_days']++;
            } elseif ($status === 'Fully Booked') {
                $stats['fully_booked']++;
            } elseif ($status === 'Closed' || $status === 'Holiday') {
                $stats['closed']++;
            }

            $schedule[$current_date] = [
                'date'            => $current_date,
                'day'             => $d,
                'day_of_week'     => $day_of_week,
                'status'          => $status,
                'max_events'      => $max_events,
                'current_events'  => $confirmed_cnt,
                'remaining_slots' => $remaining_slots,
                'reason'          => $reason,
                'notes'           => $notes,
                'is_selectable'   => ($status === 'Available' || $status === 'Limited Slots')
            ];
        }

        echo json_encode([
            'success'  => true,
            'year'     => $year,
            'month'    => $month,
            'schedule' => $schedule,
            'stats'    => $stats
        ]);
        break;

    case 'save_daily_config':
        // Admin or authorized staff check
        $caller_role = strtolower($_SESSION['role'] ?? '');
        $staff_perms = $_SESSION['permissions'] ?? [];
        $can_edit_schedule = ($caller_role === 'admin' || ($caller_role === 'staff' && in_array('schedule_events', $staff_perms)));
        if (!isset($_SESSION['logged_in']) || !$can_edit_schedule) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit();
        }

        $schedule_date = trim($_POST['schedule_date'] ?? '');
        $status        = trim($_POST['status'] ?? 'Available');
        $max_events    = max(0, intval($_POST['max_events'] ?? 3));
        $reason        = trim($_POST['reason'] ?? '');
        $notes         = trim($_POST['notes'] ?? '');

        if (empty($schedule_date) || !strtotime($schedule_date)) {
            echo json_encode(['success' => false, 'error' => 'Invalid schedule date']);
            exit();
        }

        $allowed_statuses = ['Available', 'Limited Slots', 'Fully Booked', 'Closed', 'Holiday'];
        if (!in_array($status, $allowed_statuses)) {
            $status = 'Available';
        }

        // Count confirmed/approved bookings for that date
        $b_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM bookings WHERE status IN ('confirmed', 'on_progress', 'completed') AND event_date = ?");
        $b_stmt->bind_param("s", $schedule_date);
        $b_stmt->execute();
        $confirmed_cnt = intval($b_stmt->get_result()->fetch_assoc()['cnt'] ?? 0);

        $remaining_slots = max(0, $max_events - $confirmed_cnt);

        // Insert or Update availability_schedule
        $sql = "INSERT INTO availability_schedule (schedule_date, status, max_events, current_events, remaining_slots, reason, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                status = VALUES(status),
                max_events = VALUES(max_events),
                current_events = VALUES(current_events),
                remaining_slots = VALUES(remaining_slots),
                reason = VALUES(reason),
                notes = VALUES(notes)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssiiiss", $schedule_date, $status, $max_events, $confirmed_cnt, $remaining_slots, $reason, $notes);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Availability settings saved successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save settings: ' . $conn->error]);
        }
        break;

    case 'fetch_day_bookings':
        $caller_role = strtolower($_SESSION['role'] ?? '');
        $staff_perms = $_SESSION['permissions'] ?? [];
        $can_view_schedule = ($caller_role === 'admin' || ($caller_role === 'staff' && in_array('schedule_events', $staff_perms)));
        if (!isset($_SESSION['logged_in']) || !$can_view_schedule) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit();
        }

        $date = trim($_GET['date'] ?? '');
        if (empty($date) || !strtotime($date)) {
            echo json_encode(['success' => false, 'error' => 'Invalid date']);
            exit();
        }

        $stmt = $conn->prepare("SELECT b.booking_id, b.event_name, b.event_type, b.event_time, b.status, b.pax_count, b.venue_location,
                                       u.full_name as customer_name, u.email as customer_email
                                FROM bookings b
                                LEFT JOIN users u ON b.user_id = u.user_id
                                WHERE b.event_date = ?
                                ORDER BY b.event_time ASC");
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $res = $stmt->get_result();

        $bookings = [];
        while ($row = $res->fetch_assoc()) {
            $row['formatted_time'] = date('h:i A', strtotime($row['event_time']));
            $bookings[] = $row;
        }

        echo json_encode(['success' => true, 'date' => $date, 'bookings' => $bookings]);
        break;

    case 'validate_date':
        $date = trim($_GET['date'] ?? $_POST['date'] ?? '');
        if (empty($date) || !strtotime($date)) {
            echo json_encode(['success' => false, 'is_available' => false, 'error' => 'Invalid date format']);
            exit();
        }

        // Check custom config
        $c_stmt = $conn->prepare("SELECT * FROM availability_schedule WHERE schedule_date = ?");
        $c_stmt->bind_param("s", $date);
        $c_stmt->execute();
        $cfg = $c_stmt->get_result()->fetch_assoc();

        // Check confirmed bookings count
        $b_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM bookings WHERE status IN ('confirmed', 'on_progress', 'completed') AND event_date = ?");
        $b_stmt->bind_param("s", $date);
        $b_stmt->execute();
        $confirmed_cnt = intval($b_stmt->get_result()->fetch_assoc()['cnt'] ?? 0);

        $day_of_week = date('N', strtotime($date));
        $is_weekend = ($day_of_week == 6 || $day_of_week == 7);

        if ($cfg) {
            $status = $cfg['status'];
            $max_events = intval($cfg['max_events']);
            $reason = $cfg['reason'] ?? '';
        } else {
            if ($is_weekend) {
                $status = 'Closed';
                $max_events = 0;
                $reason = 'Closed on weekends';
            } else {
                $status = 'Available';
                $max_events = 3;
                $reason = 'Regular Operations';
            }
        }

        if ($status !== 'Closed' && $status !== 'Holiday') {
            if ($max_events > 0 && $confirmed_cnt >= $max_events) {
                $status = 'Fully Booked';
            }
        }

        $remaining_slots = max(0, $max_events - $confirmed_cnt);
        $is_available = ($status === 'Available' || $status === 'Limited Slots') && ($remaining_slots > 0);

        echo json_encode([
            'success'         => true,
            'date'            => $date,
            'status'          => $status,
            'is_available'    => $is_available,
            'max_events'      => $max_events,
            'current_events'  => $confirmed_cnt,
            'remaining_slots' => $remaining_slots,
            'reason'          => $reason
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
