<?php
require_once 'includes/auth_check.php';
require_once 'db_config.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'User';

// Fetch Unread Notifications Count
$notif_query = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0";
$stmt = $conn->prepare($notif_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notif_count = $stmt->get_result()->fetch_assoc()['total'];

// Fetch Transaction History with Payment Proof Detection
$history_query = "SELECT b.*, 
                  (SELECT COUNT(*) FROM payments p WHERE p.booking_id = b.booking_id) as proof_submitted 
                  FROM bookings b 
                  WHERE b.user_id = ? 
                  ORDER BY b.created_at DESC";
$stmt3 = $conn->prepare($history_query);
$stmt3->bind_param("i", $user_id);
$stmt3->execute();
$history_res = $stmt3->get_result();

// Categorize into Active, Completed, Cancelled, and All
$all_bookings = [];
$active_bookings = [];
$completed_bookings = [];
$cancelled_bookings = [];

while ($row = $history_res->fetch_assoc()) {
    $all_bookings[] = $row;
    $st = strtolower($row['status'] ?? '');
    if ($st === 'completed') {
        $completed_bookings[] = $row;
    } elseif ($st === 'cancelled') {
        $cancelled_bookings[] = $row;
    } else {
        // Pending, on_progress, confirmed are active transactions
        $active_bookings[] = $row;
    }
}

// Determine default active tab
$active_tab = $_GET['tab'] ?? (!empty($active_bookings) ? 'active' : (!empty($completed_bookings) ? 'completed' : 'all'));
$allowed_tabs = ['active', 'completed', 'all', 'cancelled'];
if (!in_array($active_tab, $allowed_tabs)) {
    $active_tab = 'active';
}

// Check for unfinished in-progress booking
$unf_stmt = $conn->prepare("SELECT b.*, 
       (SELECT COUNT(*) FROM payments p WHERE p.booking_id = b.booking_id) as proof_submitted,
       (SELECT COUNT(*) FROM booking_items bi WHERE bi.booking_id = b.booking_id) as items_count
FROM bookings b
WHERE b.user_id = ? 
  AND b.status = 'pending' 
  AND b.payment_status = 'unpaid'
ORDER BY b.created_at DESC
LIMIT 1");
$unf_stmt->bind_param("i", $user_id);
$unf_stmt->execute();
$unf_res = $unf_stmt->get_result();
$unfinished_booking = null;
$resume_url = 'dashboard.php';
if ($unf_row = $unf_res->fetch_assoc()) {
    if ($unf_row['proof_submitted'] == 0) {
        $unfinished_booking = $unf_row;
        $resume_url = ($unf_row['items_count'] > 0) 
            ? "payment.php?booking_id={$unf_row['booking_id']}" 
            : "menu.php?booking_id={$unf_row['booking_id']}";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History | JEMmerific</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F8FAFC; }
        .tab-btn {
            box-shadow: none !important;
            transition: all 0.15s ease-in-out;
        }
        .tab-btn:hover {
            box-shadow: none !important;
        }
        /* Clean tabs & button styles without unnecessary hover shadows */
        .tab-btn {
            box-shadow: none !important;
            transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease;
        }
        .tab-btn:hover {
            box-shadow: none !important;
        }
        .tab-btn.active {
            background-color: #7c3aed !important;
            color: #ffffff !important;
            border-color: #7c3aed !important;
            box-shadow: none !important;
        }
        .tab-btn.active, .tab-btn.active * {
            color: #ffffff !important;
        }
        .tab-btn.active:hover, .tab-btn.active:hover * {
            background-color: #6d28d9 !important;
            color: #ffffff !important;
            box-shadow: none !important;
        }
        html.dark .tab-btn.active {
            background-color: #7c3aed !important;
            color: #ffffff !important;
            border-color: #7c3aed !important;
            box-shadow: none !important;
        }
        html.dark .tab-btn.active, html.dark .tab-btn.active * {
            color: #ffffff !important;
        }
        html.dark .tab-btn.active:hover, html.dark .tab-btn.active:hover * {
            background-color: #6d28d9 !important;
            color: #ffffff !important;
            box-shadow: none !important;
        }
        /* Completely remove unnecessary shadows on all buttons and interactive links */
        button, a.btn, .tab-btn, button:hover, a:hover, .btn:hover, .tab-btn:hover,
        button:focus, a:focus, .btn:focus, .tab-btn:focus,
        button:active, a:active, .btn:active, .tab-btn:active {
            box-shadow: none !important;
            -webkit-box-shadow: none !important;
            filter: none !important;
        }
    </style>
</head>
<body class="flex min-h-screen text-slate-800">

    <?php require_once 'includes/customer_sidebar.php'; ?>

    <main class="flex-1 lg:ml-64 p-4 sm:p-6 md:p-8 transition-all">
        
        <!-- Header Top Bar -->
        <header class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8 pb-6 border-b border-slate-200/80 dark:border-slate-800">
            <div class="flex items-center gap-3.5">
                <button onclick="toggleSidebar()" class="lg:hidden p-2.5 min-h-[44px] min-w-[44px] flex items-center justify-center bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-600 dark:text-slate-200 hover:bg-slate-50 transition-colors shadow-none">
                    <i class="bi bi-list text-xl"></i>
                </button>
                <div>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">Transaction History</h2>
                    <p class="text-xs sm:text-sm font-semibold text-slate-500 dark:text-slate-400 mt-0.5">Track your active event bookings, payment status, and completed transaction receipts.</p>
                </div>
            </div>

            <div class="flex items-center gap-3 w-full sm:w-auto">
                <a href="dashboard.php" class="w-full sm:w-auto justify-center px-5 py-2.5 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-extrabold rounded-xl shadow-none text-xs uppercase tracking-wider transition-all flex items-center gap-2 min-h-[44px] active:scale-95">
                    <i class="bi bi-plus-lg text-sm"></i> Book New Event
                </a>
            </div>
        </header>

        <!-- Metric Stat Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
            <!-- Active & In-Progress -->
            <div onclick="switchTransactionTab('active')" class="cursor-pointer bg-white dark:bg-[#131b2e] p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 hover:border-amber-300 dark:hover:border-amber-700/60 shadow-xs transition-all flex items-center justify-between group">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-amber-600 dark:text-amber-400">Active & In-Progress</span>
                    <h3 class="text-3xl font-black text-slate-900 dark:text-white mt-1"><?= count($active_bookings) ?></h3>
                    <p class="text-[11px] text-slate-400 dark:text-slate-500 font-medium mt-0.5">Pending & ongoing reservations</p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xl shrink-0 group-hover:scale-105 transition-transform">
                    <i class="bi bi-hourglass-split"></i>
                </div>
            </div>

            <!-- Completed Transactions -->
            <div onclick="switchTransactionTab('completed')" class="cursor-pointer bg-white dark:bg-[#131b2e] p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 hover:border-emerald-300 dark:hover:border-emerald-700/60 shadow-xs transition-all flex items-center justify-between group">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Completed History</span>
                    <h3 class="text-3xl font-black text-slate-900 dark:text-white mt-1"><?= count($completed_bookings) ?></h3>
                    <p class="text-[11px] text-slate-400 dark:text-slate-500 font-medium mt-0.5">Finalized events & official receipts</p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl shrink-0 group-hover:scale-105 transition-transform">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
            </div>

            <!-- Total Transactions -->
            <div onclick="switchTransactionTab('all')" class="cursor-pointer bg-white dark:bg-[#131b2e] p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 hover:border-purple-300 dark:hover:border-purple-700/60 shadow-xs transition-all flex items-center justify-between group">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-purple-600 dark:text-purple-400">All Transactions</span>
                    <h3 class="text-3xl font-black text-slate-900 dark:text-white mt-1"><?= count($all_bookings) ?></h3>
                    <p class="text-[11px] text-slate-400 dark:text-slate-500 font-medium mt-0.5">Total reservation history</p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center text-xl shrink-0 group-hover:scale-105 transition-transform">
                    <i class="bi bi-receipt-cutoff"></i>
                </div>
            </div>
        </div>

        <?php if ($unfinished_booking): ?>
        <!-- Unfinished Booking Notification Banner -->
        <div class="mb-8 p-5 sm:p-6 bg-gradient-to-r from-amber-500/10 via-purple-500/10 to-indigo-500/10 dark:from-amber-950/30 dark:via-purple-950/30 dark:to-indigo-950/30 border-2 border-amber-400 dark:border-amber-600/80 rounded-3xl shadow-sm relative overflow-hidden backdrop-blur-md">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-5 relative z-10">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-amber-500 text-white flex items-center justify-center text-2xl shrink-0 shadow-md shadow-amber-500/30">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap mb-1">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-500 text-white shadow-2xs">
                                Incomplete Booking in Progress
                            </span>
                            <span class="text-xs font-black text-slate-900 dark:text-white">
                                Ref: #BK-<?= str_pad($unfinished_booking['booking_id'], 4, '0', STR_PAD_LEFT) ?>
                            </span>
                        </div>
                        <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white tracking-tight">
                            <?= htmlspecialchars($unfinished_booking['event_name'] ?: 'Untitled Event') ?>
                        </h3>
                        <p class="text-xs text-slate-600 dark:text-slate-400 font-medium mt-1 flex items-center gap-3 flex-wrap">
                            <span><i class="bi bi-calendar-event text-purple-600 dark:text-purple-400 mr-1"></i><?= date('F d, Y', strtotime($unfinished_booking['event_date'])) ?></span>
                            <span><i class="bi bi-people-fill text-purple-600 dark:text-purple-400 mr-1"></i><?= $unfinished_booking['pax_count'] ?> Guests</span>
                            <span><i class="bi bi-geo-alt-fill text-purple-600 dark:text-purple-400 mr-1"></i><?= htmlspecialchars($unfinished_booking['venue_location'] ?: 'Venue pending') ?></span>
                        </p>
                    </div>
                </div>
                
                <div class="flex items-center gap-2.5 w-full md:w-auto justify-end flex-wrap">
                    <a href="<?= $resume_url ?>" class="w-full sm:w-auto min-h-[44px] px-6 py-2.5 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white font-black text-xs uppercase tracking-wider rounded-xl shadow-none transition-all flex items-center justify-center gap-2">
                        <i class="bi bi-play-circle-fill text-sm"></i> Continue Booking
                    </a>
                    <a href="dashboard.php?booking_id=<?= $unfinished_booking['booking_id'] ?>" class="w-full sm:w-auto min-h-[44px] px-4 py-2.5 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700 font-bold text-xs uppercase tracking-wider rounded-xl transition-all flex items-center justify-center gap-1.5" title="Edit Step 1: Event Details">
                        <i class="bi bi-pencil-square text-purple-600 dark:text-purple-400"></i> Edit Details
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Organized Navigation Section Tabs -->
        <div class="flex items-center gap-2 overflow-x-auto pb-2 mb-6 no-scrollbar">
            <button onclick="switchTransactionTab('active')" id="tabBtnActive" class="tab-btn px-4 py-2.5 rounded-2xl text-xs font-bold transition-colors flex items-center gap-2 whitespace-nowrap bg-white dark:bg-[#131b2e] border border-slate-200/80 dark:border-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/80 <?= $active_tab === 'active' ? 'active' : '' ?>">
                <i class="bi bi-hourglass-split"></i>
                <span>Active & In-Progress</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-500 text-white"><?= count($active_bookings) ?></span>
            </button>

            <button onclick="switchTransactionTab('completed')" id="tabBtnCompleted" class="tab-btn px-4 py-2.5 rounded-2xl text-xs font-bold transition-colors flex items-center gap-2 whitespace-nowrap bg-white dark:bg-[#131b2e] border border-slate-200/80 dark:border-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/80 <?= $active_tab === 'completed' ? 'active' : '' ?>">
                <i class="bi bi-check-circle-fill"></i>
                <span>Completed History</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-600 text-white"><?= count($completed_bookings) ?></span>
            </button>

            <button onclick="switchTransactionTab('all')" id="tabBtnAll" class="tab-btn px-4 py-2.5 rounded-2xl text-xs font-bold transition-colors flex items-center gap-2 whitespace-nowrap bg-white dark:bg-[#131b2e] border border-slate-200/80 dark:border-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/80 <?= $active_tab === 'all' ? 'active' : '' ?>">
                <i class="bi bi-collection-fill"></i>
                <span>All Transactions</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-slate-500 text-white"><?= count($all_bookings) ?></span>
            </button>

            <?php if (!empty($cancelled_bookings)): ?>
            <button onclick="switchTransactionTab('cancelled')" id="tabBtnCancelled" class="tab-btn px-4 py-2.5 rounded-2xl text-xs font-bold transition-colors flex items-center gap-2 whitespace-nowrap bg-white dark:bg-[#131b2e] border border-slate-200/80 dark:border-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/80 <?= $active_tab === 'cancelled' ? 'active' : '' ?>">
                <i class="bi bi-x-circle-fill"></i>
                <span>Cancelled</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-rose-500 text-white"><?= count($cancelled_bookings) ?></span>
            </button>
            <?php endif; ?>
        </div>

        <?php
        // Reusable Helper to render table rows cleanly
        function renderBookingTableRows($bookingsList, $show_empty_msg = 'No transactions found in this section.') {
            if (empty($bookingsList)) {
                echo '<tr><td colspan="5" class="px-6 py-12 text-center text-slate-400 dark:text-slate-500 text-xs font-bold uppercase tracking-wider">' . htmlspecialchars($show_empty_msg) . '</td></tr>';
                return;
            }

            foreach ($bookingsList as $row) {
                $status_lc = strtolower($row['status'] ?? 'pending');
                $ps_lc = strtolower($row['payment_status'] ?? 'unpaid');
                $has_proof = ($row['proof_submitted'] ?? 0) > 0;
                $refund_amount = floatval($row['refund_amount'] ?? 0);
                
                $badge_text = 'Processing';
                $badge_class = 'bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800';
                
                if ($status_lc === 'cancelled') {
                    $badge_text = 'Cancelled';
                    $badge_class = 'bg-rose-50 text-rose-600 border border-rose-200 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-800';
                    if ($refund_amount > 0) {
                        $badge_text .= ' (Refunded)';
                    }
                } elseif ($status_lc === 'completed' || $ps_lc === 'fully_paid') {
                    $badge_text = 'Completed';
                    $badge_class = 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800';
                } elseif ($status_lc === 'on_progress') {
                    $badge_text = 'In Progress';
                    $badge_class = 'bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800';
                } elseif ($status_lc === 'confirmed' || $ps_lc === 'partially_paid') {
                    $badge_text = 'Confirmed';
                    $badge_class = 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800';
                } elseif ($status_lc === 'pending' && $has_proof) {
                    $badge_text = 'Pending Review';
                    $badge_class = 'bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800';
                } elseif ($status_lc === 'pending') {
                    $badge_text = 'Draft / Unpaid';
                    $badge_class = 'bg-slate-100 text-slate-600 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700';
                }
                ?>
                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-black text-purple-600 dark:text-purple-400 uppercase tracking-wider bg-purple-50 dark:bg-purple-950/80 px-2 py-0.5 rounded-full border border-purple-100 dark:border-purple-800">
                                #BK-<?= str_pad($row['booking_id'], 4, '0', STR_PAD_LEFT) ?>
                            </span>
                            <p class="text-sm font-extrabold text-slate-900 dark:text-white"><?= htmlspecialchars($row['event_name']) ?></p>
                        </div>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-[11px] font-semibold text-purple-600 dark:text-purple-400 uppercase tracking-wider"><?= htmlspecialchars($row['event_type'] ?? 'General') ?></span>
                            <span class="text-slate-300 dark:text-slate-600">•</span>
                            <span class="text-[11px] text-slate-500 dark:text-slate-400"><i class="bi bi-people"></i> <?= $row['pax_count'] ?> Guests</span>
                            <span class="text-slate-300 dark:text-slate-600">•</span>
                            <span class="text-[11px] text-slate-500 dark:text-slate-400 truncate max-w-[180px]" title="<?= htmlspecialchars($row['venue_location'] ?? '') ?>">
                                <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($row['venue_location'] ?? 'Venue TBD') ?>
                            </span>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-xs font-semibold text-slate-600 dark:text-slate-300">
                        <div class="flex items-center gap-1.5">
                            <i class="bi bi-calendar-event text-slate-400 dark:text-slate-500"></i>
                            <?= date('M d, Y', strtotime($row['event_date'])) ?>
                        </div>
                        <span class="text-[11px] text-slate-400 dark:text-slate-500"><?= date('h:i A', strtotime($row['event_time'])) ?></span>
                    </td>
                    <td class="px-6 py-4 text-sm font-black text-purple-700 dark:text-purple-300">
                        ₱<?= number_format($row['total_amount'], 2) ?>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="<?= $badge_class ?> px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider inline-flex items-center gap-1 shadow-none">
                            <?= $badge_text ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <?php if ($status_lc === 'pending' && !$has_proof): ?>
                                <?php if ($row['total_amount'] > 0): ?>
                                    <a href="payment.php?booking_id=<?= $row['booking_id'] ?>" class="px-3.5 py-2 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white rounded-xl text-xs font-bold uppercase tracking-wider transition-colors shadow-none flex items-center gap-1 min-h-[36px]">
                                        <i class="bi bi-credit-card-fill"></i> Pay Now
                                    </a>
                                <?php else: ?>
                                    <a href="menu.php?booking_id=<?= $row['booking_id'] ?>" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold uppercase tracking-wider transition-colors shadow-none flex items-center gap-1 min-h-[36px]">
                                        <i class="bi bi-egg-fried"></i> Pick Menu
                                    </a>
                                <?php endif; ?>
                                <a href="dashboard.php?edit_id=<?= $row['booking_id'] ?>" class="p-2 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition-colors" title="Edit Event">
                                    <i class="bi bi-pencil-fill text-sm"></i>
                                </a>
                                <button onclick="deleteBooking(<?= $row['booking_id'] ?>)" class="p-2 text-rose-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-xl transition-colors" title="Delete Draft">
                                    <i class="bi bi-trash-fill text-sm"></i>
                                </button>
                            <?php elseif ($status_lc === 'completed' || $ps_lc === 'fully_paid' || $has_proof): ?>
                                <a href="receipt.php?booking_id=<?= $row['booking_id'] ?>" target="_blank" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700 rounded-xl text-xs font-bold uppercase tracking-wider transition-colors inline-flex items-center gap-1.5 min-h-[36px] shadow-none">
                                    <i class="bi bi-file-earmark-text text-purple-600 dark:text-purple-400"></i> Receipt
                                </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php
            }
        }
        ?>

        <!-- SECTION 1: ACTIVE & IN-PROGRESS REQUESTS -->
        <div id="sectionActive" class="transaction-section <?= $active_tab === 'active' ? '' : 'hidden' ?> space-y-4">
            <div class="w-full bg-white dark:bg-[#131b2e] rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden">
                <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-50/50 dark:bg-slate-900/40">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold text-lg">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">Active & In-Progress Bookings</h3>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500 text-white"><?= count($active_bookings) ?> Active</span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Reservations currently awaiting payment, admin verification, or in-progress preparation</p>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-800 text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                <th class="px-6 py-4">Event Details</th>
                                <th class="px-6 py-4">Event Date</th>
                                <th class="px-6 py-4">Total Amount</th>
                                <th class="px-6 py-4 text-center">Status</th>
                                <th class="px-6 py-4 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <?php renderBookingTableRows($active_bookings, 'No active booking requests in progress. All reservations are completed!'); ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- SECTION 2: COMPLETED TRANSACTION HISTORY -->
        <div id="sectionCompleted" class="transaction-section <?= $active_tab === 'completed' ? '' : 'hidden' ?> space-y-4">
            <div class="w-full bg-white dark:bg-[#131b2e] rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden">
                <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-50/50 dark:bg-slate-900/40">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold text-lg">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">Completed Transaction History</h3>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-600 text-white"><?= count($completed_bookings) ?> Completed</span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Archived records of fulfilled catering celebrations with verified invoices and downloadable receipts</p>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-800 text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                <th class="px-6 py-4">Event Details</th>
                                <th class="px-6 py-4">Event Date</th>
                                <th class="px-6 py-4">Total Amount</th>
                                <th class="px-6 py-4 text-center">Status</th>
                                <th class="px-6 py-4 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <?php renderBookingTableRows($completed_bookings, 'No completed transactions found yet. Fulfilled bookings will automatically archive here.'); ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- SECTION 3: ALL TRANSACTIONS -->
        <div id="sectionAll" class="transaction-section <?= $active_tab === 'all' ? '' : 'hidden' ?> space-y-4">
            <div class="w-full bg-white dark:bg-[#131b2e] rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden">
                <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-50/50 dark:bg-slate-900/40">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center font-bold text-lg">
                            <i class="bi bi-collection-fill"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">All Transaction Records</h3>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-600 text-white"><?= count($all_bookings) ?> Total</span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Chronological ledger across all your past and ongoing catering events</p>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-800 text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                <th class="px-6 py-4">Event Details</th>
                                <th class="px-6 py-4">Event Date</th>
                                <th class="px-6 py-4">Total Amount</th>
                                <th class="px-6 py-4 text-center">Status</th>
                                <th class="px-6 py-4 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <?php renderBookingTableRows($all_bookings, 'No transaction history found. Book your first event now!'); ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if (!empty($cancelled_bookings)): ?>
        <!-- SECTION 4: CANCELLED TRANSACTIONS -->
        <div id="sectionCancelled" class="transaction-section <?= $active_tab === 'cancelled' ? '' : 'hidden' ?> space-y-4">
            <div class="w-full bg-white dark:bg-[#131b2e] rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden">
                <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-50/50 dark:bg-slate-900/40">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center font-bold text-lg">
                            <i class="bi bi-x-circle-fill"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">Cancelled Bookings</h3>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500 text-white"><?= count($cancelled_bookings) ?> Cancelled</span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Records of cancelled reservations and refund statuses</p>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-800 text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                <th class="px-6 py-4">Event Details</th>
                                <th class="px-6 py-4">Event Date</th>
                                <th class="px-6 py-4">Total Amount</th>
                                <th class="px-6 py-4 text-center">Status</th>
                                <th class="px-6 py-4 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <?php renderBookingTableRows($cancelled_bookings, 'No cancelled bookings.'); ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </main>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
            document.getElementById('sidebarOverlay').classList.toggle('hidden');
        }

        function switchTransactionTab(tabName) {
            // Hide all sections
            document.querySelectorAll('.transaction-section').forEach(sec => {
                sec.classList.add('hidden');
            });

            // Deactivate all tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected section
            const targetSec = document.getElementById('section' + tabName.charAt(0).toUpperCase() + tabName.slice(1));
            if (targetSec) {
                targetSec.classList.remove('hidden');
            }

            // Activate button
            const targetBtn = document.getElementById('tabBtn' + tabName.charAt(0).toUpperCase() + tabName.slice(1));
            if (targetBtn) {
                targetBtn.classList.add('active');
            }

            // Sync with history state
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.replaceState({}, '', url);
        }

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('msg') && urlParams.get('msg') === 'payment_submitted') {
            Swal.fire({
                title: 'Payment Received!',
                text: 'We are verifying your transaction. Thank you!',
                icon: 'success',
                confirmButtonColor: '#7c3aed'
            });
        }
        if (urlParams.has('msg') && urlParams.get('msg') === 'deleted') {
            Swal.fire({
                title: 'Draft Deleted!',
                text: 'The booking draft has been permanently removed.',
                icon: 'success',
                confirmButtonColor: '#7c3aed'
            });
        }

        function deleteBooking(id) {
            Swal.fire({
                title: 'Delete this booking draft?',
                text: "All logistics and menu choices will be deleted.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Yes, delete'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `auth/delete_booking.php?booking_id=${id}`;
                }
            });
        }
    </script>
</body>
</html>
