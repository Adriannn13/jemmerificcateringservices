<?php
require_once 'includes/admin_auth_check.php';

require_once 'db_config.php';

$user_name = $_SESSION['full_name'] ?? 'System Administrator';

// Handle Approval Action
if (isset($_GET['action']) && $_GET['action'] == 'approve' && isset($_GET['booking_id'])) {
    $b_id = intval($_GET['booking_id']);
    
    // Fetch booking & customer details
    $b_stmt = $conn->prepare("SELECT b.*, u.user_id, u.full_name FROM bookings b LEFT JOIN users u ON b.user_id = u.user_id WHERE b.booking_id = ?");
    $b_stmt->bind_param("i", $b_id);
    $b_stmt->execute();
    $b_data = $b_stmt->get_result()->fetch_assoc();

    if ($b_data) {
        // Update booking status to confirmed
        $up_stmt = $conn->prepare("UPDATE bookings SET status = 'confirmed' WHERE booking_id = ?");
        $up_stmt->bind_param("i", $b_id);
        $up_stmt->execute();

        // Also update payment status if payment proof exists
        $conn->query("UPDATE payments SET status = 'Approved' WHERE booking_id = $b_id");

        // Auto-recalculate availability schedule capacity for event_date
        $event_date = $b_data['event_date'];
        if ($event_date) {
            $cnt_res = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE status IN ('confirmed', 'on_progress', 'completed') AND event_date = '$event_date'");
            $confirmed_cnt = intval($cnt_res->fetch_assoc()['cnt'] ?? 0);

            $cfg_res = $conn->query("SELECT max_events, status FROM availability_schedule WHERE schedule_date = '$event_date'");
            $cfg_data = $cfg_res ? $cfg_res->fetch_assoc() : null;

            $max_events = $cfg_data ? intval($cfg_data['max_events']) : 3;
            $current_status = $cfg_data ? $cfg_data['status'] : 'Available';

            if ($confirmed_cnt >= $max_events && $max_events > 0 && $current_status !== 'Closed' && $current_status !== 'Holiday') {
                $new_status = 'Fully Booked';
            } elseif (($max_events - $confirmed_cnt) <= 1 && $max_events > 0 && $current_status !== 'Closed' && $current_status !== 'Holiday') {
                $new_status = 'Limited Slots';
            } else {
                $new_status = $current_status;
            }

            $rem_slots = max(0, $max_events - $confirmed_cnt);

            $conn->query("INSERT INTO availability_schedule (schedule_date, status, max_events, current_events, remaining_slots) 
                          VALUES ('$event_date', '$new_status', $max_events, $confirmed_cnt, $rem_slots) 
                          ON DUPLICATE KEY UPDATE 
                          status = VALUES(status), 
                          current_events = VALUES(current_events), 
                          remaining_slots = VALUES(remaining_slots)");
        }

        // Notify customer
        try {
            $cust_id = $b_data['user_id'];
            $event_title = htmlspecialchars($b_data['event_name']);
            $notif_msg = "Great news! Your booking request for '$event_title' (Ref #BK-" . str_pad($b_id, 4, '0', STR_PAD_LEFT) . ") has been APPROVED by Admin and scheduled.";
            $n_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            if ($n_stmt) {
                $n_stmt->bind_param("is", $cust_id, $notif_msg);
                $n_stmt->execute();
            }
        } catch (Throwable $t) {
            error_log("Notification insert notice: " . $t->getMessage());
        }

        header("Location: admin_schedule.php?msg=booking_approved");
        exit();
    }
}

// Handle Cancel Action
if (isset($_GET['action']) && $_GET['action'] == 'cancel' && isset($_GET['booking_id'])) {
    $b_id = intval($_GET['booking_id']);
    $cancel_reason = trim($_GET['reason'] ?? 'Booking request was cancelled by administrator.');

    $b_stmt = $conn->prepare("SELECT b.*, u.user_id, u.full_name FROM bookings b LEFT JOIN users u ON b.user_id = u.user_id WHERE b.booking_id = ?");
    $b_stmt->bind_param("i", $b_id);
    $b_stmt->execute();
    $b_data = $b_stmt->get_result()->fetch_assoc();

    if ($b_data) {
        $c_stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled', cancel_reason = ? WHERE booking_id = ?");
        $c_stmt->bind_param("si", $cancel_reason, $b_id);
        $c_stmt->execute();

        // Also mark any pending payment as rejected
        $conn->query("UPDATE payments SET status = 'Rejected' WHERE booking_id = $b_id AND status = 'Pending'");

        // Send notification to customer
        try {
            $cust_id = $b_data['user_id'];
            $event_title = htmlspecialchars($b_data['event_name']);
            $notif_msg = "Notice: Your booking request for '$event_title' (Ref #BK-" . str_pad($b_id, 4, '0', STR_PAD_LEFT) . ") has been cancelled. Reason: " . $cancel_reason;
            $n_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            if ($n_stmt) {
                $n_stmt->bind_param("is", $cust_id, $notif_msg);
                $n_stmt->execute();
            }
        } catch (Throwable $t) {}

        header("Location: admin_requests.php?msg=cancelled");
        exit();
    }
}

// Fetch Pending Booking Requests
$requests_query = "
    SELECT b.*, COALESCE(u.full_name, 'Valued Client') as client_name, u.email as client_email,
           (SELECT COUNT(*) FROM payments p WHERE p.booking_id = b.booking_id) as proof_submitted
    FROM bookings b 
    LEFT JOIN users u ON b.user_id = u.user_id 
    WHERE b.status = 'pending' 
    ORDER BY b.created_at DESC
";
$requests_res = $conn->query($requests_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incoming Booking Requests | Admin Console</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #FAFBFF; }
    </style>
</head>
<body class="flex min-h-screen text-slate-800">

    <!-- Admin Sidebar Include -->
    <?php require_once 'includes/admin_sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="flex-1 lg:ml-64 p-4 md:p-6 transition-all">
        
        <!-- Header -->
        <header class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6 sm:mb-8 pb-6 border-b border-slate-200/80">
            <div class="flex items-center gap-3.5">
                <button onclick="toggleSidebar()" class="lg:hidden p-2.5 min-h-[44px] min-w-[44px] flex items-center justify-center bg-white border border-slate-200 rounded-xl text-slate-600 hover:bg-slate-50 transition-colors shadow-2xs active:scale-95">
                    <i class="bi bi-list text-xl"></i>
                </button>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-500 animate-pulse"></span>
                        <h2 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">Incoming Booking Requests</h2>
                    </div>
                    <p class="text-xs font-semibold text-slate-500 mt-0.5">Review incoming customer catering reservations. Approving a request pushes it directly to the Master Schedule.</p>
                </div>
            </div>

            <div class="flex items-center gap-3 w-full sm:w-auto">
                <a href="admin_schedule.php" class="w-full sm:w-auto min-h-[44px] px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-2 shadow-2xs">
                    <i class="bi bi-calendar-week"></i> View Schedule
                </a>
            </div>
        </header>

        <!-- Booking Requests Table / List -->
        <div class="space-y-6">
            <?php if ($requests_res && $requests_res->num_rows > 0): ?>
                <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
                    <div class="p-4 sm:p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                        <span class="text-xs font-extrabold text-slate-700 uppercase tracking-wider">Pending Approvals (<?= $requests_res->num_rows ?>)</span>
                        <span class="text-[10px] font-bold text-amber-600 bg-amber-50 px-2.5 py-1 rounded-full border border-amber-100">Action Required</span>
                    </div>

                    <div class="divide-y divide-slate-100">
                        <?php while ($r = $requests_res->fetch_assoc()): ?>
                            <div class="p-4 sm:p-6 hover:bg-slate-50/60 transition-colors flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 sm:gap-6">
                                
                                <!-- Client & Event Details -->
                                <div class="space-y-2 flex-1 w-full">
                                    <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                                        <span class="px-2.5 py-0.5 bg-purple-50 text-purple-700 border border-purple-100 text-[10px] font-extrabold rounded-full uppercase tracking-wider shrink-0">
                                            #BK-<?= str_pad($r['booking_id'], 4, '0', STR_PAD_LEFT) ?>
                                        </span>
                                        <h3 class="text-base font-extrabold text-slate-900 break-words"><?= htmlspecialchars($r['event_name']) ?></h3>
                                        <span class="text-xs font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-md shrink-0"><?= htmlspecialchars($r['event_type']) ?></span>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2 sm:gap-4 text-xs font-semibold text-slate-600">
                                        <span class="flex items-center gap-1.5"><i class="bi bi-person-fill text-purple-500"></i> Client: <strong class="text-slate-900"><?= htmlspecialchars($r['client_name']) ?></strong> (<?= htmlspecialchars($r['client_email']) ?>)</span>
                                        <span class="flex items-center gap-1.5"><i class="bi bi-calendar3 text-purple-500"></i> Date: <strong class="text-slate-900"><?= date('M d, Y', strtotime($r['event_date'])) ?> at <?= date('h:i A', strtotime($r['event_time'])) ?></strong></span>
                                        <span class="flex items-center gap-1.5"><i class="bi bi-people-fill text-purple-500"></i> Guests: <strong class="text-slate-900"><?= number_format($r['pax_count']) ?> Pax</strong></span>
                                    </div>

                                    <div class="flex items-center gap-2 text-xs font-medium text-slate-500">
                                        <i class="bi bi-geo-alt-fill text-slate-400 shrink-0"></i> Venue: <?= htmlspecialchars($r['venue_location']) ?>
                                    </div>
                                </div>

                                <!-- Amount & Actions -->
                                <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between lg:justify-end gap-3 sm:gap-4 w-full lg:w-auto border-t lg:border-t-0 pt-4 lg:pt-0 border-slate-100">
                                    <div class="text-left sm:text-right">
                                        <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">Total Amount</p>
                                        <p class="text-lg font-black text-purple-700">₱<?= number_format($r['total_amount'], 2) ?></p>
                                    </div>

                                    <div class="grid grid-cols-3 sm:flex items-center gap-2 w-full sm:w-auto">
                                        <a href="view_booking.php?id=<?= $r['booking_id'] ?>" class="min-h-[44px] px-3.5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs uppercase tracking-wider transition-all text-center flex items-center justify-center gap-1.5 shadow-2xs">
                                            <i class="bi bi-eye-fill"></i> <span>Details</span>
                                        </a>
                                        <button onclick="adminCancelBooking(<?= $r['booking_id'] ?>, '<?= htmlspecialchars(addslashes($r['event_name'])) ?>')" class="min-h-[44px] px-3.5 py-2.5 bg-rose-50 hover:bg-rose-100 text-rose-600 font-extrabold rounded-xl text-xs uppercase tracking-wider transition-all text-center flex items-center justify-center gap-1.5 cursor-pointer border border-rose-200/80 shadow-2xs">
                                            <i class="bi bi-x-circle-fill"></i> <span>Cancel</span>
                                        </button>
                                        <a href="admin_requests.php?action=approve&booking_id=<?= $r['booking_id'] ?>" onclick="return confirm('Approve this booking request and push to Master Schedule?');" class="min-h-[44px] px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold rounded-xl text-xs uppercase tracking-wider transition-all shadow-md text-center flex items-center justify-center gap-1.5 cursor-pointer">
                                            <i class="bi bi-check-circle-fill"></i> <span>Approve</span>
                                        </a>
                                    </div>
                                </div>

                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="p-16 bg-white rounded-3xl border border-slate-200/80 text-center space-y-3">
                    <div class="w-16 h-16 rounded-2xl bg-slate-50 text-slate-300 flex items-center justify-center text-3xl mx-auto">
                        <i class="bi bi-inbox-fill"></i>
                    </div>
                    <h3 class="text-base font-extrabold text-slate-900">No Pending Booking Requests</h3>
                    <p class="text-xs text-slate-500 font-semibold max-w-sm mx-auto">All incoming customer catering requests have been reviewed and approved into the Master Schedule.</p>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
            document.getElementById('sidebarOverlay').classList.toggle('hidden');
        }

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('msg') && urlParams.get('msg') === 'cancelled') {
            Swal.fire({
                title: 'Request Cancelled',
                text: 'The booking request has been cancelled and the client has been notified.',
                icon: 'info',
                confirmButtonColor: '#7c3aed'
            });
        } else if (urlParams.has('msg') && urlParams.get('msg') === 'deleted') {
            Swal.fire({
                title: 'Request Removed!',
                text: 'The booking request has been removed.',
                icon: 'success',
                confirmButtonColor: '#7c3aed'
            });
        }

        function adminCancelBooking(id, eventName) {
            Swal.fire({
                title: 'Cancel this booking request?',
                text: `Provide a reason for cancelling "${eventName}":`,
                input: 'text',
                inputValue: 'Schedule fully booked / conflict on selected date.',
                inputPlaceholder: 'Enter cancellation reason...',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e11d48',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, Cancel Request',
                cancelButtonText: 'No, Keep It',
                preConfirm: (reason) => {
                    return reason || 'Booking request was cancelled by administrator.';
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const encodedReason = encodeURIComponent(result.value || 'Booking request was cancelled by administrator.');
                    window.location.href = `admin_requests.php?action=cancel&booking_id=${id}&reason=${encodedReason}`;
                }
            });
        }
    </script>
</body>
</html>
