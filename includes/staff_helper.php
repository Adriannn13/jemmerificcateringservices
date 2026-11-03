<?php
// includes/staff_helper.php - Centralized helper for Staff Management, Roles & Section Permissions

if (!defined('STAFF_TASKS_DEFINED')) {
    define('STAFF_TASKS_DEFINED', true);

    // List of configurable staff tasks / sections on the dashboard
    define('STAFF_TASKS', [
        'customer_messages' => [
            'key'         => 'customer_messages',
            'title'       => 'Customer Messages',
            'short_title' => 'Messages',
            'icon'        => 'bi-chat-left-text-fill',
            'badge_color' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/60 dark:text-purple-300 border-purple-200 dark:border-purple-800',
            'color_theme' => 'purple',
            'desc'        => 'Monitor incoming client inquiries, chat in real-time, and resolve customer messages.',
            'url'         => 'admin_messages.php',
            'pages'       => ['admin_messages.php']
        ],
        'booking_requests' => [
            'key'         => 'booking_requests',
            'title'       => 'Booking Requests',
            'short_title' => 'Bookings',
            'icon'        => 'bi-bell-fill',
            'badge_color' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/60 dark:text-amber-300 border-amber-200 dark:border-amber-800',
            'color_theme' => 'amber',
            'desc'        => 'Review customer reservations, verify catering details, and manage booking approval status.',
            'url'         => 'admin_requests.php',
            'pages'       => ['admin_requests.php', 'view_booking.php', 'admin_refund_report.php']
        ],
        'schedule_events' => [
            'key'         => 'schedule_events',
            'title'       => 'Schedule Events',
            'short_title' => 'Schedule',
            'icon'        => 'bi-calendar-event-fill',
            'badge_color' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/60 dark:text-blue-300 border-blue-200 dark:border-blue-800',
            'color_theme' => 'blue',
            'desc'        => 'Monitor catering event calendar, view scheduled bookings, and toggle date availability.',
            'url'         => 'admin_schedule.php',
            'pages'       => ['admin_schedule.php']
        ],
        'menu_catalog' => [
            'key'         => 'menu_catalog',
            'title'       => 'Menu Catalog',
            'short_title' => 'Menu',
            'icon'        => 'bi-card-checklist',
            'badge_color' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
            'color_theme' => 'emerald',
            'desc'        => 'Browse and manage menu dishes, food packages, recipes, prices, and dish availability.',
            'url'         => 'inventory.php',
            'pages'       => ['inventory.php', 'toggle_availability.php', 'delete_menu_item.php']
        ],
        'ai_venue_setup' => [
            'key'         => 'ai_venue_setup',
            'title'       => 'AI Venue & Conceptual Layout',
            'short_title' => 'AI Venue Layout',
            'icon'        => 'bi-magic',
            'badge_color' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800',
            'color_theme' => 'indigo',
            'desc'        => 'Generate AI venue layout & catering setups (subject to Admin review and approval).',
            'url'         => 'admin_requests.php',
            'pages'       => ['view_booking.php']
        ]
    ]);
}

/**
 * Automatically migrate users table to support staff role, phone, plain_password,
 * permissions JSON, last_active datetime, and is_online status.
 */
function ensure_staff_schema($conn) {
    if (!$conn) return;

    try {
        $conn->query("ALTER TABLE `users` MODIFY `role` VARCHAR(50) DEFAULT 'client'");
    } catch (Throwable $t) {}

    try {
        $conn->query("ALTER TABLE `users` ADD COLUMN `phone` VARCHAR(30) NULL DEFAULT NULL AFTER `email`");
    } catch (Throwable $t) {}

    try {
        $conn->query("ALTER TABLE `users` ADD COLUMN `plain_password` VARCHAR(255) NULL DEFAULT NULL AFTER `password`");
    } catch (Throwable $t) {}

    try {
        $conn->query("ALTER TABLE `users` ADD COLUMN `permissions` TEXT NULL DEFAULT NULL AFTER `role`");
    } catch (Throwable $t) {}

    try {
        $conn->query("ALTER TABLE `users` ADD COLUMN `last_active` DATETIME NULL DEFAULT NULL AFTER `created_at`");
    } catch (Throwable $t) {}

    try {
        $conn->query("ALTER TABLE `users` ADD COLUMN `is_online` TINYINT(1) NOT NULL DEFAULT 0 AFTER `last_active`");
    } catch (Throwable $t) {}
}

/**
 * Record user activity for live online status monitoring.
 */
function update_user_activity($conn, $user_id) {
    if (!$conn || empty($user_id)) return;
    $uid = intval($user_id);
    try {
        $conn->query("UPDATE users SET last_active = NOW(), is_online = 1 WHERE user_id = $uid");
    } catch (Throwable $t) {}
}

/**
 * Determines whether a user is currently considered online (active within last 5 minutes).
 */
function is_user_online($last_active, $is_online = 1) {
    if (!$is_online || empty($last_active)) return false;
    $time = strtotime($last_active);
    if ($time === false) return false;
    return ((time() - $time) <= 300); // 300 seconds = 5 minutes
}

/**
 * Human readable relative time format (e.g., "Just now", "4m ago", "1h ago", "Oct 30, 2:30 PM")
 */
function format_last_active($last_active) {
    if (empty($last_active)) return 'Never logged in';
    $time = strtotime($last_active);
    if ($time === false) return 'Never';

    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 172800) return 'Yesterday at ' . date('h:i A', $time);
    return date('M d, Y h:i A', $time);
}

/**
 * Get permissions array for a given user or current session.
 */
function get_staff_permissions($conn, $user_id = null) {
    if ($user_id === null) {
        if (isset($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
            return $_SESSION['permissions'];
        }
        $user_id = $_SESSION['user_id'] ?? null;
    }

    if (!$conn || empty($user_id)) return [];

    $uid = intval($user_id);
    try {
        $stmt = $conn->prepare("SELECT permissions FROM users WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $perms = json_decode($row['permissions'] ?? '[]', true);
            return is_array($perms) ? $perms : [];
        }
    } catch (Throwable $t) {}

    return [];
}

/**
 * Check if the active staff member has permission for a specific section.
 * Admin always has access to all sections.
 */
function staff_has_permission($task_key, $conn = null) {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $role = strtolower($_SESSION['role'] ?? '');
    if ($role === 'admin') return true;
    if ($role !== 'staff') return false;

    // Check session permissions or query DB
    $perms = $_SESSION['permissions'] ?? null;
    if (!is_array($perms)) {
        if ($conn) {
            $perms = get_staff_permissions($conn, $_SESSION['user_id'] ?? null);
            $_SESSION['permissions'] = $perms;
        } else {
            $perms = [];
        }
    }

    return in_array($task_key, $perms, true);
}

