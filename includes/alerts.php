<?php
// includes/alerts.php
if (isset($_GET['error'])) {
    $error_msg = "";
    switch ($_GET['error']) {
        case 'invalid_credentials': $error_msg = "Invalid email or password."; break;
        case 'email_taken':         $error_msg = "That email address is already registered."; break;
        case 'pass_mismatch':       $error_msg = "Passwords do not match."; break;
        case 'pass_weak':           $error_msg = "Password must be at least 8 characters long and include uppercase, lowercase, number, and special character."; break;
        case 'invalid_email':       $error_msg = "Please enter a valid email address."; break;
        case 'invalid_csrf':        $error_msg = "Security token mismatch. Please try again."; break;
        case 'rate_limit':          $error_msg = "Too many attempts. Please wait 15 minutes before trying again."; break;
        case 'name_short':          $error_msg = "Full Name must be at least 2 characters."; break;
        case 'unauthorized':        $error_msg = "Access denied. Required privileges missing."; break;
        default:                    $error_msg = "An error occurred. Please try again."; break;
    }
    echo '<div class="mb-6 p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-xs font-bold flex items-center gap-3 shadow-xs">
            <i class="bi bi-exclamation-octagon-fill text-red-500 text-base shrink-0"></i>
            <span>' . htmlspecialchars($error_msg) . '</span>
          </div>';
}

if (isset($_GET['success'])) {
    $success_msg = "";
    switch ($_GET['success']) {
        case 'registered': $success_msg = "Account created securely! You can now log in below."; break;
        case 'logged_out': $success_msg = "You have been logged out safely."; break;
        default:           $success_msg = "Operation completed successfully."; break;
    }
    echo '<div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-3 shadow-xs">
            <i class="bi bi-check-circle-fill text-emerald-600 text-base shrink-0"></i>
            <span>' . htmlspecialchars($success_msg) . '</span>
          </div>';
}
?>