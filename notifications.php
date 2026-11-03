<?php
require_once 'includes/auth_check.php';
require_once 'db_config.php';
require_once 'includes/booking_completion_helper.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'Valued Client';

// Auto-complete finished catering events and notify
checkAndCompleteFinishedBookings($conn, $user_id);

// 1. Handle Mark All as Read
if (isset($_GET['action']) && $_GET['action'] == 'mark_read') {
    $stmt_mr = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt_mr->bind_param("i", $user_id);
    $stmt_mr->execute();
    header("Location: notifications.php");
    exit();
}

// 2. Handle Delete Single Notification
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $notif_id = intval($_GET['id']);
    $stmt_del = $conn->prepare("DELETE FROM notifications WHERE notif_id = ? AND user_id = ?");
    $stmt_del->bind_param("ii", $notif_id, $user_id);
    $stmt_del->execute();
    header("Location: notifications.php?msg=deleted");
    exit();
}

// 3. Handle Clear All Notifications
if (isset($_GET['action']) && $_GET['action'] == 'delete_all') {
    $stmt_da = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
    $stmt_da->bind_param("i", $user_id);
    $stmt_da->execute();
    header("Location: notifications.php?msg=cleared");
    exit();
}

// Fetch Unread Count
$unread_stmt = $conn->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
$unread_stmt->bind_param("i", $user_id);
$unread_stmt->execute();
$unread_count = $unread_stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Fetch All Notifications
$n_stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$n_stmt->bind_param("i", $user_id);
$n_stmt->execute();
$notifications = $n_stmt->get_result();
$total_notifications = $notifications->num_rows;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications & Activity | JEMmerific</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F8FAFC; }
    </style>
</head>
<body class="flex min-h-screen text-slate-800">

    <!-- Customer Sidebar Include -->
    <?php require_once 'includes/customer_sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="flex-1 lg:ml-64 p-4 md:p-6 transition-all">
        
        <!-- Top Navigation Header -->
        <header class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8 pb-6 border-b border-slate-200/80">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="lg:hidden p-2.5 bg-white border border-slate-200 rounded-xl text-slate-600 hover:bg-slate-50 transition-colors shadow-2xs">
                    <i class="bi bi-list text-xl"></i>
                </button>
                <div>
                    <h2 class="text-2xl font-extrabold text-slate-900 tracking-tight">Notifications & Activity</h2>
                    <p class="text-xs font-semibold text-slate-500 mt-0.5">Stay updated on your catering event bookings, payments, and admin status changes.</p>
                </div>
            </div>

            <div class="flex items-center gap-3 flex-wrap">
                <?php if ($unread_count > 0): ?>
                <a href="notifications.php?action=mark_read" class="px-4 py-2 bg-purple-50 hover:bg-purple-100 text-purple-700 border border-purple-200 font-bold rounded-xl text-xs uppercase tracking-wider transition-all flex items-center gap-2">
                    <i class="bi bi-check-all text-base"></i> Mark All as Read (<?= $unread_count ?>)
                </a>
                <?php endif; ?>

                <?php if ($total_notifications > 0): ?>
                <a href="notifications.php?action=delete_all" 
                   onclick="return confirm('Are you sure you want to clear all notifications?');" 
                   class="px-4 py-2 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 font-bold rounded-xl text-xs uppercase tracking-wider transition-all flex items-center gap-2">
                    <i class="bi bi-trash3-fill text-sm"></i> Clear All
                </a>
                <?php endif; ?>
            </div>
        </header>

        <!-- Notifications Container -->
        <div class="max-w-4xl space-y-4">
            <?php if ($total_notifications > 0): ?>
                <?php while ($n = $notifications->fetch_assoc()): ?>
                    <?php $is_unread = ($n['is_read'] == 0); ?>
                    <div class="p-5 bg-white rounded-2xl border <?= $is_unread ? 'border-purple-300 shadow-xs bg-purple-50/20' : 'border-slate-200/80' ?> flex items-start justify-between gap-4 transition-all hover:border-purple-200">
                        <div class="flex items-start gap-4 flex-1">
                            <div class="w-10 h-10 rounded-xl <?= $is_unread ? 'bg-purple-600 text-white' : 'bg-slate-100 text-slate-500' ?> flex items-center justify-center text-lg font-bold shrink-0 mt-0.5">
                                <i class="bi <?= $is_unread ? 'bi-bell-fill' : 'bi-bell' ?>"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-bold text-slate-900 leading-snug"><?= htmlspecialchars($n['message']) ?></p>
                                <p class="text-xs font-medium text-slate-400 mt-1 flex items-center gap-1">
                                    <i class="bi bi-clock text-slate-300"></i> <?= date('F d, Y \a\t h:i A', strtotime($n['created_at'])) ?>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <?php if ($is_unread): ?>
                                <span class="px-2.5 py-0.5 bg-rose-500 text-white text-[10px] font-extrabold rounded-full uppercase tracking-wider">New</span>
                            <?php endif; ?>
                            <a href="notifications.php?action=delete&id=<?= $n['notif_id'] ?>" 
                               onclick="return confirm('Delete this notification?');"
                               title="Delete Notification" 
                               class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-red-50 text-slate-400 hover:text-red-500 flex items-center justify-center transition-colors">
                                <i class="bi bi-trash3 text-sm"></i>
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="p-16 bg-white rounded-3xl border border-slate-200/80 text-center space-y-3">
                    <div class="w-16 h-16 rounded-2xl bg-slate-50 text-slate-300 flex items-center justify-center text-3xl mx-auto">
                        <i class="bi bi-bell-slash"></i>
                    </div>
                    <h3 class="text-base font-extrabold text-slate-900">No Notifications Found</h3>
                    <p class="text-xs text-slate-500 font-semibold max-w-sm mx-auto">You have no notification activity yet. Booking updates and payment verifications will appear here.</p>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
            document.getElementById('sidebarOverlay').classList.toggle('hidden');
        }
    </script>
</body>
</html>
