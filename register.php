<?php
session_start();

// Initialize CSRF Token if not present
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | JEMmerific</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        html, body { height: 100%; min-height: 100vh; margin: 0; padding: 0; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .custom-input { padding-right: 3.5rem !important; }
        .strength-bar { transition: width 0.3s ease, background-color 0.3s ease; }
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
        <!-- Left Branding Panel -->
        <div class="hidden lg:block lg:w-1/2 relative overflow-hidden">
            <img src="https://images.unsplash.com/photo-1414235077428-338989a2e8c0?ixlib=rb-4.0.3&auto=format&fit=crop&w=1470&q=80" alt="Catering" class="absolute inset-0 w-full h-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-r from-purple-900/90 via-purple-900/70 to-transparent flex items-center p-12 lg:p-16">
                <div class="max-w-md text-white">
                    <span class="inline-block px-4 py-1.5 bg-white/20 backdrop-blur-md rounded-full text-[10px] font-black uppercase tracking-[0.2em] mb-6 border border-white/30">Secure Registration</span>
                    <h2 class="text-4xl lg:text-5xl font-black mb-6 tracking-tighter leading-none">Join JEMmerific.</h2>
                    <p class="text-purple-50 text-base opacity-90 leading-relaxed">Create a protected customer account to plan events, customize menu packages, and monitor real-time catering status.</p>
                </div>
            </div>
        </div>

        <!-- Right Registration Form Panel -->
        <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-10 md:p-12 bg-gray-50 overflow-y-auto">
            <div class="w-full max-w-md my-auto">
                <div class="mb-8">
                    <a href="login.php" class="inline-flex items-center text-xs font-black text-purple-700 mb-6 uppercase tracking-widest hover:underline gap-1">
                        <i class="bi bi-arrow-left text-base"></i> Back to Login
                    </a>
                    <div class="flex items-center gap-3 mb-4">
                        <img src="uploads/logo.png" alt="JEMmerific Logo" class="w-10 h-10 object-contain">
                        <h1 class="text-3xl font-black text-gray-900 tracking-tighter">Create Account</h1>
                    </div>
                    <p class="text-xs font-semibold text-gray-400 mt-1">Fill in your details to create your secure customer profile.</p>
                </div>

                <!-- Alert Messages Container -->
                <?php include 'includes/alerts.php'; ?>

                <form id="regForm" action="auth/register_process.php" method="POST" onsubmit="return validateFormBeforeSubmit(event)" class="space-y-5">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <!-- Full Name -->
                    <div>
                        <label class="block text-[11px] font-black text-gray-600 uppercase tracking-widest mb-2 ml-1">Full Name</label>
                        <input type="text" name="full_name" required placeholder="John Doe" minlength="2"
                               class="w-full px-6 py-4 bg-white border border-gray-200 rounded-2xl shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-200 outline-none text-gray-800 font-bold text-sm">
                    </div>

                    <!-- Email Address -->
                    <div>
                        <label class="block text-[11px] font-black text-gray-600 uppercase tracking-widest mb-2 ml-1">Email Address</label>
                        <input type="email" name="email" required placeholder="john@example.com" 
                               class="w-full px-6 py-4 bg-white border border-gray-200 rounded-2xl shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-200 outline-none text-gray-800 font-bold text-sm">
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="block text-[11px] font-black text-gray-600 uppercase tracking-widest mb-2 ml-1">Password</label>
                        <div class="relative">
                            <input type="password" id="regPass" name="password" required placeholder="••••••••" oninput="evaluatePasswordStrength()"
                                   class="custom-input w-full px-6 py-4 bg-white border border-gray-200 rounded-2xl shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-200 outline-none text-gray-800 font-bold text-sm">
                            <button type="button" onclick="togglePass('regPass', 'eyeReg')" class="absolute inset-y-0 right-0 pr-5 flex items-center text-gray-400 hover:text-purple-600">
                                <i id="eyeReg" class="bi bi-eye-fill text-lg"></i>
                            </button>
                        </div>

                        <!-- Live Password Strength Meter -->
                        <div class="mt-3 space-y-2">
                            <div class="flex items-center justify-between text-[10px] font-black uppercase tracking-wider text-gray-400">
                                <span>Security Level</span>
                                <span id="strengthText" class="text-gray-400">Not Evaluated</span>
                            </div>
                            <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden">
                                <div id="strengthMeter" class="strength-bar h-full w-0 bg-gray-300"></div>
                            </div>
                        </div>

                        <!-- Password Requirements Checklist -->
                        <div class="mt-3 p-4 bg-white rounded-2xl border border-gray-100 space-y-1.5 text-xs font-semibold text-gray-400">
                            <div id="req-length" class="flex items-center gap-2 transition-colors">
                                <i id="icon-length" class="bi bi-circle text-[10px]"></i> Minimum 8 characters
                            </div>
                            <div id="req-upper" class="flex items-center gap-2 transition-colors">
                                <i id="icon-upper" class="bi bi-circle text-[10px]"></i> At least 1 uppercase letter (A-Z)
                            </div>
                            <div id="req-digit" class="flex items-center gap-2 transition-colors">
                                <i id="icon-digit" class="bi bi-circle text-[10px]"></i> At least 1 number (0-9)
                            </div>
                            <div id="req-special" class="flex items-center gap-2 transition-colors">
                                <i id="icon-special" class="bi bi-circle text-[10px]"></i> At least 1 special character (!@#$)
                            </div>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label class="block text-[11px] font-black text-gray-600 uppercase tracking-widest mb-2 ml-1">Confirm Password</label>
                        <div class="relative">
                            <input type="password" id="confirmPass" name="confirm_password" required placeholder="••••••••" oninput="checkPasswordMatch()"
                                   class="custom-input w-full px-6 py-4 bg-white border border-gray-200 rounded-2xl shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-200 outline-none text-gray-800 font-bold text-sm">
                            <button type="button" onclick="togglePass('confirmPass', 'eyeConfirm')" class="absolute inset-y-0 right-0 pr-5 flex items-center text-gray-400 hover:text-purple-600">
                                <i id="eyeConfirm" class="bi bi-eye-fill text-lg"></i>
                            </button>
                        </div>
                        <p id="matchText" class="text-[10px] font-bold mt-1.5 ml-1 hidden"></p>
                    </div>

                    <button type="submit" id="submitBtn" class="btn-shine w-full bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-extrabold py-4 rounded-2xl shadow-xl shadow-purple-500/40 hover:shadow-purple-500/60 transform hover:-translate-y-1 transition-all duration-300 uppercase tracking-widest text-xs mt-4 flex items-center justify-center gap-2">
                        <i class="bi bi-shield-lock-fill text-sm"></i> Register Account
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function togglePass(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'bi bi-eye-slash-fill text-lg';
            } else {
                input.type = 'password';
                icon.className = 'bi bi-eye-fill text-lg';
            }
        }

        let isPasswordValid = false;

        function evaluatePasswordStrength() {
            const val = document.getElementById('regPass').value;
            const meter = document.getElementById('strengthMeter');
            const text = document.getElementById('strengthText');

            const hasLength  = val.length >= 8;
            const hasUpper   = /[A-Z]/.test(val);
            const hasLower   = /[a-z]/.test(val);
            const hasDigit   = /[0-9]/.test(val);
            const hasSpecial = /[!@#$%^&*(),.?":{}|<>_\-\=\+\[\]]/.test(val);

            updateChecklist('req-length', 'icon-length', hasLength);
            updateChecklist('req-upper',  'icon-upper',  hasUpper);
            updateChecklist('req-digit',  'icon-digit',  hasDigit);
            updateChecklist('req-special','icon-special',hasSpecial);

            let score = 0;
            if (hasLength)  score++;
            if (hasUpper)   score++;
            if (hasLower)   score++;
            if (hasDigit)   score++;
            if (hasSpecial) score++;

            isPasswordValid = (hasLength && hasUpper && hasLower && hasDigit && hasSpecial);

            if (val.length === 0) {
                meter.style.width = '0%';
                meter.className = 'strength-bar h-full bg-gray-300';
                text.innerText = 'Not Evaluated';
                text.className = 'text-gray-400';
            } else if (score <= 2) {
                meter.style.width = '33%';
                meter.className = 'strength-bar h-full bg-rose-500';
                text.innerText = 'Weak';
                text.className = 'text-rose-500';
            } else if (score <= 4) {
                meter.style.width = '66%';
                meter.className = 'strength-bar h-full bg-amber-500';
                text.innerText = 'Fair / Good';
                text.className = 'text-amber-500';
            } else {
                meter.style.width = '100%';
                meter.className = 'strength-bar h-full bg-emerald-500';
                text.innerText = 'Strong & Secure';
                text.className = 'text-emerald-600';
            }

            checkPasswordMatch();
        }

        function updateChecklist(rowId, iconId, isMet) {
            const row = document.getElementById(rowId);
            const icon = document.getElementById(iconId);

            if (isMet) {
                row.className = 'flex items-center gap-2 text-emerald-600 font-bold';
                icon.className = 'bi bi-check-circle-fill text-xs text-emerald-500';
            } else {
                row.className = 'flex items-center gap-2 text-gray-400 font-semibold';
                icon.className = 'bi bi-circle text-[10px] text-gray-300';
            }
        }

        function checkPasswordMatch() {
            const pass = document.getElementById('regPass').value;
            const confirm = document.getElementById('confirmPass').value;
            const matchText = document.getElementById('matchText');

            if (confirm.length === 0) {
                matchText.classList.add('hidden');
                return;
            }

            matchText.classList.remove('hidden');
            if (pass === confirm) {
                matchText.innerText = '✓ Passwords match';
                matchText.className = 'text-[10px] font-bold mt-1.5 ml-1 text-emerald-600';
            } else {
                matchText.innerText = '✕ Passwords do not match';
                matchText.className = 'text-[10px] font-bold mt-1.5 ml-1 text-rose-500';
            }
        }

        function validateFormBeforeSubmit(e) {
            evaluatePasswordStrength();
            const pass = document.getElementById('regPass').value;
            const confirm = document.getElementById('confirmPass').value;

            if (!isPasswordValid) {
                e.preventDefault();
                alert('Please fulfill all password security requirements before registering.');
                return false;
            }

            if (pass !== confirm) {
                e.preventDefault();
                alert('Passwords do not match. Please verify your confirm password field.');
                return false;
            }

            return true;
        }
    </script>
</body>
</html>
