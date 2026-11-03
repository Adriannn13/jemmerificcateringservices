<?php
// forgot_password.php - Minimalist Professional Password Recovery for JEMmerific
session_start();
require_once 'db_config.php';

$step = 1;
$error = '';
$success = '';
$user_email = $_SESSION['reset_email'] ?? '';

// Step 1: Submit Email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_reset') {
    $email = trim($_POST['email'] ?? '');
    if (!empty($email)) {
        $stmt = $conn->prepare("SELECT user_id, full_name, email FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user) {
            // Generate a 6-digit security code
            $reset_code = sprintf("%06d", mt_rand(100000, 999999));
            $_SESSION['reset_user_id'] = $user['user_id'];
            $_SESSION['reset_email'] = $user['email'];
            $_SESSION['reset_code'] = $reset_code;
            $_SESSION['reset_expires'] = time() + (15 * 60); // 15 minutes expiry

            $step = 2;
            $user_email = $user['email'];
            $success = "Verification code generated! For security purposes in this environment, your 6-digit reset code is: <strong class='text-purple-700 font-extrabold text-base tracking-widest'>" . $reset_code . "</strong>";
        } else {
            $error = "No account found registered with that email address.";
        }
    } else {
        $error = "Please enter a valid email address.";
    }
}

// Step 2: Verify Code & Set New Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_reset') {
    $code_input = trim($_POST['code'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $step = 2; // Stay on step 2 if error

    if (!isset($_SESSION['reset_user_id']) || time() > ($_SESSION['reset_expires'] ?? 0)) {
        $error = "Verification session expired. Please start over.";
        $step = 1;
    } elseif ($code_input !== $_SESSION['reset_code']) {
        $error = "Invalid verification code. Please check and try again.";
    } elseif (strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match. Please ensure both fields are identical.";
    } else {
        // Update Password
        $user_id = $_SESSION['reset_user_id'];
        $hashed_pass = password_hash($new_password, PASSWORD_DEFAULT);

        $up_stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $up_stmt->bind_param("si", $hashed_pass, $user_id);

        if ($up_stmt->execute()) {
            // Clear session tokens
            unset($_SESSION['reset_user_id'], $_SESSION['reset_email'], $_SESSION['reset_code'], $_SESSION['reset_expires']);
            header("Location: login.php?msg=pass_reset_success");
            exit();
        } else {
            $error = "Database error updating password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | JEMmerific</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        html, body { height: 100%; min-height: 100vh; margin: 0; padding: 0; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F8FAFC; }
        @media (min-width: 1024px) {
            html {
                font-size: 84%;
            }
        }
    </style>
</head>
<body class="bg-[#F8FAFC] min-h-screen flex items-center justify-center p-4 sm:p-6 antialiased">

    <div class="w-full max-w-md">
        <!-- Brand Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 bg-gradient-to-tr from-purple-700 to-indigo-600 rounded-2xl shadow-md shadow-purple-200 text-white font-black text-2xl mb-3">
                J
            </div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Account Password Recovery</h1>
            <p class="text-xs text-slate-500 font-semibold mt-1">Jemmerific Catering Services Security Portal</p>
        </div>

        <!-- Minimalist Form Card -->
        <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200/80 shadow-sm space-y-6">
            
            <?php if (!empty($error)): ?>
                <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold flex items-center gap-3">
                    <i class="bi bi-exclamation-triangle-fill text-rose-500 text-base shrink-0"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="p-4 rounded-2xl bg-purple-50 border border-purple-200 text-purple-900 text-xs font-medium leading-relaxed">
                    <div class="flex items-center gap-2 font-bold mb-1 text-purple-800">
                        <i class="bi bi-shield-check text-purple-600 text-base"></i> Verification Code Ready
                    </div>
                    <div><?= $success ?></div>
                </div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
                <!-- STEP 1: Enter Email Form -->
                <form action="forgot_password.php" method="POST" class="space-y-5">
                    <input type="hidden" name="action" value="request_reset">
                    <div>
                        <label class="block text-xs font-extrabold text-slate-700 uppercase tracking-wider mb-2">Registered Email Address</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400">
                                <i class="bi bi-envelope-fill text-base"></i>
                            </span>
                            <input type="email" name="email" required placeholder="yourname@email.com" 
                                   class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-purple-500/20 focus:border-purple-600 outline-none transition-all">
                        </div>
                        <p class="text-[11px] text-slate-400 mt-2">Enter the email associated with your Jemmerific account to receive a security code.</p>
                    </div>

                    <button type="submit" class="w-full py-4 bg-purple-600 hover:bg-purple-700 text-white font-extrabold text-xs uppercase tracking-wider rounded-2xl shadow-md shadow-purple-200 transition-all flex items-center justify-center gap-2 cursor-pointer">
                        <span>Send Security Code</span>
                        <i class="bi bi-arrow-right"></i>
                    </button>
                </form>

            <?php else: ?>
                <!-- STEP 2: Enter Code & New Password Form -->
                <form action="forgot_password.php" method="POST" class="space-y-5">
                    <input type="hidden" name="action" value="confirm_reset">

                    <div>
                        <label class="block text-xs font-extrabold text-slate-700 uppercase tracking-wider mb-2">6-Digit Security Code</label>
                        <input type="text" name="code" maxlength="6" required placeholder="123456" 
                               class="w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl text-center text-lg font-black tracking-widest text-purple-700 focus:bg-white focus:ring-2 focus:ring-purple-500/20 focus:border-purple-600 outline-none transition-all">
                    </div>

                    <div>
                        <label class="block text-xs font-extrabold text-slate-700 uppercase tracking-wider mb-2">New Password</label>
                        <div class="relative">
                            <input type="password" id="newPass" name="new_password" required minlength="6" placeholder="At least 6 characters" 
                                   class="w-full pl-4 pr-11 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-purple-500/20 focus:border-purple-600 outline-none transition-all">
                            <button type="button" onclick="togglePass('newPass', 'eyeNew')" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                <i id="eyeNew" class="bi bi-eye-fill text-base"></i>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-extrabold text-slate-700 uppercase tracking-wider mb-2">Confirm New Password</label>
                        <div class="relative">
                            <input type="password" id="confirmPass" name="confirm_password" required minlength="6" placeholder="Repeat new password" 
                                   class="w-full pl-4 pr-11 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-purple-500/20 focus:border-purple-600 outline-none transition-all">
                            <button type="button" onclick="togglePass('confirmPass', 'eyeConfirm')" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                <i id="eyeConfirm" class="bi bi-eye-fill text-base"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="w-full py-4 bg-purple-600 hover:bg-purple-700 text-white font-extrabold text-xs uppercase tracking-wider rounded-2xl shadow-md shadow-purple-200 transition-all flex items-center justify-center gap-2 cursor-pointer">
                        <i class="bi bi-shield-check text-sm"></i>
                        <span>Reset Password Now</span>
                    </button>
                </form>
            <?php endif; ?>

            <div class="pt-4 border-t border-slate-100 text-center">
                <a href="login.php" class="text-xs font-bold text-slate-500 hover:text-purple-600 flex items-center justify-center gap-1.5 transition-colors">
                    <i class="bi bi-arrow-left"></i> Return to Login
                </a>
            </div>
        </div>
    </div>

    <script>
        function togglePass(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'bi bi-eye-slash-fill text-base';
            } else {
                input.type = 'password';
                icon.className = 'bi bi-eye-fill text-base';
            }
        }
    </script>
</body>
</html>
