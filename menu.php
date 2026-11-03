<?php
require_once 'includes/auth_check.php';
require_once 'db_config.php';

// Fetch current user info
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'User';

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

$category = isset($_GET['cat']) ? $_GET['cat'] : 'All';
$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

// --- Fetch existing selections and dietary notes ---
$saved_items = [];
$saved_item_dietary = [];
$selected_package_id = null;
$booking_data_for_progress = null;

if ($booking_id > 0) {
    // Fetch quantities & dietary notes from booking_items
    $saved_query = $conn->prepare("SELECT item_id, quantity, dietary_notes FROM booking_items WHERE booking_id = ?");
    $saved_query->bind_param("i", $booking_id);
    $saved_query->execute();
    $saved_res = $saved_query->get_result();
    while ($s_row = $saved_res->fetch_assoc()) {
        $saved_items[$s_row['item_id']] = $s_row['quantity'];
        if (!empty($s_row['dietary_notes'])) {
            $saved_item_dietary[$s_row['item_id']] = $s_row['dietary_notes'];
        }
    }

    // Fetch selected package from event_type string
    $pkg_check = $conn->prepare("SELECT event_type FROM bookings WHERE booking_id = ?");
    $pkg_check->bind_param("i", $booking_id);
    $pkg_check->execute();
    $b_data = $pkg_check->get_result()->fetch_assoc();
    if ($b_data && preg_match('/\(Package\s([A-D])\)/', $b_data['event_type'], $matches)) {
        $selected_package_id = $matches[1];
    }
}

// Fetch all available menu items for client course-by-course selection
$sql = "SELECT mi.*, mc.category_name 
        FROM menu_items mi 
        JOIN menu_categories mc ON mi.category_id = mc.category_id 
        WHERE mi.is_available = 1 
        ORDER BY mi.category_id ASC, mi.item_name ASC";
$menu_items = $conn->query($sql);

