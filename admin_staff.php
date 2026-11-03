<?php
// admin_staff.php - Staff Account Management & Activity Monitoring Dashboard
require_once 'includes/admin_auth_check.php';
require_once 'db_config.php';
require_once 'includes/staff_helper.php';

// Auto-ensure database schema for staff is in place
ensure_staff_schema($conn);

$admin_name = $_SESSION['full_name'] ?? 'System Administrator';

// ==========================================
// 1. HANDLE POST ACTIONS (ADD / EDIT)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- ADD NEW STAFF ---
    if ($action === 'add_staff') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $password  = $_POST['password'] ?? '';
        $perms     = $_POST['permissions'] ?? [];

        if (empty($full_name) || empty($email) || empty($password)) {
            header("Location: admin_staff.php?error=missing_fields");
            exit();
        }

        // Check if email already taken
        $chk_stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
        $chk_stmt->bind_param("s", $email);
        $chk_stmt->execute();
        if ($chk_stmt->get_result()->num_rows > 0) {
            header("Location: admin_staff.php?error=email_exists");
            exit();
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $perms_json      = json_encode(array_values(is_array($perms) ? $perms : []));
        $role            = 'staff';

        $ins_stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password, plain_password, role, permissions, created_at, is_online) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 0)");
        $ins_stmt->bind_param("sssssss", $full_name, $email, $phone, $hashed_password, $password, $role, $perms_json);

        if ($ins_stmt->execute()) {
            header("Location: admin_staff.php?msg=added");
        } else {
            header("Location: admin_staff.php?error=db_error");
        }
        exit();
    }

    // --- EDIT EXISTING STAFF ---
    if ($action === 'edit_staff') {
        $staff_id  = intval($_POST['staff_id'] ?? 0);
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $password  = $_POST['password'] ?? '';
        $perms     = $_POST['permissions'] ?? [];

        if (empty($staff_id) || empty($full_name) || empty($email)) {
            header("Location: admin_staff.php?error=missing_fields");
            exit();
        }

        // Verify staff exists and is indeed a staff account
        $chk_target = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? AND role = 'staff' LIMIT 1");
        $chk_target->bind_param("i", $staff_id);
        $chk_target->execute();
        if ($chk_target->get_result()->num_rows === 0) {
            header("Location: admin_staff.php?error=not_found");
            exit();
        }

        // Check email uniqueness excluding current staff
        $chk_email = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ? LIMIT 1");
        $chk_email->bind_param("si", $email, $staff_id);
        $chk_email->execute();
        if ($chk_email->get_result()->num_rows > 0) {
            header("Location: admin_staff.php?error=email_exists");
            exit();
        }

        $perms_json = json_encode(array_values(is_array($perms) ? $perms : []));

        if (!empty($password)) {
            // Updating password
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, password = ?, plain_password = ?, permissions = ? WHERE user_id = ? AND role = 'staff'");
            $upd->bind_param("ssssssi", $full_name, $email, $phone, $hashed, $password, $perms_json, $staff_id);
        } else {
            // Keeping existing password
            $upd = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, permissions = ? WHERE user_id = ? AND role = 'staff'");
            $upd->bind_param("ssssi", $full_name, $email, $phone, $perms_json, $staff_id);
        }

        if ($upd->execute()) {
            header("Location: admin_staff.php?msg=updated");
        } else {
            header("Location: admin_staff.php?error=db_error");
        }
        exit();
    }
}

// ==========================================
// 2. HANDLE GET ACTIONS (DELETE)
// ==========================================
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $delete_id = intval($_GET['id']);
    // Prevent deleting admin or customer via staff management
    $del = $conn->prepare("DELETE FROM users WHERE user_id = ? AND role = 'staff'");
    $del->bind_param("i", $delete_id);
    if ($del->execute() && $del->affected_rows > 0) {
        header("Location: admin_staff.php?msg=deleted");
    } else {
        header("Location: admin_staff.php?error=delete_failed");
    }
    exit();
}

// ==========================================
// 3. METRICS & COUNTERS
// ==========================================
$total_staff = 0;
$online_staff = 0;
$offline_staff = 0;

$cnt_res = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN is_online = 1 AND last_active >= NOW() - INTERVAL 5 MINUTE THEN 1 ELSE 0 END) as online_count
    FROM users WHERE role = 'staff'");
if ($cnt_res) {
    $row = $cnt_res->fetch_assoc();
    $total_staff   = intval($row['total'] ?? 0);
    $online_staff  = intval($row['online_count'] ?? 0);
    $offline_staff = max(0, $total_staff - $online_staff);
}

// ==========================================
// 4. SEARCH & FILTER QUERY
// ==========================================
$search        = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? 'all');
$task_filter   = trim($_GET['task'] ?? 'all');

$where_clauses = ["role = 'staff'"];

if (!empty($search)) {
    $safe_search = $conn->real_escape_string($search);
    $where_clauses[] = "(full_name LIKE '%$safe_search%' OR email LIKE '%$safe_search%' OR phone LIKE '%$safe_search%')";
}

if ($status_filter === 'online') {
    $where_clauses[] = "(is_online = 1 AND last_active >= NOW() - INTERVAL 5 MINUTE)";
} elseif ($status_filter === 'offline') {
    $where_clauses[] = "(is_online = 0 OR last_active IS NULL OR last_active < NOW() - INTERVAL 5 MINUTE)";
}

