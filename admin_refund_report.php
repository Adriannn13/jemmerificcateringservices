<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?error=unauthorized");
    exit();
}
require_once 'db_config.php';

$user_name = $_SESSION['full_name'] ?? 'System Administrator';

// 1. Handle Filters
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where = " WHERE b.status = 'cancelled'";
if ($search) {
    $s = $conn->real_escape_string($search);
    $where .= " AND (b.event_name LIKE '%$s%' OR u.full_name LIKE '%$s%' OR b.cancel_reason LIKE '%$s%')";
}
if ($date_from) $where .= " AND b.event_date >= '" . $conn->real_escape_string($date_from) . "'";
if ($date_to) $where .= " AND b.event_date <= '" . $conn->real_escape_string($date_to) . "'";

// 2. Fetch Cancelled Bookings
$query = "SELECT b.*, u.full_name, u.email 
          FROM bookings b 
          JOIN users u ON b.user_id = u.user_id 
          $where 
          ORDER BY b.created_at DESC";
$results = $conn->query($query);

// 3. Summary Stats for the filtered view
$stats = $conn->query("SELECT COUNT(*) as count, SUM(refund_amount) as total FROM bookings b JOIN users u ON b.user_id = u.user_id $where")->fetch_assoc();

// 4. Fetch Top 3 Cancellation Reasons for Chart
$reason_stats_query = "SELECT cancel_reason, COUNT(*) as reason_count 
                       FROM bookings b 
                       JOIN users u ON b.user_id = u.user_id 
                       $where 
                       AND b.cancel_reason IS NOT NULL AND b.cancel_reason != ''
                       GROUP BY cancel_reason 
                       ORDER BY reason_count DESC 
                       LIMIT 3";
