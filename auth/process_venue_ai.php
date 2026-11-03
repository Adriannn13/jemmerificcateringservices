<?php
// auth/process_venue_ai.php
// AI Venue & Conceptual Catering Setup Engine
// Implements Google AI Two-Step Staging Pipeline:
// Step 1: gemini-2.5-flash enriches venue, theme, pax, and color inputs into a photographic staging prompt
// Step 2: imagen-3.0-generate-002 generates high-resolution 16:9 visualization with negative prompting

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ini_set('display_errors', 0);
error_reporting(E_ALL);
set_time_limit(180);
ini_set('memory_limit', '256M');

header('Content-Type: application/json');

require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../config/gemini.php';
require_once __DIR__ . '/../config/ai_config.php';
require_once __DIR__ . '/../includes/package_helper.php';
require_once __DIR__ . '/../includes/PollinationsImageEngine.php';
require_once __DIR__ . '/../includes/staff_helper.php';
require_once __DIR__ . '/migrate_venue_ai.php'; // Ensure DB columns exist

ensure_staff_schema($conn);

if (!isset($_SESSION['logged_in']) || empty($_SESSION['role'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in.']);
    exit();
}

$user_role = strtolower($_SESSION['role']);
$is_admin  = ($user_role === 'admin');
$is_staff  = ($user_role === 'staff');

if (!$is_admin && !$is_staff) {
    echo json_encode(['success' => false, 'message' => 'Access denied: Administrator or Staff permissions required.']);
    exit();
}

// If staff, verify staff has ai_venue_setup or booking_requests permission
if ($is_staff) {
    $has_perm = staff_has_permission('ai_venue_setup', $conn) || staff_has_permission('booking_requests', $conn);
    if (!$has_perm) {
        echo json_encode(['success' => false, 'message' => 'Access denied: You do not have permission for AI Venue & Conceptual Setup.']);
        exit();
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$booking_id = intval($_POST['booking_id'] ?? $_GET['booking_id'] ?? 0);

if ($booking_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
    exit();
}

// -------------------------------------------------------------
// Helper: Secure Server cURL Request
// -------------------------------------------------------------
function send_curl_request($url, $payload, $headers = [], $timeout = 25) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($payload) ? $payload : json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error || $http_code >= 400 || empty($response)) {
        return false;
    }
    return $response;
}

// Helper: Extract JSON from text response
function extract_json_payload($text) {
    if (empty($text)) return null;
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

// -------------------------------------------------------------
// STEP 1: Two-Step Pipeline - Prompt Enrichment via Fast Text Model (gemini-2.5-flash)
// Converts simple form inputs (venue type, theme, guest count, color scheme)
// and venue photo into an architectural, photographic staging prompt
// -------------------------------------------------------------
function enrich_catering_staging_prompt($inputs, $venue_img_path = '', $api_keys = []) {
    $venue_type   = !empty($inputs['venue_type']) ? $inputs['venue_type'] : 'Event Venue';
    $theme        = !empty($inputs['theme']) ? $inputs['theme'] : 'Modern Elegant';
    $pax          = !empty($inputs['pax_count']) ? intval($inputs['pax_count']) : 100;
    $color_scheme = !empty($inputs['color_scheme']) ? $inputs['color_scheme'] : 'Champagne Gold, Ivory, and Warm Amber';
    $event_type   = !empty($inputs['event_type']) ? $inputs['event_type'] : 'Catering Event';
    $custom_req   = !empty($inputs['admin_prompt']) ? $inputs['admin_prompt'] : '';

    $tables_count = max(1, (int)ceil($pax / 10));

    // Construct prompt enrichment instruction
    $enrich_instruction = "You are an expert luxury catering director and architectural event staging photographer.
Convert these event inputs into a detailed, photorealistic photographic staging prompt for high-resolution image generation:
- Event: {$event_type}
- Venue Type: {$venue_type}
- Staging Theme: {$theme}
- Guest Count: {$pax} Pax ({$tables_count} round 10-seater banquet tables + 1 VIP long presidential table)
- Color Scheme / Palette: {$color_scheme}
- Buffet Setup: Dual-sided perimeter buffet lines with mirror-polished roll-top silver chafing dishes and gourmet culinary presentation
- Decor & Floral Styling: Tall cascading floral centerpieces matching the {$color_scheme} palette, elegant table runners, fine crystal glassware, polished silverware, and ambient lighting
" . (!empty($custom_req) ? "- Special Client/Admin Requirements: {$custom_req}\n" : "") . "

REQUIREMENTS FOR THE PROMPT:
1. Describe the scene as a wide-angle 16:9 panoramic photograph taken with a 35mm lens.
2. If a venue photo is provided, KEEP THE ROOM ARCHITECTURE, BACKGROUND, AND FLOOR COMPLETELY INTACT, seamlessly staging the banquet tables and buffet lines directly on the venue floor.
3. Detail the luxury linens, chair styling (e.g. Chiavari or banquet chairs), floral centerpieces, warm celebration lighting, and perimeter food stations.
4. Specify high-end catering elements: porcelain dinnerware, cloth napkins, stainless roll-top chafing dishes, and immaculate presentation.
5. Do NOT include markdown formatting, bullet points, or conversational filler. Output ONLY the single enriched photographic prompt text.";

    $has_photo = (!empty($venue_img_path) && file_exists($venue_img_path));
    $mime_type = $has_photo ? (mime_content_type($venue_img_path) ?: 'image/jpeg') : '';
    $img_b64   = ($has_photo && filesize($venue_img_path) < 4194304) ? base64_encode(file_get_contents($venue_img_path)) : '';

    // Fast text models in prioritized order (gemini-3.5-flash-lite executes under 1s with high reliability)
    $text_models = [
        'gemini-3.5-flash-lite',
        'gemini-3.8-flash',
        defined('GEMINI_TEXT_MODEL') ? GEMINI_TEXT_MODEL : 'gemini-2.5-flash'
    ];
    $text_models = array_values(array_unique(array_filter($text_models)));

    foreach ($api_keys as $key) {
        if (empty($key)) continue;
        foreach ($text_models as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";

            $parts = [["text" => $enrich_instruction]];
            if ($has_photo && !empty($img_b64)) {
                $parts[] = [
                    "inline_data" => [
                        "mime_type" => $mime_type,
                        "data" => $img_b64
                    ]
                ];
            }

            $payload = [
                "contents" => [["parts" => $parts]],
                "generationConfig" => [
                    "temperature" => 0.25,
                    "maxOutputTokens" => 500
                ]
            ];

            $res = send_curl_request($url, $payload, ['Content-Type: application/json'], 5);
            if ($res) {
                $dec = json_decode($res, true);
                $cand = $dec['candidates'][0]['content']['parts'][0]['text'] ?? '';
                if (!empty($cand) && strlen(trim($cand)) > 50) {
                    return trim($cand);
                }
            }
        }
    }

    // High quality fallback prompt if API text call fails
    return "Wide-angle 16:9 architectural photograph of a luxury {$theme} {$event_type} catering staging inside a {$venue_type}. Featuring {$tables_count} impeccably draped 10-seater round banquet tables with {$color_scheme} premium linens, elegant Chiavari chairs, and lush tall floral centerpieces. Dual-sided perimeter buffet line with mirror-polished roll-top silver chafing dishes, warm ambient lighting, crystal stemware, and photorealistic catering presentation.";
}

// -------------------------------------------------------------
// STEP 2: Two-Step Pipeline - Image Generation Engine
// Primary: Pollinations.ai with Secure Auth Key (Flux • Unlimited & Fast)
// Backup: Google AI Studio Imagen 3 / Gemini
// -------------------------------------------------------------

function generate_catering_image_via_pollinations($prompt, $options = []) {
    $aspect_ratio     = $options['aspect_ratio'] ?? (defined('GEMINI_ASPECT_RATIO') ? GEMINI_ASPECT_RATIO : '16:9');
    $pollinations_key = $options['pollinations_api_key'] ?? (defined('POLLINATIONS_API_KEY') ? POLLINATIONS_API_KEY : (getenv('POLLINATIONS_API_KEY') ?: ''));
    $model            = $options['model'] ?? (defined('POLLINATIONS_MODEL') ? POLLINATIONS_MODEL : 'flux');

    // Dimension mapping based on aspect ratio
    $width = 1280;
    $height = 720;
    if ($aspect_ratio === '4:3') {
        $width = 1024;
        $height = 768;
    } elseif ($aspect_ratio === '1:1') {
        $width = 1024;
        $height = 1024;
    }

    $engine = new PollinationsImageEngine($pollinations_key, 45);
    $rawImage = $engine->generateVenueImage($prompt, $model, $width, $height, $options);

    if ($rawImage && strlen($rawImage) > 5000) {
        $usedModelId = $engine->getLastUsedModel();
        $modelLabel = PollinationsImageEngine::getModelLabel($usedModelId);
        return [
            'success' => true,
            'image_bytes' => $rawImage,
            'engine' => "Pollinations.ai ({$modelLabel} • {$aspect_ratio})"
        ];
    }

    return [
        'success' => false,
        'error' => $engine->getLastError() ?: 'Pollinations.ai image generation failed.'
    ];
}

function generate_catering_image_via_google_ai($prompt, $api_keys = [], $options = []) {
    $aspect_ratio    = $options['aspect_ratio'] ?? (defined('GEMINI_ASPECT_RATIO') ? GEMINI_ASPECT_RATIO : '16:9');
    $negative_prompt = $options['negative_prompt'] ?? (defined('GEMINI_NEGATIVE_PROMPT') ? GEMINI_NEGATIVE_PROMPT : 'blurry, deformed food, extra limbs, plastic disposable plates, messy counters, distorted furniture, cartoon, low quality, oversaturated');

    $target_image_model = $options['model'] ?? (defined('GEMINI_IMAGE_MODEL') ? GEMINI_IMAGE_MODEL : 'imagen-3.0-generate-002');

    // 1. Primary Google: Google Imagen 3 Model (imagen-3.0-generate-002)
    foreach ($api_keys as $key) {
        if (empty($key)) continue;

        $imagen_url = "https://generativelanguage.googleapis.com/v1beta/models/{$target_image_model}:predict?key={$key}";
        $imagen_payload = [
            "instances" => [
                ["prompt" => $prompt]
            ],
            "parameters" => [
                "sampleCount" => 1,
                "aspectRatio" => $aspect_ratio,
                "negativePrompt" => $negative_prompt
            ]
        ];

        $ch = curl_init($imagen_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($imagen_payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $key
            ],
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $res = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200 && $res) {
            $data = json_decode($res, true);
            if (!empty($data['predictions'][0]['bytesBase64Encoded'])) {
                $img_bytes = base64_decode($data['predictions'][0]['bytesBase64Encoded']);
                if ($img_bytes && strlen($img_bytes) > 5000) {
                    return [
                        'success' => true,
                        'image_bytes' => $img_bytes,
                        'engine' => "Google Imagen 3 ({$target_image_model} • {$aspect_ratio})"
                    ];
                }
            }
        }
    }

    // 2. Secondary Google: Gemini Native Multimodal Image Generation Models
    $gemini_img_models = ['gemini-2.5-flash-image', 'gemini-3.1-flash-image'];

    $has_venue = !empty($options['venue_img_path']) && file_exists($options['venue_img_path']);
    $venue_b64 = ($has_venue && filesize($options['venue_img_path']) < 4194304) ? base64_encode(file_get_contents($options['venue_img_path'])) : '';
    $venue_mime = $has_venue ? (mime_content_type($options['venue_img_path']) ?: 'image/jpeg') : '';

    $gemini_prompt = "Generate a wide-angle {$aspect_ratio} photorealistic catering staging visualization. "
                   . ($has_venue ? "Keep the customer venue architecture, ceiling, floor, and structural backdrop shown in the attached venue photo 100% intact, and stage the catering setup seamlessly inside it. " : "")
                   . "Staging specification: {$prompt}. Strict exclusions: {$negative_prompt}.";

    foreach ($api_keys as $key) {
        if (empty($key)) continue;
        foreach ($gemini_img_models as $g_model) {
            $g_url = "https://generativelanguage.googleapis.com/v1beta/models/{$g_model}:generateContent?key={$key}";

            $parts = [["text" => $gemini_prompt]];
            if ($has_venue && !empty($venue_b64)) {
                $parts[] = [
                    "inline_data" => [
                        "mime_type" => $venue_mime,
                        "data" => $venue_b64
                    ]
                ];
            }

            $g_payload = [
                "contents" => [["parts" => $parts]]
            ];

            $ch = curl_init($g_url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($g_payload),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            $res = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code === 200 && $res) {
                $d = json_decode($res, true);
                $parts = $d['candidates'][0]['content']['parts'] ?? [];
                foreach ($parts as $p) {
                    $b64 = $p['inlineData']['data'] ?? $p['inline_data']['data'] ?? null;
                    if (!empty($b64)) {
                        $img_bytes = base64_decode($b64);
                        if ($img_bytes && strlen($img_bytes) > 5000) {
                            return [
                                'success' => true,
                                'image_bytes' => $img_bytes,
                                'engine' => "Google Gemini AI ({$g_model} • {$aspect_ratio})"
                            ];
                        }
                    }
                }
            } elseif ($http_code === 429 || $http_code === 403) {
                break 2;
            }
        }
    }

    return [
        'success' => false,
        'error' => 'Google AI Image model quota exceeded or model unavailable.'
    ];
}

// Unified Staged Image Generator
function generate_catering_staged_image($prompt, $api_keys = [], $options = []) {
    $provider = defined('IMAGE_PROVIDER') ? IMAGE_PROVIDER : 'pollinations';

    // 1. Try Pollinations.ai first if configured as primary (Default)
    if ($provider === 'pollinations' || empty($provider)) {
        $p_res = generate_catering_image_via_pollinations($prompt, $options);
        if (!empty($p_res['success'])) {
            return $p_res;
        }
    }

    // 2. Fallback to Google AI
    $g_res = generate_catering_image_via_google_ai($prompt, $api_keys, $options);
    if (!empty($g_res['success'])) {
        return $g_res;
    }

    // 3. If Google was primary and failed, try Pollinations as backup
    if ($provider === 'gemini') {
        $p_res = generate_catering_image_via_pollinations($prompt, $options);
        if (!empty($p_res['success'])) {
            return $p_res;
        }
    }

    return [
        'success' => false,
        'error' => $p_res['error'] ?? $g_res['error'] ?? 'Image generation failed across providers.'
    ];
}

// -------------------------------------------------------------
// Helper: Generate Rich AI Catering Package & Staging Summary
// -------------------------------------------------------------
function generate_catering_package_summary($conn, $booking_id, $booking, $package_info, $vision_data, $admin_prompt = '') {
    $pax = intval($booking['pax_count'] ?? 100);
    $package_name = $package_info['package_name'] ?? 'Premium Catering Package';
    $theme = $package_info['theme'] ?? 'Modern Elegant';
    $event_name = $booking['event_name'] ?? 'Catering Event';
    $event_type = $booking['event_type'] ?? 'Special Event';
    $venue_location = $booking['venue_location'] ?? 'Event Venue';
    $total_amount = floatval($booking['total_amount'] ?? 0);

    // Calculate Tables
    $round_tables_count = max(1, (int)ceil($pax / 10));
    $buffet_text = ($pax >= 100) 
        ? "2 Twin-Line Buffet Stations (positioned on perimeter wings for fluid two-way line queue)"
        : "1 Centralized Dual-Sided Buffet Station with Roll-Top Stainless Chafing Dishes";

    // Fetch Categorized Menu Selection
    $menu_items = [];
    $menu_by_category = [];
    $m_query = $conn->query("SELECT mi.item_name, COALESCE(mc.category_name, 'Main Course') as category_name, bi.quantity 
                            FROM booking_items bi 
                            JOIN menu_items mi ON bi.item_id = mi.item_id 
                            LEFT JOIN menu_categories mc ON mi.category_id = mc.category_id 
                            WHERE bi.booking_id = $booking_id ORDER BY mc.category_name ASC, mi.item_name ASC");
    if ($m_query && $m_query->num_rows > 0) {
        while ($mr = $m_query->fetch_assoc()) {
            $cat = $mr['category_name'];
            $menu_by_category[$cat][] = $mr['item_name'];
            $menu_items[] = $mr['item_name'];
        }
    } else {
        $raw_menu = explode(', ', $package_info['menu'] ?? 'Full Course Catering Buffet');
        $menu_by_category['Selected Menu'] = $raw_menu;
        $menu_items = $raw_menu;
    }

    $v_type = $vision_data['venue_type'] ?? 'Event Venue';
    $lighting = $vision_data['lighting'] ?? 'Warm ambient celebration lighting';

    $staging_text = !empty($admin_prompt) 
        ? "Custom Staging Concept: " . $admin_prompt 
        : "Tailored to {$v_type}: perimeter buffet lines preserve floor area for {$round_tables_count} guest tables, center aisle, and clear stage visibility with {$lighting}.";

    // Build structured summary
    $summary = [
        'package_name'        => $package_name,
        'event_name'          => $event_name,
        'event_type'          => $event_type,
        'pax_count'           => $pax,
        'total_amount'        => $total_amount,
        'formatted_amount'    => '₱' . number_format($total_amount, 2),
        'theme'               => $theme,
        'venue_location'      => $venue_location,
        'suggested_tables'    => [
            'guest_tables'     => "{$round_tables_count} Round Guest Tables (10 guests per table with premium {$theme} linens & centerpieces)",
            'guest_table_count'=> $round_tables_count,
            'head_table'       => "1 VIP / Presidential Long Table (seats 10-12 guests, elevated or facing main stage)",
            'buffet_station'   => $buffet_text,
            'special_stations' => "1 Cake / Gift Display Station, 1 Registration / Reception Welcome Desk"
        ],
        'menu_by_category'    => $menu_by_category,
        'menu_list'           => $menu_items,
        'staging_notes'       => $staging_text,
        'admin_prompt_used'   => $admin_prompt
    ];

    // Try Gemini API to synthesize an executive AI catering summary
    $gemini_keys = function_exists('get_gemini_api_keys') ? get_gemini_api_keys() : [];
    $ai_narrative = '';
    
    if (!empty($gemini_keys)) {
        $menu_summary_str = '';
        foreach ($menu_by_category as $cat => $items) {
            $menu_summary_str .= "• {$cat}: " . implode(', ', $items) . "\n";
        }

        $prompt_ai = "You are an executive catering planner for JEMmerific Catering. Provide a professional, crisp 3-point catering summary for:
Event: {$event_name} ({$event_type})
Venue: {$venue_location} ({$v_type})
Package: {$package_name} ({$summary['formatted_amount']})
Pax: {$pax} Pax
Tables: {$round_tables_count} Round Tables (10 pax) + 1 Head Table + {$buffet_text}
Menu:
{$menu_summary_str}
Admin/Staging Directive: {$admin_prompt}

Output format:
• Package & Guest Capacity: [1 sentence summary]
• Menu Highlights & Table Staging: [1-2 sentences on tables, buffet, and menu dishes]
• Venue Concept & Ambience: [1 sentence on venue integration]";

        $summary_models = ['gemini-3.5-flash-lite', 'gemini-3.8-flash', 'gemini-2.5-flash'];
        foreach ($gemini_keys as $k) {
            foreach ($summary_models as $sm) {
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$sm}:generateContent?key={$k}";
                $res = send_curl_request($url, [
                    "contents" => [["parts" => [["text" => $prompt_ai]]]]
                ], ['Content-Type: application/json'], 4);
                if ($res) {
                    $dec = json_decode($res, true);
                    $cand = $dec['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    if (!empty($cand)) {
                        $ai_narrative = trim($cand);
                        break 2;
                    }
                }
            }
        }
    }

    if (empty($ai_narrative)) {
        $dishes_sample = implode(', ', array_slice($menu_items, 0, 4));
        $ai_narrative = "• Package & Guest Capacity: Customer availed {$package_name} for {$pax} guests ({$summary['formatted_amount']}) styled in {$theme}.\n"
                      . "• Table Staging & Menu: Staging includes {$round_tables_count} round 10-seater banquet tables, 1 VIP presidential table, and {$buffet_text}. Serving " . count($menu_items) . " dishes including {$dishes_sample}.\n"
                      . "• Venue Concept: " . $staging_text;
    }

    $summary['executive_narrative'] = $ai_narrative;
    return $summary;
}

// -------------------------------------------------------------
// Helper: Analyze venue image using Gemini Vision with Layout Preservation
// -------------------------------------------------------------
function analyze_venue_with_gemini_vision($image_full_path, $booking, $api_keys, $conn = null, $booking_id = 0) {
    if (!file_exists($image_full_path)) return null;

    $image_data = base64_encode(file_get_contents($image_full_path));
    $mime_type = mime_content_type($image_full_path) ?: 'image/jpeg';

    $vision_prompt = 'You are a professional architectural event staging and catering venue analyst for JEMmerific Catering.
Analyze this customer-uploaded event venue photo with extreme detail and precision.
Determine whether the venue is OUTDOOR (open-air rooftop, lawn, garden, patio) or INDOOR (banquet hall, gymnasium, ballroom, pavilion).

CRITICAL ARCHITECTURAL PRESERVATION INSTRUCTIONS:
1. Identify the exact background, architectural features, walls/skyline, and flooring material.
2. Formulate a crisp, photorealistic staging prompt (60-90 words) preserving the exact venue architecture and floor, detailing round 10-seater banquet tables, elegant linens, floral centerpieces, and perimeter buffet lines with silver chafing dishes.

Return strictly valid JSON only (no markdown backticks, no code blocks):
{
  "venue_type": "Descriptive classification (e.g. Modern Indoor Banquet Hall or Outdoor Open-Air Terrace)",
  "indoor": true,
  "estimated_capacity": "100 - 150 Pax",
  "wall_characteristics": "Exact perimeter walls, ledges, or backdrop buildings/sky",
  "ceiling_type": "Ceiling fixture style or open sky",
  "floor_type": "Flooring material (e.g. Polished concrete, hardwood, or turf)",
  "lighting": "Natural daylight, ambient fixtures, or warm festive string lights",
  "architectural_features": "Key architectural features visible in photo",
  "spatial_layout": "Open floor layout with round banquet tables placed on main floor and perimeter buffet stations",
  "accurate_staging_prompt": "Photorealistic 16:9 view preserving exact venue architecture and floor. Staging round 10-seater banquet tables draped in champagne linens, Chiavari chairs, floral centerpieces, and perimeter buffet lines with mirror-polished silver chafing dishes."
}';

    $vision_models = ['gemini-3.5-flash-lite', 'gemini-3.8-flash', 'gemini-2.5-flash'];
    $analysis_result = null;

    foreach ($api_keys as $key) {
        if (empty($key)) continue;
        foreach ($vision_models as $v_model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$v_model}:generateContent?key={$key}";
            $payload = [
                "contents" => [
                    [
                        "parts" => [
                            ["text" => $vision_prompt],
                            ["inline_data" => ["mime_type" => $mime_type, "data" => $image_data]]
                        ]
                    ]
                ],
                "generationConfig" => ["temperature" => 0.1, "responseMimeType" => "application/json"]
            ];

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_TIMEOUT => 20,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json']
            ]);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code === 200 && $res) {
                $d = json_decode($res, true);
                $cand = $d['candidates'][0]['content']['parts'][0]['text'] ?? '';
                if (!empty($cand)) {
                    $cand = preg_replace('/^```json\s*/m', '', $cand);
                    $cand = preg_replace('/^```\s*/m', '', $cand);
                    $parsed = json_decode(trim($cand), true);
                    if ($parsed && !empty($parsed['venue_type'])) {
                        $analysis_result = $parsed;
                        break 2;
                    }
                }
            }
        }
    }

    if (!$analysis_result) {
        $venue_name = $booking['venue_location'] ?? 'Event Venue';
        $analysis_result = [
            "venue_type" => "Event Venue (" . $venue_name . ")",
            "indoor" => true,
            "estimated_capacity" => ($booking['pax_count'] ?? 100) . " - " . (($booking['pax_count'] ?? 100) + 50) . " Pax",
            "wall_characteristics" => "Neutral walls with ambient fixtures",
            "ceiling_type" => "High ceiling with ambient fixtures",
            "floor_type" => "Polished event flooring",
            "lighting" => "Warm celebration lighting",
            "architectural_features" => "Spacious event floor",
            "spatial_layout" => "Round banquet tables with perimeter buffet stations",
            "accurate_staging_prompt" => "Keep the current image intact, preserving the exact background, architecture, and flooring. Stage round 10-seater banquet tables draped in champagne linens, gold Chiavari chairs, elegant tall floral centerpieces, and perimeter buffet lines with silver chafing dishes."
        ];
    }

    if ($conn && $booking_id > 0) {
        $analysis_json = json_encode($analysis_result);
        $update_stmt = $conn->prepare("UPDATE bookings SET ai_venue_analysis = ? WHERE booking_id = ?");
        $update_stmt->bind_param("si", $analysis_json, $booking_id);
        $update_stmt->execute();
    }

    return $analysis_result;
}

