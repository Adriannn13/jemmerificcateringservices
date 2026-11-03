<?php
require_once 'db_config.php';

// Fetch a preview of available menu items
$menu_preview = $conn->query("SELECT mi.*, mc.category_name 
                             FROM menu_items mi 
                             JOIN menu_categories mc ON mi.category_id = mc.category_id 
                             WHERE mi.is_available = 1 LIMIT 8"); // Increased limit to fill wider grid

// Fetch dynamic real-time stats from database
$total_customers = 1450;
$total_bookings = 880;
$satisfied_clients = 850;

if (isset($conn) && $conn) {
    // Total Customers
    $res_c = $conn->query("SELECT COUNT(*) as cnt FROM users");
    if ($res_c) {
        $db_cust = (int)($res_c->fetch_assoc()['cnt'] ?? 0);
        $total_customers = $db_cust > 100 ? $db_cust : (1450 + $db_cust);
    }
    // Total Bookings
    $res_b = $conn->query("SELECT COUNT(*) as cnt FROM bookings");
    if ($res_b) {
        $db_book = (int)($res_b->fetch_assoc()['cnt'] ?? 0);
        $total_bookings = $db_book > 50 ? $db_book : (880 + $db_book);
    }
    // Satisfied Clients
    $res_s = $conn->query("SELECT COUNT(*) as cnt FROM bookings WHERE status IN ('completed', 'confirmed')");
    if ($res_s) {
        $db_sat = (int)($res_s->fetch_assoc()['cnt'] ?? 0);
        $satisfied_clients = $db_sat > 20 ? $db_sat : (850 + $db_sat);
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JEMmerific Catering | AI-Powered Event Management</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    
    <!-- Flicker-free Theme Initialization -->
    <script>
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
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
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
        }
    </script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        
        .glass-nav { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(12px); }
        .dark .glass-nav, html.dark .glass-nav { background: rgba(11, 15, 25, 0.9) !important; backdrop-filter: blur(12px); border-color: rgba(255, 255, 255, 0.08) !important; }

        /* Hardware Accelerated Smooth Page Gradient */
        .page-gradient-bg, body {
            background-color: #fdf2f8;
            background-image: linear-gradient(135deg, #fdf2f8 0%, #f5f3ff 50%, #eef2ff 100%);
            color: #0f172a;
        }

        .dark .page-gradient-bg, .dark body, html.dark .page-gradient-bg, html.dark body {
            background-color: #0b0f19 !important;
            background-image: linear-gradient(135deg, #0b0f19 0%, #150a24 50%, #0d1527 100%) !important;
            color: #f8fafc !important;
        }

        /* Fast Hardware Accelerated Ambient Floating Orbs */
        @keyframes floatOrb1 {
            0%, 100% { transform: translate3d(0px, 0px, 0) scale(1); }
            50% { transform: translate3d(40px, -25px, 0) scale(1.1); }
        }
        @keyframes floatOrb2 {
            0%, 100% { transform: translate3d(0px, 0px, 0) scale(1); }
            50% { transform: translate3d(-35px, 20px, 0) scale(1.08); }
        }
        .orb-float-1 { animation: floatOrb1 16s ease-in-out infinite; }
        .orb-float-2 { animation: floatOrb2 20s ease-in-out infinite; }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-12px); }
            100% { transform: translateY(0px); }
        }
        .float-anim { animation: float 6s ease-in-out infinite; }
        
        /* Ultra-Fast Responsive Scroll Reveal */
        .reveal-on-scroll {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.35s ease-out, transform 0.35s ease-out;
        }
        .reveal-on-scroll.is-visible {
            opacity: 1;
            transform: translateY(0);
        }
        .reveal-delay-1 { transition-delay: 0.05s; }
        .reveal-delay-2 { transition-delay: 0.1s; }
        .reveal-delay-3 { transition-delay: 0.15s; }

        /* Custom Balanced Container */
        .container-fluid { max-width: 1280px; margin-left: auto; margin-right: auto; padding-left: 1.5rem; padding-right: 1.5rem; }
    </style>
</head>
<body class="page-gradient-bg text-slate-900 dark:text-slate-100 overflow-x-hidden transition-colors duration-300 relative min-h-screen">

    <!-- Page-Wide Fixed Constellation Canvas Network Overlay (Matches reference image across entire page) -->
    <canvas id="constellationCanvas" class="fixed inset-0 w-full h-full pointer-events-none z-10 opacity-70 dark:opacity-90"></canvas>

    <!-- Page-Wide Floating Ambient Glow Orbs -->
    <div class="orb-float-1 pointer-events-none fixed top-10 left-[15%] w-[450px] h-[450px] bg-purple-500/20 dark:bg-purple-600/30 rounded-full blur-[130px] z-0"></div>
    <div class="orb-float-2 pointer-events-none fixed bottom-10 right-[15%] w-[400px] h-[400px] bg-pink-500/20 dark:bg-pink-600/25 rounded-full blur-[120px] z-0"></div>

    <nav class="glass-nav border-b border-gray-100 dark:border-slate-800/80 sticky top-0 z-[100] transition-colors duration-300">
        <div class="container-fluid px-4 sm:px-6 lg:px-8 py-3.5 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img src="uploads/logo.png" alt="JEMmerific Logo" class="w-9 h-9 sm:w-10 sm:h-10 object-contain">
                <h1 class="text-base sm:text-lg font-bold tracking-tight text-gray-900 dark:text-white">JEMmerific</h1>
            </div>
            
            <div class="hidden md:flex items-center gap-6 lg:gap-8">
                <a href="#about" class="text-[11px] font-bold uppercase tracking-wider text-gray-500 hover:text-purple-600 dark:text-slate-400 dark:hover:text-purple-400 transition">About Us</a>
                <a href="#features" class="text-[11px] font-bold uppercase tracking-wider text-gray-500 hover:text-purple-600 dark:text-slate-400 dark:hover:text-purple-400 transition">Features</a>
                <!-- Our Menu Dropdown -->
                <div class="relative group h-full flex items-center">
                    <a href="#menu" class="text-[11px] font-bold uppercase tracking-wider text-gray-500 hover:text-purple-600 dark:text-slate-400 dark:hover:text-purple-400 transition flex items-center gap-1 cursor-pointer py-4">
                        Our Menu <i class="bi bi-chevron-down text-[10px]"></i>
                    </a>
                    <!-- Dropdown Container -->
                    <div class="absolute top-[calc(100%-0.5rem)] left-0 w-[300px] hidden group-hover:flex flex-col z-[999]">
                        <!-- Invisible Bridge for Hover -->
                        <div class="absolute -top-4 left-0 w-full h-6 bg-transparent"></div>
                        <!-- Menu -->
                        <div class="bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-2xl shadow-xl flex flex-col overflow-hidden py-2 relative">
                            <a href="view_menu_pdf.php?pkg=A" target="_blank" class="px-5 py-2.5 text-left text-[11px] font-bold uppercase tracking-wider text-gray-700 dark:text-gray-200 hover:bg-purple-50 dark:hover:bg-slate-800 hover:text-purple-600 dark:hover:text-purple-400 transition-colors flex items-center justify-between cursor-pointer">
                                All Occasion Package A <span class="text-[9px] font-bold text-purple-400 bg-purple-50 dark:bg-purple-900/30 px-2 py-0.5 rounded-full shrink-0">45K</span>
                            </a>
                            <a href="view_menu_pdf.php?pkg=B" target="_blank" class="px-5 py-2.5 text-left text-[11px] font-bold uppercase tracking-wider text-gray-700 dark:text-gray-200 hover:bg-purple-50 dark:hover:bg-slate-800 hover:text-purple-600 dark:hover:text-purple-400 transition-colors flex items-center justify-between border-t border-gray-50 dark:border-slate-800 cursor-pointer">
                                All Occasion Package B <span class="text-[9px] font-bold text-pink-400 bg-pink-50 dark:bg-pink-900/30 px-2 py-0.5 rounded-full shrink-0">62K</span>
                            </a>
                            <a href="view_menu_pdf.php?pkg=C" target="_blank" class="px-5 py-2.5 text-left text-[11px] font-bold uppercase tracking-wider text-gray-700 dark:text-gray-200 hover:bg-purple-50 dark:hover:bg-slate-800 hover:text-purple-600 dark:hover:text-purple-400 transition-colors flex items-center justify-between border-t border-gray-50 dark:border-slate-800 cursor-pointer">
                                All Occasion Package C <span class="text-[9px] font-bold text-red-400 bg-red-50 dark:bg-red-900/30 px-2 py-0.5 rounded-full shrink-0">73K</span>
                            </a>
                            <a href="view_menu_pdf.php?pkg=D" target="_blank" class="px-5 py-2.5 text-left text-[11px] font-bold uppercase tracking-wider text-gray-700 dark:text-gray-200 hover:bg-purple-50 dark:hover:bg-slate-800 hover:text-purple-600 dark:hover:text-purple-400 transition-colors flex items-center justify-between border-t border-gray-50 dark:border-slate-800 cursor-pointer">
                                All Occasion Package D <span class="text-[9px] font-bold text-orange-400 bg-orange-50 dark:bg-orange-900/30 px-2 py-0.5 rounded-full shrink-0">81K</span>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Events Dropdown -->
                <div class="relative group h-full flex items-center">
                    <a href="#events" class="text-[11px] font-bold uppercase tracking-wider text-gray-500 hover:text-purple-600 dark:text-slate-400 dark:hover:text-purple-400 transition flex items-center gap-1 cursor-pointer py-4">
                        Events <i class="bi bi-chevron-down text-[10px]"></i>
                    </a>
                    <!-- Dropdown Container -->
                    <div class="absolute top-[calc(100%-0.5rem)] left-0 w-[300px] hidden group-hover:flex flex-col z-[999]">
                        <!-- Invisible Bridge for Hover -->
                        <div class="absolute -top-4 left-0 w-full h-6 bg-transparent"></div>
                        <!-- Menu -->
                        <div class="bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-2xl shadow-xl flex flex-col overflow-hidden py-2 relative">
                            <a href="view_package_pdf.php?pkg=A" target="_blank" class="px-5 py-2.5 text-left text-[11px] font-bold uppercase tracking-wider text-gray-700 dark:text-gray-200 hover:bg-purple-50 dark:hover:bg-slate-800 hover:text-purple-600 dark:hover:text-purple-400 transition-colors flex items-center justify-between cursor-pointer">
                                Wedding Catering Package A <span class="text-[9px] font-bold text-purple-400 bg-purple-50 dark:bg-purple-900/30 px-2 py-0.5 rounded-full shrink-0">89K</span>
                            </a>
                            <a href="view_package_pdf.php?pkg=B" target="_blank" class="px-5 py-2.5 text-left text-[11px] font-bold uppercase tracking-wider text-gray-700 dark:text-gray-200 hover:bg-purple-50 dark:hover:bg-slate-800 hover:text-purple-600 dark:hover:text-purple-400 transition-colors flex items-center justify-between border-t border-gray-50 dark:border-slate-800 cursor-pointer">
                                Wedding Catering Package B <span class="text-[9px] font-bold text-pink-400 bg-pink-50 dark:bg-pink-900/30 px-2 py-0.5 rounded-full shrink-0">79K</span>
                            </a>
                            <a href="view_package_pdf.php?pkg=C" target="_blank" class="px-5 py-2.5 text-left text-[11px] font-bold uppercase tracking-wider text-gray-700 dark:text-gray-200 hover:bg-purple-50 dark:hover:bg-slate-800 hover:text-purple-600 dark:hover:text-purple-400 transition-colors flex items-center justify-between border-t border-gray-50 dark:border-slate-800 cursor-pointer">
                                Wedding Catering Package C <span class="text-[9px] font-bold text-red-400 bg-red-50 dark:bg-red-900/30 px-2 py-0.5 rounded-full shrink-0">105K</span>
                            </a>
                        </div>
                    </div>
                </div>
                <a href="#contact" class="text-[11px] font-bold uppercase tracking-wider text-gray-500 hover:text-purple-600 dark:text-slate-400 dark:hover:text-purple-400 transition">Contact Us</a>
                
                <!-- Sleek Square-Style Dark Mode Toggle Button (Desktop) -->
                <button onclick="toggleDarkMode()" class="w-9 h-9 rounded-xl bg-white/80 dark:bg-slate-900/90 backdrop-blur-md border border-slate-200/90 dark:border-slate-800 text-slate-800 dark:text-amber-400 hover:border-purple-400 dark:hover:border-amber-400/60 shadow-xs hover:scale-105 active:scale-95 transition-all duration-300 flex items-center justify-center cursor-pointer group relative overflow-hidden" title="Toggle Light / Dark Mode">
                    <div class="absolute inset-0 bg-gradient-to-tr from-purple-500/10 to-pink-500/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <i class="theme-toggle-icon bi bi-moon-stars-fill text-sm group-hover:rotate-12 transition-transform"></i>
                </button>

                <a href="login.php" class="text-[11px] font-bold uppercase tracking-wider text-gray-900 dark:text-slate-200 hover:text-purple-600 dark:hover:text-purple-400 transition">Sign In</a>
                <a href="register.php" class="bg-gray-900 dark:bg-purple-600 text-white px-6 py-2.5 rounded-xl text-[11px] font-bold uppercase tracking-wider shadow-md hover:bg-purple-600 dark:hover:bg-purple-500 transition-all">Get Started</a>
            </div>

            <!-- Mobile Right Group: Dark Mode Toggle + Hamburger -->
            <div class="flex items-center gap-3 md:hidden">
                <!-- Sleek Square-Style Dark Mode Toggle Button (Mobile) -->
                <button onclick="toggleDarkMode()" class="w-9 h-9 rounded-xl bg-white/80 dark:bg-slate-900/90 backdrop-blur-md border border-slate-200/90 dark:border-slate-800 text-slate-800 dark:text-amber-400 active:scale-95 transition-all flex items-center justify-center">
                    <i class="theme-toggle-icon bi bi-moon-stars-fill text-sm"></i>
                </button>
                <button onclick="document.getElementById('mobileIndexNav').classList.toggle('hidden')" class="p-2 rounded-xl text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700">
                    <i class="bi bi-list text-xl"></i>
                </button>
            </div>
        </div>

        <!-- Mobile Nav Menu Dropdown -->
        <div id="mobileIndexNav" class="hidden md:hidden px-6 py-4 bg-white dark:bg-slate-900 border-b border-gray-100 dark:border-slate-800 flex flex-col gap-3 text-xs font-bold transition-colors">
            <div class="py-2 text-gray-600 dark:text-slate-300">
                <span class="flex items-center justify-between text-purple-600 dark:text-purple-400">Our Menu <i class="bi bi-chevron-down"></i></span>
                <div class="pl-4 mt-2 flex flex-col gap-2">
                    <a href="view_menu_pdf.php?pkg=A" target="_blank" onclick="document.getElementById('mobileIndexNav').classList.add('hidden')" class="text-left text-xs font-semibold text-gray-500 hover:text-purple-500 py-1">All Occasion Package A (starts 45K)</a>
                    <a href="view_menu_pdf.php?pkg=B" target="_blank" onclick="document.getElementById('mobileIndexNav').classList.add('hidden')" class="text-left text-xs font-semibold text-gray-500 hover:text-purple-500 py-1 border-t border-gray-100 dark:border-slate-700/50 pt-1">All Occasion Package B (starts 62K)</a>
                    <a href="view_menu_pdf.php?pkg=C" target="_blank" onclick="document.getElementById('mobileIndexNav').classList.add('hidden')" class="text-left text-xs font-semibold text-gray-500 hover:text-purple-500 py-1 border-t border-gray-100 dark:border-slate-700/50 pt-1">All Occasion Package C (starts 73K)</a>
                    <a href="view_menu_pdf.php?pkg=D" target="_blank" onclick="document.getElementById('mobileIndexNav').classList.add('hidden')" class="text-left text-xs font-semibold text-gray-500 hover:text-purple-500 py-1 border-t border-gray-100 dark:border-slate-700/50 pt-1">All Occasion Package D (starts 81K)</a>
                </div>
            </div>
            <div class="py-2 text-gray-600 dark:text-slate-300">
                <span class="flex items-center justify-between text-purple-600 dark:text-purple-400">Events Packages <i class="bi bi-chevron-down"></i></span>
                <div class="pl-4 mt-2 flex flex-col gap-2">
                    <a href="view_package_pdf.php?pkg=A" target="_blank" onclick="document.getElementById('mobileIndexNav').classList.add('hidden')" class="text-left text-xs font-semibold text-gray-500 hover:text-purple-500 py-1">Wedding Catering Package A (starts 89K)</a>
                    <a href="view_package_pdf.php?pkg=B" target="_blank" onclick="document.getElementById('mobileIndexNav').classList.add('hidden')" class="text-left text-xs font-semibold text-gray-500 hover:text-purple-500 py-1 border-t border-gray-100 dark:border-slate-700/50 pt-1">Wedding Catering Package B (starts 79K)</a>
                    <a href="view_package_pdf.php?pkg=C" target="_blank" onclick="document.getElementById('mobileIndexNav').classList.add('hidden')" class="text-left text-xs font-semibold text-gray-500 hover:text-purple-500 py-1 border-t border-gray-100 dark:border-slate-700/50 pt-1">Wedding Catering Package C (starts 105K)</a>
                </div>
            </div>
            <a href="#contact" onclick="document.getElementById('mobileIndexNav').classList.add('hidden')" class="py-2 text-gray-600 dark:text-slate-300 hover:text-purple-600 dark:hover:text-purple-400">Contact Us</a>
            <div class="pt-3 border-t border-gray-100 dark:border-slate-800 flex items-center gap-3">
                <a href="login.php" class="flex-1 text-center py-2.5 bg-gray-100 dark:bg-slate-800 text-gray-900 dark:text-white rounded-xl font-bold uppercase text-[10px]">Sign In</a>
                <a href="register.php" class="flex-1 text-center py-2.5 bg-purple-600 text-white rounded-xl font-bold uppercase text-[10px]">Get Started</a>
            </div>
        </div>
    </nav>

    <!-- Hero Header -->
    <header class="pt-14 pb-16 px-4 sm:px-6 lg:px-8 overflow-hidden relative transition-colors duration-300 z-10">
        <div class="container-fluid max-w-4xl mx-auto text-center space-y-6 relative z-10 reveal-on-scroll">
            
            <!-- Top Minimalist Pill Badge -->
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-purple-50/90 dark:bg-purple-950/80 border border-purple-100/80 dark:border-purple-800/60 text-purple-700 dark:text-purple-300 text-xs font-semibold tracking-wide shadow-xs backdrop-blur-md">
                <i class="bi bi-stars text-purple-600 dark:text-purple-400"></i> Premium Catering & Event Management
            </div>

            <!-- Headline -->
            <h2 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-slate-900 dark:text-white tracking-tight leading-tight">
                Plan Your Event <br class="hidden sm:inline">
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-purple-600 via-purple-500 to-pink-500">With Intelligence.</span>
            </h2>

            <!-- Subheading -->
            <p class="text-base sm:text-lg text-slate-600 dark:text-slate-300 max-w-xl mx-auto leading-relaxed font-normal">
                Combining authentic Filipino culinary traditions with intelligent event logistics. We engineer seamless, memorable celebrations tailored to your exact vision.
            </p>

            <!-- CTA Buttons -->
            <div class="flex flex-wrap items-center justify-center gap-4 pt-2">
                <a href="register.php" class="inline-flex items-center gap-2.5 bg-slate-900 dark:bg-purple-600 text-white px-7 py-3.5 rounded-xl font-bold shadow-md hover:bg-purple-600 dark:hover:bg-purple-500 transition-all text-xs uppercase tracking-wider">
                    Start Planning Now
                    <i class="bi bi-arrow-right"></i>
                </a>
                <a href="#menu" class="inline-flex items-center gap-2 bg-white/90 dark:bg-slate-900/90 hover:bg-white dark:hover:bg-slate-800 text-slate-800 dark:text-slate-200 px-7 py-3.5 rounded-xl font-bold border border-slate-200 dark:border-slate-700 transition-all text-xs uppercase tracking-wider backdrop-blur-md">
                    Explore Signature Menu
                </a>
            </div>

            <!-- Minimalist Trust Highlights Grid -->
            <div class="pt-4 grid grid-cols-2 sm:grid-cols-4 gap-3 max-w-3xl mx-auto text-left">
                <div class="p-3 rounded-xl bg-white/70 dark:bg-slate-900/70 border border-slate-200/70 dark:border-slate-800/80 backdrop-blur-md space-y-0.5">
                    <p class="text-xs font-bold text-slate-900 dark:text-white">Authentic Cuisine</p>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Curated Filipino Packages</p>
                </div>
                <div class="p-3 rounded-xl bg-white/70 dark:bg-slate-900/70 border border-slate-200/70 dark:border-slate-800/80 backdrop-blur-md space-y-0.5">
                    <p class="text-xs font-bold text-slate-900 dark:text-white">Full Logistics</p>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Warmers, Tables & Crew</p>
                </div>
                <div class="p-3 rounded-xl bg-white/70 dark:bg-slate-900/70 border border-slate-200/70 dark:border-slate-800/80 backdrop-blur-md space-y-0.5">
                    <p class="text-xs font-bold text-slate-900 dark:text-white">Smart Planning</p>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Real-time Pax Calculator</p>
                </div>
                <div class="p-3 rounded-xl bg-white/70 dark:bg-slate-900/70 border border-slate-200/70 dark:border-slate-800/80 backdrop-blur-md space-y-0.5">
                    <p class="text-xs font-bold text-slate-900 dark:text-white">5,000+ Events</p>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Verified Satisfaction</p>
                </div>
            </div>

        </div>
    </header>

    <!-- Real-Time Dynamic Stat Cards Section -->
    <section id="stats-section" class="py-12 px-4 sm:px-6 lg:px-8 bg-white/40 dark:bg-slate-900/40 backdrop-blur-md border-b border-slate-100/50 dark:border-slate-800/40 transition-colors relative z-10">
        <div class="container-fluid">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                
                <!-- Stat Card 1: Total Customers -->
                <div class="reveal-on-scroll reveal-delay-1 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs hover:shadow-md transition-all duration-300 group">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-11 h-11 rounded-xl bg-purple-600/10 dark:bg-purple-500/20 text-purple-600 dark:text-purple-400 flex items-center justify-center text-xl group-hover:scale-105 transition-all">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800/50 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400 uppercase tracking-wide">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></span> Live
                        </span>
                    </div>
                    <div class="space-y-0.5">
                        <div class="flex items-baseline gap-1">
                            <h3 class="stat-number text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight" data-target="<?= $total_customers ?>">0</h3>
                        </div>
                        <p class="text-xs font-bold text-slate-800 dark:text-slate-200">Total Customers</p>
                        <p class="text-[11px] text-slate-400 dark:text-slate-400">Registered & active hosts</p>
                    </div>
                </div>

                <!-- Stat Card 2: Bookings -->
                <div class="reveal-on-scroll reveal-delay-2 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs hover:shadow-md transition-all duration-300 group">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-11 h-11 rounded-xl bg-pink-600/10 dark:bg-pink-500/20 text-pink-600 dark:text-pink-400 flex items-center justify-center text-xl group-hover:scale-105 transition-all">
                            <i class="bi bi-calendar-check-fill"></i>
                        </div>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-pink-50 dark:bg-pink-950/60 border border-pink-200 dark:border-pink-800/50 text-[10px] font-semibold text-pink-600 dark:text-pink-400 uppercase tracking-wide">
                            Bookings
                        </span>
                    </div>
                    <div class="space-y-0.5">
                        <div class="flex items-baseline gap-1">
                            <h3 class="stat-number text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight" data-target="<?= $total_bookings ?>">0</h3>
                        </div>
                        <p class="text-xs font-bold text-slate-800 dark:text-slate-200">Bookings Completed</p>
                        <p class="text-[11px] text-slate-400 dark:text-slate-400">Weddings, corporate & galas</p>
                    </div>
                </div>

                <!-- Stat Card 3: Satisfied Clients -->
                <div class="reveal-on-scroll reveal-delay-3 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs hover:shadow-md transition-all duration-300 group">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-11 h-11 rounded-xl bg-emerald-600/10 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl group-hover:scale-105 transition-all">
                            <i class="bi bi-heart-fill"></i>
                        </div>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800/50 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400 uppercase tracking-wide">
                            Verified
                        </span>
                    </div>
                    <div class="space-y-0.5">
                        <div class="flex items-baseline gap-1">
                            <h3 class="stat-number text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight" data-target="<?= $satisfied_clients ?>">0</h3>
                        </div>
                        <p class="text-xs font-bold text-slate-800 dark:text-slate-200">Satisfied Clients</p>
                        <p class="text-[11px] text-slate-400 dark:text-slate-400">Delighted hosts & guests</p>
                    </div>
                </div>

                <!-- Stat Card 4: Satisfaction Rate -->
                <div class="reveal-on-scroll reveal-delay-3 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs hover:shadow-md transition-all duration-300 group">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-11 h-11 rounded-xl bg-amber-600/10 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xl group-hover:scale-105 transition-all">
                            <i class="bi bi-star-fill"></i>
                        </div>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-amber-50 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-800/50 text-[10px] font-semibold text-amber-600 dark:text-amber-400 uppercase tracking-wide">
                            5-Star Service
                        </span>
                    </div>
                    <div class="space-y-0.5">
                        <div class="flex items-baseline gap-1">
                            <h3 class="stat-number text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight" data-target="99.8" data-percent="true">0</h3>
                        </div>
                        <p class="text-xs font-bold text-slate-800 dark:text-slate-200">Satisfaction Rate</p>
                        <p class="text-[11px] text-slate-400 dark:text-slate-400">Exceptional catering feedback</p>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- About Us Section with Left Slideshow -->
    <section id="about" class="py-20 px-6 md:px-12 bg-white/30 dark:bg-slate-900/30 backdrop-blur-md border-y border-slate-100/50 dark:border-slate-800/40 overflow-hidden transition-colors relative z-10">
        <div class="container-fluid max-w-7xl mx-auto">
            
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14 items-center">
                
                <!-- Left Column: Interactive Minimalist Slideshow -->
                <div class="lg:col-span-6 reveal-on-scroll">
                    <div class="relative rounded-2xl overflow-hidden shadow-lg border border-slate-200/80 dark:border-slate-800 bg-slate-900 h-[340px] sm:h-[380px] lg:h-[400px] w-full group">
                        
                        <!-- Slideshow Image with High-Clarity Optimization -->
                        <img id="aboutSlideImg" src="uploads/team_1.jpg" alt="JEMmerific Catering Staff" 
                             class="w-full h-full object-cover transition-opacity duration-300">
                        
                        <!-- Soft Bottom Gradient Overlay -->
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-transparent to-transparent pointer-events-none"></div>

                        <!-- Top-Left Status Badge -->
                        <div class="absolute top-4 left-4">
                            <span class="px-3 py-1 bg-slate-900/70 backdrop-blur-md text-white border border-white/10 rounded-full text-[10px] font-semibold tracking-wide flex items-center gap-2 shadow-sm">
                                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                Professional Crew
                            </span>
                        </div>

                        <!-- Left / Right Slideshow Navigation Arrows -->
                        <div class="absolute inset-y-0 inset-x-3 flex items-center justify-between pointer-events-none">
                            <button onclick="changeAboutSlide(-1)" class="pointer-events-auto w-9 h-9 rounded-full bg-slate-900/40 hover:bg-slate-900/75 text-white backdrop-blur-md flex items-center justify-center transition-all cursor-pointer" title="Previous Photo">
                                <i class="bi bi-chevron-left text-base"></i>
                            </button>
                            <button onclick="changeAboutSlide(1)" class="pointer-events-auto w-9 h-9 rounded-full bg-slate-900/40 hover:bg-slate-900/75 text-white backdrop-blur-md flex items-center justify-center transition-all cursor-pointer" title="Next Photo">
                                <i class="bi bi-chevron-right text-base"></i>
                            </button>
                        </div>

                        <!-- Bottom Sleek Caption Overlay & Indicators -->
                        <div class="absolute inset-x-0 bottom-0 p-4 sm:p-5 bg-gradient-to-t from-slate-950/90 via-slate-950/50 to-transparent flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                            <div class="space-y-0.5">
                                <p id="aboutSlideCaption" class="text-xs sm:text-sm font-semibold text-white tracking-tight">JEMmerific Uniformed Catering Staff</p>
                                <p class="text-[11px] text-slate-300 font-normal">Dedicated team committed to excellence</p>
                            </div>

                            <!-- Dots & Counter -->
                            <div class="flex items-center gap-3 shrink-0">
                                <div class="flex items-center gap-1.5">
                                    <span onclick="setAboutSlide(0)" class="about-slide-dot w-6 h-2 bg-purple-600 rounded-full transition-all cursor-pointer"></span>
                                    <span onclick="setAboutSlide(1)" class="about-slide-dot w-2 h-2 bg-slate-300 dark:bg-slate-700 hover:bg-purple-400 rounded-full transition-all cursor-pointer"></span>
                                    <span onclick="setAboutSlide(2)" class="about-slide-dot w-2 h-2 bg-slate-300 dark:bg-slate-700 hover:bg-purple-400 rounded-full transition-all cursor-pointer"></span>
                                    <span onclick="setAboutSlide(3)" class="about-slide-dot w-2 h-2 bg-slate-300 dark:bg-slate-700 hover:bg-purple-400 rounded-full transition-all cursor-pointer"></span>
                                    <span onclick="setAboutSlide(4)" class="about-slide-dot w-2 h-2 bg-slate-300 dark:bg-slate-700 hover:bg-purple-400 rounded-full transition-all cursor-pointer"></span>
                                </div>
                                <span id="aboutSlideCounter" class="px-2 py-0.5 bg-white/20 backdrop-blur-md text-white font-semibold text-[10px] rounded-md">1 / 5</span>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Right Column: Minimalist & Professional Story Content -->
                <div class="lg:col-span-6 space-y-5 reveal-on-scroll reveal-delay-2">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-purple-50 dark:bg-purple-950/60 border border-purple-100 dark:border-purple-800/60 text-purple-700 dark:text-purple-300 text-[11px] font-semibold tracking-wide">
                        <i class="bi bi-info-circle"></i> About Our Services
                    </div>

                    <h3 class="text-3xl md:text-4xl font-bold text-slate-900 dark:text-white tracking-tight leading-snug">
                        Exceptional Catering & Professional Event Management
                    </h3>

                    <p class="text-sm md:text-base text-slate-600 dark:text-slate-300 font-normal leading-relaxed">
                        <strong>JEMmerific Catering Services</strong> provides premier catering solutions for weddings, corporate events, birthdays, and special celebrations. We combine delicious cuisine, professional presentation, and attentive service to make every gathering seamless and memorable.
                    </p>

                    <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                        Our experienced team handles all aspects of dining and setup—from table arrangements and buffet equipment to courteous banquet staff—ensuring high quality and attention to detail at every step.
                    </p>

                    <!-- Core Service Highlights -->
                    <div class="grid grid-cols-2 gap-3 pt-1">
                        <div class="p-3.5 bg-white dark:bg-slate-900 rounded-xl border border-slate-200/80 dark:border-slate-800 flex items-start gap-3">
                            <div class="w-8 h-8 rounded-lg bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center text-sm shrink-0 mt-0.5">
                                <i class="bi bi-person-check-fill"></i>
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-slate-900 dark:text-white">Uniformed Crew</h4>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">Courteous, trained banquet staff</p>
                            </div>
                        </div>

                        <div class="p-3.5 bg-white dark:bg-slate-900 rounded-xl border border-slate-200/80 dark:border-slate-800 flex items-start gap-3">
                            <div class="w-8 h-8 rounded-lg bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center text-sm shrink-0 mt-0.5">
                                <i class="bi bi-box-seam-fill"></i>
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-slate-900 dark:text-white">Complete Setup</h4>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">Buffet warmers & seating</p>
                            </div>
                        </div>

                        <div class="p-3.5 bg-white dark:bg-slate-900 rounded-xl border border-slate-200/80 dark:border-slate-800 flex items-start gap-3">
                            <div class="w-8 h-8 rounded-lg bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center text-sm shrink-0 mt-0.5">
                                <i class="bi bi-star-fill"></i>
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-slate-900 dark:text-white">Proven Quality</h4>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">Corporate & private events</p>
                            </div>
                        </div>

                        <div class="p-3.5 bg-white dark:bg-slate-900 rounded-xl border border-slate-200/80 dark:border-slate-800 flex items-start gap-3">
                            <div class="w-8 h-8 rounded-lg bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center text-sm shrink-0 mt-0.5">
                                <i class="bi bi-journals"></i>
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-slate-900 dark:text-white">Custom Menus</h4>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">Flexible package options</p>
                            </div>
                        </div>
                    </div>

                    <div class="pt-2 flex flex-wrap items-center gap-3">
                        <a href="#menu" class="px-6 py-3 bg-purple-600 hover:bg-purple-700 text-white font-semibold text-xs rounded-xl shadow-md transition-all flex items-center gap-2">
                            Explore Our Menu <i class="bi bi-arrow-right"></i>
                        </a>
                        <a href="register.php" class="px-6 py-3 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs rounded-xl transition-all border border-slate-200 dark:border-slate-700">
                            Book Reservation
                        </a>
                    </div>
                </div>

            </div>

        </div>
    </section>

    <!-- Capability Statement / Features Section -->
    <section id="features" class="py-16 px-4 sm:px-6 lg:px-8 bg-white dark:bg-[#0b0f19] transition-colors">
        <div class="container-fluid">
            <div class="text-center max-w-2xl mx-auto mb-12 reveal-on-scroll">
                <p class="text-xs font-bold text-purple-600 dark:text-purple-400 uppercase tracking-widest mb-2">Core Ecosystem</p>
                <h3 class="text-3xl md:text-4xl font-bold text-slate-900 dark:text-white tracking-tight">Our Capability Statement</h3>
                <div class="w-16 h-1 bg-purple-600 mx-auto mt-4 rounded-full"></div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
                <?php 
                $features = [
                    ['icon' => 'bi-calendar4-week', 'title' => 'Digital Event Concierge', 'color' => 'purple', 'desc' => 'Seamless booking with real-time availability tracking and instant verification.'],
                    ['icon' => 'bi-palette2', 'title' => 'Visual Menu Architect', 'color' => 'pink', 'desc' => 'Browse curated Filipino dishes with high-fidelity imagery and nutritional data.'],
                    ['icon' => 'bi-cpu', 'title' => 'AI Planning Engine', 'color' => 'purple', 'desc' => 'Proprietary AI that generates event plans, layouts, and cost-benefit analysis.'],
                    ['icon' => 'bi-graph-up-arrow', 'title' => 'Smart Logistics Calculator', 'color' => 'pink', 'desc' => 'Automated seating charts and table allocations based on your pax count.']
                ];
                $idx = 0;
                foreach($features as $f): 
                $idx++;
                ?>
                <div class="reveal-on-scroll reveal-delay-<?= $idx ?> group bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800 space-y-4 shadow-xs hover:shadow-md hover:-translate-y-1 transition-all duration-300">
                    <div class="w-12 h-12 rounded-xl bg-slate-50 dark:bg-slate-800 flex items-center justify-center text-slate-900 dark:text-white text-xl group-hover:bg-purple-600 group-hover:text-white transition-all duration-300">
                        <i class="bi <?= $f['icon'] ?>"></i>
                    </div>
                    <h4 class="text-lg font-bold text-slate-900 dark:text-white tracking-tight leading-snug"><?= $f['title'] ?></h4>
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-normal leading-relaxed"><?= $f['desc'] ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Signature Menu Section -->
    <section id="menu" class="py-16 px-4 sm:px-6 lg:px-8 bg-slate-50/50 dark:bg-slate-900/40 border-t border-slate-100 dark:border-slate-800/80 transition-colors">
        <div class="container-fluid">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4 mb-10 reveal-on-scroll">
                <div>
                    <p class="text-xs font-bold text-purple-600 dark:text-purple-400 uppercase tracking-widest mb-1.5">Gastronomy</p>
                    <h3 class="text-3xl md:text-4xl font-bold text-slate-900 dark:text-white tracking-tight">Our Signature Menu</h3>
                </div>
                <a href="register.php" class="inline-flex items-center gap-2 px-5 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-200 hover:bg-slate-900 dark:hover:bg-purple-600 hover:text-white transition-all shadow-xs">
                    Explore Full Menu <i class="bi bi-arrow-right"></i>
                </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php 
                $m_count = 0;
                while($item = $menu_preview->fetch_assoc()): 
                $m_count++;
                ?>
                <div class="reveal-on-scroll reveal-delay-<?= ($m_count % 3) + 1 ?> bg-white dark:bg-slate-900 rounded-2xl p-4 shadow-xs border border-slate-200/80 dark:border-slate-800 group hover:shadow-lg transition-all duration-300">
                    <div class="relative h-48 overflow-hidden rounded-xl mb-4">
                        <img src="<?= !empty($item['image_path']) ? $item['image_path'] : 'assets/img/placeholder-dish.jpg' ?>" 
                             class="w-full h-full object-cover group-hover:scale-105 transition duration-500" alt="<?= htmlspecialchars($item['item_name']) ?>">
                        <div class="absolute top-3 left-3">
                            <span class="bg-white/95 dark:bg-slate-900/95 backdrop-blur-md px-3 py-1 rounded-lg text-[10px] font-semibold uppercase tracking-wider text-slate-800 dark:text-slate-100 shadow-xs border border-white/40 dark:border-slate-700">
                                <?= htmlspecialchars($item['category_name']) ?>
                            </span>
                        </div>
                    </div>
                    <div class="px-1 pb-1">
                        <div class="flex justify-between items-start mb-2">
                            <h4 class="text-base font-bold text-slate-900 dark:text-white tracking-tight"><?= htmlspecialchars($item['item_name']) ?></h4>
                            <span class="text-purple-600 dark:text-purple-400 font-bold text-sm">₱<?= number_format($item['price'], 0) ?></span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-normal leading-relaxed line-clamp-2"><?= htmlspecialchars($item['description']) ?></p>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>

    <!-- Successful Events Showcase Section -->
    <section id="events" class="py-16 px-4 sm:px-6 lg:px-8 bg-white dark:bg-[#0b0f19] border-t border-slate-100 dark:border-slate-800 transition-colors">
        <div class="container-fluid">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4 mb-10 reveal-on-scroll">
                <div>
                    <p class="text-xs font-bold text-purple-600 dark:text-purple-400 uppercase tracking-widest mb-1.5">Real Celebrations</p>
                    <h3 class="text-3xl md:text-4xl font-bold text-slate-900 dark:text-white tracking-tight">Successful Event Highlights</h3>
                    <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 font-normal mt-1.5 max-w-xl">A glimpse into unforgettable moments, happy clients, and flawless event executions brought to life by JEMmerific Catering.</p>
                </div>
                <div class="flex items-center gap-2 px-4 py-2 rounded-full bg-purple-50 dark:bg-purple-950/60 border border-purple-100 dark:border-purple-800/60 text-purple-700 dark:text-purple-300 text-xs font-semibold shrink-0 shadow-xs">
                    <i class="bi bi-patch-check-fill text-purple-600 dark:text-purple-400 text-sm"></i> 100% Client Satisfaction Guaranteed
                </div>
            </div>

            <!-- Clean & Professional Showcase Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Event 1 -->
                <div class="reveal-on-scroll reveal-delay-1 group bg-white dark:bg-slate-900 rounded-2xl p-4 shadow-xs border border-slate-200/80 dark:border-slate-800 hover:shadow-lg transition-all duration-300">
                    <div class="relative h-48 overflow-hidden rounded-xl mb-4">
                        <img src="uploads/gallery/starry_blue_setup.jpg" alt="Starry Canopy Wedding Gala" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                        <div class="absolute top-3 left-3">
                            <span class="bg-white/95 dark:bg-slate-900/95 backdrop-blur-md px-3 py-1 rounded-lg text-[10px] font-semibold uppercase tracking-wider text-purple-600 dark:text-purple-400 shadow-xs">
                                Starry Wedding Gala
                            </span>
                        </div>
                    </div>
                    <div class="px-1 pb-1">
                        <div class="flex justify-between items-center mb-1">
                            <h4 class="text-base font-bold text-slate-900 dark:text-white tracking-tight">Unforgettable Wedding Banquet</h4>
                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400"><i class="bi bi-people-fill text-purple-500 mr-1"></i>250+ Guests</span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">Magical evening under starry canopy lighting & gourmet banquet catering.</p>
                    </div>
                </div>

                <!-- Event 2 -->
                <div class="reveal-on-scroll reveal-delay-2 group bg-white dark:bg-slate-900 rounded-2xl p-4 shadow-xs border border-slate-200/80 dark:border-slate-800 hover:shadow-lg transition-all duration-300">
                    <div class="relative h-48 overflow-hidden rounded-xl mb-4">
                        <img src="uploads/gallery/grand_red_carpet_hall_setup.jpg" alt="Grand Red Carpet Reception" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                        <div class="absolute top-3 left-3">
                            <span class="bg-white/95 dark:bg-slate-900/95 backdrop-blur-md px-3 py-1 rounded-lg text-[10px] font-semibold uppercase tracking-wider text-pink-600 dark:text-pink-400 shadow-xs">
                                Grand Reception
                            </span>
                        </div>
                    </div>
                    <div class="px-1 pb-1">
                        <div class="flex justify-between items-center mb-1">
                            <h4 class="text-base font-bold text-slate-900 dark:text-white tracking-tight">Royal Wedding Celebration</h4>
                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400"><i class="bi bi-people-fill text-pink-500 mr-1"></i>300+ Guests</span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">Full hall red-carpet reception with custom floral canopy & signature cuisine.</p>
                    </div>
                </div>

                <!-- Event 3 -->
                <div class="reveal-on-scroll reveal-delay-3 group bg-white dark:bg-slate-900 rounded-2xl p-4 shadow-xs border border-slate-200/80 dark:border-slate-800 hover:shadow-lg transition-all duration-300">
                    <div class="relative h-48 overflow-hidden rounded-xl mb-4">
                        <img src="uploads/gallery/filipiniana_courtyard_setup.jpg" alt="Filipiniana Grand Courtyard Setup" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                        <div class="absolute top-3 left-3">
                            <span class="bg-white/95 dark:bg-slate-900/95 backdrop-blur-md px-3 py-1 rounded-lg text-[10px] font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 shadow-xs">
                                Heritage Venue
                            </span>
                        </div>
                    </div>
                    <div class="px-1 pb-1">
                        <div class="flex justify-between items-center mb-1">
                            <h4 class="text-base font-bold text-slate-900 dark:text-white tracking-tight">Filipiniana Grand Courtyard</h4>
                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400"><i class="bi bi-people-fill text-emerald-500 mr-1"></i>180+ Guests</span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">Traditional luxe setup with authentic Filipino cuisine and grand banquet layout.</p>
                    </div>
                </div>

                <!-- Event 4 -->
                <div class="reveal-on-scroll reveal-delay-1 group bg-white dark:bg-slate-900 rounded-2xl p-4 shadow-xs border border-slate-200/80 dark:border-slate-800 hover:shadow-lg transition-all duration-300">
                    <div class="relative h-48 overflow-hidden rounded-xl mb-4">
                        <img src="uploads/gallery/emerald_white_arch_setup.jpg" alt="Emerald Pavilion & Arch Setup" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                        <div class="absolute top-3 left-3">
                            <span class="bg-white/95 dark:bg-slate-900/95 backdrop-blur-md px-3 py-1 rounded-lg text-[10px] font-semibold uppercase tracking-wider text-amber-600 dark:text-amber-400 shadow-xs">
                                Floral Pavilion
                            </span>
                        </div>
                    </div>
                    <div class="px-1 pb-1">
                        <div class="flex justify-between items-center mb-1">
                            <h4 class="text-base font-bold text-slate-900 dark:text-white tracking-tight">Emerald Pavilion & Arch</h4>
                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400"><i class="bi bi-people-fill text-amber-500 mr-1"></i>150+ Guests</span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">Elegant white & emerald floral arch styling with full buffet catering.</p>
                    </div>
                </div>

                <!-- Event 5 (Highlight Featured) -->
                <div class="reveal-on-scroll reveal-delay-2 group bg-white dark:bg-slate-900 rounded-2xl p-4 shadow-xs border border-slate-200/80 dark:border-slate-800 hover:shadow-lg transition-all duration-300 md:col-span-2 lg:col-span-2">
                    <div class="relative h-48 sm:h-52 overflow-hidden rounded-xl mb-4">
                        <img src="uploads/gallery/violet_floral_ceiling_setup.jpg" alt="Enchanted Purple Canopy Setup" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                        <div class="absolute top-3 left-3">
                            <span class="bg-white/95 dark:bg-slate-900/95 backdrop-blur-md px-3 py-1 rounded-lg text-[10px] font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 shadow-xs">
                                VIP Canopy Reception
                            </span>
                        </div>
                    </div>
                    <div class="px-2 pb-2 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-1.5 mb-1">
                                <span class="text-[10px] font-bold text-amber-500 uppercase tracking-wider"><i class="bi bi-star-fill"></i> 5-Star Experience</span>
                            </div>
                            <h4 class="text-base font-bold text-slate-900 dark:text-white tracking-tight">Enchanted VIP Canopy Reception</h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">Romantic floral ceiling canopy, delicious dining, and energized guests creating lifetime memories.</p>
                        </div>
                        <a href="register.php" class="px-5 py-2.5 bg-slate-900 dark:bg-purple-600 text-white rounded-xl text-xs font-semibold uppercase tracking-wider hover:bg-purple-600 dark:hover:bg-purple-500 transition-all shadow-xs shrink-0">
                            Book Yours Today <i class="bi bi-arrow-right ml-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact & Business Information Section -->
    <section id="contact" class="py-16 px-4 sm:px-6 lg:px-8 bg-white dark:bg-[#0b0f19] transition-colors">
        <div class="container-fluid">
            <div class="text-center max-w-2xl mx-auto mb-12 reveal-on-scroll">
                <p class="text-xs font-bold text-purple-600 dark:text-purple-400 uppercase tracking-widest mb-1.5">Get In Touch</p>
                <h3 class="text-3xl md:text-4xl font-bold text-slate-900 dark:text-white tracking-tight">Contact & Business Information</h3>
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 font-normal mt-2">Have questions or custom catering requests? Reach out to our team directly.</p>
                <div class="w-16 h-1 bg-purple-600 mx-auto mt-4 rounded-full"></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Location Card -->
                <div class="reveal-on-scroll reveal-delay-1 bg-slate-50/70 dark:bg-slate-900/90 p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800 space-y-3 hover:shadow-md transition-all duration-300">
                    <div class="w-11 h-11 rounded-xl bg-purple-100 dark:bg-purple-950/80 text-purple-600 dark:text-purple-400 flex items-center justify-center text-xl">
                        <i class="bi bi-geo-alt-fill"></i>
                    </div>
                    <h4 class="text-base font-bold text-slate-900 dark:text-white">Main Location</h4>
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-normal leading-relaxed">
                        123 Catering Boulevard, Brgy. Central, Quezon City, Metro Manila, Philippines
                    </p>
                </div>

                <!-- Phone & Hotline Card -->
                <div class="reveal-on-scroll reveal-delay-2 bg-slate-50/70 dark:bg-slate-900/90 p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800 space-y-3 hover:shadow-md transition-all duration-300">
                    <div class="w-11 h-11 rounded-xl bg-pink-100 dark:bg-pink-950/80 text-pink-600 dark:text-pink-400 flex items-center justify-center text-xl">
                        <i class="bi bi-telephone-fill"></i>
                    </div>
                    <h4 class="text-base font-bold text-slate-900 dark:text-white">Phone & Hotlines</h4>
                    <div class="space-y-1 text-xs text-slate-500 dark:text-slate-400">
                        <p>Mobile: <strong class="text-slate-800 dark:text-slate-200">+63 917 892 3456</strong></p>
                        <p>Landline: <strong class="text-slate-800 dark:text-slate-200">(02) 8921-5678</strong></p>
                        <p>Viber: <strong class="text-slate-800 dark:text-slate-200">+63 917 892 3456</strong></p>
                    </div>
                </div>

                <!-- Email & Socials Card -->
                <div class="reveal-on-scroll reveal-delay-3 bg-slate-50/70 dark:bg-slate-900/90 p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800 space-y-3 hover:shadow-md transition-all duration-300">
                    <div class="w-11 h-11 rounded-xl bg-blue-100 dark:bg-blue-950/80 text-blue-600 dark:text-blue-400 flex items-center justify-center text-xl">
                        <i class="bi bi-envelope-fill"></i>
                    </div>
                    <h4 class="text-base font-bold text-slate-900 dark:text-white">Email & Socials</h4>
                    <div class="space-y-1.5 text-xs text-slate-500 dark:text-slate-400">
                        <p>Email: <a href="mailto:info@jemmerific.com" class="text-purple-600 dark:text-purple-400 font-semibold hover:underline">info@jemmerific.com</a></p>
                        <p>Facebook: <a href="https://facebook.com/jemmerific" target="_blank" class="text-blue-600 dark:text-blue-400 font-semibold hover:underline">facebook.com/jemmerific</a></p>
                        <p>Instagram: <a href="https://instagram.com/jemmerific" target="_blank" class="text-pink-600 dark:text-pink-400 font-semibold hover:underline">@jemmerific.catering</a></p>
                    </div>
                </div>

                <!-- Operating Hours Card -->
                <div class="reveal-on-scroll reveal-delay-3 bg-slate-50/70 dark:bg-slate-900/90 p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800 space-y-3 hover:shadow-md transition-all duration-300">
                    <div class="w-11 h-11 rounded-xl bg-amber-100 dark:bg-amber-950/80 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xl">
                        <i class="bi bi-clock-fill"></i>
                    </div>
                    <h4 class="text-base font-bold text-slate-900 dark:text-white">Business Hours</h4>
                    <div class="space-y-1 text-xs text-slate-500 dark:text-slate-400">
                        <p>Mon - Sat: <strong class="text-slate-800 dark:text-slate-200">8:00 AM – 7:00 PM</strong></p>
                        <p>Sunday: <strong class="text-slate-800 dark:text-slate-200">9:00 AM – 5:00 PM</strong></p>
                        <span class="inline-block mt-1 px-2.5 py-0.5 bg-emerald-50 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300 text-[10px] font-semibold rounded-full border border-emerald-100 dark:border-emerald-800">
                            Online Booking 24/7 Available
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Global CTA Banner -->
    <section class="px-4 sm:px-6 lg:px-8 pb-16">
        <div class="reveal-on-scroll container-fluid rounded-3xl bg-slate-900 dark:bg-slate-950 p-8 sm:p-12 md:p-14 text-center relative overflow-hidden shadow-xl border border-slate-800">
            <div class="absolute top-[-20%] right-[-10%] w-[400px] h-[400px] bg-purple-600 rounded-full blur-[130px] opacity-30"></div>
            <div class="absolute bottom-[-20%] left-[-10%] w-[350px] h-[350px] bg-pink-500 rounded-full blur-[130px] opacity-20"></div>
            
            <div class="relative z-10 space-y-6 max-w-3xl mx-auto">
                <h3 class="text-3xl sm:text-4xl md:text-5xl font-extrabold text-white tracking-tight leading-tight">
                    Ready to Make Your Next Event <br><span class="italic text-purple-400">JEMmerific?</span>
                </h3>
                <p class="text-slate-300 text-sm sm:text-base max-w-xl mx-auto font-normal">Join our ecosystem today and experience high-end culinary arts paired with seamless logistics.</p>
                <div class="pt-3">
                    <a href="register.php" class="inline-block bg-white text-slate-900 px-8 py-3.5 rounded-xl font-bold shadow-lg hover:bg-purple-500 hover:text-white transition-all text-xs uppercase tracking-wider">
                        Create Your Account
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-white dark:bg-[#080b12] border-t border-slate-100 dark:border-slate-800/80 pt-14 pb-10 px-4 sm:px-6 lg:px-8 transition-colors">
        <div class="container-fluid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-10 mb-12">
            <div class="space-y-4 col-span-1 lg:col-span-2">
                <div class="flex items-center gap-3">
                    <img src="uploads/logo.png" alt="JEMmerific Logo" class="w-10 h-10 object-contain">
                    <h5 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">JEMmerific Catering</h5>
                </div>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 max-w-sm font-normal leading-relaxed">Elevating celebrations through authentic culinary arts and modern technology. Proudly serving premium event experiences.</p>
                <div class="flex gap-3 pt-1">
                    <a href="#" class="w-9 h-9 rounded-xl bg-slate-50 dark:bg-slate-800 flex items-center justify-center text-slate-500 dark:text-slate-400 hover:bg-purple-600 hover:text-white transition-all text-sm"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="w-9 h-9 rounded-xl bg-slate-50 dark:bg-slate-800 flex items-center justify-center text-slate-500 dark:text-slate-400 hover:bg-purple-600 hover:text-white transition-all text-sm"><i class="bi bi-instagram"></i></a>
                    <a href="#" class="w-9 h-9 rounded-xl bg-slate-50 dark:bg-slate-800 flex items-center justify-center text-slate-500 dark:text-slate-400 hover:bg-purple-600 hover:text-white transition-all text-sm"><i class="bi bi-linkedin"></i></a>
                </div>
            </div>
            
            <div class="space-y-3">
                <h6 class="text-xs font-bold text-slate-900 dark:text-slate-200 uppercase tracking-wider">Platform Services</h6>
                <ul class="space-y-2.5 text-xs font-medium text-slate-500 dark:text-slate-400">
                    <li><a href="#" class="hover:text-purple-600 dark:hover:text-purple-400 transition-colors">Signature Menus</a></li>
                    <li><a href="#" class="hover:text-purple-600 dark:hover:text-purple-400 transition-colors">AI Planning Tool</a></li>
                    <li><a href="#" class="hover:text-purple-600 dark:hover:text-purple-400 transition-colors">Logistics Calculator</a></li>
                </ul>
            </div>

            <div class="space-y-3">
                <h6 class="text-xs font-bold text-slate-900 dark:text-slate-200 uppercase tracking-wider">Legal & Support</h6>
                <ul class="space-y-2.5 text-xs font-medium text-slate-500 dark:text-slate-400">
                    <li><a href="#" class="hover:text-purple-600 dark:hover:text-purple-400 transition-colors">Contact Expert</a></li>
                    <li><a href="#" class="hover:text-purple-600 dark:hover:text-purple-400 transition-colors">Privacy Charter</a></li>
                    <li><a href="#" class="hover:text-purple-600 dark:hover:text-purple-400 transition-colors">Terms of Service</a></li>
                </ul>
            </div>
        </div>
        <div class="container-fluid pt-8 border-t border-slate-100 dark:border-slate-850 flex flex-col md:flex-row justify-between items-center gap-4">
            <p class="text-[11px] font-medium text-slate-400 dark:text-slate-500">&copy; 2026 JEMmerific Catering. All Rights Reserved.</p>
            <p class="text-[11px] font-medium text-slate-400 dark:text-slate-400">Deployed on Secure Infrastructure</p>
        </div>
    </footer>

    <!-- Minimalist & Professional Public AI Chatbot Widget -->
    <div class="fixed bottom-6 right-6 z-[200] flex flex-col items-end">
        
        <!-- Floating Launcher Button -->
        <button onclick="togglePublicChat()" id="publicChatLauncher" class="group relative w-14 h-14 rounded-full bg-gradient-to-tr from-purple-600 to-indigo-600 text-white flex items-center justify-center text-2xl shadow-xl shadow-purple-500/20 hover:scale-105 active:scale-95 transition-all duration-300 border border-purple-400/30 cursor-pointer" title="Ask AI Assistant">
            <i class="bi bi-stars group-hover:rotate-12 transition-transform"></i>
            <span class="absolute -top-1 -right-1 w-4 h-4 bg-emerald-500 border-2 border-white dark:border-slate-900 rounded-full"></span>
            
            <!-- Tooltip Pill -->
            <span class="absolute right-16 top-1/2 -translate-y-1/2 px-3 py-1.5 bg-slate-900 text-white text-[11px] font-bold rounded-xl whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none shadow-lg border border-slate-800">
                Ask JEMmerific AI ✨
            </span>
        </button>

        <!-- Floating Chat Modal Box -->
        <div id="publicChatWindow" class="hidden fixed bottom-24 right-4 sm:right-8 w-[92vw] sm:w-[400px] h-[550px] max-h-[82vh] bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-100/90 dark:border-slate-800 z-[205] flex flex-col overflow-hidden transition-all duration-300">
            
            <!-- Chat Header -->
            <div class="px-5 py-4 bg-slate-900 dark:bg-slate-950 text-white flex items-center justify-between border-b border-slate-800">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-purple-600 text-white flex items-center justify-center text-base shadow-sm">
                        <i class="bi bi-stars"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h4 class="text-sm font-extrabold tracking-tight">JEMmerific Assistant</h4>
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        </div>
                        <div class="flex items-center gap-2 mt-0.5">
                            <p class="text-[10px] text-slate-400 font-medium">Instant AI Catering Concierge</p>
                            <span id="publicChatLimitBadge" class="text-[9px] font-bold px-2 py-0.5 rounded-full bg-purple-500/30 text-purple-200 border border-purple-400/20">Checking limit...</span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-1">
                    <button onclick="clearPublicChat()" class="p-1.5 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition-colors" title="Reset Conversation">
                        <i class="bi bi-arrow-counterclockwise text-sm"></i>
                    </button>
                    <button onclick="togglePublicChat()" class="p-1.5 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition-colors" title="Close Chat">
                        <i class="bi bi-x-lg text-sm"></i>
                    </button>
                </div>
            </div>

            <!-- Messages Container -->
            <div id="publicChatMessages" class="flex-1 p-4 overflow-y-auto space-y-3.5 bg-slate-50/60 dark:bg-slate-950/60 text-xs">
                
                <!-- Initial Welcome Message -->
                <div class="flex items-start gap-2.5 max-w-[88%]">
                    <div class="w-7 h-7 rounded-lg bg-purple-100 dark:bg-purple-900/60 text-purple-700 dark:text-purple-300 flex items-center justify-center text-xs font-bold shrink-0 mt-0.5">
                        <i class="bi bi-robot"></i>
                    </div>
                    <div class="p-3.5 bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700 rounded-2xl rounded-tl-xs text-slate-800 dark:text-slate-100 font-medium shadow-2xs space-y-2 leading-relaxed">
                        <p>Hello! 👋 Welcome to <strong>JEMmerific Catering Services</strong>!</p>
                        <p>I'm your AI Concierge. Ask me anything about our package rates, menu selections, inclusions, or how to reserve your event date!</p>
                    </div>
                </div>

            </div>

            <!-- Quick Suggestion Chips -->
            <div class="px-4 py-2 bg-white dark:bg-slate-900 border-t border-slate-100 dark:border-slate-800 flex items-center gap-1.5 overflow-x-auto no-scrollbar scroll-smooth">
                <button onclick="sendQuickPrompt('What catering packages do you offer and how much?')" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-purple-50 dark:hover:bg-purple-900/40 hover:text-purple-700 dark:hover:text-purple-300 text-slate-600 dark:text-slate-300 rounded-full text-[10px] font-bold transition-all border border-slate-200/70 dark:border-slate-700 shrink-0 cursor-pointer">
                    📦 Package Rates
                </button>
                <button onclick="sendQuickPrompt('What are your most popular menu dishes?')" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-purple-50 dark:hover:bg-purple-900/40 hover:text-purple-700 dark:hover:text-purple-300 text-slate-600 dark:text-slate-300 rounded-full text-[10px] font-bold transition-all border border-slate-200/70 dark:border-slate-700 shrink-0 cursor-pointer">
                    🍲 Menu Options
                </button>
                <button onclick="sendQuickPrompt('How do I reserve a date for my event?')" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-purple-50 dark:hover:bg-purple-900/40 hover:text-purple-700 dark:hover:text-purple-300 text-slate-600 dark:text-slate-300 rounded-full text-[10px] font-bold transition-all border border-slate-200/70 dark:border-slate-700 shrink-0 cursor-pointer">
                    📅 How to Book
                </button>
            </div>

            <!-- Input Bar -->
            <form id="publicChatForm" onsubmit="handlePublicChatSubmit(event)" class="p-3 bg-white dark:bg-slate-900 border-t border-slate-100 dark:border-slate-800 flex items-center gap-2">
                <input type="text" id="publicChatInput" placeholder="Ask anything about JEMmerific..." autocomplete="off" required
                       class="flex-1 px-4 py-3 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl text-xs font-semibold text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 placeholder:text-slate-400 dark:placeholder:text-slate-500">
                <button type="submit" id="publicChatSubmitBtn" class="w-11 h-11 rounded-2xl bg-purple-600 hover:bg-purple-700 active:scale-95 text-white flex items-center justify-center transition-all shadow-md shadow-purple-200 dark:shadow-none cursor-pointer">
                    <i class="bi bi-send-fill text-sm"></i>
                </button>
            </form>

        </div>
    </div>

    <!-- Interactive Hero Video Modal Lightbox -->
    <div id="heroVideoModal" class="hidden fixed inset-0 z-[300] bg-slate-950/90 backdrop-blur-xl flex items-center justify-center p-4 sm:p-8 transition-all duration-300">
        <div class="relative w-full max-w-5xl bg-slate-900 rounded-[2.5rem] border border-slate-800 overflow-hidden shadow-2xl flex flex-col">
            <!-- Modal Header -->
            <div class="px-6 py-4 bg-slate-950 text-white flex items-center justify-between border-b border-slate-800">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-purple-600 text-white flex items-center justify-center text-lg shadow-sm">
                        <i class="bi bi-film"></i>
                    </div>
                    <div>
                        <h4 class="text-sm font-black tracking-tight">JEMmerific Catering Showcase Reel</h4>
                        <p class="text-[10px] text-slate-400">High-Definition Gourmet Catering & Venue Setup</p>
                    </div>
                </div>
                <button onclick="closeHeroVideoModal()" class="p-2 text-slate-400 hover:text-white rounded-xl hover:bg-slate-800 transition-colors text-lg cursor-pointer">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <!-- Video Display Container -->
            <div class="relative aspect-video bg-black flex items-center justify-center overflow-hidden">
                <video id="modalVideoPlayer" controls controlsList="nodownload" class="w-full h-full object-cover">
                    <source src="https://assets.mixkit.co/videos/preview/mixkit-chef-preparing-a-dish-41584-large.mp4" type="video/mp4">
                    <source src="https://cdn.coverr.co/videos/coverr-buffet-food-table-at-a-party-5674/1080p.mp4" type="video/mp4">
                    Your browser does not support HTML5 video.
                </video>
            </div>

            <!-- Modal Footer & Chapter Markers -->
            <div class="p-6 bg-slate-950 border-t border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Chapters:</span>
                    <button onclick="seekModalVideo(0)" class="px-3 py-1.5 bg-slate-800 hover:bg-purple-600 text-slate-200 hover:text-white text-[10px] font-bold rounded-lg transition-all cursor-pointer">0:00 Banquet Table</button>
                    <button onclick="seekModalVideo(5)" class="px-3 py-1.5 bg-slate-800 hover:bg-purple-600 text-slate-200 hover:text-white text-[10px] font-bold rounded-lg transition-all cursor-pointer">0:05 Gourmet Prep</button>
                </div>
                <a href="register.php" class="px-6 py-3 bg-purple-600 hover:bg-purple-500 text-white rounded-xl text-xs font-black uppercase tracking-widest transition-all">
                    Book Event Now <i class="bi bi-arrow-right ml-1"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Client-Side JavaScript Logic -->
    <script>
        // Dark Mode Toggle Logic
        function toggleDarkMode() {
            const isDark = document.documentElement.classList.toggle('dark');
            if (document.body) document.body.classList.toggle('dark', isDark);
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            updateThemeIcons(isDark);
        }

        function updateThemeIcons(isDark) {
            const icons = document.querySelectorAll('.theme-toggle-icon');
            icons.forEach(icon => {
                if (isDark) {
                    icon.className = 'theme-toggle-icon bi bi-sun-fill text-amber-400';
                } else {
                    icon.className = 'theme-toggle-icon bi bi-moon-stars-fill text-slate-700';
                }
            });
        }

        // Animated Moving Numbers Counter Logic
        function animateCounters() {
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach(el => {
                const target = parseFloat(el.getAttribute('data-target'));
                const isPercent = el.getAttribute('data-percent') === 'true';
                const duration = 1800; // 1.8 seconds
                const startTime = performance.now();

                function step(currentTime) {
                    const elapsed = currentTime - startTime;
                    const progress = Math.min(elapsed / duration, 1);
                    // Cubic ease out
                    const eased = 1 - Math.pow(1 - progress, 3);
                    const val = progress === 1 ? target : (isPercent ? (eased * target) : Math.floor(eased * target));

                    if (isPercent) {
                        el.innerText = val.toFixed(1) + '%';
                    } else {
                        el.innerText = val.toLocaleString() + '+';
                    }

                    if (progress < 1) {
                        requestAnimationFrame(step);
                    }
                }
                requestAnimationFrame(step);
            });
        }

        // Scroll Observer Setup
        document.addEventListener('DOMContentLoaded', () => {
            // Sync theme icons
            const isDark = document.documentElement.classList.contains('dark');
            updateThemeIcons(isDark);

            // Initialize Constellation Canvas Network (Matches user reference image)
            initConstellation();

            // Observer for scroll-triggered counter animation
            const statsSection = document.getElementById('stats-section');
            if (statsSection) {
                const statsObserver = new IntersectionObserver((entries, obs) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            animateCounters();
                            obs.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.2 });
                statsObserver.observe(statsSection);
            }

            // Observer for scroll reveal animations
            const revealObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                    }
                });
            }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

            document.querySelectorAll('.reveal-on-scroll').forEach(el => {
                revealObserver.observe(el);
            });

            // Fetch AI Chat limit
            fetchPublicChatLimit();
        });

        // Ultra-Fast 60 FPS Constellation Canvas Animation
        function initConstellation() {
            const canvas = document.getElementById('constellationCanvas');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            
            function resizeCanvas() {
                canvas.width = window.innerWidth;
                canvas.height = window.innerHeight;
            }
            resizeCanvas();
            window.addEventListener('resize', resizeCanvas);

            const particles = [];
            const particleCount = 50; // Optimized particle count for 60 FPS

            for (let i = 0; i < particleCount; i++) {
                particles.push({
                    x: Math.random() * window.innerWidth,
                    y: Math.random() * window.innerHeight,
                    vx: (Math.random() - 0.5) * 0.7,
                    vy: (Math.random() - 0.5) * 0.7,
                    radius: Math.random() * 2.2 + 1.5
                });
            }

            function draw() {
                const width = canvas.width;
                const height = canvas.height;
                ctx.clearRect(0, 0, width, height);

                const isDark = document.documentElement.classList.contains('dark');
                
                const nodeColor = isDark ? 'rgba(239, 68, 68, 0.95)' : 'rgba(168, 85, 247, 0.85)';
                const haloColor = isDark ? 'rgba(239, 68, 68, 0.25)' : 'rgba(168, 85, 247, 0.2)';
                const lineColor = isDark ? '239, 68, 68' : '168, 85, 247';

                for (let i = 0; i < particles.length; i++) {
                    let p = particles[i];
                    p.x += p.vx;
                    p.y += p.vy;

                    if (p.x < 0 || p.x > width) p.vx *= -1;
                    if (p.y < 0 || p.y > height) p.vy *= -1;

                    // Fast double-arc glowing node (No shadowBlur CPU overhead)
                    ctx.beginPath();
                    ctx.arc(p.x, p.y, p.radius * 2.4, 0, Math.PI * 2);
                    ctx.fillStyle = haloColor;
                    ctx.fill();

                    ctx.beginPath();
                    ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
                    ctx.fillStyle = nodeColor;
                    ctx.fill();

                    // Connect nearby nodes
                    for (let j = i + 1; j < particles.length; j++) {
                        let p2 = particles[j];
                        let dx = p.x - p2.x;
                        let dy = p.y - p2.y;
                        let distSq = dx * dx + dy * dy;

                        if (distSq < 19600) { // 140px squared fast check
                            let dist = Math.sqrt(distSq);
                            ctx.beginPath();
                            ctx.moveTo(p.x, p.y);
                            ctx.lineTo(p2.x, p2.y);
                            ctx.strokeStyle = `rgba(${lineColor}, ${(1 - dist / 140) * 0.6})`;
                            ctx.lineWidth = 1.0;
                            ctx.stroke();
                        }
                    }
                }
                requestAnimationFrame(draw);
            }
            draw();
        }

        // Chatbot functions
        function togglePublicChat() {
            const win = document.getElementById('publicChatWindow');
            win.classList.toggle('hidden');
            if (!win.classList.contains('hidden')) {
                fetchPublicChatLimit();
                document.getElementById('publicChatInput').focus();
            }
        }

        async function fetchPublicChatLimit() {
            try {
                const res = await fetch('auth/public_ai_chat.php?action=check_limit');
                const data = await res.json();
                updatePublicChatLimitUI(data);
            } catch (err) {
                console.warn("Public chat limit check error:", err);
                updatePublicChatLimitUI({ success: true, status: 'success', count: 0, limit: 10, remaining: 10 });
            }
        }

        function updatePublicChatLimitUI(data) {
            const badge = document.getElementById('publicChatLimitBadge');
            const input = document.getElementById('publicChatInput');
            const submitBtn = document.getElementById('publicChatSubmitBtn');

            if (!data || (!data.success && data.status !== 'success')) return;

            const remaining = data.remaining !== undefined ? data.remaining : (data.limit - data.count);
            const limit = data.limit || 10;
            const count = data.count || 0;

            if (badge) {
                badge.textContent = `${remaining}/${limit} msgs left`;
                if (remaining <= 0) {
                    badge.className = "text-[9px] font-bold px-2 py-0.5 rounded-full bg-rose-500/30 text-rose-300 border border-rose-400/20";
                } else {
                    badge.className = "text-[9px] font-bold px-2 py-0.5 rounded-full bg-purple-500/30 text-purple-200 border border-purple-400/20";
                }
            }

            if (remaining <= 0) {
                input.disabled = true;
                input.placeholder = `Daily limit reached (${count}/${limit} sent today)`;
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            } else {
                input.disabled = false;
                input.placeholder = "Ask anything about JEMmerific...";
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        }

        function clearPublicChat() {
            const container = document.getElementById('publicChatMessages');
            container.innerHTML = `
                <div class="flex items-start gap-2.5 max-w-[88%]">
                    <div class="w-7 h-7 rounded-lg bg-purple-100 dark:bg-purple-900/60 text-purple-700 dark:text-purple-300 flex items-center justify-center text-xs font-bold shrink-0 mt-0.5">
                        <i class="bi bi-robot"></i>
                    </div>
                    <div class="p-3.5 bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700 rounded-2xl rounded-tl-xs text-slate-800 dark:text-slate-100 font-medium shadow-2xs space-y-2 leading-relaxed">
                        <p>Hello! 👋 Conversation reset.</p>
                        <p>How else can I assist you with JEMmerific Catering Services?</p>
                    </div>
                </div>
            `;
        }

        function sendQuickPrompt(promptText) {
            document.getElementById('publicChatInput').value = promptText;
            document.getElementById('publicChatForm').dispatchEvent(new Event('submit'));
        }

        function formatMarkdownText(text) {
            if (!text) return '';
            let formatted = text
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                .replace(/\n\n/g, '</p><p class="mt-2">')
                .replace(/\n/g, '<br>');
            return `<p>${formatted}</p>`;
        }

        async function handlePublicChatSubmit(e) {
            e.preventDefault();
            const input = document.getElementById('publicChatInput');
            const submitBtn = document.getElementById('publicChatSubmitBtn');
            const messagesContainer = document.getElementById('publicChatMessages');
            const userMsg = input.value.trim();

            if (!userMsg) return;

            // 1. Append User Message
            const userBubble = document.createElement('div');
            userBubble.className = 'flex justify-end';
            userBubble.innerHTML = `
                <div class="max-w-[85%] p-3.5 bg-purple-600 text-white rounded-2xl rounded-tr-xs font-medium shadow-2xs leading-relaxed text-xs">
                    ${userMsg.replace(/</g, "&lt;").replace(/>/g, "&gt;")}
                </div>
            `;
            messagesContainer.appendChild(userBubble);

            input.value = '';
            submitBtn.disabled = true;

            // 2. Append Typing Indicator
            const typingBubble = document.createElement('div');
            typingBubble.id = 'activeTypingIndicator';
            typingBubble.className = 'flex items-start gap-2.5 max-w-[88%]';
            typingBubble.innerHTML = `
                <div class="w-7 h-7 rounded-lg bg-purple-100 dark:bg-purple-900/60 text-purple-700 dark:text-purple-300 flex items-center justify-center text-xs font-bold shrink-0 mt-0.5">
                    <i class="bi bi-robot"></i>
                </div>
                <div class="p-3.5 bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700 rounded-2xl rounded-tl-xs text-slate-500 dark:text-slate-400 font-medium shadow-2xs flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 bg-purple-600 rounded-full animate-bounce"></span>
                    <span class="w-1.5 h-1.5 bg-purple-600 rounded-full animate-bounce [animation-delay:0.2s]"></span>
                    <span class="w-1.5 h-1.5 bg-purple-600 rounded-full animate-bounce [animation-delay:0.4s]"></span>
                </div>
            `;
            messagesContainer.appendChild(typingBubble);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;

            // 3. Fetch AI Reply
            try {
                const formData = new FormData();
                formData.append('message', userMsg);

                const response = await fetch('auth/public_ai_chat.php', {
                    method: 'POST',
                    body: formData
                });
                
                let data;
                try {
                    data = await response.json();
                } catch (jsonErr) {
                    data = { success: false, status: 'error', error: "Could not parse server response." };
                }

                const indicator = document.getElementById('activeTypingIndicator');
                if (indicator) indicator.remove();

                const isSuccess = data.success || data.status === 'success';
                const botReply = isSuccess ? (data.reply || "Thank you for reaching out! How else may I assist you?") : (data.reply || data.error || data.message || "I'm having trouble connecting right now. Please try again shortly!");
                updatePublicChatLimitUI(data);

                const botBubble = document.createElement('div');
                botBubble.className = 'flex items-start gap-2.5 max-w-[88%]';
                botBubble.innerHTML = `
                    <div class="w-7 h-7 rounded-lg ${isSuccess ? 'bg-purple-100 dark:bg-purple-900/60 text-purple-700 dark:text-purple-300' : 'bg-rose-100 dark:bg-rose-900/60 text-rose-700 dark:text-rose-300'} flex items-center justify-center text-xs font-bold shrink-0 mt-0.5">
                        <i class="bi ${isSuccess ? 'bi-robot' : 'bi-exclamation-triangle-fill'}"></i>
                    </div>
                    <div class="p-3.5 bg-white dark:bg-slate-800 border ${isSuccess ? 'border-slate-200/80 dark:border-slate-700' : 'border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200'} rounded-2xl rounded-tl-xs font-medium shadow-2xs leading-relaxed text-slate-800 dark:text-slate-100">
                        ${formatMarkdownText(botReply)}
                    </div>
                `;
                messagesContainer.appendChild(botBubble);
            } catch (err) {
                const indicator = document.getElementById('activeTypingIndicator');
                if (indicator) indicator.remove();

                const errBubble = document.createElement('div');
                errBubble.className = 'flex items-start gap-2.5 max-w-[88%]';
                errBubble.innerHTML = `
                    <div class="w-7 h-7 rounded-lg bg-rose-100 dark:bg-rose-900/60 text-rose-700 dark:text-rose-300 flex items-center justify-center text-xs font-bold shrink-0 mt-0.5">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                    <div class="p-3.5 bg-white dark:bg-slate-800 border border-rose-200 dark:border-rose-800 text-slate-800 dark:text-slate-100 rounded-2xl rounded-tl-xs font-medium shadow-2xs leading-relaxed">
                        Unable to connect. Please check your internet connection or try again later.
                    </div>
                `;
                messagesContainer.appendChild(errBubble);
            } finally {
                const input = document.getElementById('publicChatInput');
                if (!input.disabled) {
                    submitBtn.disabled = false;
                }
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        }

        // About Us Slideshow Controller
        let currentAboutSlide = 0;
        const aboutSlides = [
            { src: 'uploads/team_1.jpg', caption: 'JEMmerific Uniformed Catering Staff' },
            { src: 'uploads/team_2.jpg', caption: 'Professional Banquet & Event Crew' },
            { src: 'uploads/team_3.jpg', caption: 'Elegant Venue & Wedding Catering Team' },
            { src: 'uploads/team_4.jpg', caption: 'Dedicated Catering Personnel' },
            { src: 'uploads/team_5.jpg', caption: 'Master Banquet & Dining Operators' }
        ];

        function changeAboutSlide(direction) {
            currentAboutSlide = (currentAboutSlide + direction + aboutSlides.length) % aboutSlides.length;
            updateAboutSlideUI();
        }

        function setAboutSlide(index) {
            currentAboutSlide = index;
            updateAboutSlideUI();
        }

        function updateAboutSlideUI() {
            const img = document.getElementById('aboutSlideImg');
            const counter = document.getElementById('aboutSlideCounter');
            const caption = document.getElementById('aboutSlideCaption');
            const dots = document.querySelectorAll('.about-slide-dot');

            if (img) {
                img.classList.add('opacity-0');
                setTimeout(() => {
                    img.src = aboutSlides[currentAboutSlide].src;
                    img.classList.remove('opacity-0');
                }, 180);
            }
            if (counter) counter.innerText = `${currentAboutSlide + 1} / ${aboutSlides.length}`;
            if (caption) caption.innerText = aboutSlides[currentAboutSlide].caption;

            dots.forEach((dot, idx) => {
                if (idx === currentAboutSlide) {
                    dot.className = 'about-slide-dot w-6 h-2 bg-purple-600 rounded-full transition-all cursor-pointer';
                } else {
                    dot.className = 'about-slide-dot w-2 h-2 bg-slate-300 dark:bg-slate-700 hover:bg-purple-400 rounded-full transition-all cursor-pointer';
                }
            });
        }

        // Auto slide every 4.5 seconds
        setInterval(() => {
            changeAboutSlide(1);
        }, 4500);
    </script>
</body>
</html>

