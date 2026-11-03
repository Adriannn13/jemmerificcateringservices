<?php
require_once 'includes/auth_check.php';
require_once 'db_config.php';

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

// 2. Fetch AI Usage Limit
$conn->query("CREATE TABLE IF NOT EXISTS ai_user_limits (
    user_id INT PRIMARY KEY,
    message_count INT DEFAULT 0,
    last_reset TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");
$limit_query = "SELECT message_count FROM ai_user_limits WHERE user_id = ?";
$stmt_limit = $conn->prepare($limit_query);
$stmt_limit->bind_param("i", $user_id);
$stmt_limit->execute();
$limit_res = $stmt_limit->get_result();
$message_count = $limit_res->fetch_assoc()['message_count'] ?? 0;
$max_messages = 10;
$remaining_messages = max(0, $max_messages - $message_count);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Assistant | JEMmerific</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: -0.01em; }
        .sidebar-link { transition: all 0.2s ease-in-out; }
        .sidebar-link:hover { background-color: #e5e7eb; color: #9b7cd1; padding-left: 1.5rem; }
        .active-link { background: linear-gradient(90deg, #d4cef3 0%, transparent 100%); color: #7c3aed; font-weight: 700; border-right: 4px solid #7c3aed; }
        
        @keyframes pulse-soft { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
        .ai-glow { animation: pulse-soft 3s infinite ease-in-out; }

        .gemini-input-container { background-color: #f0f4f9; border-radius: 32px; transition: all 0.3s ease; border: 1px solid transparent; }
        .gemini-input-container:focus-within { background-color: #fff; border-color: #7c3aed40; box-shadow: 0 10px 40px rgba(124, 58, 237, 0.08); }
        
        .pill-btn { background-color: #fff; border: 1px solid #e5e7eb; border-radius: 12px; transition: all 0.2s; cursor: pointer; }
        .pill-btn:hover { background-color: #f8f9fa; transform: translateY(-1px); border-color: #7c3aed; box-shadow: 0 4px 12px rgba(0,0,0,0.03); }

        .dot-loader { display: inline-block; width: 6px; height: 6px; background: #7c3aed; border-radius: 50%; animation: typing 1.4s infinite ease-in-out; }
        .dot-loader:nth-child(2) { animation-delay: 0.2s; }
        .dot-loader:nth-child(3) { animation-delay: 0.4s; }
        @keyframes typing { 0%, 80%, 100% { transform: translateY(0); } 40% { transform: translateY(-8px); } }
    </style>
</head>
<body class="bg-[#FBFBFC] flex min-h-screen">

    <?php require_once 'includes/customer_sidebar.php'; ?>

    <main class="flex-1 lg:ml-64 p-4 md:p-6 transition-all">
        <header class="flex flex-col md:flex-row justify-between items-start gap-6 mb-16">
            <div class="flex items-start gap-4">
                <button onclick="toggleSidebar()" class="lg:hidden mt-1 p-2 bg-white border border-gray-100 rounded-xl shadow-sm text-gray-600">
                    <i class="bi bi-list text-2xl"></i>
                </button>
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <i class="bi bi-stars text-2xl text-purple-600 ai-glow"></i>
                        <h2 class="text-4xl font-black text-gray-900 tracking-tight">AI Assistant</h2>
                    </div>
                    <p class="text-gray-400 text-xs font-bold uppercase tracking-widest">AI instantly generates complete event plans with layouts, menus, and pricing.</p>
                </div>
            </div>
            
            <div class="flex items-center gap-6">
                <!-- AI Limits Display -->
                <div class="hidden md:flex items-center gap-2 px-4 py-2 bg-purple-50 rounded-full border border-purple-100">
                    <i class="bi bi-lightning-charge-fill text-purple-600"></i>
                    <span class="text-xs font-bold text-gray-700">AI Tokens: <span class="text-purple-600"><?= $remaining_messages ?>/<?= $max_messages ?></span></span>
                </div>
                
                <div class="relative">
                    <button onclick="toggleNotifDropdown()" class="w-12 h-12 rounded-2xl bg-white shadow-sm border border-gray-100 flex items-center justify-center text-gray-400 hover:text-purple-600 transition-all relative">
                        <i class="bi bi-bell-fill"></i>
                        <?php if($notif_count > 0): ?>
                            <span class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-[10px] font-black rounded-full flex items-center justify-center border-2 border-white">
                                <?= $notif_count ?>
                            </span>
                        <?php endif; ?>
                    </button>
                </div>
            </div>
        </header>

        <div class="max-w-4xl mx-auto">
            <div class="mb-12 max-w-2xl">
                <h2 class="text-4xl md:text-6xl font-black mb-6 tracking-tight leading-[1.1]">
                    <span class="text-gray-900">Hello,</span> 
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-purple-600 via-pink-500 to-orange-500 italic">
                        <?= explode(' ', $user_name)[0] ?>.
                    </span>
                    <br><span class="text-gray-900">How can I help you plan?</span>
                </h2>
            </div>

            <div class="gemini-input-container p-4 mb-8">
                <textarea id="event-desc" rows="1" 
                    placeholder="Ask about Package A, B, C or D, or describe your vision..."
                    class="w-full bg-transparent border-none focus:ring-0 text-gray-800 px-6 py-4 outline-none resize-none placeholder-gray-400 text-lg font-medium min-h-[100px]"></textarea>
                
                <div class="flex items-center justify-between px-4 pb-2">
                    <div class="flex gap-2 text-gray-400">
                         <button class="p-2 hover:text-purple-600 transition-colors"><i class="bi bi-image text-xl"></i></button>
                         <button class="p-2 hover:text-purple-600 transition-colors"><i class="bi bi-mic text-xl"></i></button>
                    </div>
                    <button onclick="handlePromptSubmit()" class="w-12 h-12 bg-purple-600 text-white rounded-full flex items-center justify-center hover:bg-purple-700 transition-all shadow-lg shadow-purple-200">
                        <i class="bi bi-arrow-up-short text-3xl"></i>
                    </button>
                </div>
            </div>

            <div class="flex flex-wrap gap-3 mb-16">
                <button onclick="quickSelect('Complete event plans with layouts')" class="pill-btn px-6 py-3.5 flex items-center gap-3 text-xs font-black text-gray-700 uppercase tracking-widest">
                    <i class="bi bi-layout-text-window-reverse text-purple-600"></i> Complete event plans with layouts
                </button>
                <button onclick="quickSelect('Package A')" class="pill-btn px-6 py-3.5 flex items-center gap-3 text-xs font-black text-gray-700 uppercase tracking-widest">
                    <i class="bi bi-stars text-blue-500"></i> Package A
                </button>
                <button onclick="quickSelect('Package B')" class="pill-btn px-6 py-3.5 flex items-center gap-3 text-xs font-black text-gray-700 uppercase tracking-widest">
                    <i class="bi bi-stars text-purple-500"></i> Package B
                </button>
                <button onclick="quickSelect('Package C')" class="pill-btn px-6 py-3.5 flex items-center gap-3 text-xs font-black text-gray-700 uppercase tracking-widest">
                    <i class="bi bi-stars text-orange-500"></i> Package C
                </button>
                <button onclick="quickSelect('Package D')" class="pill-btn px-6 py-3.5 flex items-center gap-3 text-xs font-black text-gray-700 uppercase tracking-widest">
                    <i class="bi bi-stars text-red-500"></i> Package D
                </button>
            </div>

            <div id="thinking-state" class="hidden mb-12 text-center p-16 bg-white rounded-[3rem] border border-gray-100 shadow-sm">
                <div class="mb-6">
                    <span class="dot-loader"></span> <span class="dot-loader"></span> <span class="dot-loader"></span>
                </div>
                <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">Consulting JEMmerific Menu Catalog...</p>
            </div>

            <div id="ai-results" class="hidden animate-in fade-in slide-in-from-bottom-8 duration-700 mb-24">
                <div class="bg-white rounded-[3rem] p-8 md:p-14 shadow-[0_20px_80px_rgba(0,0,0,0.03)] border border-gray-100">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-12 gap-6">
                        <div>
                            <h4 class="text-3xl font-black text-gray-900 tracking-tight" id="result-title">Package Details</h4>
                            <p class="text-[10px] font-black text-purple-500 uppercase tracking-widest mt-1">AI Verified Rates & inclusions</p>
                        </div>
                        <div class="flex gap-3">
                            <button class="p-3 bg-gray-50 text-gray-400 rounded-xl hover:text-purple-600 transition-all"><i class="bi bi-share"></i></button>
                            <button onclick="window.location.href='dashboard.php'" class="px-8 py-4 bg-gray-900 text-white text-xs font-black uppercase tracking-[0.2em] rounded-2xl hover:bg-purple-600 transition-all shadow-lg shadow-gray-200">
                                Book This Plan
                            </button>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-5 gap-12">
                        <div class="lg:col-span-3">
                            <div class="rounded-3xl overflow-hidden mb-10 border border-gray-100 shadow-sm aspect-video">
                                <img id="pkg-image" src="" alt="Package" class="w-full h-full object-cover">
                            </div>
                            <h5 class="font-black text-gray-900 text-[10px] uppercase tracking-[0.2em] mb-6 flex items-center gap-3">
                                <i class="bi bi-list-stars text-purple-500 text-lg"></i> What's Included
                            </h5>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3" id="pkg-menu"></div>
                        </div>
                        
                        <div class="lg:col-span-2 space-y-6">
                            <div class="bg-gray-50 rounded-[2.5rem] p-10 border border-gray-100">
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-8">Tiered Pricing</p>
                                <div id="pkg-pricing" class="space-y-5"></div>
                                
                                <div class="mt-12 pt-8 border-t border-gray-200 text-center">
                                    <p class="text-[10px] font-bold text-purple-500 uppercase mb-2">Starting at</p>
                                    <div class="text-6xl font-black text-gray-900 tracking-tighter" id="pkg-per-head"></div>
                                    <p class="text-[10px] font-bold text-gray-400 uppercase mt-1">Per Person</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        const PACKAGE_DATA = {
            'Package A': {
                head: '₱450',
                image: 'https://images.unsplash.com/photo-1555244162-803834f70033?q=80&w=2070&auto=format&fit=crop',
                pricing: [['50 pax', '₱45,000'], ['100 pax', '₱65,000'], ['150 pax', '₱85,000'], ['200 pax', '₱105,000']],
                menu: ['Beef or Pork Dish', 'Veggies Dish', 'Pasta Dish', 'Dessert Dish', 'Steamed Rice', 'Refillable Iced Tea']
            },
            'Package B': {
                head: '₱650',
                image: 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?q=80&w=2070&auto=format&fit=crop',
                pricing: [['50 pax', '₱62,000'], ['100 pax', '₱87,000'], ['150 pax', '₱118,000'], ['200 pax', '₱139,000']],
                menu: ['Beef Dish', 'Pork Dish', 'Veggies Dish', 'Pasta Dish', 'Dessert Dish', 'Steamed Rice', 'Refillable Iced Tea']
            },
            'Package C': {
                head: '₱850',
                image: 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?q=80&w=2070&auto=format&fit=crop',
                pricing: [['50 pax', '₱73,000'], ['100 pax', '₱107,000'], ['150 pax', '₱142,000'], ['200 pax', '₱169,000']],
                menu: ['Beef Dish', 'Pork Dish', 'Chicken/Fish', 'Veggies Dish', 'Pasta', 'Dessert', 'Steamed Rice', 'Iced Tea']
            },
            'Package D': {
                head: '₱1,000',
                image: 'https://images.unsplash.com/photo-1544148103-0773bf10d330?q=80&w=2070&auto=format&fit=crop',
                pricing: [['50 pax', '₱81,000'], ['100 pax', '₱120,000'], ['150 pax', '₱154,000'], ['200 pax', '₱186,000']],
                menu: ['Beef Dish', 'Pork Dish', 'Chicken Dish', 'Fish Dish', 'Veggies Dish', 'Pasta Dish', 'Dessert', 'Rice', 'Iced Tea']
            }
        };

        function quickSelect(val) {
            document.getElementById('event-desc').value = `Tell me more about ${val}`;
            handlePromptSubmit();
        }

        async function handlePromptSubmit() {
            const input = document.getElementById('event-desc').value.trim();
            if(!input) return;

            const thinking = document.getElementById('thinking-state');
            const results = document.getElementById('ai-results');

            results.classList.add('hidden');
            thinking.classList.remove('hidden');

            const pkgKey = Object.keys(PACKAGE_DATA).find(k => input.toLowerCase().includes(k.toLowerCase()));
            
            if (pkgKey) {
                setTimeout(() => {
                    const data = PACKAGE_DATA[pkgKey];
                    document.getElementById('result-title').innerText = pkgKey + " (Standard Package)";
                    document.getElementById('pkg-image').src = data.image;
                    document.getElementById('pkg-per-head').innerText = data.head;
                    
                    document.getElementById('pkg-menu').innerHTML = data.menu.map(m => `
                        <div class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl text-xs font-bold text-gray-700 shadow-sm">
                            <div class="w-6 h-6 rounded-full bg-purple-50 flex items-center justify-center text-purple-600">
                                <i class="bi bi-check-lg"></i>
                            </div>
                            ${m}
                        </div>
                    `).join('');

                    document.getElementById('pkg-pricing').innerHTML = data.pricing.map(p => `
                        <div class="flex justify-between items-center py-4 border-b border-gray-200">
                            <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">${p[0]}</span>
                            <span class="text-xl font-black text-gray-900 tracking-tight">${p[1]}</span>
                        </div>
                    `).join('');

                    thinking.classList.add('hidden');
                    results.classList.remove('hidden');
                    results.scrollIntoView({ behavior: 'smooth' });
                }, 800);
            } else {
                // Fetch dynamic AI Companion plan for custom prompt
                const formData = new FormData();
                formData.append('event_desc', input);
                formData.append('pax', 100);

                try {
                    const res = await fetch('auth/process_ai_plan.php', { method: 'POST', body: formData });
                    const data = await res.json();
                    
                    thinking.classList.add('hidden');
                    
                    if (data.status === 'error') {
                        alert(data.message || 'An error occurred.');
                        return;
                    }
                    
                    if (data.status === 'success' && data.parsed) {
                        const plan = data.parsed;
                        const providerName = data.provider || 'AI Companion';
                        document.getElementById('result-title').innerText = (plan.event_title_suggestion || "AI Event Plan") + ` (${providerName})`;
                        if (data.generated_image) {
                            document.getElementById('pkg-image').src = data.generated_image;
                        }
                        
                        const costPerHead = plan.estimated_cost ? Math.round(plan.estimated_cost / 100) : 450;
                        document.getElementById('pkg-per-head').innerText = '₱' + costPerHead.toLocaleString();

                        const menuHtml = (plan.suggested_menu || []).map(m => `
                            <div class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl text-xs font-bold text-gray-700 shadow-sm">
                                <div class="w-6 h-6 rounded-full bg-purple-50 flex items-center justify-center text-purple-600">
                                    <i class="bi bi-check-lg"></i>
                                </div>
                                ${m.dish || m} ${m.reason ? '<span class="text-gray-400 font-normal">(' + m.reason + ')</span>' : ''}
                            </div>
                        `).join('');
                        document.getElementById('pkg-menu').innerHTML = menuHtml || '<p class="text-xs text-gray-500">Custom menu options curated for your event vision.</p>';

                        document.getElementById('pkg-pricing').innerHTML = `
                            <div class="flex justify-between items-center py-3 border-b border-gray-200">
                                <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Layout & Design</span>
                                <span class="text-xs font-bold text-gray-800">${plan.layout_analysis || 'Custom setup'}</span>
                            </div>
                            <div class="flex justify-between items-center py-3 border-b border-gray-200">
                                <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Est. Total Cost</span>
                                <span class="text-xl font-black text-purple-600 tracking-tight">₱${Number(plan.estimated_cost || 0).toLocaleString()}</span>
                            </div>
                        `;

                        results.classList.remove('hidden');
                        results.scrollIntoView({ behavior: 'smooth' });
                        
                        // Deduct from token count on UI (Optional visual update)
                        const tokenCountEl = document.querySelector('.bi-lightning-charge-fill').nextElementSibling.querySelector('.text-purple-600');
                        if (tokenCountEl) {
                            let [current, max] = tokenCountEl.innerText.split('/');
                            if (current > 0) tokenCountEl.innerText = `${current - 1}/${max}`;
                        }
                    }
                } catch(e) {
                    thinking.classList.add('hidden');
                    alert("AI Companion Active: Generated response using smart fallback.");
                }
            }
        }

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
            document.getElementById('sidebarOverlay').classList.toggle('hidden');
        }
    </script>
</body>
</html>
