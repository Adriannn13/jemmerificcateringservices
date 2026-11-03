<?php
// includes/topbar_actions.php - Unified Top Bar Action Icons (Profile, Notifications, Quick Updates) & Date
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($conn)) {
    require_once __DIR__ . '/../db_config.php';
}

$top_user_id   = $_SESSION['user_id'] ?? 0;
$top_user_name = $_SESSION['full_name'] ?? 'User';
$top_role      = strtolower($_SESSION['role'] ?? 'client');
$top_is_admin  = ($top_role === 'admin');
$top_is_staff  = ($top_role === 'staff');
$top_can_see_admin_notifs = $top_is_admin || ($top_is_staff && in_array('booking_requests', $_SESSION['permissions'] ?? []));

// 1. Fetch Notifications
$top_notif_count = 0;
$top_notif_items = [];

if ($top_can_see_admin_notifs) {
    // Admin: Pending bookings & Pending payments
    try {
        $p_bk = $conn->query("SELECT b.booking_id, b.event_name, b.created_at, COALESCE(u.full_name, 'Client') as client_name FROM bookings b LEFT JOIN users u ON b.user_id = u.user_id WHERE b.status = 'pending' ORDER BY b.created_at DESC LIMIT 3");
        if ($p_bk) {
            while ($row = $p_bk->fetch_assoc()) {
                $top_notif_items[] = [
                    'title' => 'Pending Booking Request',
                    'desc'  => $row['client_name'] . ' requested "' . $row['event_name'] . '"',
                    'time'  => date('M d, h:i A', strtotime($row['created_at'])),
                    'icon'  => 'bi-calendar-plus text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-950/60',
                    'link'  => 'view_booking.php?id=' . $row['booking_id']
                ];
                $top_notif_count++;
            }
        }

        $p_pay = $conn->query("SELECT p.amount, p.created_at, b.booking_id, b.event_name, COALESCE(u.full_name, 'Client') as client_name FROM payments p JOIN bookings b ON p.booking_id = b.booking_id LEFT JOIN users u ON b.user_id = u.user_id WHERE p.status = 'Pending' ORDER BY p.created_at DESC LIMIT 3");
        if ($p_pay) {
            while ($row = $p_pay->fetch_assoc()) {
                $top_notif_items[] = [
                    'title' => 'Payment For Verification',
                    'desc'  => '₱' . number_format($row['amount'], 2) . ' from ' . $row['client_name'],
                    'time'  => date('M d, h:i A', strtotime($row['created_at'])),
                    'icon'  => 'bi-cash-stack text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/60',
                    'link'  => 'view_booking.php?id=' . $row['booking_id']
                ];
                $top_notif_count++;
            }
        }
    } catch (Exception $e) {}
} else {
    // Customer: Notifications table
    try {
        $n_cnt = $conn->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
        $n_cnt->bind_param("i", $top_user_id);
        $n_cnt->execute();
        $top_notif_count = intval($n_cnt->get_result()->fetch_assoc()['total'] ?? 0);

        $n_list = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 4");
        $n_list->bind_param("i", $top_user_id);
        $n_list->execute();
        $n_res = $n_list->get_result();
        while ($row = $n_res->fetch_assoc()) {
            $top_notif_items[] = [
                'title' => $row['is_read'] ? 'Notification' : 'New Update',
                'desc'  => $row['message'],
                'time'  => date('M d, h:i A', strtotime($row['created_at'])),
                'icon'  => 'bi-bell-fill text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-950/60',
                'link'  => 'notifications.php'
            ];
        }
    } catch (Exception $e) {}
}

