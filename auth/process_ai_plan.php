<?php
// Prevent any accidental HTML output from warnings/errors
error_reporting(0);
ini_set('display_errors', 0);

require_once '../includes/auth_check.php';
require_once '../db_config.php';
require_once '../config/gemini.php';
require_once '../config/ai_config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $event_desc = trim($_POST['event_desc'] ?? '');
        $pax = intval($_POST['pax'] ?? 50);
        if ($pax <= 0) $pax = 50;
        
        $user_id = $_SESSION['user_id'] ?? 0;
        if (!$user_id) throw new Exception("Unauthorized");

        // Check AI usage limit
        $conn->query("CREATE TABLE IF NOT EXISTS ai_user_limits (
            user_id INT PRIMARY KEY,
            message_count INT DEFAULT 0,
            last_reset TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        
        $stmt_check = $conn->prepare("SELECT message_count FROM ai_user_limits WHERE user_id = ?");
        $stmt_check->bind_param("i", $user_id);
        $stmt_check->execute();
        $res_check = $stmt_check->get_result();
        $message_count = $res_check->fetch_assoc()['message_count'] ?? 0;
        
        if ($message_count >= 10) {
            echo json_encode([
                'status' => 'error',
                'message' => 'AI limit reached. You have used your 10 free AI messages.'
            ]);
            exit;
        }

        // 1. Fetch available menu items from DB for context
        $dishes_query = $conn->query("SELECT m.item_id, m.item_name, c.category_name as category, m.description, m.price FROM menu_items m LEFT JOIN menu_categories c ON m.category_id = c.category_id WHERE m.is_available = 1");
        $available_menu = [];
        while ($d = $dishes_query->fetch_assoc()) { 
            $available_menu[] = [
                'item_id' => $d['item_id'],
                'dish' => $d['item_name'],
                'category' => $d['category'] ?? 'General',
                'description' => $d['description'] ?? '',
                'price' => floatval($d['price'])
            ]; 
        }
        $menu_context_json = json_encode($available_menu);

        $prompt = "Act as JEMmerific AI Companion & World-Class Event Designer. 
Client's Vision: '$event_desc' for $pax guests.
Available Menu Catalog: $menu_context_json.

Generate a structured event plan and layout. Respond strictly with valid JSON only. Do not include markdown code block syntax.

JSON format:
{
  \"event_title_suggestion\": \"Event Title\",
  \"layout_analysis\": \"Analysis of seating, stage, and buffet positioning for $pax pax.\",
  \"layout_design_suggestion\": \"1. Buffet setup along perimeter\\n2. Stage centered\\n3. Guest tables arranged systematically\",
  \"suggested_menu\": [
    {\"item_id\": 1, \"dish\": \"Dish Name\", \"quantity\": $pax, \"reason\": \"Popular crowd favorite\", \"price\": 150}
  ],
  \"estimated_cost\": 45000,
  \"timeline\": \"2 hours setup, 4 hours main event, 1 hour cleanup\",
  \"special_notes\": \"Ensure ample spacing for catering staff and guest flow.\"
}";

        $plan_json = null;
        $active_provider = $AI_CONFIG['ai_provider'] ?? 'auto';
        $provider_used = 'Smart Local Companion';

        // Provider Strategy:
        // If specific provider selected, attempt that first. If it fails or if 'auto', try fallback cascade.

        // Helper function for cURL requests with timeout
        function curl_post_json($url, $payload, $headers = [], $timeout = 4) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($payload) ? $payload : json_encode($payload));
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            if (!empty($headers)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            }
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($curl_error || $http_code >= 400 || empty($response)) {
                return false;
            }
            return $response;
        }

        // Helper function to extract JSON
        function parse_json_from_text($text) {
            if (empty($text)) return null;
            // Clean markdown blocks if present
            $text = preg_replace('/^```json\s*/m', '', $text);
            $text = preg_replace('/^```\s*/m', '', $text);
            preg_match('/\{.*\}/s', $text, $matches);
            if (isset($matches[0])) {
                $decoded = json_decode($matches[0], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }
            }
            return null;
        }

        // --- 0. GROQ CLOUD LPU API (Ultra Fast Llama 3.3 / Qwen) ---
        if (!$plan_json && ($active_provider === 'groq' || $active_provider === 'auto' || !empty($AI_CONFIG['groq_api_key']))) {
            $groq_key = !empty($AI_CONFIG['groq_api_key']) ? $AI_CONFIG['groq_api_key'] : (getenv('GROQ_API_KEY') ?: '');
            $groq_model = $AI_CONFIG['groq_model'] ?? 'qwen-3.6-27b';
            if (!empty($groq_key)) {
                $url = "https://api.groq.com/openai/v1/chat/completions";
                $payload = [
                    "model" => $groq_model,
                    "messages" => [
                        ["role" => "system", "content" => "You are a world-class JSON event planning assistant. Output ONLY valid JSON."],
                        ["role" => "user", "content" => $prompt]
                    ],
                    "temperature" => 0.6,
                    "response_format" => ["type" => "json_object"]
                ];
                $res = curl_post_json($url, $payload, [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $groq_key
                ], 8);
                if ($res) {
                    $resData = json_decode($res, true);
                    $text = $resData['choices'][0]['message']['content'] ?? '';
                    $plan_json = parse_json_from_text($text);
                    if ($plan_json) {
                        $provider_used = "Groq LPU Cloud ($groq_model)";
                    }
                }
            }
        }

        // --- 1. OLLAMA LOCAL MODEL RUNNER (Llama 3 / Qwen) ---
        if ($active_provider === 'ollama' || $active_provider === 'auto') {
            $ollama_url = rtrim($AI_CONFIG['ollama_url'] ?? 'http://localhost:11434', '/');
            $ollama_model = $AI_CONFIG['ollama_model'] ?? 'llama3';
            
            // Call Ollama chat completions endpoint
            $ollama_endpoint = $ollama_url . '/api/chat';
            $ollama_payload = [
                'model' => $ollama_model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpful JSON event planning assistant.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'stream' => false,
                'format' => 'json'
            ];

            $res = curl_post_json($ollama_endpoint, $ollama_payload, ['Content-Type: application/json'], 5);
            if ($res) {
                $resData = json_decode($res, true);
                $content = $resData['message']['content'] ?? '';
                $plan_json = parse_json_from_text($content);
                if ($plan_json) {
                    $provider_used = "Ollama Local ($ollama_model)";
                }
            }
        }

        // --- 2. GOOGLE GEMINI API ---
        if (!$plan_json && ($active_provider === 'gemini' || $active_provider === 'auto')) {
            $gemini_key = !empty($AI_CONFIG['gemini_api_key']) ? $AI_CONFIG['gemini_api_key'] : (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '');
            $gemini_model = $AI_CONFIG['gemini_model'] ?? 'gemini-1.5-flash';
            if (!empty($gemini_key)) {
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$gemini_model}:generateContent?key=" . $gemini_key;
                $payload = [
                    "contents" => [
                        ["parts" => [["text" => $prompt]]]
                    ]
                ];
                $res = curl_post_json($url, $payload, ['Content-Type: application/json'], 5);
                if ($res) {
                    $resData = json_decode($res, true);
                    $text = $resData['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    $plan_json = parse_json_from_text($text);
                    if ($plan_json) {
                        $provider_used = "Google Gemini ($gemini_model)";
                    }
                }
            }
        }

        // --- 3. CHATGPT / OPENAI API ---
        if (!$plan_json && ($active_provider === 'openai' || $active_provider === 'auto')) {
            $openai_key = $AI_CONFIG['openai_api_key'] ?? '';
            $openai_model = $AI_CONFIG['openai_model'] ?? 'gpt-4o-mini';
            if (!empty($openai_key)) {
                $url = "https://api.openai.com/v1/chat/completions";
                $payload = [
                    "model" => $openai_model,
                    "messages" => [
                        ["role" => "system", "content" => "You are a JSON event planning assistant."],
                        ["role" => "user", "content" => $prompt]
                    ],
                    "response_format" => ["type" => "json_object"]
                ];
                $res = curl_post_json($url, $payload, [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $openai_key
                ], 5);
                if ($res) {
                    $resData = json_decode($res, true);
                    $text = $resData['choices'][0]['message']['content'] ?? '';
                    $plan_json = parse_json_from_text($text);
                    if ($plan_json) {
                        $provider_used = "OpenAI ChatGPT ($openai_model)";
                    }
                }
            }
        }

        // --- 4. COPILOT / CUSTOM API ---
        if (!$plan_json && ($active_provider === 'copilot')) {
            $copilot_key = $AI_CONFIG['copilot_api_key'] ?? '';
            $copilot_url = rtrim($AI_CONFIG['copilot_url'] ?? 'https://api.openai.com/v1', '/');
            $copilot_model = $AI_CONFIG['copilot_model'] ?? 'gpt-4o-mini';
            if (!empty($copilot_key)) {
                $url = $copilot_url . '/chat/completions';
                $payload = [
                    "model" => $copilot_model,
                    "messages" => [
                        ["role" => "system", "content" => "You are a JSON event planning assistant."],
                        ["role" => "user", "content" => $prompt]
                    ]
                ];
                $res = curl_post_json($url, $payload, [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $copilot_key
                ], 5);
                if ($res) {
                    $resData = json_decode($res, true);
                    $text = $resData['choices'][0]['message']['content'] ?? '';
                    $plan_json = parse_json_from_text($text);
                    if ($plan_json) {
                        $provider_used = "Copilot Engine ($copilot_model)";
                    }
                }
            }
        }

        // --- 5. SMART OFFLINE COMPANION FALLBACK ENGINE ---
        // Guaranteed zero-error fallback optimized for CPU & Offline operation!
        if (!$plan_json) {
            $provider_used = "JEM Local Smart AI Companion";
            
            // Build dynamic suggested menu from actual DB items
            $suggested_items = [];
            $total_estimated = 0;

            if (!empty($available_menu)) {
                // Select 4 to 6 items from menu
                $sample_keys = array_rand($available_menu, min(5, count($available_menu)));
                if (!is_array($sample_keys)) $sample_keys = [$sample_keys];
                
                foreach ($sample_keys as $k) {
                    $item = $available_menu[$k];
                    $cost_per_item = $item['price'] > 0 ? $item['price'] : 150;
                    $suggested_items[] = [
                        'item_id' => $item['item_id'],
                        'dish' => $item['dish'],
                        'quantity' => $pax,
                        'reason' => 'Curated for ' . ($item['category'] ?? 'Buffet') . ' selection',
                        'price' => $cost_per_item
                    ];
                    $total_estimated += ($cost_per_item * ($pax / 10)); 
                }
            } else {
                $suggested_items = [
                    ['item_id' => 101, 'dish' => 'Braised Beef Roast with Gravy', 'quantity' => $pax, 'reason' => 'Premium main dish', 'price' => 220],
                    ['item_id' => 102, 'dish' => 'Garlic Buttered Chicken & Mushrooms', 'quantity' => $pax, 'reason' => 'Crowd favorite', 'price' => 180],
                    ['item_id' => 103, 'dish' => 'Creamy Carbonara Pasta', 'quantity' => $pax, 'reason' => 'Signature pasta', 'price' => 140],
                    ['item_id' => 104, 'dish' => 'Steam Jasmine Rice', 'quantity' => $pax, 'reason' => 'Unlimited staple', 'price' => 50],
                    ['item_id' => 105, 'dish' => 'Mango Chiffon Cake Slices', 'quantity' => $pax, 'reason' => 'Delicate dessert', 'price' => 90]
                ];
            }

            // Estimate reasonable cost per pax
            $tier_rate = 450;
            if ($pax >= 150) $tier_rate = 400;
            if ($pax >= 200) $tier_rate = 380;
            $estimated_total = max($pax * $tier_rate, $total_estimated * 2.5);

            $tables_needed = ceil($pax / 10);
            $event_type_name = !empty($event_desc) ? ucwords(substr($event_desc, 0, 30)) : 'Special Catering';

            $plan_json = [
                'event_title_suggestion' => "Exclusive $event_type_name Plan ($pax Pax)",
                'layout_analysis' => "Optimized spatial arrangement for $pax guests across $tables_needed round guest tables (10 pax per table). Requires 1 central stage/backdrop area and a dual-sided buffet lineup for smooth flow.",
                'layout_design_suggestion' => "1. Entrance & Registration Table: Positioned 3m from main doorway with welcome signage.\n2. Buffet Lineup: Dual-sided 16ft buffet table along left perimeter with heating chafing dishes.\n3. Guest Seating: $tables_needed round tables evenly spaced with 1.5m clearance aisle.\n4. Stage & Backdrop: Centered at front wall with ambient spotlighting and sound system setup.\n5. Beverage & Dessert Bar: Corner station near entrance for continuous refreshment access.",
                'suggested_menu' => $suggested_items,
                'estimated_cost' => round($estimated_total, 2),
                'timeline' => "08:00 AM - Venue ingress & table arrangement\n10:30 AM - Food temperature prep & chafing setup\n11:30 AM - Guest arrival & welcome drink service\n12:00 PM - Buffet opening\n03:00 PM - Dessert service & egress packup",
                'special_notes' => "Smart local companion generated plan tailored for $pax pax. Includes linen tablecloths, silver utensils, and uniform service crew."
            ];
        }

        // Database Logging
        try {
            $stmt = $conn->prepare("INSERT INTO ai_event_plans (suggested_layout_desc, estimated_cost) VALUES (?, ?)");
            if ($stmt) {
                $layout_desc = $plan_json['layout_design_suggestion'] ?? '';
                $cost = floatval($plan_json['estimated_cost'] ?? 0);
                $stmt->bind_param("sd", $layout_desc, $cost);
                $stmt->execute();
            }
        } catch (\Throwable $e) {
            // Ignore DB log error if table differs
        }
        
        try {
            // Update AI limit count
            $stmt_update = $conn->prepare("INSERT INTO ai_user_limits (user_id, message_count) VALUES (?, 1) ON DUPLICATE KEY UPDATE message_count = message_count + 1");
            if ($stmt_update) {
                $stmt_update->bind_param("i", $user_id);
                $stmt_update->execute();
            }
        } catch (\Throwable $e) {
            // Ignore if error occurs
        }

        echo json_encode([
            'status' => 'success',
            'provider' => $provider_used,
            'parsed' => $plan_json,
            'generated_image' => 'https://images.unsplash.com/photo-1519167758481-83f550bb49b3?q=80&w=1200&auto=format&fit=crop'
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}
?>