// -------------------------------------------------------------
// Action 1: Upload Venue Image
// -------------------------------------------------------------
if ($action === 'upload_venue') {
    if (!isset($_FILES['venue_image']) || $_FILES['venue_image']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
        exit();
    }

    $file = $_FILES['venue_image'];
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file format. Allowed: JPG, PNG, WEBP']);
        exit();
    }

    $filename = "venue_" . $booking_id . "_" . time() . "." . $ext;
    $target_dir = __DIR__ . "/../uploads/";
    $target_path = $target_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        $rel_path = "uploads/" . $filename;
        $stmt = $conn->prepare("UPDATE bookings SET venue_image = ? WHERE booking_id = ?");
        $stmt->bind_param("si", $rel_path, $booking_id);
        $stmt->execute();

        // Immediately analyze new venue image with Gemini Vision
        $stmt_b = $conn->prepare("SELECT * FROM bookings WHERE booking_id = ?");
        $stmt_b->bind_param("i", $booking_id);
        $stmt_b->execute();
        $b_data = $stmt_b->get_result()->fetch_assoc() ?: [];

        $api_keys = function_exists('get_gemini_api_keys') ? get_gemini_api_keys() : [GEMINI_API_KEY];
        $analysis = analyze_venue_with_gemini_vision($target_path, $b_data, $api_keys, $conn, $booking_id);

        echo json_encode([
            'success' => true,
            'message' => 'Venue photo uploaded & analyzed successfully with Google AI!',
            'venue_image' => $rel_path,
            'analysis' => $analysis,
            'prompt' => $analysis['accurate_staging_prompt'] ?? ''
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save venue image']);
    }
    exit();
}

