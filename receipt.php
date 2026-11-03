<?php
require_once 'includes/auth_check.php';
require_once 'db_config.php';

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'client';

// 1. Security Check & Fetch Booking (Admins can view all, Clients only theirs)
if ($user_role === 'admin') {
    $b_stmt = $conn->prepare("SELECT b.*, u.full_name, u.email, p.reference_no FROM bookings b JOIN users u ON b.user_id = u.user_id LEFT JOIN payments p ON b.booking_id = p.booking_id WHERE b.booking_id = ? ORDER BY p.created_at DESC LIMIT 1");
    $b_stmt->bind_param("i", $booking_id);
} else {
    $b_stmt = $conn->prepare("SELECT b.*, u.full_name, u.email, p.reference_no FROM bookings b JOIN users u ON b.user_id = u.user_id LEFT JOIN payments p ON b.booking_id = p.booking_id WHERE b.booking_id = ? AND b.user_id = ? ORDER BY p.created_at DESC LIMIT 1");
    $b_stmt->bind_param("ii", $booking_id, $user_id);
}
$b_stmt->execute();
$booking = $b_stmt->get_result()->fetch_assoc();

if (!$booking) {
    die("Access denied or receipt not found.");
}

// 2. Fetch Items
$i_stmt = $conn->prepare("SELECT bi.*, mi.item_name FROM booking_items bi JOIN menu_items mi ON bi.item_id = mi.item_id WHERE bi.booking_id = ?");
$i_stmt->bind_param("i", $booking_id);
$i_stmt->execute();
$items = $i_stmt->get_result();

// 3. Fetch Dietary Restrictions
$dr_stmt = $conn->prepare("SELECT restriction_type FROM dietary_restrictions WHERE booking_id = ?");
$dr_stmt->bind_param("i", $booking_id);
$dr_stmt->execute();
$dietary_notes = $dr_stmt->get_result()->fetch_assoc()['restriction_type'] ?? '';


// Calculate Payment Breakdown
$total = $booking['total_amount'];
$status = $booking['payment_status'];
$downpayment_val = min($total, 10000);

