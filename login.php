<?php
// Initialize session cleanly; discard stale session cookies if logged out or if file access is restricted
if (session_status() === PHP_SESSION_NONE) {
    if (isset($_GET['success']) && $_GET['success'] === 'logged_out' && isset($_COOKIE[session_name()])) {
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        unset($_COOKIE[session_name()]);
    }
    
    // Safely start session, recovering if stale session file in XAMPP tmp causes permission issue
    if (!@session_start()) {
        @session_id(bin2hex(random_bytes(16)));
        @session_start();
    }
}
// Secret bypass for development
if (isset($_GET['secret_key']) && $_GET['secret_key'] === 'my_super_secret_code') {
    $_SESSION['user_id'] = 1;
    $_SESSION['full_name'] = 'System Administrator';
    $_SESSION['role'] = 'admin';
    $_SESSION['logged_in'] = true;
    header("Location: admin_dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | JEMmerific</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        html, body { height: 100%; min-height: 100vh; margin: 0; padding: 0; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .input-transition { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        /* Enhanced contrast for text inputs */
        .custom-input { padding-right: 3.5rem !important; }
        .custom-input::placeholder { color: #9CA3AF; font-weight: 500; }
        @keyframes continuousShine {
            0% { transform: translateX(-150%) skewX(-20deg); }
            100% { transform: translateX(200%) skewX(-20deg); }
        }
        .btn-shine {
            position: relative;
            overflow: hidden;
        }
        .btn-shine::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 50%;
            height: 100%;
            background: linear-gradient(to right, transparent, rgba(255, 255, 255, 0.4), transparent);
            transform: translateX(-150%) skewX(-20deg);
            animation: continuousShine 2.5s infinite ease-in-out;
            z-index: 10;
            pointer-events: none;
        }
        @media (min-width: 1024px) {
            html {
                font-size: 84%;
            }
        }
    </style>
</head>
<body class="bg-white min-h-screen">

    <div class="flex min-h-screen w-full">
        <div class="hidden lg:block lg:w-1/2 relative overflow-hidden">
            <img src="https://images.unsplash.com/photo-1555244162-803834f70033?ixlib=rb-4.0.3&auto=format&fit=crop&w=1470&q=80" alt="Catering" class="absolute inset-0 w-full h-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-r from-purple-900/90 to-transparent flex items-center p-12 lg:p-16">
                <div class="max-w-md text-white">
                    <span class="inline-block px-4 py-1.5 bg-white/20 backdrop-blur-md rounded-full text-[10px] font-black uppercase tracking-[0.2em] mb-6 border border-white/30">Catering Excellence</span>
                    <h2 class="text-4xl lg:text-5xl font-black mb-4 tracking-tighter leading-none">Elevate Your Event Planning.</h2>
                    <p class="text-purple-50 text-base opacity-90 leading-relaxed">Access the JEMmerific suite for premium event logistics.</p>
                </div>
            </div>
        </div>

        <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-10 md:p-12 bg-gray-50">
            <div class="w-full max-w-md my-auto">
                <div class="mb-8 text-center lg:text-left">
                    <div class="inline-flex items-center justify-center p-2 bg-white shadow-md rounded-2xl mb-4">
                        <img src="uploads/logo.png" alt="JEMmerific Logo" class="w-12 h-12 object-contain">
                    </div>
                    <h1 class="text-3xl font-black text-gray-900 tracking-tighter">JEMmerific</h1>
                    <p class="text-gray-500 mt-1 text-xs font-semibold italic">"Taste the magic, manage the moments."</p>
                </div>

                <?php include 'includes/alerts.php'; ?>

                <?php if (isset($_GET['msg']) && $_GET['msg'] === 'pass_reset_success'): ?>
                    <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold flex items-center gap-3">
                        <i class="bi bi-check-circle-fill text-emerald-600 text-base shrink-0"></i>
                        <span>Password reset successful! You can now log in with your new password.</span>
                    </div>
                <?php endif; ?>

                <form action="auth/login_process.php" method="POST" class="space-y-5">
                    <div>
                        <label class="block text-[11px] font-black text-gray-600 uppercase tracking-widest mb-1.5 ml-1">Email Address</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-500">
                                <i class="bi bi-person-circle text-lg"></i>
                            </span>
                            <input type="email" name="email" required placeholder="name@email.com" 
                                   class="input-transition w-full pl-12 pr-5 py-3.5 bg-white border border-gray-200 rounded-2xl shadow-sm focus:border-purple-400 focus:ring-4 focus:ring-purple-100 outline-none text-gray-800 font-bold text-sm">
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between items-center mb-1.5 ml-1">
                            <label class="text-[11px] font-black text-gray-600 uppercase tracking-widest">Password</label>
                            <a href="forgot_password.php" class="text-[10px] font-bold text-purple-600 hover:underline uppercase tracking-widest">Forgot?</a>
                        </div>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-500">
                                <i class="bi bi-shield-lock-fill text-lg"></i>
                            </span>
                            <input type="password" id="loginPass" name="password" required placeholder="••••••••" 
                                   class="custom-input input-transition w-full pl-12 py-3.5 bg-white border border-gray-200 rounded-2xl shadow-sm focus:border-purple-400 focus:ring-4 focus:ring-purple-100 outline-none text-gray-800 font-bold text-sm">
                            <button type="button" onclick="togglePass('loginPass', 'eyeLogin')" class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-500 hover:text-purple-600">
                                <i id="eyeLogin" class="bi bi-eye-fill text-lg"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-shine w-full bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-extrabold py-4 rounded-2xl shadow-xl shadow-purple-500/40 hover:shadow-purple-500/60 transform hover:-translate-y-1 transition-all duration-300 uppercase tracking-widest text-xs">
                       Sign In
                    </button>
                </form>

                <div class="mt-12 pt-8 border-t border-gray-200 text-center">
                    <p class="text-gray-600 text-sm font-semibold">New here? <a href="register.php" class="text-purple-700 font-black hover:underline ml-1 uppercase">Register Account</a></p>
                </div>
            </div>
        </div>
    </div>
    <script>
        function togglePass(id, iconId) {
            const input = document.getElementById(id);
            const icon = document.getElementById(iconId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'bi bi-eye-slash-fill text-xl';
            } else {
                input.type = 'password';
                icon.className = 'bi bi-eye-fill text-xl';
            }
        }
    </script>
</body>
</html>
