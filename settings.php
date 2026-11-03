<?php 
// settings.php - Customer Account & Password Settings (Minimalist Professional Design)
require_once 'includes/auth_check.php'; 
require_once 'db_config.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'Valued Client';

// Fetch fresh user profile data
$stmt = $conn->prepare("SELECT full_name, email, created_at FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

// Fetch customer stats for sidebar cards
$b_stmt = $conn->prepare("SELECT COUNT(*) as total_bookings, SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed_bookings FROM bookings WHERE user_id = ?");
$b_stmt->bind_param("i", $user_id);
$b_stmt->execute();
$b_stats = $b_stmt->get_result()->fetch_assoc();
$total_bookings = $b_stats['total_bookings'] ?? 0;
$completed_bookings = $b_stats['completed_bookings'] ?? 0;

$msg = $_GET['msg'] ?? $_GET['status'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings | JEMmerific</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F8FAFC; }
    </style>
</head>
<body class="flex min-h-screen text-slate-800 antialiased">

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
                    <h2 class="text-2xl font-extrabold text-slate-900 tracking-tight">Account Settings</h2>
                    <p class="text-xs font-semibold text-slate-500 mt-0.5">Manage your personal profile information and update security credentials.</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="dashboard.php" class="px-4 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl shadow-md shadow-purple-200 text-xs uppercase tracking-wider transition-all flex items-center gap-2">
                    <i class="bi bi-plus-lg"></i> Book New Event
                </a>
            </div>
        </header>

        <!-- Status Alerts -->
        <?php if ($msg === 'profile_success' || $msg === 'success'): ?>
            <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold flex items-center justify-between shadow-2xs">
                <div class="flex items-center gap-3">
                    <i class="bi bi-check-circle-fill text-emerald-600 text-base"></i>
                    <span>Your account profile details have been successfully updated!</span>
                </div>
                <a href="settings.php" class="text-emerald-700 hover:text-emerald-900"><i class="bi bi-x-lg"></i></a>
            </div>
        <?php endif; ?>

        <?php if ($msg === 'pass_success'): ?>
            <div class="mb-6 p-4 rounded-2xl bg-purple-50 border border-purple-200 text-purple-900 text-xs font-semibold flex items-center justify-between shadow-2xs">
                <div class="flex items-center gap-3">
                    <i class="bi bi-shield-check text-purple-600 text-lg"></i>
                    <span>Security Notice: Your password has been updated successfully!</span>
                </div>
                <a href="settings.php" class="text-purple-700 hover:text-purple-900"><i class="bi bi-x-lg"></i></a>
            </div>
        <?php endif; ?>

        <?php if ($msg === 'invalid_current'): ?>
            <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold flex items-center justify-between shadow-2xs">
                <div class="flex items-center gap-3">
                    <i class="bi bi-exclamation-triangle-fill text-rose-500 text-base"></i>
                    <span>Error: The current password you entered is incorrect. Please try again.</span>
                </div>
                <a href="settings.php" class="text-rose-700 hover:text-rose-900"><i class="bi bi-x-lg"></i></a>
            </div>
        <?php endif; ?>

        <?php if ($msg === 'current_required'): ?>
            <div class="mb-6 p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-800 text-xs font-semibold flex items-center justify-between shadow-2xs">
                <div class="flex items-center gap-3">
                    <i class="bi bi-info-circle-fill text-amber-600 text-base"></i>
                    <span>Please enter your current password to authorize setting a new password.</span>
                </div>
                <a href="settings.php" class="text-amber-700 hover:text-amber-900"><i class="bi bi-x-lg"></i></a>
            </div>
        <?php endif; ?>

        <?php if ($msg === 'pass_mismatch'): ?>
            <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold flex items-center justify-between shadow-2xs">
                <div class="flex items-center gap-3">
                    <i class="bi bi-exclamation-octagon-fill text-rose-500 text-base"></i>
                    <span>Error: The new password and confirmation password do not match.</span>
                </div>
                <a href="settings.php" class="text-rose-700 hover:text-rose-900"><i class="bi bi-x-lg"></i></a>
            </div>
        <?php endif; ?>

        <!-- Layout Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- Main Content Form (8 Cols) -->
            <div class="lg:col-span-8 space-y-8">
                
                <!-- Profile Header Banner -->
                <div class="bg-white dark:bg-[#131b2e] p-6 md:p-8 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-xs flex flex-col sm:flex-row items-center gap-6">
                    <div class="w-20 h-20 rounded-2xl bg-gradient-to-tr from-purple-600 to-indigo-600 text-white flex items-center justify-center text-3xl font-extrabold shadow-sm dark:shadow-none border border-white/20 dark:border-slate-700 uppercase shrink-0">
                        <?= substr($user_name, 0, 1) ?>
                    </div>
                    <div class="text-center sm:text-left flex-1">
                        <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2">
                            <h3 class="text-xl font-extrabold text-slate-900"><?= htmlspecialchars($user_data['full_name'] ?? $user_name) ?></h3>
                            <span class="px-2.5 py-0.5 bg-purple-50 text-purple-700 border border-purple-100 rounded-full text-[10px] font-bold uppercase tracking-wider">Catering Client</span>
                        </div>
                        <p class="text-xs font-semibold text-slate-500 mt-1"><?= htmlspecialchars($user_data['email'] ?? '') ?></p>
                        <div class="flex flex-wrap items-center justify-center sm:justify-start gap-3 mt-3 text-xs font-semibold">
                            <span class="px-3 py-1 bg-slate-100 text-slate-600 rounded-full flex items-center gap-1.5 text-[11px]">
                                <i class="bi bi-calendar3 text-slate-400"></i> Member since <?= date("M Y", strtotime($user_data['created_at'] ?? 'now')) ?>
                            </span>
                            <span class="px-3 py-1 bg-emerald-50 text-emerald-700 border border-emerald-100 rounded-full flex items-center gap-1.5 text-[11px]">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Protected Account
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Appearance & Theme Settings Card -->
                <div class="bg-white dark:bg-[#131b2e] rounded-3xl p-6 md:p-8 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4 mb-8">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                        <div>
                            <h3 class="text-base font-extrabold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
                                <i class="bi bi-moon-stars-fill text-purple-600 dark:text-amber-400"></i> Appearance & Dark Mode
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mt-0.5">Toggle dark theme across your Customer Catering Portal.</p>
                        </div>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-4 bg-slate-50 dark:bg-slate-800/80 rounded-2xl border border-slate-200/80 dark:border-slate-700/80">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-purple-600/10 dark:bg-amber-400/20 text-purple-600 dark:text-amber-400 flex items-center justify-center text-lg shrink-0">
                                <i id="settingsThemeIcon" class="theme-toggle-icon bi bi-moon-stars-fill"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-slate-900 dark:text-white">Customer Portal Dark Theme</p>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Enjoy comfortable night viewing for event planning</p>
                            </div>
                        </div>

                        <button type="button" onclick="toggleAppDarkMode()" class="w-full sm:w-auto px-5 py-2.5 bg-purple-600 hover:bg-purple-700 text-white dark:bg-slate-800 dark:hover:bg-slate-700 text-white dark:text-slate-100 border border-purple-500/30 dark:border-slate-700 font-extrabold text-xs uppercase tracking-wider rounded-xl shadow-sm transition-all flex items-center justify-center gap-2.5 cursor-pointer shrink-0 active:scale-95">
                            <i class="theme-toggle-icon bi bi-moon-stars-fill text-purple-200 dark:text-amber-400 text-sm"></i>
                            <span>Toggle Dark Mode</span>
                        </button>
                    </div>
                </div>

                <!-- Update Settings Form -->
                <form action="update_profile.php" method="POST" class="space-y-8">
                    
                    <!-- General Profile Details Card -->
                    <div class="bg-white p-6 md:p-8 rounded-3xl border border-slate-200/80 shadow-xs space-y-6">
                        <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                            <div class="w-9 h-9 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-lg font-bold">
                                <i class="bi bi-person-fill"></i>
                            </div>
                            <div>
                                <h4 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider">General Profile Details</h4>
                                <p class="text-[11px] text-slate-500">Your account identity & primary contact information</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-extrabold text-slate-700 uppercase tracking-wider mb-2">Full Name</label>
                                <input type="text" name="full_name" value="<?= htmlspecialchars($user_data['full_name'] ?? '') ?>" required 
                                       class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-purple-500/20 focus:border-purple-600 outline-none transition-all">
                            </div>

                            <div>
                                <label class="block text-xs font-extrabold text-slate-700 uppercase tracking-wider mb-2">Email Address</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($user_data['email'] ?? '') ?>" required 
                                       class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-purple-500/20 focus:border-purple-600 outline-none transition-all">
                            </div>
                        </div>
                    </div>

                    <!-- MINIMALIST PROFESSIONAL SECURITY & CHANGE PASSWORD CARD -->
                    <div class="bg-white p-6 md:p-8 rounded-3xl border border-slate-200/80 shadow-xs space-y-6">
                        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-lg font-bold">
                                    <i class="bi bi-shield-lock-fill"></i>
                                </div>
                                <div>
                                    <h4 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider">Change Password & Security</h4>
                                    <p class="text-[11px] text-slate-500">Update your account login password with high security</p>
                                </div>
                            </div>
                            <span class="px-2.5 py-1 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-full uppercase tracking-wider">Optional</span>
                        </div>

                        <div class="space-y-4">
                            <!-- Current Password -->
                            <div>
                                <label class="block text-xs font-extrabold text-slate-700 uppercase tracking-wider mb-2">Current Password</label>
                                <div class="relative max-w-md">
                                    <input type="password" id="currentPassword" name="current_password" placeholder="Enter current password to authorize change" 
                                           class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-purple-500/20 focus:border-purple-600 outline-none transition-all pr-10">
                                    <button type="button" onclick="togglePass('currentPassword', 'currEye')" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                        <i id="currEye" class="bi bi-eye-fill text-base"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- New Password & Confirm Password Grid -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                                <div>
                                    <label class="block text-xs font-extrabold text-slate-700 uppercase tracking-wider mb-2">New Password</label>
                                    <div class="relative">
                                        <input type="password" id="newPassword" name="new_password" placeholder="Minimum 6 characters" minlength="6" oninput="validatePassword()"
                                               class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-purple-500/20 focus:border-purple-600 outline-none transition-all pr-10">
                                        <button type="button" onclick="togglePass('newPassword', 'newEye')" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                            <i id="newEye" class="bi bi-eye-fill text-base"></i>
                                        </button>
                                    </div>
                                    <!-- Password Strength Indicator Bar -->
                                    <div class="mt-2 flex items-center gap-2">
                                        <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden flex gap-1">
                                            <div id="strBar1" class="h-full w-1/3 bg-slate-200 transition-all"></div>
                                            <div id="strBar2" class="h-full w-1/3 bg-slate-200 transition-all"></div>
                                            <div id="strBar3" class="h-full w-1/3 bg-slate-200 transition-all"></div>
                                        </div>
                                        <span id="strText" class="text-[10px] font-bold text-slate-400 uppercase">Too short</span>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-extrabold text-slate-700 uppercase tracking-wider mb-2">Confirm New Password</label>
                                    <div class="relative">
                                        <input type="password" id="confirmPassword" name="confirm_password" placeholder="Re-enter new password" minlength="6" oninput="validatePassword()"
                                               class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-purple-500/20 focus:border-purple-600 outline-none transition-all pr-10">
                                        <button type="button" onclick="togglePass('confirmPassword', 'confEye')" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                            <i id="confEye" class="bi bi-eye-fill text-base"></i>
                                        </button>
                                    </div>
                                    <div id="matchBadge" class="mt-2 text-[10px] font-bold text-slate-400 flex items-center gap-1">
                                        <span>Repeat password to confirm match</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Save Action Button -->
                    <div class="flex justify-end">
                        <button type="submit" class="w-full sm:w-auto px-8 py-3.5 bg-purple-600 hover:bg-purple-700 text-white font-extrabold rounded-xl shadow-md shadow-purple-200 transition-all text-xs uppercase tracking-wider flex items-center justify-center gap-2 cursor-pointer">
                            <i class="bi bi-floppy-fill text-sm"></i> Save Settings & Changes
                        </button>
                    </div>
                </form>

            </div>

            <!-- Right Column: Account Stats Cards (4 Cols) -->
            <div class="lg:col-span-4 space-y-6">
                
                <!-- Activity Overview -->
                <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-xs space-y-4">
                    <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                        <div class="w-8 h-8 rounded-lg bg-purple-100 text-purple-700 flex items-center justify-center text-sm font-bold">
                            <i class="bi bi-bar-chart-fill"></i>
                        </div>
                        <h4 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider">Catering Activity Overview</h4>
                    </div>

                    <div class="grid grid-cols-2 gap-3 pt-1">
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 text-center">
                            <p class="text-2xl font-black text-purple-700"><?= $total_bookings ?></p>
                            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mt-1">Total Events</p>
                        </div>
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 text-center">
                            <p class="text-2xl font-black text-emerald-600"><?= $completed_bookings ?></p>
                            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mt-1">Completed</p>
                        </div>
                    </div>
                </div>

                <!-- Security Standard Notice -->
                <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-xs space-y-4">
                    <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                        <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center text-sm font-bold">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <h4 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider">Security Protection</h4>
                    </div>

                    <div class="space-y-3 text-xs font-semibold text-slate-700">
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-2"><i class="bi bi-lock-fill text-slate-400"></i> Password Encryption</span>
                            <span class="text-emerald-600 font-bold">Bcrypt Hashing</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-2"><i class="bi bi-person-check-fill text-slate-400"></i> Session Protection</span>
                            <span class="text-emerald-600 font-bold">Active</span>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </main>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
            document.getElementById('sidebarOverlay').classList.toggle('hidden');
        }

        function togglePass(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (input.type === 'password') {
                input.type = 'text';
                if (icon) icon.className = 'bi bi-eye-slash-fill text-base';
            } else {
                input.type = 'password';
                if (icon) icon.className = 'bi bi-eye-fill text-base';
            }
        }

        // Live Password Match & Strength Validator
        function validatePassword() {
            const newP = document.getElementById('newPassword').value;
            const confP = document.getElementById('confirmPassword').value;

            const b1 = document.getElementById('strBar1');
            const b2 = document.getElementById('strBar2');
            const b3 = document.getElementById('strBar3');
            const txt = document.getElementById('strText');
            const matchBadge = document.getElementById('matchBadge');

            // Strength Calculation
            if (newP.length === 0) {
                b1.className = 'h-full w-1/3 bg-slate-200 transition-all';
                b2.className = 'h-full w-1/3 bg-slate-200 transition-all';
                b3.className = 'h-full w-1/3 bg-slate-200 transition-all';
                txt.textContent = 'Too short';
                txt.className = 'text-[10px] font-bold text-slate-400 uppercase';
            } else if (newP.length < 6) {
                b1.className = 'h-full w-1/3 bg-rose-500 transition-all';
                b2.className = 'h-full w-1/3 bg-slate-200 transition-all';
                b3.className = 'h-full w-1/3 bg-slate-200 transition-all';
                txt.textContent = 'Weak';
                txt.className = 'text-[10px] font-bold text-rose-500 uppercase';
            } else if (newP.length >= 6 && /\d/.test(newP)) {
                b1.className = 'h-full w-1/3 bg-emerald-500 transition-all';
                b2.className = 'h-full w-1/3 bg-emerald-500 transition-all';
                b3.className = 'h-full w-1/3 bg-emerald-500 transition-all';
                txt.textContent = 'Strong';
                txt.className = 'text-[10px] font-bold text-emerald-600 uppercase';
            } else {
                b1.className = 'h-full w-1/3 bg-amber-500 transition-all';
                b2.className = 'h-full w-1/3 bg-amber-500 transition-all';
                b3.className = 'h-full w-1/3 bg-slate-200 transition-all';
                txt.textContent = 'Medium';
                txt.className = 'text-[10px] font-bold text-amber-600 uppercase';
            }

            // Match Check
            if (confP.length === 0) {
                matchBadge.innerHTML = '<span class="text-slate-400">Repeat password to confirm match</span>';
            } else if (newP === confP) {
                matchBadge.innerHTML = '<span class="text-emerald-600 font-extrabold flex items-center gap-1"><i class="bi bi-check-circle-fill"></i> Passwords match</span>';
            } else {
                matchBadge.innerHTML = '<span class="text-rose-500 font-extrabold flex items-center gap-1"><i class="bi bi-x-circle-fill"></i> Passwords do not match</span>';
            }
        }
    </script>
</body>
</html>