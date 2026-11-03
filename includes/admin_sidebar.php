<?php
// includes/admin_sidebar.php - Minimalist Clean Admin Sidebar Navigation
require_once __DIR__ . '/staff_helper.php';
$current_page = basename($_SERVER['SCRIPT_NAME']);
$user_name = $_SESSION['full_name'] ?? 'System Admin';
$user_role = strtolower($_SESSION['role'] ?? 'client');
$is_admin  = ($user_role === 'admin');
$is_staff  = ($user_role === 'staff');
$staff_perms = $is_staff ? get_staff_permissions($conn ?? null, $_SESSION['user_id'] ?? null) : [];

$adm_pending_count = 0;
$adm_unread_msg_count = 0;
if (isset($conn)) {
    $adm_count_res = $conn->query("SELECT COUNT(*) as total FROM bookings WHERE status = 'pending'");
    if ($adm_count_res) {
        $adm_pending_count = $adm_count_res->fetch_assoc()['total'] ?? 0;
    }
    
    // Check unread messages for admin / staff
    $adm_id = $_SESSION['user_id'] ?? 1;
    try {
        $msg_res = $conn->query("SELECT COUNT(*) as total FROM messages WHERE (receiver_id = $adm_id OR receiver_id = 1) AND is_read = 0");
        if ($msg_res) {
            $adm_unread_msg_count = $msg_res->fetch_assoc()['total'] ?? 0;
        }
    } catch (Exception $e) {
        $adm_unread_msg_count = 0;
    }
}
?>
<script>
    // Theme Initialization (Flicker-Free execution before DOM paint)
    (function() {
        const savedTheme = localStorage.getItem('theme');
        const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        if (savedTheme === 'dark' || (!savedTheme && systemDark)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    })();
</script>
<script>
    if (window.tailwind) {
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        darkbg: '#0b0f19',
                        darkcard: '#131b2e',
                    }
                }
            }
        };
    }
