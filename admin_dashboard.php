<?php
require_once 'includes/admin_auth_check.php';

require_once 'db_config.php';

// Define user info
$user_name = $_SESSION['full_name'] ?? 'System Administrator';

// Check if refund_amount column exists to prevent crash if migration wasn't run
$check_refund = $conn->query("SHOW COLUMNS FROM bookings LIKE 'refund_amount'");
$refund_select = ($check_refund->num_rows > 0) ? "(SELECT COALESCE(SUM(refund_amount), 0) FROM bookings WHERE status = 'cancelled')" : "0";

// 2. Fetch Key Stats
$stats_query = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM bookings WHERE status != 'cancelled') as total_events,
        (SELECT COALESCE(SUM(pax_count), 0) FROM bookings WHERE status != 'cancelled') as total_guests,
        (SELECT (SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE payment_status = 'fully_paid') + (SELECT COUNT(*) * 10000 FROM bookings WHERE payment_status = 'partially_paid')) as total_revenue,
        (SELECT (SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE status != 'cancelled' AND payment_status = 'unpaid') + (SELECT COALESCE(SUM(total_amount - 10000), 0) FROM bookings WHERE status != 'cancelled' AND payment_status = 'partially_paid')) as projected_revenue,
        (SELECT COUNT(*) FROM payments WHERE status = 'Pending') as pending_payments,
        (SELECT COALESCE(AVG(pax_count), 0) FROM bookings WHERE status != 'cancelled') as avg_pax,
        $refund_select as total_refunds
");
$stats = $stats_query->fetch_assoc();