$cat_res = $conn->query("SELECT category_name FROM menu_categories ORDER BY category_id ASC");
$nav_categories = ['Main Course', 'Veggies', 'Pasta', 'Dessert'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Selection | JEMmerific</title>
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
        .category-pill { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .modal { transition: opacity 0.25s ease; }
        body.modal-active { overflow: hidden; }
    </style>
</head>
<body class="bg-slate-50 dark:bg-[#0b0f19] transition-colors duration-300 min-h-screen text-slate-800 dark:text-slate-200 flex flex-col">

    <?php require_once 'includes/wizard_header.php'; ?>

    <main class="flex-1 max-w-6xl w-full mx-auto px-4 pb-36 sm:pb-32 transition-all">
        <div class="mb-3">
            <a href="dashboard.php?booking_id=<?= $booking_id ?>" class="inline-flex items-center gap-1.5 text-xs font-extrabold text-purple-600 dark:text-purple-400 hover:text-purple-700 dark:hover:text-purple-300 transition-colors">
                <i class="bi bi-arrow-left"></i> Edit Event Details (Step 1)
            </a>
        </div>
        <header class="flex flex-col md:flex-row justify-between items-start gap-4 mb-8">
            <div>
                <span class="text-[10px] font-bold text-purple-600 dark:text-purple-300 uppercase tracking-widest bg-purple-50 dark:bg-purple-950/60 px-2.5 py-1 rounded-full border border-purple-100 dark:border-purple-800/50">Step 2 of 3</span>
                <h2 class="text-3xl font-extrabold text-slate-900 dark:text-slate-100 tracking-tight mt-2">Pick Package &amp; Menu Selection</h2>
                <p class="text-xs text-slate-500 font-semibold mt-1">Select your base catering package and customize your dish selection.</p>
            </div>
            <div class="text-right">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Booking Ref</p>
                <p class="text-sm font-extrabold text-purple-600">#BK-<?= str_pad($booking_id, 4, '0', STR_PAD_LEFT) ?></p>
            </div>
        </header>



        <!-- Step Progress Bar (Step 2: Choose Menu) -->
        <?php $active_step = 2; require_once 'includes/booking_steps.php'; ?>

        <form action="process_menu_selection.php" method="POST">
            <input type="hidden" name="booking_id" value="<?= $booking_id ?>">
            
            <!-- Package Selection Section -->
            <div class="mb-12">
                <div class="flex items-center gap-2 mb-6">
                    <span class="w-1 h-4 bg-purple-500 rounded-full"></span>
                    <h3 class="text-xs font-black text-gray-900 uppercase tracking-widest">Select a Base Package (Per Pax)</h3>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <?php
                    $pkg_res = $conn->query("SELECT * FROM packages ORDER BY package_id ASC");
                    
                    // Fetch all pricing tiers for JS lookup
                    $tiers_res = $conn->query("SELECT * FROM package_pricing_tiers");
                    $price_tiers = [];
                    while($t = $tiers_res->fetch_assoc()) {
                        $price_tiers[$t['package_id']][$t['pax_threshold']] = (float)$t['package_price'];
                    }

                    while ($pkg = $pkg_res->fetch_assoc()):
                    ?>
                    <label class="relative group cursor-pointer">
                        <input type="radio" name="selected_package" value="<?= $pkg['package_id'] ?>" <?= ($selected_package_id == $pkg['package_id']) ? 'checked' : '' ?> data-rate="<?= $pkg['price_per_pax'] ?>" data-limit="<?= $pkg['main_course_limit'] ?? 0 ?>" data-pasta-limit="<?= $pkg['pasta_limit'] ?? 0 ?>" data-veggies-limit="<?= $pkg['veggies_limit'] ?? 0 ?>" data-dessert-limit="<?= $pkg['dessert_limit'] ?? 0 ?>" class="package-radio peer sr-only">
                        <div class="p-6 bg-white dark:bg-[#131b2e] border-2 border-gray-100 dark:border-slate-800 rounded-[2rem] peer-checked:border-purple-600 dark:peer-checked:border-purple-500 peer-checked:bg-purple-50/80 dark:peer-checked:bg-purple-950/70 peer-checked:ring-4 peer-checked:ring-purple-500/20 peer-checked:shadow-xl peer-checked:shadow-purple-500/10 peer-checked:scale-[1.02] peer-checked:[&_.pkg-badge]:bg-purple-600 peer-checked:[&_.pkg-badge]:text-white transition-all duration-300 shadow-sm group-hover:shadow-md">
                            <div class="flex justify-between items-start mb-4">
                                <div class="pkg-badge w-10 h-10 rounded-xl bg-gray-900 dark:bg-slate-800 flex items-center justify-center text-white font-black text-sm transition-colors">
                                    <?= $pkg['package_id'] ?>
                                </div>
                                <div class="text-right">
                                    <p class="text-[10px] font-black text-gray-400 dark:text-slate-400 uppercase">Rate/HEAD</p>
                                    <p class="text-sm font-black text-purple-600 dark:text-purple-400">₱<?= number_format($pkg['price_per_pax'], 2) ?></p>
                                </div>
                            </div>
                            <h4 class="font-black text-gray-900 dark:text-slate-100 text-sm mb-1"><?= htmlspecialchars($pkg['package_name'], ENT_QUOTES, 'UTF-8') ?></h4>
                            <p class="text-[10px] text-gray-400 dark:text-slate-400 font-medium leading-tight"><?= htmlspecialchars($pkg['description'], ENT_QUOTES, 'UTF-8') ?></p>
                            <button type="button" data-name="<?= htmlspecialchars($pkg['package_name'], ENT_QUOTES, 'UTF-8') ?>" data-inclusions="<?= htmlspecialchars($pkg['inclusions'] ?? '', ENT_QUOTES, 'UTF-8') ?>" onclick="openPackageInclusionsModal(event, this)" class="mt-3 text-[9px] font-black text-purple-600 dark:text-purple-400 hover:text-purple-800 dark:hover:text-purple-300 uppercase tracking-widest block transition-colors"><i class="bi bi-info-circle-fill mr-1"></i> View Free</button>
                            <div class="mt-4 flex items-center gap-1.5 opacity-0 peer-checked:opacity-100 transition-all transform translate-y-1 peer-checked:translate-y-0">
                                <span class="px-3 py-1 bg-purple-600 text-white rounded-full text-[10px] font-black uppercase tracking-wider flex items-center gap-1 shadow-md shadow-purple-600/30">
                                    <i class="bi bi-check-lg text-xs font-black"></i> Selected Package
                                </span>
                            </div>
                        </div>
                    </label>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- Step-by-Step Course Selection Stepper -->
            <div class="mb-8 p-4 md:p-5 bg-white dark:bg-[#131b2e] rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800/80 mb-4 gap-2">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-purple-600 animate-pulse"></span>
                        <h3 class="text-xs font-black text-slate-900 dark:text-slate-100 uppercase tracking-widest">Select Menu Courses Sequentially</h3>
                    </div>
                    <div class="flex items-center gap-2">
                        <span id="activeCourseGuidance" class="text-xs font-extrabold text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-950/60 px-3 py-1 rounded-full border border-purple-100 dark:border-purple-800/50">Course 1 of 4: Pick Main Course</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <!-- Course 1: Main Course -->
                    <button type="button" onclick="switchCourseCategory(1)" id="course-tab-1" class="course-step-tab p-4 rounded-2xl border-2 transition-all text-left cursor-pointer bg-purple-50 dark:bg-purple-950/70 border-purple-600 text-purple-900 dark:text-purple-100 shadow-sm relative overflow-hidden">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-[9px] font-black uppercase tracking-wider text-purple-600 dark:text-purple-300">Course 1</span>
                            <span id="course-check-1" class="hidden text-emerald-500 font-extrabold text-sm"><i class="bi bi-check-circle-fill"></i></span>
                        </div>
                        <p class="text-xs font-black tracking-tight flex items-center gap-1.5">
                            <i class="bi bi-fire text-amber-500"></i> Main Course
                        </p>
                        <p id="course-count-1" class="text-[10px] font-black text-purple-700 dark:text-purple-300 mt-1">0/1 Selected</p>
                    </button>

                    <!-- Course 2: Veggies -->
                    <button type="button" onclick="switchCourseCategory(4)" id="course-tab-4" class="course-step-tab p-4 rounded-2xl border-2 transition-all text-left cursor-pointer bg-white dark:bg-[#131b2e] border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:border-purple-300 relative overflow-hidden">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-[9px] font-black uppercase tracking-wider text-slate-400">Course 2</span>
                            <span id="course-check-4" class="hidden text-emerald-500 font-extrabold text-sm"><i class="bi bi-check-circle-fill"></i></span>
                        </div>
                        <p class="text-xs font-black tracking-tight flex items-center gap-1.5">
                            <i class="bi bi-flower1 text-emerald-500"></i> Veggies
                        </p>
                        <p id="course-count-4" class="text-[10px] font-black text-slate-400 mt-1">0/1 Selected</p>
                    </button>

                    <!-- Course 3: Pasta -->
                    <button type="button" onclick="switchCourseCategory(2)" id="course-tab-2" class="course-step-tab p-4 rounded-2xl border-2 transition-all text-left cursor-pointer bg-white dark:bg-[#131b2e] border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:border-purple-300 relative overflow-hidden">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-[9px] font-black uppercase tracking-wider text-slate-400">Course 3</span>
                            <span id="course-check-2" class="hidden text-emerald-500 font-extrabold text-sm"><i class="bi bi-check-circle-fill"></i></span>
                        </div>
                        <p class="text-xs font-black tracking-tight flex items-center gap-1.5">
                            <i class="bi bi-bezier2 text-orange-500"></i> Pasta
                        </p>
                        <p id="course-count-2" class="text-[10px] font-black text-slate-400 mt-1">0/1 Selected</p>
                    </button>

                    <!-- Course 4: Dessert -->
                    <button type="button" onclick="switchCourseCategory(5)" id="course-tab-5" class="course-step-tab p-4 rounded-2xl border-2 transition-all text-left cursor-pointer bg-white dark:bg-[#131b2e] border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:border-purple-300 relative overflow-hidden">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-[9px] font-black uppercase tracking-wider text-slate-400">Course 4</span>
                            <span id="course-check-5" class="hidden text-emerald-500 font-extrabold text-sm"><i class="bi bi-check-circle-fill"></i></span>
                        </div>
                        <p class="text-xs font-black tracking-tight flex items-center gap-1.5">
                            <i class="bi bi-cake2-fill text-pink-500"></i> Dessert
                        </p>
                        <p id="course-count-5" class="text-[10px] font-black text-slate-400 mt-1">0/1 Selected</p>
                    </button>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mb-8" id="menu-search-container">
                <div class="relative flex-1 w-full">
                    <span class="absolute left-5 top-1/2 -translate-y-1/2 text-slate-400">
                        <i class="bi bi-search text-base"></i>
                    </span>
                    <input type="text" id="dish-search" placeholder="Search dishes in current course..." 
                           class="w-full pl-14 pr-10 py-3.5 bg-white dark:bg-[#131b2e] border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xs outline-none focus:border-purple-600 focus:ring-2 focus:ring-purple-500/20 transition text-xs font-bold text-slate-900 dark:text-slate-100">
                </div>
                
                <div class="flex items-center gap-2 text-xs font-bold text-slate-500 dark:text-slate-400">
                    <span id="course-indicator-chip" class="px-3 py-1.5 bg-purple-100 dark:bg-purple-950/80 text-purple-700 dark:text-purple-300 rounded-xl font-extrabold uppercase text-[10px] tracking-wider">
                        Showing: Main Course
                    </span>
                    <button type="button" onclick="showAllCoursesToggle()" id="toggleAllCoursesBtn" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl font-bold uppercase text-[10px] tracking-wider transition cursor-pointer">
                        View All
                    </button>
                </div>
            </div>

            <div id="dishes-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-32">
                <?php while($dish = $menu_items->fetch_assoc()): ?>
                <div class="dish-card bg-white dark:bg-[#131b2e] rounded-3xl shadow-sm border border-slate-200/80 dark:border-slate-800 overflow-hidden group hover:shadow-xl hover:shadow-purple-500/10 hover:-translate-y-1 transition-all duration-300 flex flex-col" data-dish-category="<?= $dish['category_id'] ?>" data-dish-name="<?= htmlspecialchars($dish['item_name']) ?>">
                    <div class="relative h-56 overflow-hidden bg-slate-100 dark:bg-slate-800">
                        <img src="<?= !empty($dish['image_path']) ? $dish['image_path'] : 'assets/img/placeholder-dish.jpg' ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                        <div class="absolute top-4 left-4">
                            <span class="bg-white/95 dark:bg-slate-900/90 backdrop-blur-md text-slate-900 dark:text-slate-100 text-[10px] font-black px-3 py-1.5 rounded-xl uppercase tracking-wider shadow-xs border border-slate-100 dark:border-slate-800">
                                <?= htmlspecialchars($dish['category_name']) ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="p-6 md:p-7 flex-1 flex flex-col justify-between">
                        <div>
                            <div class="flex justify-between items-start mb-2">
                                <h3 class="text-xl font-black text-slate-900 dark:text-slate-100 tracking-tight"><?= htmlspecialchars($dish['item_name']) ?></h3>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed font-semibold mb-4 min-h-[36px]">
                                <?= htmlspecialchars($dish['description']) ?>
                            </p>

                            <!-- View Recipe & Dietary Notes Buttons (Placed Side-by-Side) -->
                            <div class="flex items-center gap-3 flex-wrap my-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                                <?php if(!empty($dish['recipe'])): ?>
                                <button type="button" onclick='openRecipeModal(<?= json_encode($dish["item_name"]) ?>, <?= json_encode($dish["recipe"]) ?>)' class="text-[10px] font-black text-purple-600 dark:text-purple-400 hover:text-purple-700 dark:hover:text-purple-300 uppercase tracking-widest transition flex items-center gap-1 cursor-pointer">
                                    <i class="bi bi-journal-text text-xs"></i> View Recipe
                                </button>
                                <?php endif; ?>
                                
                                <button type="button" onclick='openDietaryModal(<?= $dish["item_id"] ?>, <?= htmlspecialchars(json_encode($dish["item_name"])) ?>)' class="text-[10px] font-black text-amber-600 dark:text-amber-400 hover:text-amber-700 dark:hover:text-amber-300 uppercase tracking-widest transition flex items-center gap-1.5 cursor-pointer">
                                    <i class="bi bi-shield-exclamation text-xs"></i> Dietary Notes 
                                    <span id="badge-dietary-<?= $dish['item_id'] ?>" class="<?= !empty($saved_item_dietary[$dish['item_id']]) ? '' : 'hidden' ?> px-1.5 py-0.5 bg-amber-500 text-white rounded-md text-[8px] font-black uppercase tracking-wider">Note Added</span>
                                </button>
                            </div>

                            <!-- Live Note Preview Badge -->
                            <div id="preview-dietary-<?= $dish['item_id'] ?>" class="<?= !empty($saved_item_dietary[$dish['item_id']]) ? '' : 'hidden' ?> mb-3 p-2.5 bg-amber-50/90 dark:bg-amber-950/50 border border-amber-200 dark:border-amber-800/60 rounded-xl text-[11px] text-amber-900 dark:text-amber-300 font-semibold flex items-start gap-1.5">
                                <i class="bi bi-chat-square-text-fill text-amber-600 mt-0.5 shrink-0 text-xs"></i>
                                <span id="text-dietary-preview-<?= $dish['item_id'] ?>" class="truncate"><?= htmlspecialchars($saved_item_dietary[$dish['item_id']] ?? '') ?></span>
                            </div>
                        </div>
                        
                        <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                            <?php $current_qty = isset($saved_items[$dish['item_id']]) ? $saved_items[$dish['item_id']] : 0; ?>
                            <input type="hidden" name="items[<?= $dish['item_id'] ?>][qty]" value="<?= $current_qty > 0 ? 1 : 0 ?>" data-price="<?= $dish['price'] ?>" data-category="<?= $dish['category_id'] ?>" class="dish-qty" id="qty-input-<?= $dish['item_id'] ?>">
                            <input type="hidden" name="items[<?= $dish['item_id'] ?>][price]" value="<?= $dish['price'] ?>">
                            <input type="hidden" name="items[<?= $dish['item_id'] ?>][dietary_notes]" id="dietary-input-<?= $dish['item_id'] ?>" value="<?= htmlspecialchars($saved_item_dietary[$dish['item_id']] ?? '') ?>" class="dish-dietary-input">
                            
                            <button type="button" onclick="toggleDishSelection(<?= $dish['item_id'] ?>)" id="btn-select-<?= $dish['item_id'] ?>" class="w-full py-3.5 rounded-2xl font-black text-xs uppercase tracking-widest transition-all <?= $current_qty > 0 ? 'bg-purple-600 text-white shadow-lg shadow-purple-600/30 ring-2 ring-purple-600/40' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-purple-50 dark:hover:bg-purple-950/60 hover:text-purple-600' ?> cursor-pointer flex items-center justify-center gap-2">
                                <i class="bi <?= $current_qty > 0 ? 'bi-check-circle-fill' : 'bi-plus-circle' ?>"></i>
                                <span id="text-select-<?= $dish['item_id'] ?>"><?= $current_qty > 0 ? 'Selected' : 'Select Dish' ?></span>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>

            <!-- Floating Selected Counter -->
            <div id="floating-counter" onclick="scrollToSummary()" class="fixed bottom-32 right-8 z-[60] hidden transition-all duration-500 transform translate-y-4 opacity-0 cursor-pointer hover:scale-105 active:scale-95">
                <div class="bg-gray-900/90 backdrop-blur-xl text-white px-6 py-4 rounded-[2rem] shadow-[0_20px_50px_rgba(0,0,0,0.3)] flex items-center gap-4 border border-white/10">
                    <div class="w-10 h-10 rounded-xl bg-purple-600 flex items-center justify-center shadow-lg shadow-purple-500/20">
                        <i class="bi bi-cart-check-fill text-lg"></i>
                    </div>
                    <div>
                        <p class="text-[9px] font-black text-gray-400 uppercase tracking-[0.2em] leading-none mb-1.5">Currently Selected</p>
                        <p id="total-items-count" class="text-base font-black tracking-tight">0 Items</p>
                    </div>
                </div>
            </div>

            <div class="fixed bottom-0 left-0 right-0 bg-white/95 dark:bg-[#131b2e]/95 backdrop-blur-xl border-t border-gray-100 dark:border-slate-800 p-3 sm:p-5 flex flex-col md:flex-row items-center justify-between z-40 shadow-2xl gap-3">
                <div class="flex items-center gap-3 w-full md:w-auto justify-between md:justify-start">
                    <div class="flex items-center gap-2.5">
                        <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-2xl bg-purple-100 dark:bg-purple-950/80 flex items-center justify-center text-purple-600 dark:text-purple-400 shrink-0">
                            <i class="bi bi-bag-check-fill text-lg sm:text-xl"></i>
                        </div>
                        <div>
                            <p class="text-[9px] sm:text-[10px] font-black text-gray-400 dark:text-slate-400 uppercase tracking-widest">Selection Progress</p>
                            <div id="autosave-indicator" class="text-[9px] font-bold text-gray-400 dark:text-slate-400 uppercase tracking-widest mt-0.5 flex items-center gap-1">
                                <!-- Status will appear here -->
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="selection-status" class="hidden lg:flex items-center gap-6 border-l border-gray-100 dark:border-slate-800 pl-8 ml-8">
                    <!-- Counters injected here by JS -->
                </div>

                <div class="flex items-center gap-2 sm:gap-3 w-full md:w-auto flex-wrap justify-between sm:justify-end">
                    <a href="dashboard.php?booking_id=<?= $booking_id ?>" class="px-4 py-2.5 sm:px-5 sm:py-3.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl sm:rounded-2xl font-black text-[10px] sm:text-[11px] uppercase tracking-widest transition-all flex items-center gap-1.5 border border-slate-200 dark:border-slate-700 shadow-2xs cursor-pointer min-h-[42px]">
                        <i class="bi bi-arrow-left text-sm"></i> Back to Step 1
                    </a>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="clearSelection()" class="text-[10px] font-black text-gray-400 dark:text-slate-400 uppercase tracking-widest hover:text-purple-600 transition-colors px-2 py-2 cursor-pointer">
                            <i class="bi bi-eraser-fill mr-1"></i> Clear
                        </button>
                        <button type="button" onclick="deleteBooking(<?= $booking_id ?>)" class="text-[10px] font-black text-red-400 uppercase tracking-widest hover:text-red-600 transition-colors px-2 py-2 cursor-pointer">
                            <i class="bi bi-trash3 mr-1"></i> Delete
                        </button>
                    </div>
                    <button type="submit" name="action" value="save_and_summary" class="w-full sm:w-auto bg-purple-600 hover:bg-purple-700 text-white px-6 sm:px-8 py-3 sm:py-3.5 rounded-xl sm:rounded-2xl font-black text-[10px] sm:text-[11px] uppercase tracking-widest transition-all shadow-xl shadow-purple-500/20 flex items-center justify-center gap-2 cursor-pointer min-h-[42px]">
                        Confirm &amp; View Summary <i class="bi bi-arrow-right"></i>
                    </button>
                </div>
            </div>
        </form>
    </main>

    <div id="recipeModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-[100] transition-opacity duration-300">
        <div class="absolute w-full h-full bg-slate-950/60 backdrop-blur-xs" onclick="closeRecipeModal()"></div>
        <div class="bg-white dark:bg-[#131b2e] w-full max-w-lg mx-4 rounded-3xl shadow-2xl z-50 overflow-hidden relative border border-slate-100 dark:border-slate-800 p-8">
            <div class="flex justify-between items-start mb-6 pb-4 border-b border-slate-100 dark:border-slate-800">
                <div>
                    <h3 id="modalDishName" class="text-2xl font-black text-slate-900 dark:text-slate-100 tracking-tight">Recipe</h3>
                    <p class="text-[10px] font-bold text-purple-600 dark:text-purple-400 uppercase tracking-widest mt-0.5">Cooking Instructions &amp; Ingredients</p>
                </div>
                <button onclick="closeRecipeModal()" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 text-xl cursor-pointer p-1"><i class="bi bi-x-lg"></i></button>
            </div>
            <div id="modalRecipeContent" class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed max-h-[350px] overflow-y-auto pr-4 whitespace-pre-wrap font-medium"></div>
            <button onclick="closeRecipeModal()" class="mt-8 w-full py-3.5 bg-slate-900 dark:bg-purple-600 text-white rounded-2xl text-xs font-black uppercase tracking-widest hover:bg-purple-700 transition cursor-pointer">Close</button>
        </div>
    </div>

    <!-- Dietary Notes & Food Instructions Modal -->
    <div id="dietaryModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-[100] transition-opacity duration-300">
        <div class="absolute w-full h-full bg-slate-950/60 backdrop-blur-xs" onclick="closeDietaryModal()"></div>
        <div class="bg-white dark:bg-[#131b2e] w-full max-w-lg mx-4 rounded-3xl shadow-2xl z-50 overflow-hidden relative border border-slate-100 dark:border-slate-800 p-6 md:p-8">
            <div class="flex justify-between items-start mb-4 pb-3 border-b border-slate-100 dark:border-slate-800">
                <div>
                    <span class="text-[10px] font-black text-amber-600 dark:text-amber-400 uppercase tracking-widest bg-amber-50 dark:bg-amber-950/60 px-2.5 py-1 rounded-full border border-amber-200 dark:border-amber-800/50">Dietary Instructions</span>
                    <h3 id="modalDietaryDishName" class="text-xl font-extrabold text-slate-900 dark:text-slate-100 tracking-tight mt-1">Dish Name</h3>
                </div>
                <button onclick="closeDietaryModal()" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 p-1 cursor-pointer"><i class="bi bi-x-lg text-lg"></i></button>
            </div>

            <p class="text-xs text-slate-500 dark:text-slate-400 font-semibold mb-3">Add dietary requirements, allergies, or special preparation notes for this dish:</p>

            <!-- Quick Tag Chips -->
            <div class="flex flex-wrap gap-1.5 mb-4">
                <button type="button" onclick="appendDietaryTag('🌱 Vegetarian portion only')" class="px-2.5 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 text-[10px] font-bold border border-emerald-200 dark:border-emerald-800/50 hover:bg-emerald-100 transition cursor-pointer">+ Vegetarian</button>
                <button type="button" onclick="appendDietaryTag('🥜 Nut Allergy Alert (No Nuts)')" class="px-2.5 py-1 rounded-lg bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 text-[10px] font-bold border border-rose-200 dark:border-rose-800/50 hover:bg-rose-100 transition cursor-pointer">+ Nut Allergy</button>
                <button type="button" onclick="appendDietaryTag('🌶️ Mild / No Chili')" class="px-2.5 py-1 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 text-[10px] font-bold border border-amber-200 dark:border-amber-800/50 hover:bg-amber-100 transition cursor-pointer">+ Mild / No Spice</button>
                <button type="button" onclick="appendDietaryTag('🧂 Low Sodium (Less Salty)')" class="px-2.5 py-1 rounded-lg bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 text-[10px] font-bold border border-blue-200 dark:border-blue-800/50 hover:bg-blue-100 transition cursor-pointer">+ Less Salty</button>
                <button type="button" onclick="appendDietaryTag('🥛 Dairy-Free')" class="px-2.5 py-1 rounded-lg bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 text-[10px] font-bold border border-purple-200 dark:border-purple-800/50 hover:bg-purple-100 transition cursor-pointer">+ Dairy-Free</button>
                <button type="button" onclick="appendDietaryTag('🥣 Sauce Separated')" class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-[10px] font-bold border border-slate-200 dark:border-slate-700 hover:bg-slate-200 transition cursor-pointer">+ Sauce Separated</button>
            </div>

            <textarea id="modalDietaryText" rows="3" placeholder="e.g. 2 guests with peanut allergy, sauce on the side, please keep less salty..." class="w-full p-3.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:border-purple-600 outline-none text-xs font-semibold text-slate-900 dark:text-slate-100 resize-none mb-4"></textarea>

            <div class="flex items-center gap-3">
                <button type="button" onclick="clearCurrentDishDietary()" class="flex-1 py-3 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-extrabold uppercase tracking-wider transition cursor-pointer">Clear Note</button>
                <button type="button" onclick="saveCurrentDishDietary()" class="flex-1 py-3 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-extrabold uppercase tracking-wider shadow-md shadow-purple-600/30 transition cursor-pointer">Save Note</button>
            </div>
        </div>
    </div>

    <script>
function openPackageInclusionsModal(e, btn) {
            e.preventDefault(); 
            e.stopPropagation();
            const name = btn.getAttribute('data-name');
            const inclusions = btn.getAttribute('data-inclusions');
            openRecipeModal(name + ' Inclusions', inclusions);
        }

function openRecipeModal(name, recipe) {
            document.getElementById('modalDishName').innerText = name;
            document.getElementById('modalRecipeContent').innerText = recipe;
            const modal = document.getElementById('recipeModal');
            modal.classList.remove('opacity-0', 'pointer-events-none');
            document.body.classList.add('modal-active');
        }

function closeRecipeModal() {
            const modal = document.getElementById('recipeModal');
            modal.classList.add('opacity-0', 'pointer-events-none');
            document.body.classList.remove('modal-active');
        }

        // --- Course Stepper & Sequential Navigation Logic ---
        let currentCourseCategory = 1; // 1: Main Course, 4: Veggies, 2: Pasta, 5: Dessert
        let isShowingAllCourses = false;
        const courseSequence = [1, 4, 2, 5];
        const courseNames = { 1: 'Main Course', 4: 'Veggies', 2: 'Pasta', 5: 'Dessert' };
        const courseStepNames = { 
            1: 'Course 1 of 4: Pick Main Course', 
            4: 'Course 2 of 4: Pick Veggies', 
            2: 'Course 3 of 4: Pick Pasta', 
            5: 'Course 4 of 4: Pick Dessert' 
        };

        function switchCourseCategory(catId, isAutoAdvance = false) {
            currentCourseCategory = catId;
            isShowingAllCourses = false;
            
            const allBtn = document.getElementById('toggleAllCoursesBtn');
            if (allBtn) allBtn.innerText = 'View All';
            const chip = document.getElementById('course-indicator-chip');
            if (chip) chip.innerText = 'Showing: ' + (courseNames[catId] || 'Selected Course');
            const guidance = document.getElementById('activeCourseGuidance');
            if (guidance) guidance.innerText = courseStepNames[catId] || '';

            // Update tab styles
            courseSequence.forEach(cId => {
                const tab = document.getElementById(`course-tab-${cId}`);
                if (tab) {
                    if (cId === catId) {
                        tab.className = "course-step-tab p-4 rounded-2xl border-2 transition-all text-left cursor-pointer bg-purple-50 dark:bg-purple-950/70 border-purple-600 text-purple-900 dark:text-purple-100 shadow-sm ring-2 ring-purple-500/20 relative overflow-hidden";
                    } else {
                        tab.className = "course-step-tab p-4 rounded-2xl border-2 transition-all text-left cursor-pointer bg-white dark:bg-[#131b2e] border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:border-purple-300 relative overflow-hidden";
                    }
                }
            });

            // Filter dishes - ONLY show dishes belonging to current course!
            document.querySelectorAll('.dish-card').forEach(card => {
                const cardCat = parseInt(card.getAttribute('data-dish-category'));
                if (cardCat === catId) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });

            // If auto-advanced, smoothly scroll user to top of menu
            if (isAutoAdvance) {
                const container = document.getElementById('menu-search-container');
                if (container) {
                    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        }

        function showAllCoursesToggle() {
            isShowingAllCourses = !isShowingAllCourses;
            const allBtn = document.getElementById('toggleAllCoursesBtn');
            const chip = document.getElementById('course-indicator-chip');
            const guidance = document.getElementById('activeCourseGuidance');

            if (isShowingAllCourses) {
                document.querySelectorAll('.dish-card').forEach(card => card.style.display = 'flex');
                if (allBtn) allBtn.innerText = 'By Course';
                if (chip) chip.innerText = 'Showing: All Courses';
                if (guidance) guidance.innerText = 'Viewing all categories';
            } else {
                switchCourseCategory(currentCourseCategory);
            }
        }

        // --- Dietary Notes Modal Functions ---
        let currentDietaryItemId = null;

        function openDietaryModal(itemId, itemName) {
            currentDietaryItemId = itemId;
            document.getElementById('modalDietaryDishName').innerText = itemName;
            const currentNote = document.getElementById(`dietary-input-${itemId}`).value;
            document.getElementById('modalDietaryText').value = currentNote;
            
            const modal = document.getElementById('dietaryModal');
            modal.classList.remove('opacity-0', 'pointer-events-none');
            document.body.classList.add('modal-active');
        }

        function closeDietaryModal() {
            const modal = document.getElementById('dietaryModal');
            modal.classList.add('opacity-0', 'pointer-events-none');
            document.body.classList.remove('modal-active');
        }

        function appendDietaryTag(tag) {
            const txt = document.getElementById('modalDietaryText');
            if (txt.value.trim() === '') {
                txt.value = tag;
            } else if (!txt.value.includes(tag)) {
                txt.value = txt.value.trim() + ', ' + tag;
            }
        }

        function saveCurrentDishDietary() {
            if (!currentDietaryItemId) return;
            const note = document.getElementById('modalDietaryText').value.trim();
            const input = document.getElementById(`dietary-input-${currentDietaryItemId}`);
            const badge = document.getElementById(`badge-dietary-${currentDietaryItemId}`);
            const preview = document.getElementById(`preview-dietary-${currentDietaryItemId}`);
            const previewText = document.getElementById(`text-dietary-preview-${currentDietaryItemId}`);

            if (input) input.value = note;

            if (note) {
                if (badge) badge.classList.remove('hidden');
                if (preview) preview.classList.remove('hidden');
                if (previewText) previewText.innerText = note;
            } else {
                if (badge) badge.classList.add('hidden');
                if (preview) preview.classList.add('hidden');
            }

            closeDietaryModal();

            // Trigger autosave
            if (typeof performAutoSave === 'function') {
                performAutoSave();
            }

            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Dietary note saved',
                showConfirmButton: false,
                timer: 1500
            });
        }

        function clearCurrentDishDietary() {
            document.getElementById('modalDietaryText').value = '';
            saveCurrentDishDietary();
        }

        function toggleDishSelection(itemId) {
            const input = document.querySelector(`input[name="items[${itemId}][qty]"]`);
            const btn = document.getElementById(`btn-select-${itemId}`);
            const textSpan = document.getElementById(`text-select-${itemId}`);
            
            if (input) {
                const categoryId = parseInt(input.dataset.category);
                const packageRadios = document.querySelectorAll('.package-radio');
                const isPackageSelected = Array.from(packageRadios).some(r => r.checked);

                const restrictedCats = [1, 2, 4, 5];
                if (restrictedCats.includes(categoryId) && !isPackageSelected && input.value == 0) {
                    Swal.fire({
                        title: 'Select Package First!',
                        text: 'Choose Package A, B, C, or D to unlock Main, Pasta, Veggies & Dessert.',
                        icon: 'info',
                        confirmButtonColor: '#7c3aed',
                        customClass: { popup: 'rounded-[2rem]' }
                    });
                    return;
                }

                const isSelecting = (parseInt(input.value) === 0);

                if (parseInt(input.value) > 0) {
                    input.value = 0;
                    if(btn) {
                        btn.className = "w-full py-3.5 rounded-2xl font-black text-xs uppercase tracking-widest transition-all bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-purple-50 dark:hover:bg-purple-950/60 hover:text-purple-600 cursor-pointer flex items-center justify-center gap-2";
                        btn.querySelector('i').className = "bi bi-plus-circle";
                    }
                    if(textSpan) textSpan.innerText = "Select Dish";
                } else {
                    input.value = 1;
                    if(btn) {
                        btn.className = "w-full py-3.5 rounded-2xl font-black text-xs uppercase tracking-widest transition-all bg-purple-600 text-white shadow-lg shadow-purple-600/30 ring-2 ring-purple-600/40 cursor-pointer flex items-center justify-center gap-2";
                        btn.querySelector('i').className = "bi bi-check-circle-fill";
                    }
                    if(textSpan) textSpan.innerText = "Selected";
                }

                input.dispatchEvent(new Event('input', {bubbles: true}));

                // Check for Auto-Progression to the next course if selecting
                if (isSelecting && typeof checkAndAutoAdvanceCourse === 'function') {
                    checkAndAutoAdvanceCourse(categoryId);
                }
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            const qtyInputs = document.querySelectorAll('.dish-qty');
            const packageRadios = document.querySelectorAll('.package-radio');
            const subtotalDisplay = document.getElementById('subtotal-display');
            const indicator = document.getElementById('autosave-indicator');
            const paxCount = <?= isset($booking_id) ? (int)$conn->query("SELECT pax_count FROM bookings WHERE booking_id = $booking_id")->fetch_assoc()['pax_count'] : 0 ?>;

            let lastClearedState = null;

            // Expose globally for HTML onclick attributes
            window.clearSelection = function() {
                Swal.fire({
                    title: 'Clear all items?',
                    text: "This will reset your menu selections to zero.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#7c3aed',
                    confirmButtonText: 'Yes, clear it',
                    customClass: { popup: 'rounded-[2rem]' }
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Capture current state before clearing
                        lastClearedState = {
                            qtys: Array.from(qtyInputs).map(i => ({ name: i.name, value: i.value })),
                            packageId: Array.from(packageRadios).find(r => r.checked)?.value || null
                        };

                        qtyInputs.forEach(i => i.value = 0);
                        packageRadios.forEach(r => r.checked = false);
                        calculateSubtotal(); 
                        
                        // Trigger immediate auto-save instead of waiting for the debounce timer
                        clearTimeout(autoSaveTimer);
                        performAutoSave();

                        // Show Success message with Undo option
                        Swal.fire({
                            title: 'Selections Cleared',
                            text: 'Your menu has been reset. Did you do this by mistake?',
                            icon: 'success',
                            showCancelButton: true,
                            confirmButtonColor: '#7c3aed',
                            confirmButtonText: 'Undo Clear',
                            cancelButtonText: 'Dismiss',
                            customClass: { popup: 'rounded-[2rem]' }
                        }).then((undoResult) => {
                            if (undoResult.isConfirmed && lastClearedState) {
                                // Restore Quantities
                                lastClearedState.qtys.forEach(item => {
                                    const input = document.querySelector(`input[name="${CSS.escape(item.name)}"]`);
                                    if(input) input.value = item.value;
                                });
                                
                                // Restore Package
                                if (lastClearedState.packageId) {
                                    const radio = document.querySelector(`input[name="selected_package"][value="${CSS.escape(lastClearedState.packageId)}"]`);
                                    if(radio) radio.checked = true;
                                }

                                calculateSubtotal();
                                clearTimeout(autoSaveTimer);
                                performAutoSave();

                                Swal.fire({
                                    title: 'Restored!',
                                    text: 'Your previous selections have been recovered.',
                                    icon: 'success',
                                    timer: 2000,
                                    showConfirmButton: false,
                                    customClass: { popup: 'rounded-[2rem]' }
                                });
                            }
                        });
                    }
                });
            };

            window.deleteBooking = function(id) {
                Swal.fire({
                    title: 'Delete this booking?',
                    text: "This will permanently remove your progress and event details.",
                    icon: 'error',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    confirmButtonText: 'Yes, delete everything',
                    customClass: { popup: 'rounded-[2rem]' }
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = `auth/delete_booking.php?booking_id=${id}`;
                    }
                });
            };

            let autoSaveTimer;
            let hasPendingChanges = false;
            let isSubmitting = false;
            let isInitialized = false;

            // Prevent warning when the user is actually submitting the form
            document.querySelector('form').addEventListener('submit', () => {
                isSubmitting = true;
            });

            // Custom Modal for Internal Navigation (Links)
            document.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', function(e) {
                    const targetUrl = this.href;
                    const clickedPill = this;

                    // Only intercept if there are changes, it's a real link, and not an action button
                    if (hasPendingChanges && targetUrl && !targetUrl.includes('#') && !this.hasAttribute('onclick')) {
                        e.preventDefault();
                        
                        // Highlight the specific category pill being clicked
                        if (clickedPill.classList.contains('category-pill')) {
                            clickedPill.classList.add('animate-pulse', 'ring-2', 'ring-purple-400', 'text-purple-600');
                            clickedPill.innerHTML = `<i class="bi bi-arrow-repeat animate-spin mr-2"></i> Saving...`;
                        }

                        // Clear any pending debounce timer and save immediately
                        clearTimeout(autoSaveTimer);
                        performAutoSave().finally(() => {
                            hasPendingChanges = false;
                            window.location.href = targetUrl;
                        });
                    }
                });
            });

            function performAutoSave() {
                indicator.innerHTML = `<i class="bi bi-arrow-repeat animate-spin"></i> Auto-saving...`;
                
                const formData = new FormData(document.querySelector('form'));
                formData.append('action', 'auto_save');

                return fetch('auth/auto_save_menu.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        indicator.innerHTML = `<i class="bi bi-check2-circle text-green-500"></i> Last saved at ${data.time}`;
                        hasPendingChanges = false;
                    }
                })
                .catch(err => {
                    indicator.innerHTML = `<span class="text-red-400">Save failed</span>`;
                });
            }

            function scrollToSummary() {
                window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
            }

            window.currentCounts = { 1: 0, 2: 0, 4: 0, 5: 0 };
            window.currentLimits = { 1: 0, 2: 0, 4: 0, 5: 0 };

            window.checkAndAutoAdvanceCourse = function(categoryId) {
                if (categoryId !== currentCourseCategory || isShowingAllCourses) return;

                setTimeout(() => {
                    const cCount = window.currentCounts[categoryId] || 0;
                    const cLimit = window.currentLimits[categoryId] || 0;

                    if (cLimit > 0 && cCount >= cLimit) {
                        const currentIndex = courseSequence.indexOf(categoryId);
                        if (currentIndex !== -1 && currentIndex < courseSequence.length - 1) {
                            const nextCat = courseSequence[currentIndex + 1];

                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'success',
                                title: `✅ ${courseNames[categoryId]} selected! Proceeding to ${courseNames[nextCat]}...`,
                                showConfirmButton: false,
                                timer: 1400
                            });

                            setTimeout(() => {
                                switchCourseCategory(nextCat, true);
                            }, 450);
                        } else if (currentIndex === courseSequence.length - 1) {
                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'success',
                                title: `🎉 All 4 courses selected! Review or proceed to summary.`,
                                showConfirmButton: false,
                                timer: 2000
                            });
                        }
                    }
                }, 40);
            };

            function calculateSubtotal() {
                if (isInitialized) hasPendingChanges = true;
                
                let total = 0;
                let totalItems = 0;
                let counts = { 1: 0, 2: 0, 4: 0, 5: 0 };
                let limits = { 1: 0, 2: 0, 4: 0, 5: 0 };
                let overLimit = false;
                let errorMsg = "";

                const isPackageSelected = Array.from(packageRadios).some(r => r.checked);

                // Add Package Cost
                packageRadios.forEach(radio => {
                    if (radio.checked) {
                        total += parseFloat(radio.dataset.rate) * paxCount;
                        limits[1] = parseInt(radio.dataset.limit) || 0;
                        limits[2] = parseInt(radio.dataset.pastaLimit) || 0;
                        limits[4] = parseInt(radio.dataset.veggiesLimit) || 0;
                        limits[5] = parseInt(radio.dataset.dessertLimit) || 0;
                    }
                });

                window.currentLimits = limits;

                const restrictedCats = [1, 2, 4, 5];
                qtyInputs.forEach(input => {
                    if (restrictedCats.includes(parseInt(input.dataset.category))) {
                        input.disabled = !isPackageSelected;
                        if (!isPackageSelected) input.value = 0;
                    }
                });

                qtyInputs.forEach(input => {
                    const qty = parseInt(input.value) || 0;
                    const price = parseFloat(input.dataset.price) || 0;
                    const categoryId = parseInt(input.dataset.category);
                    
                    if (qty > 0 && restrictedCats.includes(categoryId)) {
                        counts[categoryId]++; 
                    }
                    if (qty > 0) {
                        totalItems += qty;
                    }
                    total += qty * price;
                });

                window.currentCounts = counts;

                // Update Course Stepper Tabs (Course 1: Main, Course 2: Veggies, Course 3: Pasta, Course 4: Dessert)
                courseSequence.forEach(cId => {
                    const countEl = document.getElementById(`course-count-${cId}`);
                    const checkEl = document.getElementById(`course-check-${cId}`);
                    const cCount = counts[cId] || 0;
                    const cLimit = limits[cId] || 0;

                    if (countEl) {
                        countEl.innerText = `${cCount}/${cLimit} Selected`;
                        if (cCount >= cLimit && cLimit > 0) {
                            countEl.className = "text-[10px] font-black text-emerald-600 dark:text-emerald-400 mt-1";
                        } else {
                            countEl.className = (cId === currentCourseCategory) ? "text-[10px] font-black text-purple-700 dark:text-purple-300 mt-1" : "text-[10px] font-black text-slate-400 mt-1";
                        }
                    }
                    if (checkEl) {
                        if (cCount >= cLimit && cLimit > 0) {
                            checkEl.classList.remove('hidden');
                        } else {
                            checkEl.classList.add('hidden');
                        }
                    }
                });

                // Update Floating Counter
                const floatingCounter = document.getElementById('floating-counter');
                const countDisplay = document.getElementById('total-items-count');
                if (totalItems > 0) {
                    floatingCounter.classList.remove('hidden');
                    setTimeout(() => {
                        floatingCounter.classList.remove('opacity-0', 'translate-y-4');
                    }, 10);
                    countDisplay.innerText = `${totalItems} ${totalItems === 1 ? 'Item' : 'Items'}`;
                } else {
                    floatingCounter.classList.add('opacity-0', 'translate-y-4');
                    setTimeout(() => floatingCounter.classList.add('hidden'), 500);
                }

                // Update Selection Counters UI in Bottom Bar
                const statusContainer = document.getElementById('selection-status');
                if (isPackageSelected) {
                    statusContainer.classList.remove('hidden');
                    const labels = { 1: 'Main', 4: 'Veg', 2: 'Pasta', 5: 'Dessert' };
                    let html = '';
                    courseSequence.forEach(catId => {
                        const currentCount = counts[catId];
                        const currentLimit = limits[catId];

                        if (currentCount > currentLimit) {
                            overLimit = true;
                            errorMsg = `${labels[catId]} Limit Exceeded or Not Included`;
                        }

                        if (currentLimit > 0 || currentCount > 0) {
                            const isOver = currentCount > currentLimit;
                            const isFull = currentCount === currentLimit;
                            const color = isOver ? 'text-red-500 animate-pulse' : (isFull ? 'text-emerald-500 font-bold' : 'text-purple-600');
                            const badge = isFull ? '<i class="bi bi-check-circle-fill text-emerald-400 ml-1"></i>' : (isOver ? '<i class="bi bi-x-circle-fill text-red-400 ml-1"></i>' : '');
                            html += `
                                <div class="text-center cursor-pointer hover:opacity-80 transition" onclick="switchCourseCategory(${catId})">
                                    <p class="text-[8px] font-black text-slate-400 uppercase tracking-tighter">${labels[catId]}</p>
                                    <p class="text-xs font-black ${color}">${currentCount}/${currentLimit}${badge}</p>
                                </div>`;
                        }
                    });
                    statusContainer.innerHTML = html;
                } else {
                    statusContainer.classList.add('hidden');
                }

                const submitBtn = document.querySelector('button[type="submit"]');

                if (overLimit) {
                    subtotalDisplay.innerHTML = `<span class="text-red-500">${errorMsg}</span>`;
                    submitBtn.disabled = true;
                    submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                } else {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');

                    // Trigger Auto-save after 1.5 seconds of inactivity
                    clearTimeout(autoSaveTimer);
                    autoSaveTimer = setTimeout(performAutoSave, 1500);
                }
            }

            qtyInputs.forEach(input => {
                input.addEventListener('input', calculateSubtotal);
                input.addEventListener('change', calculateSubtotal);
            });

            packageRadios.forEach(radio => {
                radio.addEventListener('change', calculateSubtotal);
            });

            // Auto-select first package if none selected
            const firstPackage = document.querySelector('.package-radio:checked');
            if (!firstPackage) {
                const defaultPkg = document.querySelector('.package-radio');
                if (defaultPkg) {
                    defaultPkg.checked = true;
                    defaultPkg.dispatchEvent(new Event('change', {bubbles: true}));
                }
            }

            // Initialize state on load
            calculateSubtotal();
            
            // Set initial front view: ONLY Main Course visible (not shuffled!)
            switchCourseCategory(1);
            isInitialized = true;

            // Browser-level fallback for tab closing/refreshing
            window.addEventListener('beforeunload', (e) => {
                if (hasPendingChanges && !isSubmitting) {
                    e.preventDefault();
                    e.returnValue = ''; // Standard requirement for showing the browser prompt
                }
            });
        });

        function performSearch() {
            const searchInput = document.getElementById('dish-search');
            const term = searchInput.value.toLowerCase().trim();
            document.querySelectorAll('.dish-card').forEach(card => {
                const cardCat = parseInt(card.getAttribute('data-dish-category'));
                const name = (card.getAttribute('data-dish-name') || card.querySelector('h3').innerText).toLowerCase();
                const desc = card.querySelector('p').innerText.toLowerCase();
                
                const matchesTerm = (term === '' || name.includes(term) || desc.includes(term));
                const matchesCategory = isShowingAllCourses || (cardCat === currentCourseCategory);

                if (matchesTerm && matchesCategory) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Instant Search Logic
        document.getElementById('dish-search').addEventListener('input', performSearch);

        // Filter Button
        document.getElementById('filter-btn').addEventListener('click', function() {
            this.style.transform = 'scale(0.95)';
            setTimeout(() => this.style.transform = '', 150);
            performSearch();
        });
    </script>
    <script>
        function toggleNotifDropdown() {
            const dropdown = document.getElementById('notif-dropdown');
            const badge = document.getElementById('notif-badge');
            const isHidden = dropdown.classList.contains('hidden');
            
            dropdown.classList.toggle('hidden');
            
            if (isHidden && badge) {
                // Mark as read via AJAX
                fetch('auth/mark_read.php')
                    .then(response => response.json())
                    .then(data => {
                        if(data.status === 'success') {
                            badge.classList.add('hidden');
                        }
                    });
            }
        }
    </script>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }
    </script>
</body>
</html>