</script>
<style>
    @media (min-width: 1024px) {
        html { font-size: 84%; }
    }

    /* Global Universal Dark Mode Overrides for Admin Dashboard */
    html.dark body, body.dark {
        background-color: #0b0f19 !important;
        color: #f8fafc !important;
    }

    /* Top Header Bar: Transparent & No Dark Shadow Bar */
    html.dark header, 
    html.dark header .bg-white, 
    html.dark header.bg-white {
        background-color: transparent !important;
        border-color: transparent !important;
        box-shadow: none !important;
    }

    /* All Light Background Utilities & Opacity Containers mapped to Dark Surfaces */
    html.dark .glass-card,
    html.dark .bg-white,
    html.dark .bg-slate-50,
    html.dark .bg-slate-100,
    html.dark .bg-slate-200,
    html.dark .bg-slate-300,
    html.dark .bg-gray-50,
    html.dark .bg-gray-100,
    html.dark .bg-gray-200,
    html.dark .bg-gray-300,
    html.dark .bg-\[\#FBFBFC\],
    html.dark .bg-\[\#F8FAFC\],
    html.dark .bg-\[\#FAFBFF\],
    html.dark .bg-\[\#F1F5F9\]\/50,
    html.dark .stat-card,
    html.dark div[class*="bg-slate-50"],
    html.dark div[class*="bg-slate-100"],
    html.dark div[class*="bg-slate-200"],
    html.dark div[class*="bg-gray-50"],
    html.dark div[class*="bg-gray-100"] {
        background-color: #131b2e !important;
        border-color: #1e293b !important;
        color: #f8fafc !important;
    }

    /* Remove bright white glow shadows from buttons & cards */
    html.dark .shadow-gray-100,
    html.dark .shadow-gray-200,
    html.dark .shadow-purple-200,
    html.dark .shadow-white {
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.4), 0 2px 4px -2px rgba(0, 0, 0, 0.4) !important;
    }

    /* Sidebar Dark Theme */
    html.dark aside#sidebar, body.dark aside#sidebar {
        background-color: #131b2e !important;
        border-color: #1e293b !important;
        color: #f8fafc !important;
    }

    html.dark aside#sidebar .bg-slate-50, 
    html.dark aside#sidebar .bg-white,
    body.dark aside#sidebar .bg-slate-50,
    body.dark aside#sidebar .bg-white {
        background-color: #1e293b !important;
        border-color: #334155 !important;
        color: #f8fafc !important;
    }

    html.dark aside#sidebar .text-slate-900,
    html.dark aside#sidebar .text-slate-600,
    html.dark aside#sidebar .text-slate-700 {
        color: #f1f5f9 !important;
    }

    html.dark aside#sidebar .text-slate-400 {
        color: #94a3b8 !important;
    }

    /* Global Cards & Main Content Blocks */
    html.dark main, body.dark main {
        background-color: #0b0f19 !important;
    }

    /* Status Pills & Badges In Dark Mode (High-contrast text & dark tint backgrounds) */
    html.dark .bg-emerald-50, 
    html.dark .bg-emerald-100,
    html.dark .bg-emerald-200,
    html.dark .bg-emerald-50\/90,
    html.dark .bg-green-50,
    html.dark .bg-green-100,
    html.dark .bg-green-200,
    html.dark span.bg-emerald-50,
    html.dark span.bg-emerald-100,
    html.dark span.bg-green-100 {
        background-color: rgba(6, 78, 59, 0.85) !important;
        color: #34d399 !important;
        border-color: rgba(52, 211, 153, 0.4) !important;
    }
    html.dark .bg-emerald-50 *, 
    html.dark .bg-emerald-100 *,
    html.dark .bg-green-50 *,
    html.dark .bg-green-100 * {
        color: #34d399 !important;
    }

    html.dark .bg-amber-50, 
    html.dark .bg-amber-100,
    html.dark .bg-amber-200,
    html.dark .bg-amber-50\/90,
    html.dark .bg-yellow-50,
    html.dark .bg-yellow-100,
    html.dark span.bg-amber-50,
    html.dark span.bg-amber-100 {
        background-color: rgba(120, 53, 15, 0.85) !important;
        color: #fbbf24 !important;
        border-color: rgba(251, 191, 36, 0.4) !important;
    }
    html.dark .bg-amber-50 *, html.dark .bg-amber-100 *, html.dark .bg-yellow-100 * {
        color: #fbbf24 !important;
    }

    html.dark .bg-rose-50, 
    html.dark .bg-rose-100,
    html.dark .bg-rose-200,
    html.dark .bg-rose-50\/90,
    html.dark .bg-red-50, 
    html.dark .bg-red-100,
    html.dark .bg-red-200,
    html.dark span.bg-rose-50,
    html.dark span.bg-red-100 {
        background-color: rgba(136, 19, 55, 0.85) !important;
        color: #f43f5e !important;
        border-color: rgba(244, 63, 94, 0.4) !important;
    }
    html.dark .bg-rose-50 *, html.dark .bg-rose-100 *, html.dark .bg-red-100 * {
        color: #f43f5e !important;
    }

    html.dark .bg-purple-50, html.dark .bg-purple-100, html.dark span.bg-purple-50 {
        background-color: rgba(88, 28, 135, 0.85) !important;
        color: #c084fc !important;
        border-color: rgba(168, 85, 247, 0.4) !important;
    }
    html.dark .bg-purple-50 *, html.dark .bg-purple-100 * {
        color: #c084fc !important;
    }

    html.dark .bg-blue-50, html.dark .bg-blue-100, html.dark .bg-indigo-50, html.dark .bg-indigo-100 {
        background-color: rgba(30, 58, 138, 0.85) !important;
        color: #60a5fa !important;
        border-color: rgba(96, 165, 250, 0.4) !important;
    }
    html.dark .bg-blue-50 *, html.dark .bg-blue-100 * {
        color: #60a5fa !important;
    }

    /* Action Buttons & Rate/Pax Badges inside dark mode cards */
    html.dark button.bg-white, html.dark a.bg-white, html.dark span.bg-white,
    html.dark button.bg-slate-100, html.dark a.bg-slate-100, html.dark span.bg-slate-100 {
        background-color: #1b253b !important;
        color: #f8fafc !important;
        border-color: #334155 !important;
    }

    html.dark button.bg-white:hover, html.dark a.bg-white:hover,
    html.dark button.bg-slate-100:hover, html.dark a.bg-slate-100:hover {
        background-color: #26334d !important;
        color: #ffffff !important;
    }

    /* Hover Effects: Smooth Dark Navy Highlight (#1e2d4a) instead of White/Grey */
    html.dark .conv-item:hover,
    html.dark .conv-item.active,
    html.dark .hover\:bg-slate-50:hover,
    html.dark .hover\:bg-slate-50\/60:hover,
    html.dark .hover\:bg-slate-50\/50:hover,
    html.dark .hover\:bg-slate-100:hover,
    html.dark .hover\:bg-slate-200:hover,
    html.dark .hover\:bg-gray-50:hover,
    html.dark .hover\:bg-gray-50\/30:hover,
    html.dark .hover\:bg-gray-100:hover,
    html.dark .hover\:bg-purple-50:hover,
    html.dark .hover\:bg-white:hover,
    html.dark tr.hover\:bg-gray-50\/30:hover,
    html.dark tr:hover,
    html.dark div[class*="hover:bg-slate-50"]:hover,
    html.dark div[class*="hover:bg-gray-50"]:hover {
        background-color: #1e2d4a !important;
        border-color: #3b4d71 !important;
        color: #ffffff !important;
    }

    /* Chat Center & Conversation Items */
    html.dark #chatCol, html.dark #emptyAdminState, html.dark #chatHistory,
    html.dark .conv-item.active {
        background-color: #1a102b !important;
        border-color: #5b21b6 !important;
        color: #f8fafc !important;
    }

    html.dark #chatInputArea {
        background-color: #131b2e !important;
        border-color: #1e293b !important;
    }

    html.dark #chatInputArea div[class*="flex-1"] {
        background-color: #1b253b !important;
        border-color: #334155 !important;
    }

    /* Calendar Grid Cells & Schedule Days */
    html.dark .calendar-day, 
    html.dark div[class*="grid"] > div {
        border-color: #1e293b !important;
    }

    /* High-Contrast Typography */
    html.dark h1, html.dark h2, html.dark h3, html.dark h4, html.dark h5, html.dark h6,
    html.dark .text-slate-900, html.dark .text-gray-900, html.dark .text-gray-800, html.dark .text-slate-800, html.dark .text-slate-700 {
        color: #f8fafc !important;
    }

    html.dark p, html.dark span:not(.bg-emerald-50):not(.bg-amber-50):not(.bg-rose-50):not(.bg-purple-50):not(.bg-blue-50),
    html.dark .text-slate-600, html.dark .text-slate-500, html.dark .text-gray-500, html.dark .text-gray-600 {
        color: #cbd5e1 !important;
    }

    html.dark .text-slate-400, html.dark .text-gray-400 {
        color: #94a3b8 !important;
    }

    /* Data Tables & Modals */
    html.dark table { color: #f8fafc !important; }
    html.dark tr, html.dark td, html.dark th { border-color: #1e293b !important; }
    html.dark th { background-color: #1b253b !important; color: #cbd5e1 !important; }
    html.dark td { background-color: #131b2e !important; color: #f8fafc !important; }

    /* Form Inputs & Selects Dark Mode + Focus/Autofill White Spot Fix */
    html.dark input:not([type="checkbox"]):not([type="radio"]):not(.bg-transparent), 
    html.dark select, 
    html.dark textarea:not(.bg-transparent) {
        background-color: #1b253b !important;
        border-color: #334155 !important;
        color: #f8fafc !important;
    }

    /* Transparent inputs & textareas (e.g. Chat messageInput) */
    html.dark textarea.bg-transparent,
    html.dark input.bg-transparent,
    html.dark textarea[class*="bg-transparent"],
    html.dark input[class*="bg-transparent"],
    html.dark #messageInput {
        background-color: transparent !important;
        border-color: transparent !important;
        box-shadow: none !important;
    }

    html.dark input:not(.bg-transparent):focus, 
    html.dark textarea:not(.bg-transparent):focus, 
    html.dark select:focus {
        background-color: #1b253b !important;
        border-color: #7c3aed !important;
        color: #ffffff !important;
        outline: none !important;
    }

    /* Prevent WebKit autofill & focus white background spot */
    html.dark input:-webkit-autofill,
    html.dark input:-webkit-autofill:hover, 
    html.dark input:-webkit-autofill:focus,
    html.dark textarea:-webkit-autofill,
    html.dark textarea:-webkit-autofill:hover,
    html.dark textarea:-webkit-autofill:focus,
    html.dark select:-webkit-autofill,
    html.dark select:-webkit-autofill:hover,
    html.dark select:-webkit-autofill:focus {
        -webkit-text-fill-color: #f8fafc !important;
        -webkit-box-shadow: 0 0 0px 1000px #1b253b inset !important;
        transition: background-color 5000s ease-in-out 0s !important;
    }

    html.dark ::placeholder { color: #94a3b8 !important; }
    html.dark .modal-content, html.dark [role="dialog"] {
        background-color: #131b2e !important;
        border-color: #1e293b !important;
        color: #f8fafc !important;
    }

    /* Modal Backdrop & Overlay Layering: Ensure Modals Dim/Cover Sidebar */
    .swal2-container,
    [role="dialog"] {
        z-index: 99999 !important;
    }
    div[id*="Modal"]:not(.hidden),
    div[id*="modal"]:not(.hidden) {
        z-index: 9000 !important;
    }
</style>

<div id="sidebarOverlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/30 backdrop-blur-sm z-20 hidden lg:hidden transition-opacity"></div>

<aside id="sidebar" class="w-64 bg-white dark:bg-[#131b2e] border-r border-slate-200/80 dark:border-slate-800 flex flex-col fixed h-full z-30 transition-transform duration-300 transform -translate-x-full lg:translate-x-0 shadow-sm">
    <!-- Brand Header -->
    <div class="p-4 pb-2.5 flex items-center justify-between">
        <a href="<?= $is_admin ? 'admin_dashboard.php' : 'staff_dashboard.php' ?>" class="flex items-center gap-2.5">
            <img src="uploads/logo.png" alt="JEMmerific Logo" class="w-9 h-9 object-contain rounded-lg">
            <div>
                <h1 class="text-sm font-extrabold tracking-tight text-slate-900 dark:text-white leading-tight">JEMmerific</h1>
                <span class="text-[9px] font-bold text-purple-600 dark:text-purple-400 uppercase tracking-widest bg-purple-50 dark:bg-purple-950/80 px-2 py-0.5 rounded-full inline-block"><?= $is_admin ? 'Admin Console' : 'Staff Portal' ?></span>
            </div>
        </a>
        <button onclick="toggleSidebar()" class="lg:hidden text-slate-400 hover:text-slate-700 p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
            <i class="bi bi-x-lg text-lg"></i>
        </button>
    </div>

    <!-- Navigation Items -->
    <nav class="flex-1 px-3 py-2 space-y-1 overflow-y-auto no-scrollbar">
        <p class="text-[9px] font-bold text-slate-400 dark:text-slate-400 uppercase tracking-widest px-2.5 mb-1.5">Navigation</p>

        <?php if ($is_admin): ?>
        <a href="admin_dashboard.php" class="flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold transition-all <?= $current_page == 'admin_dashboard.php' ? 'bg-purple-50 dark:bg-purple-950/80 text-purple-700 dark:text-purple-300 font-bold shadow-sm border border-purple-100 dark:border-purple-800' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white' ?>">
            <div class="flex items-center gap-2.5">
                <i class="bi bi-grid-fill text-sm <?= $current_page == 'admin_dashboard.php' ? 'text-purple-600 dark:text-purple-400' : 'text-slate-400' ?>"></i>
                <span>Dashboard</span>
            </div>
        </a>
        <?php else: ?>
        <a href="staff_dashboard.php" class="flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold transition-all <?= $current_page == 'staff_dashboard.php' ? 'bg-purple-50 dark:bg-purple-950/80 text-purple-700 dark:text-purple-300 font-bold shadow-sm border border-purple-100 dark:border-purple-800' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white' ?>">
            <div class="flex items-center gap-2.5">
                <i class="bi bi-grid-fill text-sm <?= $current_page == 'staff_dashboard.php' ? 'text-purple-600 dark:text-purple-400' : 'text-slate-400' ?>"></i>
                <span>Dashboard</span>
            </div>
        </a>
        <?php endif; ?>

        <?php if ($is_admin || in_array('customer_messages', $staff_perms)): ?>
        <a href="admin_messages.php" class="flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold transition-all <?= $current_page == 'admin_messages.php' ? 'bg-purple-50 dark:bg-purple-950/80 text-purple-700 dark:text-purple-300 font-bold shadow-sm border border-purple-100 dark:border-purple-800' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white' ?>">
            <div class="flex items-center gap-2.5">
                <i class="bi bi-chat-left-text-fill text-sm <?= $current_page == 'admin_messages.php' ? 'text-purple-600 dark:text-purple-400' : 'text-slate-400' ?>"></i>
                <span>Customer Messages</span>
            </div>
            <?php if ($adm_unread_msg_count > 0): ?>
                <span class="w-5 h-5 rounded-full bg-rose-500 text-white font-extrabold text-[10px] flex items-center justify-center shadow-2xs shrink-0">
                    <?= $adm_unread_msg_count ?>
                </span>
            <?php endif; ?>
        </a>
        <?php endif; ?>

        <?php if ($is_admin || in_array('booking_requests', $staff_perms)): ?>
        <a href="admin_requests.php" class="flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold transition-all <?= $current_page == 'admin_requests.php' ? 'bg-purple-50 dark:bg-purple-950/80 text-purple-700 dark:text-purple-300 font-bold shadow-sm border border-purple-100 dark:border-purple-800' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white' ?>">
            <div class="flex items-center gap-2.5">
                <i class="bi bi-bell-fill text-sm <?= $current_page == 'admin_requests.php' ? 'text-purple-600 dark:text-purple-400' : 'text-slate-400' ?>"></i>
                <span>Booking Requests</span>
            </div>
            <?php if ($adm_pending_count > 0): ?>
                <span class="px-2 py-0.5 text-[9px] font-extrabold bg-amber-500 text-white rounded-full"><?= $adm_pending_count ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>

        <?php if ($is_admin || in_array('schedule_events', $staff_perms)): ?>
        <a href="admin_schedule.php" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-semibold transition-all <?= $current_page == 'admin_schedule.php' ? 'bg-purple-50 dark:bg-purple-950/80 text-purple-700 dark:text-purple-300 font-bold shadow-sm border border-purple-100 dark:border-purple-800' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white' ?>">
            <i class="bi bi-calendar-event-fill text-sm <?= $current_page == 'admin_schedule.php' ? 'text-purple-600 dark:text-purple-400' : 'text-slate-400' ?>"></i>
            <span>Schedule Events</span>
        </a>
        <?php endif; ?>

        <?php if ($is_admin || in_array('menu_catalog', $staff_perms)): ?>
        <a href="inventory.php" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-semibold transition-all <?= $current_page == 'inventory.php' ? 'bg-purple-50 dark:bg-purple-950/80 text-purple-700 dark:text-purple-300 font-bold shadow-sm border border-purple-100 dark:border-purple-800' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white' ?>">
            <i class="bi bi-card-checklist text-sm <?= $current_page == 'inventory.php' ? 'text-purple-600 dark:text-purple-400' : 'text-slate-400' ?>"></i>
            <span>Menu Catalog</span>
        </a>
        <?php endif; ?>

        <?php if ($is_admin): ?>
        <a href="users.php" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-semibold transition-all <?= $current_page == 'users.php' ? 'bg-purple-50 dark:bg-purple-950/80 text-purple-700 dark:text-purple-300 font-bold shadow-sm border border-purple-100 dark:border-purple-800' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white' ?>">
            <i class="bi bi-people-fill text-sm <?= $current_page == 'users.php' ? 'text-purple-600 dark:text-purple-400' : 'text-slate-400' ?>"></i>
            <span>Customers</span>
        </a>

        <!-- Staff Button Directly Under Customers -->
        <a href="admin_staff.php" class="flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold transition-all <?= $current_page == 'admin_staff.php' ? 'bg-purple-50 dark:bg-purple-950/80 text-purple-700 dark:text-purple-300 font-bold shadow-sm border border-purple-100 dark:border-purple-800' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white' ?>">
            <div class="flex items-center gap-2.5">
                <i class="bi bi-person-badge-fill text-sm <?= $current_page == 'admin_staff.php' ? 'text-purple-600 dark:text-purple-400' : 'text-slate-400' ?>"></i>
                <span>Staff</span>
            </div>
            <span class="text-[9px] font-extrabold uppercase px-1.5 py-0.5 rounded-md bg-purple-100 text-purple-700 dark:bg-purple-900/60 dark:text-purple-300">Team</span>
        </a>
        <?php endif; ?>

        <div class="pt-3 mt-3 border-t border-slate-100 dark:border-slate-800">
            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest px-2.5 mb-1.5">System</p>
            <?php if ($is_admin): ?>
            <a href="admin_settings.php" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-semibold transition-all <?= $current_page == 'admin_settings.php' ? 'bg-purple-50 dark:bg-purple-950/80 text-purple-700 dark:text-purple-300 font-bold shadow-sm border border-purple-100 dark:border-purple-800' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white' ?>">
                <i class="bi bi-gear-fill text-sm <?= $current_page == 'admin_settings.php' ? 'text-purple-600 dark:text-purple-400' : 'text-slate-400' ?>"></i>
                <span>Admin & AI Settings</span>
            </a>
            <?php endif; ?>

            <!-- Dark Mode Sidebar Toggle Button -->
            <button onclick="toggleAppDarkMode()" class="w-full flex items-center justify-between px-3 py-2 mt-1 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-all border border-slate-200/80 dark:border-slate-700/80 cursor-pointer shadow-2xs group">
                <div class="flex items-center gap-2.5">
                    <i id="sidebarThemeIcon" class="theme-toggle-icon bi bi-moon-stars-fill text-sm text-purple-600 dark:text-amber-400 group-hover:rotate-12 transition-transform"></i>
                    <span class="font-bold">Dark Mode</span>
                </div>
                <span id="sidebarThemeStatusBadge" class="theme-status-badge text-[9px] font-black uppercase px-2 py-0.5 rounded-full bg-purple-50 text-purple-700 dark:bg-purple-950/80 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                    OFF
                </span>
            </button>
        </div>
    </nav>

    <!-- User Profile Footer -->
    <div class="p-3 m-3 bg-slate-50 dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/80 rounded-2xl">
        <div class="flex items-center gap-2.5 mb-2.5">
            <div class="w-8 h-8 rounded-xl bg-purple-600 text-white font-bold flex items-center justify-center text-xs shadow-sm uppercase">
                <?= substr($user_name, 0, 1) ?>
            </div>
            <div class="overflow-hidden">
                <p class="text-xs font-bold text-slate-900 dark:text-white truncate"><?= htmlspecialchars($user_name) ?></p>
                <p class="text-[9px] font-medium text-slate-500 dark:text-slate-400"><?= $is_admin ? 'Administrator' : 'Staff Member' ?></p>
            </div>
        </div>
        <a href="auth/logout.php" class="flex items-center justify-center gap-1.5 w-full py-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300 hover:text-red-600 dark:hover:text-red-400 hover:border-red-200 dark:hover:border-red-800 hover:bg-red-50 dark:hover:bg-red-950/40 transition-all shadow-2xs">
            <i class="bi bi-box-arrow-right"></i> Sign Out
        </a>
    </div>
</aside>

<script>
    if (typeof toggleSidebar !== 'function') {
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if (sidebar) sidebar.classList.toggle('-translate-x-full');
            if (overlay) overlay.classList.toggle('hidden');
        }
    }

    function toggleAppDarkMode() {
        const isDark = document.documentElement.classList.toggle('dark');
        if (document.body) document.body.classList.toggle('dark', isDark);
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        updateAppThemeUI(isDark);
    }

    function updateAppThemeUI(isDark) {
        const statusBadges = document.querySelectorAll('#sidebarThemeStatusBadge, .theme-status-badge');
        const themeIcons = document.querySelectorAll('#sidebarThemeIcon, .theme-toggle-icon');
        
        statusBadges.forEach(badge => {
            if (badge) {
                badge.innerText = isDark ? 'ON' : 'OFF';
                if (isDark) {
                    badge.className = "theme-status-badge text-[9px] font-black uppercase px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30";
                } else {
                    badge.className = "theme-status-badge text-[9px] font-black uppercase px-2 py-0.5 rounded-full bg-purple-50 text-purple-700 border border-purple-200";
                }
            }
        });

        themeIcons.forEach(icon => {
            if (icon) {
                if (isDark) {
                    icon.className = 'theme-toggle-icon bi bi-sun-fill text-amber-400 text-sm';
                } else {
                    icon.className = 'theme-toggle-icon bi bi-moon-stars-fill text-purple-600 text-sm';
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const isDark = document.documentElement.classList.contains('dark');
        if (document.body && isDark) document.body.classList.add('dark');
        updateAppThemeUI(isDark);

        // Periodic user heartbeat to keep online status active while tab is open
        setInterval(() => {
            fetch('auth/staff_heartbeat.php', { method: 'POST', credentials: 'same-origin' }).catch(() => {});
        }, 45000);
    });
</script>