// 3. Fetch Upcoming Schedule
$schedule_preview = $conn->query("
    SELECT b.*, COALESCE(u.full_name, 'Guest Client') as client_name 
    FROM bookings b 
    LEFT JOIN users u ON b.user_id = u.user_id 
    WHERE b.event_date >= CURDATE() AND b.status != 'cancelled'
    ORDER BY b.event_date ASC, b.event_time ASC
    LIMIT 5
");

// 3.5 Fetch Recent Pending Payment Details
$pending_list = $conn->query("
    SELECT b.booking_id, b.event_name, u.full_name, b.total_amount 
    FROM payments p 
    JOIN bookings b ON p.booking_id = b.booking_id 
    JOIN users u ON b.user_id = u.user_id 
    WHERE p.status = 'Pending' 
    ORDER BY p.created_at DESC 
    LIMIT 3
");

// 4. Fetch Monthly Event Data
$monthly_data_query = $conn->query("
    SELECT MONTH(event_date) as m, COUNT(*) as total 
    FROM bookings 
    WHERE YEAR(event_date) = YEAR(CURDATE()) AND status != 'cancelled' AND MONTH(event_date) <= 6
    GROUP BY MONTH(event_date)
");
$monthly_counts = array_fill(0, 6, 0);
while($row = $monthly_data_query->fetch_assoc()) {
    $monthly_counts[(int)$row['m'] - 1] = (int)$row['total'];
}

// 5. Fetch Revenue vs Refunds
$refund_col_exists = ($conn->query("SHOW COLUMNS FROM bookings LIKE 'refund_amount'")->num_rows > 0);
$refund_field = $refund_col_exists ? "SUM(refund_amount)" : "0";

$financial_stats = $conn->query("
    SELECT 
        DATE_FORMAT(event_date, '%b') as label,
        (SUM(CASE WHEN payment_status = 'fully_paid' THEN total_amount ELSE 0 END) + SUM(CASE WHEN payment_status = 'partially_paid' THEN 10000 ELSE 0 END)) as revenue,
        $refund_field as refunds
    FROM bookings 
    WHERE event_date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
    GROUP BY YEAR(event_date), MONTH(event_date)
    ORDER BY YEAR(event_date) ASC, MONTH(event_date) ASC");

$fin_labels = []; $fin_revenue = []; $fin_refunds = [];
while($f = $financial_stats->fetch_assoc()){
    $fin_labels[] = $f['label']; $fin_revenue[] = (float)$f['revenue']; $fin_refunds[] = (float)$f['refunds'];
}

// 6. Fetch Yearly Overview
$yearly_stats_query = $conn->query("
    SELECT 
        YEAR(event_date) as year,
        (SUM(CASE WHEN payment_status = 'fully_paid' THEN total_amount ELSE 0 END) + SUM(CASE WHEN payment_status = 'partially_paid' THEN 10000 ELSE 0 END)) as revenue,
        SUM(pax_count) as customers
    FROM bookings 
    WHERE status != 'cancelled'
    GROUP BY YEAR(event_date)
    ORDER BY YEAR(event_date) ASC
");

$year_labels = []; $year_revenue = []; $year_customers = [];
$highest_revenue = 0;
$highest_revenue_year = 'N/A';
$highest_customers = 0;
$highest_customers_year = 'N/A';

while($y = $yearly_stats_query->fetch_assoc()){
    $yr = $y['year'];
    $rev = (float)$y['revenue'];
    $cust = (int)$y['customers'];
    
    $year_labels[] = $yr;
    $year_revenue[] = $rev;
    $year_customers[] = $cust;

    if($rev > $highest_revenue) {
        $highest_revenue = $rev;
        $highest_revenue_year = $yr;
    }
    if($cust > $highest_customers) {
        $highest_customers = $cust;
        $highest_customers_year = $yr;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JEMmerific | Admin Dashboard</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F8FAFC; color: #0F172A; }
        .stat-card { transition: all 0.2s ease; }
        .stat-card:hover { border-color: #CBD5E1; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05); }
        .sidebar-link { transition: all 0.2s ease-in-out; }
        .sidebar-link:hover { background-color: #F1F5F9; color: #0F172A; }
        .active-link { background-color: #F1F5F9; color: #0F172A; font-weight: 600; border-left: 3px solid #0F172A; }
        /* Hide scrollbar for clean look */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94A3B8; }
    </style>
</head>
<body class="flex min-h-screen">

    <?php require_once 'includes/admin_sidebar.php'; ?>

    <main class="flex-1 lg:ml-64 p-4 sm:p-6 md:p-8 lg:p-10 transition-all overflow-x-hidden">
        <div class="w-full max-w-[1400px] mx-auto space-y-6 sm:space-y-8">
            <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div class="flex items-center gap-3.5">
                    <button onclick="toggleSidebar()" class="lg:hidden p-2.5 min-h-[44px] min-w-[44px] flex items-center justify-center bg-white border border-gray-200 rounded-xl shadow-sm text-gray-600 hover:bg-gray-50 active:scale-95 transition-all">
                        <i class="bi bi-list text-xl"></i>
                    </button>
                    <div>
                        <div class="mb-1 flex items-center">
                            <span id="adminWelcomeTypingText" class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-[0.25em]"></span>
                            <span class="inline-block w-1.5 h-3.5 bg-slate-900 dark:bg-white animate-pulse rounded-2xs ml-1"></span>
                        </div>
                        <h2 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Dashboard Overview</h2>
                        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 font-medium mt-1">Analytics and performance metrics for your business.</p>
                    </div>
                </div>
            </header>

            <!-- Key Stats -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <div class="stat-card bg-white p-6 rounded-2xl border border-gray-200 flex flex-col justify-between">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <p class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider">Total Events</p>
                            <h3 class="text-3xl font-bold text-slate-900 mt-2"><?= number_format($stats['total_events']) ?></h3>
                        </div>
                        <div class="p-2.5 bg-slate-50 text-slate-600 rounded-xl">
                            <i class="bi bi-calendar-check text-lg"></i>
                        </div>
                    </div>
                    <div class="flex items-center text-[12px] font-medium text-emerald-600">
                        <i class="bi bi-arrow-up-right mr-1"></i> Active Bookings
                    </div>
                </div>

                <div class="stat-card bg-white p-6 rounded-2xl border border-gray-200 flex flex-col justify-between">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <p class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider">Total Guests</p>
                            <h3 class="text-3xl font-bold text-slate-900 mt-2"><?= number_format($stats['total_guests']) ?></h3>
                        </div>
                        <div class="p-2.5 bg-slate-50 text-slate-600 rounded-xl">
                            <i class="bi bi-people text-lg"></i>
                        </div>
                    </div>
                    <div class="flex items-center text-[12px] font-medium text-slate-500">
                        Across all confirmed events
                    </div>
                </div>

                <div class="stat-card bg-white p-6 rounded-2xl border border-gray-200 flex flex-col justify-between">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <p class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider">Collected Revenue</p>
                            <h3 class="text-3xl font-bold text-slate-900 mt-2">
                                ₱<?= $stats['total_revenue'] >= 1000000 ? number_format($stats['total_revenue'] / 1000000, 2) . 'M' : number_format($stats['total_revenue']) ?>
                            </h3>
                        </div>
                        <div class="p-2.5 bg-emerald-50 text-emerald-600 rounded-xl">
                            <i class="bi bi-cash-stack text-lg"></i>
                        </div>
                    </div>
                    <div class="flex items-center text-[12px] font-medium text-slate-500">
                        Forecast: ₱<?= number_format($stats['projected_revenue']) ?>
                    </div>
                </div>

                <div class="stat-card bg-white p-6 rounded-2xl border border-gray-200 flex flex-col justify-between">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <p class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider">Avg Event Size</p>
                            <h3 class="text-3xl font-bold text-slate-900 mt-2"><?= number_format($stats['avg_pax']) ?></h3>
                        </div>
                        <div class="p-2.5 bg-slate-50 text-slate-600 rounded-xl">
                            <i class="bi bi-pie-chart text-lg"></i>
                        </div>
                    </div>
                    <div class="flex items-center text-[12px] font-medium text-slate-500">
                        Guests per booking
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <div class="bg-white p-6 md:p-8 rounded-2xl border border-gray-200 lg:col-span-1">
                    <h4 class="text-sm font-semibold text-slate-900 mb-6 flex items-center justify-between">
                        Events Over Time
                        <i class="bi bi-graph-up text-slate-400"></i>
                    </h4>
                    <div class="relative h-[280px]">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>

                <div class="bg-white p-6 md:p-8 rounded-2xl border border-gray-200 lg:col-span-2">
                    <h4 class="text-sm font-semibold text-slate-900 mb-6 flex items-center justify-between">
                        Financial Performance
                        <i class="bi bi-bar-chart text-slate-400"></i>
                    </h4>
                    <div class="relative h-[280px]">
                        <canvas id="financialChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Yearly Comparison Section -->
            <div class="bg-white p-6 md:p-8 rounded-2xl border border-gray-200 mb-5">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                    <div>
                        <h4 class="text-sm font-semibold text-slate-900 flex items-center gap-2">
                            Yearly Performance Overview
                            <i class="bi bi-calendar-check text-slate-400"></i>
                        </h4>
                        <p class="text-[12px] text-slate-500 mt-1">Compare revenue vs guest volume across years.</p>
                    </div>
                    <div class="flex gap-4">
                        <div class="text-right">
                            <p class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider">Top Revenue Year</p>
                            <p class="text-sm font-bold text-indigo-600"><?= $highest_revenue_year ?> (₱<?= number_format($highest_revenue) ?>)</p>
                        </div>
                        <div class="text-right border-l border-gray-100 pl-4">
                            <p class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider">Top Guests Year</p>
                            <p class="text-sm font-bold text-emerald-600"><?= $highest_customers_year ?> (<?= number_format($highest_customers) ?> pax)</p>
                        </div>
                    </div>
                </div>
                <div class="relative h-[320px]">
                    <canvas id="yearlyChart"></canvas>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
                <!-- Upcoming Events -->
                <div class="bg-white rounded-2xl border border-gray-200 xl:col-span-2 overflow-hidden flex flex-col">
                    <div class="p-6 md:p-8 border-b border-gray-100 flex justify-between items-center bg-white">
                        <h4 class="font-semibold text-slate-900 text-sm">Upcoming Events</h4>
                        <a href="admin_schedule.php" class="text-[12px] font-medium text-slate-500 hover:text-slate-800 transition-colors">View All</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr>
                                    <th class="px-6 py-4 text-[11px] font-semibold text-slate-500 uppercase tracking-wider bg-slate-50 border-b border-gray-100">Event</th>
                                    <th class="px-6 py-4 text-[11px] font-semibold text-slate-500 uppercase tracking-wider bg-slate-50 border-b border-gray-100">Date</th>
                                    <th class="px-6 py-4 text-[11px] font-semibold text-slate-500 uppercase tracking-wider bg-slate-50 border-b border-gray-100">Guests</th>
                                    <th class="px-6 py-4 text-[11px] font-semibold text-slate-500 uppercase tracking-wider bg-slate-50 border-b border-gray-100 text-right">Status & Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if($schedule_preview->num_rows > 0): ?>
                                    <?php while($row = $schedule_preview->fetch_assoc()): ?>
                                    <tr class="hover:bg-slate-50/50 transition-colors group">
                                        <td class="px-6 py-4">
                                            <p class="font-semibold text-slate-900 text-sm group-hover:text-blue-600 transition-colors"><?= htmlspecialchars($row['event_name']) ?></p>
                                            <p class="text-[11px] text-slate-500 mt-1 truncate max-w-[200px]"><?= htmlspecialchars($row['venue_location'] ?? 'TBD') ?></p>
                                        </td>
                                        <td class="px-6 py-4">
                                            <p class="text-sm font-medium text-slate-700"><?= date('M d, Y', strtotime($row['event_date'])) ?></p>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-slate-100 text-slate-600 text-[11px] font-semibold">
                                                <i class="bi bi-people"></i> <?= number_format($row['pax_count']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="inline-flex items-center gap-2 justify-end">
                                                <?php
                                                    $s = strtolower($row['status']);
                                                    $color = ($s == 'confirmed') ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200';
                                                ?>
                                                <span class="inline-flex px-2.5 py-1 rounded-md text-[10px] font-semibold uppercase tracking-wide border <?= $color ?>">
                                                    <?= $s ?>
                                                </span>
                                                <button onclick="confirmRemoveEvent(<?= $row['booking_id'] ?>, '<?= htmlspecialchars(addslashes($row['event_name'])) ?>')" 
                                                        class="p-2 min-h-[36px] min-w-[36px] flex items-center justify-center text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-colors cursor-pointer" 
                                                        title="Remove Event">
                                                    <i class="bi bi-trash3 text-sm"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-12 text-center text-sm text-slate-500">No upcoming events scheduled.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- AI Event Plan Generator (Minimalist Version) -->
                <div class="bg-slate-900 rounded-2xl border border-slate-800 text-white xl:col-span-1 flex flex-col">
                    <div class="p-6 md:p-8 flex-1 flex flex-col">
                        <div class="mb-6">
                            <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-md bg-slate-800 border border-slate-700 mb-4">
                                <i class="bi bi-stars text-amber-400 text-[11px]"></i>
                                <span class="text-[10px] font-semibold text-slate-300 uppercase tracking-widest">AI Studio</span>
                            </div>
                            <h3 class="text-xl font-bold tracking-tight text-white mb-2">Event Planner</h3>
                            <p class="text-sm text-slate-400 font-medium">Generate intelligent layouts, menus, and estimates.</p>
                        </div>

                        <form id="admin-ai-form" class="space-y-4 mt-auto" onsubmit="generateAdminAIPlan(event)">
                            <div>
                                <label class="block text-[11px] font-medium text-slate-400 mb-1.5">Vision / Theme</label>
                                <input type="text" id="ai_event_desc" required placeholder="e.g. Minimalist Corporate Gala" class="w-full px-4 py-3 bg-slate-800/50 border border-slate-700 rounded-xl text-white placeholder-slate-500 text-sm focus:bg-slate-800 focus:border-slate-500 outline-none transition-all min-h-[44px]">
                            </div>
                            <div>
                                <label class="block text-[11px] font-medium text-slate-400 mb-1.5">Guest Count</label>
                                <input type="number" id="ai_pax" required min="10" value="100" class="w-full px-4 py-3 bg-slate-800/50 border border-slate-700 rounded-xl text-white text-sm focus:bg-slate-800 focus:border-slate-500 outline-none transition-all min-h-[44px]">
                            </div>

                            <button type="submit" id="ai-generate-btn" class="w-full mt-4 py-3.5 min-h-[44px] bg-white text-slate-900 hover:bg-slate-100 rounded-xl font-bold text-xs transition-colors flex justify-center items-center gap-2 active:scale-[0.99]">
                                Generate Plan
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- AI Output Results Box -->
            <div id="ai-output-container" class="hidden bg-slate-900 rounded-2xl border border-slate-800 p-6 md:p-8 text-white">
                <div id="ai-loading-spinner" class="py-8 text-center hidden">
                    <div class="inline-block w-8 h-8 border-2 border-slate-700 border-t-white rounded-full animate-spin mb-4"></div>
                    <p class="text-xs font-medium text-slate-400 uppercase tracking-widest">Processing Intelligence...</p>
                </div>

                <div id="ai-result-content" class="hidden space-y-6">
                    <div class="border-b border-slate-800 pb-6">
                        <h4 id="res-event-title" class="text-lg font-bold text-white mb-2"></h4>
                        <p id="res-layout" class="text-sm text-slate-400 leading-relaxed"></p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <h5 class="text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-4">Curated Menu</h5>
                            <div id="res-menu-list" class="space-y-3"></div>
                        </div>
                        <div>
                            <h5 class="text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-4">Estimated Total</h5>
                            <p id="res-cost" class="text-3xl font-bold text-white mb-2"></p>
                            <p id="res-notes" class="text-xs text-slate-500 italic"></p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script>
        // Minimalist Chart Options
        Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
        Chart.defaults.color = '#64748B';

        const isDarkTheme = document.documentElement.classList.contains('dark');
        const gridColor = isDarkTheme ? 'rgba(255, 255, 255, 0.1)' : '#F1F5F9';
        const tickColor = isDarkTheme ? '#94a3b8' : '#64748b';

        const commonOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { display: false },
                tooltip: {
                    backgroundColor: isDarkTheme ? '#1e293b' : '#0F172A',
                    titleColor: '#ffffff',
                    bodyColor: '#cbd5e1',
                    padding: 12,
                    titleFont: { size: 13, weight: '600' },
                    bodyFont: { size: 12 },
                    cornerRadius: 8,
                    displayColors: false
                }
            },
            scales: { 
                y: { 
                    beginAtZero: true, 
                    border: { display: false }, 
                    grid: { color: gridColor, drawTicks: false }, 
                    ticks: { color: tickColor, padding: 10, font: { size: 11 } } 
                }, 
                x: { 
                    border: { display: false }, 
                    grid: { display: false }, 
                    ticks: { color: tickColor, padding: 10, font: { size: 11 } } 
                } 
            },
            interaction: { mode: 'index', intersect: false }
        };

        // Monthly Events - Sleek Line Chart
        const mCtx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(mCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Events',
                    data: <?= json_encode($monthly_counts) ?>,
                    borderColor: isDarkTheme ? '#c084fc' : '#7c3aed',
                    backgroundColor: isDarkTheme ? 'rgba(192, 132, 252, 0.2)' : 'rgba(124, 58, 237, 0.08)',
                    borderWidth: 3,
                    pointBackgroundColor: isDarkTheme ? '#ffffff' : '#7c3aed',
                    pointBorderColor: isDarkTheme ? '#c084fc' : '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: commonOptions
        });

        // Financial Performance - Clean Bar Chart
        const fCtx = document.getElementById('financialChart').getContext('2d');
        new Chart(fCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($fin_labels) ?>,
                datasets: [
                    { 
                        label: 'Revenue', 
                        data: <?= json_encode($fin_revenue) ?>, 
                        backgroundColor: isDarkTheme ? '#818cf8' : '#6366f1', 
                        borderRadius: 6,
                        barThickness: 16
                    },
                    { 
                        label: 'Refunds', 
                        data: <?= json_encode($fin_refunds) ?>, 
                        backgroundColor: isDarkTheme ? '#f43f5e' : '#ef4444', 
                        borderRadius: 6,
                        barThickness: 16
                    }
                ]
            },
            options: {
                ...commonOptions,
                plugins: { 
                    ...commonOptions.plugins,
                    legend: { 
                        display: true, 
                        position: 'top', 
                        align: 'end',
                        labels: { color: tickColor, boxWidth: 8, boxHeight: 8, usePointStyle: true, font: { size: 11, weight: '500' } } 
                    },
                    tooltip: {
                        ...commonOptions.plugins.tooltip,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += '₱' + context.parsed.y.toLocaleString();
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: { 
                    y: { 
                        ...commonOptions.scales.y, 
                        ticks: { ...commonOptions.scales.y.ticks, callback: v => '₱' + (v >= 1000 ? (v/1000) + 'k' : v) } 
                    },
                    x: commonOptions.scales.x
                }
            }
        });

        // Yearly Overview - Mixed Dual Axis Chart
        const yCtx = document.getElementById('yearlyChart').getContext('2d');
        new Chart(yCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($year_labels) ?>,
                datasets: [
                    {
                        type: 'line',
                        label: 'Customers (Guests)',
                        data: <?= json_encode($year_customers) ?>,
                        borderColor: isDarkTheme ? '#34d399' : '#10b981',
                        backgroundColor: isDarkTheme ? '#34d399' : '#10b981',
                        borderWidth: 3,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: isDarkTheme ? '#34d399' : '#10b981',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        tension: 0.4,
                        yAxisID: 'y1'
                    },
                    {
                        type: 'bar',
                        label: 'Revenue',
                        data: <?= json_encode($year_revenue) ?>,
                        backgroundColor: isDarkTheme ? '#818cf8' : '#6366f1',
                        borderRadius: 6,
                        barThickness: 24,
                        yAxisID: 'y'
                    }
                ]
            },
            options: {
                ...commonOptions,
                plugins: {
                    ...commonOptions.plugins,
                    legend: {
                        display: true,
                        position: 'top',
                        align: 'end',
                        labels: { color: tickColor, boxWidth: 12, boxHeight: 12, usePointStyle: true, font: { size: 11, weight: '500' } }
                    },
                    tooltip: {
                        ...commonOptions.plugins.tooltip,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.dataset.yAxisID === 'y') {
                                    label += '₱' + context.parsed.y.toLocaleString();
                                } else {
                                    label += context.parsed.y.toLocaleString() + ' pax';
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: commonOptions.scales.x,
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        grid: { color: gridColor, drawTicks: false },
                        ticks: { color: tickColor, padding: 10, font: { size: 11 }, callback: v => '₱' + (v >= 1000 ? (v/1000) + 'k' : v) }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: { display: false },
                        ticks: { color: tickColor, padding: 10, font: { size: 11 } }
                    }
                }
            }
        });

        async function generateAdminAIPlan(e) {
            e.preventDefault();
            const desc = document.getElementById('ai_event_desc').value;
            const pax = document.getElementById('ai_pax').value;

            const container = document.getElementById('ai-output-container');
            const spinner = document.getElementById('ai-loading-spinner');
            const resultContent = document.getElementById('ai-result-content');
            const btn = document.getElementById('ai-generate-btn');

            container.classList.remove('hidden');
            spinner.classList.remove('hidden');
            resultContent.classList.add('hidden');
            btn.disabled = true;
            btn.innerHTML = '<span class="opacity-50">Generating...</span>';

            const formData = new FormData();
            formData.append('event_desc', desc);
            formData.append('pax', pax);

            try {
                const res = await fetch('auth/process_ai_plan.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                spinner.classList.add('hidden');
                btn.disabled = false;
                btn.innerHTML = 'Generate Plan';

                if (data.status === 'success' && data.parsed) {
                    const plan = data.parsed;
                    document.getElementById('res-event-title').innerText = plan.event_title_suggestion || "Curated Plan";
                    document.getElementById('res-layout').innerText = plan.layout_design_suggestion || plan.layout_analysis || "Optimal arrangement based on capacity.";

                    const menuDiv = document.getElementById('res-menu-list');
                    menuDiv.innerHTML = '';
                    if (plan.suggested_menu && plan.suggested_menu.length > 0) {
                        plan.suggested_menu.forEach(item => {
                            menuDiv.innerHTML += `
                                <div class="flex justify-between items-start border-b border-slate-800 pb-2 last:border-0 last:pb-0">
                                    <span class="font-medium text-slate-200 text-sm">${item.dish}</span>
                                    <span class="text-slate-500 text-[11px] ml-4 text-right max-w-[50%]">${item.reason || ''}</span>
                                </div>`;
                        });
                    } else {
                        menuDiv.innerHTML = '<p class="text-slate-500 text-sm">Custom curated buffet selection.</p>';
                    }

                    document.getElementById('res-cost').innerText = '₱' + (plan.estimated_cost ? Number(plan.estimated_cost).toLocaleString() : '0.00');
                    document.getElementById('res-notes').innerText = plan.special_notes ? 'Note: ' + plan.special_notes : '';

                    resultContent.classList.remove('hidden');
                } else {
                    alert(data.message || "Failed to generate plan.");
                }
            } catch(err) {
                spinner.classList.add('hidden');
                btn.disabled = false;
                btn.innerHTML = 'Generate Plan';
                alert("Processed request with local fallback.");
            }
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if(sidebar) sidebar.classList.toggle('-translate-x-full');
            if(overlay) overlay.classList.toggle('hidden');
        }

        function confirmRemoveEvent(bookingId, eventName) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Remove Event?',
                    text: `Are you sure you want to remove "${eventName}"? All associated event details will be deleted.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmColor: '#ef4444',
                    cancelColor: '#64748b',
                    confirmButtonText: 'Yes, remove',
                    cancelButtonText: 'Cancel',
                    customClass: {
                        popup: 'rounded-2xl',
                        confirmButton: 'rounded-xl font-bold px-4 py-2 text-xs',
                        cancelButton: 'rounded-xl font-bold px-4 py-2 text-xs'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = `auth/delete_booking.php?booking_id=${bookingId}`;
                    }
                });
            } else {
                if (confirm(`Are you sure you want to remove "${eventName}"?`)) {
                    window.location.href = `auth/delete_booking.php?booking_id=${bookingId}`;
                }
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            <?php if(isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Event removed successfully!',
                    showConfirmButton: false,
                    timer: 3500
                });
            }
            <?php endif; ?>

            const welcomeText = "WELCOME BACK, <?= htmlspecialchars(addslashes(strtoupper($user_name))) ?>!";
            const textContainer = document.getElementById('adminWelcomeTypingText');
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