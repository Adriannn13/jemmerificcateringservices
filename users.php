<?php
require_once 'includes/admin_auth_check.php';
require_once 'db_config.php';

$user_name = $_SESSION['full_name'] ?? 'System Administrator';

// --- HANDLE DELETE ACTION ---
if (isset($_GET['delete_id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete_id']);
    // Check for admin/staff role to prevent deleting staff or admins from customer list
    $conn->query("DELETE FROM users WHERE user_id = '$id' AND role != 'admin' AND role != 'staff'");
    header("Location: users.php?msg=deleted");
    exit();
}

// Search Logic
$search = $_GET['search'] ?? '';
$where = " WHERE (role != 'admin' AND role != 'staff' OR role IS NULL OR role = '' OR role = 'client')";
if($search) {
    $search_safe = mysqli_real_escape_string($conn, $search);
    $where .= " AND (full_name LIKE '%$search_safe%' OR email LIKE '%$search_safe%')";
}

// Stats Query
$total_customers = $conn->query("SELECT COUNT(*) as count FROM users WHERE (role != 'admin' AND role != 'staff' OR role IS NULL OR role = '' OR role = 'client')")->fetch_assoc()['count'];
$new_today = $conn->query("SELECT COUNT(*) as count FROM users WHERE (role != 'admin' AND role != 'staff' OR role IS NULL OR role = '' OR role = 'client') AND DATE(created_at) = CURDATE()")->fetch_assoc()['count'];

