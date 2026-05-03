<?php
// .env ファイルをパースして $_ENV に入れる簡易処理
// VS Codeの構成に基づき、一つ上の階層 (../.env) を見に行くよう修正
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0)
            continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value);
        }
    }
}

// チェック処理
if (empty($_ENV['GOOGLE_CLIENT_ID'])) {
    die('Error: GOOGLE_CLIENT_ID is not set in .env (Path: ' . realpath($envPath) . ')');
}

session_start();

// .env から読み込んだ値を使用（credentials.json の読み込みは不要なため削除）
$clientId = $_ENV['GOOGLE_CLIENT_ID'];
// image_5edc0e.jpg で設定した「リダイレクト URI」と完全に一致させる（末尾の /test/ を追加）
$redirectUri = 'https://desktop-mnoqic1.tail7aa158.ts.net/index.php?page=google_callback';

$params = [
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => 'https://www.googleapis.com/auth/calendar.events', // カレンダーの操作権限
    'access_type' => 'offline', // リフレッシュトークンをもらうために必須
    'prompt' => 'consent'  // 常に承認画面を出し、トークンを確実にもらう
];

// 認証URLの生成とリダイレクト
$authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
header('Location: ' . $authUrl);
exit;