$reason_stats_res = $conn->query($reason_stats_query);
$reason_labels = [];
$reason_counts = [];
while($rs = $reason_stats_res->fetch_assoc()) {
    $reason_labels[] = (strlen($rs['cancel_reason']) > 25) ? substr($rs['cancel_reason'], 0, 22) . '...' : $rs['cancel_reason'];
    $reason_counts[] = (int)$rs['reason_count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refund Report | JEMmerific</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: -0.01em; background-color: #FAFBFF; }
        .sidebar-link { transition: all 0.2s ease-in-out; }
        .sidebar-link:hover { background-color: #e5e7eb; color: #9b7cd1; padding-left: 1.5rem; }
        .active-link { background: linear-gradient(90deg, #d4cef3 0%, transparent 100%); color: #7c3aed; font-weight: 700; border-right: 4px solid #7c3aed; }
        .glass-card { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(10px); }
    </style>
</head>
<body class="flex min-h-screen">

    <?php require_once 'includes/admin_sidebar.php'; ?>

    <main class="flex-1 lg:ml-64 p-4 md:p-6">
        <header class="flex flex-col md:flex-row justify-between items-start gap-6 mb-12">
            <div class="flex items-start gap-4">
                <button onclick="toggleSidebar()" class="lg:hidden mt-1 p-2 bg-white border border-gray-100 rounded-xl shadow-sm text-gray-600">
                    <i class="bi bi-list text-2xl"></i>
                </button>
                <div>
                    <h2 class="text-3xl font-extrabold text-gray-900 tracking-tight">Refund Analysis Report</h2>
                    <p class="text-sm text-gray-400 font-medium">Audit cancellation reasons and financial returns.</p>
                </div>
            </div>
            <div class="flex gap-4">
                <div class="bg-white p-4 px-6 rounded-3xl shadow-sm border border-gray-50 flex items-center gap-4">
                    <div class="w-10 h-10 bg-red-50 rounded-2xl flex items-center justify-center text-red-500">
                        <i class="bi bi-x-circle-fill"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Cancelled Events</p>
                        <p class="text-lg font-black text-gray-800"><?= number_format($stats['count']) ?></p>
                    </div>
                </div>
                <div class="bg-white p-4 px-6 rounded-3xl shadow-sm border border-gray-50 flex items-center gap-4">
                    <div class="w-10 h-10 bg-purple-50 rounded-2xl flex items-center justify-center text-purple-600">
                        <i class="bi bi-currency-exchange"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Total Refunded</p>
                        <p class="text-lg font-black text-gray-800">₱<?= number_format($stats['total'] ?? 0, 2) ?></p>
                    </div>
                </div>
            </div>
        </header>

        <!-- Filter Bar -->
        <div class="glass-card p-6 rounded-[2rem] mb-10 shadow-sm">
            <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="md:col-span-2 relative">
                    <i class="bi bi-search absolute left-5 top-3.5 text-gray-400"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search client, event, or reason..." 
                           class="w-full pl-14 pr-6 py-3.5 bg-gray-50/50 border-none rounded-2xl text-sm font-medium focus:ring-2 focus:ring-purple-500/20">
                </div>
                <div class="flex gap-2">
                    <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="flex-1 px-4 py-3.5 bg-gray-50/50 border-none rounded-2xl text-xs font-bold text-gray-600">
                    <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" class="flex-1 px-4 py-3.5 bg-gray-50/50 border-none rounded-2xl text-xs font-bold text-gray-600">
                </div>
                <button type="submit" class="bg-gray-900 text-white py-3.5 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-purple-600 transition shadow-xl">
                    Generate Report
                </button>
            </form>
        </div>

        <?php if(!empty($reason_counts)): ?>
        <!-- Insights Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-10 mb-10">
            <div class="lg:col-span-1 bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-50 flex flex-col">
                <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-6 flex items-center gap-2">
                    <i class="bi bi-pie-chart-fill text-purple-500"></i> Primary Cancellation Drivers
                </h4>
                <div class="flex-1 min-h-[200px] relative">
                    <canvas id="reasonsChart"></canvas>
                </div>
            </div>
            
            <div class="lg:col-span-2 bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-50">
                <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-6">Financial Impact Overview</h4>
                <div class="space-y-6">
                    <p class="text-sm text-gray-500 leading-relaxed">
                        Based on current filters, <strong><?= number_format($stats['count']) ?></strong> bookings were cancelled. 
                        The most frequent reason is <span class="text-purple-600 font-bold">"<?= htmlspecialchars($reason_labels[0] ?? 'N/A') ?>"</span>.
                    </p>
                    <div class="p-6 bg-purple-50 rounded-2xl border border-purple-100">
                        <p class="text-[9px] font-black text-purple-400 uppercase tracking-widest mb-1">Average Refund Per Event</p>
                        <p class="text-2xl font-black text-purple-600">₱<?= number_format($stats['count'] > 0 ? $stats['total'] / $stats['count'] : 0, 2) ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Report Table -->
        <div class="bg-white rounded-[2.5rem] overflow-hidden shadow-sm border border-slate-50">
            <table class="w-full text-left">
                <thead class="bg-gray-50/50">
                    <tr class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">
                        <th class="px-8 py-6">Cancellation Date</th>
                        <th class="px-8 py-6">Client & Event</th>
                        <th class="px-8 py-6">Reason for Cancellation</th>
                        <th class="px-8 py-6 text-right">Amount Refunded</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php if($results->num_rows > 0): ?>
                        <?php while($row = $results->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50/30 transition group">
                            <td class="px-8 py-6">
                                <p class="text-sm font-bold text-gray-800"><?= date('M d, Y', strtotime($row['created_at'])) ?></p>
                                <p class="text-[10px] text-gray-400 uppercase tracking-tighter"><?= date('h:i A', strtotime($row['created_at'])) ?></p>
                            </td>
                            <td class="px-8 py-6">
                                <p class="font-bold text-gray-900 text-sm"><?= htmlspecialchars($row['full_name']) ?></p>
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] text-purple-500 font-bold uppercase tracking-widest">#BK-<?= str_pad($row['booking_id'], 4, '0', STR_PAD_LEFT) ?></span>
                                    <span class="text-[10px] text-gray-400">&bull;</span>
                                    <span class="text-[10px] text-gray-400 font-medium"><?= htmlspecialchars($row['event_name']) ?></span>
                                </div>
                            </td>
                            <td class="px-8 py-6">
                                <div class="max-w-xs">
                                    <p class="text-xs text-gray-600 leading-relaxed italic">"<?= htmlspecialchars($row['cancel_reason'] ?: 'No reason specified') ?>"</p>
                                </div>
                            </td>
                            <td class="px-8 py-6 text-right">
                                <p class="text-sm font-black text-red-500">₱<?= number_format($row['refund_amount'], 2) ?></p>
                                <?php if($row['refund_amount'] > 0): ?>
                                    <span class="text-[8px] font-black text-green-500 bg-green-50 px-2 py-0.5 rounded uppercase">Processed</span>
                                <?php else: ?>
                                    <span class="text-[8px] font-black text-gray-400 bg-gray-50 px-2 py-0.5 rounded uppercase">No Refund</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="py-24 text-center">
                                <i class="bi bi-file-earmark-x text-5xl text-gray-100 block mb-4"></i>
                                <p class="text-xs font-bold text-gray-300 uppercase tracking-[0.2em]">No refund records found</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Export Footer -->
        <div class="mt-8 flex justify-end gap-4">
            <a href="auth/export_refunds.php?<?= http_build_query($_GET) ?>" class="flex items-center gap-2 px-8 py-4 bg-emerald-600 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-emerald-700 transition-all shadow-lg shadow-emerald-100">
                <i class="bi bi-file-earmark-excel"></i> Export to Excel
            </a>
            <button onclick="window.print()" class="flex items-center gap-2 px-8 py-4 bg-white border border-gray-200 rounded-2xl text-[10px] font-black uppercase tracking-widest text-gray-600 hover:bg-gray-900 hover:text-white hover:border-gray-900 transition-all shadow-sm">
                <i class="bi bi-printer"></i> Print Audit Trail
            </button>
        </div>
    </main>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }

        <?php if(!empty($reason_counts)): ?>
        const ctx = document.getElementById('reasonsChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($reason_labels) ?>,
                datasets: [{
                    data: <?= json_encode($reason_counts) ?>,
                    backgroundColor: ['#7c3aed', '#ec4899', '#f43f5e'],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: { family: 'Inter', size: 10, weight: '600' }
                        }
                    }
                },
                cutout: '75%'
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>