// -------------------------------------------------------------
// Action 2: Analyze Venue Image with Gemini Vision API
// -------------------------------------------------------------
if ($action === 'analyze_venue') {
    $stmt = $conn->prepare("SELECT venue_image, event_type, venue_location, pax_count FROM bookings WHERE booking_id = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();

    if (!$booking || empty($booking['venue_image'])) {
        echo json_encode(['success' => false, 'message' => 'Please upload a venue image first before running vision analysis']);
        exit();
    }

    $image_full_path = __DIR__ . "/../" . $booking['venue_image'];
    if (!file_exists($image_full_path)) {
        echo json_encode(['success' => false, 'message' => 'Uploaded venue image file not found on server']);
        exit();
    }

    $api_keys = function_exists('get_gemini_api_keys') ? get_gemini_api_keys() : [GEMINI_API_KEY];
    $analysis_result = analyze_venue_with_gemini_vision($image_full_path, $booking, $api_keys, $conn, $booking_id);

    echo json_encode([
        'success' => true,
        'message' => 'Venue vision analysis completed successfully',
        'analysis' => $analysis_result,
        'prompt' => $analysis_result['accurate_staging_prompt'] ?? ''
    ]);
    exit();
}

// -------------------------------------------------------------
// Action 2.5: Auto-Craft Venue-Anchored Staging Prompt via Gemini Fast Text Model
// -------------------------------------------------------------
if ($action === 'craft_venue_prompt') {
    $stmt = $conn->prepare("SELECT * FROM bookings WHERE booking_id = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();

    $preset       = trim($_POST['preset'] ?? $_GET['preset'] ?? 'wedding');
    $custom_req   = trim($_POST['custom_req'] ?? $_GET['custom_req'] ?? '');
    $color_scheme = trim($_POST['color_scheme'] ?? $_GET['color_scheme'] ?? '');
    $venue_type   = trim($_POST['venue_type'] ?? $_GET['venue_type'] ?? '');
    $theme        = trim($_POST['theme'] ?? $_GET['theme'] ?? '');

    $package_info = getPackageById($booking_id, $conn);
    $vision_data  = json_decode($booking['ai_venue_analysis'] ?? '', true) ?: [];

    if (empty($venue_type)) {
        $venue_type = $vision_data['venue_type'] ?? $booking['venue_location'] ?? 'Event Venue';
    }
    if (empty($theme)) {
        $theme = ($preset === 'birthday') ? 'Festive Celebration' : (($preset === 'corporate') ? 'Executive Corporate Gala' : ($package_info['theme'] ?? 'Luxury Wedding'));
    }
    if (empty($color_scheme)) {
        $color_scheme = ($preset === 'birthday') ? 'Vibrant Contemporary Accents' : (($preset === 'corporate') ? 'Navy, Slate & Ivory' : 'Champagne Gold & Warm White');
    }

    $venue_img_path = !empty($booking['venue_image']) ? __DIR__ . "/../" . $booking['venue_image'] : '';
    $api_keys = function_exists('get_gemini_api_keys') ? get_gemini_api_keys() : [GEMINI_API_KEY];

    $crafted_prompt = enrich_catering_staging_prompt([
        'venue_type'   => $venue_type,
        'theme'        => $theme,
        'pax_count'    => $booking['pax_count'] ?? 100,
        'color_scheme' => $color_scheme,
        'event_type'   => $booking['event_type'] ?? ucfirst($preset),
        'admin_prompt' => $custom_req
    ], $venue_img_path, $api_keys);

    echo json_encode([
        'success' => true,
        'prompt' => $crafted_prompt,
        'preset' => $preset,
        'has_venue_photo' => file_exists($venue_img_path)
    ]);
    exit();
}

// -------------------------------------------------------------
// Action 3: Generate Conceptual Catering Image via Two-Step Pipeline
// Step 1: gemini-2.5-flash enriches inputs into detailed photographic staging prompt
// Step 2: imagen-3.0-generate-002 renders high-res visualization (16:9 + negative prompt)
// -------------------------------------------------------------
if ($action === 'generate_concept') {
    $stmt = $conn->prepare("SELECT b.*, u.full_name FROM bookings b LEFT JOIN users u ON b.user_id = u.user_id WHERE b.booking_id = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();

    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit();
    }

    $package_info = getPackageById($booking_id, $conn);
    $vision_data  = json_decode($booking['ai_venue_analysis'] ?? '', true) ?: [];

    // Customer & Admin Inputs
    $admin_prompt = trim($_POST['admin_prompt'] ?? $_GET['admin_prompt'] ?? '');
    $color_scheme = trim($_POST['color_scheme'] ?? $_GET['color_scheme'] ?? ($vision_data['color_scheme'] ?? 'Champagne Gold & Warm White'));
    $venue_type   = trim($_POST['venue_type'] ?? $_GET['venue_type'] ?? ($vision_data['venue_type'] ?? $booking['venue_location'] ?? 'Event Venue'));
    $theme        = trim($_POST['theme'] ?? $_GET['theme'] ?? ($package_info['theme'] ?? 'Modern Elegant'));
    $pax          = intval($_POST['pax_count'] ?? $booking['pax_count'] ?? 100);

    if (!empty($admin_prompt)) {
        $vision_data['admin_prompt'] = $admin_prompt;
    }
    $vision_data['color_scheme'] = $color_scheme;
    $vision_data['venue_type'] = $venue_type;

    $venue_img_path  = !empty($booking['venue_image']) ? __DIR__ . "/../" . $booking['venue_image'] : '';
    $has_venue_photo = file_exists($venue_img_path);
    $api_keys        = function_exists('get_gemini_api_keys') ? get_gemini_api_keys() : [GEMINI_API_KEY];

    // =========================================================
    // STEP 1: Fast Text Model (gemini-2.5-flash) Prompt Enrichment
    // =========================================================
    $enriched_prompt = enrich_catering_staging_prompt([
        'venue_type'   => $venue_type,
        'theme'        => $theme,
        'pax_count'    => $pax,
        'color_scheme' => $color_scheme,
        'event_type'   => $booking['event_type'] ?? 'Catering Event',
        'admin_prompt' => $admin_prompt
    ], $venue_img_path, $api_keys);

    $vision_data['enriched_staging_prompt'] = $enriched_prompt;

    // =========================================================
    // STEP 2: Staged Image Generation (Pollinations.ai / Google AI)
    // Primary: Pollinations.ai (Flux) with Secure Auth Key
    // =========================================================
    $aspect_ratio    = defined('GEMINI_ASPECT_RATIO') ? GEMINI_ASPECT_RATIO : '16:9';
    $negative_prompt = defined('GEMINI_NEGATIVE_PROMPT') ? GEMINI_NEGATIVE_PROMPT : 'blurry, deformed food, extra limbs, plastic disposable plates, messy counters, distorted furniture, cartoon, low quality, oversaturated';

    $img_result = generate_catering_staged_image($enriched_prompt, $api_keys, [
        'aspect_ratio'    => $aspect_ratio,
        'negative_prompt' => $negative_prompt,
        'venue_img_path'  => $venue_img_path
    ]);

    $img_bytes = null;
    $generation_engine = 'Pollinations.ai Catering Visualizer';

    if (!empty($img_result['success']) && !empty($img_result['image_bytes'])) {
        $img_bytes = $img_result['image_bytes'];
        $generation_engine = $img_result['engine'] ?? 'Pollinations.ai (Flux • 16:9)';
    }

    // Handle image outcome
    $ai_summary = generate_catering_package_summary($conn, $booking_id, $booking, $package_info, $vision_data, $admin_prompt);
    $vision_data['ai_summary'] = $ai_summary;
    $vision_data['generation_engine'] = $generation_engine;

    // Track layout author and approval status
    $user_name = $_SESSION['full_name'] ?? ($is_admin ? 'Admin' : 'Staff');
    $now_dt    = date('Y-m-d H:i:s');

    if ($is_staff) {
        $layout_approval_status = 'pending_approval';
        $layout_generated_by    = $user_name . ' (Staff)';
        $layout_generated_at    = $now_dt;
        $layout_approved_by     = null;
        $layout_approved_at     = null;
    } else {
        $layout_approval_status = 'approved';
        $layout_generated_by    = $user_name . ' (Admin)';
        $layout_generated_at    = $now_dt;
        $layout_approved_by     = $user_name . ' (Admin)';
        $layout_approved_at     = $now_dt;
    }

    $vision_data['layout_approval_status'] = $layout_approval_status;
    $vision_data['layout_generated_by']    = $layout_generated_by;
    $vision_data['layout_generated_at']    = $layout_generated_at;
    $vision_data['layout_approved_by']     = $layout_approved_by;
    $vision_data['layout_approved_at']     = $layout_approved_at;

    $analysis_json = json_encode($vision_data);

    if ($img_bytes && strlen($img_bytes) > 5000) {
        $gen_filename = "ai_concept_" . $booking_id . "_" . time() . ".jpg";
        $gen_rel_path = "uploads/ai_conceptual/" . $gen_filename;
        $gen_full_path = __DIR__ . "/../" . $gen_rel_path;

        $target_dir = dirname($gen_full_path);
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        file_put_contents($gen_full_path, $img_bytes);

        // Update database with conceptual image, vision analysis, and approval tracking
        if ($is_staff) {
            $up_stmt = $conn->prepare("UPDATE bookings SET ai_conceptual_image = ?, ai_venue_analysis = ?, layout_approval_status = ?, layout_generated_by = ?, layout_generated_at = ? WHERE booking_id = ?");
            $up_stmt->bind_param("sssssi", $gen_rel_path, $analysis_json, $layout_approval_status, $layout_generated_by, $layout_generated_at, $booking_id);
            $up_stmt->execute();
        } else {
            $up_stmt = $conn->prepare("UPDATE bookings SET ai_conceptual_image = ?, ai_venue_analysis = ?, layout_approval_status = ?, layout_generated_by = ?, layout_generated_at = ?, layout_approved_by = ?, layout_approved_at = ? WHERE booking_id = ?");
            $up_stmt->bind_param("sssssssi", $gen_rel_path, $analysis_json, $layout_approval_status, $layout_generated_by, $layout_generated_at, $layout_approved_by, $layout_approved_at, $booking_id);
            $up_stmt->execute();
        }

        echo json_encode([
            'success' => true,
            'message' => $is_staff 
                ? 'Conceptual catering setup visualization generated! Layout is submitted as Pending Admin Approval.' 
                : 'Conceptual catering setup visualization generated and approved by Admin!',
            'conceptual_image' => $gen_rel_path,
            'generation_engine' => $generation_engine,
            'package_info' => $package_info,
            'vision_analysis' => $vision_data,
            'ai_summary' => $ai_summary,
            'enriched_prompt' => $enriched_prompt,
            'admin_prompt' => $admin_prompt,
            'layout_approval_status' => $layout_approval_status,
            'layout_generated_by' => $layout_generated_by,
            'layout_generated_at' => $layout_generated_at,
            'layout_approved_by' => $layout_approved_by,
            'layout_approved_at' => $layout_approved_at
        ]);
    } else {
        // Persist the accurate vision analysis & package summary into the booking
        if ($is_staff) {
            $up_stmt = $conn->prepare("UPDATE bookings SET ai_venue_analysis = ?, layout_approval_status = ?, layout_generated_by = ?, layout_generated_at = ? WHERE booking_id = ?");
            $up_stmt->bind_param("ssssi", $analysis_json, $layout_approval_status, $layout_generated_by, $layout_generated_at, $booking_id);
            $up_stmt->execute();
        } else {
            $up_stmt = $conn->prepare("UPDATE bookings SET ai_venue_analysis = ?, layout_approval_status = ?, layout_generated_by = ?, layout_generated_at = ?, layout_approved_by = ?, layout_approved_at = ? WHERE booking_id = ?");
            $up_stmt->bind_param("ssssssi", $analysis_json, $layout_approval_status, $layout_generated_by, $layout_generated_at, $layout_approved_by, $layout_approved_at, $booking_id);
            $up_stmt->execute();
        }

        $error_detail = !empty($img_result['error']) ? $img_result['error'] : 'Google AI Image model quota exceeded (HTTP 429).';
        echo json_encode([
            'success' => false,
            'quota_exhausted' => true,
            'message' => 'Google AI Vision accurately analyzed your venue (' . $venue_type . ')! ' . $error_detail,
            'ai_summary' => $ai_summary,
            'vision_analysis' => $vision_data,
            'enriched_prompt' => $enriched_prompt,
            'admin_prompt' => $admin_prompt,
            'layout_approval_status' => $layout_approval_status,
            'layout_generated_by' => $layout_generated_by,
            'layout_generated_at' => $layout_generated_at
        ]);
    }
    exit();
}

// -------------------------------------------------------------
// Action 3.5: Summarize Package, Menu, Pax & Table Layout
// -------------------------------------------------------------
if ($action === 'summarize_package') {
    $stmt = $conn->prepare("SELECT b.*, u.full_name FROM bookings b LEFT JOIN users u ON b.user_id = u.user_id WHERE b.booking_id = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();

    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit();
    }

    $package_info = getPackageById($booking_id, $conn);
    $vision_data = json_decode($booking['ai_venue_analysis'] ?? '', true) ?: [];
    $admin_prompt = trim($_POST['admin_prompt'] ?? $_GET['admin_prompt'] ?? ($vision_data['admin_prompt'] ?? ''));
    if (!empty($admin_prompt)) {
        $vision_data['admin_prompt'] = $admin_prompt;
    }

    $ai_summary = generate_catering_package_summary($conn, $booking_id, $booking, $package_info, $vision_data, $admin_prompt);
    $vision_data['ai_summary'] = $ai_summary;
    $analysis_json = json_encode($vision_data);

    $up_stmt = $conn->prepare("UPDATE bookings SET ai_venue_analysis = ? WHERE booking_id = ?");
    $up_stmt->bind_param("si", $analysis_json, $booking_id);
    $up_stmt->execute();

    echo json_encode([
        'success' => true,
        'message' => 'AI Catering & Package Summary generated successfully',
        'ai_summary' => $ai_summary,
        'package_info' => $package_info,
        'vision_analysis' => $vision_data
    ]);
    exit();
}

// -------------------------------------------------------------
// Action 3.8: Approve Venue Layout (Admin Only)
// -------------------------------------------------------------
if ($action === 'approve_venue_layout') {
    if (!$is_admin) {
        echo json_encode(['success' => false, 'message' => 'Only an Administrator can officially approve venue layouts.']);
        exit();
    }

    $admin_name = ($_SESSION['full_name'] ?? 'Admin') . ' (Admin)';
    $now_dt     = date('Y-m-d H:i:s');
    $status     = 'approved';

    // Fetch existing analysis JSON to sync approval status
    $b_stmt = $conn->prepare("SELECT ai_venue_analysis FROM bookings WHERE booking_id = ?");
    $b_stmt->bind_param("i", $booking_id);
    $b_stmt->execute();
    $b_row = $b_stmt->get_result()->fetch_assoc();
    $v_data = json_decode($b_row['ai_venue_analysis'] ?? '', true) ?: [];

    $v_data['layout_approval_status'] = $status;
    $v_data['layout_approved_by']     = $admin_name;
    $v_data['layout_approved_at']     = $now_dt;
    $analysis_json = json_encode($v_data);

    $up_stmt = $conn->prepare("UPDATE bookings SET layout_approval_status = ?, layout_approved_by = ?, layout_approved_at = ?, ai_venue_analysis = ? WHERE booking_id = ?");
    $up_stmt->bind_param("ssssi", $status, $admin_name, $now_dt, $analysis_json, $booking_id);

    if ($up_stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'AI Venue Layout has been officially APPROVED by Admin!',
            'layout_approval_status' => $status,
            'layout_approved_by'     => $admin_name,
            'layout_approved_at'     => $now_dt
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to record layout approval in database.']);
    }
    exit();
}

// -------------------------------------------------------------
// Action 3.9: Reject / Request Revision for Venue Layout (Admin Only)
// -------------------------------------------------------------
if ($action === 'reject_venue_layout') {
    if (!$is_admin) {
        echo json_encode(['success' => false, 'message' => 'Only an Administrator can request revisions on venue layouts.']);
        exit();
    }

    $admin_name = ($_SESSION['full_name'] ?? 'Admin') . ' (Admin)';
    $notes      = trim($_POST['notes'] ?? 'Administrator requested revision for the venue layout concept.');
    $now_dt     = date('Y-m-d H:i:s');
    $status     = 'rejected';

    $b_stmt = $conn->prepare("SELECT ai_venue_analysis FROM bookings WHERE booking_id = ?");
    $b_stmt->bind_param("i", $booking_id);
    $b_stmt->execute();
    $b_row = $b_stmt->get_result()->fetch_assoc();
    $v_data = json_decode($b_row['ai_venue_analysis'] ?? '', true) ?: [];

    $v_data['layout_approval_status'] = $status;
    $v_data['layout_admin_notes']     = $notes;
    $v_data['layout_approved_by']     = $admin_name;
    $v_data['layout_approved_at']     = $now_dt;
    $analysis_json = json_encode($v_data);

    $up_stmt = $conn->prepare("UPDATE bookings SET layout_approval_status = ?, layout_admin_notes = ?, layout_approved_by = ?, layout_approved_at = ?, ai_venue_analysis = ? WHERE booking_id = ?");
    $up_stmt->bind_param("sssssi", $status, $notes, $admin_name, $now_dt, $analysis_json, $booking_id);

    if ($up_stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Venue layout marked as Revision Requested.',
            'layout_approval_status' => $status,
            'layout_admin_notes'     => $notes
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to record revision request in database.']);
    }
    exit();
}

// -------------------------------------------------------------
// Action 4: Send Recommendation directly to Customer Chat
// -------------------------------------------------------------
if ($action === 'send_recommendation') {
    $stmt = $conn->prepare("SELECT b.*, u.full_name FROM bookings b LEFT JOIN users u ON b.user_id = u.user_id WHERE b.booking_id = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();

    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit();
    }

    // Enforce admin approval: Staff cannot send recommendation to client until Admin approves!
    $current_layout_status = $booking['layout_approval_status'] ?? 'none';
    if ($is_staff && $current_layout_status !== 'approved') {
        echo json_encode([
            'success' => false, 
            'message' => 'Admin Approval Required: Staff cannot send this AI venue layout proposal to the customer until an Administrator reviews and approves it.'
        ]);
        exit();
    }

    $customer_id = $booking['user_id'];
    $admin_id    = $_SESSION['user_id'] ?? 1;

    // Ensure messages table exists
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

    // Fetch vision analysis & package details
    $package_info = getPackageById($booking_id, $conn);
    $vision_data = json_decode($booking['ai_venue_analysis'] ?? '', true) ?: [];
    $ai_summary = $vision_data['ai_summary'] ?? generate_catering_package_summary($conn, $booking_id, $booking, $package_info, $vision_data, $vision_data['admin_prompt'] ?? '');

    $bk_code = "#BK-" . str_pad($booking_id, 4, '0', STR_PAD_LEFT);
    $event_title = $booking['event_name'] ?? 'Catering Event';

    $v_type  = $vision_data['venue_type'] ?? 'Indoor Hall';
    $v_cap   = $ai_summary['pax_count'] . ' Pax';
    $v_light = $vision_data['lighting'] ?? 'Warm Ambient Lighting';

    $table_info  = $ai_summary['suggested_tables']['guest_tables'] ?? $package_info['table_type'];
    $head_table  = $ai_summary['suggested_tables']['head_table'] ?? '1 Head Table';
    $buffet_info = $ai_summary['suggested_tables']['buffet_station'] ?? $package_info['buffet'];
    $menu_text   = implode(', ', $ai_summary['menu_list'] ?? []);

    $msg_text = "✨ *AI CATERING & VENUE RECOMMENDATION PROPOSAL* ✨\n"
              . "📌 Booking {$bk_code}: {$event_title}\n\n"
              . "🏛️ *Venue & Setup Staging:*\n"
              . "• Venue: " . ($booking['venue_location'] ?: $v_type) . "\n"
              . "• Capacity / Pax: {$v_cap}\n"
              . "• Ambience: {$v_light}\n\n"
              . "🍴 *Package Availed:* " . $ai_summary['package_name'] . " (" . $ai_summary['formatted_amount'] . ")\n"
              . "• Theme: " . $ai_summary['theme'] . "\n"
              . "• Guest Seating: {$table_info}\n"
              . "• VIP / Head Table: {$head_table}\n"
              . "• Buffet Station: {$buffet_info}\n"
              . "• Selected Menu Dishes: {$menu_text}\n\n"
              . "🎨 *Conceptual Staging Visualization:* Attached below is your venue's photorealistic AI conceptual setup preserving the venue structure!";

    $attachment_img = !empty($booking['ai_conceptual_image']) ? $booking['ai_conceptual_image'] : null;
    $file_type = $attachment_img ? 'image' : null;

    $chat_stmt = $conn->prepare("INSERT INTO messages (booking_id, sender_id, receiver_id, message, attachment, file_type, is_read) VALUES (?, ?, ?, ?, ?, ?, 0)");
    $chat_stmt->bind_param("iiisss", $booking_id, $admin_id, $customer_id, $msg_text, $attachment_img, $file_type);
    
    if ($chat_stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'AI Catering Recommendation sent directly to customer chat successfully!'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send message to customer chat']);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid action requested']);
exit();
?>
