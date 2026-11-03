<?php
// staff_dashboard.php - Customized Dashboard for Staff Members based on Admin Setup
require_once 'includes/admin_auth_check.php';
require_once 'db_config.php';
require_once 'includes/staff_helper.php';

$user_id   = $_SESSION['user_id'] ?? 0;
$user_name = $_SESSION['full_name'] ?? 'Staff Member';
$user_role = strtolower($_SESSION['role'] ?? 'staff');
$is_admin  = ($user_role === 'admin');

// Update activity
update_user_activity($conn, $user_id);

// Fetch permissions for this staff member
$staff_perms = get_staff_permissions($conn, $user_id);
if ($is_admin) {
    // If admin is viewing staff portal, admin has all permissions
    $staff_perms = array_keys(STAFF_TASKS);
}

// ----------------------------------------------------
// Section Metrics for permitted areas
// ----------------------------------------------------
$unread_msg_count = 0;
$pending_booking_count = 0;
$upcoming_event_count = 0;
$total_menu_count = 0;

if (in_array('customer_messages', $staff_perms)) {
    try {
        $m_res = $conn->query("SELECT COUNT(*) as total FROM messages WHERE (receiver_id = $user_id OR receiver_id = 1) AND is_read = 0");
        if ($m_res) $unread_msg_count = intval($m_res->fetch_assoc()['total'] ?? 0);
    } catch (Exception $e) {}
}

if (in_array('booking_requests', $staff_perms)) {
    try {
        $b_res = $conn->query("SELECT COUNT(*) as total FROM bookings WHERE status = 'pending'");
        if ($b_res) $pending_booking_count = intval($b_res->fetch_assoc()['total'] ?? 0);
    } catch (Exception $e) {}
}

if (in_array('schedule_events', $staff_perms)) {
    try {
        $s_res = $conn->query("SELECT COUNT(*) as total FROM bookings WHERE event_date >= CURDATE() AND status != 'cancelled'");
        if ($s_res) $upcoming_event_count = intval($s_res->fetch_assoc()['total'] ?? 0);
    } catch (Exception $e) {}
}

if (in_array('menu_catalog', $staff_perms)) {
    try {
        $mi_res = $conn->query("SELECT COUNT(*) as total FROM menu_items WHERE is_available = 1");
        if ($mi_res) $total_menu_count = intval($mi_res->fetch_assoc()['total'] ?? 0);
    } catch (Exception $e) {}
}