// 2. Fetch Quick Updates
$top_quick_updates = [];
try {
    if ($top_is_admin) {
        $qu_res = $conn->query("SELECT b.booking_id, b.event_name, b.status, b.event_date, b.pax_count, COALESCE(u.full_name, 'Client') as client_name, b.payment_status FROM bookings b LEFT JOIN users u ON b.user_id = u.user_id ORDER BY b.booking_id DESC LIMIT 4");
        if ($qu_res) {
            while ($qu = $qu_res->fetch_assoc()) {
                $top_quick_updates[] = $qu;
            }
        }
    } else {
        $cqu = $conn->prepare("SELECT booking_id, event_name, event_date, event_time, status, payment_status, pax_count FROM bookings WHERE user_id = ? ORDER BY booking_id DESC LIMIT 4");
        $cqu->bind_param("i", $top_user_id);
        $cqu->execute();
        $cqu_res = $cqu->get_result();
        while ($qu = $cqu_res->fetch_assoc()) {
            $top_quick_updates[] = $qu;
        }
    }
} catch (Exception $e) {}
return; // Action bar removed for clean, minimalist header across entire system
?>

<!-- Topbar Actions Container: Professional, Clean & Properly Positioned -->
<div class="flex items-center gap-2.5 sm:gap-3 shrink-0 relative select-none">
    
    <!-- Clean Date Display Pill (Visible on sm and up) -->
    <div class="hidden sm:inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 text-xs font-semibold text-slate-600 dark:text-slate-300 shadow-2xs">
        <i class="bi bi-calendar3 text-purple-600 dark:text-purple-400 text-xs"></i>
        <span><?= date('F j, Y') ?></span>
    </div>

    <!-- Unified Action Toolbar Card -->
    <div class="inline-flex items-center p-1 bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl shadow-2xs gap-0.5">

        <!-- 1. QUICK UPDATES -->
        <div class="relative">
            <button id="btnQuickUpdates" onclick="toggleTopMenu('dropdownQuickUpdates', event)" 
                    class="relative w-9 h-9 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-amber-600 dark:hover:text-amber-400 transition-all flex items-center justify-center cursor-pointer group"
                    title="Quick Updates & Live Activity" aria-label="Quick Updates">
                <i class="bi bi-lightning-charge-fill text-amber-500 group-hover:scale-110 transition-transform text-sm"></i>
                <span class="absolute top-2 right-2 w-2 h-2 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-slate-900"></span>
            </button>

            <!-- Quick Updates Dropdown -->
            <div id="dropdownQuickUpdates" class="hidden absolute right-0 mt-2.5 w-[min(24rem,calc(100vw-2.5rem))] sm:w-96 bg-white dark:bg-[#131b2e] rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 overflow-hidden z-[120] transition-all">
                <div class="p-3.5 bg-slate-50/80 dark:bg-slate-900/60 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xs">
                            <i class="bi bi-lightning-charge-fill"></i>
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-slate-900 dark:text-white">Quick Updates</h4>
                            <p class="text-[10px] text-slate-500 dark:text-slate-400"><?= $top_is_admin ? 'Operational Activity' : 'Your Event Status' ?></p>
                        </div>
                    </div>
                    <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-emerald-50 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Live
                    </span>
                </div>

                <div class="p-2.5 max-h-72 overflow-y-auto space-y-1.5">
                    <?php if (!empty($top_quick_updates)): ?>
                        <?php foreach ($top_quick_updates as $qu): ?>
                            <?php
                                $st = strtolower($qu['status'] ?? 'pending');
                                $badge_map = [
                                    'confirmed'   => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800',
                                    'on_progress' => 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800',
                                    'pending'     => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800',
                                    'completed'   => 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/60 dark:text-purple-300 dark:border-purple-800',
                                    'cancelled'   => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-800'
                                ];
                                $badge_cls = $badge_map[$st] ?? 'bg-slate-50 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700';
                            ?>
                            <div class="p-2 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-slate-100 dark:border-slate-800/80 hover:border-amber-200 dark:hover:border-amber-700/50 transition flex items-center justify-between gap-2.5">
                                <div class="overflow-hidden">
                                    <p class="text-xs font-semibold text-slate-900 dark:text-white truncate"><?= htmlspecialchars($qu['event_name']) ?></p>
                                    <div class="flex items-center gap-2 mt-0.5 text-[10px] text-slate-500 dark:text-slate-400">
                                        <span><?= date('M d, Y', strtotime($qu['event_date'])) ?></span>
                                        <span>•</span>
                                        <span><?= $qu['pax_count'] ?? 100 ?> Pax</span>
                                        <?php if ($top_is_admin && !empty($qu['client_name'])): ?>
                                            <span>•</span>
                                            <span class="truncate max-w-[80px]"><?= htmlspecialchars($qu['client_name']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider border shrink-0 <?= $badge_cls ?>">
                                    <?= htmlspecialchars($qu['status']) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-6 text-center text-slate-400">
                            <i class="bi bi-inbox text-2xl block mb-1"></i>
                            <p class="text-xs font-medium">No recent updates</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="p-2.5 bg-slate-50/80 dark:bg-slate-900/80 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs">
                    <?php if ($top_is_admin): ?>
                        <a href="admin_schedule.php" class="text-purple-600 dark:text-purple-400 font-bold hover:underline flex items-center gap-1">
                            <i class="bi bi-calendar3"></i> Master Schedule
                        </a>
                        <a href="admin_requests.php" class="text-slate-600 dark:text-slate-400 font-bold hover:underline flex items-center gap-1">
                            Booking Requests &rarr;
                        </a>
                    <?php else: ?>
                        <a href="dashboard.php" class="text-purple-600 dark:text-purple-400 font-bold hover:underline flex items-center gap-1">
                            <i class="bi bi-plus-circle-fill"></i> Book New Event
                        </a>
                        <a href="transactions.php" class="text-slate-600 dark:text-slate-400 font-bold hover:underline flex items-center gap-1">
                            View All Bookings &rarr;
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Subtle Vertical Separator -->
        <div class="w-px h-5 bg-slate-200 dark:bg-slate-800 mx-0.5"></div>

        <!-- 2. NOTIFICATIONS -->
        <div class="relative">
            <button id="btnNotif" onclick="toggleTopMenu('dropdownNotif', event)" 
                    class="relative w-9 h-9 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-purple-600 dark:hover:text-purple-400 transition-all flex items-center justify-center cursor-pointer group"
                    title="Notifications" aria-label="Notifications">
                <i class="bi bi-bell text-slate-600 dark:text-slate-300 group-hover:text-purple-600 dark:group-hover:text-purple-400 text-sm"></i>
                <?php if ($top_notif_count > 0): ?>
                    <span class="absolute top-1.5 right-1.5 min-w-[16px] h-4 px-1 rounded-full bg-rose-500 text-white text-[9px] font-extrabold flex items-center justify-center ring-2 ring-white dark:ring-slate-900 shadow-2xs">
                        <?= $top_notif_count > 9 ? '9+' : $top_notif_count ?>
                    </span>
                <?php endif; ?>
            </button>

            <!-- Notifications Dropdown -->
            <div id="dropdownNotif" class="hidden absolute right-0 mt-2.5 w-[min(24rem,calc(100vw-2.5rem))] sm:w-96 bg-white dark:bg-[#131b2e] rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 overflow-hidden z-[120] transition-all">
                <div class="p-3.5 bg-slate-50/80 dark:bg-slate-900/60 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-lg bg-purple-600/10 text-purple-600 dark:text-purple-400 flex items-center justify-center text-xs">
                            <i class="bi bi-bell-fill"></i>
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-slate-900 dark:text-white">Notifications</h4>
                            <p class="text-[10px] text-slate-500 dark:text-slate-400"><?= $top_notif_count ?> unread notification<?= $top_notif_count == 1 ? '' : 's' ?></p>
                        </div>
                    </div>
                    <?php if (!$top_is_admin && $top_notif_count > 0): ?>
                        <a href="notifications.php?action=mark_read" class="text-[10px] font-bold text-purple-600 dark:text-purple-400 hover:underline">
                            Mark all read
                        </a>
                    <?php endif; ?>
                </div>

                <div class="p-2.5 max-h-72 overflow-y-auto space-y-1.5">
                    <?php if (!empty($top_notif_items)): ?>
                        <?php foreach ($top_notif_items as $item): ?>
                            <a href="<?= htmlspecialchars($item['link']) ?>" class="p-2.5 rounded-xl bg-slate-50/70 dark:bg-slate-900/40 border border-slate-100 dark:border-slate-800/80 hover:border-purple-200 dark:hover:border-purple-700/50 transition flex items-start gap-2.5 group/notif block">
                                <div class="w-7 h-7 rounded-lg flex items-center justify-center text-xs shrink-0 <?= $item['icon'] ?>">
                                    <i class="bi bi-bell"></i>
                                </div>
                                <div class="flex-1 overflow-hidden">
                                    <p class="text-xs font-semibold text-slate-900 dark:text-white group-hover/notif:text-purple-600 dark:group-hover/notif:text-purple-400 transition-colors truncate">
                                        <?= htmlspecialchars($item['title']) ?>
                                    </p>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 line-clamp-2 mt-0.5 leading-snug">
                                        <?= htmlspecialchars($item['desc']) ?>
                                    </p>
                                    <span class="text-[9px] text-slate-400 dark:text-slate-500 mt-1 block">
                                        <i class="bi bi-clock"></i> <?= $item['time'] ?>
                                    </span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-6 text-center text-slate-400">
                            <i class="bi bi-bell-slash text-2xl block mb-1"></i>
                            <p class="text-xs font-medium">No new notifications</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="p-2.5 bg-slate-50/80 dark:bg-slate-900/80 border-t border-slate-100 dark:border-slate-800 text-center">
                    <a href="<?= $top_is_admin ? 'admin_requests.php' : 'notifications.php' ?>" class="text-xs font-bold text-purple-600 dark:text-purple-400 hover:underline">
                        View All in Notification Center &rarr;
                    </a>
                </div>
            </div>
        </div>

        <!-- Subtle Vertical Separator -->
        <div class="w-px h-5 bg-slate-200 dark:bg-slate-800 mx-0.5"></div>

        <!-- 3. PROFILE PILL -->
        <div class="relative">
            <button id="btnProfile" onclick="toggleTopMenu('dropdownProfile', event)" 
                    class="h-9 pl-1 pr-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-all flex items-center gap-2 cursor-pointer group"
                    title="Account & Profile" aria-label="Account & Profile">
                <div class="w-7 h-7 rounded-lg bg-gradient-to-tr from-purple-600 to-indigo-600 text-white font-bold text-xs flex items-center justify-center shadow-xs uppercase">
                    <?= substr($top_user_name, 0, 1) ?>
                </div>
                <span class="text-xs font-bold text-slate-700 dark:text-slate-200 hidden md:inline max-w-[100px] truncate">
                    <?= htmlspecialchars(explode(' ', $top_user_name)[0]) ?>
                </span>
                <i class="bi bi-chevron-down text-[10px] text-slate-400 group-hover:text-slate-600 dark:group-hover:text-slate-200 transition-colors"></i>
            </button>

            <!-- Profile Dropdown -->
            <div id="dropdownProfile" class="hidden absolute right-0 mt-2.5 w-[min(20rem,calc(100vw-2.5rem))] sm:w-72 bg-white dark:bg-[#131b2e] rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 overflow-hidden z-[120] transition-all">
                <div class="p-3.5 bg-slate-50/80 dark:bg-slate-900/60 border-b border-slate-100 dark:border-slate-800 flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-purple-600 to-indigo-600 text-white font-extrabold text-sm flex items-center justify-center shadow-xs uppercase shrink-0">
                        <?= substr($top_user_name, 0, 1) ?>
                    </div>
                    <div class="overflow-hidden">
                        <p class="text-xs font-bold text-slate-900 dark:text-white truncate"><?= htmlspecialchars($top_user_name) ?></p>
                        <span class="inline-block mt-0.5 px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider <?= $top_is_admin ? 'bg-purple-100 dark:bg-purple-950/80 text-purple-700 dark:text-purple-300' : 'bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300' ?>">
                            <?= $top_is_admin ? 'Administrator' : 'Client Customer' ?>
                        </span>
                    </div>
                </div>

                <div class="p-2 space-y-0.5">
                    <?php if ($top_is_admin): ?>
                        <a href="admin_settings.php" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-purple-50 dark:hover:bg-purple-950/60 hover:text-purple-600 dark:hover:text-purple-400 transition">
                            <i class="bi bi-sliders text-sm text-purple-600 dark:text-purple-400"></i>
                            <span>Admin & AI Settings</span>
                        </a>
                        <a href="admin_schedule.php" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-purple-50 dark:hover:bg-purple-950/60 hover:text-purple-600 dark:hover:text-purple-400 transition">
                            <i class="bi bi-calendar-event text-sm text-purple-600 dark:text-purple-400"></i>
                            <span>Master Schedule</span>
                        </a>
                        <a href="users.php" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-purple-50 dark:hover:bg-purple-950/60 hover:text-purple-600 dark:hover:text-purple-400 transition">
                            <i class="bi bi-people text-sm text-purple-600 dark:text-purple-400"></i>
                            <span>Customer Directory</span>
                        </a>
                    <?php else: ?>
                        <a href="settings.php" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-purple-50 dark:hover:bg-purple-950/60 hover:text-purple-600 dark:hover:text-purple-400 transition">
                            <i class="bi bi-person-gear text-sm text-purple-600 dark:text-purple-400"></i>
                            <span>Account Settings</span>
                        </a>
                        <a href="transactions.php" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-purple-50 dark:hover:bg-purple-950/60 hover:text-purple-600 dark:hover:text-purple-400 transition">
                            <i class="bi bi-receipt text-sm text-purple-600 dark:text-purple-400"></i>
                            <span>Transactions & Invoices</span>
                        </a>
                        <a href="messages.php" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-purple-50 dark:hover:bg-purple-950/60 hover:text-purple-600 dark:hover:text-purple-400 transition">
                            <i class="bi bi-chat-dots text-sm text-purple-600 dark:text-purple-400"></i>
                            <span>Messages with Catering</span>
                        </a>
                    <?php endif; ?>

                    <div class="pt-1 mt-1 border-t border-slate-100 dark:border-slate-800">
                        <a href="auth/logout.php" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition">
                            <i class="bi bi-box-arrow-right text-sm"></i>
                            <span>Sign Out</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>

<!-- Dropdown Click Outside & Toggle JavaScript Handler -->
<script>
    if (typeof toggleTopMenu !== 'function') {
        function toggleTopMenu(menuId, event) {
            if (event) event.stopPropagation();
            const menus = ['dropdownQuickUpdates', 'dropdownNotif', 'dropdownProfile'];
            menus.forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    if (id === menuId) {
                        el.classList.toggle('hidden');
                    } else {
                        el.classList.add('hidden');
                    }
                }
            });
        }

        document.addEventListener('click', function(e) {
            const dropdownIds = ['dropdownQuickUpdates', 'dropdownNotif', 'dropdownProfile'];
            const buttonIds = ['btnQuickUpdates', 'btnNotif', 'btnProfile'];
            
            const isClickInsideDropdown = dropdownIds.some(id => {
                const el = document.getElementById(id);
                return el && el.contains(e.target);
            });

            const isClickOnButton = buttonIds.some(id => {
                const el = document.getElementById(id);
                return el && el.contains(e.target);
            });

            if (!isClickInsideDropdown && !isClickOnButton) {
                dropdownIds.forEach(id => {
                    document.getElementById(id)?.classList.add('hidden');
                });
            }
        });
    }
</script>
