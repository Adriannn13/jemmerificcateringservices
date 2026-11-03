<?php
// config/gemini.php - Centralized Gemini & Vision AI Configuration
require_once __DIR__ . '/../includes/env_loader.php';

// Load dynamic AI config if available
$ai_settings_file = __DIR__ . '/ai_settings.json';
$configured_gemini_key = getenv('GEMINI_API_KEY') ?: '';
$configured_gemini_model = 'gemini-2.5-flash';
$configured_image_model = 'imagen-3.0-generate-002';
$configured_aspect_ratio = '16:9';
$configured_negative_prompt = 'blurry, deformed food, extra limbs, plastic disposable plates, messy counters, distorted furniture, cartoon, low quality, oversaturated';

$configured_image_provider = 'pollinations';
$configured_pollinations_key = getenv('POLLINATIONS_API_KEY') ?: '';
$configured_pollinations_model = 'flux';

if (file_exists($ai_settings_file)) {
    $settings_data = json_decode(file_get_contents($ai_settings_file), true);
    if (!empty($settings_data['gemini_api_key'])) {
        $configured_gemini_key = trim($settings_data['gemini_api_key']);
    }
    if (!empty($settings_data['gemini_model'])) {
        $configured_gemini_model = trim($settings_data['gemini_model']);
    }
    if (!empty($settings_data['gemini_image_model'])) {
        $configured_image_model = trim($settings_data['gemini_image_model']);
    }
    if (!empty($settings_data['image_provider'])) {
        $configured_image_provider = trim($settings_data['image_provider']);
    }
    if (!empty($settings_data['pollinations_api_key'])) {
        $configured_pollinations_key = trim($settings_data['pollinations_api_key']);
    }
    if (!empty($settings_data['pollinations_model'])) {
        $configured_pollinations_model = trim($settings_data['pollinations_model']);
    }
    if (!empty($settings_data['image_aspect_ratio'])) {
        $configured_aspect_ratio = trim($settings_data['image_aspect_ratio']);
    }
    if (!empty($settings_data['negative_prompt'])) {
        $configured_negative_prompt = trim($settings_data['negative_prompt']);
    }
}

// Image Generation Provider: 'pollinations' (recommended) or 'gemini'
if (!defined('IMAGE_PROVIDER')) {
    define('IMAGE_PROVIDER', $configured_image_provider ?: 'pollinations');
}

// Pollinations.ai Secure Secret Key (sk_...) - Never exposed to client-side JS
if (!defined('POLLINATIONS_API_KEY')) {
    define('POLLINATIONS_API_KEY', $configured_pollinations_key ?: (getenv('POLLINATIONS_API_KEY') ?: ''));
}

// Pollinations Model ('flux', 'flux-realism', 'turbo')
if (!defined('POLLINATIONS_MODEL')) {
    define('POLLINATIONS_MODEL', $configured_pollinations_model ?: 'flux');
}

// Primary API Key (Prioritizes user's Google AI Studio key from settings, then environment variable)
if (!defined('GEMINI_API_KEY')) {
    define('GEMINI_API_KEY', !empty($configured_gemini_key) ? $configured_gemini_key : (getenv('GEMINI_API_KEY') ?: ''));
}

// Vision & Multimodal API Key
if (!defined('GEMINI_VISION_API_KEY')) {
    define('GEMINI_VISION_API_KEY', getenv('GEMINI_VISION_API_KEY') ?: GEMINI_API_KEY);
}

// Fast Text Model for Prompt Enrichment (Step 1 of two-step pipeline)
if (!defined('GEMINI_TEXT_MODEL')) {
    define('GEMINI_TEXT_MODEL', 'gemini-3.5-flash-lite');
}

// Vision Model for venue spatial detection
if (!defined('GEMINI_VISION_MODEL')) {
    define('GEMINI_VISION_MODEL', 'gemini-3.5-flash-lite');
}

// Google AI Image Generation Model (Step 2 of two-step pipeline)
if (!defined('GEMINI_IMAGE_MODEL')) {
    define('GEMINI_IMAGE_MODEL', $configured_image_model ?: 'imagen-3.0-generate-002');
}

// Negative Prompt configuration to maintain pristine high-end catering aesthetics
if (!defined('GEMINI_NEGATIVE_PROMPT')) {
    define('GEMINI_NEGATIVE_PROMPT', $configured_negative_prompt);
}

// Default Aspect Ratio (16:9 gives panoramic perspective of venue width and table layout)
if (!defined('GEMINI_ASPECT_RATIO')) {
    define('GEMINI_ASPECT_RATIO', $configured_aspect_ratio ?: '16:9');
}

/**
 * Returns prioritized array of candidate Gemini API keys
 */
function get_gemini_api_keys() {
    $keys = [];
    
    // 1. User's configured key from settings (Google AI Studio key)
    global $configured_gemini_key;
    if (!empty($configured_gemini_key)) {
        $keys[] = $configured_gemini_key;
    }
    
    // 2. Constants
    if (defined('GEMINI_API_KEY') && !empty(GEMINI_API_KEY)) {
        $keys[] = GEMINI_API_KEY;
    }
    if (defined('GEMINI_VISION_API_KEY') && !empty(GEMINI_VISION_API_KEY)) {
        $keys[] = GEMINI_VISION_API_KEY;
    }
    
    return array_values(array_filter(array_unique($keys)));
}
?>
