<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit();
    }

    $ai_provider = trim($_POST['ai_provider'] ?? 'gemini');
    $ollama_url = rtrim(trim($_POST['ollama_url'] ?? 'http://localhost:11434'), '/');
    $ollama_model = trim($_POST['ollama_model'] ?? 'llama3');
    $gemini_api_key = trim($_POST['gemini_api_key'] ?? '');
    $gemini_model = trim($_POST['gemini_model'] ?? 'gemini-2.5-flash');
    $gemini_image_model = trim($_POST['gemini_image_model'] ?? 'imagen-3.0-generate-002');
    $image_aspect_ratio = trim($_POST['image_aspect_ratio'] ?? '16:9');
    $image_provider = trim($_POST['image_provider'] ?? 'pollinations');
    $pollinations_api_key = trim($_POST['pollinations_api_key'] ?? '');
    $pollinations_model = trim($_POST['pollinations_model'] ?? 'flux');
    $openai_api_key = trim($_POST['openai_api_key'] ?? '');
    $openai_model = trim($_POST['openai_model'] ?? 'gpt-4o-mini');
    $copilot_api_key = trim($_POST['copilot_api_key'] ?? '');
    $copilot_url = rtrim(trim($_POST['copilot_url'] ?? 'https://api.openai.com/v1'), '/');
    $copilot_model = trim($_POST['copilot_model'] ?? 'gpt-4o-mini');
    $low_resource_mode = isset($_POST['low_resource_mode']) ? true : false;
    $daily_message_limit = isset($_POST['daily_message_limit']) ? min(10, max(1, intval($_POST['daily_message_limit']))) : 10;

    $config_data = [
        'ai_provider' => $ai_provider,
        'image_provider' => $image_provider,
        'pollinations_api_key' => $pollinations_api_key,
        'pollinations_model' => $pollinations_model,
        'ollama_url' => $ollama_url,
        'ollama_model' => $ollama_model,
        'gemini_api_key' => $gemini_api_key,
        'gemini_model' => $gemini_model,
        'gemini_image_model' => $gemini_image_model,
        'image_aspect_ratio' => $image_aspect_ratio,
        'negative_prompt' => $negative_prompt,
        'openai_api_key' => $openai_api_key,
        'openai_model' => $openai_model,
        'copilot_api_key' => $copilot_api_key,
        'copilot_url' => $copilot_url,
        'copilot_model' => $copilot_model,
        'low_resource_mode' => $low_resource_mode,
        'daily_message_limit' => $daily_message_limit,
        'updated_at' => date('Y-m-d H:i:s')
    ];

    $file_path = __DIR__ . '/../config/ai_settings.json';
    if (file_put_contents($file_path, json_encode($config_data, JSON_PRETTY_PRINT))) {
        echo json_encode(['status' => 'success', 'message' => 'AI Configuration saved successfully!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to write configuration file. Check file permissions.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>
