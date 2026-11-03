<?php
// includes/booking_completion_helper.php
// Automatically completes finished catering events and sends completion notifications

if (!function_exists('checkAndCompleteFinishedBookings')) {
    /**
     * Inspects active bookings ('confirmed', 'on_progress') that have finished based on event date/time,
     * updates their status to 'completed', and sends a completion notification if not already sent.
     *
     * @param mysqli $conn Active database connection
     * @param int|null $specific_user_id Optional user ID to filter by
     * @return int Number of bookings marked completed
     */
    function checkAndCompleteFinishedBookings($conn, $specific_user_id = null) {
        if (!$conn) return 0;

        $user_filter = "";
        if ($specific_user_id !== null && intval($specific_user_id) > 0) {
            $user_filter = " AND b.user_id = " . intval($specific_user_id);
        }

        $query = "SELECT b.booking_id, b.user_id, b.event_name, b.event_date, b.event_time, b.event_end_time 
                  FROM bookings b 
                  WHERE b.status IN ('confirmed', 'on_progress') 
                    AND (
                        b.event_date < CURDATE() 
                        OR (
                            b.event_date = CURDATE() 
                            AND (
                                (b.event_end_time IS NOT NULL AND b.event_end_time != '' AND b.event_end_time <= CURTIME())
                                OR (b.event_time IS NOT NULL AND b.event_time <= SUBTIME(CURTIME(), '05:00:00'))
                            )
                        )
                    )
                  $user_filter";

        $res = $conn->query($query);
        $completed_count = 0;

        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $bid = intval($row['booking_id']);
                $uid = intval($row['user_id']);
                $ename = htmlspecialchars($row['event_name'] ?: 'Catering Event');
                $ref = '#BK-' . str_pad($bid, 4, '0', STR_PAD_LEFT);

                // 1. Mark booking as completed
                $conn->query("UPDATE bookings SET status = 'completed', payment_status = 'fully_paid' WHERE booking_id = $bid");
                $conn->query("UPDATE payments SET status = 'Verified' WHERE booking_id = $bid");

                // 2. Check if completion notification already exists for this booking ref
                $check_notif = $conn->prepare("SELECT notif_id FROM notifications WHERE user_id = ? AND message LIKE ? LIMIT 1");
                $like_pattern = "%$ref%";
                $check_notif->bind_param("is", $uid, $like_pattern);
                $check_notif->execute();
                $existing = $check_notif->get_result()->fetch_assoc();

                // 3. Insert completion notification if none exists
                if (!$existing) {
                    $completed_msg = "Event Completed! Thank you for choosing JEMmerific for your event '$ename' ($ref). We hope you and your guests enjoyed the celebration!";
                    $ins = $conn->prepare("INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, 0)");
                    if ($ins) {
                        $ins->bind_param("is", $uid, $completed_msg);
                        $ins->execute();
                    }
                }

                $completed_count++;
            }
        }

        return $completed_count;
    }
}
