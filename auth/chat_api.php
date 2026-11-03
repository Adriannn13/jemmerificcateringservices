<?php
// auth/chat_api.php - Central API for Customer & Admin Real-Time Communication Center
// Adapted for InfinityFree shared hosting & Localhost

// Prevent PHP warnings/notices from corrupting the JSON response
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'success' => false,
        'message' => 'Unauthorized access. Please log in.',
        'error' => 'Unauthorized',
        'conversations' => [],
        'messages' => [],
        'bookings' => []
    ]);
    exit();
}

require_once __DIR__ . '/../db_config.php';

$current_user_id = (int)$_SESSION['user_id'];
$user_role = strtolower($_SESSION['role'] ?? '');
$staff_perms = $_SESSION['permissions'] ?? [];
$is_admin = ($user_role === 'admin' || $user_role === 'administrator' || $current_user_id === 1 || ($user_role === 'staff' && in_array('customer_messages', $staff_perms)));
session_write_close(); // Release session file lock immediately to prevent hanging requests on InfinityFree

// Read input payload (handles JSON php://input as well as standard POST/GET)
$rawInput = file_get_contents('php://input');
$jsonData = json_decode($rawInput, true);
if (!is_array($jsonData)) {
    $jsonData = [];
}

$action = trim($_GET['action'] ?? ($jsonData['action'] ?? ($_POST['action'] ?? '')));

