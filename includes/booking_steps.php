<?php
// includes/booking_steps.php - Minimalist Step Process Bar for Booking Flow
$step = isset($active_step) ? $active_step : 1;
$curr_bid = 0;
if (!empty($booking_id)) {
    $curr_bid = intval($booking_id);
} elseif (!empty($_GET['booking_id'])) {
    $curr_bid = intval($_GET['booking_id']);
} elseif (!empty($_GET['edit_id'])) {
    $curr_bid = intval($_GET['edit_id']);
} elseif (!empty($edit_booking['booking_id'])) {
    $curr_bid = intval($edit_booking['booking_id']);
}
?>
<div class="mb-6 md:mb-8 p-3 sm:p-4 md:p-5 bg-white dark:bg-[#131b2e] rounded-2xl sm:rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
    <div class="flex items-center justify-between max-w-3xl mx-auto relative px-1 sm:px-2">
        
        <!-- Step 1: Event Details -->
        <a href="<?= $curr_bid > 0 ? 'dashboard.php?booking_id=' . $curr_bid : 'dashboard.php' ?>" class="flex flex-col md:flex-row items-center gap-1 sm:gap-1.5 md:gap-3 z-10 group text-center md:text-left" title="Return to Event Details (Step 1)">
            <div class="w-8 h-8 sm:w-9 sm:h-9 md:w-10 md:h-10 rounded-full flex items-center justify-center font-extrabold text-xs transition-all <?= $step >= 1 ? 'bg-purple-600 text-white shadow-md shadow-purple-600/30' : 'bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500' ?>">
                <?= $step > 1 ? '<i class="bi bi-check-lg text-sm sm:text-base"></i>' : '1' ?>
            </div>
            <div>
                <p class="text-[10px] md:text-xs font-extrabold tracking-tight <?= $step >= 1 ? 'text-slate-900 dark:text-slate-100' : 'text-slate-400 dark:text-slate-500' ?>">Step 1</p>
                <p class="text-[9px] md:text-[11px] font-semibold text-slate-500 dark:text-slate-400 whitespace-nowrap">Details</p>
            </div>
        </a>

        <!-- Progress Line 1 -> 2 -->
        <div class="flex-1 h-0.5 sm:h-1 mx-2 sm:mx-3 md:mx-6 rounded-full <?= $step >= 2 ? 'bg-purple-600' : 'bg-slate-200 dark:bg-slate-800' ?> transition-all"></div>

        <!-- Step 2: Choose Menu -->
        <a href="<?= $curr_bid > 0 ? 'menu.php?booking_id=' . $curr_bid : 'javascript:void(0)' ?>" class="flex flex-col md:flex-row items-center gap-1 sm:gap-1.5 md:gap-3 z-10 group text-center md:text-left" title="Go to Menu & Package Selection (Step 2)">
            <div class="w-8 h-8 sm:w-9 sm:h-9 md:w-10 md:h-10 rounded-full flex items-center justify-center font-extrabold text-xs transition-all <?= $step >= 2 ? 'bg-purple-600 text-white shadow-md shadow-purple-600/30' : ($step == 1 ? 'bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-300 border border-purple-200 dark:border-purple-800/60' : 'bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500') ?>">
                <?= $step > 2 ? '<i class="bi bi-check-lg text-sm sm:text-base"></i>' : '2' ?>
            </div>
            <div>
                <p class="text-[10px] md:text-xs font-extrabold tracking-tight <?= $step >= 2 ? 'text-slate-900 dark:text-slate-100' : 'text-slate-400 dark:text-slate-500' ?>">Step 2</p>
                <p class="text-[9px] md:text-[11px] font-semibold text-slate-500 dark:text-slate-400 whitespace-nowrap">Menu</p>
            </div>
        </a>

        <!-- Progress Line 2 -> 3 -->
        <div class="flex-1 h-0.5 sm:h-1 mx-2 sm:mx-3 md:mx-6 rounded-full <?= $step >= 3 ? 'bg-purple-600' : 'bg-slate-200 dark:bg-slate-800' ?> transition-all"></div>

        <!-- Step 3: Payment -->
        <a href="<?= $curr_bid > 0 ? 'summary.php?booking_id=' . $curr_bid : 'javascript:void(0)' ?>" class="flex flex-col md:flex-row items-center gap-1 sm:gap-1.5 md:gap-3 z-10 group text-center md:text-left" title="Go to Summary & Payment (Step 3)">
            <div class="w-8 h-8 sm:w-9 sm:h-9 md:w-10 md:h-10 rounded-full flex items-center justify-center font-extrabold text-xs transition-all <?= $step >= 3 ? 'bg-purple-600 text-white shadow-md shadow-purple-600/30' : ($step == 2 ? 'bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-300 border border-purple-200 dark:border-purple-800/60' : 'bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500') ?>">
                3
            </div>
            <div>
                <p class="text-[10px] md:text-xs font-extrabold tracking-tight <?= $step >= 3 ? 'text-slate-900 dark:text-slate-100' : 'text-slate-400 dark:text-slate-500' ?>">Step 3</p>
                <p class="text-[9px] md:text-[11px] font-semibold text-slate-500 dark:text-slate-400 whitespace-nowrap">Checkout</p>
            </div>
        </a>

    </div>
</div>
