<?php
require_once 'includes/admin_auth_check.php';
require_once 'db_config.php';
require_once 'includes/package_helper.php';
require_once 'auth/migrate_venue_ai.php';

$booking_id = intval($_GET['id'] ?? 0);

// 1. Fetch Master Details
$stmt = $conn->prepare("SELECT b.*, u.full_name, u.email, p.payment_method, p.reference_no as p_ref, p.screenshot_path, p.account_name, p.account_number 
                        FROM bookings b 
                        LEFT JOIN users u ON b.user_id = u.user_id 
                        LEFT JOIN payments p ON b.booking_id = p.booking_id
                        WHERE b.booking_id = ?");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) die("Booking not found.");

$package_details = getPackageById($booking_id, $conn);
$ai_analysis = json_decode($booking['ai_venue_analysis'] ?? '', true);

// User role & AI Venue layout approval parameters
$user_role              = strtolower($_SESSION['role'] ?? 'admin');
$is_admin               = ($user_role === 'admin');
$is_staff               = ($user_role === 'staff');
$layout_approval_status = $booking['layout_approval_status'] ?? ($ai_analysis['layout_approval_status'] ?? 'none');
$layout_generated_by    = $booking['layout_generated_by'] ?? ($ai_analysis['layout_generated_by'] ?? '');
$layout_generated_at    = $booking['layout_generated_at'] ?? ($ai_analysis['layout_generated_at'] ?? '');
$layout_approved_by     = $booking['layout_approved_by'] ?? ($ai_analysis['layout_approved_by'] ?? '');
$layout_approved_at     = $booking['layout_approved_at'] ?? ($ai_analysis['layout_approved_at'] ?? '');
$layout_admin_notes     = $booking['layout_admin_notes'] ?? ($ai_analysis['layout_admin_notes'] ?? '');

// 2. Fetch Menu Selection
$items = $conn->query("SELECT bi.*, mi.item_name, mi.image_path FROM booking_items bi JOIN menu_items mi ON bi.item_id = mi.item_id WHERE bi.booking_id = $booking_id");

// 3. Fetch Dietary Restrictions
$dr_stmt = $conn->prepare("SELECT restriction_type, notes FROM dietary_restrictions WHERE booking_id = ? LIMIT 1");
$dr_stmt->bind_param("i", $booking_id);
$dr_stmt->execute();
$dietary_row = $dr_stmt->get_result()->fetch_assoc();
$dietary_restriction = $dietary_row['restriction_type'] ?? '';
$dietary_notes_extra = $dietary_row['notes'] ?? '';

// 4. Fetch ALL payment records for this booking
$pay_stmt = $conn->prepare("SELECT payment_id, payment_method, reference_no, account_name, account_number, screenshot_path, status, created_at FROM payments WHERE booking_id = ? ORDER BY created_at ASC");
$pay_stmt->bind_param("i", $booking_id);
$pay_stmt->execute();
$all_payments_res = $pay_stmt->get_result();
$all_payments = [];
while ($pay_row = $all_payments_res->fetch_assoc()) {
    $all_payments[] = $pay_row;
}

// Compute paid amounts from payment_status
$total_amount   = (float)($booking['total_amount'] ?? 0);
$payment_status = $booking['payment_status'] ?? 'unpaid';
$downpayment_amount = min($total_amount, 10000.00);

$verified_count = 0;
foreach ($all_payments as $p) {
    if ($p['status'] === 'Verified') $verified_count++;
}
$total_payments_submitted = count($all_payments);

if ($payment_status === 'fully_paid') {
    $amount_paid       = $total_amount;
    $remaining_balance = 0.00;
} elseif ($payment_status === 'partially_paid') {
    $amount_paid       = $downpayment_amount;
    $remaining_balance = $total_amount - $amount_paid;
} elseif ($payment_status === 'refunded') {
    $amount_paid       = (float)($booking['refund_amount'] ?? 0);
    $remaining_balance = 0.00;
} else {
    $amount_paid       = 0.00;
    $remaining_balance = $total_amount;
}

$dietary_display = '';
if (!empty($dietary_restriction)) {
    $dietary_display = $dietary_restriction;
} elseif (!empty($booking['special_requests'])) {
    $dietary_display = $booking['special_requests'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking #BK-<?= str_pad($booking_id, 4, '0', STR_PAD_LEFT) ?> Details | Admin</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        function applyAppTheme() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        }
        applyAppTheme();
        window.addEventListener('storage', function(e) {
            if (e.key === 'theme') applyAppTheme();
        });
        document.addEventListener('DOMContentLoaded', applyAppTheme);
    </script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .badge-paid    { background: #d1fae5; color: #065f46; }
        .badge-partial { background: #fef3c7; color: #92400e; }
        .badge-unpaid  { background: #fee2e2; color: #991b1b; }
        .badge-refund  { background: #dbeafe; color: #1e40af; }
        html.dark .badge-paid    { background: rgba(6, 78, 59, 0.85) !important; color: #34d399 !important; }
        html.dark .badge-partial { background: rgba(120, 53, 15, 0.85) !important; color: #fbbf24 !important; }
        html.dark .badge-unpaid  { background: rgba(136, 19, 55, 0.85) !important; color: #f43f5e !important; }
        html.dark .badge-refund  { background: rgba(30, 58, 138, 0.85) !important; color: #60a5fa !important; }
    </style>
</head>
<body class="bg-slate-50 dark:bg-[#0b0f19] transition-colors duration-300 p-3 sm:p-5 md:p-6 min-h-screen">
    <div class="max-w-6xl mx-auto space-y-4">

        <!-- Top Navigation & Actions Bar -->
        <div class="flex items-center justify-between gap-3 pb-1">
            <a href="admin_schedule.php" class="inline-flex items-center gap-1.5 text-xs font-extrabold text-slate-700 dark:text-slate-300 hover:text-purple-700 dark:hover:text-purple-400 transition-colors">
                <i class="bi bi-arrow-left"></i> Back to Schedule
            </a>
            <div class="flex items-center gap-2">
                <a href="receipt.php?booking_id=<?= $booking_id ?>" target="_blank" 
                   class="px-3.5 py-1.5 bg-white dark:bg-[#131b2e] border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-extrabold text-slate-800 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition inline-flex items-center gap-1.5 shadow-sm">
                    <i class="bi bi-receipt text-purple-600 dark:text-purple-400"></i> Generate Receipt
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

            <!-- LEFT COLUMN: Main Booking Details, AI Setup, Payment Summary -->
            <div class="lg:col-span-2 space-y-4">

                <!-- Action Required Banner for Pending Bookings -->
                <?php if ($booking['status'] === 'pending'): ?>
                <div class="p-3.5 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 rounded-2xl flex items-start gap-3 text-xs shadow-xs">
                    <div class="mt-0.5 w-7 h-7 rounded-xl bg-amber-100 dark:bg-amber-900/60 flex items-center justify-center text-amber-800 dark:text-amber-300 shrink-0 font-bold">
                        <i class="bi bi-telephone-outbound-fill"></i>
                    </div>
                    <div>
                        <h4 class="font-extrabold text-amber-900 dark:text-amber-200 uppercase tracking-wider text-[10px] mb-0.5">Verification Required</h4>
                        <p class="text-amber-800 dark:text-amber-300 font-semibold leading-normal">
                            Call client at <strong><?= htmlspecialchars($booking['account_number'] ?? 'N/A') ?></strong> to confirm event details &amp; payment before updating status.
                        </p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Event Details Card -->
                <div class="bg-white dark:bg-[#131b2e] p-5 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 space-y-4">
                    
                    <!-- Header Title & Status -->
                    <div class="flex items-start justify-between gap-3 flex-wrap border-b border-slate-200 dark:border-slate-800/80 pb-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] font-black text-purple-700 dark:text-purple-400 uppercase tracking-wider">Ref: #BK-<?= str_pad($booking_id, 4, '0', STR_PAD_LEFT) ?></span>
                                <span class="text-slate-300 dark:text-slate-700">•</span>
                                <span class="text-xs font-bold text-slate-600 dark:text-slate-400"><?= htmlspecialchars($booking['event_type']) ?></span>
                            </div>
                            <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-slate-100 tracking-tight mt-0.5"><?= htmlspecialchars($booking['event_name']) ?></h2>
                        </div>
                        <?php
                            $status_map = [
                                'pending'     => ['label' => 'Pending',     'class' => 'bg-amber-100 dark:bg-amber-950/80 text-amber-900 dark:text-amber-300 border border-amber-200 dark:border-amber-800'],
                                'confirmed'   => ['label' => 'Confirmed',   'class' => 'bg-emerald-100 dark:bg-emerald-950/80 text-emerald-900 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800'],
                                'on_progress' => ['label' => 'In Progress', 'class' => 'bg-blue-100 dark:bg-blue-950/80 text-blue-900 dark:text-blue-300 border border-blue-200 dark:border-blue-800'],
                                'completed'   => ['label' => 'Completed',   'class' => 'bg-purple-100 dark:bg-purple-950/80 text-purple-900 dark:text-purple-300 border border-purple-200 dark:border-purple-800'],
                                'cancelled'   => ['label' => 'Cancelled',   'class' => 'bg-rose-100 dark:bg-rose-950/80 text-rose-900 dark:text-rose-300 border border-rose-200 dark:border-rose-800'],
                            ];
                            $s = $status_map[$booking['status']] ?? ['label' => ucfirst($booking['status']), 'class' => 'bg-slate-100 text-slate-600'];
                        ?>
                        <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider <?= $s['class'] ?>">
                            <?= $s['label'] ?>
                        </span>
                    </div>

                    <!-- Client Info Strip -->
                    <div class="flex items-center gap-3 p-3 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-800">
                        <div class="w-8 h-8 rounded-full bg-purple-600 text-white flex items-center justify-center font-black text-xs shrink-0 shadow-xs">
                            <?= strtoupper(substr($booking['full_name'] ?? 'U', 0, 1)) ?>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-bold text-slate-900 dark:text-slate-100 truncate"><?= htmlspecialchars($booking['full_name'] ?? 'N/A') ?></p>
                            <p class="text-[11px] text-slate-600 dark:text-slate-400 font-medium truncate"><?= htmlspecialchars($booking['email'] ?? 'N/A') ?></p>
                        </div>
                    </div>

                    <!-- Event Metrics: Date, Time, Guests -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                        <div class="p-3 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-800">
                            <p class="text-[9px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-wider flex items-center gap-1">
                                <i class="bi bi-calendar-event text-purple-600 dark:text-purple-400"></i> Event Date
                            </p>
                            <p class="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100 mt-0.5"><?= date('F j, Y', strtotime($booking['event_date'])) ?></p>
                        </div>
                        <div class="p-3 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-800">
                            <p class="text-[9px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-wider flex items-center gap-1">
                                <i class="bi bi-clock text-amber-500"></i> Event Time
                            </p>
                            <p class="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100 mt-0.5"><?= date('h:i A', strtotime($booking['event_time'])) ?><?= !empty($booking['event_end_time']) ? ' - ' . date('h:i A', strtotime($booking['event_end_time'])) : '' ?></p>
                        </div>
                        <div class="p-3 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-800">
                            <p class="text-[9px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-wider flex items-center gap-1">
                                <i class="bi bi-people text-emerald-600 dark:text-emerald-400"></i> Guest Count
                            </p>
                            <p class="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100 mt-0.5"><?= number_format($booking['pax_count']) ?> Pax</p>
                        </div>
                    </div>

                    <!-- Dedicated Venue & Address Details Card (Full, Clear, Not Cut Short) -->
                    <div class="p-3.5 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-800 flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg bg-purple-100 dark:bg-purple-950/80 text-purple-600 dark:text-purple-400 flex items-center justify-center shrink-0 mt-0.5">
                            <i class="bi bi-geo-alt-fill text-base"></i>
                        </div>
                        <div class="flex-1 min-w-0 space-y-1">
                            <div class="flex items-center justify-between gap-2 flex-wrap">
                                <p class="text-[9px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Event Venue &amp; Complete Address Details</p>
                                <?php if (!empty($booking['table_count'])): ?>
                                    <span class="text-[10px] font-bold text-purple-700 dark:text-purple-300 bg-purple-50 dark:bg-purple-950/60 px-2 py-0.5 rounded-md border border-purple-200 dark:border-purple-800/50 inline-flex items-center gap-1">
                                        <i class="bi bi-grid-3x3-gap-fill text-[9px]"></i> <?= $booking['table_count'] ?> Tables
                                    </span>
                                <?php endif; ?>
                            </div>
                            <p class="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100 break-words leading-relaxed">
                                <?= htmlspecialchars($booking['venue_location'] ?: 'Not Specified') ?>
                            </p>
                            <?php if (!empty($booking['venue_address']) && trim($booking['venue_address']) !== trim($booking['venue_location'])): ?>
                                <p class="text-xs text-slate-600 dark:text-slate-300 font-medium break-words leading-normal flex items-start gap-1.5 pt-0.5">
                                    <i class="bi bi-signpost-2 text-[11px] text-slate-400 mt-0.5 shrink-0"></i>
                                    <span><?= htmlspecialchars($booking['venue_address']) ?></span>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Dietary Restrictions & Special Notes -->
                    <?php if (!empty($dietary_display)): ?>
                    <div class="p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/50 rounded-xl text-xs">
                        <span class="font-extrabold text-amber-900 dark:text-amber-300 uppercase tracking-wider text-[10px] block mb-0.5">
                            <i class="bi bi-exclamation-triangle-fill text-amber-600 mr-1"></i> Special Requests / Dietary Restrictions:
                        </span>
                        <p class="text-slate-800 dark:text-slate-300 font-semibold"><?= nl2br(htmlspecialchars($dietary_display)) ?></p>
                        <?php if (!empty($dietary_notes_extra) && $dietary_notes_extra !== $dietary_display): ?>
                            <p class="text-xs text-amber-800 dark:text-amber-400 mt-1 font-medium border-t border-amber-200 dark:border-amber-800 pt-1"><?= nl2br(htmlspecialchars($dietary_notes_extra)) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Menu Selection Table -->
                    <div class="pt-2 border-t border-slate-200 dark:border-slate-800">
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-600 dark:text-slate-400 block mb-2">Selected Menu Dishes</span>
                        <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-xl">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-100/80 dark:bg-slate-900/60 text-[9px] text-slate-600 dark:text-slate-400 uppercase font-extrabold border-b border-slate-200 dark:border-slate-800">
                                    <tr>
                                        <th class="py-2.5 px-3">Item Description</th>
                                        <th class="py-2.5 px-3 text-center">Qty</th>
                                        <th class="py-2.5 px-3 text-right">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200/80 dark:divide-slate-800/60">
                                    <?php while($item = $items->fetch_assoc()): ?>
                                    <tr class="text-slate-800 dark:text-slate-300">
                                        <td class="py-2.5 px-3">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 shrink-0 aspect-square rounded-xl overflow-hidden border border-slate-200 dark:border-slate-800 bg-slate-100 dark:bg-slate-800 flex items-center justify-center shadow-2xs">
                                                    <?php if (!empty($item['image_path'])): ?>
                                                        <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="<?= htmlspecialchars($item['item_name']) ?>" class="w-full h-full aspect-square object-cover block">
                                                    <?php else: ?>
                                                        <span class="text-purple-600 dark:text-purple-400 font-black text-xs uppercase"><?= substr($item['item_name'], 0, 1) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <span class="font-bold text-slate-900 dark:text-slate-100 block truncate"><?= htmlspecialchars($item['item_name']) ?></span>
                                                    <?php if (!empty($item['dietary_notes'])): ?>
                                                        <span class="inline-flex items-center gap-1 text-[9px] font-bold text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/60 px-1.5 py-0.5 rounded border border-amber-200 dark:border-amber-800/40 mt-0.5">
                                                            <i class="bi bi-tag-fill text-[8px]"></i> <?= htmlspecialchars($item['dietary_notes']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-2.5 px-3 text-center text-slate-600 dark:text-slate-400 font-bold"><?= $item['quantity'] ?> Qty</td>
                                        <td class="py-2.5 px-3 text-right font-bold text-slate-900 dark:text-slate-100">₱<?= number_format($item['subtotal'], 2) ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-2.5 flex justify-between items-center px-1">
                            <span class="text-[10px] font-black text-slate-600 dark:text-slate-400 uppercase tracking-wider">Total Contract Amount</span>
                            <span class="text-lg font-black text-purple-700 dark:text-purple-400">₱<?= number_format($total_amount, 2) ?></span>
                        </div>
                    </div>
                </div>

                <!-- AI VENUE ANALYSIS & CONCEPTUAL SETUP CARD -->
                <div class="bg-white dark:bg-gradient-to-br dark:from-purple-950 dark:via-indigo-950 dark:to-slate-900 text-slate-900 dark:text-white p-5 sm:p-6 rounded-2xl shadow-sm border border-purple-200 dark:border-purple-800/50 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-purple-100 dark:border-white/10">
                        <div>
                            <div class="flex items-center gap-2">
                                <i class="bi bi-stars text-purple-600 dark:text-purple-400 text-lg"></i>
                                <h3 class="text-base font-black tracking-tight text-slate-900 dark:text-white">AI Venue &amp; Conceptual Catering Setup</h3>
                            </div>
                            <p class="text-[11px] text-slate-600 dark:text-purple-200 font-medium">Generate realistic catering layout options based on venue structures.</p>
                        </div>
                        <button id="btn-analyze-venue" onclick="analyzeVenueAI()" 
                                class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-extrabold uppercase tracking-wider shadow-md shadow-purple-600/20 transition flex items-center justify-center gap-1.5 shrink-0 cursor-pointer">
                            <i class="bi bi-cpu"></i> Analyze Venue
                        </button>
                    </div>

                    <!-- Layout Approval Status Banner -->
                    <div id="layout-approval-banner" class="rounded-2xl p-3.5 sm:p-4 border transition-all <?= ($layout_approval_status === 'pending_approval') ? 'bg-amber-500/10 dark:bg-amber-950/40 border-amber-300 dark:border-amber-700/80' : (($layout_approval_status === 'approved') ? 'bg-emerald-500/10 dark:bg-emerald-950/40 border-emerald-300 dark:border-emerald-700/80' : (($layout_approval_status === 'rejected') ? 'bg-rose-500/10 dark:bg-rose-950/40 border-rose-300 dark:border-rose-700/80' : 'hidden')) ?>">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                            <div class="flex items-start sm:items-center gap-2.5">
                                <span id="approval-indicator-dot" class="w-2.5 h-2.5 rounded-full mt-1 sm:mt-0 shrink-0 <?= ($layout_approval_status === 'pending_approval') ? 'bg-amber-500 animate-ping' : (($layout_approval_status === 'approved') ? 'bg-emerald-500' : 'bg-rose-500') ?>"></span>
                                <div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span id="approval-status-title" class="text-xs font-black uppercase tracking-wider <?= ($layout_approval_status === 'pending_approval') ? 'text-amber-800 dark:text-amber-300' : (($layout_approval_status === 'approved') ? 'text-emerald-800 dark:text-emerald-300' : 'text-rose-800 dark:text-rose-300') ?>">
                                            <?php if ($layout_approval_status === 'pending_approval'): ?>
                                                Pending Admin Approval
                                            <?php elseif ($layout_approval_status === 'approved'): ?>
                                                Layout Approved by Administrator
                                            <?php elseif ($layout_approval_status === 'rejected'): ?>
                                                Revision Requested by Admin
                                            <?php else: ?>
                                                Layout Status
                                            <?php endif; ?>
                                        </span>
                                        <span id="approval-badge-role" class="text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-md <?= ($layout_approval_status === 'pending_approval') ? 'bg-amber-200/80 dark:bg-amber-900/60 text-amber-900 dark:text-amber-200' : (($layout_approval_status === 'approved') ? 'bg-emerald-200/80 dark:bg-emerald-900/60 text-emerald-900 dark:text-emerald-200' : 'bg-rose-200/80 dark:bg-rose-900/60 text-rose-900 dark:text-rose-200') ?>">
                                            <?= ($layout_approval_status === 'approved') ? 'Admin Approved' : (($layout_approval_status === 'pending_approval') ? 'Awaiting Review' : 'Needs Changes') ?>
                                        </span>
                                    </div>
                                    <p id="approval-status-subtitle" class="text-[11px] font-medium mt-0.5 <?= ($layout_approval_status === 'pending_approval') ? 'text-amber-700 dark:text-amber-400' : (($layout_approval_status === 'approved') ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400') ?>">
                                        <?php if ($layout_approval_status === 'pending_approval'): ?>
                                            Generated by <strong><?= htmlspecialchars($layout_generated_by ?: 'Staff') ?></strong><?= !empty($layout_generated_at) ? ' on ' . date('M d, Y h:i A', strtotime($layout_generated_at)) : '' ?>. Staff-generated layout concepts require administrator approval before finalization.
                                        <?php elseif ($layout_approval_status === 'approved'): ?>
                                            Approved by <strong><?= htmlspecialchars($layout_approved_by ?: 'Administrator') ?></strong><?= !empty($layout_approved_at) ? ' on ' . date('M d, Y h:i A', strtotime($layout_approved_at)) : '' ?>. Layout is verified and client-ready.
                                        <?php elseif ($layout_approval_status === 'rejected'): ?>
                                            Note from Admin: "<?= htmlspecialchars($layout_admin_notes ?: 'Please adjust theme or tables.') ?>". Re-generation required.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Approval Action Buttons (Visible to Admin when Pending) -->
                            <div id="admin-approval-buttons" class="<?= $is_admin && $layout_approval_status === 'pending_approval' ? '' : 'hidden' ?> flex items-center gap-2 w-full sm:w-auto justify-end shrink-0">
                                <button type="button" onclick="approveVenueLayout(<?= $booking_id ?>)" id="btn-approve-layout" class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-black uppercase tracking-wider shadow-sm transition flex items-center gap-1.5 cursor-pointer">
                                    <i class="bi bi-check-circle-fill"></i> Approve Layout
                                </button>
                                <button type="button" onclick="rejectVenueLayout(<?= $booking_id ?>)" id="btn-reject-layout" class="px-3.5 py-1.5 bg-rose-100 hover:bg-rose-200 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 rounded-xl text-xs font-bold transition flex items-center gap-1 cursor-pointer">
                                    <i class="bi bi-x-circle"></i> Request Revision
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Upload Venue Image Strip -->
                    <div class="p-3 bg-purple-50/70 dark:bg-white/5 rounded-xl border border-purple-100 dark:border-white/10 text-xs">
                        <form id="upload-venue-form" onsubmit="uploadVenuePhoto(event)" class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-slate-800 dark:text-white font-bold">Venue Reference Image:</span>
                            <div class="flex items-center gap-2 flex-wrap">
                                <input type="file" id="venue-file-input" name="venue_image" accept="image/*" required class="text-[11px] text-slate-700 dark:text-gray-200 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-[10px] file:font-bold file:bg-purple-600 file:text-white hover:file:bg-purple-700 cursor-pointer">
                                <button type="submit" class="px-3 py-1 bg-slate-800 dark:bg-white/20 hover:bg-slate-900 dark:hover:bg-white/30 text-white text-xs font-bold rounded-lg transition cursor-pointer">Upload</button>
                            </div>
                        </form>
                    </div>

                    <!-- Status Notification Banner -->
                    <div id="ai-status-banner" class="hidden p-3 rounded-xl border text-xs font-bold flex items-center gap-2"></div>

                    <!-- AI Vision Stats Grid -->
                    <div>
                        <span class="text-[9px] font-black uppercase tracking-wider text-purple-700 dark:text-purple-300 block mb-2">Step 1: AI Vision Analysis</span>
                        <div id="vision-analysis-grid" class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                            <div class="bg-slate-50 dark:bg-white/5 p-2.5 rounded-xl border border-slate-200 dark:border-white/10">
                                <p class="text-[8px] font-extrabold text-slate-500 dark:text-purple-200 uppercase">Venue Type</p>
                                <p id="v-type" class="text-xs font-bold text-slate-900 dark:text-white truncate"><?= htmlspecialchars($ai_analysis['venue_type'] ?? 'Pending') ?></p>
                            </div>
                            <div class="bg-slate-50 dark:bg-white/5 p-2.5 rounded-xl border border-slate-200 dark:border-white/10">
                                <p class="text-[8px] font-extrabold text-slate-500 dark:text-purple-200 uppercase">Capacity</p>
                                <p id="v-cap" class="text-xs font-bold text-slate-900 dark:text-white truncate"><?= htmlspecialchars($ai_analysis['estimated_capacity'] ?? 'Pending') ?></p>
                            </div>
                            <div class="bg-slate-50 dark:bg-white/5 p-2.5 rounded-xl border border-slate-200 dark:border-white/10">
                                <p class="text-[8px] font-extrabold text-purple-700 dark:text-purple-300 uppercase">Recommended Pkg</p>
                                <p id="v-pkg" class="text-xs font-bold text-purple-700 dark:text-purple-300 truncate"><?= htmlspecialchars($package_details['package_name']) ?></p>
                            </div>
                            <div class="bg-slate-50 dark:bg-white/5 p-2.5 rounded-xl border border-slate-200 dark:border-white/10">
                                <p class="text-[8px] font-extrabold text-slate-500 dark:text-purple-200 uppercase">Suggested Menu</p>
                                <p id="v-menu" class="text-xs font-bold text-slate-900 dark:text-white truncate" title="<?= htmlspecialchars($package_details['menu']) ?>"><?= htmlspecialchars($package_details['menu']) ?></p>
                            </div>
                            <div class="bg-slate-50 dark:bg-white/5 p-2.5 rounded-xl border border-slate-200 dark:border-white/10">
                                <p class="text-[8px] font-extrabold text-slate-500 dark:text-purple-200 uppercase">Table Layout</p>
                                <p id="v-layout" class="text-xs font-bold text-slate-900 dark:text-white truncate" title="<?= htmlspecialchars($package_details['table_type']) ?>"><?= htmlspecialchars($package_details['table_type']) ?></p>
                            </div>
                        </div>

                        <!-- Detected Architectural Anchors -->
                        <div id="arch-anchors-box" class="mt-2.5 p-2.5 bg-slate-50 dark:bg-white/5 rounded-xl border border-slate-200 dark:border-white/10 text-[11px] grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <div>
                                <span class="text-[9px] font-extrabold uppercase text-purple-700 dark:text-purple-300 block"><i class="bi bi-bricks mr-1"></i> Structure / Walls</span>
                                <span id="v-walls" class="text-slate-700 dark:text-slate-300 font-medium truncate block" title="<?= htmlspecialchars($ai_analysis['wall_characteristics'] ?? 'Analyzed from photo') ?>"><?= htmlspecialchars($ai_analysis['wall_characteristics'] ?? 'Preserved from photo') ?></span>
                            </div>
                            <div>
                                <span class="text-[9px] font-extrabold uppercase text-purple-700 dark:text-purple-300 block"><i class="bi bi-grid-3x3 mr-1"></i> Flooring / Ground</span>
                                <span id="v-floor" class="text-slate-700 dark:text-slate-300 font-medium truncate block" title="<?= htmlspecialchars($ai_analysis['floor_type'] ?? 'Analyzed from photo') ?>"><?= htmlspecialchars($ai_analysis['floor_type'] ?? 'Preserved from photo') ?></span>
                            </div>
                            <div>
                                <span class="text-[9px] font-extrabold uppercase text-purple-700 dark:text-purple-300 block"><i class="bi bi-sun mr-1"></i> Lighting & Ambience</span>
                                <span id="v-light" class="text-slate-700 dark:text-slate-300 font-medium truncate block" title="<?= htmlspecialchars($ai_analysis['lighting'] ?? 'Natural & ambient') ?>"><?= htmlspecialchars($ai_analysis['lighting'] ?? 'Natural & ambient') ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 2: TWO-STEP AI CATERING STAGING PIPELINE -->
                    <div class="p-3.5 bg-purple-50/70 dark:bg-white/5 rounded-2xl border border-purple-200 dark:border-white/10 space-y-3">
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <label class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
                                <i class="bi bi-sliders text-purple-600 dark:text-purple-400"></i> Venue &amp; Catering Setup Parameters:
                            </label>
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="text-[10px] text-slate-500 dark:text-purple-300 font-bold mr-0.5">Presets:</span>
                                <button type="button" onclick="setPresetPrompt('wedding')" id="preset-btn-wedding" class="text-[10px] font-bold px-2 py-0.5 rounded-lg bg-white dark:bg-white/10 hover:bg-purple-100 dark:hover:bg-purple-900/50 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800 transition cursor-pointer">✨ Wedding</button>
                                <button type="button" onclick="setPresetPrompt('birthday')" id="preset-btn-birthday" class="text-[10px] font-bold px-2 py-0.5 rounded-lg bg-white dark:bg-white/10 hover:bg-purple-100 dark:hover:bg-purple-900/50 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800 transition cursor-pointer">🎉 Birthday</button>
                                <button type="button" onclick="setPresetPrompt('corporate')" id="preset-btn-corporate" class="text-[10px] font-bold px-2 py-0.5 rounded-lg bg-white dark:bg-white/10 hover:bg-purple-100 dark:hover:bg-purple-900/50 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800 transition cursor-pointer">🏢 Corporate</button>
                            </div>
                        </div>

                        <!-- 4-Field Input Grid: Venue Type, Theme, Guest Count, Color Scheme -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2 text-xs">
                            <div>
                                <label class="text-[9px] font-extrabold uppercase text-slate-600 dark:text-purple-300 block mb-1">Venue Type</label>
                                <input type="text" id="ai-venue-type" value="<?= htmlspecialchars(!empty($ai_analysis['venue_type']) ? $ai_analysis['venue_type'] : ($booking['venue_location'] ?: 'Banquet Hall')) ?>" 
                                       class="w-full text-xs p-2 rounded-lg border border-purple-200 dark:border-purple-800/80 bg-white dark:bg-slate-900 text-slate-900 dark:text-white font-medium focus:ring-2 focus:ring-purple-500 focus:outline-none" placeholder="e.g. Grand Ballroom, Rooftop Lawn">
                            </div>
                            <div>
                                <label class="text-[9px] font-extrabold uppercase text-slate-600 dark:text-purple-300 block mb-1">Theme &amp; Style</label>
                                <input type="text" id="ai-theme" value="<?= htmlspecialchars(!empty($package_details['theme']) ? $package_details['theme'] : 'Modern Elegant') ?>" 
                                       class="w-full text-xs p-2 rounded-lg border border-purple-200 dark:border-purple-800/80 bg-white dark:bg-slate-900 text-slate-900 dark:text-white font-medium focus:ring-2 focus:ring-purple-500 focus:outline-none" placeholder="e.g. Modern Elegant, Rustic Gold">
                            </div>
                            <div>
                                <label class="text-[9px] font-extrabold uppercase text-slate-600 dark:text-purple-300 block mb-1">Guest Count (Pax)</label>
                                <input type="number" id="ai-pax" value="<?= intval($booking['pax_count'] ?: 100) ?>" min="10" max="1000"
                                       class="w-full text-xs p-2 rounded-lg border border-purple-200 dark:border-purple-800/80 bg-white dark:bg-slate-900 text-slate-900 dark:text-white font-medium focus:ring-2 focus:ring-purple-500 focus:outline-none">
                            </div>
                            <div>
                                <label class="text-[9px] font-extrabold uppercase text-slate-600 dark:text-purple-300 block mb-1">Color Scheme / Palette</label>
                                <input type="text" id="ai-color-scheme" value="<?= htmlspecialchars(!empty($ai_analysis['color_scheme']) ? $ai_analysis['color_scheme'] : 'Champagne Gold & Warm White') ?>" 
                                       class="w-full text-xs p-2 rounded-lg border border-purple-200 dark:border-purple-800/80 bg-white dark:bg-slate-900 text-slate-900 dark:text-white font-medium focus:ring-2 focus:ring-purple-500 focus:outline-none" placeholder="e.g. Champagne Gold &amp; Ivory">
                            </div>
                        </div>

                        <!-- Prompt Details Textarea -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label for="admin-prompt-input" class="text-[10px] font-bold text-slate-700 dark:text-purple-200 uppercase tracking-wider flex items-center gap-1">
                                    <i class="bi bi-chat-square-quote text-purple-600 dark:text-purple-400"></i> Detailed Staging Prompt:
                                </label>
                                <button type="button" onclick="enrichStagingPrompt()" id="btn-enrich-prompt" class="text-[10px] font-extrabold px-2.5 py-1 rounded-lg bg-purple-600 hover:bg-purple-700 text-white shadow-xs transition cursor-pointer inline-flex items-center gap-1" title="Calls fast text model to synthesize an architectural staging prompt">
                                    <i class="bi bi-stars"></i> Enrich Prompt (Google AI)
                                </button>
                            </div>
                            <textarea id="admin-prompt-input" rows="3" 
                                      class="w-full text-xs p-3 rounded-xl border border-purple-200 dark:border-purple-800/80 bg-white dark:bg-slate-900/90 text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-purple-500 focus:outline-none leading-relaxed transition resize-y font-medium shadow-2xs" 
                                      placeholder="Detailed photographic staging prompt (click 'Enrich Prompt' or type custom directives)..."><?= htmlspecialchars(!empty($ai_analysis['admin_prompt']) ? $ai_analysis['admin_prompt'] : (!empty($ai_analysis['enriched_staging_prompt']) ? $ai_analysis['enriched_staging_prompt'] : ($ai_analysis['accurate_staging_prompt'] ?? ''))) ?></textarea>
                        </div>

                        <div class="flex items-center justify-between flex-wrap gap-2 pt-0.5">
                            <p class="text-[10px] text-slate-500 dark:text-slate-400 font-medium">
                                <i class="bi bi-info-circle mr-1"></i> Two-Step Pipeline: Google AI enriches venue parameters &rarr; Pollinations.ai (Flux) renders 16:9 visualization.
                            </p>
                            <button type="button" id="btn-prompt-ai" onclick="generateConceptualSetup()" 
                                    class="px-4 py-2 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white rounded-xl text-xs font-extrabold uppercase tracking-wider shadow-md shadow-purple-600/25 transition inline-flex items-center gap-1.5 cursor-pointer">
                                <i class="bi bi-camera-reels"></i> <?= $is_staff ? 'Generate Venue Layout (Pending Admin Approval)' : 'Generate 16:9 Image (Pollinations Flux AI)' ?>
                            </button>
                        </div>
                    </div>

                    <!-- Comparison Images: Original vs AI Visualization -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <!-- Original Venue -->
                        <div class="bg-slate-50 dark:bg-white/5 p-3 rounded-xl border border-slate-200 dark:border-white/10">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[10px] font-black uppercase text-slate-600 dark:text-purple-200"><i class="bi bi-camera mr-1"></i> Original Venue</span>
                                <span class="text-[8px] font-bold px-2 py-0.5 rounded-full bg-slate-200 dark:bg-white/20 text-slate-800 dark:text-white">Base Photo</span>
                            </div>
                            <div class="relative rounded-lg overflow-hidden bg-slate-200 dark:bg-black/40 aspect-video flex items-center justify-center border border-slate-300 dark:border-white/10">
                                <?php if (!empty($booking['venue_image'])): ?>
                                    <img id="img-original-venue" src="<?= htmlspecialchars($booking['venue_image']) ?>" class="w-full h-full object-cover rounded-lg">
                                <?php else: ?>
                                    <div id="img-original-venue-placeholder" class="text-center p-4 text-slate-500 dark:text-gray-400">
                                        <i class="bi bi-image text-2xl block mb-1"></i>
                                        <p class="text-[10px] font-bold">No venue photo</p>
                                    </div>
                                    <img id="img-original-venue" src="" class="hidden w-full h-full object-cover rounded-lg">
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- AI Visualization -->
                        <div class="bg-slate-50 dark:bg-white/5 p-3 rounded-xl border border-slate-200 dark:border-white/10">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[10px] font-black uppercase text-purple-700 dark:text-purple-300"><i class="bi bi-magic mr-1"></i> AI Setup Concept</span>
                                <span id="ai-concept-badge" class="text-[8px] font-bold px-2 py-0.5 rounded-full bg-purple-100 dark:bg-purple-500/30 text-purple-700 dark:text-purple-200"><?= !empty($ai_analysis['generation_engine']) ? htmlspecialchars($ai_analysis['generation_engine']) : 'Google Gemini AI Pro' ?></span>
                            </div>
                            <div class="relative rounded-lg overflow-hidden bg-slate-200 dark:bg-black/40 aspect-video flex items-center justify-center border border-purple-200 dark:border-purple-500/30">
                                <?php if (!empty($booking['ai_conceptual_image'])): ?>
                                    <img id="img-ai-concept" src="<?= htmlspecialchars($booking['ai_conceptual_image']) ?>" class="w-full h-full object-cover rounded-lg cursor-pointer hover:opacity-95 hover:scale-[1.01] transition-all duration-200" onclick="window.open(this.src, '_blank')" title="Click to inspect full resolution concept">
                                <?php else: ?>
                                    <div id="img-ai-concept-placeholder" class="text-center p-4 text-purple-600 dark:text-purple-300">
                                        <i class="bi bi-stars text-2xl text-purple-600 dark:text-purple-300 block mb-1"></i>
                                        <p class="text-[10px] font-bold">Input prompt &amp; click "Generate Setup" above</p>
                                    </div>
                                    <img id="img-ai-concept" src="" class="hidden w-full h-full object-cover rounded-lg">
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- AI CATERING & PACKAGE LAYOUT SUMMARY CARD -->
                    <?php 
                        $has_summary = !empty($ai_analysis['ai_summary']); 
                        $sum = $has_summary ? $ai_analysis['ai_summary'] : null;
                    ?>
                    <div id="ai-summary-card" class="<?= $has_summary ? '' : 'hidden' ?> p-4 bg-slate-50 dark:bg-white/5 rounded-2xl border border-purple-200 dark:border-purple-800/50 space-y-3">
                        <div class="flex items-center justify-between border-b border-purple-100 dark:border-white/10 pb-2.5 flex-wrap gap-2">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-lg bg-purple-600 text-white flex items-center justify-center text-xs shadow-xs">
                                    <i class="bi bi-file-earmark-text-fill"></i>
                                </div>
                                <div>
                                    <h4 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white">AI Package &amp; Staging Summary</h4>
                                    <p class="text-[10px] text-slate-500 dark:text-purple-300 font-medium">Customer package, pax count, table layout &amp; menu dishes</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" onclick="copyAISummary()" class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-white dark:bg-white/10 hover:bg-slate-100 dark:hover:bg-white/20 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-white/10 transition inline-flex items-center gap-1 cursor-pointer">
                                    <i class="bi bi-clipboard"></i> Copy Summary
                                </button>
                                <button type="button" onclick="sendAIRecommendation()" class="px-2.5 py-1 text-[11px] font-extrabold rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white transition inline-flex items-center gap-1 shadow-xs cursor-pointer">
                                    <i class="bi bi-send-fill"></i> Send to Client
                                </button>
                            </div>
                        </div>

                        <!-- AI Summary Content Container -->
                        <div id="ai-summary-content" class="text-xs text-slate-700 dark:text-slate-200 leading-relaxed space-y-2.5">
                            <?php if ($has_summary && is_array($sum)): ?>
                                <!-- Executive Overview -->
                                <?php if (!empty($sum['executive_narrative'])): ?>
                                    <div class="p-3 bg-purple-50/70 dark:bg-purple-950/40 rounded-xl border border-purple-100 dark:border-purple-800/50 space-y-1">
                                        <p class="text-[10px] font-extrabold uppercase text-purple-700 dark:text-purple-300">Executive Staging Overview</p>
                                        <p class="text-xs text-slate-800 dark:text-slate-200 whitespace-pre-line font-medium leading-relaxed"><?= htmlspecialchars($sum['executive_narrative']) ?></p>
                                    </div>
                                <?php endif; ?>

                                <!-- Package & Tables Grid -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                    <div class="p-3 bg-white dark:bg-white/5 rounded-xl border border-slate-200 dark:border-white/10">
                                        <p class="text-[9px] font-extrabold text-slate-500 dark:text-purple-300 uppercase">Package Availed</p>
                                        <p class="text-xs font-bold text-slate-900 dark:text-white mt-0.5"><?= htmlspecialchars($sum['package_name'] ?? $package_details['package_name']) ?></p>
                                        <p class="text-[11px] text-purple-600 dark:text-purple-400 font-bold mt-1">Contract: <?= htmlspecialchars($sum['formatted_amount'] ?? ('₱' . number_format($total_amount, 2))) ?> • <?= $booking['pax_count'] ?> Guests</p>
                                    </div>
                                    <div class="p-3 bg-white dark:bg-white/5 rounded-xl border border-slate-200 dark:border-white/10">
                                        <p class="text-[9px] font-extrabold text-slate-500 dark:text-purple-300 uppercase">Suggested Table Setup</p>
                                        <p class="text-xs font-bold text-slate-900 dark:text-white mt-0.5"><?= htmlspecialchars($sum['suggested_tables']['guest_tables'] ?? (ceil($booking['pax_count'] / 10) . ' Round Tables')) ?></p>
                                        <p class="text-[11px] text-slate-600 dark:text-slate-400 font-medium mt-1"><?= htmlspecialchars($sum['suggested_tables']['head_table'] ?? '1 VIP Head Table') ?> • <?= htmlspecialchars($sum['suggested_tables']['buffet_station'] ?? 'Twin Buffet Stations') ?></p>
                                    </div>
                                </div>

                                <!-- Curated Menu Dishes -->
                                <?php if (!empty($sum['menu_by_category'])): ?>
                                    <div class="p-3 bg-white dark:bg-white/5 rounded-xl border border-slate-200 dark:border-white/10">
                                        <p class="text-[9px] font-extrabold text-slate-500 dark:text-purple-300 uppercase mb-1.5">Selected Menu Dishes Included:</p>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                            <?php foreach ($sum['menu_by_category'] as $cat => $dishes): ?>
                                                <div>
                                                    <span class="text-[10px] font-extrabold text-purple-700 dark:text-purple-300 uppercase block"><?= htmlspecialchars($cat) ?>:</span>
                                                    <span class="text-[11px] text-slate-700 dark:text-slate-300 font-medium"><?= htmlspecialchars(implode(', ', (array)$dishes)) ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- AI Action Toolbar -->
                    <div class="flex flex-wrap items-center justify-end gap-2 pt-2 border-t border-purple-100 dark:border-white/10">
                        <button id="btn-regenerate-concept" onclick="generateConceptualSetup()" class="px-3 py-1.5 bg-slate-100 dark:bg-white/15 hover:bg-slate-200 dark:hover:bg-white/25 text-slate-800 dark:text-white rounded-lg text-xs font-bold transition inline-flex items-center gap-1 cursor-pointer">
                            <i class="bi bi-arrow-clockwise"></i> Re-generate
                        </button>
                        <a id="btn-download-concept" 
                           href="<?= !empty($booking['ai_conceptual_image']) ? htmlspecialchars($booking['ai_conceptual_image']) : '#' ?>" 
                           download="Catering_Conceptual_Setup_BK<?= $booking_id ?>.jpg"
                           class="<?= empty($booking['ai_conceptual_image']) ? 'pointer-events-none opacity-40' : '' ?> px-3 py-1.5 bg-slate-100 dark:bg-white/15 hover:bg-slate-200 dark:hover:bg-white/25 text-slate-800 dark:text-white rounded-lg text-xs font-bold transition inline-flex items-center gap-1">
                            <i class="bi bi-download"></i> Download
                        </a>
                        <button onclick="sendAIRecommendation()" class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-extrabold uppercase tracking-wider transition inline-flex items-center gap-1 shadow-md shadow-emerald-600/20 cursor-pointer">
                            <i class="bi bi-send-check-fill"></i> Send to Client
                        </button>
                    </div>
                </div>

                <!-- FINANCIAL SUMMARY & PAYMENT PROGRESS CARD -->
                <div class="bg-white dark:bg-gradient-to-br dark:from-purple-900 dark:via-indigo-900 dark:to-purple-950 text-slate-900 dark:text-white p-5 rounded-2xl shadow-sm border border-purple-200 dark:border-purple-700/50 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[9px] font-black uppercase tracking-wider text-purple-700 dark:text-purple-200">Payment Breakdown</p>
                            <h3 class="text-base font-black text-slate-900 dark:text-white">Financial Tracking</h3>
                        </div>
                        <?php
                            $ps_map = [
                                'fully_paid'     => ['label' => 'Fully Paid',     'class' => 'badge-paid'],
                                'partially_paid' => ['label' => 'Partial / DP',   'class' => 'badge-partial'],
                                'unpaid'         => ['label' => 'Unpaid',          'class' => 'badge-unpaid'],
                                'refunded'       => ['label' => 'Refunded',        'class' => 'badge-refund'],
                            ];
                            $ps = $ps_map[$payment_status] ?? ['label' => ucfirst($payment_status), 'class' => 'badge-unpaid'];
                        ?>
                        <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider <?= $ps['class'] ?>">
                            <?= $ps['label'] ?>
                        </span>
                    </div>

                    <!-- Progress Bar -->
                    <?php $progress_pct = ($total_amount > 0) ? min(100, round(($amount_paid / $total_amount) * 100)) : 0; ?>
                    <div>
                        <div class="flex justify-between text-[9px] font-black uppercase tracking-wider text-slate-600 dark:text-purple-200 mb-1">
                            <span>Payment Progress</span>
                            <span><?= $progress_pct ?>% Paid</span>
                        </div>
                        <div class="w-full h-2.5 bg-slate-200 dark:bg-white/20 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-700 bg-gradient-to-r from-purple-600 to-indigo-600 dark:from-emerald-400 dark:to-purple-300" style="width: <?= $progress_pct ?>%;"></div>
                        </div>
                    </div>

                    <!-- Financial Stats Grid -->
                    <div class="grid grid-cols-3 gap-2.5">
                        <div class="bg-slate-50 dark:bg-white/10 rounded-xl p-3 border border-slate-200 dark:border-white/20">
                            <p class="text-[8px] font-extrabold uppercase tracking-wider text-slate-600 dark:text-purple-200 mb-1">Contract Total</p>
                            <p class="text-sm font-black text-slate-900 dark:text-white">₱<?= number_format($total_amount, 2) ?></p>
                        </div>
                        <div class="bg-emerald-50 dark:bg-emerald-500/30 rounded-xl p-3 border border-emerald-200 dark:border-emerald-400/40">
                            <p class="text-[8px] font-extrabold uppercase tracking-wider text-emerald-800 dark:text-emerald-200 mb-1">Amount Paid</p>
                            <p class="text-sm font-black text-emerald-700 dark:text-emerald-300">₱<?= number_format($amount_paid, 2) ?></p>
                        </div>
                        <div class="<?= $remaining_balance > 0 ? 'bg-rose-50 dark:bg-rose-500/30 border-rose-200 dark:border-rose-400/40' : 'bg-slate-50 dark:bg-white/10 border-slate-200 dark:border-white/20' ?> rounded-xl p-3 border">
                            <p class="text-[8px] font-extrabold uppercase tracking-wider <?= $remaining_balance > 0 ? 'text-rose-800 dark:text-rose-200' : 'text-slate-600 dark:text-purple-200' ?> mb-1">Balance Due</p>
                            <p class="text-sm font-black <?= $remaining_balance > 0 ? 'text-rose-700 dark:text-rose-300' : 'text-slate-900 dark:text-white opacity-80' ?>">₱<?= number_format($remaining_balance, 2) ?></p>
                        </div>
                    </div>
                </div>

            </div>

            <!-- RIGHT COLUMN: Proof, Payment History & Status Actions -->
            <div class="space-y-4">

                <!-- Payment Proof Card -->
                <div class="bg-white dark:bg-[#131b2e] p-5 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 space-y-3">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-600 dark:text-slate-400 block">Payment Proof Attachment</span>
                    <?php if($booking['screenshot_path']): ?>
                        <img src="uploads/<?= $booking['screenshot_path'] ?>" 
                             class="w-full h-auto rounded-xl shadow-xs border border-slate-200 dark:border-slate-700 cursor-pointer hover:opacity-90 transition max-h-48 object-cover"
                             onclick="window.open('uploads/<?= $booking['screenshot_path'] ?>', '_blank')"
                             title="Click to view full size">
                        <div class="space-y-2 text-xs">
                            <div class="flex items-center justify-between p-2 bg-slate-50 dark:bg-slate-900/60 rounded-lg border border-slate-200 dark:border-slate-800">
                                <span class="text-[10px] font-extrabold text-slate-600 dark:text-slate-400 uppercase">Method:</span>
                                <span class="font-bold text-slate-900 dark:text-slate-100"><?= htmlspecialchars($booking['payment_method'] ?? 'N/A') ?></span>
                            </div>
                            <div class="flex items-center justify-between p-2 bg-slate-50 dark:bg-slate-900/60 rounded-lg border border-slate-200 dark:border-slate-800">
                                <span class="text-[10px] font-extrabold text-slate-600 dark:text-slate-400 uppercase">Ref No:</span>
                                <span class="font-bold tracking-wider text-purple-700 dark:text-purple-300"><?= htmlspecialchars($booking['p_ref'] ?? 'N/A') ?></span>
                            </div>
                            <div class="flex items-center justify-between p-2 bg-slate-50 dark:bg-slate-900/60 rounded-lg border border-slate-200 dark:border-slate-800">
                                <span class="text-[10px] font-extrabold text-slate-600 dark:text-slate-400 uppercase">Sender Name:</span>
                                <span class="font-bold text-slate-900 dark:text-slate-100"><?= htmlspecialchars($booking['account_name'] ?? 'N/A') ?></span>
                            </div>
                            <div class="flex items-center justify-between p-2 bg-slate-50 dark:bg-slate-900/60 rounded-lg border border-slate-200 dark:border-slate-800">
                                <span class="text-[10px] font-extrabold text-slate-600 dark:text-slate-400 uppercase">Mobile:</span>
                                <span class="font-bold text-slate-900 dark:text-slate-100"><?= htmlspecialchars($booking['account_number'] ?? 'N/A') ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="py-8 text-center text-slate-500 border border-dashed border-slate-200 dark:border-slate-800 rounded-xl">
                            <i class="bi bi-slash-circle text-2xl block mb-1"></i>
                            <p class="text-[10px] font-black uppercase">No proof attached</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Payment History List -->
                <?php if (count($all_payments) > 0): ?>
                <div class="bg-white dark:bg-[#131b2e] p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-600 dark:text-slate-400 block">Payment Submissions</span>
                    <div class="space-y-2">
                        <?php foreach ($all_payments as $idx => $pay): ?>
                        <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 text-xs">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-lg bg-purple-100 dark:bg-purple-950/80 text-purple-700 dark:text-purple-400 flex items-center justify-center font-black text-[10px]">
                                    <?= $idx + 1 ?>
                                </div>
                                <div>
                                    <p class="font-bold text-slate-900 dark:text-slate-200"><?= htmlspecialchars($pay['payment_method'] ?? 'N/A') ?></p>
                                    <p class="text-[9px] text-slate-500 dark:text-slate-400 font-medium"><?= date('M d, Y', strtotime($pay['created_at'])) ?></p>
                                </div>
                            </div>
                            <?php
                                $ps_badge = [
                                    'Verified' => 'bg-emerald-100 dark:bg-emerald-950/80 text-emerald-800 dark:text-emerald-300',
                                    'Pending'  => 'bg-amber-100 dark:bg-amber-950/80 text-amber-800 dark:text-amber-300',
                                    'Rejected' => 'bg-rose-100 dark:bg-rose-950/80 text-rose-800 dark:text-rose-300',
                                    'Refunded' => 'bg-blue-100 dark:bg-blue-950/80 text-blue-800 dark:text-blue-300',
                                ][$pay['status']] ?? 'bg-slate-100 text-slate-600';
                            ?>
                            <span class="px-2 py-0.5 rounded-full text-[8px] font-black uppercase tracking-wider <?= $ps_badge ?>">
                                <?= $pay['status'] ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>


            </div>
        </div>
    </div>

    <!-- JavaScript logic for Venue AI Vision & Conceptual Image Generator -->
    <script>
        const bookingId = <?= $booking_id ?>;
        const currentUserRole = <?= json_encode($user_role) ?>;
        const isUserAdmin = <?= $is_admin ? 'true' : 'false' ?>;
        let currentLayoutStatus = <?= json_encode($layout_approval_status) ?>;

        function escapeHtml(text) {
            if (!text) return '';
            return String(text)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        async function approveVenueLayout(bId) {
            const resConfirm = await Swal.fire({
                title: 'Approve Venue Layout?',
                text: 'This will officially approve the staff-generated AI venue layout as verified and client-ready.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, Approve Layout'
            });

            if (!resConfirm.isConfirmed) return;

            try {
                const formData = new FormData();
                formData.append('action', 'approve_venue_layout');
                formData.append('booking_id', bId);

                const res = await fetch('auth/process_venue_ai.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    currentLayoutStatus = 'approved';
                    updateLayoutApprovalBannerUI('approved', data.layout_approved_by, data.layout_approved_at);
                    Swal.fire({
                        title: 'Layout Approved!',
                        text: data.message,
                        icon: 'success',
                        confirmButtonColor: '#10b981'
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: data.message || 'Failed to approve layout',
                        icon: 'error',
                        confirmButtonColor: '#ef4444'
                    });
                }
            } catch (e) {
                Swal.fire({ title: 'Error', text: 'Network or server error approving layout', icon: 'error' });
            }
        }

        async function rejectVenueLayout(bId) {
            const { value: notes } = await Swal.fire({
                title: 'Request Layout Revision',
                text: 'Enter notes or instructions for revising the venue layout concept:',
                input: 'textarea',
                inputPlaceholder: 'e.g. Please change color palette or reconfigure buffet line...',
                showCancelButton: true,
                confirmButtonColor: '#f43f5e',
                confirmButtonText: 'Submit Revision Request',
                inputValidator: (value) => {
                    if (!value) {
                        return 'Please provide revision notes for the staff!';
                    }
                }
            });

            if (!notes) return;

            try {
                const formData = new FormData();
                formData.append('action', 'reject_venue_layout');
                formData.append('booking_id', bId);
                formData.append('notes', notes);

                const res = await fetch('auth/process_venue_ai.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    currentLayoutStatus = 'rejected';
                    updateLayoutApprovalBannerUI('rejected', null, null, notes);
                    Swal.fire({
                        title: 'Revision Requested',
                        text: 'Venue layout marked as Revision Requested.',
                        icon: 'info',
                        confirmButtonColor: '#7c3aed'
                    });
                } else {
                    Swal.fire({ title: 'Error', text: data.message || 'Failed to update layout status', icon: 'error' });
                }
            } catch (e) {
                Swal.fire({ title: 'Error', text: 'Network or server error', icon: 'error' });
            }
        }

        function updateLayoutApprovalBannerUI(status, approver, approvedAt, notes = '') {
            const banner = document.getElementById('layout-approval-banner');
            const title = document.getElementById('approval-status-title');
            const badge = document.getElementById('approval-badge-role');
            const subtitle = document.getElementById('approval-status-subtitle');
            const buttons = document.getElementById('admin-approval-buttons');
            const dot = document.getElementById('approval-indicator-dot');
            if (!banner) return;

            banner.classList.remove('hidden', 'bg-amber-500/10', 'border-amber-300', 'bg-emerald-500/10', 'border-emerald-300', 'bg-rose-500/10', 'border-rose-300', 'dark:bg-amber-950/40', 'dark:border-amber-700/80', 'dark:bg-emerald-950/40', 'dark:border-emerald-700/80', 'dark:bg-rose-950/40', 'dark:border-rose-700/80');

            if (status === 'approved') {
                banner.classList.add('bg-emerald-500/10', 'border-emerald-300', 'dark:bg-emerald-950/40', 'dark:border-emerald-700/80');
                if (dot) dot.className = 'w-2.5 h-2.5 rounded-full mt-1 sm:mt-0 shrink-0 bg-emerald-500';
                if (title) {
                    title.className = 'text-xs font-black uppercase tracking-wider text-emerald-800 dark:text-emerald-300';
                    title.innerText = 'Layout Approved by Administrator';
                }
                if (badge) {
                    badge.className = 'text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-md bg-emerald-200/80 dark:bg-emerald-900/60 text-emerald-900 dark:text-emerald-200';
                    badge.innerText = 'Admin Approved';
                }
                if (subtitle) {
                    subtitle.className = 'text-[11px] font-medium mt-0.5 text-emerald-700 dark:text-emerald-400';
                    subtitle.innerHTML = `Approved by <strong>${escapeHtml(approver || 'Administrator')}</strong>. Layout is verified and client-ready.`;
                }
                if (buttons) buttons.classList.add('hidden');
            } else if (status === 'pending_approval') {
                banner.classList.add('bg-amber-500/10', 'border-amber-300', 'dark:bg-amber-950/40', 'dark:border-amber-700/80');
                if (dot) dot.className = 'w-2.5 h-2.5 rounded-full mt-1 sm:mt-0 shrink-0 bg-amber-500 animate-ping';
                if (title) {
                    title.className = 'text-xs font-black uppercase tracking-wider text-amber-800 dark:text-amber-300';
                    title.innerText = 'Pending Admin Approval';
                }
                if (badge) {
                    badge.className = 'text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-md bg-amber-200/80 dark:bg-amber-900/60 text-amber-900 dark:text-amber-200';
                    badge.innerText = 'Awaiting Review';
                }
                if (subtitle) {
                    subtitle.className = 'text-[11px] font-medium mt-0.5 text-amber-700 dark:text-amber-400';
                    subtitle.innerHTML = 'Staff-generated layout concepts require administrator approval before finalization.';
                }
                if (buttons && isUserAdmin) buttons.classList.remove('hidden');
            } else if (status === 'rejected') {
                banner.classList.add('bg-rose-500/10', 'border-rose-300', 'dark:bg-rose-950/40', 'dark:border-rose-700/80');
                if (dot) dot.className = 'w-2.5 h-2.5 rounded-full mt-1 sm:mt-0 shrink-0 bg-rose-500';
                if (title) {
                    title.className = 'text-xs font-black uppercase tracking-wider text-rose-800 dark:text-rose-300';
                    title.innerText = 'Revision Requested by Admin';
                }
                if (badge) {
                    badge.className = 'text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-md bg-rose-200/80 dark:bg-rose-900/60 text-rose-900 dark:text-rose-200';
                    badge.innerText = 'Needs Changes';
                }
                if (subtitle) {
                    subtitle.className = 'text-[11px] font-medium mt-0.5 text-rose-700 dark:text-rose-400';
                    subtitle.innerHTML = `Note: "${escapeHtml(notes)}". Please re-generate setup with requested adjustments.`;
                }
                if (buttons) buttons.classList.add('hidden');
            }
        }

        function showAIBanner(message, type = 'info') {
            const banner = document.getElementById('ai-status-banner');
            if (!banner) return;
            
            // Reset base classes with rich shadows, padding, and border
            banner.className = 'p-3.5 rounded-xl border text-xs font-extrabold flex items-center gap-3 transition-all duration-300 shadow-sm';
            
            let iconHtml = '';
            if (type === 'success') {
                banner.classList.add(
                    'bg-emerald-50', 'border-emerald-300', 'text-emerald-950',
                    'dark:bg-emerald-950/80', 'dark:border-emerald-500/60', 'dark:text-emerald-100'
                );
                iconHtml = '<i class="bi bi-check-circle-fill text-emerald-600 dark:text-emerald-400 text-base shrink-0"></i>';
            } else if (type === 'error') {
                banner.classList.add(
                    'bg-rose-50', 'border-rose-300', 'text-rose-950',
                    'dark:bg-rose-950/80', 'dark:border-rose-500/60', 'dark:text-rose-100'
                );
                iconHtml = '<i class="bi bi-exclamation-triangle-fill text-rose-600 dark:text-rose-400 text-base shrink-0"></i>';
            } else {
                banner.classList.add(
                    'bg-indigo-50', 'border-indigo-300', 'text-indigo-950',
                    'dark:bg-indigo-950/80', 'dark:border-indigo-500/60', 'dark:text-indigo-100'
                );
                iconHtml = '<i class="bi bi-info-circle-fill text-indigo-600 dark:text-indigo-400 text-base shrink-0"></i>';
            }

            banner.innerHTML = `${iconHtml} <span class="leading-relaxed flex-1">${escapeHtml(message)}</span>`;
            banner.classList.remove('hidden');
        }

        function updateVenueAnalysisUI(analysis) {
            if (!analysis) return;
            if (document.getElementById('v-type')) {
                document.getElementById('v-type').innerText = analysis.venue_type || 'Event Venue';
            }
            if (document.getElementById('ai-venue-type') && analysis.venue_type) {
                document.getElementById('ai-venue-type').value = analysis.venue_type;
            }
            if (document.getElementById('v-cap')) {
                document.getElementById('v-cap').innerText = analysis.estimated_capacity || '100 Pax';
            }
            if (document.getElementById('v-walls') && analysis.wall_characteristics) {
                document.getElementById('v-walls').innerText = analysis.wall_characteristics;
                document.getElementById('v-walls').title = analysis.wall_characteristics;
            }
            if (document.getElementById('v-floor') && analysis.floor_type) {
                document.getElementById('v-floor').innerText = analysis.floor_type;
                document.getElementById('v-floor').title = analysis.floor_type;
            }
            if (document.getElementById('v-light') && analysis.lighting) {
                document.getElementById('v-light').innerText = analysis.lighting;
                document.getElementById('v-light').title = analysis.lighting;
            }
        }

        async function uploadVenuePhoto(e) {
            e.preventDefault();
            const fileInput = document.getElementById('venue-file-input');
            if (!fileInput.files || fileInput.files.length === 0) return;

            const formData = new FormData();
            formData.append('action', 'upload_venue');
            formData.append('booking_id', bookingId);
            formData.append('venue_image', fileInput.files[0]);

            showAIBanner('Uploading venue photo & Gemini Vision is analyzing layout details...', 'info');

            try {
                const res = await fetch('auth/process_venue_ai.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    const imgOrg = document.getElementById('img-original-venue');
                    const imgPlaceholder = document.getElementById('img-original-venue-placeholder');
                    if (imgOrg && data.venue_image) {
                        imgOrg.src = data.venue_image + '?t=' + new Date().getTime();
                        imgOrg.classList.remove('hidden');
                    }
                    if (imgPlaceholder) imgPlaceholder.classList.add('hidden');

                    if (data.analysis) {
                        updateVenueAnalysisUI(data.analysis);
                    }

                    if (data.conceptual_image) {
                        const imgConcept = document.getElementById('img-ai-concept');
                        const conceptPlaceholder = document.getElementById('img-ai-concept-placeholder');
                        const downloadBtn = document.getElementById('btn-download-concept');
                        const conceptBadge = document.getElementById('ai-concept-badge');
                        if (imgConcept) {
                            imgConcept.src = data.conceptual_image + '?t=' + new Date().getTime();
                            imgConcept.classList.remove('hidden');
                        }
                        if (conceptPlaceholder) conceptPlaceholder.classList.add('hidden');
                        if (downloadBtn) {
                            downloadBtn.href = data.conceptual_image;
                            downloadBtn.classList.remove('pointer-events-none', 'opacity-40');
                        }
                        if (conceptBadge) {
                            conceptBadge.innerText = 'Google Gemini AI Pro';
                        }
                    }

                    if (data.prompt) {
                        const promptInput = document.getElementById('admin-prompt-input');
                        if (promptInput) {
                            promptInput.value = data.prompt;
                            promptInput.classList.add('ring-2', 'ring-purple-500');
                            setTimeout(() => promptInput.classList.remove('ring-2', 'ring-purple-500'), 2500);
                        }
                    }

                    const vType = data.analysis?.venue_type || 'Venue layout';
                    showAIBanner(`✅ Venue photo uploaded & analyzed! Detected: ${vType}. Staged setup concept generated with your venue image 100% intact.`, 'success');
                } else {
                    showAIBanner(data.message || 'Error uploading venue photo.', 'error');
                }
            } catch (err) {
                showAIBanner('Error uploading and analyzing venue photo.', 'error');
            }
        }

        async function analyzeVenueAI() {
            const btn = document.getElementById('btn-analyze-venue');
            const origBtnText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = `<i class="bi bi-arrow-repeat animate-spin"></i> Step 1: Vision Analysis...`;

            showAIBanner('Step 1: Running Gemini Vision analysis on uploaded venue photo...', 'info');

            try {
                const formData = new FormData();
                formData.append('action', 'analyze_venue');
                formData.append('booking_id', bookingId);

                const res = await fetch('auth/process_venue_ai.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success && data.analysis) {
                    updateVenueAnalysisUI(data.analysis);

                    const promptInput = document.getElementById('admin-prompt-input');
                    if (promptInput && (data.prompt || data.analysis.accurate_staging_prompt)) {
                        promptInput.value = data.prompt || data.analysis.accurate_staging_prompt;
                        promptInput.classList.add('ring-2', 'ring-purple-500');
                        setTimeout(() => promptInput.classList.remove('ring-2', 'ring-purple-500'), 2500);
                    }

                    const vType = data.analysis.venue_type || 'Venue';
                    showAIBanner(`✅ Step 1 Complete: Gemini Vision analyzed your venue (${vType})! Staging parameters and prompt updated. Click "Generate 16:9 Image" to render.`, 'success');
                } else {
                    showAIBanner(data.message || 'Vision analysis failed.', 'error');
                }
            } catch (err) {
                showAIBanner('Error during venue vision analysis.', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = origBtnText;
            }
        }

        async function enrichStagingPrompt() {
            const btn = document.getElementById('btn-enrich-prompt');
            const origText = btn ? btn.innerHTML : '';
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = `<i class="bi bi-arrow-repeat animate-spin"></i> Step 1: Enriching...`;
            }

            const promptInput = document.getElementById('admin-prompt-input');
            const venueType = document.getElementById('ai-venue-type')?.value || '';
            const theme = document.getElementById('ai-theme')?.value || '';
            const pax = document.getElementById('ai-pax')?.value || '';
            const colorScheme = document.getElementById('ai-color-scheme')?.value || '';

            showAIBanner('Step 1: Google AI Studio is converting form inputs & venue features into detailed staging prompt...', 'info');

            try {
                const formData = new FormData();
                formData.append('action', 'craft_venue_prompt');
                formData.append('booking_id', bookingId);
                formData.append('venue_type', venueType);
                formData.append('theme', theme);
                formData.append('pax_count', pax);
                formData.append('color_scheme', colorScheme);
                formData.append('custom_req', promptInput ? promptInput.value.trim() : '');

                const res = await fetch('auth/process_venue_ai.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success && data.prompt) {
                    if (promptInput) {
                        promptInput.value = data.prompt;
                        promptInput.focus();
                        promptInput.classList.add('ring-2', 'ring-purple-500');
                        setTimeout(() => promptInput.classList.remove('ring-2', 'ring-purple-500'), 2500);
                    }
                    showAIBanner('✅ Step 1 Complete: Prompt enriched via Google AI! Click Step 2 to generate 16:9 image.', 'success');
                } else {
                    showAIBanner(data.message || 'Could not enrich prompt from inputs.', 'error');
                }
            } catch (err) {
                showAIBanner('Error enriching staging prompt.', 'error');
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = origText;
                }
            }
        }

        async function setPresetPrompt(type) {
            const promptInput = document.getElementById('admin-prompt-input');
            const targetBtn = document.getElementById(`preset-btn-${type}`);
            const origText = targetBtn ? targetBtn.innerHTML : '';
            if (targetBtn) {
                targetBtn.innerHTML = `<i class="bi bi-arrow-repeat animate-spin"></i> Fetching...`;
            }

            // Auto-adjust theme and color presets in inputs
            const themeInput = document.getElementById('ai-theme');
            const colorInput = document.getElementById('ai-color-scheme');
            if (type === 'wedding') {
                if (themeInput) themeInput.value = 'Luxury Wedding Reception';
                if (colorInput) colorInput.value = 'Champagne Gold & Warm White';
            } else if (type === 'birthday') {
                if (themeInput) themeInput.value = 'Festive Celebratory Birthday';
                if (colorInput) colorInput.value = 'Vibrant Colorful Modern Accents';
            } else if (type === 'corporate') {
                if (themeInput) themeInput.value = 'Executive Corporate Gala';
                if (colorInput) colorInput.value = 'Navy, Slate & Ivory';
            }

            showAIBanner(`AI is crafting prompt tailored for ${type}...`, 'info');

            try {
                const formData = new FormData();
                formData.append('action', 'craft_venue_prompt');
                formData.append('booking_id', bookingId);
                formData.append('preset', type);
                formData.append('venue_type', document.getElementById('ai-venue-type')?.value || '');
                formData.append('theme', document.getElementById('ai-theme')?.value || '');
                formData.append('pax_count', document.getElementById('ai-pax')?.value || '');
                formData.append('color_scheme', document.getElementById('ai-color-scheme')?.value || '');

                const res = await fetch('auth/process_venue_ai.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success && data.prompt) {
                    promptInput.value = data.prompt;
                    promptInput.focus();
                    promptInput.classList.add('ring-2', 'ring-purple-500');
                    setTimeout(() => promptInput.classList.remove('ring-2', 'ring-purple-500'), 2500);
                    showAIBanner(`Prompt enriched for ${type} based on customer venue!`, 'success');
                    return;
                }
            } catch (err) {} finally {
                if (targetBtn) targetBtn.innerHTML = origText;
            }

            let text = "";
            if (type === 'wedding') {
                text = `Keep the current image intact, preserving the exact venue architecture, background, and floor. Stage luxury wedding catering with round 10-seater banquet tables draped in champagne linens, gold Chiavari chairs, lush white floral centerpieces, and perimeter twin buffet lines with roll-top silver chafing dishes.`;
            } else if (type === 'birthday') {
                text = `Keep the current image intact, preserving the exact venue architecture, background, and floor. Stage a festive celebratory birthday catering setup with colorful modern table runners on round guest tables, cheerful floral accents, dessert station, and perimeter dual-sided buffet lines with mirror-finished chafing dishes.`;
            } else if (type === 'corporate') {
                text = `Keep the current image intact, preserving the exact venue architecture, background, and floor. Stage an executive corporate banquet catering setup with elegant navy and ivory draped round tables, neat banquet seating, and perimeter buffet lines with mirror-finished chafing dishes.`;
            }
            if (promptInput) {
                promptInput.value = text;
                promptInput.focus();
            }
        }

        async function fetchVenuePromptAI() {
            const btn = document.getElementById('btn-fetch-prompt');
            const orig = btn ? btn.innerHTML : '';
            if (btn) btn.innerHTML = `<i class="bi bi-arrow-repeat animate-spin"></i> Fetching Prompt...`;
            await setPresetPrompt('wedding');
            if (btn) btn.innerHTML = orig;
        }

        function copyAISummary() {
            const summaryCard = document.getElementById('ai-summary-content');
            if (!summaryCard) return;
            const textToCopy = summaryCard.innerText.trim();
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(textToCopy).then(() => {
                    showAIBanner('AI Package & Staging Summary copied to clipboard!', 'success');
                }).catch(() => {
                    showAIBanner('Failed to copy. Please manually select and copy the text.', 'error');
                });
            } else {
                showAIBanner('AI Package Summary displayed on screen.', 'info');
            }
        }

        function renderAISummary(sum) {
            if (!sum) return;
            const card = document.getElementById('ai-summary-card');
            const container = document.getElementById('ai-summary-content');
            if (!card || !container) return;

            let html = '';

            // 1. Executive Narrative
            if (sum.executive_narrative) {
                html += `
                    <div class="p-3 bg-purple-50/70 dark:bg-purple-950/40 rounded-xl border border-purple-100 dark:border-purple-800/50 space-y-1">
                        <p class="text-[10px] font-extrabold uppercase text-purple-700 dark:text-purple-300">Executive Staging Overview</p>
                        <p class="text-xs text-slate-800 dark:text-slate-200 whitespace-pre-line font-medium leading-relaxed">${escapeHtml(sum.executive_narrative)}</p>
                    </div>
                `;
            }

            // 2. Package & Tables Grid
            const pkgName = sum.package_name || 'Catering Package';
            const total = sum.formatted_amount || '₱0.00';
            const pax = sum.pax_count || <?= $booking['pax_count'] ?>;
            const tables = (sum.suggested_tables && sum.suggested_tables.guest_tables) ? sum.suggested_tables.guest_tables : `${Math.ceil(pax / 10)} Round Tables`;
            const headTable = (sum.suggested_tables && sum.suggested_tables.head_table) ? sum.suggested_tables.head_table : '1 VIP Head Table';
            const buffet = (sum.suggested_tables && sum.suggested_tables.buffet_station) ? sum.suggested_tables.buffet_station : 'Twin Buffet Lines';

            html += `
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    <div class="p-3 bg-white dark:bg-white/5 rounded-xl border border-slate-200 dark:border-white/10">
                        <p class="text-[9px] font-extrabold text-slate-500 dark:text-purple-300 uppercase">Package Availed</p>
                        <p class="text-xs font-bold text-slate-900 dark:text-white mt-0.5">${escapeHtml(pkgName)}</p>
                        <p class="text-[11px] text-purple-600 dark:text-purple-400 font-bold mt-1">Contract: ${escapeHtml(total)} • ${pax} Guests</p>
                    </div>
                    <div class="p-3 bg-white dark:bg-white/5 rounded-xl border border-slate-200 dark:border-white/10">
                        <p class="text-[9px] font-extrabold text-slate-500 dark:text-purple-300 uppercase">Suggested Table Setup</p>
                        <p class="text-xs font-bold text-slate-900 dark:text-white mt-0.5">${escapeHtml(tables)}</p>
                        <p class="text-[11px] text-slate-600 dark:text-slate-400 font-medium mt-1">${escapeHtml(headTable)} • ${escapeHtml(buffet)}</p>
                    </div>
                </div>
            `;

            // 3. Menu Breakdown
            if (sum.menu_by_category && Object.keys(sum.menu_by_category).length > 0) {
                html += `
                    <div class="p-3 bg-white dark:bg-white/5 rounded-xl border border-slate-200 dark:border-white/10">
                        <p class="text-[9px] font-extrabold text-slate-500 dark:text-purple-300 uppercase mb-1.5">Selected Menu Dishes Included:</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                `;
                for (const [cat, dishes] of Object.entries(sum.menu_by_category)) {
                    const dishList = Array.isArray(dishes) ? dishes.join(', ') : dishes;
                    html += `
                        <div>
                            <span class="text-[10px] font-extrabold text-purple-700 dark:text-purple-300 uppercase block">${escapeHtml(cat)}:</span>
                            <span class="text-[11px] text-slate-700 dark:text-slate-300 font-medium">${escapeHtml(dishList)}</span>
                        </div>
                    `;
                }
                html += `
                        </div>
                    </div>
                `;
            }

            container.innerHTML = html;
            card.classList.remove('hidden');
        }

        async function generateConceptualSetup() {
            const regenBtn = document.getElementById('btn-regenerate-concept');
            const promptBtn = document.getElementById('btn-prompt-ai');
            const origRegenText = regenBtn ? regenBtn.innerHTML : '';
            const origPromptText = promptBtn ? promptBtn.innerHTML : '';
            
            if (regenBtn) {
                regenBtn.disabled = true;
                regenBtn.innerHTML = `<i class="bi bi-arrow-repeat animate-spin"></i> Rendering...`;
            }
            if (promptBtn) {
                promptBtn.disabled = true;
                promptBtn.innerHTML = `<i class="bi bi-arrow-repeat animate-spin"></i> Prompting AI &amp; Rendering...`;
            }

            const promptInput = document.getElementById('admin-prompt-input');
            const adminPrompt = promptInput ? promptInput.value.trim() : '';
            const venueType = document.getElementById('ai-venue-type')?.value || '';
            const theme = document.getElementById('ai-theme')?.value || '';
            const pax = document.getElementById('ai-pax')?.value || '';
            const colorScheme = document.getElementById('ai-color-scheme')?.value || '';

            showAIBanner('Pipeline running: Google AI enriching prompt &rarr; Pollinations.ai (Flux) generating 16:9 visualization...', 'info');

            try {
                const formData = new FormData();
                formData.append('action', 'generate_concept');
                formData.append('booking_id', bookingId);
                formData.append('venue_type', venueType);
                formData.append('theme', theme);
                formData.append('pax_count', pax);
                formData.append('color_scheme', colorScheme);
                if (adminPrompt) {
                    formData.append('admin_prompt', adminPrompt);
                }

                const res = await fetch('auth/process_venue_ai.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.enriched_prompt && promptInput && !adminPrompt) {
                    promptInput.value = data.enriched_prompt;
                }

                if (data.ai_summary) {
                    renderAISummary(data.ai_summary);
                }

                if (data.layout_approval_status) {
                    currentLayoutStatus = data.layout_approval_status;
                    updateLayoutApprovalBannerUI(data.layout_approval_status, data.layout_approved_by, data.layout_approved_at);
                }

                if (data.success && data.conceptual_image) {
                    const imgConcept = document.getElementById('img-ai-concept');
                    const conceptPlaceholder = document.getElementById('img-ai-concept-placeholder');
                    const downloadBtn = document.getElementById('btn-download-concept');
                    const conceptBadge = document.getElementById('ai-concept-badge');

                    imgConcept.src = data.conceptual_image + '?t=' + new Date().getTime();
                    imgConcept.classList.remove('hidden');
                    if (conceptPlaceholder) conceptPlaceholder.classList.add('hidden');

                    downloadBtn.href = data.conceptual_image;
                    downloadBtn.classList.remove('pointer-events-none', 'opacity-40');

                    if (conceptBadge && data.generation_engine) {
                        conceptBadge.innerText = data.generation_engine;
                    }

                    showAIBanner('✅ 16:9 Conceptual Catering Visualization generated via Pollinations.ai!', 'success');
                } else if (data.quota_exhausted) {
                    showAIBanner('⚠️ Google AI Vision analyzed your venue successfully! Note: Image generation quota reached. Staging prompt and package breakdown are updated.', 'warning');
                } else if (data.ai_summary) {
                    showAIBanner('AI Package & Staging Summary generated! ' + (data.message || ''), 'info');
                } else {
                    showAIBanner(data.message || 'Failed to generate conceptual setup image.', 'error');
                }
            } catch (err) {
                showAIBanner('Error processing AI concept setup. Please try again.', 'error');
            } finally {
                if (regenBtn) {
                    regenBtn.disabled = false;
                    regenBtn.innerHTML = origRegenText;
                }
                if (promptBtn) {
                    promptBtn.disabled = false;
                    promptBtn.innerHTML = origPromptText;
                }
            }
        }

        async function sendAIRecommendation() {
            if (currentUserRole === 'staff' && currentLayoutStatus !== 'approved') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Admin Approval Required',
                    text: 'This AI venue layout must be reviewed and approved by an Administrator before it can be sent to the customer.',
                    confirmButtonColor: '#7c3aed'
                });
                return;
            }

            showAIBanner('Sending AI catering recommendation & package proposal to customer chat...', 'info');

            try {
                const formData = new FormData();
                formData.append('action', 'send_recommendation');
                formData.append('booking_id', bookingId);

                const res = await fetch('auth/process_venue_ai.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    showAIBanner(data.message, 'success');
                } else {
                    showAIBanner(data.message || 'Failed to send recommendation.', 'error');
                }
            } catch (err) {
                showAIBanner('Error sending recommendation to chat.', 'error');
            }
        }
    </script>
</body>
</html>