// Auto-ensure messages table exists
try {
    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
        $conn->query("CREATE TABLE IF NOT EXISTS `messages` (
          `id` INT(11) NOT NULL AUTO_INCREMENT,
          `booking_id` INT(11) NOT NULL,
          `sender_id` INT(11) NOT NULL,
          `receiver_id` INT(11) NOT NULL,
          `message` TEXT DEFAULT NULL,
          `attachment` VARCHAR(255) DEFAULT NULL,
          `file_type` VARCHAR(50) DEFAULT NULL,
          `is_read` TINYINT(1) DEFAULT 0,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `booking_id` (`booking_id`),
          KEY `sender_id` (`sender_id`),
          KEY `receiver_id` (`receiver_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    }
} catch (Throwable $t) {}

switch ($action) {
    case 'fetch_conversations':
        if (!$is_admin) {
            echo json_encode([
                'status' => 'error',
                'success' => false,
                'message' => 'Access denied: Admin privileges required.',
                'error' => 'Access denied',
                'conversations' => []
            ]);
            exit();
        }

        $search = trim($_GET['search'] ?? ($jsonData['search'] ?? ''));
        $status_filter = trim($_GET['status'] ?? ($jsonData['status'] ?? 'all'));

        // Check if admin_archived_chat column exists on InfinityFree DB
        $has_archived_col = false;
        try {
            $col_res = $conn->query("SHOW COLUMNS FROM `bookings` LIKE 'admin_archived_chat'");
            if ($col_res && $col_res->num_rows > 0) {
                $has_archived_col = true;
            } else {
                @$conn->query("ALTER TABLE `bookings` ADD COLUMN `admin_archived_chat` TINYINT(1) DEFAULT 0");
                $has_archived_col = true;
            }
        } catch (Throwable $t) {
            $has_archived_col = false;
        }

        $params = [];
        $param_types = "";
        $where_clauses = [];

        if ($has_archived_col) {
            if ($status_filter === 'archived') {
                $where_clauses[] = "b.admin_archived_chat = 1";
            } else {
                $where_clauses[] = "b.admin_archived_chat = 0";
            }
        }

        if ($status_filter !== 'all' && $status_filter !== 'archived') {
            if ($status_filter === 'approved') {
                $where_clauses[] = "b.status IN ('confirmed', 'on_progress')";
            } else {
                $where_clauses[] = "b.status = ?";
                $params[] = $status_filter;
                $param_types .= "s";
            }
        }

        if (!empty($search)) {
            $where_clauses[] = "(u.full_name LIKE ? OR b.event_name LIKE ? OR CAST(b.booking_id AS CHAR) LIKE ? OR b.reference_no LIKE ?)";
            $search_param = "%$search%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $param_types .= "ssss";
        }

        $where_sql = !empty($where_clauses) ? ("WHERE " . implode(' AND ', $where_clauses)) : "";

        $sql = "SELECT b.booking_id, b.event_name, b.event_type, b.event_date, b.pax_count, b.status, b.created_at as booking_created,
                       u.user_id as customer_id, u.full_name as customer_name, u.email as customer_email,
                       (SELECT message FROM messages m WHERE m.booking_id = b.booking_id ORDER BY m.id DESC LIMIT 1) as last_message,
                       (SELECT attachment FROM messages m WHERE m.booking_id = b.booking_id ORDER BY m.id DESC LIMIT 1) as last_attachment,
                       (SELECT file_type FROM messages m WHERE m.booking_id = b.booking_id ORDER BY m.id DESC LIMIT 1) as last_file_type,
                       (SELECT created_at FROM messages m WHERE m.booking_id = b.booking_id ORDER BY m.id DESC LIMIT 1) as last_message_time,
                       (SELECT COUNT(*) FROM messages m WHERE m.booking_id = b.booking_id AND m.receiver_id = ? AND m.is_read = 0) as unread_count
                FROM bookings b
                LEFT JOIN users u ON b.user_id = u.user_id
                $where_sql
                ORDER BY COALESCE(last_message_time, b.created_at) DESC";

        try {
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                echo json_encode([
                    'status' => 'error',
                    'success' => false,
                    'message' => 'Query error: ' . $conn->error,
                    'conversations' => []
                ]);
                exit;
            }

            $all_params = array_merge([$current_user_id], $params);
            $all_types = "i" . $param_types;
            $stmt->bind_param($all_types, ...$all_params);
            $stmt->execute();
            $res = $stmt->get_result();

            $conversations = [];
            while ($row = $res->fetch_assoc()) {
                $snippet = $row['last_message'];
                if (empty($snippet) && !empty($row['last_attachment'])) {
                    $snippet = "📎 Attachment (" . strtoupper($row['last_file_type'] ?? 'FILE') . ")";
                } elseif (empty($snippet)) {
                    $snippet = "No messages yet";
                }
                $row['snippet'] = $snippet;
                $row['formatted_date'] = !empty($row['event_date']) ? date('M d, Y', strtotime($row['event_date'])) : '';
                $row['time_ago'] = !empty($row['last_message_time']) ? date('h:i A, M d', strtotime($row['last_message_time'])) : (!empty($row['booking_created']) ? date('h:i A, M d', strtotime($row['booking_created'])) : '');
                $conversations[] = $row;
            }

            echo json_encode([
                'status' => 'success',
                'success' => true,
                'conversations' => $conversations
            ]);
        } catch (Throwable $e) {
            echo json_encode([
                'status' => 'error',
                'success' => false,
                'message' => 'Query error: ' . $e->getMessage(),
                'conversations' => []
            ]);
        }
        break;

    case 'fetch_customer_bookings':
        try {
            $stmt = $conn->prepare("SELECT b.booking_id, b.event_name, b.event_type, b.event_date, b.status,
                                           (SELECT COUNT(*) FROM messages m WHERE m.booking_id = b.booking_id AND m.receiver_id = ? AND m.is_read = 0) as unread_count
                                    FROM bookings b
                                    WHERE b.user_id = ?
                                    ORDER BY b.event_date DESC, b.booking_id DESC");
            $stmt->bind_param("ii", $current_user_id, $current_user_id);
            $stmt->execute();
            $res = $stmt->get_result();
            
            $bookings = [];
            while ($row = $res->fetch_assoc()) {
                $row['formatted_date'] = !empty($row['event_date']) ? date('M d, Y', strtotime($row['event_date'])) : '';
                $bookings[] = $row;
            }
            echo json_encode([
                'status' => 'success',
                'success' => true,
                'bookings' => $bookings
            ]);
        } catch (Throwable $e) {
            echo json_encode([
                'status' => 'error',
                'success' => false,
                'message' => 'Failed to load bookings: ' . $e->getMessage(),
                'bookings' => []
            ]);
        }
        break;

    case 'fetch_messages':
        $booking_id = intval($_GET['booking_id'] ?? ($jsonData['booking_id'] ?? 0));
        $after_id = intval($_GET['after_id'] ?? ($jsonData['after_id'] ?? 0));

        if ($booking_id <= 0) {
            echo json_encode([
                'status' => 'error',
                'success' => false,
                'message' => 'Invalid booking ID',
                'error' => 'Invalid booking ID'
            ]);
            exit();
        }

        try {
            // Delta polling: Only return new messages if after_id > 0
            if ($after_id > 0) {
                $m_stmt = $conn->prepare("SELECT m.*, u.full_name as sender_name, u.role as sender_role
                                          FROM messages m
                                          LEFT JOIN users u ON m.sender_id = u.user_id
                                          WHERE m.booking_id = ? AND m.id > ?
                                          ORDER BY m.id ASC");
                $m_stmt->bind_param("ii", $booking_id, $after_id);
                $m_stmt->execute();
                $m_res = $m_stmt->get_result();

                if ($m_res->num_rows === 0) {
                    echo json_encode([
                        'status' => 'success',
                        'success' => true,
                        'has_new' => false,
                        'messages' => [],
                        'booking_id' => $booking_id
                    ]);
                    exit();
                }

                // Mark incoming as read
                $mark_stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE booking_id = ? AND receiver_id = ? AND id > ? AND is_read = 0");
                $mark_stmt->bind_param("iii", $booking_id, $current_user_id, $after_id);
                $mark_stmt->execute();

                $messages = [];
                while ($msg = $m_res->fetch_assoc()) {
                    $msg['is_mine'] = ($msg['sender_id'] == $current_user_id);
                    $msg['time_formatted'] = date('h:i A', strtotime($msg['created_at']));
                    $msg['date_formatted'] = date('M d, Y', strtotime($msg['created_at']));
                    $s_role = strtolower($msg['sender_role'] ?? '');
                    if ($s_role === 'staff') {
                        $msg['sender_display'] = (!empty($msg['sender_name']) ? $msg['sender_name'] : 'Staff') . ' (Staff)';
                    } elseif ($s_role === 'admin') {
                        $msg['sender_display'] = (!empty($msg['sender_name']) ? $msg['sender_name'] : 'Admin') . ' (Admin)';
                    } else {
                        $msg['sender_display'] = !empty($msg['sender_name']) ? $msg['sender_name'] : 'Customer';
                    }
                    $messages[] = $msg;
                }

                $unread_count_res = $is_admin
                    ? $conn->query("SELECT COUNT(*) as total FROM messages WHERE (receiver_id = $current_user_id OR receiver_id = 1) AND is_read = 0")
                    : $conn->query("SELECT COUNT(*) as total FROM messages WHERE receiver_id = $current_user_id AND is_read = 0");
                $remaining_unread = $unread_count_res ? (int)($unread_count_res->fetch_assoc()['total'] ?? 0) : 0;

                echo json_encode([
                    'status' => 'success',
                    'success' => true,
                    'has_new' => true,
                    'messages' => $messages,
                    'booking_id' => $booking_id,
                    'remaining_unread' => $remaining_unread
                ]);
                exit();
            }

            // Full load (after_id == 0)
            $b_stmt = $conn->prepare("SELECT b.*, u.full_name as customer_name, u.email as customer_email 
                                      FROM bookings b 
                                      LEFT JOIN users u ON b.user_id = u.user_id 
                                      WHERE b.booking_id = ?");
            $b_stmt->bind_param("i", $booking_id);
            $b_stmt->execute();
            $booking_info = $b_stmt->get_result()->fetch_assoc();

            if (!$booking_info) {
                echo json_encode([
                    'status' => 'error',
                    'success' => false,
                    'message' => 'Booking not found',
                    'error' => 'Booking not found'
                ]);
                exit();
            }

            if (!$is_admin && $booking_info['user_id'] != $current_user_id) {
                echo json_encode([
                    'status' => 'error',
                    'success' => false,
                    'message' => 'Access denied to this booking chat',
                    'error' => 'Access denied'
                ]);
                exit();
            }

            // Mark incoming unread as read
            $mark_stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE booking_id = ? AND receiver_id = ? AND is_read = 0");
            $mark_stmt->bind_param("ii", $booking_id, $current_user_id);
            $mark_stmt->execute();

            $unread_count_res = $is_admin
                ? $conn->query("SELECT COUNT(*) as total FROM messages WHERE (receiver_id = $current_user_id OR receiver_id = 1) AND is_read = 0")
                : $conn->query("SELECT COUNT(*) as total FROM messages WHERE receiver_id = $current_user_id AND is_read = 0");
            $remaining_unread = $unread_count_res ? (int)($unread_count_res->fetch_assoc()['total'] ?? 0) : 0;

            $m_stmt = $conn->prepare("SELECT m.*, u.full_name as sender_name, u.role as sender_role
                                      FROM messages m
                                      LEFT JOIN users u ON m.sender_id = u.user_id
                                      WHERE m.booking_id = ?
                                      ORDER BY m.id ASC");
            $m_stmt->bind_param("i", $booking_id);
            $m_stmt->execute();
            $m_res = $m_stmt->get_result();

            $messages = [];
            while ($msg = $m_res->fetch_assoc()) {
                $msg['is_mine'] = ($msg['sender_id'] == $current_user_id);
                $msg['time_formatted'] = date('h:i A', strtotime($msg['created_at']));
                $msg['date_formatted'] = date('M d, Y', strtotime($msg['created_at']));
                $s_role = strtolower($msg['sender_role'] ?? '');
                if ($s_role === 'staff') {
                    $msg['sender_display'] = (!empty($msg['sender_name']) ? $msg['sender_name'] : 'Staff') . ' (Staff)';
                } elseif ($s_role === 'admin') {
                    $msg['sender_display'] = (!empty($msg['sender_name']) ? $msg['sender_name'] : 'Admin') . ' (Admin)';
                } else {
                    $msg['sender_display'] = !empty($msg['sender_name']) ? $msg['sender_name'] : 'Customer';
                }
                $messages[] = $msg;
            }

            $booking_info['formatted_event_date'] = !empty($booking_info['event_date']) ? date('F d, Y', strtotime($booking_info['event_date'])) : '';

            echo json_encode([
                'status' => 'success',
                'success' => true,
                'booking' => $booking_info,
                'messages' => $messages,
                'has_new' => true,
                'remaining_unread' => $remaining_unread
            ]);
        } catch (Throwable $e) {
            echo json_encode([
                'status' => 'error',
                'success' => false,
                'message' => 'Error loading messages: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ]);
        }
        break;

    case 'send_message':
        $booking_id = intval($jsonData['booking_id'] ?? ($_POST['booking_id'] ?? 0));
        $message_text = trim($jsonData['message'] ?? ($_POST['message'] ?? ''));

        if ($booking_id <= 0) {
            echo json_encode([
                'status' => 'error',
                'success' => false,
                'message' => 'Invalid booking ID',
                'error' => 'Invalid booking ID'
            ]);
            exit();
        }

        try {
            $b_stmt = $conn->prepare("SELECT booking_id, user_id, event_name FROM bookings WHERE booking_id = ?");
            $b_stmt->bind_param("i", $booking_id);
            $b_stmt->execute();
            $b_info = $b_stmt->get_result()->fetch_assoc();

            if (!$b_info) {
                echo json_encode([
                    'status' => 'error',
                    'success' => false,
                    'message' => 'Booking not found',
                    'error' => 'Booking not found'
                ]);
                exit();
            }

            $customer_id = (int)$b_info['user_id'];
            $admin_res = $conn->query("SELECT user_id FROM users WHERE LOWER(role) = 'admin' ORDER BY user_id ASC LIMIT 1");
            $admin_id = ($admin_res && $admin_row = $admin_res->fetch_assoc()) ? (int)$admin_row['user_id'] : 1;

            if ($is_admin) {
                $sender_id = $current_user_id;
                $receiver_id = $customer_id;
            } else {
                if ($current_user_id != $customer_id) {
                    echo json_encode([
                        'status' => 'error',
                        'success' => false,
                        'message' => 'Unauthorized access to this booking chat',
                        'error' => 'Unauthorized'
                    ]);
                    exit();
                }
                $sender_id = $current_user_id;
                $receiver_id = $admin_id;
            }

            $attachment_path = null;
            $file_type = null;

            // Handle file upload
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/../uploads/chat_files/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $file_tmp = $_FILES['attachment']['tmp_name'];
                $file_name = $_FILES['attachment']['name'];
                $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                $allowed_types = [
                    'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'gif' => 'image',
                    'pdf' => 'pdf',
                    'doc' => 'docx', 'docx' => 'docx',
                    'xls' => 'excel', 'xlsx' => 'excel',
                    'txt' => 'txt'
                ];

                if (!isset($allowed_types[$ext])) {
                    echo json_encode([
                        'status' => 'error',
                        'success' => false,
                        'message' => 'Unsupported file extension (.' . $ext . ')',
                        'error' => 'Unsupported file extension'
                    ]);
                    exit();
                }

                $file_type = $allowed_types[$ext];
                $new_filename = 'chat_' . $booking_id . '_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                $destination = $upload_dir . $new_filename;

                if (move_uploaded_file($file_tmp, $destination)) {
                    $attachment_path = 'uploads/chat_files/' . $new_filename;
                } else {
                    echo json_encode([
                        'status' => 'error',
                        'success' => false,
                        'message' => 'Failed to save uploaded file',
                        'error' => 'Failed to save uploaded file'
                    ]);
                    exit();
                }
            }

            if (empty($message_text) && empty($attachment_path)) {
                echo json_encode([
                    'status' => 'error',
                    'success' => false,
                    'message' => 'Message or file attachment is required',
                    'error' => 'Message or file attachment is required'
                ]);
                exit();
            }

            $in_stmt = $conn->prepare("INSERT INTO messages (booking_id, sender_id, receiver_id, message, attachment, file_type, is_read) VALUES (?, ?, ?, ?, ?, ?, 0)");
            $in_stmt->bind_param("iiisss", $booking_id, $sender_id, $receiver_id, $message_text, $attachment_path, $file_type);

            if ($in_stmt->execute()) {
                $msg_id = $conn->insert_id;

                // Send notification to receiver
                if ($is_admin) {
                    if ($user_role === 'staff') {
                        $staff_name = !empty($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Staff';
                        $notif_title = "New Message from {$staff_name} (Staff)";
                    } else {
                        $notif_title = "New Message from Admin";
                    }
                } else {
                    $notif_title = "New Message from Client";
                }
                $notif_content = !empty($message_text) ? mb_strimwidth($message_text, 0, 80, '...') : "Uploaded a file attachment (" . strtoupper($file_type ?? 'FILE') . ")";
                $full_notif_msg = "$notif_title: $notif_content";

                try {
                    $n_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, 0)");
                    if ($n_stmt) {
                        $n_stmt->bind_param("is", $receiver_id, $full_notif_msg);
                        $n_stmt->execute();
                    }
                } catch (Throwable $t) {}

                echo json_encode([
                    'status' => 'success',
                    'success' => true,
                    'message_id' => $msg_id
                ]);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'success' => false,
                    'message' => 'Database insert failed: ' . $conn->error,
                    'error' => 'Database insert failed'
                ]);
            }
        } catch (Throwable $t) {
            echo json_encode([
                'status' => 'error',
                'success' => false,
                'message' => 'Message error: ' . $t->getMessage(),
                'error' => $t->getMessage()
            ]);
        }
        break;

    case 'check_unread':
        $count = 0;
        try {
            if ($is_admin) {
                $res = $conn->query("SELECT COUNT(*) as total FROM messages WHERE (receiver_id = $current_user_id OR receiver_id = 1) AND is_read = 0");
                $count = $res ? ($res->fetch_assoc()['total'] ?? 0) : 0;
            } else {
                $stmt = $conn->prepare("SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND is_read = 0");
                $stmt->bind_param("i", $current_user_id);
                $stmt->execute();
                $count = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
            }
            echo json_encode([
                'status' => 'success',
                'success' => true,
                'unread_count' => $count
            ]);
        } catch (Throwable $t) {
            echo json_encode([
                'status' => 'success',
                'success' => true,
                'unread_count' => 0
            ]);
        }
        break;

    case 'archive_chat':
        if (!$is_admin) {
            echo json_encode(['status' => 'error', 'success' => false, 'error' => 'Access denied']);
            exit();
        }
        $b_id = intval($jsonData['booking_id'] ?? ($_POST['booking_id'] ?? 0));
        try {
            $stmt = $conn->prepare("UPDATE bookings SET admin_archived_chat = 1 WHERE booking_id = ?");
            $stmt->bind_param("i", $b_id);
            $stmt->execute();
            echo json_encode(['status' => 'success', 'success' => true]);
        } catch (Throwable $t) {
            echo json_encode(['status' => 'error', 'success' => false, 'error' => $t->getMessage()]);
        }
        break;

    case 'unarchive_chat':
        if (!$is_admin) {
            echo json_encode(['status' => 'error', 'success' => false, 'error' => 'Access denied']);
            exit();
        }
        $b_id = intval($jsonData['booking_id'] ?? ($_POST['booking_id'] ?? 0));
        try {
            $stmt = $conn->prepare("UPDATE bookings SET admin_archived_chat = 0 WHERE booking_id = ?");
            $stmt->bind_param("i", $b_id);
            $stmt->execute();
            echo json_encode(['status' => 'success', 'success' => true]);
        } catch (Throwable $t) {
            echo json_encode(['status' => 'error', 'success' => false, 'error' => $t->getMessage()]);
        }
        break;

    case 'delete_chat':
        if (!$is_admin) {
            echo json_encode(['status' => 'error', 'success' => false, 'error' => 'Access denied']);
            exit();
        }
        $b_id = intval($jsonData['booking_id'] ?? ($_POST['booking_id'] ?? 0));
        try {
            $stmt = $conn->prepare("DELETE FROM messages WHERE booking_id = ?");
            $stmt->bind_param("i", $b_id);
            $stmt->execute();
            echo json_encode(['status' => 'success', 'success' => true]);
        } catch (Throwable $t) {
            echo json_encode(['status' => 'error', 'success' => false, 'error' => $t->getMessage()]);
        }
        break;

    default:
        echo json_encode([
            'status' => 'error',
            'success' => false,
            'message' => 'Invalid action specified',
            'error' => 'Invalid action'
        ]);
        break;
}