$users_res = $conn->query("SELECT * FROM users $where ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management | JEMmerific</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: -0.01em; background-color: #FAFBFF; }
        .stat-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.05); }
        .sidebar-link { transition: all 0.2s ease-in-out; }
        .sidebar-link:hover { background-color: #e5e7eb; color: #9b7cd1; padding-left: 1.5rem; }
        .active-link { background: linear-gradient(90deg, #d4cef3 0%, transparent 100%); color: #7c3aed; font-weight: 700; border-right: 4px solid #7c3aed; }
        .glass-card { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(10px); }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #E2E8F0; border-radius: 10px; }
    </style>
</head>
<body class="flex min-h-screen">

    <?php require_once 'includes/admin_sidebar.php'; ?>

    <main class="flex-1 lg:ml-64 p-4 md:p-6 transition-all">
        <header class="flex flex-col md:flex-row justify-between items-start gap-6 mb-12">
            <div class="flex items-start gap-4">
                <button onclick="toggleSidebar()" class="lg:hidden mt-1 p-2 bg-white border border-gray-100 rounded-xl shadow-sm text-gray-600">
                    <i class="bi bi-list text-2xl"></i>
                </button>
                <div>
                <h2 class="text-4xl font-black text-gray-900 tracking-tighter">Customer</h2>
                <p class="text-sm text-gray-400 font-semibold mt-1">Monitor user activity and manage client access.</p>
                </div>
            </div>
            
            <div class="flex gap-4">
                <div class="bg-white p-4 px-6 rounded-3xl shadow-sm border border-gray-50 flex items-center gap-4">
                    <div class="w-10 h-10 bg-purple-50 rounded-2xl flex items-center justify-center text-purple-600">
                        <i class="bi bi-people"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-gray-400 uppercase">Total Users</p>
                        <p class="text-lg font-black text-gray-800"><?= $total_customers ?></p>
                    </div>
                </div>
                <div class="bg-white p-4 px-6 rounded-3xl shadow-sm border border-gray-50 flex items-center gap-4">
                    <div class="w-10 h-10 bg-green-50 rounded-2xl flex items-center justify-center text-green-600">
                        <i class="bi bi-person-plus"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-gray-400 uppercase">New Today</p>
                        <p class="text-lg font-black text-gray-800"><?= $new_today ?></p>
                    </div>
                </div>
            </div>
        </header>

        <div class="glass-card p-4 rounded-[2rem] mb-8 shadow-sm">
            <form action="" method="GET" class="flex gap-4">
                <div class="relative flex-1">
                    <i class="bi bi-search absolute left-5 top-3.5 text-gray-400"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name, email, or user ID..." 
                           class="w-full pl-14 pr-6 py-3.5 bg-gray-50/50 border-none rounded-2xl text-sm font-medium focus:ring-2 focus:ring-purple-500/20 transition-all">
                </div>
                <button type="submit" class="bg-gray-900 text-white px-8 py-3.5 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-purple-600 transition shadow-xl shadow-gray-100">
                    Filter Results
                </button>
            </form>
        </div>

        <div class="bg-white rounded-[3.5rem] overflow-hidden shadow-sm border border-slate-50">
            <table class="w-full text-left">
                <thead class="bg-gray-50/50">
                    <tr class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">
                        <th class="px-8 py-6">Customer Profile</th>
                        <th class="px-8 py-6">Contact Info</th>
                        <th class="px-8 py-6">Registration Date</th>
                        <th class="px-8 py-6 text-center">Status</th>
                        <th class="px-8 py-6 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php if($users_res->num_rows > 0): ?>
                        <?php while($user = $users_res->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50/30 transition group">
                            <td class="px-8 py-6">
                                <div class="flex items-center gap-4">
                                    <?php 
                                        $colors = ['bg-indigo-100 text-indigo-600', 'bg-purple-100 text-purple-600', 'bg-blue-100 text-blue-600', 'bg-rose-100 text-rose-600'];
                                        $color = $colors[ord($user['full_name'][0]) % count($colors)];
                                    ?>
                                    <div class="w-12 h-12 <?= $color ?> rounded-2xl flex items-center justify-center font-black text-sm shadow-sm border border-white">
                                        <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <p class="font-bold text-gray-900 text-sm"><?= htmlspecialchars($user['full_name']) ?></p>
                                        <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">ID: #<?= str_pad($user['user_id'], 5, '0', STR_PAD_LEFT) ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-8 py-6">
                                <div class="flex flex-col">
                                    <span class="text-sm font-semibold text-gray-700"><?= htmlspecialchars($user['email']) ?></span>
                                    <span class="text-[10px] text-gray-400">Verified Account</span>
                                </div>
                            </td>
                            <td class="px-8 py-6">
                                <p class="text-sm font-bold text-gray-800"><?= date('M d, Y', strtotime($user['created_at'])) ?></p>
                                <p class="text-[10px] text-gray-400"><?= date('h:i A', strtotime($user['created_at'])) ?></p>
                            </td>
                            <td class="px-8 py-6 text-center">
                                <span class="px-4 py-1.5 rounded-full text-[9px] font-black tracking-widest uppercase bg-green-100 text-green-600 border border-green-200">
                                    Active
                                </span>
                            </td>
                            <td class="px-8 py-6 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <button onclick="viewUser(<?= $user['user_id'] ?>)" class="p-2.5 bg-gray-50 text-gray-400 rounded-xl hover:bg-purple-100 hover:text-purple-600 transition" title="View Profile">
                                        <i class="bi bi-person-badge"></i>
                                    </button>
                                    <button onclick="confirmDelete(<?= $user['user_id'] ?>)" class="p-2.5 bg-red-50 text-red-400 rounded-xl hover:bg-red-500 hover:text-white transition" title="Remove User">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="py-24 text-center">
                                <div class="flex flex-col items-center gap-4">
                                    <i class="bi bi-people text-5xl text-gray-100"></i>
                                    <p class="text-xs font-bold text-gray-300 uppercase tracking-[0.2em]">No customers found in database</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
            document.getElementById('sidebarOverlay').classList.toggle('hidden');
        }

        function confirmDelete(id) {
            Swal.fire({
                title: 'Remove Customer?',
                text: "The user will lose access to their account and history. This cannot be undone.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#7c3aed',
                cancelButtonColor: '#f3f4f6',
                confirmButtonText: 'Confirm Delete',
                cancelButtonText: '<span style="color: #6b7280">Cancel</span>',
                customClass: {
                    popup: 'rounded-[2rem]',
                    confirmButton: 'rounded-xl px-8 py-3 text-xs font-black uppercase tracking-widest',
                    cancelButton: 'rounded-xl px-8 py-3 text-xs font-black uppercase tracking-widest'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `users.php?delete_id=${id}`;
                }
            })
        }

        function viewUser(id) {
            // This is where you'd trigger a modal to fetch user activity (bookings, etc.)
            Swal.fire({
                title: 'User Profile',
                text: 'Feature for viewing user booking history is being prepared.',
                icon: 'info',
                confirmButtonColor: '#7c3aed',
                customClass: { popup: 'rounded-[2rem]' }
            });
        }

        // Show toast for deleted message
        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
        Toast.fire({
            icon: 'success',
            title: 'Customer record removed'
        });
        <?php endif; ?>
    </script>
</body>
</html>
