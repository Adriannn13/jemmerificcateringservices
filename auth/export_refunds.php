<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized access.");
}
require_once '../db_config.php';

// 1. Replicate Filter Logic from the Report Page
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where = " WHERE b.status = 'cancelled'";
if ($search) {
    $s = $conn->real_escape_string($search);
    $where .= " AND (b.event_name LIKE '%$s%' OR u.full_name LIKE '%$s%' OR b.cancel_reason LIKE '%$s%')";
}
if ($date_from) $where .= " AND b.event_date >= '" . $conn->real_escape_string($date_from) . "'";
if ($date_to) $where .= " AND b.event_date <= '" . $conn->real_escape_string($date_to) . "'";

// 2. Fetch Data
$query = "SELECT b.booking_id, b.created_at as cancel_date, u.full_name, b.event_name, b.cancel_reason, b.refund_amount 
          FROM bookings b 
          JOIN users u ON b.user_id = u.user_id 
          $where 
          ORDER BY b.created_at DESC";
$results = $conn->query($query);

// 3. Set Headers for CSV Download
$filename = "Refund_Report_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

// 4. Create File Pointer and Write Content
$output = fopen('php://output', 'w');

// Write Column Headers
fputcsv($output, [
    'Booking ID', 
    'Cancellation Date', 
    'Client Name', 
    'Event Name', 
    'Reason for Cancellation', 
    'Amount Refunded (PHP)'
]);

// Write Data Rows
if ($results->num_rows > 0) {
    while ($row = $results->fetch_assoc()) {
        fputcsv($output, [
            '#BK-' . str_pad($row['booking_id'], 4, '0', STR_PAD_LEFT),
            date('M d, Y h:i A', strtotime($row['cancel_date'])),
            $row['full_name'],
            $row['event_name'],
            $row['cancel_reason'] ?: 'N/A',
            number_format($row['refund_amount'], 2, '.', '')
        ]);
    }
}

fclose($output);
$conn->close();
exit();