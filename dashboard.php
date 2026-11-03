<?php
require_once 'includes/auth_check.php';
require_once 'db_config.php';

// Fetch current user info
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'User';

// Check if we are editing an existing pending booking (via ?booking_id=XX or ?edit_id=XX)
$edit_booking = null;
$booking_param = intval($_GET['booking_id'] ?? ($_GET['edit_id'] ?? 0));
$is_auto_restored_draft = false;

if ($booking_param > 0) {
    $e_stmt = $conn->prepare("SELECT * FROM bookings WHERE booking_id = ? AND user_id = ? AND status = 'pending'");
    $e_stmt->bind_param("ii", $booking_param, $user_id);
    $e_stmt->execute();
    $edit_booking = $e_stmt->get_result()->fetch_assoc();
} elseif (!isset($_GET['new'])) {
    // If client accidentally navigated away or returned to booking without an ID,
    // automatically retrieve their unfinished pending booking!
    $latest_stmt = $conn->prepare("SELECT * FROM bookings WHERE user_id = ? AND status = 'pending' AND payment_status = 'unpaid' ORDER BY booking_id DESC LIMIT 1");
    $latest_stmt->bind_param("i", $user_id);
    $latest_stmt->execute();
    $edit_booking = $latest_stmt->get_result()->fetch_assoc();
    if ($edit_booking) {
        $is_auto_restored_draft = true;
    }
}

