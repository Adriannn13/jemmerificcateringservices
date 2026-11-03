<?php
// auth/generate_venue_image.php
// Direct REST API Endpoint for Imagen 3 & Gemini Catering Image Generation
// Pure cURL implementation without external composer dependencies

header('Content-Type: application/json');

require_once __DIR__ . '/../config/gemini.php';
require_once __DIR__ . '/../config/ai_config.php';

require_once __DIR__ . '/../includes/PollinationsImageEngine.php';

// Securely retrieve API Key from server configuration (Never exposed to browser JS)
$apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : (getenv('GEMINI_API_KEY') ?: '');
$pollinationsKey = defined('POLLINATIONS_API_KEY') ? POLLINATIONS_API_KEY : (getenv('POLLINATIONS_API_KEY') ?: '');
$pollinationsModel = $_POST['model'] ?? (defined('POLLINATIONS_MODEL') ? POLLINATIONS_MODEL : 'flux');

// 1. Get input from customer's booking form
$venueType    = $_POST['venue'] ?? $_POST['venue_type'] ?? 'The Grand Orchid Ballroom, Manila';
$theme        = $_POST['theme'] ?? 'Elegant luxury wedding, white orchids floral arrangements, crystal chandeliers';
$colorTone    = $_POST['colors'] ?? $_POST['color_scheme'] ?? 'Emerald green, gold accents, and warm white florals';
$setupType    = $_POST['setup'] ?? $_POST['setup_type'] ?? 'Buffet station with round guest tables';
$desiredTime  = $_POST['time_lighting'] ?? 'Golden hour sunset lighting glimmering through floor-to-ceiling glass windows';
$specificNotes = $_POST['notes'] ?? 'A clean empty stage with polished marble floors reflecting the light';
$customPrompt = trim($_POST['prompt'] ?? $_POST['admin_prompt'] ?? '');
$aspectRatio  = $_POST['aspect_ratio'] ?? '16:9'; // Options: '1:1', '4:3', '16:9'
$bookingId    = intval($_POST['booking_id'] ?? 0);

// 2. Build a rich photographic prompt using generateAccurateVenuePrompt
if (!empty($customPrompt)) {
    $prompt = $customPrompt;
} else {
    $prompt = generateAccurateVenuePrompt($venueType, $theme, $desiredTime, $specificNotes, [
        'colors' => $colorTone,
        'pax'    => intval($_POST['pax'] ?? 100)
    ]);
}

// 3. Request Image Generation via PollinationsImageEngine
$width = 1280;
$height = 720;
if ($aspectRatio === '4:3') {
    $width = 1024;
    $height = 768;
} elseif ($aspectRatio === '1:1') {
    $width = 1024;
    $height = 1024;
}

$engine = new PollinationsImageEngine($pollinationsKey, 45);
$rawImage = $engine->generateVenueImage($prompt, $pollinationsModel, $width, $height);

$imageBase64 = null;
$engineName = "Pollinations.ai (" . PollinationsImageEngine::getModelLabel($engine->getLastUsedModel()) . " • {$aspectRatio})";

if ($rawImage && strlen($rawImage) > 5000) {
    $imageBase64 = base64_encode($rawImage);
}

// Secondary Backup: If Pollinations.ai is unreachable, try Google AI Imagen 3 / Gemini
if (!$imageBase64 && !empty($apiKey)) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/imagen-3.0-generate-002:predict?key=" . $apiKey;
    $data = [
        'instances' => [['prompt' => $prompt]],
        'parameters' => [
            'sampleCount' => 1,
            'aspectRatio' => $aspectRatio,
            'outputMimeType' => 'image/jpeg',
            'negativePrompt' => 'blurry, deformed food, extra limbs, plastic disposable plates, messy counters, distorted furniture, cartoon, low quality'
        ]
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'x-goog-api-key: ' . $apiKey]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $g_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($g_code === 200 && $response) {
        $result = json_decode($response, true);
        if (!empty($result['predictions'][0]['bytesBase64Encoded'])) {
            $imageBase64 = $result['predictions'][0]['bytesBase64Encoded'];
            $engineName = 'Google Imagen 3 (16:9 Backup)';
        }
    }
}

// Note: Never fetch static stock images from uploads folder. 
// If Google AI returns quota or error, report the actual status honestly so user knows the exact state.

// 4. Return the Base64 image back to frontend
if (!empty($imageBase64)) {
    $saved_rel_path = null;

    // Persist to database & uploads if booking_id provided
    if ($bookingId > 0) {
        $imgBytes = base64_decode($imageBase64);
        $gen_filename = "ai_concept_" . $bookingId . "_" . time() . ".jpg";
        $saved_rel_path = "uploads/ai_conceptual/" . $gen_filename;
        $saved_full_path = __DIR__ . "/../" . $saved_rel_path;
        if (!is_dir(dirname($saved_full_path))) {
            mkdir(dirname($saved_full_path), 0777, true);
        }
        file_put_contents($saved_full_path, $imgBytes);

        if (file_exists(__DIR__ . '/../db_config.php')) {
            require_once __DIR__ . '/../db_config.php';
            if (isset($conn)) {
                $up = $conn->prepare("UPDATE bookings SET ai_conceptual_image = ? WHERE booking_id = ?");
                $up->bind_param("si", $saved_rel_path, $bookingId);
                $up->execute();
            }
        }
    }

    echo json_encode([
        'status' => 'success',
        'image_url' => 'data:image/jpeg;base64,' . $imageBase64,
        'saved_path' => $saved_rel_path,
        'engine' => $engineName,
        'prompt' => $prompt
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Image generation failed',
        'details' => $result
    ]);
}
?>