// Recent Pending Bookings preview if staff has booking_requests
$recent_bookings = null;
if (in_array('booking_requests', $staff_perms)) {
    $recent_bookings = $conn->query("
        SELECT b.booking_id, b.event_name, b.event_date, b.event_time, b.pax_count, b.status, b.total_amount,
               COALESCE(u.full_name, 'Client') as client_name, u.email as client_email
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.user_id
        WHERE b.status = 'pending'
        ORDER BY b.created_at DESC
        LIMIT 4
    ");
}

// Upcoming Catering Events preview if staff has schedule_events
$upcoming_events = null;
if (in_array('schedule_events', $staff_perms)) {
    $upcoming_events = $conn->query("
        SELECT b.booking_id, b.event_name, b.event_date, b.event_time, b.status, b.event_type,
               COALESCE(u.full_name, 'Client') as client_name
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.user_id
        WHERE b.event_date >= CURDATE() AND b.status != 'cancelled'
        ORDER BY b.event_date ASC, b.event_time ASC
        LIMIT 4
    ");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Portal | JEMmerific</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: -0.01em; background-color: #FAFBFF; }
        .glass-card { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); }
        .action-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .action-card:hover { transform: translateY(-4px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.07); }
        .online-dot { animation: pulseGlow 2s infinite ease-in-out; }
        @keyframes pulseGlow {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
    </style>
</head>
<body class="flex min-h-screen text-slate-800 antialiased">

    <!-- Sidebar Navigation (customized automatically for staff) -->
    <?php require_once 'includes/admin_sidebar.php'; ?>

    <!-- Main Workspace Container -->
    <main class="flex-1 lg:ml-64 p-4 md:p-8 transition-all min-h-screen flex flex-col">
        
        <!-- Welcome Top Header Bar -->
        <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
            <div class="flex items-center gap-3.5">
                <button type="button" onclick="toggleSidebar()" class="lg:hidden p-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-sm text-slate-600 dark:text-slate-200">
                    <i class="bi bi-list text-2xl"></i>
                </button>
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                            Welcome, <?= htmlspecialchars($user_name) ?>!
                        </h1>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 shadow-2xs">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 online-dot"></span>
                            Active Now
                        </span>
                    </div>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 font-medium mt-1">
                        Staff Operations Dashboard • You have access to <span class="font-bold text-purple-600 dark:text-purple-400"><?= count($staff_perms) ?></span> authorized dashboard section(s).
                    </p>
                </div>
            </div>

            <?php if ($is_admin): ?>
                <div class="flex items-center gap-2">
                    <a href="admin_dashboard.php" class="px-4 py-2 bg-purple-600 text-white rounded-xl text-xs font-bold uppercase tracking-wider hover:bg-purple-700 transition flex items-center gap-1.5 shadow-sm">
                        <i class="bi bi-arrow-left"></i> Return to Admin Console
                    </a>
                </div>
            <?php endif; ?>
        </header>

        <!-- Notification if no permissions assigned yet -->
        <?php if (empty($staff_perms)): ?>
            <div class="p-8 rounded-3xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200 mb-8 flex flex-col items-center text-center">
                <div class="w-16 h-16 rounded-2xl bg-amber-100 dark:bg-amber-900/60 text-amber-600 dark:text-amber-300 flex items-center justify-center text-3xl mb-3">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
                <h3 class="text-lg font-black">No Dashboard Sections Assigned Yet</h3>
                <p class="text-xs max-w-md mt-1 text-amber-700 dark:text-amber-300">
                    Your staff account is currently active, but the administrator has not yet assigned specific tasks (Customer Messages, Booking Requests, Schedule Events, or Menu Catalog) to your profile. Please contact the administrator to grant access.
                </p>
            </div>
        <?php endif; ?>

        <!-- Permitted Sections Workspace Cards -->
        <?php if (!empty($staff_perms)): ?>
            <div class="mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xs font-black uppercase tracking-widest text-slate-400">Assigned Navigation Sections</h2>
                    <span class="text-[10px] font-bold text-slate-400"><?= count($staff_perms) ?> of <?= count(STAFF_TASKS) ?> Sections Granted</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    
                    <!-- 1. Customer Messages Card -->
                    <?php if (in_array('customer_messages', $staff_perms)): ?>
                        <div class="action-card bg-white dark:bg-[#131b2e] rounded-3xl p-6 border border-purple-100 dark:border-purple-900/50 shadow-sm flex flex-col justify-between group">
                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <div class="w-12 h-12 rounded-2xl bg-purple-50 dark:bg-purple-950/70 text-purple-600 dark:text-purple-400 flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">
                                        <i class="bi bi-chat-left-text-fill"></i>
                                    </div>
                                    <?php if ($unread_msg_count > 0): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-black bg-rose-500 text-white shadow-sm animate-pulse">
                                            <?= $unread_msg_count ?> Unread
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[9px] font-extrabold bg-purple-50 text-purple-700 dark:bg-purple-950 dark:text-purple-300">
                                            Active
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <h3 class="text-base font-extrabold text-slate-900 dark:text-white">Customer Messages</h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                    Chat with customers in real-time, monitor inquiries, and resolve guest questions.
                                </p>
                            </div>
                            <div class="pt-5 mt-4 border-t border-slate-100 dark:border-slate-800">
                                <a href="admin_messages.php" class="w-full flex items-center justify-center gap-2 py-3 rounded-2xl bg-purple-50 hover:bg-purple-600 dark:bg-purple-950/60 dark:hover:bg-purple-600 text-purple-700 hover:text-white dark:text-purple-300 dark:hover:text-white font-extrabold text-xs uppercase tracking-wider transition-all shadow-2xs">
                                    <span>Open Messages</span>
                                    <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- 2. Booking Requests Card -->
                    <?php if (in_array('booking_requests', $staff_perms)): ?>
                        <div class="action-card bg-white dark:bg-[#131b2e] rounded-3xl p-6 border border-amber-100 dark:border-amber-900/50 shadow-sm flex flex-col justify-between group">
                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <div class="w-12 h-12 rounded-2xl bg-amber-50 dark:bg-amber-950/70 text-amber-600 dark:text-amber-400 flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">
                                        <i class="bi bi-bell-fill"></i>
                                    </div>
                                    <?php if ($pending_booking_count > 0): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-black bg-amber-500 text-white shadow-sm">
                                            <?= $pending_booking_count ?> Pending
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[9px] font-extrabold bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                            All Clear
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <h3 class="text-base font-extrabold text-slate-900 dark:text-white">Booking Requests</h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                    Review client event reservations, verify guest details, and update confirmation statuses.
                                </p>
                            </div>
                            <div class="pt-5 mt-4 border-t border-slate-100 dark:border-slate-800">
                                <a href="admin_requests.php" class="w-full flex items-center justify-center gap-2 py-3 rounded-2xl bg-amber-50 hover:bg-amber-600 dark:bg-amber-950/60 dark:hover:bg-amber-600 text-amber-700 hover:text-white dark:text-amber-300 dark:hover:text-white font-extrabold text-xs uppercase tracking-wider transition-all shadow-2xs">
                                    <span>Review Requests</span>
                                    <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- 3. Schedule Events Card -->
                    <?php if (in_array('schedule_events', $staff_perms)): ?>
                        <div class="action-card bg-white dark:bg-[#131b2e] rounded-3xl p-6 border border-blue-100 dark:border-blue-900/50 shadow-sm flex flex-col justify-between group">
                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <div class="w-12 h-12 rounded-2xl bg-blue-50 dark:bg-blue-950/70 text-blue-600 dark:text-blue-400 flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">
                                        <i class="bi bi-calendar-event-fill"></i>
                                    </div>
                                    <span class="px-2.5 py-0.5 rounded-full text-[9px] font-extrabold bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                                        <?= $upcoming_event_count ?> Scheduled
                                    </span>
                                </div>
                                <h3 class="text-base font-extrabold text-slate-900 dark:text-white">Schedule Events</h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                    View catering calendar, monitor scheduled event dates, and toggle date availability.
                                </p>
                            </div>
                            <div class="pt-5 mt-4 border-t border-slate-100 dark:border-slate-800">
                                <a href="admin_schedule.php" class="w-full flex items-center justify-center gap-2 py-3 rounded-2xl bg-blue-50 hover:bg-blue-600 dark:bg-blue-950/60 dark:hover:bg-blue-600 text-blue-700 hover:text-white dark:text-blue-300 dark:hover:text-white font-extrabold text-xs uppercase tracking-wider transition-all shadow-2xs">
                                    <span>View Calendar</span>
                                    <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- 4. Menu Catalog Card -->
                    <?php if (in_array('menu_catalog', $staff_perms)): ?>
                        <div class="action-card bg-white dark:bg-[#131b2e] rounded-3xl p-6 border border-emerald-100 dark:border-emerald-900/50 shadow-sm flex flex-col justify-between group">
                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950/70 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">
                                        <i class="bi bi-card-checklist"></i>
                                    </div>
                                    <span class="px-2.5 py-0.5 rounded-full text-[9px] font-extrabold bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                        <?= $total_menu_count ?> Dishes Active
                                    </span>
                                </div>
                                <h3 class="text-base font-extrabold text-slate-900 dark:text-white">Menu Catalog</h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                    Browse catering packages, dish recipes, manage prices, and check ingredients availability.
                                </p>
                            </div>
                            <div class="pt-5 mt-4 border-t border-slate-100 dark:border-slate-800">
                                <a href="inventory.php" class="w-full flex items-center justify-center gap-2 py-3 rounded-2xl bg-emerald-50 hover:bg-emerald-600 dark:bg-emerald-950/60 dark:hover:bg-emerald-600 text-emerald-700 hover:text-white dark:text-emerald-300 dark:hover:text-white font-extrabold text-xs uppercase tracking-wider transition-all shadow-2xs">
                                    <span>Manage Catalog</span>
                                    <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- 5. AI Venue & Conceptual Layout Card -->
                    <?php if (in_array('ai_venue_setup', $staff_perms)): ?>
                        <div class="action-card bg-white dark:bg-[#131b2e] rounded-3xl p-6 border border-indigo-100 dark:border-indigo-900/50 shadow-sm flex flex-col justify-between group">
                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">
                                        <i class="bi bi-magic"></i>
                                    </div>
                                    <span class="px-2.5 py-0.5 rounded-full text-[9px] font-extrabold bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                                        Admin Approval Mode
                                    </span>
                                </div>
                                <h3 class="text-base font-extrabold text-slate-900 dark:text-white">AI Venue Layout</h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                                    Generate AI venue staging & conceptual setups. Layouts are submitted for Admin review &amp; approval.
                                </p>
                            </div>
                            <div class="pt-5 mt-4 border-t border-slate-100 dark:border-slate-800">
                                <a href="admin_requests.php" class="w-full flex items-center justify-center gap-2 py-3 rounded-2xl bg-indigo-50 hover:bg-indigo-600 dark:bg-indigo-950/60 dark:hover:bg-indigo-600 text-indigo-700 hover:text-white dark:text-indigo-300 dark:hover:text-white font-extrabold text-xs uppercase tracking-wider transition-all shadow-2xs">
                                    <span>Open Bookings &amp; Layouts</span>
                                    <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        <?php endif; ?>

        <!-- Dynamic Workstation Previews based on Permitted Tasks -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- Workstation 1: Pending Bookings Preview (if permitted) -->
            <?php if (in_array('booking_requests', $staff_perms) && $recent_bookings): ?>
                <div class="bg-white dark:bg-[#131b2e] rounded-3xl p-6 border border-slate-200/80 dark:border-slate-800 shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2.5">
                                <i class="bi bi-bell-fill text-amber-500 text-lg"></i>
                                <h3 class="text-sm font-extrabold text-slate-900 dark:text-white">Pending Reservations</h3>
                            </div>
                            <a href="admin_requests.php" class="text-xs font-bold text-amber-600 dark:text-amber-400 hover:underline">
                                View All (<?= $pending_booking_count ?>)
                            </a>
                        </div>
                        <?php if ($recent_bookings->num_rows > 0): ?>
                            <div class="space-y-3">
                                <?php while ($bk = $recent_bookings->fetch_assoc()): ?>
                                    <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700 flex items-center justify-between gap-3">
                                        <div class="overflow-hidden">
                                            <p class="font-extrabold text-slate-900 dark:text-white text-xs truncate"><?= htmlspecialchars($bk['event_name']) ?></p>
                                            <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5"><?= htmlspecialchars($bk['client_name']) ?> • <?= date('M d, Y', strtotime($bk['event_date'])) ?></p>
                                        </div>
                                        <a href="view_booking.php?id=<?= $bk['booking_id'] ?>" class="px-3 py-1.5 rounded-xl bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300 text-[10px] font-bold uppercase tracking-wider hover:bg-amber-100 transition shrink-0">
                                            Verify
                                        </a>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="py-10 text-center text-slate-400 text-xs">
                                <i class="bi bi-check-circle text-2xl text-emerald-500 block mb-2"></i>
                                No pending bookings waiting for review.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Workstation 2: Upcoming Catering Schedule Preview (if permitted) -->
            <?php if (in_array('schedule_events', $staff_perms) && $upcoming_events): ?>
                <div class="bg-white dark:bg-[#131b2e] rounded-3xl p-6 border border-slate-200/80 dark:border-slate-800 shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2.5">
                                <i class="bi bi-calendar-event-fill text-blue-500 text-lg"></i>
                                <h3 class="text-sm font-extrabold text-slate-900 dark:text-white">Upcoming Catering Events</h3>
                            </div>
                            <a href="admin_schedule.php" class="text-xs font-bold text-blue-600 dark:text-blue-400 hover:underline">
                                Calendar View
                            </a>
                        </div>
                        <?php if ($upcoming_events->num_rows > 0): ?>
                            <div class="space-y-3">
                                <?php while ($ev = $upcoming_events->fetch_assoc()): ?>
                                    <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700 flex items-center justify-between gap-3">
                                        <div class="overflow-hidden">
                                            <p class="font-extrabold text-slate-900 dark:text-white text-xs truncate"><?= htmlspecialchars($ev['event_name']) ?></p>
                                            <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">
                                                <?= date('M d, Y', strtotime($ev['event_date'])) ?> at <?= date('h:i A', strtotime($ev['event_time'])) ?> • <?= htmlspecialchars($ev['client_name']) ?>
                                            </p>
                                        </div>
                                        <span class="px-2.5 py-1 rounded-xl bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300 text-[10px] font-bold uppercase tracking-wider shrink-0">
                                            <?= htmlspecialchars($ev['status']) ?>
                                        </span>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="py-10 text-center text-slate-400 text-xs">
                                <i class="bi bi-calendar-x text-2xl text-slate-300 block mb-2"></i>
                                No upcoming catering events scheduled.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>

    </main>

    <!-- Error / Restriction SweetAlert Alerts -->
    <?php if (isset($_GET['error'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                <?php if ($_GET['error'] === 'permission_denied'): ?>
                    Swal.fire({
                        title: 'Section Restricted',
                        text: 'You do not currently have permission to navigate to that section. Please check with your administrator to assign this task.',
                        icon: 'warning',
                        confirmButtonColor: '#7c3aed',
                        customClass: { popup: 'rounded-3xl' }
                    });
                <?php elseif ($_GET['error'] === 'admin_only'): ?>
                    Swal.fire({
                        title: 'Administrator Only',
                        text: 'This dashboard section is restricted exclusively to System Administrators.',
                        icon: 'info',
                        confirmButtonColor: '#7c3aed',
                        customClass: { popup: 'rounded-3xl' }
                    });
                <?php endif; ?>
            });
        </script>
    <?php endif; ?>

</body>
</html>

