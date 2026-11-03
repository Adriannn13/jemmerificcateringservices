<?php
// config/ai_config.php - Central AI Assistant Configuration
require_once __DIR__ . '/../includes/env_loader.php';

function get_ai_config() {
    $file = __DIR__ . '/ai_settings.json';
    $default_config = [
        'ai_provider' => 'gemini', // 'gemini', 'groq', 'ollama', 'openai', 'copilot', or 'auto'
        'groq_api_key' => getenv('GROQ_API_KEY') ?: '',
        'groq_model' => 'qwen/qwen3.8-27b',
        'ollama_url' => 'http://localhost:11434',
        'ollama_model' => 'llama3',
        'gemini_api_key' => defined('GEMINI_API_KEY') ? GEMINI_API_KEY : (getenv('GEMINI_API_KEY') ?: ''),
        'gemini_model' => 'gemini-3.5-flash',
        'gemini_image_model' => 'imagen-3.0-generate-002',
        'image_provider' => 'pollinations', // 'pollinations' or 'gemini'
        'pollinations_api_key' => defined('POLLINATIONS_API_KEY') ? POLLINATIONS_API_KEY : (getenv('POLLINATIONS_API_KEY') ?: ''),
        'pollinations_model' => 'flux', // 'flux', 'flux-realism', 'turbo'
        'image_aspect_ratio' => '16:9',
        'negative_prompt' => 'blurry, deformed food, extra limbs, plastic disposable plates, messy counters, distorted furniture, cartoon, low quality, oversaturated',
        'openai_api_key' => getenv('OPENAI_API_KEY') ?: '',
        'openai_model' => 'gpt-4o-mini',
        'copilot_api_key' => '',
        'copilot_url' => 'https://api.openai.com/v1',
        'copilot_model' => 'gpt-4o-mini',
        'low_resource_mode' => true,
        'daily_message_limit' => 10
    ];

    if (file_exists($file)) {
        $json_data = json_decode(file_get_contents($file), true);
        if (is_array($json_data)) {
            // Filter out empty settings so environment variables take precedence
            $filtered_json = array_filter($json_data, function($val) {
                return $val !== '' && $val !== null;
            });
            return array_merge($default_config, $filtered_json);
        }
    }
    return $default_config;
}

$AI_CONFIG = get_ai_config();
?>
