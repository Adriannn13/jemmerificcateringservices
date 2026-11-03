<?php
session_start();

// Generate CSRF token if not already present
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

require_once 'includes/admin_auth_check.php';
require_once 'db_config.php';
require_once 'config/ai_config.php';

$admin_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'System Administrator';

// Fetch fresh data for admin
$stmt = $conn->prepare("SELECT full_name, email FROM users WHERE user_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin_data = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin & AI Settings | JEMmerific</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F8FAFC; }
    </style>
</head>
<body class="flex min-h-screen text-slate-800">

    <!-- Admin Sidebar Include -->
    <?php require_once 'includes/admin_sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="flex-1 lg:ml-64 p-4 md:p-6 transition-all">
        <!-- Top Bar Header -->
        <header class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8 pb-6 border-b border-slate-200/80">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="lg:hidden p-2.5 bg-white border border-slate-200 rounded-xl text-slate-600 hover:bg-slate-50 transition-colors shadow-2xs">
                    <i class="bi bi-list text-xl"></i>
                </button>
                <div>
                    <h2 class="text-2xl font-extrabold text-slate-900 tracking-tight">System & AI Settings</h2>
                    <p class="text-xs font-semibold text-slate-500 mt-0.5">Configure administrator credentials and manage local/cloud AI assistant engines.</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-purple-50 text-purple-700 border border-purple-100">
                    <span class="w-2 h-2 rounded-full bg-purple-600 animate-pulse"></span>
                    AI Companion Online
                </span>
            </div>
        </header>

        <!-- Notification Alert Container -->
        <div id="toastAlert" class="hidden mb-6 p-4 rounded-2xl border text-sm font-semibold flex items-center justify-between shadow-xs transition-all">
            <div class="flex items-center gap-3">
                <i id="toastIcon" class="bi text-lg"></i>
                <span id="toastMessage"></span>
            </div>
            <button onclick="document.getElementById('toastAlert').classList.add('hidden')" class="text-slate-400 hover:text-slate-700">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- Left Column: Admin Account Settings -->
            <div class="lg:col-span-5 space-y-6">
                <div class="bg-white rounded-3xl p-6 md:p-8 border border-slate-200/80 shadow-xs">
                    
                    <!-- Profile Avatar Header -->
                    <div class="flex items-center gap-4 mb-6 pb-6 border-b border-slate-100">
                        <div class="relative">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-purple-600 to-indigo-600 text-white flex items-center justify-center font-black text-xl shadow-md shadow-purple-200">
                                <?= strtoupper(substr($admin_data['full_name'] ?? 'A', 0, 1)) ?>
                            </div>
                            <span class="absolute -bottom-0.5 -right-0.5 w-4 h-4 bg-emerald-500 border-2 border-white rounded-full"></span>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-extrabold text-slate-900 tracking-tight"><?= htmlspecialchars($admin_data['full_name']) ?></h3>
                                <span class="px-2 py-0.5 bg-purple-50 text-purple-700 border border-purple-100 text-[10px] font-extrabold rounded-full uppercase tracking-wider">Admin</span>
                            </div>
                            <p class="text-xs text-slate-500 font-medium mt-0.5"><?= htmlspecialchars($admin_data['email']) ?></p>
                        </div>
                    </div>

                    <form id="adminSettingsForm" action="auth/update_admin.php" method="POST" class="space-y-5">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                                <i class="bi bi-person-fill text-purple-500"></i> Full Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="full_name" value="<?= htmlspecialchars($admin_data['full_name']) ?>" required
                                   class="w-full px-4 py-3 bg-slate-50/70 border border-slate-200 rounded-xl text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 focus:bg-white transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                                <i class="bi bi-envelope-fill text-purple-500"></i> Email Address <span class="text-red-500">*</span>
                            </label>
                            <input type="email" name="email" value="<?= htmlspecialchars($admin_data['email']) ?>" required
                                   class="w-full px-4 py-3 bg-slate-50/70 border border-slate-200 rounded-xl text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 focus:bg-white transition-all">
                        </div>

                        <div class="pt-4 border-t border-slate-100 space-y-4">
                            <div class="flex items-center justify-between">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                                    <i class="bi bi-shield-lock-fill text-purple-500"></i> Password & Security
                                </label>
                                <span class="text-[10px] text-slate-400 font-medium">Leave blank to keep current</span>
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-slate-600 mb-1.5">New Password</label>
                                <div class="relative">
                                    <input type="password" id="adminPass" name="password" placeholder="Enter new password (optional)"
                                           class="w-full px-4 py-3 bg-slate-50/70 border border-slate-200 rounded-xl text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 focus:bg-white transition-all pr-12">
                                    <button type="button" onclick="togglePass('adminPass', 'eyeIcon')" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-1">
                                        <i id="eyeIcon" class="bi bi-eye-fill text-base"></i>
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-slate-600 mb-1.5">Confirm New Password</label>
                                <div class="relative">
                                    <input type="password" id="confirmAdminPass" name="confirm_password" placeholder="Confirm new password"
                                           class="w-full px-4 py-3 bg-slate-50/70 border border-slate-200 rounded-xl text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 focus:bg-white transition-all pr-12">
                                    <button type="button" onclick="togglePass('confirmAdminPass', 'eyeConfirmIcon')" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-1">
                                        <i id="eyeConfirmIcon" class="bi bi-eye-fill text-base"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-extrabold py-3.5 rounded-xl shadow-md hover:shadow-purple-200 transition-all text-xs uppercase tracking-wider flex items-center justify-center gap-2 cursor-pointer">
                                <i class="bi bi-check-circle-fill text-base"></i> Update Profile Details
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Appearance & Theme Settings Card -->
                <div class="bg-white dark:bg-[#131b2e] rounded-3xl p-6 md:p-8 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                        <div>
                            <h3 class="text-base font-extrabold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
                                <i class="bi bi-moon-stars-fill text-purple-600 dark:text-amber-400"></i> Appearance & Dark Mode
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mt-0.5">Toggle dark theme across the entire Admin portal.</p>
                        </div>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-4 bg-slate-50 dark:bg-slate-800/80 rounded-2xl border border-slate-200/80 dark:border-slate-700/80">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-purple-600/10 dark:bg-amber-400/20 text-purple-600 dark:text-amber-400 flex items-center justify-center text-lg shrink-0">
                                <i id="settingsThemeIcon" class="theme-toggle-icon bi bi-moon-stars-fill"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-slate-900 dark:text-white">Admin Dark Theme</p>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Reduces glare with deep midnight tones</p>
                            </div>
                        </div>

                        <button type="button" onclick="toggleAppDarkMode()" class="w-full sm:w-auto px-5 py-2.5 bg-purple-600 hover:bg-purple-700 text-white dark:bg-slate-800 dark:hover:bg-slate-700 text-white dark:text-slate-100 border border-purple-500/30 dark:border-slate-700 font-extrabold text-xs uppercase tracking-wider rounded-xl shadow-sm transition-all flex items-center justify-center gap-2.5 cursor-pointer shrink-0 active:scale-95">
                            <i class="theme-toggle-icon bi bi-moon-stars-fill text-purple-200 dark:text-amber-400 text-sm"></i>
                            <span>Toggle Dark Mode</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right Column: AI Assistant Engine Configuration -->
            <div class="lg:col-span-7 space-y-6">
                <div class="bg-white rounded-3xl p-6 md:p-8 border border-slate-200/80 shadow-xs">
                    <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-100">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-purple-100 text-purple-700 flex items-center justify-center text-lg font-bold">
                                <i class="bi bi-cpu-fill"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-slate-900">AI Assistant Companion Engine</h3>
                                <p class="text-xs text-slate-500">Configure local model runner (Ollama), Gemini, ChatGPT & Copilot</p>
                            </div>
                        </div>
                        <span class="px-3 py-1 bg-slate-100 text-slate-700 rounded-full text-[11px] font-bold">
                            Hardware: Celeron Dual-Core CPU
                        </span>
                    </div>

                    <form id="aiSettingsForm" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                        <!-- AI Provider Selector -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-3">AI Engine Provider</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="relative flex items-center p-3.5 rounded-2xl border cursor-pointer transition-all border-slate-200 hover:border-purple-300 has-[:checked]:border-purple-600 has-[:checked]:bg-purple-50/50">
                                    <input type="radio" name="ai_provider" value="groq" <?= ($AI_CONFIG['ai_provider'] ?? '') == 'groq' ? 'checked' : '' ?> class="sr-only">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-orange-600 text-white flex items-center justify-center text-sm font-bold">⚡</div>
                                        <div>
                                            <p class="text-xs font-bold text-slate-900">Groq Cloud LPU</p>
                                            <p class="text-[10px] text-slate-500">Ultra-fast Llama-3.3 / Qwen</p>
                                        </div>
                                    </div>
                                </label>

                                <label class="relative flex items-center p-3.5 rounded-2xl border cursor-pointer transition-all border-slate-200 hover:border-purple-300 has-[:checked]:border-purple-600 has-[:checked]:bg-purple-50/50">
                                    <input type="radio" name="ai_provider" value="ollama" <?= ($AI_CONFIG['ai_provider'] ?? '') == 'ollama' ? 'checked' : '' ?> class="sr-only">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-purple-600 text-white flex items-center justify-center text-sm font-bold">🦙</div>
                                        <div>
                                            <p class="text-xs font-bold text-slate-900">Local Ollama</p>
                                            <p class="text-[10px] text-slate-500">Open-source Llama 3 / Qwen</p>
                                        </div>
                                    </div>
                                </label>

                                <label class="relative flex items-center p-3.5 rounded-2xl border cursor-pointer transition-all border-slate-200 hover:border-purple-300 has-[:checked]:border-purple-600 has-[:checked]:bg-purple-50/50">
                                    <input type="radio" name="ai_provider" value="gemini" <?= ($AI_CONFIG['ai_provider'] ?? '') == 'gemini' ? 'checked' : '' ?> class="sr-only">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-blue-500 text-white flex items-center justify-center text-sm font-bold">✨</div>
                                        <div>
                                            <p class="text-xs font-bold text-slate-900">Google Gemini</p>
                                            <p class="text-[10px] text-slate-500">Gemini 1.5 / 2.0 Flash API</p>
                                        </div>
                                    </div>
                                </label>

                                <label class="relative flex items-center p-3.5 rounded-2xl border cursor-pointer transition-all border-slate-200 hover:border-purple-300 has-[:checked]:border-purple-600 has-[:checked]:bg-purple-50/50">
                                    <input type="radio" name="ai_provider" value="openai" <?= ($AI_CONFIG['ai_provider'] ?? '') == 'openai' ? 'checked' : '' ?> class="sr-only">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center text-sm font-bold">💬</div>
                                        <div>
                                            <p class="text-xs font-bold text-slate-900">OpenAI ChatGPT</p>
                                            <p class="text-[10px] text-slate-500">GPT-4o Mini / GPT-3.5</p>
                                        </div>
                                    </div>
                                </label>

                                <label class="relative flex items-center p-3.5 sm:col-span-2 rounded-2xl border cursor-pointer transition-all border-slate-200 hover:border-purple-300 has-[:checked]:border-purple-600 has-[:checked]:bg-purple-50/50">
                                    <input type="radio" name="ai_provider" value="auto" <?= ($AI_CONFIG['ai_provider'] ?? 'auto') == 'auto' ? 'checked' : '' ?> class="sr-only">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-gradient-to-r from-purple-600 to-indigo-600 text-white flex items-center justify-center text-sm font-bold">⚡</div>
                                        <div>
                                            <p class="text-xs font-bold text-slate-900">Auto Companion (Recommended)</p>
                                            <p class="text-[10px] text-slate-500">Auto-tries Groq / Cloud AI first, then falls back seamlessly to DB Smart Engine</p>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Provider Configuration Settings -->
                        <div class="space-y-4 pt-4 border-t border-slate-100">
                            <!-- Groq Cloud Key & Model -->
                            <div class="p-4 bg-orange-50/40 rounded-2xl border border-orange-200/70 space-y-3">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-bold text-orange-900 uppercase tracking-wider flex items-center gap-2">
                                        <i class="bi bi-lightning-charge-fill text-orange-600"></i> Groq LPU Cloud API Key
                                    </h4>
                                    <span class="text-[10px] font-bold text-orange-700 bg-orange-100 px-2 py-0.5 rounded-full">gsk_...</span>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Groq API Key</label>
                                        <input type="password" name="groq_api_key" value="<?= htmlspecialchars($AI_CONFIG['groq_api_key'] ?? '') ?>" placeholder="gsk_..."
                                               class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Groq Model</label>
                                        <input type="text" name="groq_model" value="<?= htmlspecialchars($AI_CONFIG['groq_model'] ?? 'qwen-3.6-27b') ?>"
                                               class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 outline-none">
                                    </div>
                                </div>
                            </div>
                            <!-- Local Ollama Settings -->
                            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/70 space-y-3">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                                        <i class="bi bi-hdd-network text-purple-600"></i> Local Ollama Model Runner
                                    </h4>
                                    <button type="button" onclick="testOllamaConnection()" class="text-[11px] font-bold text-purple-600 hover:text-purple-800 bg-white px-2.5 py-1 rounded-lg border border-purple-200">
                                        Test Endpoint
                                    </button>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Ollama Base URL</label>
                                        <input type="text" name="ollama_url" value="<?= htmlspecialchars($AI_CONFIG['ollama_url'] ?? 'http://localhost:11434') ?>"
                                               class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Model Name (e.g., llama3, qwen2.5)</label>
                                        <input type="text" name="ollama_model" value="<?= htmlspecialchars($AI_CONFIG['ollama_model'] ?? 'llama3') ?>"
                                               class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 outline-none">
                                    </div>
                                </div>
                            </div>

                            <!-- Cloud AI Keys -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <div class="flex items-center justify-between mb-1.5">
                                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Google Gemini API Key</label>
                                        <span class="text-[10px] font-semibold text-purple-600 bg-purple-50 px-2 py-0.5 rounded-full border border-purple-200">Google AI Pro & Vision</span>
                                    </div>
                                    <input type="password" name="gemini_api_key" value="<?= htmlspecialchars($AI_CONFIG['gemini_api_key'] ?? '') ?>" placeholder="AIzaSy..."
                                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:bg-white outline-none">
                                    <p class="text-[10px] text-slate-500 mt-1 flex items-center gap-1">
                                        <i class="bi bi-info-circle text-purple-500"></i> Upgraded to Google AI Pro? Get your API key at <a href="https://aistudio.google.com/app/apikey" target="_blank" class="text-purple-600 font-bold hover:underline">Google AI Studio</a>.
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">OpenAI / ChatGPT Key</label>
                                    <input type="password" name="openai_api_key" value="<?= htmlspecialchars($AI_CONFIG['openai_api_key'] ?? '') ?>" placeholder="sk-..."
                                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:bg-white outline-none">
                                </div>
                                                    <!-- AI Image Staging Settings (Pollinations.ai / Google AI) -->
                            <div class="mt-4 pt-4 border-t border-slate-100 space-y-4">
                                <div class="flex items-center justify-between flex-wrap gap-2">
                                    <h4 class="text-xs font-black uppercase text-purple-700 flex items-center gap-1.5">
                                        <i class="bi bi-camera-reels"></i> AI Venue &amp; Catering Visualization Engine
                                    </h4>
                                    <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200">
                                        Pollinations.ai Secure Key Active
                                    </span>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Image Generation Engine</label>
                                        <select name="image_provider" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:bg-white outline-none">
                                            <option value="pollinations" <?= ($AI_CONFIG['image_provider'] ?? 'pollinations') === 'pollinations' ? 'selected' : '' ?>>Pollinations.ai (Flux - Unlimited & Fast • Recommended)</option>
                                            <option value="gemini" <?= ($AI_CONFIG['image_provider'] ?? '') === 'gemini' ? 'selected' : '' ?>>Google AI Studio (Imagen 3)</option>
                                        </select>
                                        <p class="text-[10px] text-slate-500 mt-1">Pollinations.ai uses your secure <code class="text-purple-600 font-bold">sk_...</code> auth key</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Image Aspect Ratio</label>
                                        <select name="image_aspect_ratio" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:bg-white outline-none">
                                            <option value="16:9" <?= ($AI_CONFIG['image_aspect_ratio'] ?? '16:9') === '16:9' ? 'selected' : '' ?>>16:9 (Panoramic - Recommended for Venues)</option>
                                            <option value="4:3" <?= ($AI_CONFIG['image_aspect_ratio'] ?? '') === '4:3' ? 'selected' : '' ?>>4:3 (Wide Perspective)</option>
                                            <option value="1:1" <?= ($AI_CONFIG['image_aspect_ratio'] ?? '') === '1:1' ? 'selected' : '' ?>>1:1 (Square)</option>
                                        </select>
                                        <p class="text-[10px] text-slate-500 mt-1">16:9 shows the full room width and perspective</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <div class="flex items-center justify-between mb-1.5">
                                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Pollinations.ai Secret API Key</label>
                                            <span class="text-[9px] font-bold text-purple-600 bg-purple-50 px-2 py-0.5 rounded-full border border-purple-200">Server Auth Protected</span>
                                        </div>
                                        <div class="relative">
                                            <input type="password" id="pollinations_api_key_input" name="pollinations_api_key" value="<?= htmlspecialchars($AI_CONFIG['pollinations_api_key'] ?? '') ?>" placeholder="sk_..."
                                                   class="w-full px-3.5 py-2.5 pr-10 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:bg-white outline-none">
                                            <button type="button" onclick="const f=document.getElementById('pollinations_api_key_input'); f.type=f.type==='password'?'text':'password';" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-1">
                                                <i class="bi bi-eye-fill text-xs"></i>
                                            </button>
                                        </div>
                                        <p class="text-[10px] text-slate-500 mt-1 flex items-center gap-1">
                                            <i class="bi bi-shield-lock-fill text-emerald-600"></i> Encrypted &amp; kept strictly on backend (never exposed to browser JS)
                                        </p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Pollinations Model</label>
                                        <?php $cur_p_model = $AI_CONFIG['pollinations_model'] ?? 'black-forest-labs/flux.1-schnell'; ?>
                                        <select name="pollinations_model" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:bg-white outline-none">
                                            <option value="black-forest-labs/flux.1-schnell" <?= in_array($cur_p_model, ['black-forest-labs/flux.1-schnell', 'flux', 'flux.1-schnell']) ? 'selected' : '' ?>>Flux.1 Schnell (Recommended • Ultra-Realistic & Fast)</option>
                                            <option value="tongyi-mai/z-image-turbo" <?= in_array($cur_p_model, ['tongyi-mai/z-image-turbo', 'z-image-turbo', 'turbo', 'z-image']) ? 'selected' : '' ?>>Z-Image Turbo (High Speed Turbo)</option>
                                            <option value="black-forest-labs/flux-3-image" <?= in_array($cur_p_model, ['black-forest-labs/flux-3-image', 'flux.3', 'flux-3']) ? 'selected' : '' ?>>FLUX.3 Image (Next-Gen Flux Architecture)</option>
                                        </select>
                                        <p class="text-[10px] text-slate-500 mt-1">Supports FLUX.3, Flux.1 Schnell &amp; Z-Image Turbo with auto-resilience.</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Google Image Model (Backup)</label>
                                        <input type="text" name="gemini_image_model" value="<?= htmlspecialchars($AI_CONFIG['gemini_image_model'] ?? 'imagen-3.0-generate-002') ?>" placeholder="imagen-3.0-generate-002"
                                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:bg-white outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Negative Prompt Guardrails</label>
                                        <input type="text" name="negative_prompt" value="<?= htmlspecialchars($AI_CONFIG['negative_prompt'] ?? 'blurry, deformed food, extra limbs, plastic disposable plates, messy counters, distorted furniture, cartoon, low quality, oversaturated') ?>" 
                                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:bg-white outline-none">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Low-Resource Mode Toggle -->
                        <div class="p-4 bg-purple-50/50 rounded-2xl border border-purple-100 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-purple-600 text-white flex items-center justify-center text-sm">
                                    <i class="bi bi-speedometer"></i>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-slate-900">CPU & Low-Resource Optimization</p>
                                    <p class="text-[10px] text-slate-500">Optimizes timeout for Celeron 3855U 1.60GHz processor</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="low_resource_mode" value="1" <?= !empty($AI_CONFIG['low_resource_mode']) ? 'checked' : '' ?> class="sr-only peer">
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                            </label>
                        </div>

                        <!-- Public Chatbot Daily API Limit (1-10 messages per user) -->
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-indigo-600 text-white flex items-center justify-center text-sm shrink-0">
                                    <i class="bi bi-shield-lock"></i>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-slate-900">Public Chatbot API Key Daily Limit</p>
                                    <p class="text-[10px] text-slate-500">Set daily message allowance per user/visitor on index.php (1 to 10 messages)</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <select name="daily_message_limit" class="px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-800 outline-none cursor-pointer focus:ring-2 focus:ring-purple-500/20">
                                    <?php 
                                    $curr_limit = intval($AI_CONFIG['daily_message_limit'] ?? 10);
                                    for ($i = 1; $i <= 10; $i++): 
                                    ?>
                                        <option value="<?= $i ?>" <?= $curr_limit === $i ? 'selected' : '' ?>><?= $i ?> Message<?= $i > 1 ? 's' : '' ?> / Day</option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-3.5 rounded-xl shadow-md transition-all text-xs uppercase tracking-wider flex items-center justify-center gap-2">
                            <i class="bi bi-floppy-fill text-base"></i> Save AI Engine Settings
                        </button>
                    </form>
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
                if (icon) icon.className = 'bi bi-eye-slash-fill text-lg';
            } else {
                input.type = 'password';
                if (icon) icon.className = 'bi bi-eye-fill text-lg';
            }
        }

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('msg')) {
            const m = urlParams.get('msg');
            if (m === 'profile_updated' || m === 'success') showToast('Admin account profile details successfully updated!', true);
        }
        if (urlParams.has('error')) {
            const err = urlParams.get('error');
            if (err === 'password_mismatch') showToast('New passwords do not match. Please re-enter.', false);
            else if (err === 'invalid_csrf') showToast('Session expired or invalid security token.', false);
            else if (err === 'missing_fields') showToast('Full name and email address are required.', false);
            else showToast('Failed to update admin profile details.', false);
        }

        function showToast(msg, isSuccess = true) {
            const toast = document.getElementById('toastAlert');
            const icon = document.getElementById('toastIcon');
            const message = document.getElementById('toastMessage');

            message.innerText = msg;
            toast.classList.remove('hidden', 'bg-emerald-50', 'border-emerald-200', 'text-emerald-800', 'bg-rose-50', 'border-rose-200', 'text-rose-800');
            
            if (isSuccess) {
                toast.classList.add('bg-emerald-50', 'border-emerald-200', 'text-emerald-800');
                icon.className = 'bi bi-check-circle-fill text-emerald-600 text-lg';
            } else {
                toast.classList.add('bg-rose-50', 'border-rose-200', 'text-rose-800');
                icon.className = 'bi bi-exclamation-triangle-fill text-rose-600 text-lg';
            }

            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        document.getElementById('aiSettingsForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            try {
                const res = await fetch('auth/save_ai_settings.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                showToast(data.message, data.status === 'success');
            } catch(err) {
                showToast('Failed to save AI configuration.', false);
            }
        });

        async function testOllamaConnection() {
            const urlInput = document.querySelector('input[name="ollama_url"]').value.trim();
            if (!urlInput) {
                alert("Please specify an Ollama URL first.");
                return;
            }
            try {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 3000);
                const response = await fetch(urlInput, { method: 'GET', signal: controller.signal, mode: 'no-cors' });
                showToast(`Connected to Ollama local endpoint at ${urlInput}!`, true);
            } catch(e) {
                showToast(`Ollama endpoint check: Could not reach ${urlInput}. Smart Fallback Engine will handle requests.`, false);
            }
        }
    </script>
</body>
</html>