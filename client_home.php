<?php 
require_once 'includes/auth_check.php'; 
require_once 'db_config.php';
require_once 'includes/booking_completion_helper.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'User';

// Auto-complete finished catering events and notify
checkAndCompleteFinishedBookings($conn, $user_id);

// 1. Fetch Unread Notifications Count
$notif_query = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0";
$stmt = $conn->prepare($notif_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notif_count = $stmt->get_result()->fetch_assoc()['total'];

// 1.1 Fetch Latest 5 Notifications
$notif_list_query = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
$stmt_list = $conn->prepare($notif_list_query);
$stmt_list->bind_param("i", $user_id);
$stmt_list->execute();
$notifications_res = $stmt_list->get_result();

// 1.2 Check if user is a new customer (0 bookings) or returning customer (>=1 bookings)
$b_cnt_stmt = $conn->prepare("SELECT COUNT(*) as total FROM bookings WHERE user_id = ?");
$b_cnt_stmt->bind_param("i", $user_id);
$b_cnt_stmt->execute();
$previous_bookings_cnt = intval($b_cnt_stmt->get_result()->fetch_assoc()['total'] ?? 0);

$greeting_prefix = ($previous_bookings_cnt > 0) ? "WELCOME BACK" : "WELCOME";

// 1.3 Check for any unfinished pending booking for this user
$unfin_stmt = $conn->prepare("SELECT b.*, 
    (SELECT COUNT(*) FROM booking_items bi WHERE bi.booking_id = b.booking_id) as items_count,
    (SELECT COUNT(*) FROM payments p WHERE p.booking_id = b.booking_id) as payments_count
    FROM bookings b 
    WHERE b.user_id = ? AND b.status = 'pending' AND b.payment_status = 'unpaid'
    ORDER BY b.booking_id DESC LIMIT 1");
$unfin_stmt->bind_param("i", $user_id);
$unfin_stmt->execute();
$unfin_row = $unfin_stmt->get_result()->fetch_assoc();

$unfinished_booking = null;
$resume_url = '';
$resume_step_label = '';
if ($unfin_row && intval($unfin_row['payments_count']) == 0) {
    $unfinished_booking = $unfin_row;
    if (intval($unfinished_booking['items_count']) == 0) {
        $resume_url = "menu.php?booking_id=" . $unfinished_booking['booking_id'];
        $resume_step_label = "Step 2: Menu & Package Selection";
    } else {
        $resume_url = "summary.php?booking_id=" . $unfinished_booking['booking_id'];
        $resume_step_label = "Step 3: Review & Payment Checkout";
    }
}

// 1.4 Fetch All Available Menu Items for "All Food Menu Available"
$all_menu_items = [];
$menu_categories = [];
$m_res = $conn->query("SELECT mi.*, mc.category_name 
                      FROM menu_items mi 
                      JOIN menu_categories mc ON mi.category_id = mc.category_id 
                      WHERE mi.is_available = 1 
                      ORDER BY mc.category_id ASC, mi.item_name ASC");
if ($m_res) {
    while ($m_row = $m_res->fetch_assoc()) {
        $all_menu_items[] = $m_row;
        if (!in_array($m_row['category_name'], $menu_categories)) {
            $menu_categories[] = $m_row['category_name'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | JEMmerific</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style> 
        body { font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: -0.01em; } 
        .sidebar-link { transition: all 0.2s ease-in-out; }
        .sidebar-link:hover { background-color: #e5e7eb; color: #9b7cd1; padding-left: 1.5rem; }
        .active-link { background: linear-gradient(90deg, #d4cef3 0%, transparent 100%); color: #7c3aed; font-weight: 700; border-right: 4px solid #7c3aed; }
    </style>
</head>
<body class="bg-[#FBFBFC] flex min-h-screen text-gray-800">

    <?php require_once 'includes/customer_sidebar.php'; ?>

    <main class="flex-1 lg:ml-64 p-4 md:p-6 transition-all">
        <header class="flex flex-col md:flex-row justify-between items-start gap-6 mb-8">
            <div class="flex items-start gap-4 max-w-full">
                <button onclick="toggleSidebar()" class="lg:hidden mt-1 p-2 bg-white border border-gray-100 rounded-xl shadow-sm text-gray-600 shrink-0">
                    <i class="bi bi-list text-2xl"></i>
                </button>
                <div class="max-w-full">
                    <!-- Clean Text Only Typing Greeting -->
                    <div class="mb-1.5 flex items-center">
                        <span id="welcomeTypingText" class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-[0.25em]"></span>
                        <span class="inline-block w-1.5 h-3.5 bg-slate-900 dark:bg-white animate-pulse rounded-2xs ml-1"></span>
                    </div>
                    <h2 class="text-3xl sm:text-4xl font-black text-gray-900 dark:text-white tracking-tight">Dashboard</h2>
                    <p class="text-xs text-gray-400 dark:text-slate-400 mt-1 font-medium italic">"Taste the magic, manage the moments."</p>
                </div>
            </div>
        </header>

        <?php if ($unfinished_booking): ?>
        <!-- Unfinished Booking Notification Banner -->
        <div class="mb-10 p-5 sm:p-6 bg-gradient-to-r from-amber-500/10 via-purple-500/10 to-indigo-500/10 dark:from-amber-950/40 dark:via-purple-950/40 dark:to-indigo-950/40 border-2 border-amber-400 dark:border-amber-600/80 rounded-3xl shadow-lg relative overflow-hidden backdrop-blur-md">
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
                            <span class="text-xs font-bold text-purple-700 dark:text-purple-300">
                                • Next: <?= $resume_step_label ?>
                            </span>
                        </div>
                        <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white tracking-tight">
                            <?= htmlspecialchars($unfinished_booking['event_name'] ?: 'Untitled Event') ?>
                        </h3>
                        <p class="text-xs text-slate-600 dark:text-slate-300 font-medium mt-1 flex items-center gap-3 flex-wrap">
                            <span><i class="bi bi-calendar-event text-purple-600 dark:text-purple-400 mr-1"></i><?= date('F d, Y', strtotime($unfinished_booking['event_date'])) ?></span>
                            <span><i class="bi bi-people-fill text-purple-600 dark:text-purple-400 mr-1"></i><?= $unfinished_booking['pax_count'] ?> Guests</span>
                            <span><i class="bi bi-geo-alt-fill text-purple-600 dark:text-purple-400 mr-1"></i><?= htmlspecialchars($unfinished_booking['venue_location'] ?: 'Venue pending') ?></span>
                        </p>
                    </div>
                </div>
                
                <div class="flex items-center gap-2.5 w-full md:w-auto justify-end flex-wrap">
                    <a href="<?= $resume_url ?>" class="w-full sm:w-auto px-6 py-3.5 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white font-black text-xs uppercase tracking-wider rounded-xl shadow-md shadow-purple-600/30 transition-all flex items-center justify-center gap-2">
                        <i class="bi bi-play-circle-fill text-sm"></i> Continue Booking
                    </a>
                    <a href="dashboard.php?booking_id=<?= $unfinished_booking['booking_id'] ?>" class="w-full sm:w-auto px-4 py-3.5 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700 font-bold text-xs uppercase tracking-wider rounded-xl transition-all flex items-center justify-center gap-1.5" title="Edit Step 1: Event Details">
                        <i class="bi bi-pencil-square"></i> Edit Step 1
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Main Action Banner -->
        <div class="mb-12 bg-gradient-to-r from-purple-900 via-gray-900 to-black text-white p-6 sm:p-10 md:p-14 rounded-3xl sm:rounded-[3rem] shadow-2xl relative overflow-hidden flex flex-col md:flex-row items-center justify-between gap-8">
            <div class="relative z-10 max-w-xl text-center md:text-left">
                <span class="text-[10px] font-black text-purple-400 uppercase tracking-[0.3em] mb-3 block">Seamless Event Catering</span>
                <h3 class="text-2xl sm:text-3xl md:text-4xl font-black tracking-tight mb-4">Plan &amp; Book Your Complete Catering Event</h3>
                <p class="text-gray-300 text-xs sm:text-sm font-medium leading-relaxed mb-6">Book your date, select your customized menu packages, and process payments all in one single, streamlined process.</p>
                
                <?php if ($unfinished_booking): ?>
                    <div class="flex items-center justify-center md:justify-start gap-4 flex-wrap">
                        <a href="<?= $resume_url ?>" class="w-full sm:w-auto inline-flex items-center justify-center gap-3 bg-purple-600 hover:bg-purple-500 text-white font-black text-xs uppercase tracking-widest px-8 py-4 rounded-2xl shadow-xl shadow-purple-600/30 transition-all hover:scale-105">
                            <i class="bi bi-play-circle-fill text-lg"></i> Continue Booking (#BK-<?= str_pad($unfinished_booking['booking_id'], 4, '0', STR_PAD_LEFT) ?>)
                        </a>
                        <a href="dashboard.php?new=1" class="text-xs text-purple-300 hover:text-white font-bold underline transition-colors">
                            Or Start a New Booking
                        </a>
                    </div>
                <?php else: ?>
                    <a href="dashboard.php" class="w-full sm:w-auto inline-flex items-center justify-center gap-3 bg-purple-600 hover:bg-purple-500 text-white font-black text-xs uppercase tracking-widest px-8 py-4 rounded-2xl shadow-xl shadow-purple-600/30 transition-all hover:scale-105">
                        <i class="bi bi-calendar-check-fill text-lg"></i> Start Event Booking
                    </a>
                <?php endif; ?>
            </div>
            <div class="w-full md:w-auto flex justify-center relative z-10">
                <div class="w-28 h-28 sm:w-32 sm:h-32 md:w-40 md:h-40 rounded-3xl bg-white/10 backdrop-blur-md border border-white/20 flex items-center justify-center text-purple-400 text-5xl sm:text-6xl shadow-2xl">
                    <i class="bi bi-magic"></i>
                </div>
            </div>
        </div>

        <!-- Services Section -->
        <div id="services" class="mb-16 scroll-mt-6">
            <div class="flex items-center gap-3 mb-6 sm:mb-8">
                <span class="w-1 h-6 bg-purple-600 rounded-full"></span>
                <h3 class="text-lg sm:text-xl font-black text-gray-900 uppercase tracking-widest">Our Premium Services</h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 sm:gap-8">
                <div class="bg-white p-6 sm:p-8 md:p-10 rounded-2xl sm:rounded-[3rem] shadow-sm border border-gray-100 hover:shadow-xl transition-all group">
                    <div class="w-14 h-14 bg-purple-50 rounded-2xl flex items-center justify-center text-purple-600 text-2xl mb-6 group-hover:bg-purple-600 group-hover:text-white transition-all">
                        <i class="bi bi-cup-straw"></i>
                    </div>
                    <h4 class="text-lg font-black mb-2 uppercase tracking-tight">Wedding Catering</h4>
                    <p class="text-sm text-gray-400 font-medium leading-relaxed">Elegant dining experiences curated for your most special day with personalized menus.</p>
                </div>
                <div class="bg-white p-6 sm:p-8 md:p-10 rounded-2xl sm:rounded-[3rem] shadow-sm border border-gray-100 hover:shadow-xl transition-all group">
                    <div class="w-14 h-14 bg-blue-50 rounded-2xl flex items-center justify-center text-blue-600 text-2xl mb-6 group-hover:bg-blue-600 group-hover:text-white transition-all">
                        <i class="bi bi-briefcase-fill"></i>
                    </div>
                    <h4 class="text-lg font-black mb-2 uppercase tracking-tight">Corporate Events</h4>
                    <p class="text-sm text-gray-400 font-medium leading-relaxed">Professional catering for seminars, meetings, and galas with seamless logistics.</p>
                </div>
                <div class="bg-white p-6 sm:p-8 md:p-10 rounded-2xl sm:rounded-[3rem] shadow-sm border border-gray-100 hover:shadow-xl transition-all group">
                    <div class="w-14 h-14 bg-pink-50 rounded-2xl flex items-center justify-center text-pink-600 text-2xl mb-6 group-hover:bg-pink-600 group-hover:text-white transition-all">
                        <i class="bi bi-balloon-heart-fill"></i>
                    </div>
                    <h4 class="text-lg font-black mb-2 uppercase tracking-tight">Social Parties</h4>
                    <p class="text-sm text-gray-400 font-medium leading-relaxed">Vibrant celebrations for birthdays and anniversaries with authentic Filipino flavors.</p>
                </div>
            </div>
        </div>

        <!-- Event Gallery Section -->
        <div id="gallery" class="mb-16 scroll-mt-6">
            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center gap-3">
                    <span class="w-1 h-6 bg-purple-600 rounded-full"></span>
                    <h3 class="text-xl font-black text-gray-900 uppercase tracking-widest">Event Gallery</h3>
                </div>
                <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Categorized Showcases</span>
            </div>
            <div id="gallery-grid" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                <!-- 1. Birthday Event -->
                <div class="relative group rounded-2xl overflow-hidden bg-gray-900 shadow-md cursor-pointer border border-gray-100 hover:shadow-xl transition-all duration-300" onclick="openLightbox(0)">
                    <img src="uploads/gallery/birthday.png" alt="Birthday Event Setup" class="w-full aspect-[4/3] object-cover group-hover:scale-105 transition-transform duration-500 opacity-90 group-hover:opacity-100">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/20 to-transparent p-4 flex flex-col justify-between">
                        <div class="flex justify-end">
                            <span class="px-2.5 py-0.5 bg-pink-500/80 backdrop-blur-md text-white text-[9px] font-black uppercase tracking-wider rounded-full shadow-2xs">
                                Birthday
                            </span>
                        </div>
                        <div>
                            <p class="text-[9px] font-black text-pink-300 uppercase tracking-widest mb-0.5">Celebration</p>
                            <h4 class="text-xs sm:text-sm font-black text-white leading-tight truncate">Birthday Event Setup</h4>
                        </div>
                    </div>
                </div>

                <!-- 2. Wedding Event -->
                <div class="relative group rounded-2xl overflow-hidden bg-gray-900 shadow-md cursor-pointer border border-gray-100 hover:shadow-xl transition-all duration-300" onclick="openLightbox(1)">
                    <img src="uploads/gallery/wedding.png" alt="Wedding Reception Setup" class="w-full aspect-[4/3] object-cover group-hover:scale-105 transition-transform duration-500 opacity-90 group-hover:opacity-100">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/20 to-transparent p-4 flex flex-col justify-between">
                        <div class="flex justify-end">
                            <span class="px-2.5 py-0.5 bg-purple-500/80 backdrop-blur-md text-white text-[9px] font-black uppercase tracking-wider rounded-full shadow-2xs">
                                Wedding
                            </span>
                        </div>
                        <div>
                            <p class="text-[9px] font-black text-purple-300 uppercase tracking-widest mb-0.5">Luxe Banquet</p>
                            <h4 class="text-xs sm:text-sm font-black text-white leading-tight truncate">Wedding Event Setup</h4>
                        </div>
                    </div>
                </div>

                <!-- 3. Corporate Event -->
                <div class="relative group rounded-2xl overflow-hidden bg-gray-900 shadow-md cursor-pointer border border-gray-100 hover:shadow-xl transition-all duration-300" onclick="openLightbox(2)">
                    <img src="uploads/gallery/corporate.png" alt="Corporate Event Setup" class="w-full aspect-[4/3] object-cover group-hover:scale-105 transition-transform duration-500 opacity-90 group-hover:opacity-100">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/20 to-transparent p-4 flex flex-col justify-between">
                        <div class="flex justify-end">
                            <span class="px-2.5 py-0.5 bg-blue-500/80 backdrop-blur-md text-white text-[9px] font-black uppercase tracking-wider rounded-full shadow-2xs">
                                Corporate
                            </span>
                        </div>
                        <div>
                            <p class="text-[9px] font-black text-blue-300 uppercase tracking-widest mb-0.5">Executive</p>
                            <h4 class="text-xs sm:text-sm font-black text-white leading-tight truncate">Corporate Event Setup</h4>
                        </div>
                    </div>
                </div>

                <!-- 4. Special Occasion -->
                <div class="relative group rounded-2xl overflow-hidden bg-gray-900 shadow-md cursor-pointer border border-gray-100 hover:shadow-xl transition-all duration-300" onclick="openLightbox(3)">
                    <img src="uploads/gallery/special_occasion.png" alt="Special Occasion Catering Setup" class="w-full aspect-[4/3] object-cover group-hover:scale-105 transition-transform duration-500 opacity-90 group-hover:opacity-100">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/20 to-transparent p-4 flex flex-col justify-between">
                        <div class="flex justify-end">
                            <span class="px-2.5 py-0.5 bg-amber-500/80 backdrop-blur-md text-white text-[9px] font-black uppercase tracking-wider rounded-full shadow-2xs">
                                Special Occasion
                            </span>
                        </div>
                        <div>
                            <p class="text-[9px] font-black text-amber-300 uppercase tracking-widest mb-0.5">Festive Party</p>
                            <h4 class="text-xs sm:text-sm font-black text-white leading-tight truncate">Special Occasion Setup</h4>
                        </div>
                    </div>
                </div>

                <!-- 5. Starry Chandelier Ballroom -->
                <div class="relative group rounded-2xl overflow-hidden bg-gray-900 shadow-md cursor-pointer border border-gray-100 hover:shadow-xl transition-all duration-300" onclick="openLightbox(4)">
                    <img src="uploads/gallery/starry_blue_setup.jpg" alt="Starry Chandelier Ballroom Setup" class="w-full aspect-[4/3] object-cover group-hover:scale-105 transition-transform duration-500 opacity-90 group-hover:opacity-100">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/20 to-transparent p-4 flex flex-col justify-between">
                        <div class="flex justify-end">
                            <span class="px-2.5 py-0.5 bg-indigo-500/80 backdrop-blur-md text-white text-[9px] font-black uppercase tracking-wider rounded-full shadow-2xs">
                                Starry Banquet
                            </span>
                        </div>
                        <div>
                            <p class="text-[9px] font-black text-indigo-300 uppercase tracking-widest mb-0.5">Celestial Elegance</p>
                            <h4 class="text-xs sm:text-sm font-black text-white leading-tight truncate">Starry Chandelier Ballroom</h4>
                        </div>
                    </div>
                </div>

                <!-- 6. Filipiniana Grand Courtyard -->
                <div class="relative group rounded-2xl overflow-hidden bg-gray-900 shadow-md cursor-pointer border border-gray-100 hover:shadow-xl transition-all duration-300" onclick="openLightbox(5)">
                    <img src="uploads/gallery/filipiniana_courtyard_setup.jpg" alt="Filipiniana Grand Courtyard Setup" class="w-full aspect-[4/3] object-cover group-hover:scale-105 transition-transform duration-500 opacity-90 group-hover:opacity-100">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/20 to-transparent p-4 flex flex-col justify-between">
                        <div class="flex justify-end">
                            <span class="px-2.5 py-0.5 bg-emerald-500/80 backdrop-blur-md text-white text-[9px] font-black uppercase tracking-wider rounded-full shadow-2xs">
                                Heritage Venue
                            </span>
                        </div>
                        <div>
                            <p class="text-[9px] font-black text-emerald-300 uppercase tracking-widest mb-0.5">Traditional Luxe</p>
                            <h4 class="text-xs sm:text-sm font-black text-white leading-tight truncate">Filipiniana Courtyard</h4>
                        </div>
                    </div>
                </div>

                <!-- 7. Emerald Pavilion & Arch -->
                <div class="relative group rounded-2xl overflow-hidden bg-gray-900 shadow-md cursor-pointer border border-gray-100 hover:shadow-xl transition-all duration-300" onclick="openLightbox(6)">
                    <img src="uploads/gallery/emerald_white_arch_setup.jpg" alt="Emerald Pavilion & Floral Arch Setup" class="w-full aspect-[4/3] object-cover group-hover:scale-105 transition-transform duration-500 opacity-90 group-hover:opacity-100">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/20 to-transparent p-4 flex flex-col justify-between">
                        <div class="flex justify-end">
                            <span class="px-2.5 py-0.5 bg-teal-500/80 backdrop-blur-md text-white text-[9px] font-black uppercase tracking-wider rounded-full shadow-2xs">
                                Floral Arch
                            </span>
                        </div>
                        <div>
                            <p class="text-[9px] font-black text-teal-300 uppercase tracking-widest mb-0.5">White & Emerald</p>
                            <h4 class="text-xs sm:text-sm font-black text-white leading-tight truncate">Emerald Pavilion</h4>
                        </div>
                    </div>
                </div>

                <!-- 8. Enchanted Purple Canopy -->
                <div class="relative group rounded-2xl overflow-hidden bg-gray-900 shadow-md cursor-pointer border border-gray-100 hover:shadow-xl transition-all duration-300" onclick="openLightbox(7)">
                    <img src="uploads/gallery/violet_floral_ceiling_setup.jpg" alt="Enchanted Purple Canopy Setup" class="w-full aspect-[4/3] object-cover group-hover:scale-105 transition-transform duration-500 opacity-90 group-hover:opacity-100">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/20 to-transparent p-4 flex flex-col justify-between">
                        <div class="flex justify-end">
                            <span class="px-2.5 py-0.5 bg-purple-600/80 backdrop-blur-md text-white text-[9px] font-black uppercase tracking-wider rounded-full shadow-2xs">
                                VIP Reception
                            </span>
                        </div>
                        <div>
                            <p class="text-[9px] font-black text-purple-300 uppercase tracking-widest mb-0.5">Romantic Canopy</p>
                            <h4 class="text-xs sm:text-sm font-black text-white leading-tight truncate">Purple Canopy Setup</h4>
                        </div>
                    </div>
                </div>

                <!-- 9. Grand Red Carpet Ballroom -->
                <div class="relative group rounded-2xl overflow-hidden bg-gray-900 shadow-md cursor-pointer border border-gray-100 hover:shadow-xl transition-all duration-300" onclick="openLightbox(8)">
                    <img src="uploads/gallery/grand_red_carpet_hall_setup.jpg" alt="Grand Red Carpet Ballroom Setup" class="w-full aspect-[4/3] object-cover group-hover:scale-105 transition-transform duration-500 opacity-90 group-hover:opacity-100">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/20 to-transparent p-4 flex flex-col justify-between">
                        <div class="flex justify-end">
                            <span class="px-2.5 py-0.5 bg-rose-500/80 backdrop-blur-md text-white text-[9px] font-black uppercase tracking-wider rounded-full shadow-2xs">
                                Grand Debut
                            </span>
                        </div>
                        <div>
                            <p class="text-[9px] font-black text-rose-300 uppercase tracking-widest mb-0.5">Royal Celebration</p>
                            <h4 class="text-xs sm:text-sm font-black text-white leading-tight truncate">Red Carpet Ballroom</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- All Food Menu Available Section -->
        <div id="food-menu" class="mb-16 scroll-mt-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8 pb-4 border-b border-gray-100 dark:border-slate-800">
                <div class="flex items-center gap-3">
                    <span class="w-1.5 h-7 bg-emerald-600 rounded-full"></span>
                    <div>
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <h3 class="text-xl font-black text-gray-900 dark:text-white uppercase tracking-widest">All Food Menu Available</h3>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-100 dark:bg-emerald-950/80 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                <?= count($all_menu_items) ?> Dishes Available
                            </span>
                        </div>
                        <p class="text-xs text-gray-400 dark:text-slate-400 font-semibold mt-0.5">Explore our handcrafted catering dishes available for your celebration.</p>
                    </div>
                </div>

                <!-- Category Filter Pills -->
                <div class="flex items-center gap-1.5 flex-wrap">
                    <button type="button" onclick="filterFoodMenu('all', this)" class="food-filter-btn px-3.5 py-1.5 rounded-full text-xs font-bold transition-all bg-purple-600 text-white shadow-xs cursor-pointer">
                        All (<?= count($all_menu_items) ?>)
                    </button>
                    <?php foreach ($menu_categories as $cat): ?>
                        <button type="button" onclick="filterFoodMenu('<?= htmlspecialchars($cat) ?>', this)" class="food-filter-btn px-3.5 py-1.5 rounded-full text-xs font-bold transition-all bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 cursor-pointer">
                            <?= htmlspecialchars($cat) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Dishes Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6" id="foodMenuGrid">
                <?php foreach ($all_menu_items as $dish): ?>
                    <div class="food-card bg-white dark:bg-[#131b2e] rounded-3xl p-4 border border-gray-100 dark:border-slate-800 shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col justify-between group" data-category="<?= htmlspecialchars($dish['category_name']) ?>">
                        <!-- Dish Image -->
                        <div class="aspect-square w-full rounded-2xl overflow-hidden bg-slate-100 dark:bg-slate-800 relative mb-3.5 shadow-2xs">
                            <?php if (!empty($dish['image_path']) && file_exists($dish['image_path'])): ?>
                                <img src="<?= htmlspecialchars($dish['image_path']) ?>" alt="<?= htmlspecialchars($dish['item_name']) ?>" class="w-full h-full aspect-square object-cover group-hover:scale-105 transition-transform duration-500">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center bg-purple-50 dark:bg-purple-950/40 text-purple-600 text-3xl">
                                    <i class="bi bi-egg-fried"></i>
                                </div>
                            <?php endif; ?>
                            <span class="absolute top-2.5 right-2.5 px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-black/70 backdrop-blur-md text-white border border-white/20">
                                <?= htmlspecialchars($dish['category_name']) ?>
                            </span>
                        </div>
                        
                        <!-- Details -->
                        <div class="flex-1 flex flex-col justify-between">
                            <div>
                                <h4 class="font-black text-sm text-slate-900 dark:text-white group-hover:text-purple-600 dark:group-hover:text-purple-400 transition-colors line-clamp-1">
                                    <?= htmlspecialchars($dish['item_name']) ?>
                                </h4>
                                <?php if (!empty($dish['description'])): ?>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium line-clamp-2 mt-1">
                                        <?= htmlspecialchars($dish['description']) ?>
                                    </p>
                                <?php else: ?>
                                    <p class="text-xs text-slate-400 dark:text-slate-500 italic mt-1">
                                        Handcrafted specialty catering dish
                                    </p>
                                <?php endif; ?>
                            </div>

                            <div class="pt-3 mt-3 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between">
                                <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                                    <i class="bi bi-check-circle-fill"></i> Available
                                </span>
                                <a href="dashboard.php" class="px-3 py-1 bg-purple-50 dark:bg-purple-950/60 hover:bg-purple-600 dark:hover:bg-purple-600 text-purple-700 dark:text-purple-300 hover:text-white dark:hover:text-white rounded-xl text-[10px] font-black uppercase tracking-wider transition-all inline-flex items-center gap-1">
                                    Book Event <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <script>
        function filterFoodMenu(category, clickedBtn) {
            const buttons = document.querySelectorAll('.food-filter-btn');
            buttons.forEach(btn => {
                btn.classList.remove('bg-purple-600', 'text-white', 'shadow-xs');
                btn.classList.add('bg-slate-100', 'dark:bg-slate-800', 'text-slate-600', 'dark:text-slate-300');
            });
            if (clickedBtn) {
                clickedBtn.classList.add('bg-purple-600', 'text-white', 'shadow-xs');
                clickedBtn.classList.remove('bg-slate-100', 'dark:bg-slate-800', 'text-slate-600', 'dark:text-slate-300');
            }

            const cards = document.querySelectorAll('.food-card');
            cards.forEach(card => {
                if (category === 'all' || card.getAttribute('data-category') === category) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }
    </script>

    <!-- Image Lightbox Overlay -->
    <div id="imageLightbox" onclick="closeLightbox()" class="fixed inset-0 z-[200] hidden bg-black/90 backdrop-blur-xl flex items-center justify-center p-4 md:p-12 transition-all duration-300 opacity-0">
        <button onclick="closeLightbox()" class="absolute top-8 right-8 text-white/70 hover:text-white text-4xl z-[210]">
            <i class="bi bi-x-lg"></i>
        </button>

        <button onclick="event.stopPropagation(); changeImage(-1)" class="absolute left-4 md:left-8 text-white/50 hover:text-white text-5xl transition-colors z-[210]">
            <i class="bi bi-chevron-left"></i>
        </button>
        <div class="flex flex-col items-center">
            <img id="lightboxImage" src="" onclick="event.stopPropagation()" class="max-w-[85vw] max-h-[75vh] md:max-w-full md:max-h-[80vh] rounded-2xl shadow-2xl transform scale-95 transition-all duration-300">
            <p id="lightboxCaption" class="mt-8 px-8 py-3 bg-white/10 backdrop-blur-md border border-white/20 rounded-full text-white text-[10px] font-black uppercase tracking-[0.3em] opacity-0 transition-opacity duration-300"></p>
        </div>
        <button onclick="event.stopPropagation(); changeImage(1)" class="absolute right-4 md:right-8 text-white/50 hover:text-white text-5xl transition-colors z-[210]">
            <i class="bi bi-chevron-right"></i>
        </button>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }

        let currentImgIndex = 0;
        let galleryData = [];

        function openLightbox(index) {
            const lightbox = document.getElementById('imageLightbox');
            const img = document.getElementById('lightboxImage');
            const caption = document.getElementById('lightboxCaption');
            
            if (galleryData.length === 0) {
                galleryData = Array.from(document.querySelectorAll('#gallery-grid img')).map(el => ({
                    src: el.src,
                    title: el.alt || "JEMmerific Event"
                }));
            }

            currentImgIndex = index;
            img.src = galleryData[currentImgIndex].src;
            caption.innerText = galleryData[currentImgIndex].title;
            
            lightbox.classList.remove('hidden');
            setTimeout(() => {
                lightbox.classList.remove('opacity-0');
                caption.classList.remove('opacity-0');
                img.classList.remove('scale-95');
            }, 10);
            document.body.style.overflow = 'hidden';
        }

        function changeImage(direction) {
            const img = document.getElementById('lightboxImage');
            const caption = document.getElementById('lightboxCaption');

            if (galleryData.length === 0) {
                galleryData = Array.from(document.querySelectorAll('#gallery-grid img')).map(el => ({
                    src: el.src,
                    title: el.alt || "JEMmerific Event"
                }));
            }

            currentImgIndex = (currentImgIndex + direction + galleryData.length) % galleryData.length;
            
            img.classList.add('opacity-0', 'scale-95');
            caption.classList.add('opacity-0');
            setTimeout(() => {
                img.src = galleryData[currentImgIndex].src;
                caption.innerText = galleryData[currentImgIndex].title;
                img.classList.remove('opacity-0', 'scale-95');
                caption.classList.remove('opacity-0');
            }, 200);
        }

        document.addEventListener('keydown', (e) => {
            const lightbox = document.getElementById('imageLightbox');
            if (lightbox.classList.contains('hidden')) return;
            
            if (e.key === 'ArrowLeft') changeImage(-1);
            if (e.key === 'ArrowRight') changeImage(1);
            if (e.key === 'Escape') closeLightbox();
        });

        function closeLightbox() {
            const lightbox = document.getElementById('imageLightbox');
            const img = document.getElementById('lightboxImage');
            lightbox.classList.add('opacity-0');
            img.classList.add('scale-95');
            setTimeout(() => {
                lightbox.classList.add('hidden');
            }, 300);
            document.body.style.overflow = 'auto'; // Re-enable scrolling
        }

        document.addEventListener('DOMContentLoaded', () => {
            const welcomeText = "<?= htmlspecialchars(addslashes($greeting_prefix)) ?>, <?= htmlspecialchars(addslashes(strtoupper($user_name))) ?>!";
            const textContainer = document.getElementById('welcomeTypingText');
            if (textContainer) {
                textContainer.textContent = '';
                let idx = 0;
                function typeLetter() {
                    if (idx < welcomeText.length) {
                        textContainer.textContent += welcomeText.charAt(idx);
                        idx++;
                        setTimeout(typeLetter, 70);
                    }
                }
                typeLetter();
            }
        });
    </script>
</body>
</html>
