<?php
require_once 'includes/admin_auth_check.php';
require_once 'db_config.php';
require_once 'auth/auto_migrate_availability.php';
require_once 'includes/booking_completion_helper.php';

// Auto-check and mark completed events that have elapsed
checkAndCompleteFinishedBookings($conn);

$user_name = $_SESSION['full_name'] ?? 'System Administrator';

// Handle Direct Status Updates from Table or Modal
if (isset($_POST['action']) && $_POST['action'] === 'update_booking_status') {
    $b_id = intval($_POST['booking_id'] ?? 0);
    $new_status = trim($_POST['new_status'] ?? '');
    $allowed_statuses = ['pending', 'on_progress', 'confirmed', 'completed', 'cancelled'];
    
    if ($b_id > 0 && in_array($new_status, $allowed_statuses)) {
        $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE booking_id = ?");
        $stmt->bind_param("si", $new_status, $b_id);
        $stmt->execute();

        // Update financial status automatically when changing booking status
        if ($new_status === 'completed') {
            $conn->query("UPDATE bookings SET payment_status = 'fully_paid' WHERE booking_id = $b_id");
            $conn->query("UPDATE payments SET status = 'Verified' WHERE booking_id = $b_id");
        } elseif ($new_status === 'confirmed') {
            $conn->query("UPDATE bookings SET payment_status = 'partially_paid' WHERE booking_id = $b_id");
            $conn->query("UPDATE payments SET status = 'Verified' WHERE booking_id = $b_id");
        }
        
        // Recalculate capacity for that event date
        $b_res = $conn->query("SELECT event_date FROM bookings WHERE booking_id = $b_id");
        if ($b_res && $b_row = $b_res->fetch_assoc()) {
            $event_date = $b_row['event_date'];
            if ($event_date) {
                $cnt_res = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE status IN ('confirmed', 'on_progress', 'completed') AND event_date = '$event_date'");
                $confirmed_cnt = intval($cnt_res->fetch_assoc()['cnt'] ?? 0);

                $cfg_res = $conn->query("SELECT max_events, status FROM availability_schedule WHERE schedule_date = '$event_date'");
                $cfg_data = $cfg_res ? $cfg_res->fetch_assoc() : null;

                $max_events = $cfg_data ? intval($cfg_data['max_events']) : 3;
                $current_status = $cfg_data ? $cfg_data['status'] : 'Available';

                if ($confirmed_cnt >= $max_events && $max_events > 0 && $current_status !== 'Closed' && $current_status !== 'Holiday') {
                    $up_status = 'Fully Booked';
                } elseif (($max_events - $confirmed_cnt) <= 1 && $max_events > 0 && $current_status !== 'Closed' && $current_status !== 'Holiday') {
                    $up_status = 'Limited Slots';
                } else {
                    $up_status = 'Available';
                }

                $rem_slots = max(0, $max_events - $confirmed_cnt);

                $conn->query("INSERT INTO availability_schedule (schedule_date, status, max_events, current_events, remaining_slots) 
                              VALUES ('$event_date', '$up_status', $max_events, $confirmed_cnt, $rem_slots) 
                              ON DUPLICATE KEY UPDATE 
                              status = VALUES(status), 
                              current_events = VALUES(current_events), 
                              remaining_slots = VALUES(remaining_slots)");
            }
        }

        // Notify customer about the status update
        $b_info_stmt = $conn->prepare("SELECT b.event_name, b.user_id FROM bookings b WHERE b.booking_id = ?");
        $b_info_stmt->bind_param("i", $b_id);
        $b_info_stmt->execute();
        $b_info = $b_info_stmt->get_result()->fetch_assoc();

        if ($b_info && !empty($b_info['user_id'])) {
            $cust_id = $b_info['user_id'];
            $event_title = htmlspecialchars($b_info['event_name']);
            $ref_str = "#BK-" . str_pad($b_id, 4, '0', STR_PAD_LEFT);

            $notif_msg = "";
            if ($new_status === 'confirmed') {
                $notif_msg = "Great news! Your booking '$event_title' ($ref_str) has been CONFIRMED by Admin.";
            } elseif ($new_status === 'on_progress') {
                $notif_msg = "Update: Your event '$event_title' ($ref_str) is now IN PROGRESS! Our catering team is actively preparing.";
            } elseif ($new_status === 'completed') {
                $notif_msg = "Event Completed! Thank you for choosing JEMmerific Catering for your event '$event_title' ($ref_str). We hope you and your guests enjoyed the celebration!";
            } elseif ($new_status === 'cancelled') {
                $notif_msg = "Notice: Your booking for '$event_title' ($ref_str) has been CANCELLED. Please contact admin if you have questions.";
            } elseif ($new_status === 'pending') {
                $notif_msg = "Notice: Your booking status for '$event_title' ($ref_str) is set to PENDING review.";
            }

            if (!empty($notif_msg)) {
                try {
                    $n_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                    if ($n_stmt) {
                        $n_stmt->bind_param("is", $cust_id, $notif_msg);
                        $n_stmt->execute();
                    }
                } catch (Throwable $t) {
                    error_log("Notification insert notice: " . $t->getMessage());
                }
            }
        }

        header("Location: admin_schedule.php?msg=status_updated");
        exit();
    }
}

// Fetch Booking Counts
$counts_query = $conn->query("SELECT 
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'on_progress' THEN 1 ELSE 0 END) as on_progress,
    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM bookings");
$counts = $counts_query->fetch_assoc();

// Filter Logic for Bookings Table below
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$where = " WHERE 1=1";
if($status_filter) $where .= " AND b.status = '" . $conn->real_escape_string($status_filter) . "'";
if($search) {
    $s = $conn->real_escape_string($search);
    $where .= " AND (b.event_name LIKE '%$s%' OR u.full_name LIKE '%$s%' OR b.venue_location LIKE '%$s%')";
}

$schedule_sql = "SELECT b.*, u.full_name, 
                (SELECT COUNT(*) FROM payments p WHERE p.booking_id = b.booking_id) as has_payment_record,
                (SELECT p.account_name FROM payments p WHERE p.booking_id = b.booking_id ORDER BY p.created_at DESC LIMIT 1) as sender_name
                FROM bookings b 
                LEFT JOIN users u ON b.user_id = u.user_id 
                $where 
                ORDER BY b.event_date DESC";
$schedule_res = $conn->query($schedule_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Availability Calendar & Schedule | JEMmerific</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: -0.01em; background-color: #FAFBFF; }
        .sidebar-link { transition: all 0.2s ease-in-out; }
        .sidebar-link:hover { background-color: #e5e7eb; color: #9b7cd1; padding-left: 1.5rem; }
        .active-link { background: linear-gradient(90deg, #d4cef3 0%, transparent 100%); color: #7c3aed; font-weight: 700; border-right: 4px solid #7c3aed; }
        .calendar-cell { min-height: 100px; transition: all 0.15s ease-in-out; }
        .calendar-cell:hover { transform: translateY(-2px); }
    </style>
</head>
<body class="flex min-h-screen">

    <?php require_once 'includes/admin_sidebar.php'; ?>

    <main class="flex-1 lg:ml-64 p-4 md:p-6 transition-all">
        <?php if(isset($_GET['msg'])): ?>
            <div class="mb-6 p-4 bg-green-50 border border-green-100 text-green-600 rounded-2xl text-xs font-bold flex items-center gap-2">
                <i class="bi bi-check-circle-fill"></i> Operation successful! Status updated.
            </div>
        <?php endif; ?>

        <header class="flex flex-col md:flex-row justify-between items-start gap-4 mb-6 sm:mb-8">
            <div class="flex items-start gap-3.5">
                <button onclick="toggleSidebar()" class="lg:hidden mt-0.5 p-2.5 min-h-[44px] min-w-[44px] flex items-center justify-center bg-white border border-gray-100 rounded-xl shadow-sm text-gray-600 hover:bg-gray-50 active:scale-95 transition-all">
                    <i class="bi bi-list text-2xl"></i>
                </button>
                <div>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight">Availability Calendar & Schedule</h2>
                    <p class="text-xs text-gray-400 dark:text-slate-400 font-medium mt-1">Manage operating dates, daily capacity limits, and view customer event reservations.</p>
                </div>
            </div>
        </header>

        <!-- Availability Statistics Dashboard Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-2xs">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[10px] font-black text-emerald-600 uppercase tracking-wider">Available Days</span>
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                </div>
                <h3 id="statAvailable" class="text-3xl font-black text-slate-900">--</h3>
                <p class="text-[10px] text-slate-400 font-semibold mt-1">Open for customer bookings</p>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-2xs">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[10px] font-black text-rose-600 uppercase tracking-wider">Fully Booked</span>
                    <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                </div>
                <h3 id="statFullyBooked" class="text-3xl font-black text-slate-900">--</h3>
                <p class="text-[10px] text-slate-400 font-semibold mt-1">Max capacity reached</p>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-2xs">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[10px] font-black text-slate-600 uppercase tracking-wider">Closed / Holidays</span>
                    <span class="w-2.5 h-2.5 rounded-full bg-slate-700"></span>
                </div>
                <h3 id="statClosed" class="text-3xl font-black text-slate-900">--</h3>
                <p class="text-[10px] text-slate-400 font-semibold mt-1">Non-operating dates</p>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-2xs">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[10px] font-black text-purple-600 uppercase tracking-wider">Upcoming Events</span>
                    <i class="bi bi-calendar-event text-purple-600"></i>
                </div>
                <h3 id="statUpcoming" class="text-3xl font-black text-slate-900">--</h3>
                <p class="text-[10px] text-slate-400 font-semibold mt-1">Confirmed scheduled catering</p>
            </div>
        </div>

        <!-- Monthly Availability Calendar Section -->
        <div class="bg-white p-4 sm:p-6 md:p-8 rounded-3xl border border-slate-200/80 shadow-2xs mb-10 overflow-hidden">
            <!-- Calendar Navigation Header -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 mb-6 pb-6 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-purple-50 text-purple-600 rounded-2xl border border-purple-100">
                        <i class="bi bi-calendar3 text-xl"></i>
                    </div>
                    <div>
                        <h3 id="calendarMonthTitle" class="text-xl sm:text-2xl font-black text-slate-900">Loading...</h3>
                        <p class="text-xs text-slate-400 font-medium">Click any date to edit daily capacity or view booked events</p>
                    </div>
                </div>

                <div class="flex items-center gap-2 justify-end sm:justify-start">
                    <button onclick="prevMonth()" class="min-h-[40px] px-3.5 py-2 bg-slate-100 hover:bg-slate-200 active:scale-95 text-slate-700 font-bold text-xs rounded-xl transition-all flex items-center gap-1 shadow-2xs">
                        <i class="bi bi-chevron-left"></i> Prev
                    </button>
                    <button onclick="currentMonth()" class="min-h-[40px] px-3.5 py-2 bg-purple-50 hover:bg-purple-100 text-purple-700 border border-purple-200 font-extrabold text-xs rounded-xl transition-all shadow-2xs">
                        Today
                    </button>
                    <button onclick="nextMonth()" class="min-h-[40px] px-3.5 py-2 bg-slate-100 hover:bg-slate-200 active:scale-95 text-slate-700 font-bold text-xs rounded-xl transition-all flex items-center gap-1 shadow-2xs">
                        Next <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>

            <!-- Availability Color Legend -->
            <div class="flex items-center gap-2.5 sm:gap-3 flex-wrap mb-6 p-3 bg-slate-50/80 rounded-2xl border border-slate-200/60 shadow-xs text-xs font-bold">
                <span class="text-slate-400 uppercase tracking-widest text-[10px] font-black px-1 flex items-center gap-1.5">
                    <i class="bi bi-info-circle text-slate-400"></i> Legend:
                </span>
                <span class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-50/90 text-emerald-700 border border-emerald-200/80 rounded-xl text-xs font-bold shadow-xs hover:bg-emerald-100/50 transition">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 shadow-sm"></span> Available
                </span>
                <span class="inline-flex items-center gap-2 px-3 py-1.5 bg-amber-50/90 text-amber-700 border border-amber-200/80 rounded-xl text-xs font-bold shadow-xs hover:bg-amber-100/50 transition">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500 shadow-sm"></span> Limited Slots
                </span>
                <span class="inline-flex items-center gap-2 px-3 py-1.5 bg-rose-50/90 text-rose-700 border border-rose-200/80 rounded-xl text-xs font-bold shadow-xs hover:bg-rose-100/50 transition">
                    <span class="w-2.5 h-2.5 rounded-full bg-rose-500 shadow-sm"></span> Fully Booked
                </span>
                <span class="inline-flex items-center gap-2 px-3 py-1.5 bg-slate-100 text-slate-700 border border-slate-300/80 rounded-xl text-xs font-bold shadow-xs hover:bg-slate-200/50 transition">
                    <span class="w-2.5 h-2.5 rounded-full bg-slate-600 shadow-sm"></span> Closed
                </span>
                <span class="inline-flex items-center gap-2 px-3 py-1.5 bg-purple-50 text-purple-700 border border-purple-200/80 rounded-xl text-xs font-bold shadow-xs hover:bg-purple-100/50 transition">
                    <span class="w-2.5 h-2.5 rounded-full bg-purple-600 shadow-sm"></span> Holiday
                </span>
            </div>

            <!-- Calendar Horizontal Swipe Container for Mobile -->
            <div class="overflow-x-auto -mx-2 sm:mx-0 px-2 sm:px-0 pb-2">
                <div class="min-w-[620px]">
                    <!-- Calendar Grid Header (Mon - Sun) -->
                    <div class="grid grid-cols-7 gap-2 mb-2 text-center text-xs font-extrabold text-slate-400 uppercase tracking-wider">
                        <div class="py-2">Mon</div>
                        <div class="py-2">Tue</div>
                        <div class="py-2">Wed</div>
                        <div class="py-2">Thu</div>
                        <div class="py-2">Fri</div>
                        <div class="py-2 text-rose-500">Sat</div>
                        <div class="py-2 text-rose-500">Sun</div>
                    </div>

                    <!-- Interactive Calendar Days Grid -->
                    <div id="adminCalendarGrid" class="grid grid-cols-7 gap-2">
                        <!-- Dynamically populated via JS -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter & Search Bookings List Table -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
            <h3 class="text-xl font-extrabold text-slate-900">Reservations & Bookings List</h3>
            <form method="GET" class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search event, client, venue..." 
                       class="px-4 py-2.5 min-h-[44px] bg-white border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:border-purple-600 w-full sm:w-auto">
                <select name="status" onchange="this.form.submit()" class="px-4 py-2.5 min-h-[44px] bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none w-full sm:w-auto">
                    <option value="">All Statuses</option>
                    <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="on_progress" <?= $status_filter == 'on_progress' ? 'selected' : '' ?>>On Progress</option>
                    <option value="confirmed" <?= $status_filter == 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                    <option value="completed" <?= $status_filter == 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $status_filter == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
                <button type="submit" class="px-5 py-2.5 min-h-[44px] bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs rounded-xl flex items-center justify-center transition-all shadow-2xs">Search</button>
            </form>
        </div>

        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-2xs overflow-hidden mb-12">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200/80 text-[10px] font-black uppercase tracking-wider text-slate-400">
                            <th class="py-4 px-6">Booking Details</th>
                            <th class="py-4 px-6">Event Date & Time</th>
                            <th class="py-4 px-6">Guest Pax</th>
                            <th class="py-4 px-6">Venue</th>
                            <th class="py-4 px-6 text-center">Status</th>
                            <th class="py-4 px-6 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs font-semibold">
                        <?php if ($schedule_res && $schedule_res->num_rows > 0): ?>
                            <?php while($row = $schedule_res->fetch_assoc()): ?>
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="py-4 px-6">
                                        <div class="font-extrabold text-slate-900 text-sm"><?= htmlspecialchars($row['event_name']) ?></div>
                                        <div class="text-slate-400 text-[11px]"><?= htmlspecialchars($row['full_name'] ?? 'Client') ?> • #BK-<?= str_pad($row['booking_id'], 4, '0', STR_PAD_LEFT) ?></div>
                                    </td>
                                    <td class="py-4 px-6">
                                        <div class="font-bold text-slate-800"><?= date('M d, Y', strtotime($row['event_date'])) ?></div>
                                        <div class="text-slate-400 text-[11px]"><?= date('h:i A', strtotime($row['event_time'])) ?></div>
                                    </td>
                                    <td class="py-4 px-6 font-bold text-slate-700">
                                        <?= number_format($row['pax_count']) ?> Pax
                                    </td>
                                    <td class="py-4 px-6 text-slate-600 max-w-xs truncate">
                                        <?= htmlspecialchars($row['venue_location'] ?? 'N/A') ?>
                                    </td>
                                    <td class="py-4 px-6 text-center">
                                        <?php
                                            $st = $row['status'];
                                            $badge = 'bg-slate-100 text-slate-700';
                                            if ($st === 'pending') $badge = 'bg-amber-100 text-amber-800 border border-amber-200';
                                            elseif ($st === 'confirmed') $badge = 'bg-emerald-100 text-emerald-800 border border-emerald-200';
                                            elseif ($st === 'on_progress') $badge = 'bg-blue-100 text-blue-800 border border-blue-200';
                                            elseif ($st === 'completed') $badge = 'bg-purple-100 text-purple-800 border border-purple-200';
                                            elseif ($st === 'cancelled') $badge = 'bg-rose-100 text-rose-700 border border-rose-200';
                                        ?>
                                        <form method="POST" action="admin_schedule.php" class="inline-block">
                                            <input type="hidden" name="action" value="update_booking_status">
                                            <input type="hidden" name="booking_id" value="<?= $row['booking_id'] ?>">
                                            <select name="new_status" onchange="this.form.submit()" class="px-3 py-1.5 min-h-[36px] rounded-full text-[10px] font-black uppercase tracking-wider cursor-pointer border outline-none shadow-2xs transition-all <?= $badge ?>" title="Change status for this reservation">
                                                <option value="pending" <?= $st == 'pending' ? 'selected' : '' ?>>⏳ Pending</option>
                                                <option value="confirmed" <?= $st == 'confirmed' ? 'selected' : '' ?>>✅ Confirmed</option>
                                                <option value="on_progress" <?= $st == 'on_progress' ? 'selected' : '' ?>>🔄 On Progress</option>
                                                <option value="completed" <?= $st == 'completed' ? 'selected' : '' ?>>🎉 Completed</option>
                                                <option value="cancelled" <?= $st == 'cancelled' ? 'selected' : '' ?>>❌ Cancelled</option>
                                            </select>
                                        </form>
                                    </td>
                                    <td class="py-4 px-6 text-right">
                                        <a href="view_booking.php?id=<?= $row['booking_id'] ?>" class="px-4 py-2 min-h-[36px] inline-flex items-center justify-center bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition shadow-2xs">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-400 font-medium">No bookings found matching your search.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Modal 1: Edit Daily Availability Config -->
    <div id="editAvailabilityModal" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs hidden flex items-center justify-center p-4" onclick="closeEditModal()">
        <div class="bg-white w-full max-w-lg rounded-3xl p-6 sm:p-8 shadow-2xl border border-slate-100 relative" onclick="event.stopPropagation()">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-6">
                <div>
                    <span class="text-[10px] font-black text-purple-600 uppercase tracking-widest block">Configure Schedule</span>
                    <h3 id="modalDateTitle" class="text-xl font-black text-slate-900">Date Config</h3>
                </div>
                <button onclick="closeEditModal()" class="text-slate-400 hover:text-slate-700 p-1">
                    <i class="bi bi-x-lg text-lg"></i>
                </button>
            </div>

            <form id="editAvailabilityForm" onsubmit="saveAvailabilityConfig(event)" class="space-y-5">
                <input type="hidden" id="modalScheduleDate" name="schedule_date">

                <div>
                    <label class="block text-xs font-extrabold text-slate-700 uppercase tracking-wider mb-2">Availability Status</label>
                    <select id="modalStatusSelect" name="status" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900 outline-none focus:border-purple-600">
                        <option value="Available">🟢 Available (Normal Booking)</option>
                        <option value="Limited Slots">🟡 Limited Slots (Capacity High)</option>
                        <option value="Fully Booked">🔴 Fully Booked (No More Bookings)</option>
                        <option value="Closed">⚫ Closed (Business Closed / Maintenance)</option>
                        <option value="Holiday">🟣 Holiday (Automatic Closure)</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-extrabold text-slate-700 uppercase tracking-wider mb-2">Maximum Events Limit</label>
                        <input type="number" id="modalMaxEvents" name="max_events" min="0" max="20" required 
                               class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900 outline-none focus:border-purple-600">
                    </div>
                    <div>
                        <label class="block text-xs font-extrabold text-slate-700 uppercase tracking-wider mb-2">Current Approved</label>
                        <input type="text" id="modalCurrentEvents" readonly class="w-full px-4 py-3 bg-slate-100 border border-slate-200 rounded-xl text-xs font-bold text-slate-500 cursor-not-allowed">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-extrabold text-slate-700 uppercase tracking-wider mb-2">Reason / Label (Visible to Client)</label>
                    <input type="text" id="modalReason" name="reason" placeholder="e.g. Regular Operations, Staff Outing, Christmas" 
                           class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900 outline-none focus:border-purple-600">
                </div>

                <div>
                    <label class="block text-xs font-extrabold text-slate-700 uppercase tracking-wider mb-2">Internal Staff Notes (Staff Only)</label>
                    <textarea id="modalNotes" name="notes" rows="3" placeholder="e.g. Need extra generator, Bring 50 additional chairs" 
                              class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900 outline-none focus:border-purple-600 resize-none"></textarea>
                </div>

                <div class="flex items-center gap-3 pt-4 border-t border-slate-100">
                    <button type="button" onclick="viewDayBookingsModal()" class="px-4 py-3 bg-purple-50 hover:bg-purple-100 text-purple-700 font-extrabold text-xs rounded-xl border border-purple-200 flex items-center gap-1">
                        <i class="bi bi-list-task"></i> View Bookings
                    </button>
                    <div class="ml-auto flex items-center gap-2">
                        <button type="button" onclick="closeEditModal()" class="px-4 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-3 bg-purple-600 hover:bg-purple-700 text-white font-extrabold text-xs rounded-xl shadow-md transition-all">
                            Save Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal 2: View Day Bookings -->
    <div id="viewBookingsModal" class="fixed inset-0 z-[60] bg-slate-950/60 backdrop-blur-xs hidden flex items-center justify-center p-4" onclick="closeBookingsModal()">
        <div class="bg-white w-full max-w-2xl rounded-3xl p-6 sm:p-8 shadow-2xl border border-slate-100 relative" onclick="event.stopPropagation()">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-6">
                <div>
                    <span class="text-[10px] font-black text-purple-600 uppercase tracking-widest block">Scheduled Catering</span>
                    <h3 id="dayBookingsTitle" class="text-xl font-black text-slate-900">Bookings for Date</h3>
                </div>
                <button onclick="closeBookingsModal()" class="text-slate-400 hover:text-slate-700 p-1">
                    <i class="bi bi-x-lg text-lg"></i>
                </button>
            </div>

            <div id="dayBookingsList" class="space-y-3 max-h-96 overflow-y-auto pr-1">
                <!-- Dynamically loaded -->
            </div>

            <div class="pt-6 border-t border-slate-100 text-right mt-6">
                <button onclick="closeBookingsModal()" class="px-5 py-2.5 bg-slate-900 text-white font-bold text-xs rounded-xl">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentYear = new Date().getFullYear();
        let currentMonthNum = new Date().getMonth() + 1; // 1-12
        let monthScheduleData = {};
        let activeSelectedDate = '';

        document.addEventListener('DOMContentLoaded', () => {
            loadMonthlyAvailability(currentYear, currentMonthNum);
        });

        async function loadMonthlyAvailability(year, month) {
            try {
                const res = await fetch(`auth/availability_api.php?action=fetch_month&year=${year}&month=${month}`);
                const data = await res.json();

                if (!data.success) {
                    Swal.fire('Error', 'Failed to load availability calendar', 'error');
                    return;
                }

                monthScheduleData = data.schedule;

                // Update Stats Dashboard
                document.getElementById('statAvailable').textContent = data.stats.available_days;
                document.getElementById('statFullyBooked').textContent = data.stats.fully_booked;
                document.getElementById('statClosed').textContent = data.stats.closed;
                document.getElementById('statUpcoming').textContent = data.stats.upcoming_events;

                // Update Title
                const monthName = new Date(year, month - 1, 1).toLocaleString('default', { month: 'long' });
                document.getElementById('calendarMonthTitle').textContent = `${monthName} ${year}`;

                renderCalendarGrid(year, month, data.schedule);
            } catch (err) {
                console.error("Error fetching availability calendar:", err);
            }
        }

        function renderCalendarGrid(year, month, schedule) {
            const grid = document.getElementById('adminCalendarGrid');
            grid.innerHTML = '';

            const firstDayIndex = new Date(year, month - 1, 1).getDay(); // 0 (Sun) to 6 (Sat)
            // Adjust so 0 is Mon, 6 is Sun
            let offset = firstDayIndex === 0 ? 6 : firstDayIndex - 1;

            // Empty padding cells for start of month
            for (let i = 0; i < offset; i++) {
                const emptyCell = document.createElement('div');
                emptyCell.className = 'bg-slate-50/50 rounded-2xl border border-dashed border-slate-100 opacity-40 min-h-[90px]';
                grid.appendChild(emptyCell);
            }

            const todayStr = new Date().toISOString().split('T')[0];

            Object.values(schedule).forEach(item => {
                const cell = document.createElement('div');
                const isToday = item.date === todayStr;

                let statusBg = 'bg-emerald-50 border-emerald-200 text-emerald-900';
                let dotColor = 'bg-emerald-500';
                let statusLabel = 'Available';

                if (item.status === 'Limited Slots') {
                    statusBg = 'bg-amber-50 border-amber-200 text-amber-900';
                    dotColor = 'bg-amber-500';
                    statusLabel = 'Limited';
                } else if (item.status === 'Fully Booked') {
                    statusBg = 'bg-rose-50 border-rose-200 text-rose-900';
                    dotColor = 'bg-rose-500';
                    statusLabel = 'Fully Booked';
                } else if (item.status === 'Closed') {
                    statusBg = 'bg-slate-100 border-slate-200 text-slate-700';
                    dotColor = 'bg-slate-700';
                    statusLabel = 'Closed';
                } else if (item.status === 'Holiday') {
                    statusBg = 'bg-purple-50 border-purple-200 text-purple-900';
                    dotColor = 'bg-purple-600';
                    statusLabel = 'Holiday';
                }

                cell.className = `calendar-cell ${statusBg} border rounded-2xl p-2.5 cursor-pointer shadow-2xs flex flex-col justify-between ${isToday ? 'ring-2 ring-purple-600 ring-offset-1' : ''}`;
                cell.onclick = () => openEditModal(item.date);

                cell.innerHTML = `
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-black ${isToday ? 'text-purple-700 bg-purple-100 px-1.5 py-0.5 rounded-md' : ''}">${item.day}</span>
                        <span class="w-2 h-2 rounded-full ${dotColor}"></span>
                    </div>
                    <div class="my-1">
                        <span class="text-[9px] font-extrabold uppercase tracking-wider block truncate">${item.reason || statusLabel}</span>
                    </div>
                    <div class="flex items-center justify-between border-t border-slate-200/50 pt-1 text-[9px] font-bold">
                        <span>${item.current_events}/${item.max_events} Booked</span>
                        ${item.notes ? '<i class="bi bi-sticky-fill text-amber-500 text-[10px]" title="Staff Notes Attached"></i>' : ''}
                    </div>
                `;

                grid.appendChild(cell);
            });
        }

        function prevMonth() {
            currentMonthNum--;
            if (currentMonthNum < 1) {
                currentMonthNum = 12;
                currentYear--;
            }
            loadMonthlyAvailability(currentYear, currentMonthNum);
        }

        function nextMonth() {
            currentMonthNum++;
            if (currentMonthNum > 12) {
                currentMonthNum = 1;
                currentYear++;
            }
            loadMonthlyAvailability(currentYear, currentMonthNum);
        }

        function currentMonth() {
            currentYear = new Date().getFullYear();
            currentMonthNum = new Date().getMonth() + 1;
            loadMonthlyAvailability(currentYear, currentMonthNum);
        }

        function openEditModal(dateStr) {
            activeSelectedDate = dateStr;
            const item = monthScheduleData[dateStr] || {
                date: dateStr,
                status: 'Available',
                max_events: 3,
                current_events: 0,
                reason: '',
                notes: ''
            };

            const formattedDate = new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });

            document.getElementById('modalDateTitle').textContent = formattedDate;
            document.getElementById('modalScheduleDate').value = dateStr;
            document.getElementById('modalStatusSelect').value = item.status;
            document.getElementById('modalMaxEvents').value = item.max_events;
            document.getElementById('modalCurrentEvents').value = `${item.current_events} Approved Events`;
            document.getElementById('modalReason').value = item.reason || '';
            document.getElementById('modalNotes').value = item.notes || '';

            document.getElementById('editAvailabilityModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editAvailabilityModal').classList.add('hidden');
        }

        async function saveAvailabilityConfig(e) {
            e.preventDefault();
            const form = document.getElementById('editAvailabilityForm');
            const formData = new FormData(form);
            formData.append('action', 'save_daily_config');

            try {
                const res = await fetch('auth/availability_api.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Updated!',
                        text: 'Daily availability configuration saved.',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    closeEditModal();
                    loadMonthlyAvailability(currentYear, currentMonthNum);
                } else {
                    Swal.fire('Error', data.error || 'Failed to update schedule', 'error');
                }
            } catch (err) {
                console.error("Error saving availability config:", err);
                Swal.fire('Error', 'Server error while saving configuration', 'error');
            }
        }

        async function viewDayBookingsModal() {
            if (!activeSelectedDate) return;

            try {
                const res = await fetch(`auth/availability_api.php?action=fetch_day_bookings&date=${activeSelectedDate}`);
                const data = await res.json();

                if (!data.success) {
                    Swal.fire('Error', data.error || 'Failed to fetch day bookings', 'error');
                    return;
                }

                const formattedDate = new Date(activeSelectedDate + 'T00:00:00').toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
                document.getElementById('dayBookingsTitle').textContent = `Bookings for ${formattedDate}`;

                const listContainer = document.getElementById('dayBookingsList');
                if (data.bookings.length === 0) {
                    listContainer.innerHTML = '<div class="p-6 text-center text-slate-400 font-medium bg-slate-50 rounded-2xl border border-dashed border-slate-200">No scheduled bookings on this date.</div>';
                } else {
                    let html = '';
                    data.bookings.forEach(b => {
                        let stBadge = 'bg-slate-100 text-slate-700';
                        if (b.status === 'confirmed' || b.status === 'on_progress') stBadge = 'bg-emerald-100 text-emerald-800';
                        else if (b.status === 'pending') stBadge = 'bg-amber-100 text-amber-800';

                        html += `
                            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80 flex items-center justify-between gap-4">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="font-extrabold text-slate-900 text-sm">${escapeHtml(b.event_name)}</h4>
                                        <span class="text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full ${stBadge}">${b.status}</span>
                                    </div>
                                    <p class="text-xs text-slate-500 font-semibold mt-1">
                                        <i class="bi bi-clock"></i> ${b.formatted_time} • <i class="bi bi-[#people]"></i> ${b.pax_count} Pax • ${escapeHtml(b.customer_name || 'Client')}
                                    </p>
                                    <p class="text-[11px] text-slate-400 mt-0.5 truncate max-w-md"><i class="bi bi-geo-alt"></i> ${escapeHtml(b.venue_location || 'Venue N/A')}</p>
                                </div>
                                <a href="view_booking.php?id=${b.booking_id}" target="_blank" class="px-3 py-1.5 bg-white border border-slate-200 text-slate-700 font-bold text-xs rounded-xl shadow-2xs hover:bg-slate-100 transition shrink-0">
                                    Details
                                </a>
                            </div>
                        `;
                    });
                    listContainer.innerHTML = html;
                }

                document.getElementById('viewBookingsModal').classList.remove('hidden');
            } catch (err) {
                console.error("Error fetching day bookings:", err);
            }
        }

        function closeBookingsModal() {
            document.getElementById('viewBookingsModal').classList.add('hidden');
        }

        function escapeHtml(text) {
            if (!text) return '';
            return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }
    </script>
</body>
</html>