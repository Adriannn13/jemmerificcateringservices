<?php
// auth/public_ai_chat.php - Public AI Concierge Endpoint adapted for InfinityFree & Localhost

// Prevent PHP warnings/notices from corrupting the JSON response
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../config/ai_config.php';

// Determine User Identifier (User ID if logged in, otherwise IP address)
$user_id = $_SESSION['user_id'] ?? null;
if ($user_id) {
    $identifier = "user_" . $user_id;
} else {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $identifier = "ip_" . $ip;
}
session_write_close(); // Release session file lock immediately to prevent hanging requests on InfinityFree

// Read frontend payload (supports both JSON php://input and standard FormData)
$rawInput = file_get_contents('php://input');
$jsonData = json_decode($rawInput, true);
if (!is_array($jsonData)) {
    $jsonData = [];
}

$userMessage = trim($jsonData['message'] ?? ($_POST['message'] ?? ''));
$action = trim($_GET['action'] ?? ($jsonData['action'] ?? ($_POST['action'] ?? '')));

// Daily Message Limit (default 10)
$daily_limit = min(10, max(1, intval($AI_CONFIG['daily_message_limit'] ?? 10)));
$current_count = 0;

// Safe Daily Usage Table Check
try {
    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
        $conn->query("CREATE TABLE IF NOT EXISTS chatbot_daily_usage (
            id INT AUTO_INCREMENT PRIMARY KEY,
            identifier VARCHAR(100) NOT NULL UNIQUE,
            message_count INT NOT NULL DEFAULT 0,
            usage_date DATE NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");

        $stmt = $conn->prepare("SELECT message_count, usage_date FROM chatbot_daily_usage WHERE identifier = ?");
        if ($stmt) {
            $stmt->bind_param("s", $identifier);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                if ($row['usage_date'] === date('Y-m-d')) {
                    $current_count = intval($row['message_count']);
                } else {
                    // Reset for new date
                    $reset_stmt = $conn->prepare("UPDATE chatbot_daily_usage SET message_count = 0, usage_date = CURDATE() WHERE identifier = ?");
                    if ($reset_stmt) {
                        $reset_stmt->bind_param("s", $identifier);
                        $reset_stmt->execute();
                    }
                    $current_count = 0;
                }
            }
        }
    }
} catch (Throwable $t) {
    // Graceful fallback if database table cannot be reached
}

$remaining = max(0, $daily_limit - $current_count);

// 0. Handle Limit Check Request (GET or action=check_limit)
if ($_SERVER['REQUEST_METHOD'] === 'GET' || $action === 'check_limit') {
    echo json_encode([
        'status' => 'success',
        'success' => true,
        'count' => $current_count,
        'limit' => $daily_limit,
        'remaining' => $remaining,
        'limit_reached' => ($current_count >= $daily_limit)
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'success' => false,
        'reply' => 'Invalid request method.',
        'error' => 'Invalid request method'
    ]);
    exit();
}

if (empty($userMessage)) {
    echo json_encode([
        'status' => 'error',
        'success' => false,
        'reply' => 'Empty message received.',
        'error' => 'Empty message received'
    ]);
    exit();
}

// 1. Enforce API Key / Message Limit Per User
if ($current_count >= $daily_limit) {
    echo json_encode([
        'status' => 'error',
        'success' => false,
        'error' => "Daily message limit reached ({$current_count}/{$daily_limit} messages today).",
        'reply' => "Daily message limit reached ({$current_count}/{$daily_limit} messages today). Please click 'Get Started' above or come back tomorrow to continue!",
        'remaining' => 0,
        'limit' => $daily_limit,
        'count' => $current_count,
        'limit_reached' => true
    ]);
    exit();
}

// 2. Fetch Live Packages & Menu Catalog Context from DB safely
$pkg_context = "Package A: ₱350/pax, Package B: ₱450/pax, Package C: ₱550/pax, Package D: ₱650/pax";
$menu_context = "Garlic Butter Shrimp, Pork Hamonado, Beef Broccoli, Chicken Cordon Bleu, Carbonara, Baked Mac, Leche Flan";

