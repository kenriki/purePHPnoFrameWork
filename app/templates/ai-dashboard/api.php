<?php

declare(strict_types=1);

require_once 'env_loader.php';
require_once 'AiManager.php';

// .envの読み込み
loadEnv(__DIR__ . '/.env');

// AiManagerのインスタンス化 (PHP 8.5 Named Arguments)
try {
    $manager = new AiManager(
        geminiKey: $_ENV['GEMINI_KEY'] ?? throw new Exception('GEMINI_KEY not set'),
    );
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Initialization failed: ' . $e->getMessage()]);
    exit;
}

header('Content-Type: application/json');

$provider = $_GET['provider'] ?? '';
$prompt = $_GET['prompt'] ?? '';

if (!$provider || !$prompt) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

try {
    // 各AIに問い合わせ
    $result = $manager->ask($provider, $prompt);
    echo json_encode(['response' => $result]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}