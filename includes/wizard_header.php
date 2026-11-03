<script>
    // Theme Initialization for Booking Wizard (dashboard.php)
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
<style>
    @media (min-width: 1024px) {
        html { font-size: 84%; }
    }

    /* Universal Dark Mode Overrides for Booking Flow (dashboard.php, menu.php, payment.php) */
    html.dark body, body.dark {
        background-color: #0b0f19 !important;
        color: #f8fafc !important;
    }

    html.dark header, html.dark header.bg-white {
        background-color: #131b2e !important;
        border-color: #1e293b !important;
    }

    html.dark .bg-white,
    html.dark .bg-slate-50,
    html.dark .bg-slate-100,
    html.dark .bg-slate-200,
    html.dark .bg-gray-50,
    html.dark .bg-gray-100,
    html.dark .bg-\[\#FBFBFC\] {
        background-color: #131b2e !important;
        border-color: #1e293b !important;
        color: #f8fafc !important;
    }

    /* Light badge overrides in dark mode */
    html.dark .bg-purple-50,
    html.dark .bg-purple-50\/80,
    html.dark .bg-purple-50\/60,
    html.dark .bg-purple-50\/50,
    html.dark .bg-purple-50\/30,
    html.dark .bg-purple-50\/20 {
        background-color: rgba(124, 58, 237, 0.15) !important;
        color: #c084fc !important;
        border-color: rgba(124, 58, 237, 0.3) !important;
    }

    html.dark .bg-emerald-50,
    html.dark .bg-emerald-50\/90,
    html.dark .bg-emerald-50\/50 {
        background-color: rgba(16, 185, 129, 0.15) !important;
        color: #34d399 !important;
        border-color: rgba(16, 185, 129, 0.3) !important;
    }

    html.dark .bg-amber-50,
    html.dark .bg-amber-50\/90 {
        background-color: rgba(245, 158, 11, 0.15) !important;
        color: #fbbf24 !important;
        border-color: rgba(245, 158, 11, 0.3) !important;
    }

    html.dark .bg-rose-50,
    html.dark .bg-rose-50\/90 {
        background-color: rgba(244, 63, 94, 0.15) !important;
        color: #f87171 !important;
        border-color: rgba(244, 63, 94, 0.3) !important;
    }

    /* Remove glowing white shadows in dark mode */
    html.dark .shadow-purple-200,
    html.dark .shadow-gray-200 {
        box-shadow: 0 4px 14px 0 rgba(124, 58, 237, 0.25) !important;
    }

    html.dark .border-slate-200\/80,
    html.dark .border-slate-100,
    html.dark .border-slate-200,
    html.dark .border-purple-100,
    html.dark .border-purple-200 {
        border-color: #1e293b !important;
    }

    /* Form Inputs, Selects & Textareas Dark Mode + Focus/Autofill White Spot Fix */
    html.dark input:not([type="checkbox"]):not([type="radio"]), 
    html.dark select, 
    html.dark textarea {
        background-color: #1b253b !important;
        border-color: #334155 !important;
        color: #f8fafc !important;
    }

    html.dark input:focus, 
    html.dark textarea:focus, 
    html.dark select:focus {
        background-color: #1b253b !important;
        border-color: #7c3aed !important;
        color: #ffffff !important;
        outline: none !important;
    }

    /* Prevent Chrome/Webkit autofill and focus white background spot */
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

    /* High-Contrast Typography */
    html.dark h1, html.dark h2, html.dark h3, html.dark h4, html.dark h5, html.dark h6,
    html.dark .text-slate-900, html.dark .text-gray-900, html.dark .text-gray-800, html.dark .text-slate-800, html.dark .text-slate-700 {
        color: #f8fafc !important;
    }

    html.dark p, html.dark span:not(.bg-purple-600):not(.text-white),
    html.dark .text-slate-600, html.dark .text-slate-500, html.dark .text-gray-500, html.dark .text-gray-600 {
        color: #cbd5e1 !important;
    }

    html.dark .text-slate-400, html.dark .text-gray-400 {
        color: #94a3b8 !important;
    }

    /* Menu Wizard (menu.php) Category Pills & Footer Sticky Summary */
    html.dark .category-pill,
    html.dark a.category-pill,
    html.dark .category-pill.bg-white,
    html.dark .category-pill.bg-gray-900 {
        background-color: #131b2e !important;
        color: #cbd5e1 !important;
        border: 1px solid #1e293b !important;
        box-shadow: none !important;
    }

    html.dark a.category-pill.bg-purple-600,
    html.dark a.category-pill.bg-gray-900.text-white {
        background-color: #7c3aed !important;
        color: #ffffff !important;
        border-color: #7c3aed !important;
    }

    html.dark .fixed.bottom-0,
    html.dark div[class*="fixed bottom-0"] {
        background-color: #131b2e !important;
        border-color: #1e293b !important;
        color: #f8fafc !important;
    }

    html.dark .border-gray-100,
    html.dark .border-gray-200 {
        border-color: #1e293b !important;
    }

    html.dark ::placeholder { color: #94a3b8 !important; }
</style>
<header class="bg-white dark:bg-[#131b2e] border-b border-slate-200/80 dark:border-slate-800 sticky top-0 z-[50] px-6 py-3.5 mb-8 shadow-xs">
    <div class="max-w-6xl mx-auto flex items-center justify-between">
        <!-- Logo & Branding -->
        <a href="client_home.php" class="flex items-center gap-3 group">
            <img src="uploads/logo.png" alt="JEMmerific Logo" class="w-10 h-10 object-contain rounded-xl">
            <div>
                <h1 class="text-base font-extrabold text-slate-900 dark:text-slate-100 leading-none group-hover:text-purple-600 transition-colors">JEMmerific</h1>
                <span class="text-[10px] font-bold text-purple-600 dark:text-purple-300 uppercase tracking-widest bg-purple-50 dark:bg-purple-950/60 px-2.5 py-0.5 rounded-full border border-purple-100 dark:border-purple-800/50 inline-block mt-0.5">Catering Experience</span>
            </div>
        </a>

        <!-- Right Exit Action -->
        <a href="client_home.php" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold rounded-xl text-xs uppercase tracking-wider transition-all flex items-center gap-1.5 shadow-2xs border border-slate-200 dark:border-slate-700" title="Return to Dashboard (your booking progress is saved as draft)">
            <i class="bi bi-arrow-left text-sm"></i> Dashboard
        </a>
    </div>
</header>