try {
    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
        $packages_summary = [];
        $pkg_res = $conn->query("SELECT package_name, price_per_pax, main_course_limit, pasta_limit, veggies_limit, dessert_limit FROM packages ORDER BY package_id ASC");
        if ($pkg_res && $pkg_res->num_rows > 0) {
            while ($p = $pkg_res->fetch_assoc()) {
                $packages_summary[] = "• " . $p['package_name'] . ": ₱" . number_format($p['price_per_pax'], 2) . " / head (Limits: " . $p['main_course_limit'] . " Main, " . $p['pasta_limit'] . " Pasta, " . $p['veggies_limit'] . " Veg, " . $p['dessert_limit'] . " Dessert)";
            }
            if (!empty($packages_summary)) {
                $pkg_context = implode("\n", $packages_summary);
            }
        }

        $dishes_summary = [];
        $menu_res = $conn->query("SELECT mi.item_name, mc.category_name FROM menu_items mi JOIN menu_categories mc ON mi.category_id = mc.category_id WHERE mi.is_available = 1 ORDER BY mc.category_name, mi.item_name LIMIT 30");
        if ($menu_res && $menu_res->num_rows > 0) {
            while ($m = $menu_res->fetch_assoc()) {
                $dishes_summary[] = $m['item_name'] . " (" . $m['category_name'] . ")";
            }
            if (!empty($dishes_summary)) {
                $menu_context = implode(", ", $dishes_summary);
            }
        }
    }
} catch (Throwable $t) {
    // Keep standard fallback strings
}

$system_prompt = "You are JEMmerific Assistant, a helpful and friendly AI catering concierge for JEMmerific Catering Services.
Answer customer questions accurately and concisely about catering services, pricing, packages, menu choices, and booking procedures.

OFFICIAL CATERING DETAILS:
- Packages & Rates:
$pkg_context
- Live Available Menu Catalog Sample:
$menu_context
- All packages include complete buffet setup, guest tables & chairs, food warmers, professional waitstaff, and custom event layout.
- Booking Steps: 1) Click 'Get Started' to Register/Login -> 2) Pick event date & pax count -> 3) Select dishes according to package tier -> 4) Upload payment proof -> 5) Track confirmation.

GUIDELINES:
- Keep responses friendly, elegant, clear, and professional.
- Use simple markdown formatting (bullet points, bold text) for readability.
- Keep responses concise (under 150 words).
- Encourage the customer to click 'Get Started' or 'Sign In' to reserve their date.

Customer Question: \"$userMessage\"";

// Function to call AI provider safely on InfinityFree shared hosting
function callExternalAIProvider($prompt, $ai_config) {
    // 1. Try Groq LPU if configured
    $groqKey = $ai_config['groq_api_key'] ?? '';
    $groqModel = $ai_config['groq_model'] ?? 'qwen/qwen3.8-27b';
    if (!empty($groqKey)) {
        try {
            $ch = curl_init("https://api.groq.com/openai/v1/chat/completions");
            $payload = [
                "model" => $groqModel,
                "messages" => [
                    ["role" => "system", "content" => "You are JEMmerific Assistant."],
                    ["role" => "user", "content" => $prompt]
                ],
                "temperature" => 0.7,
                "max_tokens" => 350
            ];
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/json",
                    "Authorization: Bearer " . $groqKey
                ],
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response && $httpCode === 200) {
                $result = json_decode($response, true);
                $content = $result['choices'][0]['message']['content'] ?? '';
                if (!empty($content)) {
                    return ['success' => true, 'reply' => trim($content)];
                }
            }
        } catch (Throwable $t) {}
    }

    // 2. Try Gemini API if configured
    $geminiKey = $ai_config['gemini_api_key'] ?? '';
    $geminiModel = $ai_config['gemini_model'] ?? 'gemini-3.5-flash';
    if (!empty($geminiKey)) {
        try {
            $geminiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$geminiModel}:generateContent?key=" . $geminiKey;
            $payload = [
                "contents" => [
                    ["parts" => [["text" => $prompt]]]
                ],
                "generationConfig" => [
                    "maxOutputTokens" => 350,
                    "temperature" => 0.7
                ]
            ];
            $ch = curl_init($geminiUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response && $httpCode === 200) {
                $result = json_decode($response, true);
                $content = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
                if (!empty($content)) {
                    return ['success' => true, 'reply' => trim($content)];
                }
            }
        } catch (Throwable $t) {}
    }

    // 3. Try OpenAI API if configured
    $openAiKey = $ai_config['openai_api_key'] ?? '';
    if (!empty($openAiKey)) {
        try {
            $url = "https://api.openai.com/v1/chat/completions";
            $payload = [
                "model" => $ai_config['openai_model'] ?? "gpt-3.5-turbo",
                "messages" => [
                    ["role" => "system", "content" => "You are JEMmerific Assistant."],
                    ["role" => "user", "content" => $prompt]
                ]
            ];
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/json",
                    "Authorization: Bearer " . $openAiKey
                ],
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response && $httpCode === 200) {
                $result = json_decode($response, true);
                $content = $result['choices'][0]['message']['content'] ?? '';
                if (!empty($content)) {
                    return ['success' => true, 'reply' => trim($content)];
                }
            }
        } catch (Throwable $t) {}
    }

    return ['success' => false, 'error' => 'External AI unreachable'];
}