// 1. Fetch Unread Notifications Count
$notif_query = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0";
$stmt = $conn->prepare($notif_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notif_count = $stmt->get_result()->fetch_assoc()['total'];

// 1.1 Fetch Latest 5 Notifications for Dropdown
$notif_list_query = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
$stmt_list = $conn->prepare($notif_list_query);
$stmt_list->bind_param("i", $user_id);
$stmt_list->execute();
$notifications_res = $stmt_list->get_result();

// 2. Fetch Transaction History with Payment Proof Detection
$history_query = "SELECT b.*, 
                  (SELECT COUNT(*) FROM payments p WHERE p.booking_id = b.booking_id) as proof_submitted 
                  FROM bookings b 
                  WHERE b.user_id = ? 
                  ORDER BY b.created_at DESC";
$stmt3 = $conn->prepare($history_query);
$stmt3->bind_param("i", $user_id);
$stmt3->execute();
$history_res = $stmt3->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book an Event | JEMmerific</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
        body { font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: -0.01em; }
        .sidebar-link { transition: all 0.2s ease-in-out; }
        .sidebar-link:hover { background-color: #e5e7eb; color: #9b7cd1; padding-left: 1.5rem; }
        .active-link { background: linear-gradient(90deg, #d4cef3 0%, transparent 100%); color: #7c3aed; font-weight: 700; border-right: 4px solid #7c3aed; }
        /* Smooth scroll for the table area */
        .overflow-x-auto::-webkit-scrollbar { height: 6px; }
        .overflow-x-auto::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    </style>
</head>
<body class="bg-slate-50 dark:bg-[#0b0f19] transition-colors duration-300 min-h-screen text-slate-800 dark:text-slate-200 flex flex-col">

    <?php require_once 'includes/wizard_header.php'; ?>

    <main class="flex-1 max-w-5xl w-full mx-auto px-4 pb-16 transition-all">
        <header class="flex flex-col md:flex-row justify-between items-start gap-4 mb-8">
            <div>
                <span class="text-[10px] font-bold text-purple-600 dark:text-purple-300 uppercase tracking-widest bg-purple-50 dark:bg-purple-950/60 px-2.5 py-1 rounded-full border border-purple-100 dark:border-purple-800/50">Step 1 of 3</span>
                <h2 class="text-3xl font-extrabold text-slate-900 dark:text-slate-100 tracking-tight mt-2"><?= $edit_booking ? 'Edit Event Details' : 'Event Details & Venue' ?></h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 font-semibold mt-1"><?= $edit_booking ? 'Modify your event logistics below.' : 'Provide basic event details. Next step: Pick Menu & Packages.' ?></p>
            </div>
            <div id="autosave-indicator" class="text-xs font-bold text-slate-400"></div>
        </header>



        <!-- Step Progress Bar (Step 1: Event Details) -->
        <?php $active_step = 1; require_once 'includes/booking_steps.php'; ?>

        <?php if ($edit_booking): ?>
        <!-- Unfinished Draft Notification Card -->
        <div class="mb-6 p-4 md:p-5 bg-gradient-to-r from-purple-50 via-indigo-50/50 to-purple-50 dark:from-purple-950/70 dark:via-indigo-950/50 dark:to-purple-950/70 border border-purple-200 dark:border-purple-800/80 rounded-3xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-xs">
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-2xl bg-purple-600 text-white flex items-center justify-center text-xl shrink-0 shadow-md shadow-purple-600/30">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-[9px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-purple-600 text-white shadow-2xs">In Progress Draft</span>
                        <span class="text-xs font-black text-slate-900 dark:text-white">#BK-<?= str_pad($edit_booking['booking_id'], 4, '0', STR_PAD_LEFT) ?> • <?= htmlspecialchars($edit_booking['event_name'] ?: 'Untitled Event') ?></span>
                    </div>
                    <p class="text-[11px] text-slate-600 dark:text-slate-300 font-semibold mt-1">
                        <?= $is_auto_restored_draft ? 'We restored your in-progress booking details so you don\'t lose any information.' : 'Your saved event details have been loaded. You can modify them or proceed to Step 2.' ?>
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2.5 w-full sm:w-auto justify-end shrink-0">
                <a href="menu.php?booking_id=<?= $edit_booking['booking_id'] ?>" class="px-4 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-extrabold uppercase tracking-wider transition shadow-sm inline-flex items-center gap-1.5 whitespace-nowrap">
                    Proceed to Menu <i class="bi bi-arrow-right"></i>
                </a>
                <a href="dashboard.php?new=1" class="px-3.5 py-2.5 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold border border-slate-200 dark:border-slate-700 transition whitespace-nowrap" title="Start a fresh new booking">
                    <i class="bi bi-plus-lg"></i> New Booking
                </a>
            </div>
        </div>
        <?php endif; ?>

        <div class="w-full bg-white dark:bg-[#131b2e] p-4 sm:p-8 md:p-10 rounded-2xl sm:rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <form id="logistics-form" action="process_booking.php" method="POST" class="space-y-8">
                <input type="hidden" name="booking_id" id="booking_id_input" value="<?= $edit_booking['booking_id'] ?? '0' ?>">

                <div class="space-y-6">
                    <div class="flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-slate-800">
                        <span class="w-1.5 h-5 bg-purple-600 rounded-full"></span>
                        <h3 class="text-sm font-extrabold text-slate-900 dark:text-slate-100 uppercase tracking-wider">General Event Information</h3>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Event Title</label>
                            <input type="text" name="event_name" required value="<?= $edit_booking ? htmlspecialchars($edit_booking['event_name']) : '' ?>" placeholder="e.g. Santos Wedding Reception" 
                                   class="w-full px-4 py-3.5 bg-slate-50 dark:bg-slate-900/70 border border-slate-200 dark:border-slate-700 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:border-purple-600 focus:ring-2 focus:ring-purple-500/20 outline-none transition-all text-sm font-bold text-slate-900 dark:text-slate-100">
                        </div>
                        <div>
                            <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Event Category</label>
                            <select name="event_type" required 
                                     class="w-full px-4 py-3.5 bg-slate-50 dark:bg-slate-900/70 border border-slate-200 dark:border-slate-700 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:border-purple-600 focus:ring-2 focus:ring-purple-500/20 outline-none transition-all cursor-pointer text-sm font-bold text-slate-900 dark:text-slate-100">
                                <option value="" disabled <?= !$edit_booking ? 'selected' : '' ?>>Select event type</option>
                                <option value="Wedding" <?= ($edit_booking && strpos($edit_booking['event_type'], 'Wedding') !== false) ? 'selected' : '' ?>>Wedding Celebration</option>
                                <option value="Birthday" <?= ($edit_booking && strpos($edit_booking['event_type'], 'Birthday') !== false) ? 'selected' : '' ?>>Birthday Party</option>
                                <option value="Corporate" <?= ($edit_booking && strpos($edit_booking['event_type'], 'Corporate') !== false) ? 'selected' : '' ?>>Corporate Parties</option>
                                <option value="Others" <?= ($edit_booking && strpos($edit_booking['event_type'], 'Event Parties') !== false) ? 'selected' : '' ?>>Event Parties</option>
                                <option value="Others" <?= ($edit_booking && strpos($edit_booking['event_type'], 'Baptismal') !== false) ? 'selected' : '' ?>>Baptismal</option>
                                <option value="Others" <?= ($edit_booking && strpos($edit_booking['event_type'], 'Others') !== false) ? 'selected' : '' ?>>Others</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div>
                        <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Event Date</label>
                        <div class="relative cursor-pointer" onclick="openCustomerCalendarModal()">
                            <input type="text" readonly id="datePickerDisplay" 
                                   value="<?= $edit_booking ? date('F d, Y', strtotime($edit_booking['event_date'])) : '' ?>" 
                                   placeholder="Click to Pick Available Date" 
                                   onclick="openCustomerCalendarModal()" 
                                   onfocus="openCustomerCalendarModal()" 
                                   class="w-full px-4 py-3.5 bg-slate-50 dark:bg-slate-900/70 border border-slate-200 dark:border-slate-700 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:border-purple-600 outline-none text-sm font-bold text-slate-900 dark:text-slate-100 cursor-pointer pr-10">
                            <input type="hidden" name="event_date" id="selected_event_date" required value="<?= $edit_booking ? $edit_booking['event_date'] : '' ?>">
                            <i class="bi bi-calendar3 text-purple-600 text-base absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                        </div>
                        <!-- Live Selected Date Info Badge -->
                        <div id="selectedDateInfoCard" class="mt-2.5 p-2.5 bg-purple-50/80 dark:bg-purple-950/50 border border-purple-100 dark:border-purple-800/50 rounded-xl <?= $edit_booking ? '' : 'hidden' ?>">
                            <div class="flex items-center justify-between text-xs">
                                <div class="flex items-center gap-1.5">
                                    <span id="infoStatusDot" class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                                    <span id="infoStatusBadge" class="font-extrabold text-purple-900 dark:text-purple-200 text-[11px]">Available</span>
                                </div>
                                <span id="infoSlotsBadge" class="font-bold text-purple-700 dark:text-purple-300 text-[10px]">Slots Open</span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Start Time</label>
                        <input type="time" name="event_time" id="event_time" required value="<?= $edit_booking ? $edit_booking['event_time'] : '' ?>" 
                               class="w-full px-4 py-3.5 bg-slate-50 dark:bg-slate-900/70 border border-slate-200 dark:border-slate-700 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:border-purple-600 outline-none text-sm font-bold text-slate-900 dark:text-slate-100">
                    </div>
                    <div>
                        <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">End Time</label>
                        <input type="time" name="event_end_time" id="event_end_time" required value="<?= $edit_booking ? ($edit_booking['event_end_time'] ?? '') : '' ?>" 
                               class="w-full px-4 py-3.5 bg-slate-50 dark:bg-slate-900/70 border border-slate-200 dark:border-slate-700 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:border-purple-600 outline-none text-sm font-bold text-slate-900 dark:text-slate-100">
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300 uppercase tracking-wider">Guest Count (Pax)</label>
                            <button type="button" id="toggle-pax-mode-btn" class="text-[11px] font-bold text-purple-600 dark:text-purple-400 hover:text-purple-700 dark:hover:text-purple-300 hover:underline cursor-pointer flex items-center gap-1 transition">
                                <i class="bi bi-pencil-square" id="toggle-pax-icon"></i>
                                <span id="toggle-pax-text">Custom Pax</span>
                            </button>
                        </div>

                        <!-- Hidden form field storing submitted pax_count value -->
                        <input type="hidden" name="pax_count" id="pax-count-value" value="<?= $edit_booking ? $edit_booking['pax_count'] : '' ?>">

                        <!-- Preset Dropdown Mode (Full Width) -->
                        <div id="pax-preset-container" class="relative w-full">
                            <select id="pax-preset" class="w-full px-4 py-3.5 bg-slate-50 dark:bg-slate-900/70 border border-slate-200 dark:border-slate-700 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:border-purple-600 outline-none text-sm font-bold text-slate-900 dark:text-slate-100 cursor-pointer transition-all">
                                <option value="">Select Pax</option>
                                <option value="50">50 Pax</option>
                                <option value="100">100 Pax</option>
                                <option value="150">150 Pax</option>
                                <option value="200">200 Pax</option>
                                <option value="custom">✏️ Custom Pax...</option>
                            </select>
                        </div>

                        <!-- Custom Pax Input Mode (Full Width with integrated Presets button) -->
                        <div id="pax-custom-container" class="relative w-full hidden">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-purple-600 dark:text-purple-400">
                                <i class="bi bi-people-fill text-sm"></i>
                            </div>
                            <input type="number" id="pax-custom-input" min="1" max="3000" placeholder="Enter guest count (e.g. 75)"
                                   class="w-full pl-10 pr-24 py-3.5 bg-slate-50 dark:bg-slate-900/70 border border-slate-200 dark:border-slate-700 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:border-purple-600 outline-none text-sm font-bold text-slate-900 dark:text-slate-100 transition-all">
                            <button type="button" id="btn-back-to-presets" title="Switch back to preset options"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 px-2.5 py-1.5 bg-purple-100 hover:bg-purple-200 dark:bg-purple-950/70 dark:hover:bg-purple-900 border border-purple-200 dark:border-purple-800 text-purple-700 dark:text-purple-300 rounded-lg text-xs font-bold transition flex items-center gap-1 cursor-pointer">
                                <span>Presets</span>
                                <i class="bi bi-chevron-down text-[10px]"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Detailed Event Venue & Structured Address Section -->
                <div class="space-y-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-xs font-black text-slate-900 dark:text-slate-100 uppercase tracking-wider flex items-center gap-2">
                                <i class="bi bi-geo-alt-fill text-purple-600 dark:text-purple-400"></i> Event Place &amp; Complete Address
                            </h3>
                            <p class="text-[11px] text-slate-400 dark:text-slate-500 font-medium mt-0.5">Enter your exact venue details, street, and location below for precise catering logistics.</p>
                        </div>
                    </div>

                    <!-- Row 1: Street Address, Subdivision, & Venue Name -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">
                                House / Building No. &amp; Street <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" id="venue_street" placeholder="e.g. #55 Hockey St., Unit 4B" 
                                   class="w-full px-3.5 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700/80 rounded-xl focus:border-purple-600 outline-none text-xs font-semibold text-slate-900 dark:text-slate-100 shadow-none">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">
                                Subdivision / Village / Compound
                            </label>
                            <input type="text" id="venue_subdivision" placeholder="e.g. BF Homes Phase 2, Lopena Compound" 
                                   class="w-full px-3.5 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700/80 rounded-xl focus:border-purple-600 outline-none text-xs font-semibold text-slate-900 dark:text-slate-100 shadow-none">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">
                                Venue / Function Hall / Landmark
                            </label>
                            <input type="text" id="venue_specific" placeholder="e.g. Grand Ballroom, Lopena Clubhouse" 
                                   class="w-full px-3.5 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700/80 rounded-xl focus:border-purple-600 outline-none text-xs font-semibold text-slate-900 dark:text-slate-100 shadow-none">
                        </div>
                    </div>

                    <!-- Row 2: Cascading Region, City, Barangay & ZIP Code -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">
                                Region / Province <span class="text-rose-500">*</span>
                            </label>
                            <select id="venue_region" class="w-full px-3.5 py-2.5 bg-slate-50/70 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700/80 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:border-purple-600 outline-none text-xs font-semibold text-slate-900 dark:text-slate-100 cursor-pointer shadow-none">
                                <option value="">Select Region / Province</option>
                                <option value="NCR">Metro Manila (NCR)</option>
                                <option value="Cavite">Cavite</option>
                                <option value="Laguna">Laguna</option>
                                <option value="Batangas">Batangas</option>
                                <option value="Rizal">Rizal</option>
                                <option value="Bulacan">Bulacan</option>
                                <option value="Pampanga">Pampanga</option>
                                <option value="Cebu">Cebu</option>
                                <option value="Davao">Davao del Sur</option>
                                <option value="Other">Other Region / Province</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">
                                City / Municipality <span class="text-rose-500">*</span>
                            </label>
                            <select id="venue_city" disabled class="w-full px-3.5 py-2.5 bg-slate-50/70 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700/80 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:border-purple-600 outline-none text-xs font-semibold text-slate-900 dark:text-slate-100 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed shadow-none">
                                <option value="">Select City / Municipality</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">
                                Barangay / District <span class="text-rose-500">*</span>
                            </label>
                            <select id="venue_barangay" disabled class="w-full px-3.5 py-2.5 bg-slate-50/70 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700/80 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:border-purple-600 outline-none text-xs font-semibold text-slate-900 dark:text-slate-100 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed shadow-none">
                                <option value="">Select Barangay / District</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">
                                ZIP / Postal Code
                            </label>
                            <input type="text" id="venue_zip" placeholder="e.g. 1700" maxlength="10"
                                   class="w-full px-3.5 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700/80 rounded-xl focus:border-purple-600 outline-none text-xs font-semibold text-slate-900 dark:text-slate-100 shadow-none">
                        </div>
                    </div>

                    <!-- Hidden province element -->
                    <input type="hidden" id="venue_province" value="">

                    <!-- Custom Barangay / Subdivision Input -->
                    <div id="custom_barangay_wrapper" class="hidden">
                        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">
                            Specify Custom Barangay / Zone
                        </label>
                        <input type="text" id="custom_barangay_input" placeholder="e.g. Zone 4, Sitio Pag-asa" 
                               class="w-full px-3.5 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700/80 rounded-xl focus:border-purple-600 outline-none text-xs font-semibold text-slate-900 dark:text-slate-100 shadow-none">
                    </div>

                    <!-- Minimalist Live Compiled Venue Address Card -->
                    <div class="p-3 bg-slate-50/80 dark:bg-slate-900/50 border border-slate-200/80 dark:border-slate-800 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 shadow-none">
                        <div class="flex items-center gap-2 overflow-hidden flex-1">
                            <i class="bi bi-geo-alt-fill text-purple-600 dark:text-purple-400 text-xs shrink-0"></i>
                            <div class="min-w-0 flex-1">
                                <span class="block text-[9px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Live Compiled Event Address</span>
                                <input type="text" name="venue_location" id="venue_location_compiled" required 
                                       value="<?= $edit_booking ? htmlspecialchars($edit_booking['venue_location']) : '' ?>" 
                                       placeholder="Complete address will compile automatically as you fill out the fields above" 
                                       class="w-full bg-transparent border-0 p-0 text-xs font-bold text-slate-800 dark:text-slate-100 outline-none truncate focus:ring-0" readonly>
                            </div>
                        </div>
                        <button type="button" onclick="toggleManualAddressEdit()" id="toggleEditAddrBtn" class="text-[11px] font-bold text-purple-600 dark:text-purple-400 hover:text-purple-700 dark:hover:text-purple-300 transition shrink-0 cursor-pointer flex items-center gap-1 self-start sm:self-center shadow-none">
                            <i class="bi bi-pencil-square"></i> Edit Manually
                        </button>
                    </div>
                    <input type="hidden" name="venue_address" id="venue_address_hidden" value="<?= $edit_booking ? htmlspecialchars($edit_booking['venue_address'] ?? $edit_booking['venue_location']) : '' ?>">
                </div>

                <div class="pt-4 flex flex-col sm:flex-row justify-between items-center gap-3 sm:gap-4 border-t border-slate-100 dark:border-slate-800">
                    <a href="client_home.php" class="w-full sm:w-auto px-6 py-3.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold rounded-xl text-xs uppercase tracking-wider transition-all text-center flex items-center justify-center gap-1.5 min-h-[44px]">
                        <i class="bi bi-arrow-left"></i> Return to Portal
                    </a>
                    <button type="submit" name="action" value="save_and_continue"
                            class="w-full sm:w-auto px-8 py-3.5 bg-purple-600 hover:bg-purple-700 text-white font-extrabold rounded-xl shadow-md transition-all text-xs uppercase tracking-wider flex items-center justify-center gap-2 cursor-pointer min-h-[44px]">
                        <?= $edit_booking ? 'Update & Proceed to Menu' : 'Save Details & Proceed to Menu Selection' ?> <i class="bi bi-arrow-right text-base"></i>
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('msg') && urlParams.get('msg') === 'payment_submitted') {
            Swal.fire({
                title: 'Payment Received!',
                text: 'We are now verifying your transaction. Check the status below.',
                icon: 'success',
                confirmButtonColor: '#7c3aed'
            });
        }
        if (urlParams.has('msg') && urlParams.get('msg') === 'draft_saved') {
            Swal.fire({
                title: 'Draft Saved!',
                text: 'Your booking details have been saved as a draft. You can continue later from your transaction history.',
                icon: 'info',
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
                title: 'Delete this draft?',
                text: "All event logistics and menu selections will be removed.",
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Yes, delete it'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `auth/delete_booking.php?booking_id=${id}`;
                }
            });
        }

        // --- Streamlined Philippine Location Coverage (Accurate & Standardized) ---
        const phCoverage = {
            "NCR": {
                name: "Metro Manila (NCR)",
                province: "Metro Manila",
                cities: [
                    { city: "Parañaque City", zip: "1700", barangays: ["BF Homes", "San Antonio", "Sun Valley", "Don Bosco", "Moonwalk", "San Isidro", "San Dionisio", "Baclaran", "Tambo", "Sto. Niño", "Marcelo Green", "Merville", "Vitalez", "La Huerta", "Don Galo"] },
                    { city: "Taguig City (BGC)", zip: "1630", barangays: ["Fort Bonifacio (BGC)", "Western Bicutan", "Ususan", "Tuktukan", "Pinagsama", "Signal Village", "Bagumbayan", "Hagonoy", "Lower Bicutan", "Central Bicutan", "Calzada", "Ibayo-Tipas"] },
                    { city: "Makati City", zip: "1200", barangays: ["Bel-Air", "Poblacion", "San Lorenzo", "Bangkal", "Magallanes", "Pio del Pilar", "Palanan", "Guadalupe Nuevo", "Guadalupe Viejo", "Cembo", "Olympia", "San Antonio", "Tejeros"] },
                    { city: "Quezon City", zip: "1100", barangays: ["Diliman", "Cubao", "Commonwealth", "Batasan Hills", "Fairview", "Novaliches", "Loyola Heights", "New Manila", "South Triangle", "Kamuning", "Tandang Sora", "Project 6", "Project 8"] },
                    { city: "Manila", zip: "1000", barangays: ["Ermita", "Malate", "Intramuros", "Binondo", "Santa Cruz", "Sampaloc", "Paco", "Pandacan", "Santa Ana", "Tondo", "San Miguel", "San Andres", "Santa Mesa"] },
                    { city: "Pasig City", zip: "1600", barangays: ["Kapitolyo", "San Antonio", "Ugong", "Ortigas Center", "Rosario", "Maybunga", "Pinagbuhatan", "Caniogan", "Bagong Ilog", "Dela Paz", "Manggahan", "Santolan"] },
                    { city: "Mandaluyong City", zip: "1550", barangays: ["Highway Hills", "Wack-Wack Greenhills", "Plainview", "Barangka", "Mauway", "Addition Hills", "Pleasant Hills", "Hulo"] },
                    { city: "Muntinlupa City", zip: "1770", barangays: ["Alabang", "Ayala Alabang", "Putatan", "Poblacion", "Cupang", "Buli", "Sucat", "Tunasan", "Bayanan"] },
                    { city: "Las Piñas City", zip: "1740", barangays: ["Talon Uno", "Talon Dos", "Talon Tres", "Talon Cuatro", "Talon Singko", "Pamplona Uno", "Pamplona Dos", "Pamplona Tres", "Almanza Uno", "Almanza Dos", "Pulang Lupa Uno", "Pulang Lupa Dos", "Zapote", "Pilar Village", "Manuyo Uno", "Manuyo Dos", "Ilaya"] },
                    { city: "Pasay City", zip: "1300", barangays: ["Malibay", "Maricaban", "San Isidro", "San Jose", "San Roque", "Sto. Niño", "Villamor", "Bay City / MOA Area"] },
                    { city: "San Juan City", zip: "1500", barangays: ["Greenhills", "West Crame", "Addition Hills", "Little Baguio", "Batis", "Kabayanan", "Tibagan"] },
                    { city: "Marikina City", zip: "1800", barangays: ["Concepcion Uno", "Concepcion Dos", "Marikina Heights", "San Roque", "Industrial Valley", "Barangka", "Fortune", "Nangka", "Parang"] },
                    { city: "Caloocan City", zip: "1400", barangays: ["Grace Park", "Bagong Barrio", "Monumento", "Bagumbong", "Camarin", "Deparo", "Tala"] },
                    { city: "Malabon City", zip: "1470", barangays: ["Concepcion", "Potrero", "Tonsuya", "Flores", "Hulong Duhat", "Ibaba", "Muzon", "Niugan"] },
                    { city: "Navotas City", zip: "1485", barangays: ["Bagumbayan South", "Daanghari", "Navotas East", "Navotas West", "San Jose", "San Roque", "Tangos"] },
                    { city: "Valenzuela City", zip: "1440", barangays: ["Karuhatan", "Gen. T. de Leon", "Malinta", "Marulas", "Malanday", "Polo", "Arkong Bato"] },
                    { city: "Pateros", zip: "1620", barangays: ["Aguho", "Magtanggol", "Martires del 96", "Poblacion", "San Pedro", "San Roque", "Santa Ana"] }
                ]
            },
            "Cavite": {
                name: "Cavite (CALABARZON)",
                province: "Cavite",
                cities: [
                    { city: "Bacoor City", zip: "4102", barangays: ["Molino I-VII", "Habay I-II", "Panapaan", "Talaba", "Queens Row", "San Nicolas", "Mambog", "Salinas", "Niog"] },
                    { city: "Imus City", zip: "4103", barangays: ["Anabu", "Bucandala", "Malagasang", "Medicion", "Poblacion", "Tanzang Luma", "Bayan Luma", "Carsadang Bago"] },
                    { city: "Dasmariñas City", zip: "4114", barangays: ["Salitran", "San Agustin", "Burol", "Paliparan", "Sampaloc", "Fatima", "Sabang", "Langkaan"] },
                    { city: "Tagaytay City", zip: "4120", barangays: ["Mendez Crossing", "Silang Junction", "Maharlika", "Tagaytay Rotonda", "Kaybagal", "Sungay", "Iruhin", "Asisan"] },
                    { city: "General Trias City", zip: "4107", barangays: ["Manggahan", "San Francisco", "Bacao", "Pasong Camachile", "Tejero", "Poblacion"] },
                    { city: "Silang", zip: "4118", barangays: ["Biga", "Bulihan", "Balite", "Tubuan", "Poblacion", "Lalaan", "Tartaria"] },
                    { city: "Kawit", zip: "4104", barangays: ["Binakayan", "Gahak", "Marulas", "Poblacion", "San Sebastian", "Santa Isabel", "Tabon"] },
                    { city: "Trece Martires City", zip: "4109", barangays: ["De Ocampo", "Inocencio", "Lapidario", "Luciano", "Osorio", "Perez", "San Agustin"] },
                    { city: "Carmona", zip: "4116", barangays: ["Maduya", "Cabilang Baybay", "Mabuhay", "Milagrosa", "Poblacion"] },
                    { city: "Tanza", zip: "4108", barangays: ["Amaya", "Bagtas", "Biga", "Julugan", "Paradahan", "Poblacion"] },
                    { city: "Rosario", zip: "4106", barangays: ["Bagbag", "Kanluran", "Ligtong", "Muzon", "Poblacion", "Tejeros Convention"] }
                ]
            },
            "Laguna": {
                name: "Laguna (CALABARZON)",
                province: "Laguna",
                cities: [
                    { city: "Calamba City", zip: "4027", barangays: ["Real", "Canlubang", "Bucal", "Pansol", "Halang", "Turbina", "Mayapa", "Makiling", "Barandal", "Parian", "Poblacion", "Lecheria"] },
                    { city: "Santa Rosa City", zip: "4026", barangays: ["Don Jose", "Balibago", "Tagapo", "Macabling", "Dila", "Pulong Santa Cruz", "Malitlit", "Ibaba", "Kanluran"] },
                    { city: "Biñan City", zip: "4024", barangays: ["San Antonio", "De La Paz", "Canlalay", "Tubigan", "Platero", "Santo Tomas", "San Vicente", "Malaban"] },
                    { city: "Cabuyao City", zip: "4025", barangays: ["Banlic", "Bigaa", "Mamatid", "Niugan", "Poblacion", "Pulo", "Sala", "San Isidro"] },
                    { city: "San Pedro City", zip: "4023", barangays: ["Landayan", "Nueva", "Pacita Complex", "Poblacion", "San Antonio", "San Roque", "San Vicente"] },
                    { city: "Los Baños", zip: "4030", barangays: ["Batong Malake", "Bayog", "Lalawigan", "Mayondon", "Poblacion", "San Antonio"] },
                    { city: "San Pablo City", zip: "4000", barangays: ["Concepcion", "Del Remedio", "San Cristobal", "San Francisco", "San Gabriel", "San Jose", "Poblacion"] }
                ]
            },
            "Rizal": {
                name: "Rizal (CALABARZON)",
                province: "Rizal",
                cities: [
                    { city: "Antipolo City", zip: "1870", barangays: ["San Roque", "Dela Paz", "Beverly Hills", "Cupang", "Mayamot", "Mambugan", "Santa Cruz", "Dalig"] },
                    { city: "Cainta", zip: "1900", barangays: ["San Andres", "San Isidro", "San Juan", "San Roque", "Santa Rosa", "Santo Domingo"] },
                    { city: "Taytay", zip: "1920", barangays: ["Dolores", "Muzon", "San Isidro", "San Juan", "Santa Ana"] },
                    { city: "Angono", zip: "1930", barangays: ["Bagumbayan", "Kalayaan", "Mahabang Parang", "Poblacion", "San Pedro", "San Roque"] },
                    { city: "Binangonan", zip: "1940", barangays: ["Batingan", "Calumpang", "Darangan", "Layunan", "Libid", "Poblacion"] },
                    { city: "San Mateo", zip: "1850", barangays: ["Ampid", "Banaba", "Dulong Bayan", "Guitnang Bayan", "Malanday", "Silangan"] }
                ]
            },
            "Batangas": {
                name: "Batangas (CALABARZON)",
                province: "Batangas",
                cities: [
                    { city: "Batangas City", zip: "4200", barangays: ["Alangilan", "Bolbok", "Kumintang Ibaba", "Kumintang Ilaya", "Pallocan", "Poblacion", "Santa Rita"] },
                    { city: "Lipa City", zip: "4217", barangays: ["Balintawak", "Inosluban", "Marawoy", "Mataas Na Lupa", "Poblacion", "Sabang"] },
                    { city: "Tanauan City", zip: "4232", barangays: ["Darasa", "Natatas", "Poblacion", "Sambat", "Santor", "Trapiche"] },
                    { city: "Santo Tomas City", zip: "4234", barangays: ["San Antonio", "San Bartolome", "San Felix", "San Miguel", "San Pedro", "San Rafael"] },
                    { city: "Nasugbu", zip: "4231", barangays: ["Balaytigue", "Bilaran", "Bucana", "Poblacion", "Wawa"] }
                ]
            },
            "Bulacan": {
                name: "Bulacan (Central Luzon)",
                province: "Bulacan",
                cities: [
                    { city: "San Jose del Monte City", zip: "3023", barangays: ["Ciudad Real", "Kaypian", "Muzon", "Sapang Palay", "Tungkong Mangga", "Graceville"] },
                    { city: "Malolos City", zip: "3000", barangays: ["Bulihan", "Cofradia", "Dakila", "Guinhawa", "Longos", "Mojon", "Poblacion"] },
                    { city: "Meycauayan City", zip: "3020", barangays: ["Bancal", "Calvario", "Camalig", "Iba", "Langka", "Malhacan", "Perez", "Poblacion"] },
                    { city: "Santa Maria", zip: "3022", barangays: ["Bagbaguin", "Caypombo", "Guyong", "Poblacion", "Pulong Buhangin", "San Gabriel"] },
                    { city: "Marilao", zip: "3019", barangays: ["Abangan Norte", "Abangan Sur", "Ibayo", "Lias", "Patubig", "Poblacion", "Prenza"] }
                ]
            },
            "Pampanga": {
                name: "Pampanga (Central Luzon)",
                province: "Pampanga",
                cities: [
                    { city: "Angeles City", zip: "2009", barangays: ["Balibago", "Cutcut", "Malabanias", "Pandan", "Pulung Maragul", "Santo Rosario"] },
                    { city: "San Fernando City", zip: "2000", barangays: ["Dolores", "Sindalan", "San Agustin", "San Jose", "Telabastagan", "Quebiawan"] },
                    { city: "Mabalacat City", zip: "2010", barangays: ["Camachiles", "Dau", "Dolores", "Mabiga", "Poblacion", "San Francisco"] }
                ]
            },
            "Cebu": {
                name: "Cebu (Central Visayas)",
                province: "Cebu",
                cities: [
                    { city: "Cebu City", zip: "6000", barangays: ["Banilad", "Guadalupe", "Lahug", "Mabolo", "Poblacion"] },
                    { city: "Mandaue City", zip: "6014", barangays: ["Bakilid", "Centro", "Subangdaku", "Tipolo"] }
                ]
            },
            "Davao": {
                name: "Davao del Sur",
                province: "Davao del Sur",
                cities: [
                    { city: "Davao City", zip: "8000", barangays: ["Buhangin", "Matina", "Poblacion", "Talomo", "Toril"] }
                ]
            },
            "Other": {
                name: "Other Region / Province",
                province: "Philippines",
                cities: [
                    { city: "Other Philippine City / Municipality", zip: "", barangays: [] }
                ]
            }
        };

        let isManualAddressEdit = false;

        function toggleManualAddressEdit() {
            const input = document.getElementById('venue_location_compiled');
            const btn = document.getElementById('toggleEditAddrBtn');
            if (isManualAddressEdit) {
                isManualAddressEdit = false;
                input.readOnly = true;
                btn.innerHTML = '<i class="bi bi-pencil-square"></i> Edit Manually';
                btn.classList.remove('text-emerald-600', 'dark:text-emerald-400');
                btn.classList.add('text-purple-600', 'dark:text-purple-400');
                updateCompiledVenueAddress();
            } else {
                isManualAddressEdit = true;
                input.readOnly = false;
                input.focus();
                btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Auto-Sync Mode';
                btn.classList.remove('text-purple-600', 'dark:text-purple-400');
                btn.classList.add('text-emerald-600', 'dark:text-emerald-400');
            }
        }

        function updateCompiledVenueAddress() {
            if (isManualAddressEdit) return;

            const streetInput = document.getElementById('venue_street');
            const subdivInput = document.getElementById('venue_subdivision');
            const specificInput = document.getElementById('venue_specific');
            const regionSel = document.getElementById('venue_region');
            const provInput = document.getElementById('venue_province');
            const citySel = document.getElementById('venue_city');
            const brgySel = document.getElementById('venue_barangay');
            const customBrgyInput = document.getElementById('custom_barangay_input');
            const zipInput = document.getElementById('venue_zip');
            const compiledInput = document.getElementById('venue_location_compiled');
            const hiddenAddress = document.getElementById('venue_address_hidden');

            const specific = specificInput ? specificInput.value.trim() : '';
            const street = streetInput ? streetInput.value.trim() : '';
            const subdivision = subdivInput ? subdivInput.value.trim() : '';
            const brgy = (brgySel && brgySel.value === 'custom') ? (customBrgyInput ? customBrgyInput.value.trim() : '') : (brgySel ? brgySel.value : '');
            const city = citySel ? citySel.value : '';
            const regionKey = regionSel ? regionSel.value : '';
            const prov = regionKey && phCoverage[regionKey] ? phCoverage[regionKey].province : '';
            const zip = zipInput ? zipInput.value.trim() : '';

            if (provInput) provInput.value = prov;

            const parts = [];
            if (specific) parts.push(specific);
            if (street) parts.push(street);
            if (subdivision) parts.push(subdivision);
            if (brgy) parts.push(brgy.startsWith('Brgy') ? brgy : 'Brgy. ' + brgy);
            if (city) parts.push(city);
            if (prov && prov !== city) parts.push(prov);
            if (zip) parts.push(zip);

            const compiledText = parts.join(', ');
            if (compiledInput) compiledInput.value = compiledText;
            if (hiddenAddress) hiddenAddress.value = compiledText;
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Location address elements
            const streetInput = document.getElementById('venue_street');
            const subdivInput = document.getElementById('venue_subdivision');
            const specificInput = document.getElementById('venue_specific');
            const regionSel = document.getElementById('venue_region');
            const provInput = document.getElementById('venue_province');
            const citySel = document.getElementById('venue_city');
            const brgySel = document.getElementById('venue_barangay');
            const customBrgyWrapper = document.getElementById('custom_barangay_wrapper');
            const customBrgyInput = document.getElementById('custom_barangay_input');
            const zipInput = document.getElementById('venue_zip');
            const compiledInput = document.getElementById('venue_location_compiled');

            function populateCities(regKey, selectedCity = '', selectedZip = '') {
                citySel.innerHTML = '<option value="">Select City / Municipality</option>';
                brgySel.innerHTML = '<option value="">Select Barangay / District</option>';
                brgySel.disabled = true;
                if (customBrgyWrapper) customBrgyWrapper.classList.add('hidden');

                if (!regKey || !phCoverage[regKey]) {
                    citySel.disabled = true;
                    if (provInput) provInput.value = '';
                    if (zipInput && !selectedZip) zipInput.value = '';
                    return;
                }

                citySel.disabled = false;
                const cov = phCoverage[regKey];
                if (provInput) provInput.value = cov.province;

                cov.cities.forEach(cObj => {
                    const opt = document.createElement('option');
                    opt.value = cObj.city;
                    opt.textContent = cObj.city;
                    opt.dataset.zip = cObj.zip || '';
                    if (selectedCity && cObj.city.toLowerCase() === selectedCity.toLowerCase()) {
                        opt.selected = true;
                        if (zipInput && !selectedZip && cObj.zip) zipInput.value = cObj.zip;
                    }
                    citySel.appendChild(opt);
                });

                if (selectedZip && zipInput) {
                    zipInput.value = selectedZip;
                }
            }

            function populateBarangays(regKey, cityName, selectedBrgy = '') {
                brgySel.innerHTML = '<option value="">Select Barangay / District</option>';
                if (!cityName) {
                    brgySel.disabled = true;
                    if (customBrgyWrapper) customBrgyWrapper.classList.add('hidden');
                    return;
                }

                brgySel.disabled = false;
                const cov = phCoverage[regKey];
                if (!cov) return;

                const foundCity = cov.cities.find(c => c.city === cityName);
                if (foundCity) {
                    // Update zip if empty or matching previous default
                    if (foundCity.zip && zipInput && !zipInput.value) {
                        zipInput.value = foundCity.zip;
                    }

                    if (foundCity.barangays && foundCity.barangays.length > 0) {
                        foundCity.barangays.forEach(b => {
                            const opt = document.createElement('option');
                            opt.value = b;
                            opt.textContent = b;
                            if (selectedBrgy && b.toLowerCase() === selectedBrgy.toLowerCase()) {
                                opt.selected = true;
                            }
                            brgySel.appendChild(opt);
                        });
                    }
                }

                // Add Custom option
                const customOpt = document.createElement('option');
                customOpt.value = 'custom';
                customOpt.textContent = 'Other / Custom Barangay / Zone...';
                if (selectedBrgy && (!foundCity || !foundCity.barangays?.includes(selectedBrgy))) {
                    customOpt.selected = true;
                    if (customBrgyWrapper) {
                        customBrgyWrapper.classList.remove('hidden');
                        customBrgyInput.value = selectedBrgy;
                    }
                }
                brgySel.appendChild(customOpt);
            }

            // Region Change: Cascades to Cities
            regionSel.addEventListener('change', function() {
                populateCities(this.value);
                updateCompiledVenueAddress();
            });

            // City Change: Cascades to Barangays & Suggests ZIP Code
            citySel.addEventListener('change', function() {
                const selectedOpt = this.options[this.selectedIndex];
                if (selectedOpt && selectedOpt.dataset.zip && zipInput) {
                    zipInput.value = selectedOpt.dataset.zip;
                }
                populateBarangays(regionSel.value, this.value);
                updateCompiledVenueAddress();
            });

            // Barangay Change
            brgySel.addEventListener('change', function() {
                if (this.value === 'custom') {
                    if (customBrgyWrapper) {
                        customBrgyWrapper.classList.remove('hidden');
                        customBrgyInput.focus();
                    }
                } else {
                    if (customBrgyWrapper) {
                        customBrgyWrapper.classList.add('hidden');
                        customBrgyInput.value = '';
                    }
                }
                updateCompiledVenueAddress();
            });

            // Live updates from text fields
            if (streetInput) streetInput.addEventListener('input', updateCompiledVenueAddress);
            if (subdivInput) subdivInput.addEventListener('input', updateCompiledVenueAddress);
            if (specificInput) specificInput.addEventListener('input', updateCompiledVenueAddress);
            if (customBrgyInput) customBrgyInput.addEventListener('input', updateCompiledVenueAddress);
            if (zipInput) zipInput.addEventListener('input', updateCompiledVenueAddress);

            // Existing booking pre-population vs Fresh blank booking
            const existingVenue = '<?= $edit_booking ? addslashes($edit_booking["venue_location"]) : "" ?>';
            if (existingVenue) {
                let matched = false;
                Object.keys(phCoverage).forEach(rKey => {
                    phCoverage[rKey].cities.forEach(cObj => {
                        const cleanCity = cObj.city.replace(/ City/i, '').replace(/ \(.*\)/i, '').toLowerCase();
                        if (!matched && existingVenue.toLowerCase().includes(cleanCity)) {
                            regionSel.value = rKey;
                            populateCities(rKey, cObj.city, cObj.zip);
                            populateBarangays(rKey, cObj.city);
                            if (streetInput) streetInput.value = existingVenue;
                            matched = true;
                        }
                    });
                });
                if (!matched) {
                    if (streetInput) streetInput.value = existingVenue;
                }
                if (compiledInput) compiledInput.value = existingVenue;
            } else {
                // Fresh Booking: Region starts completely blank as requested by user
                regionSel.value = '';
                citySel.innerHTML = '<option value="">Select City / Municipality</option>';
                citySel.disabled = true;
                brgySel.innerHTML = '<option value="">Select Barangay / District</option>';
                brgySel.disabled = true;
            }

            // Pax Dropdown & Custom Input Logic (Full-width, No Overflow)
            const paxValueInput = document.getElementById('pax-count-value');
            const paxPresetContainer = document.getElementById('pax-preset-container');
            const paxCustomContainer = document.getElementById('pax-custom-container');
            const paxPreset = document.getElementById('pax-preset');
            const paxCustomInput = document.getElementById('pax-custom-input');
            const togglePaxBtn = document.getElementById('toggle-pax-mode-btn');
            const togglePaxText = document.getElementById('toggle-pax-text');
            const togglePaxIcon = document.getElementById('toggle-pax-icon');
            const btnBackToPresets = document.getElementById('btn-back-to-presets');
            const initialPax = '<?= $edit_booking ? $edit_booking["pax_count"] : "" ?>';

            function showCustomMode() {
                paxPresetContainer.classList.add('hidden');
                paxCustomContainer.classList.remove('hidden');
                togglePaxText.textContent = 'Use Presets';
                togglePaxIcon.className = 'bi bi-list-ul';
                setTimeout(() => paxCustomInput.focus(), 50);
            }

            function showPresetMode() {
                paxCustomContainer.classList.add('hidden');
                paxPresetContainer.classList.remove('hidden');
                togglePaxText.textContent = 'Custom Pax';
                togglePaxIcon.className = 'bi bi-pencil-square';
                if (paxPreset.value === 'custom') {
                    paxPreset.value = '';
                }
            }

            function initPax() {
                if (initialPax && initialPax !== '0') {
                    const parsed = parseInt(initialPax);
                    paxValueInput.value = parsed;
                    if ([50, 100, 150, 200].includes(parsed)) {
                        paxPreset.value = String(parsed);
                        showPresetMode();
                    } else {
                        paxCustomInput.value = parsed;
                        paxPreset.value = 'custom';
                        showCustomMode();
                    }
                } else {
                    showPresetMode();
                }
            }

            paxPreset.addEventListener('change', function() {
                if (this.value === 'custom') {
                    showCustomMode();
                } else if (this.value) {
                    paxValueInput.value = this.value;
                    paxCustomInput.value = this.value;
                    paxValueInput.dispatchEvent(new Event('input', { bubbles: true }));
                } else {
                    paxValueInput.value = '';
                    paxCustomInput.value = '';
                    paxValueInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });

            paxCustomInput.addEventListener('input', function() {
                paxValueInput.value = this.value;
                paxValueInput.dispatchEvent(new Event('input', { bubbles: true }));
            });

            togglePaxBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (paxPresetContainer.classList.contains('hidden')) {
                    showPresetMode();
                } else {
                    showCustomMode();
                }
            });

            btnBackToPresets.addEventListener('click', function(e) {
                e.preventDefault();
                showPresetMode();
            });

            initPax();

            // Original autosave code
            const form = document.getElementById('logistics-form');
            const indicator = document.getElementById('autosave-indicator');
            const bookingIdInput = document.getElementById('booking_id_input');
            let autoSaveTimer;

            function performAutoSave() {
                indicator.innerHTML = `<i class="bi bi-arrow-repeat animate-spin"></i> Auto-saving...`;
                const formData = new FormData(form);
                fetch('auth/auto_save_logistics.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        indicator.innerHTML = `<i class="bi bi-check2-circle text-green-500"></i> Last saved at ${data.time}`;
                        if (bookingIdInput.value === '0' || !bookingIdInput.value) {
                            bookingIdInput.value = data.booking_id;
                            try {
                                const newUrl = new URL(window.location.href);
                                if (!newUrl.searchParams.get('booking_id')) {
                                    newUrl.searchParams.set('booking_id', data.booking_id);
                                    window.history.replaceState({ path: newUrl.href }, '', newUrl.href);
                                }
                            } catch(e) {}
                        }
                    }
                })
                .catch(err => {
                    indicator.innerHTML = `<span class="text-red-400">Save failed</span>`;
                });
            }

            form.querySelectorAll('input, select, textarea').forEach(input => {
                const eventType = (input.tagName === 'SELECT') ? 'change' : 'input';
                input.addEventListener(eventType, () => {
                    clearTimeout(autoSaveTimer);
                    autoSaveTimer = setTimeout(performAutoSave, 1500);
                });
            });
        });
    </script>

    <!-- Customer Availability Calendar Selection Modal -->
    <div id="customerCalendarModal" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs hidden flex items-center justify-center p-4" onclick="closeCustomerCalendarModal()">
        <div class="bg-white dark:bg-[#131b2e] w-full max-w-xl rounded-3xl p-6 sm:p-8 shadow-2xl border border-slate-100 dark:border-slate-800 relative" onclick="event.stopPropagation()">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 mb-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-300 rounded-xl border border-purple-100 dark:border-purple-800/50">
                        <i class="bi bi-calendar-event text-lg"></i>
                    </div>
                    <div>
                        <h3 id="custCalendarMonthTitle" class="text-lg font-black text-slate-900 dark:text-slate-100">Select Event Date</h3>
                        <p class="text-xs text-slate-400 font-medium">Choose an available date for catering services</p>
                    </div>
                </div>
                <button onclick="closeCustomerCalendarModal()" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 p-1">
                    <i class="bi bi-x-lg text-lg"></i>
                </button>
            </div>

            <!-- Month Controls -->
            <div class="flex items-center justify-between mb-4">
                <button onclick="custPrevMonth()" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs rounded-xl flex items-center gap-1">
                    <i class="bi bi-chevron-left"></i> Prev
                </button>
                <span id="custMonthHeader" class="text-sm font-black text-slate-800 dark:text-slate-100"></span>
                <button onclick="custNextMonth()" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs rounded-xl flex items-center gap-1">
                    Next <i class="bi bi-chevron-right"></i>
                </button>
            </div>

            <!-- Color Legend -->
            <div class="flex items-center gap-2 flex-wrap mb-4 p-3 bg-slate-50/80 dark:bg-slate-900/60 rounded-2xl border border-slate-200/60 dark:border-slate-800 shadow-xs text-xs font-bold">
                <span class="text-slate-400 uppercase tracking-widest text-[10px] font-black px-1 flex items-center gap-1.5">
                    <i class="bi bi-info-circle text-slate-400"></i> Legend:
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-emerald-50/90 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200/80 dark:border-emerald-800/50 rounded-xl text-xs font-bold shadow-xs">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 shadow-sm"></span> Available
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-amber-50/90 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200/80 dark:border-amber-800/50 rounded-xl text-xs font-bold shadow-xs">
                    <span class="w-2 h-2 rounded-full bg-amber-500 shadow-sm"></span> Limited Slots
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-rose-50/90 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200/80 dark:border-rose-800/50 rounded-xl text-xs font-bold shadow-xs">
                    <span class="w-2 h-2 rounded-full bg-rose-500 shadow-sm"></span> Fully Booked
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-300/80 dark:border-slate-700 rounded-xl text-xs font-bold shadow-xs">
                    <span class="w-2 h-2 rounded-full bg-slate-600 shadow-sm"></span> Closed
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border border-purple-200/80 dark:border-purple-800/50 rounded-xl text-xs font-bold shadow-xs">
                    <span class="w-2 h-2 rounded-full bg-purple-600 shadow-sm"></span> Holiday
                </span>
            </div>

            <!-- Grid Header -->
            <div class="grid grid-cols-7 gap-1.5 mb-2 text-center text-[10px] font-black text-slate-400 uppercase tracking-wider">
                <div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div class="text-rose-500">Sat</div><div class="text-rose-500">Sun</div>
            </div>

            <!-- Calendar Grid -->
            <div id="custCalendarGrid" class="grid grid-cols-7 gap-1.5 max-h-72 overflow-y-auto pr-1">
                <!-- JS populated -->
            </div>

            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 text-right mt-4">
                <button onclick="closeCustomerCalendarModal()" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs rounded-xl">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        let custYear = new Date().getFullYear();
        let custMonth = new Date().getMonth() + 1;
        let custScheduleData = {};

        function openCustomerCalendarModal() {
            const modal = document.getElementById('customerCalendarModal');
            if (modal) {
                modal.classList.remove('hidden');
                document.getElementById('custCalendarGrid').innerHTML = '<div class="col-span-7 py-10 text-center text-xs font-bold text-purple-600"><i class="bi bi-arrow-repeat animate-spin text-2xl block mb-2"></i> Loading availability calendar...</div>';
                loadCustomerCalendar(custYear, custMonth);
            }
        }

        function closeCustomerCalendarModal() {
            const modal = document.getElementById('customerCalendarModal');
            if (modal) modal.classList.add('hidden');
        }

        async function loadCustomerCalendar(year, month) {
            try {
                const res = await fetch(`auth/availability_api.php?action=fetch_month&year=${year}&month=${month}`);
                const data = await res.json();
                if (!data.success) {
                    document.getElementById('custCalendarGrid').innerHTML = '<div class="col-span-7 py-6 text-center text-xs font-bold text-rose-500">Failed to load calendar. Please refresh page.</div>';
                    return;
                }

                custScheduleData = data.schedule;

                const monthName = new Date(year, month - 1, 1).toLocaleString('default', { month: 'long' });
                document.getElementById('custMonthHeader').textContent = `${monthName} ${year}`;

                renderCustCalendarGrid(year, month, data.schedule);
            } catch (err) {
                console.error("Error loading customer calendar:", err);
                document.getElementById('custCalendarGrid').innerHTML = '<div class="col-span-7 py-6 text-center text-xs font-bold text-rose-500">Network error loading calendar.</div>';
            }
        }

        function renderCustCalendarGrid(year, month, schedule) {
            const grid = document.getElementById('custCalendarGrid');
            grid.innerHTML = '';

            const firstDayIndex = new Date(year, month - 1, 1).getDay();
            let offset = firstDayIndex === 0 ? 6 : firstDayIndex - 1;

            for (let i = 0; i < offset; i++) {
                const empty = document.createElement('div');
                empty.className = 'bg-slate-50/50 rounded-xl min-h-[60px] opacity-30';
                grid.appendChild(empty);
            }

            const todayStr = new Date().toISOString().split('T')[0];

            Object.values(schedule).forEach(item => {
                const cell = document.createElement('div');
                const isPast = item.date < todayStr;
                const isSelectable = item.is_selectable && !isPast;

                let bgClass = 'bg-emerald-50 border-emerald-200 text-emerald-900 hover:bg-emerald-100';
                let statusLabel = 'Available';

                if (item.status === 'Limited Slots') {
                    bgClass = 'bg-amber-50 border-amber-200 text-amber-900 hover:bg-amber-100';
                    statusLabel = 'Limited';
                } else if (item.status === 'Fully Booked') {
                    bgClass = 'bg-rose-50 border-rose-200 text-rose-800 opacity-60 cursor-not-allowed';
                    statusLabel = 'Full';
                } else if (item.status === 'Closed') {
                    bgClass = 'bg-slate-100 border-slate-200 text-slate-500 opacity-60 cursor-not-allowed';
                    statusLabel = 'Closed';
                } else if (item.status === 'Holiday') {
                    bgClass = 'bg-purple-50 border-purple-200 text-purple-800 opacity-60 cursor-not-allowed';
                    statusLabel = 'Holiday';
                }

                if (isPast) {
                    bgClass = 'bg-slate-100 border-slate-200 text-slate-400 opacity-50 cursor-not-allowed';
                    statusLabel = 'Passed';
                }

                cell.className = `p-2 rounded-xl border text-center flex flex-col justify-between min-h-[65px] transition-all ${bgClass} ${isSelectable ? 'cursor-pointer active:scale-95 shadow-2xs' : ''}`;

                if (isSelectable) {
                    cell.onclick = () => selectDateForCustomer(item);
                } else {
                    cell.onclick = () => {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Date Unavailable',
                            text: `This date is ${item.status}. ${item.reason ? '(' + item.reason + ').' : ''} Please select an available date.`,
                            confirmColor: '#7c3aed'
                        });
                    };
                }

                cell.innerHTML = `
                    <div class="flex items-center justify-between text-[11px] font-black">
                        <span>${item.day}</span>
                        ${isSelectable ? '<i class="bi bi-check-circle-fill text-emerald-600 text-[10px]"></i>' : ''}
                    </div>
                    <div class="text-[9px] font-extrabold truncate mt-1 uppercase">${statusLabel}</div>
                    <div class="text-[8px] font-bold text-slate-500">${item.remaining_slots > 0 ? item.remaining_slots + ' left' : '0 left'}</div>
                `;

                grid.appendChild(cell);
            });
        }

        function custPrevMonth() {
            custMonth--;
            if (custMonth < 1) {
                custMonth = 12;
                custYear--;
            }
            loadCustomerCalendar(custYear, custMonth);
        }

        function custNextMonth() {
            custMonth++;
            if (custMonth > 12) {
                custMonth = 1;
                custYear++;
            }
            loadCustomerCalendar(custYear, custMonth);
        }

        function selectDateForCustomer(item) {
            document.getElementById('selected_event_date').value = item.date;

            const formatted = new Date(item.date + 'T00:00:00').toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
            const displayEl = document.getElementById('datePickerDisplay');
            if (displayEl) {
                displayEl.value = formatted;
                displayEl.textContent = formatted;
            }

            const card = document.getElementById('selectedDateInfoCard');
            const dot = document.getElementById('infoStatusDot');
            const badge = document.getElementById('infoStatusBadge');
            const slots = document.getElementById('infoSlotsBadge');

            card.classList.remove('hidden');
            badge.textContent = item.status;
            slots.textContent = `${item.remaining_slots} Slots Remaining`;

            if (item.status === 'Limited Slots') {
                dot.className = 'w-2.5 h-2.5 rounded-full bg-amber-500';
            } else {
                dot.className = 'w-2.5 h-2.5 rounded-full bg-emerald-500';
            }

            closeCustomerCalendarModal();

            // Trigger autosave dispatch
            const event = new Event('change');
            document.getElementById('selected_event_date').dispatchEvent(event);
        }

        function toggleNotifDropdown() {
            const dropdown = document.getElementById('notif-dropdown');
            const badge = document.getElementById('notif-badge');
            const isHidden = dropdown.classList.contains('hidden');
            dropdown.classList.toggle('hidden');
            if (isHidden && badge) {
                fetch('auth/mark_read.php')
                    .then(response => response.json())
                    .then(data => {
                        if(data.status === 'success') {
                            badge.classList.add('hidden');
                        }
                    });
            }
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