if ($status === 'fully_paid') {
    $amount_paid = $total;
    $balance = 0;
} else if ($status === 'partially_paid') {
    $amount_paid = $downpayment_val;
    $balance = $total - $downpayment_val;
} else {
    $amount_paid = 0;
    $balance = $total;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt #<?= str_pad($booking_id, 5, '0', STR_PAD_LEFT) ?> | JEMmerific</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;700;800&display=swap" rel="stylesheet">
    <script>
        function applyAppTheme() {
            const savedTheme = localStorage.getItem('theme') || 'light';
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
        @media print { 
            .no-print { display: none !important; } 
            body { background: white !important; padding: 0 !important; }
            html, body { color: black !important; }
            .receipt-container { border: none !important; box-shadow: none !important; padding: 0 !important; background: white !important; } 
            /* Disable dark mode specifically for print */
            * { color: inherit; background: transparent; }
        }
        @media (min-width: 1024px) {
            html {
                font-size: 84%;
            }
        }
    </style>
</head>
<body class="bg-slate-50 dark:bg-[#0b0f19] text-slate-900 dark:text-slate-100 p-4 sm:p-6 md:p-8 transition-colors duration-300">
    <div class="max-w-3xl mx-auto bg-white dark:bg-[#131b2e] p-6 sm:p-10 rounded-2xl shadow-xs border border-slate-200/80 dark:border-slate-800 receipt-container relative overflow-hidden transition-colors duration-300">
        <!-- Decorative element -->
        <div class="absolute top-0 right-0 w-24 h-24 bg-purple-50 dark:bg-purple-900/20 rounded-bl-[4rem] -mr-10 -mt-10"></div>

        <div class="flex justify-between items-start mb-8">
            <div>
                <h1 class="text-2xl font-black text-slate-900 dark:text-slate-100 tracking-tighter italic mb-0.5">JEM<span class="text-purple-600 dark:text-purple-400">merific.</span></h1>
                <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest">Catering Services Receipt</p>
            </div>
            <div class="text-right">
                <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest">Receipt Number</p>
                <p class="text-base font-black text-slate-900 dark:text-slate-100">#BK-<?= str_pad($booking_id, 6, '0', STR_PAD_LEFT) ?></p>
                <p class="text-[9px] text-slate-400 font-medium mt-0.5">Generated: <?= date('M d, Y h:i A') ?></p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-8 mb-8 p-4 bg-gray-50/70 dark:bg-slate-800/50 rounded-xl border border-gray-100 dark:border-slate-700/50">
            <div>
                <p class="text-[9px] text-purple-600 dark:text-purple-400 font-black uppercase tracking-widest mb-2">Client Information</p>
                <p class="text-xs font-bold text-gray-900 dark:text-slate-100"><?= htmlspecialchars($booking['full_name']) ?></p>
                <p class="text-[11px] text-gray-500 dark:text-slate-400 mt-0.5"><?= htmlspecialchars($booking['email']) ?></p>
            </div>
            <div class="text-right">
                <p class="text-[9px] text-purple-600 dark:text-purple-400 font-black uppercase tracking-widest mb-2">Event Logistics</p>
                <p class="text-xs font-bold text-gray-900 dark:text-slate-100"><?= htmlspecialchars($booking['event_name']) ?></p>
                <p class="text-[11px] text-gray-500 dark:text-slate-400 mt-0.5"><?= date('l, M d, Y', strtotime($booking['event_date'])) ?> @ <?= date('h:i A', strtotime($booking['event_time'])) ?><?= !empty($booking['event_end_time']) ? ' - ' . date('h:i A', strtotime($booking['event_end_time'])) : '' ?></p>
                <p class="text-[11px] text-gray-500 dark:text-slate-400 mt-0.5"><?= htmlspecialchars($booking['venue_location']) ?> | <?= $booking['pax_count'] ?> Pax</p>
            </div>
        </div>

        <?php if (!empty($dietary_notes)): ?>
        <div class="mb-8">
            <p class="text-[9px] text-purple-600 dark:text-purple-400 font-black uppercase tracking-widest mb-2">Dietary Restrictions / Special Notes</p>
            <p class="text-xs font-medium text-gray-700 dark:text-slate-300 leading-relaxed bg-gray-50 dark:bg-slate-800 p-4 rounded-xl border border-gray-100 dark:border-slate-700/50"><?= nl2br(htmlspecialchars($dietary_notes)) ?></p>
        </div>
        <?php endif; ?>

        <table class="w-full mb-8">
            <thead>
                <tr class="text-[9px] text-gray-400 dark:text-slate-500 font-black uppercase tracking-widest border-b border-gray-100 dark:border-slate-700">
                    <th class="py-2.5 text-left">Description</th>
                    <th class="py-2.5 text-center">Qty</th>
                    <th class="py-2.5 text-right">Price</th>
                    <th class="py-2.5 text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-slate-800/80">
                <?php while($row = $items->fetch_assoc()): ?>
                <tr>
                    <td class="py-3 text-xs">
                        <span class="font-bold text-gray-800 dark:text-slate-200"><?= htmlspecialchars($row['item_name']) ?></span>
                        <?php if (!empty($row['dietary_notes'])): ?>
                            <span class="inline-flex items-center gap-1 text-[9px] font-bold text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/60 px-1.5 py-0.5 rounded border border-amber-200 dark:border-amber-800/40 ml-1.5 align-middle">
                                <i class="bi bi-tag-fill text-[8px]"></i> <?= htmlspecialchars($row['dietary_notes']) ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="py-3 text-center text-xs text-gray-500 dark:text-slate-400 font-medium"><?= $row['quantity'] ?></td>
                    <td class="py-3 text-right text-xs text-gray-500 dark:text-slate-400">₱<?= number_format($row['unit_price'], 2) ?></td>
                    <td class="py-3 text-right text-xs font-black text-gray-900 dark:text-slate-100">₱<?= number_format($row['subtotal'], 2) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <div class="bg-gray-50/80 dark:bg-slate-800/50 rounded-xl p-6 border border-gray-100 dark:border-slate-700/50">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 pb-6 border-b border-dashed border-gray-200 dark:border-slate-700">
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <p class="text-[9px] text-gray-400 dark:text-slate-500 font-black uppercase tracking-widest">Initial Downpayment</p>
                        <p class="text-xs font-bold text-gray-700 dark:text-slate-300">₱<?= number_format($downpayment_val, 2) ?></p>
                    </div>
                    <div class="flex justify-between items-center">
                        <p class="text-[9px] text-gray-400 dark:text-slate-500 font-black uppercase tracking-widest">Amount Settled</p>
                        <p class="text-xs font-bold text-green-600 dark:text-emerald-400">₱<?= number_format($amount_paid, 2) ?></p>
                    </div>
                    <div class="flex justify-between items-center">
                        <p class="text-[9px] text-gray-400 dark:text-slate-500 font-black uppercase tracking-widest">Outstanding Balance</p>
                        <p class="text-xs font-bold text-red-500 dark:text-rose-400">₱<?= number_format($balance, 2) ?></p>
                    </div>
                </div>
                <div class="flex flex-col justify-center md:items-end">
                    <p class="text-[9px] text-gray-400 dark:text-slate-500 font-black uppercase tracking-widest mb-1.5">Current Status</p>
                    <span class="inline-block px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest shadow-2xs 
                        <?= $status == 'fully_paid' ? 'bg-green-100 dark:bg-emerald-900/40 text-green-600 dark:text-emerald-400' : ($status == 'partially_paid' ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400' : 'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-600 dark:text-yellow-400') ?>">
                        <?= strtoupper(str_replace('_', ' ', $status)) ?>
                    </span>
                    <?php if (!empty($booking['reference_no'])): ?>
                        <div class="mt-3 text-right">
                            <p class="text-[9px] text-gray-400 dark:text-slate-500 font-black uppercase tracking-widest mb-0.5">Payment Ref</p>
                            <p class="text-[10px] font-bold text-gray-600 dark:text-slate-400 tracking-widest"><?= htmlspecialchars($booking['reference_no']) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="flex justify-between items-center">
                <div>
                    <p class="text-[9px] text-gray-400 dark:text-slate-500 font-black uppercase tracking-widest mb-0.5">Total Event Cost</p>
                    <p class="text-[11px] text-gray-400 dark:text-slate-500 font-medium italic">Inclusive of all taxes &amp; service fees</p>
                </div>
                <div class="text-right">
                    <p class="text-2xl font-black text-purple-600 dark:text-purple-400 tracking-tight">₱<?= number_format($total, 2) ?></p>
                </div>
            </div>
        </div>

        <div class="mt-10 text-center">
            <p class="text-[9px] text-gray-300 dark:text-slate-600 font-black uppercase tracking-[0.25em]">Thank you for choosing JEMmerific Catering!</p>
        </div>
    </div>

    <div class="max-w-3xl mx-auto mt-6 flex flex-col sm:flex-row gap-3 no-print">
        <button onclick="window.print()" class="w-full sm:flex-1 bg-gray-900 dark:bg-[#1a233a] border border-transparent dark:border-slate-800 text-white py-3.5 rounded-xl font-black text-[11px] uppercase tracking-widest shadow-md hover:bg-purple-600 dark:hover:bg-purple-600 transition-all active:scale-95 cursor-pointer flex items-center justify-center min-h-[44px]">
            <i class="bi bi-printer-fill mr-2"></i> Print Receipt
        </button>
        <a href="<?= $user_role === 'admin' ? 'admin_dashboard.php' : 'client_home.php' ?>" class="w-full sm:flex-1 text-center bg-white dark:bg-[#131b2e] border border-gray-200 dark:border-slate-800 text-gray-600 dark:text-slate-300 py-3.5 rounded-xl font-black text-[11px] uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-slate-800/80 transition-all flex items-center justify-center min-h-[44px]">
            <i class="bi bi-house-door mr-2"></i> Return to Dashboard
        </a>
    </div>
</body>
</html>