if ($task_filter !== 'all' && isset(STAFF_TASKS[$task_filter])) {
    $safe_task = $conn->real_escape_string($task_filter);
    $where_clauses[] = "(permissions LIKE '%\"$safe_task\"%')";
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);
$staff_list_query = "SELECT user_id, full_name, email, phone, password, plain_password, permissions, created_at, last_active, is_online,
    (CASE WHEN is_online = 1 AND last_active >= NOW() - INTERVAL 5 MINUTE THEN 1 ELSE 0 END) as is_active_now
    FROM users $where_sql ORDER BY is_active_now DESC, created_at DESC";
$staff_records = $conn->query($staff_list_query);

// Keep track of tasks defined
$defined_tasks = STAFF_TASKS;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management & Monitoring | JEMmerific</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: -0.01em; background-color: #FAFBFF; }
        .glass-card { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); }
        .stat-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 15px 25px -5px rgba(0, 0, 0, 0.05); }
        .online-dot { animation: pulseGlow 2s infinite ease-in-out; }
        @keyframes pulseGlow {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #E2E8F0; border-radius: 10px; }
    </style>
</head>
<body class="flex min-h-screen text-slate-800 antialiased">

    <!-- Admin Sidebar Navigation -->
    <?php require_once 'includes/admin_sidebar.php'; ?>

    <!-- Main Workspace Container -->
    <main class="flex-1 lg:ml-64 p-4 md:p-8 transition-all min-h-screen flex flex-col">
        
        <!-- Header Bar -->
        <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
            <div class="flex items-center gap-3.5">
                <button type="button" onclick="toggleSidebar()" class="lg:hidden p-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-sm text-slate-600 dark:text-slate-200">
                    <i class="bi bi-list text-2xl"></i>
                </button>
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">Staff Management</h1>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-purple-100 text-purple-700 dark:bg-purple-900/60 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                            Access Control
                        </span>
                    </div>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 font-medium mt-1">
                        Create staff accounts, monitor active status, view login credentials, and configure section permissions.
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3 w-full md:w-auto">
                <button type="button" onclick="window.location.reload()" class="p-3 bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700 text-slate-600 dark:text-slate-300 rounded-2xl hover:bg-slate-50 dark:hover:bg-slate-700 transition shadow-2xs" title="Refresh Live Status">
                    <i class="bi bi-arrow-clockwise text-lg"></i>
                </button>
                <button type="button" onclick="openAddStaffModal()" class="flex-1 md:flex-initial flex items-center justify-center gap-2 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-extrabold px-6 py-3.5 rounded-2xl shadow-lg shadow-purple-500/25 hover:shadow-purple-500/40 transform hover:-translate-y-0.5 transition-all text-xs uppercase tracking-wider">
                    <i class="bi bi-person-plus-fill text-base"></i>
                    <span>Add New Staff</span>
                </button>
            </div>
        </header>

        <!-- Metric Stat Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <!-- Total Staff -->
            <div class="stat-card bg-white dark:bg-[#131b2e] p-5 rounded-3xl border border-slate-100 dark:border-slate-800 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center text-xl shrink-0">
                    <i class="bi bi-person-badge-fill"></i>
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-400">Total Staff</p>
                    <p class="text-2xl font-black text-slate-900 dark:text-white mt-0.5"><?= $total_staff ?></p>
                </div>
            </div>

            <!-- Online Now -->
            <div class="stat-card bg-white dark:bg-[#131b2e] p-5 rounded-3xl border border-slate-100 dark:border-slate-800 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl shrink-0 relative">
                    <i class="bi bi-broadcast"></i>
                    <span class="absolute top-2 right-2 w-2.5 h-2.5 bg-emerald-500 rounded-full online-dot"></span>
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-400">Online Now</p>
                    <div class="flex items-center gap-2 mt-0.5">
                        <span class="text-2xl font-black text-emerald-600 dark:text-emerald-400"><?= $online_staff ?></span>
                        <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">Active</span>
                    </div>
                </div>
            </div>

            <!-- Offline Staff -->
            <div class="stat-card bg-white dark:bg-[#131b2e] p-5 rounded-3xl border border-slate-100 dark:border-slate-800 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 flex items-center justify-center text-xl shrink-0">
                    <i class="bi bi-moon-fill"></i>
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-400">Offline</p>
                    <p class="text-2xl font-black text-slate-700 dark:text-slate-300 mt-0.5"><?= $offline_staff ?></p>
                </div>
            </div>

            <!-- Configured Tasks -->
            <div class="stat-card bg-white dark:bg-[#131b2e] p-5 rounded-3xl border border-slate-100 dark:border-slate-800 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xl shrink-0">
                    <i class="bi bi-shield-check"></i>
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-400">Available Tasks</p>
                    <p class="text-2xl font-black text-slate-900 dark:text-white mt-0.5"><?= count($defined_tasks) ?> Sections</p>
                </div>
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <div class="glass-card bg-white/90 dark:bg-[#131b2e]/90 p-4 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-sm mb-6">
            <form action="admin_staff.php" method="GET" class="flex flex-col md:flex-row gap-3">
                <!-- Search Text -->
                <div class="relative flex-1">
                    <i class="bi bi-search absolute left-4 top-3.5 text-slate-400"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search staff by name, email, or phone..." 
                           class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/80 rounded-2xl text-xs sm:text-sm font-semibold text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-purple-500/30">
                </div>

                <!-- Status Filter -->
                <div class="w-full md:w-44">
                    <select name="status" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/80 rounded-2xl text-xs font-bold text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-purple-500/30">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="online" <?= $status_filter === 'online' ? 'selected' : '' ?>>🟢 Online Now</option>
                        <option value="offline" <?= $status_filter === 'offline' ? 'selected' : '' ?>>⚪ Offline</option>
                    </select>
                </div>

                <!-- Task Filter -->
                <div class="w-full md:w-52">
                    <select name="task" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/80 rounded-2xl text-xs font-bold text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-purple-500/30">
                        <option value="all" <?= $task_filter === 'all' ? 'selected' : '' ?>>All Assigned Tasks</option>
                        <?php foreach ($defined_tasks as $t_key => $t_info): ?>
                            <option value="<?= $t_key ?>" <?= $task_filter === $t_key ? 'selected' : '' ?>><?= $t_info['title'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Submit Filter Button -->
                <button type="submit" class="bg-slate-900 dark:bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-2xl text-xs font-black uppercase tracking-wider transition shadow-sm">
                    Filter
                </button>
                <?php if (!empty($search) || $status_filter !== 'all' || $task_filter !== 'all'): ?>
                    <a href="admin_staff.php" class="p-3 bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-300 hover:text-slate-800 dark:hover:text-white rounded-2xl text-center text-xs font-bold flex items-center justify-center transition">
                        Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Staff Accounts Data Table -->
        <div class="bg-white dark:bg-[#131b2e] rounded-3xl overflow-hidden shadow-sm border border-slate-200/80 dark:border-slate-800 flex-1">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50/70 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-800 text-[10px] font-black text-slate-400 dark:text-slate-400 uppercase tracking-widest">
                        <tr>
                            <th class="px-6 py-4">Staff Member</th>
                            <th class="px-6 py-4">Contact & Login Credentials</th>
                            <th class="px-6 py-4">Assigned Tasks / Sections</th>
                            <th class="px-6 py-4 text-center">Online Status</th>
                            <th class="px-6 py-4 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
                        <?php if ($staff_records && $staff_records->num_rows > 0): ?>
                            <?php while ($staff = $staff_records->fetch_assoc()): ?>
                                <?php 
                                    $is_online = (intval($staff['is_active_now']) === 1);
                                    $perms = json_decode($staff['permissions'] ?? '[]', true) ?: [];
                                    $plain_pass = $staff['plain_password'] ?? '';
                                    // Visual color for avatar
                                    $name_initial = strtoupper(substr($staff['full_name'], 0, 1));
                                    $avatar_colors = ['bg-purple-100 text-purple-700', 'bg-indigo-100 text-indigo-700', 'bg-blue-100 text-blue-700', 'bg-emerald-100 text-emerald-700', 'bg-amber-100 text-amber-700'];
                                    $avatar_theme = $avatar_colors[ord($name_initial) % count($avatar_colors)];
                                ?>
                                <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
                                    <!-- Staff Profile -->
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3.5">
                                            <div class="relative shrink-0">
                                                <div class="w-11 h-11 rounded-2xl <?= $avatar_theme ?> font-black text-sm flex items-center justify-center shadow-2xs border border-white dark:border-slate-700">
                                                    <?= $name_initial ?>
                                                </div>
                                                <!-- Online Status Dot -->
                                                <span class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 rounded-full border-2 border-white dark:border-slate-900 <?= $is_online ? 'bg-emerald-500 online-dot' : 'bg-slate-300 dark:bg-slate-600' ?>" title="<?= $is_online ? 'Active now' : 'Offline' ?>"></span>
                                            </div>
                                            <div>
                                                <p class="font-extrabold text-slate-900 dark:text-white text-sm"><?= htmlspecialchars($staff['full_name']) ?></p>
                                                <div class="flex items-center gap-2 mt-0.5">
                                                    <span class="text-[10px] font-bold text-purple-600 dark:text-purple-400 uppercase tracking-wider">#STF-<?= str_pad($staff['user_id'], 4, '0', STR_PAD_LEFT) ?></span>
                                                    <span class="text-slate-300 dark:text-slate-600">•</span>
                                                    <span class="text-[10px] text-slate-400">Created <?= date('M d, Y', strtotime($staff['created_at'])) ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Contact Info & Credentials -->
                                    <td class="px-6 py-4">
                                        <div class="space-y-1.5">
                                            <div class="flex items-center gap-2">
                                                <i class="bi bi-envelope text-slate-400 text-xs"></i>
                                                <span class="font-semibold text-slate-800 dark:text-slate-200"><?= htmlspecialchars($staff['email']) ?></span>
                                                <button type="button" onclick="copyToClipboard('<?= addslashes($staff['email']) ?>', 'Email copied!')" class="text-slate-400 hover:text-purple-600 text-[11px]" title="Copy Email">
                                                    <i class="bi bi-copy"></i>
                                                </button>
                                            </div>
                                            <div class="flex items-center gap-2 text-slate-500 dark:text-slate-400 text-[11px]">
                                                <i class="bi bi-telephone text-slate-400"></i>
                                                <span><?= !empty($staff['phone']) ? htmlspecialchars($staff['phone']) : '<span class="italic text-slate-400">No phone provided</span>' ?></span>
                                            </div>
                                            <!-- Password Viewer (Admin View) -->
                                            <div class="flex items-center gap-2 pt-0.5">
                                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Pass:</span>
                                                <div class="flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-lg border border-slate-200/60 dark:border-slate-700">
                                                    <span id="pass_mask_<?= $staff['user_id'] ?>" class="font-mono text-xs text-slate-600 dark:text-slate-300 font-bold">••••••••</span>
                                                    <span id="pass_raw_<?= $staff['user_id'] ?>" class="font-mono text-xs text-purple-700 dark:text-purple-300 font-extrabold hidden select-all"><?= htmlspecialchars($plain_pass ?: '••••••••') ?></span>
                                                    <?php if (!empty($plain_pass)): ?>
                                                        <button type="button" onclick="togglePassVisibility(<?= $staff['user_id'] ?>)" class="text-slate-400 hover:text-purple-600 text-xs ml-1" title="Toggle password view">
                                                            <i id="pass_eye_<?= $staff['user_id'] ?>" class="bi bi-eye"></i>
                                                        </button>
                                                        <button type="button" onclick="copyToClipboard('<?= addslashes($plain_pass) ?>', 'Password copied!')" class="text-slate-400 hover:text-purple-600 text-xs" title="Copy password">
                                                            <i class="bi bi-copy"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Assigned Tasks & Permissions -->
                                    <td class="px-6 py-4">
                                        <?php if (!empty($perms)): ?>
                                            <div class="flex flex-wrap gap-1.5 max-w-xs">
                                                <?php foreach ($perms as $p_key): ?>
                                                    <?php if (isset($defined_tasks[$p_key])): ?>
                                                        <?php $t_obj = $defined_tasks[$p_key]; ?>
                                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-[10px] font-bold border <?= $t_obj['badge_color'] ?>" title="<?= $t_obj['desc'] ?>">
                                                            <i class="bi <?= $t_obj['icon'] ?>"></i>
                                                            <span><?= $t_obj['short_title'] ?></span>
                                                        </span>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="inline-block px-2.5 py-1 rounded-xl text-[10px] font-bold bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400 border border-slate-200 dark:border-slate-700 italic">
                                                No sections assigned
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Online Status & Activity Monitoring -->
                                    <td class="px-6 py-4 text-center">
                                        <div class="inline-flex flex-col items-center gap-1">
                                            <?php if ($is_online): ?>
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 shadow-2xs">
                                                    <span class="w-2 h-2 rounded-full bg-emerald-500 online-dot"></span>
                                                    <span>Online Now</span>
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                                                    <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                                                    <span>Offline</span>
                                                </span>
                                            <?php endif; ?>
                                            <span class="text-[10px] text-slate-400 dark:text-slate-500 font-medium">
                                                <?= format_last_active($staff['last_active']) ?>
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Action Buttons -->
                                    <td class="px-6 py-4 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <!-- Monitor / View Details Modal Trigger -->
                                            <button type="button" onclick="openViewStaffModal(<?= htmlspecialchars(json_encode($staff)) ?>)" class="p-2.5 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-300 hover:bg-purple-600 hover:text-white transition shadow-2xs" title="Monitor Account & View Credentials">
                                                <i class="bi bi-eye-fill"></i>
                                            </button>
                                            
                                            <!-- Edit Staff Modal Trigger -->
                                            <button type="button" onclick="openEditStaffModal(<?= htmlspecialchars(json_encode($staff)) ?>)" class="p-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-indigo-600 hover:text-white transition shadow-2xs" title="Edit Staff & Permissions">
                                                <i class="bi bi-pencil-fill"></i>
                                            </button>

                                            <!-- Delete Staff Account -->
                                            <button type="button" onclick="confirmDeleteStaff(<?= $staff['user_id'] ?>, '<?= addslashes($staff['full_name']) ?>')" class="p-2.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 hover:bg-rose-600 hover:text-white transition shadow-2xs" title="Delete Staff Account">
                                                <i class="bi bi-trash3-fill"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="py-20 text-center">
                                    <div class="flex flex-col items-center justify-center gap-3">
                                        <div class="w-16 h-16 rounded-3xl bg-purple-50 dark:bg-purple-950/60 text-purple-400 flex items-center justify-center text-3xl">
                                            <i class="bi bi-people"></i>
                                        </div>
                                        <p class="text-sm font-extrabold text-slate-700 dark:text-slate-200">No Staff Accounts Found</p>
                                        <p class="text-xs text-slate-400 max-w-sm">No staff members match the selected filters. Click "+ Add New Staff" to create an account with customized section permissions.</p>
                                        <button type="button" onclick="openAddStaffModal()" class="mt-2 px-5 py-2.5 rounded-xl bg-purple-600 text-white font-bold text-xs uppercase tracking-wider hover:bg-purple-700 transition">
                                            Create First Staff Account
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- ============================================================== -->
    <!-- 1. ADD NEW STAFF MODAL -->
    <!-- ============================================================== -->
    <div id="addStaffModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white dark:bg-[#131b2e] w-full max-w-xl rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 p-6 sm:p-8 my-auto relative animate-in fade-in zoom-in-95 duration-200">
            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 mb-6">
                <div>
                    <h3 class="text-xl font-black text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="bi bi-person-plus-fill text-purple-600"></i> Add Staff Account
                    </h3>
                    <p class="text-xs text-slate-400 mt-0.5">Create a login profile and configure dashboard section tasks.</p>
                </div>
                <button type="button" onclick="closeAddStaffModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                    <i class="bi bi-x-lg text-lg"></i>
                </button>
            </div>

            <!-- Form -->
            <form action="admin_staff.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add_staff">

                <!-- Full Name -->
                <div>
                    <label class="block text-[11px] font-black uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5 ml-1">Staff Full Name</label>
                    <div class="relative">
                        <i class="bi bi-person absolute left-4 top-3.5 text-slate-400 text-sm"></i>
                        <input type="text" name="full_name" required placeholder="e.g. Maria Santos" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-2xl text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-purple-500/30">
                    </div>
                </div>

                <!-- Email & Phone in 2-cols -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-black uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5 ml-1">Email Address (Login)</label>
                        <div class="relative">
                            <i class="bi bi-envelope absolute left-4 top-3.5 text-slate-400 text-sm"></i>
                            <input type="email" name="email" required placeholder="staff@jemmerific.com" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-2xl text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-purple-500/30">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-black uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5 ml-1">Phone Number</label>
                        <div class="relative">
                            <i class="bi bi-telephone absolute left-4 top-3.5 text-slate-400 text-sm"></i>
                            <input type="text" name="phone" placeholder="0912 345 6789" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-2xl text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-purple-500/30">
                        </div>
                    </div>
                </div>

                <!-- Password with generator -->
                <div>
                    <div class="flex items-center justify-between mb-1.5 ml-1">
                        <label class="text-[11px] font-black uppercase tracking-wider text-slate-600 dark:text-slate-300">Staff Password</label>
                        <button type="button" onclick="generateStaffPassword('add_pass')" class="text-[10px] font-extrabold text-purple-600 dark:text-purple-400 hover:underline uppercase tracking-wider">
                            <i class="bi bi-magic mr-1"></i> Generate Password
                        </button>
                    </div>
                    <div class="relative">
                        <i class="bi bi-shield-lock absolute left-4 top-3.5 text-slate-400 text-sm"></i>
                        <input type="text" id="add_pass" name="password" required placeholder="Enter login password" class="w-full pl-11 pr-11 py-3 bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-2xl text-xs sm:text-sm font-mono font-bold text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-purple-500/30">
                    </div>
                    <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1 ml-1">The password will be securely hashed for login, and saved for admin monitoring and view.</p>
                </div>

                <!-- Tasks / Permissions Selection -->
                <div class="pt-2">
                    <div class="flex items-center justify-between mb-2 ml-1">
                        <label class="text-[11px] font-black uppercase tracking-wider text-slate-600 dark:text-slate-300">Assign Dashboard Tasks & Permissions</label>
                        <div class="flex gap-2">
                            <button type="button" onclick="toggleAllCheckboxes('#addStaffModal', true)" class="text-[10px] font-bold text-purple-600 dark:text-purple-400 hover:underline">Select All</button>
                            <span class="text-slate-300 dark:text-slate-600">•</span>
                            <button type="button" onclick="toggleAllCheckboxes('#addStaffModal', false)" class="text-[10px] font-bold text-slate-500 hover:underline">Clear</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <?php foreach ($defined_tasks as $t_key => $t_info): ?>
                            <label class="flex items-start gap-3 p-3 rounded-2xl border border-slate-200/80 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/40 hover:bg-purple-50/50 dark:hover:bg-purple-950/20 cursor-pointer transition select-none">
                                <input type="checkbox" name="permissions[]" value="<?= $t_key ?>" class="mt-1 w-4 h-4 rounded text-purple-600 focus:ring-purple-500/30">
                                <div>
                                    <div class="flex items-center gap-1.5">
                                        <i class="bi <?= $t_info['icon'] ?> text-purple-600 text-xs"></i>
                                        <span class="text-xs font-black text-slate-900 dark:text-white"><?= $t_info['title'] ?></span>
                                    </div>
                                    <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5 leading-snug"><?= $t_info['desc'] ?></p>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" onclick="closeAddStaffModal()" class="px-5 py-3 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold text-xs uppercase tracking-wider hover:bg-slate-200 transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-7 py-3 rounded-2xl bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-black text-xs uppercase tracking-wider shadow-lg shadow-purple-500/25 transition">
                        Create Staff Account
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- 2. EDIT STAFF MODAL -->
    <!-- ============================================================== -->
    <div id="editStaffModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white dark:bg-[#131b2e] w-full max-w-xl rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 p-6 sm:p-8 my-auto relative animate-in fade-in zoom-in-95 duration-200">
            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 mb-6">
                <div>
                    <h3 class="text-xl font-black text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="bi bi-pencil-square text-indigo-600"></i> Edit Staff Account
                    </h3>
                    <p class="text-xs text-slate-400 mt-0.5">Modify staff contact information, login password, and permissions.</p>
                </div>
                <button type="button" onclick="closeEditStaffModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                    <i class="bi bi-x-lg text-lg"></i>
                </button>
            </div>

            <!-- Form -->
            <form action="admin_staff.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="edit_staff">
                <input type="hidden" id="edit_staff_id" name="staff_id" value="">

                <!-- Full Name -->
                <div>
                    <label class="block text-[11px] font-black uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5 ml-1">Staff Full Name</label>
                    <div class="relative">
                        <i class="bi bi-person absolute left-4 top-3.5 text-slate-400 text-sm"></i>
                        <input type="text" id="edit_full_name" name="full_name" required class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-2xl text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-purple-500/30">
                    </div>
                </div>

                <!-- Email & Phone -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-black uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5 ml-1">Email Address</label>
                        <div class="relative">
                            <i class="bi bi-envelope absolute left-4 top-3.5 text-slate-400 text-sm"></i>
                            <input type="email" id="edit_email" name="email" required class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-2xl text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-purple-500/30">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-black uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5 ml-1">Phone Number</label>
                        <div class="relative">
                            <i class="bi bi-telephone absolute left-4 top-3.5 text-slate-400 text-sm"></i>
                            <input type="text" id="edit_phone" name="phone" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-2xl text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-purple-500/30">
                        </div>
                    </div>
                </div>

                <!-- Change Password (Optional) -->
                <div>
                    <div class="flex items-center justify-between mb-1.5 ml-1">
                        <label class="text-[11px] font-black uppercase tracking-wider text-slate-600 dark:text-slate-300">
                            Reset Password <span class="text-slate-400 text-[10px] font-medium lowercase">(leave blank to keep current)</span>
                        </label>
                        <button type="button" onclick="generateStaffPassword('edit_pass')" class="text-[10px] font-extrabold text-purple-600 dark:text-purple-400 hover:underline uppercase tracking-wider">
                            <i class="bi bi-magic mr-1"></i> Generate
                        </button>
                    </div>
                    <div class="relative">
                        <i class="bi bi-shield-lock absolute left-4 top-3.5 text-slate-400 text-sm"></i>
                        <input type="text" id="edit_pass" name="password" placeholder="Enter new password if updating" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-2xl text-xs sm:text-sm font-mono font-bold text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-purple-500/30">
                    </div>
                </div>

                <!-- Tasks / Permissions Selection -->
                <div class="pt-2">
                    <div class="flex items-center justify-between mb-2 ml-1">
                        <label class="text-[11px] font-black uppercase tracking-wider text-slate-600 dark:text-slate-300">Assign Dashboard Tasks & Permissions</label>
                        <div class="flex gap-2">
                            <button type="button" onclick="toggleAllCheckboxes('#editStaffModal', true)" class="text-[10px] font-bold text-purple-600 dark:text-purple-400 hover:underline">Select All</button>
                            <span class="text-slate-300 dark:text-slate-600">•</span>
                            <button type="button" onclick="toggleAllCheckboxes('#editStaffModal', false)" class="text-[10px] font-bold text-slate-500 hover:underline">Clear</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <?php foreach ($defined_tasks as $t_key => $t_info): ?>
                            <label class="flex items-start gap-3 p-3 rounded-2xl border border-slate-200/80 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/40 hover:bg-purple-50/50 dark:hover:bg-purple-950/20 cursor-pointer transition select-none">
                                <input type="checkbox" id="edit_perm_<?= $t_key ?>" name="permissions[]" value="<?= $t_key ?>" class="mt-1 w-4 h-4 rounded text-purple-600 focus:ring-purple-500/30">
                                <div>
                                    <div class="flex items-center gap-1.5">
                                        <i class="bi <?= $t_info['icon'] ?> text-purple-600 text-xs"></i>
                                        <span class="text-xs font-black text-slate-900 dark:text-white"><?= $t_info['title'] ?></span>
                                    </div>
                                    <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5 leading-snug"><?= $t_info['desc'] ?></p>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" onclick="closeEditStaffModal()" class="px-5 py-3 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold text-xs uppercase tracking-wider hover:bg-slate-200 transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-7 py-3 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-black text-xs uppercase tracking-wider shadow-lg shadow-indigo-500/25 transition">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- 3. VIEW & MONITOR STAFF MODAL -->
    <!-- ============================================================== -->
    <div id="viewStaffModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white dark:bg-[#131b2e] w-full max-w-lg rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 p-6 sm:p-8 my-auto relative animate-in fade-in zoom-in-95 duration-200">
            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 mb-6">
                <div>
                    <h3 class="text-xl font-black text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="bi bi-person-badge text-purple-600"></i> Staff Account Monitor
                    </h3>
                    <p class="text-xs text-slate-400 mt-0.5">Real-time status, credentials, and assigned navigation privileges.</p>
                </div>
                <button type="button" onclick="closeViewStaffModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                    <i class="bi bi-x-lg text-lg"></i>
                </button>
            </div>

            <!-- Profile Overview Header -->
            <div class="flex items-center gap-4 p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700 mb-5">
                <div id="view_avatar" class="w-14 h-14 rounded-2xl bg-purple-600 text-white font-black text-xl flex items-center justify-center shadow-md">
                    S
                </div>
                <div class="flex-1 overflow-hidden">
                    <div class="flex items-center gap-2">
                        <h4 id="view_name" class="text-base font-extrabold text-slate-900 dark:text-white truncate">Staff Name</h4>
                        <span id="view_online_pill" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase">
                            Status
                        </span>
                    </div>
                    <p id="view_id_str" class="text-xs font-bold text-purple-600 dark:text-purple-400 uppercase tracking-wider mt-0.5">#STF-0000</p>
                    <p id="view_last_active" class="text-[10px] text-slate-400 mt-0.5">Last active: Recently</p>
                </div>
            </div>

            <!-- Credentials Box -->
            <div class="space-y-3 mb-5">
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Account Credentials</p>
                <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700 space-y-2.5 text-xs">
                    <!-- Email -->
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400 font-bold uppercase text-[10px]">Email:</span>
                        <div class="flex items-center gap-2">
                            <span id="view_email" class="font-bold text-slate-800 dark:text-slate-200 select-all">staff@email.com</span>
                            <button type="button" onclick="copyViewEmail()" class="text-slate-400 hover:text-purple-600" title="Copy Email">
                                <i class="bi bi-copy"></i>
                            </button>
                        </div>
                    </div>
                    <!-- Password -->
                    <div class="flex items-center justify-between border-t border-slate-100 dark:border-slate-700/60 pt-2">
                        <span class="text-slate-400 font-bold uppercase text-[10px]">Password:</span>
                        <div class="flex items-center gap-2">
                            <span id="view_password" class="font-mono font-extrabold text-purple-700 dark:text-purple-300 bg-purple-50 dark:bg-purple-950/60 px-2.5 py-0.5 rounded-lg border border-purple-200 dark:border-purple-800 select-all">••••••••</span>
                            <button type="button" onclick="copyViewPassword()" class="text-slate-400 hover:text-purple-600" title="Copy Password">
                                <i class="bi bi-copy"></i>
                            </button>
                        </div>
                    </div>
                    <!-- Phone -->
                    <div class="flex items-center justify-between border-t border-slate-100 dark:border-slate-700/60 pt-2">
                        <span class="text-slate-400 font-bold uppercase text-[10px]">Phone:</span>
                        <span id="view_phone" class="font-bold text-slate-800 dark:text-slate-200">Not provided</span>
                    </div>
                </div>
            </div>

            <!-- Assigned Sections Breakdown -->
            <div class="space-y-2 mb-6">
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Assigned Dashboard Privileges</p>
                <div id="view_permissions_list" class="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                    <!-- Dynamic rendering via JS -->
                </div>
            </div>

            <!-- Modal Action Buttons -->
            <div class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-800">
                <button type="button" id="view_edit_btn" class="px-5 py-2.5 rounded-2xl bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300 font-bold text-xs uppercase tracking-wider hover:bg-indigo-100 transition flex items-center gap-1.5">
                    <i class="bi bi-pencil-fill"></i> Edit Account
                </button>
                <button type="button" onclick="closeViewStaffModal()" class="px-6 py-2.5 rounded-2xl bg-slate-900 dark:bg-slate-700 text-white font-bold text-xs uppercase tracking-wider hover:bg-purple-600 transition">
                    Done
                </button>
            </div>
        </div>
    </div>

    <!-- JavaScript Handlers -->
    <script>
        const DEFINED_TASKS = <?= json_encode($defined_tasks) ?>;
        let currentViewingStaff = null;

        // Toggle Password Mask/Raw in Table
        function togglePassVisibility(userId) {
            const mask = document.getElementById(`pass_mask_${userId}`);
            const raw = document.getElementById(`pass_raw_${userId}`);
            const eye = document.getElementById(`pass_eye_${userId}`);
            if (raw.classList.contains('hidden')) {
                raw.classList.remove('hidden');
                mask.classList.add('hidden');
                eye.className = 'bi bi-eye-slash';
            } else {
                raw.classList.add('hidden');
                mask.classList.remove('hidden');
                eye.className = 'bi bi-eye';
            }
        }

        // Copy Helper
        function copyToClipboard(text, successTitle) {
            if (!text) return;
            navigator.clipboard.writeText(text).then(() => {
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000,
                    timerProgressBar: true
                });
                Toast.fire({
                    icon: 'success',
                    title: successTitle || 'Copied to clipboard!'
                });
            });
        }

        function copyViewEmail() {
            if (currentViewingStaff) copyToClipboard(currentViewingStaff.email, 'Email copied!');
        }

        function copyViewPassword() {
            if (currentViewingStaff) copyToClipboard(currentViewingStaff.plain_password || currentViewingStaff.password, 'Password copied!');
        }

        // Generate Random Strong Staff Password
        function generateStaffPassword(targetInputId) {
            const charset = "abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#$%";
            let pass = "";
            for (let i = 0; i < 10; i++) {
                pass += charset.charAt(Math.floor(Math.random() * charset.length));
            }
            document.getElementById(targetInputId).value = pass;
        }

        // Toggle All Checkboxes
        function toggleAllCheckboxes(modalSelector, checkState) {
            const modal = document.querySelector(modalSelector);
            if (modal) {
                const cbs = modal.querySelectorAll('input[type="checkbox"][name="permissions[]"]');
                cbs.forEach(cb => cb.checked = checkState);
            }
        }

        // Modal Controls: Add Staff
        function openAddStaffModal() {
            document.getElementById('addStaffModal').classList.remove('hidden');
        }
        function closeAddStaffModal() {
            document.getElementById('addStaffModal').classList.add('hidden');
        }

        // Modal Controls: Edit Staff
        function openEditStaffModal(staff) {
            document.getElementById('edit_staff_id').value = staff.user_id;
            document.getElementById('edit_full_name').value = staff.full_name;
            document.getElementById('edit_email').value = staff.email;
            document.getElementById('edit_phone').value = staff.phone || '';
            document.getElementById('edit_pass').value = '';

            // Check permissions
            let perms = [];
            try {
                perms = typeof staff.permissions === 'string' ? JSON.parse(staff.permissions) : (staff.permissions || []);
            } catch(e) { perms = []; }

            for (const key in DEFINED_TASKS) {
                const cb = document.getElementById(`edit_perm_${key}`);
                if (cb) {
                    cb.checked = perms.includes(key);
                }
            }

            document.getElementById('editStaffModal').classList.remove('hidden');
        }
        function closeEditStaffModal() {
            document.getElementById('editStaffModal').classList.add('hidden');
        }

        // Modal Controls: View / Monitor Staff
        function openViewStaffModal(staff) {
            currentViewingStaff = staff;
            document.getElementById('view_avatar').innerText = staff.full_name ? staff.full_name.charAt(0).toUpperCase() : 'S';
            document.getElementById('view_name').innerText = staff.full_name;
            document.getElementById('view_id_str').innerText = '#STF-' + String(staff.user_id).padStart(4, '0');
            document.getElementById('view_email').innerText = staff.email;
            document.getElementById('view_phone').innerText = staff.phone || 'Not provided';
            document.getElementById('view_password').innerText = staff.plain_password || staff.password || '••••••••';
            document.getElementById('view_last_active').innerText = staff.last_active ? 'Last active: ' + staff.last_active : 'Never logged in';

            // Online Pill
            const isOnline = (parseInt(staff.is_active_now) === 1);
            const pill = document.getElementById('view_online_pill');
            if (isOnline) {
                pill.className = "inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800";
                pill.innerHTML = '<span class="w-2 h-2 rounded-full bg-emerald-500 online-dot"></span> Online Now';
            } else {
                pill.className = "inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[9px] font-bold bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400 border border-slate-200 dark:border-slate-700";
                pill.innerHTML = '<span class="w-2 h-2 rounded-full bg-slate-400"></span> Offline';
            }

            // Permissions List
            let perms = [];
            try {
                perms = typeof staff.permissions === 'string' ? JSON.parse(staff.permissions) : (staff.permissions || []);
            } catch(e) { perms = []; }

            const listContainer = document.getElementById('view_permissions_list');
            listContainer.innerHTML = '';

            for (const key in DEFINED_TASKS) {
                const t = DEFINED_TASKS[key];
                const hasPerm = perms.includes(key);
                const itemDiv = document.createElement('div');
                itemDiv.className = `p-2.5 rounded-xl border flex items-center justify-between text-xs ${hasPerm ? 'bg-purple-50/60 dark:bg-purple-950/40 border-purple-200 dark:border-purple-800 text-purple-900 dark:text-purple-200' : 'bg-slate-50 dark:bg-slate-800/40 border-slate-200/60 dark:border-slate-700 text-slate-400 dark:text-slate-500 opacity-60'}`;
                itemDiv.innerHTML = `
                    <div class="flex items-center gap-2">
                        <i class="bi ${t.icon} text-sm"></i>
                        <span class="font-extrabold">${t.title}</span>
                    </div>
                    <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded-full ${hasPerm ? 'bg-purple-600 text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-500'}">
                        ${hasPerm ? 'Permitted' : 'Locked'}
                    </span>
                `;
                listContainer.appendChild(itemDiv);
            }

            // Edit button hook
            document.getElementById('view_edit_btn').onclick = function() {
                closeViewStaffModal();
                openEditStaffModal(staff);
            };

            document.getElementById('viewStaffModal').classList.remove('hidden');
        }
        function closeViewStaffModal() {
            document.getElementById('viewStaffModal').classList.add('hidden');
        }

        // Delete Confirmation with SweetAlert2
        function confirmDeleteStaff(id, name) {
            Swal.fire({
                title: 'Delete Staff Account?',
                text: `Are you sure you want to delete ${name}'s staff account? They will immediately lose access to all dashboard sections.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e11d48',
                cancelButtonColor: '#94a3b8',
                confirmButtonText: 'Yes, Delete Account',
                cancelButtonText: 'Cancel',
                customClass: {
                    popup: 'rounded-3xl',
                    confirmButton: 'rounded-xl px-6 py-2.5 text-xs font-black uppercase tracking-wider',
                    cancelButton: 'rounded-xl px-6 py-2.5 text-xs font-black uppercase tracking-wider'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `admin_staff.php?action=delete&id=${id}`;
                }
            });
        }

        // Feedback Toasts
        <?php if (isset($_GET['msg'])): ?>
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3500,
                timerProgressBar: true
            });
            <?php if ($_GET['msg'] === 'added'): ?>
                Toast.fire({ icon: 'success', title: 'Staff account successfully created!' });
            <?php elseif ($_GET['msg'] === 'updated'): ?>
                Toast.fire({ icon: 'success', title: 'Staff details and permissions updated!' });
            <?php elseif ($_GET['msg'] === 'deleted'): ?>
                Toast.fire({ icon: 'success', title: 'Staff account has been removed.' });
            <?php endif; ?>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            Swal.fire({
                title: 'Operation Failed',
                text: '<?= $_GET['error'] === 'email_exists' ? 'This email address is already registered to another account.' : ($_GET['error'] === 'missing_fields' ? 'Please fill in all required fields.' : 'An error occurred while processing the staff account.') ?>',
                icon: 'error',
                confirmButtonColor: '#7c3aed',
                customClass: { popup: 'rounded-3xl' }
            });
        <?php endif; ?>
    </script>
</body>
</html>

