<?php
require_once 'includes/auth_check.php';
require_once 'db_config.php';

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
$user_id = $_SESSION['user_id'];

// 1. Fetch Event Details
$b_stmt = $conn->prepare("SELECT * FROM bookings WHERE booking_id = ? AND user_id = ?");
$b_stmt->bind_param("ii", $booking_id, $user_id);
$b_stmt->execute();
$booking = $b_stmt->get_result()->fetch_assoc();

if (!$booking) {
    die("Unauthorized access or booking not found.");
}

// 1.5 Fetch Dietary Restrictions
$dr_stmt = $conn->prepare("SELECT restriction_type FROM dietary_restrictions WHERE booking_id = ?");
$dr_stmt->bind_param("i", $booking_id);
$dr_stmt->execute();
$dietary_restrictions_res = $dr_stmt->get_result();
$dietary_notes = $dietary_restrictions_res->fetch_assoc()['restriction_type'] ?? '';

// 2. Fetch Selected Menu Items
$i_stmt = $conn->prepare("SELECT bi.*, mi.item_name, mi.category_id, mi.image_path 
                          FROM booking_items bi 
                          JOIN menu_items mi ON bi.item_id = mi.item_id 
                          WHERE bi.booking_id = ?");
$i_stmt->bind_param("i", $booking_id);
$i_stmt->execute();
$items = $i_stmt->get_result();

// --- Package Breakdown Logic ---
$package_name = null;
$package_price_per_pax = 0;
$package_subtotal = 0;
$is_package_selected = false;
$package_limits = ['main' => 0, 'pasta' => 0, 'veggies' => 0, 'dessert' => 0];
$category_counts = [1 => 0, 2 => 0, 4 => 0, 5 => 0]; 

$selected_items_list = [];
while ($row = $items->fetch_assoc()) {
    $selected_items_list[] = $row;
    if (isset($category_counts[$row['category_id']])) {
        $category_counts[$row['category_id']]++;
    }
}

if (strpos($booking['event_type'], 'Package') !== false) {
    $is_package_selected = true;
    preg_match('/\((Package\s([A-D]))\)/', $booking['event_type'], $matches);
    if (isset($matches[2])) {
        $package_id = $matches[2];
        $package_name = $matches[1];

        $p_stmt = $conn->prepare("SELECT * FROM packages WHERE package_id = ?");
        $p_stmt->bind_param("s", $package_id);
        $p_stmt->execute();
        $pkg_data = $p_stmt->get_result()->fetch_assoc();
        
        if ($pkg_data) {
            $package_price_per_pax = $pkg_data['price_per_pax'];
            $package_subtotal = $booking['pax_count'] * $package_price_per_pax;
            $package_limits = [
                'main' => $pkg_data['main_course_limit'],
                'pasta' => $pkg_data['pasta_limit'],
                'veggies' => $pkg_data['veggies_limit'],
                'dessert' => $pkg_data['dessert_limit']
            ];
        }
    }
}

$downpayment_required = min($booking['total_amount'], 10000);
$remaining_balance = $booking['total_amount'] - $downpayment_required;
$is_modal = isset($_GET['modal']) && $_GET['modal'] == 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Your Event | JEMmerific</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
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
        
        <?php if($is_modal): ?>
        .no-modal { display: none !important; }
        <?php endif; ?>
    </style>
</head>
<body class="bg-slate-50 dark:bg-[#0b0f19] transition-colors duration-300 min-h-screen <?= $is_modal ? 'p-3 md:p-5' : 'p-4 md:p-8' ?>">
    <main class="max-w-3xl mx-auto space-y-4">
        
        <!-- Header -->
        <header class="text-center <?= $is_modal ? 'no-modal' : 'mb-4' ?>">
            <span class="text-[10px] font-extrabold text-purple-700 dark:text-purple-400 uppercase tracking-widest bg-purple-100/80 dark:bg-purple-950/60 px-3 py-1 rounded-full border border-purple-200 dark:border-purple-800/50 inline-block mb-1.5 shadow-xs">Booking Summary</span>
            <h2 class="text-2xl font-black text-slate-900 dark:text-slate-100 tracking-tight">Review Event Details</h2>
        </header>

        <!-- Main Compact Summary Card -->
        <div class="bg-white dark:bg-[#131b2e] rounded-2xl p-5 md:p-6 border border-slate-200 dark:border-slate-800 shadow-sm space-y-5">
            
            <!-- Logistics Header Grid -->
            <div>
                <div class="flex items-center justify-between mb-2.5">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-600 dark:text-slate-400">Event Overview</span>
                    <div class="flex items-center gap-3">
                        <a href="dashboard.php?booking_id=<?= $booking_id ?>" class="text-[10px] font-extrabold text-purple-600 dark:text-purple-400 hover:text-purple-700 dark:hover:text-purple-300 inline-flex items-center gap-1 transition">
                            <i class="bi bi-pencil-square"></i> Edit Details (Step 1)
                        </a>
                        <span class="text-xs font-black text-purple-700 dark:text-purple-400">Ref: #BK-<?= str_pad($booking_id, 4, '0', STR_PAD_LEFT) ?></span>
                    </div>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200/80 dark:border-slate-800">
                    <div>
                        <p class="text-[9px] font-extrabold text-slate-600 dark:text-slate-400 uppercase tracking-wider">Event Name</p>
                        <p class="text-xs font-bold text-slate-900 dark:text-slate-100 truncate"><?= htmlspecialchars($booking['event_name']) ?></p>
                    </div>
                    <div>
                        <p class="text-[9px] font-extrabold text-slate-600 dark:text-slate-400 uppercase tracking-wider">Date & Time</p>
                        <p class="text-xs font-bold text-slate-900 dark:text-slate-100"><?= date('M d, Y', strtotime($booking['event_date'])) ?></p>
                        <p class="text-[10px] font-bold text-slate-600 dark:text-slate-400"><?= date('h:i A', strtotime($booking['event_time'])) ?><?= !empty($booking['event_end_time']) ? ' - ' . date('h:i A', strtotime($booking['event_end_time'])) : '' ?></p>
                    </div>
                    <div>
                        <p class="text-[9px] font-extrabold text-slate-600 dark:text-slate-400 uppercase tracking-wider">Guest Count</p>
                        <p class="text-xs font-bold text-slate-900 dark:text-slate-100"><?= number_format($booking['pax_count']) ?> Pax</p>
                    </div>
                    <div>
                        <p class="text-[9px] font-extrabold text-slate-600 dark:text-slate-400 uppercase tracking-wider">Venue Location</p>
                        <p class="text-xs font-bold text-slate-900 dark:text-slate-100 break-words"><?= htmlspecialchars($booking['venue_location']) ?></p>
                    </div>
                </div>
            </div>

            <!-- Dietary Notes Strip (if present) -->
            <?php if (!empty($dietary_notes)): ?>
            <div class="p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/50 rounded-xl text-xs">
                <span class="font-extrabold text-amber-900 dark:text-amber-300 uppercase tracking-wider text-[10px] block mb-0.5">
                    <i class="bi bi-exclamation-triangle-fill text-amber-600 mr-1"></i> Special Requests & Dietary Notes:
                </span>
                <p class="text-slate-800 dark:text-slate-300 font-semibold"><?= nl2br(htmlspecialchars($dietary_notes)) ?></p>
            </div>
            <?php endif; ?>

            <!-- Package Limits Inline Badges -->
            <?php if ($is_package_selected): ?>
            <div>
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-600 dark:text-slate-400 block mb-2">Package Category Usage</span>
                <div class="flex flex-wrap items-center gap-2">
                    <div class="px-2.5 py-1 bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 rounded-lg text-[10px] font-bold">
                        <span class="text-slate-600 dark:text-slate-400 uppercase mr-1">Main:</span>
                        <span class="text-slate-900 dark:text-slate-100 font-extrabold"><?= $category_counts[1] ?>/<?= $package_limits['main'] ?></span>
                    </div>
                    <div class="px-2.5 py-1 bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 rounded-lg text-[10px] font-bold">
                        <span class="text-slate-600 dark:text-slate-400 uppercase mr-1">Pasta:</span>
                        <span class="text-slate-900 dark:text-slate-100 font-extrabold"><?= $category_counts[2] ?>/<?= $package_limits['pasta'] ?></span>
                    </div>
                    <div class="px-2.5 py-1 bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 rounded-lg text-[10px] font-bold">
                        <span class="text-slate-600 dark:text-slate-400 uppercase mr-1">Veggies:</span>
                        <span class="text-slate-900 dark:text-slate-100 font-extrabold"><?= $category_counts[4] ?>/<?= $package_limits['veggies'] ?></span>
                    </div>
                    <div class="px-2.5 py-1 bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 rounded-lg text-[10px] font-bold">
                        <span class="text-slate-600 dark:text-slate-400 uppercase mr-1">Dessert:</span>
                        <span class="text-slate-900 dark:text-slate-100 font-extrabold">
                            <?php if ($package_limits['dessert'] <= 0): ?>
                                N/A
                            <?php elseif ($package_limits['dessert'] >= 90): ?>
                                Unlimited
                            <?php else: ?>
                                <?= $category_counts[5] ?>/<?= $package_limits['dessert'] ?>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Itemized Table -->
            <div>
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-600 dark:text-slate-400 block mb-2">Order Itemization</span>
                <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-xl">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-100/80 dark:bg-slate-900/60 text-[9px] text-slate-600 dark:text-slate-400 uppercase font-extrabold border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="py-2.5 px-3">Item / Package</th>
                                <th class="py-2.5 px-3 text-center">Qty</th>
                                <th class="py-2.5 px-3 text-right">Unit Price</th>
                                <th class="py-2.5 px-3 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80 dark:divide-slate-800/60">
                            <?php if ($is_package_selected): ?>
                            <tr class="font-bold text-slate-900 dark:text-slate-100">
                                <td class="py-2.5 px-3 flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-purple-600"></span>
                                    <?= htmlspecialchars($package_name) ?>
                                </td>
                                <td class="py-2.5 px-3 text-center text-slate-600 dark:text-slate-400 font-bold"><?= number_format($booking['pax_count']) ?> Pax</td>
                                <td class="py-2.5 px-3 text-right text-slate-600 dark:text-slate-400 font-bold">₱<?= number_format($package_price_per_pax, 2) ?></td>
                                <td class="py-2.5 px-3 text-right font-black text-purple-700 dark:text-purple-400">₱<?= number_format($package_subtotal, 2) ?></td>
                            </tr>
                            <?php endif; ?>

                            <?php foreach ($selected_items_list as $row): ?>
                            <tr class="text-slate-800 dark:text-slate-300">
                                <td class="py-2.5 px-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 shrink-0 aspect-square rounded-lg overflow-hidden border border-slate-200 dark:border-slate-800 bg-slate-100 dark:bg-slate-800 flex items-center justify-center shadow-2xs">
                                            <?php if (!empty($row['image_path'])): ?>
                                                <img src="<?= htmlspecialchars($row['image_path']) ?>" alt="<?= htmlspecialchars($row['item_name']) ?>" class="w-full h-full aspect-square object-cover block">
                                            <?php else: ?>
                                                <span class="text-purple-600 dark:text-purple-400 font-bold text-xs uppercase"><?= substr($row['item_name'], 0, 1) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <span class="font-semibold text-slate-900 dark:text-slate-100 block truncate"><?= htmlspecialchars($row['item_name']) ?></span>
                                            <?php if (!empty($row['dietary_notes'])): ?>
                                                <div class="inline-flex items-center gap-1 text-[9px] font-bold text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/60 px-1.5 py-0.5 rounded border border-amber-200 dark:border-amber-800/40 mt-0.5">
                                                    <i class="bi bi-tag-fill text-[8px]"></i> <?= htmlspecialchars($row['dietary_notes']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-2.5 px-3 text-center text-slate-600 dark:text-slate-400 font-semibold"><?= $row['quantity'] ?></td>
                                <td class="py-2.5 px-3 text-right text-slate-600 dark:text-slate-400 font-medium">₱<?= number_format($row['unit_price'], 2) ?></td>
                                <td class="py-2.5 px-3 text-right font-bold text-slate-900 dark:text-slate-100">₱<?= number_format($row['subtotal'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Compact Payment Breakdown Card -->
            <div class="pt-3 border-t border-slate-200 dark:border-slate-800 space-y-2">
                <div class="flex justify-between items-center text-xs font-bold text-slate-600 dark:text-slate-400 px-1">
                    <span>Full Event Total</span>
                    <span class="font-black text-slate-900 dark:text-slate-100">₱<?= number_format($booking['total_amount'], 2) ?></span>
                </div>

                <div class="flex justify-between items-center p-3.5 bg-purple-50 dark:bg-purple-950/60 border border-purple-200 dark:border-purple-800/50 rounded-xl">
                    <div>
                        <p class="text-purple-800 dark:text-purple-300 font-extrabold uppercase text-[10px] tracking-wider">Required Downpayment (50%)</p>
                        <p class="text-[10px] text-purple-600 dark:text-slate-400 font-semibold">Due now to confirm reservation</p>
                    </div>
                    <p class="text-xl font-black text-purple-700 dark:text-purple-400 tracking-tight">₱<?= number_format($downpayment_required, 2) ?></p>
                </div>

                <div class="flex justify-between items-center text-xs text-slate-600 dark:text-slate-400 px-1">
                    <span>Remaining Balance</span>
                    <span class="font-bold text-slate-800 dark:text-slate-300">₱<?= number_format($remaining_balance, 2) ?></span>
                </div>
            </div>

        </div>

        <!-- Action Buttons (Hidden when in modal frame) -->
        <div class="flex flex-col sm:flex-row gap-2.5 sm:gap-3 no-modal pt-2">
            <a href="dashboard.php?booking_id=<?= $booking_id ?>" class="w-full sm:w-auto px-4 py-3.5 text-center bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 rounded-xl font-bold uppercase tracking-wider text-xs transition-all shadow-xs flex items-center justify-center gap-1.5 min-h-[44px]" title="Modify event date, venue, guest count, or category">
                <i class="bi bi-calendar-event text-purple-600 dark:text-purple-400"></i> Edit Details (Step 1)
            </a>
            <a href="menu.php?booking_id=<?= $booking_id ?>" class="w-full sm:flex-1 text-center bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700 py-3.5 rounded-xl font-extrabold uppercase tracking-wider text-xs transition-all shadow-xs flex items-center justify-center gap-1.5 min-h-[44px]">
                <i class="bi bi-arrow-left"></i> Edit Menu (Step 2)
            </a>
            <form action="payment.php" method="GET" class="w-full sm:flex-[1.5]">
                <input type="hidden" name="booking_id" value="<?= $booking_id ?>">
                <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white py-3.5 rounded-xl font-extrabold uppercase tracking-wider text-xs shadow-md shadow-purple-600/20 transition-all flex items-center justify-center gap-2 cursor-pointer min-h-[44px]">
                    Proceed to Payment <i class="bi bi-arrow-right"></i>
                </button>
            </form>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const isPackageSelected = <?= json_encode($is_package_selected) ?>;
            const packageLimits = <?= json_encode($package_limits) ?>;
            const categoryCounts = <?= json_encode($category_counts) ?>;

            if (isPackageSelected) {
                let recommendations = [];

                if (packageLimits.main > 0 && categoryCounts[1] < packageLimits.main) {
                    const remaining = packageLimits.main - categoryCounts[1];
                    recommendations.push(`You have ${remaining} remaining Main Course slot(s).`);
                }

                if (packageLimits.dessert > 0 && packageLimits.dessert < 90 && categoryCounts[5] < packageLimits.dessert) {
                    const remaining = packageLimits.dessert - categoryCounts[5];
                    recommendations.push(`You have ${remaining} remaining Dessert slot(s).`);
                }

                if (recommendations.length > 0) {
                    Swal.fire({
                        title: 'Recommendation',
                        html: 'You have unused slots in your package:<br><br>' + recommendations.join('<br>'),
                        icon: 'info',
                        confirmButtonColor: '#7c3aed',
                        customClass: { popup: 'rounded-2xl' }
                    });
                }
            }
        });
    </script>
</body>
</html>