$aiResult = callExternalAIProvider($system_prompt, $AI_CONFIG);
$reply = $aiResult['success'] ? $aiResult['reply'] : null;

// Graceful Local Fallback Engine (Guarantees fast response even if free host blocks external curl)
if (empty($reply)) {
    $msg_lower = strtolower($userMessage);
    if (strpos($msg_lower, 'package') !== false || strpos($msg_lower, 'tier') !== false || strpos($msg_lower, 'price') !== false || strpos($msg_lower, 'cost') !== false || strpos($msg_lower, 'rate') !== false || strpos($msg_lower, 'how much') !== false) {
        $reply = "We offer **4 Catering Package Tiers** tailored for every event:\n\n" .
                 "• **Package A (₱350/pax)**: 2 Mains, 1 Pasta, 1 Veggie, 1 Dessert\n" .
                 "• **Package B (₱450/pax)**: 3 Mains, 1 Pasta, 1 Veggie, 1 Dessert\n" .
                 "• **Package C (₱550/pax)**: 4 Mains, 1 Pasta, 1 Veggie, 2 Desserts\n" .
                 "• **Package D (₱650/pax)**: 5 Mains, 1 Pasta, 1 Veggie, 2 Desserts\n\n" .
                 "All packages include complete buffet setup, guest tables & chairs, food warmers, and professional waitstaff! Click **Get Started** to customize your event.";
    } elseif (strpos($msg_lower, 'menu') !== false || strpos($msg_lower, 'dish') !== false || strpos($msg_lower, 'food') !== false || strpos($msg_lower, 'item') !== false) {
        $reply = "Our menu features delicious Filipino classics & international favorites! Some popular options include:\n\n" .
                 "• **Main Courses**: Garlic Butter Shrimp, Beef Broccoli, Pork Hamonado, Chicken Cordon Bleu\n" .
                 "• **Pastas**: Carbonara, Baked Macaroni, Spaghetti\n" .
                 "• **Desserts**: Leche Flan, Mango Graham, Buko Pandan\n\n" .
                 "You can pick your exact dishes after selecting your package tier!";
    } elseif (strpos($msg_lower, 'book') !== false || strpos($msg_lower, 'reserve') !== false || strpos($msg_lower, 'how to') !== false || strpos($msg_lower, 'order') !== false) {
        $reply = "Booking with JEMmerific is simple:\n\n" .
                 "1. Click **Get Started** to create your account or Sign In.\n" .
                 "2. Select your event date, time, and estimated guest count.\n" .
                 "3. Pick your desired package tier & custom dishes.\n" .
                 "4. Submit your downpayment proof to secure your date!\n\n" .
                 "Our team verifies your reservation immediately once submitted.";
    } elseif (strpos($msg_lower, 'hello') !== false || strpos($msg_lower, 'hi') !== false || strpos($msg_lower, 'hey') !== false) {
        $reply = "Hello! 👋 Welcome to **JEMmerific Catering Services**! I am your AI Assistant. How can I assist you with your upcoming event or catering questions today?";
    } else {
        $reply = "Thank you for reaching out! At **JEMmerific Catering Services**, we engineer memorable celebrations with authentic flavors, complete buffet setups, dining tables, food warmers, and staff included.\n\n" .
                 "Feel free to ask me about our **Packages (Package A to D)**, **Menu Items**, or how to **Reserve your Event Date**!";
    }
}

// Increment Message Count safely
$new_count = $current_count + 1;
try {
    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
        $upsert_stmt = $conn->prepare("INSERT INTO chatbot_daily_usage (identifier, message_count, usage_date) VALUES (?, ?, CURDATE()) ON DUPLICATE KEY UPDATE message_count = ?, usage_date = CURDATE()");
        if ($upsert_stmt) {
            $upsert_stmt->bind_param("sii", $identifier, $new_count, $new_count);
            $upsert_stmt->execute();
        }
    }
} catch (Throwable $t) {}

$remaining = max(0, $daily_limit - $new_count);

// Send response formatted to support both the user's snippet and the frontend
echo json_encode([
    'status' => 'success',
    'success' => true,
    'reply' => $reply,
    'count' => $new_count,
    'limit' => $daily_limit,
    'remaining' => $remaining,
    'limit_reached' => ($new_count >= $daily_limit)
]);
exit;
