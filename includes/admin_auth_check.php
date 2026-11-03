<?php
// includes/admin_auth_check.php - Unified Access Gatekeeper for Admin & Staff Roles
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$path = (basename(dirname($_SERVER['PHP_SELF'])) == 'auth') ? '../' : '';

// 1. Verify Authentication
if (!isset($_SESSION['logged_in']) || empty($_SESSION['role'])) {
    header("Location: " . $path . "login.php?error=unauthorized");
    exit();
}

$user_role = strtolower($_SESSION['role']);

// 2. Reject clients and invalid roles immediately
if ($user_role !== 'admin' && $user_role !== 'staff') {
    header("Location: " . $path . "login.php?error=unauthorized");
    exit();
}

// 3. Include Staff Helper for permissions and online tracking
require_once __DIR__ . '/staff_helper.php';

// 4. Update online activity timestamp (if DB connection is available)
if (isset($conn) && !empty($_SESSION['user_id'])) {
    update_user_activity($conn, $_SESSION['user_id']);
}

// 5. System Administrator has full unrestricted access
if ($user_role === 'admin') {
    return;
}

// 6. Handle Staff Access Rights
if ($user_role === 'staff') {
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');

    // Staff Portal Dashboard is always accessible to staff
    if ($script === 'staff_dashboard.php') {
        return;
    }

    // Special check for view_booking.php: accessible by either booking_requests or ai_venue_setup
    if ($script === 'view_booking.php') {
        if (staff_has_permission('booking_requests', $conn ?? null) || staff_has_permission('ai_venue_setup', $conn ?? null)) {
            return;
        }
        header("Location: " . $path . "staff_dashboard.php?error=permission_denied&section=booking_requests");
        exit();
    }

    // Map section scripts to their required permission keys
    $page_permission_map = [
        'admin_messages.php'       => 'customer_messages',
        'admin_requests.php'       => 'booking_requests',
        'view_booking.php'         => 'booking_requests',
        'admin_refund_report.php'  => 'booking_requests',
        'admin_schedule.php'       => 'schedule_events',
        'inventory.php'            => 'menu_catalog',
        'toggle_availability.php'  => 'menu_catalog',
        'delete_menu_item.php'     => 'menu_catalog',
    ];

    if (isset($page_permission_map[$script])) {
        $required_key = $page_permission_map[$script];
        if (staff_has_permission($required_key, $conn ?? null)) {
            return; // Permitted
        } else {
            // Permission missing for this specific section
            header("Location: " . $path . "staff_dashboard.php?error=permission_denied&section=" . urlencode($required_key));
            exit();
        }
    }

    // Direct access to strictly Admin-Only sections (e.g. admin_dashboard.php, admin_staff.php, users.php, admin_settings.php)
    header("Location: " . $path . "staff_dashboard.php?error=admin_only");
    exit();
}
?>
