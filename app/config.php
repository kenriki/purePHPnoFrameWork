<?php

// ------------------------------------------------------------
// .env ローダー
// ------------------------------------------------------------
$envPath = dirname(__DIR__) . '/.env';

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // コメント行はスキップ
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // KEY=VALUE 形式のみ処理
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // 環境変数として登録
            putenv("$key=$value");
        }
    }
}

// ------------------------------------------------------------
// パス定義
// ------------------------------------------------------------
define('APP_ROOT', dirname(__DIR__));
define('DATA_PATH', APP_ROOT . '/app/data/pages.json');
define('TEMPLATE_PATH', APP_ROOT . '/app/templates/');

// ------------------------------------------------------------
// DB 接続情報（.env から読み込み）
// ------------------------------------------------------------
define('DB_HOST', getenv('DB_HOST'));
define('DB_PORT', getenv('DB_PORT'));
define('DB_NAME', getenv('DB_NAME'));
define('DB_USER', getenv('DB_USER'));
define('DB_PASS', getenv('DB_PASS'));

// ------------------------------------------------------------
// 管理メールアドレス
// ------------------------------------------------------------
define('ADMIN_EMAIL', getenv('ADMIN_EMAIL'));

// ------------------------------------------------------------
// SMTP 設定（Gmail 用）
// ------------------------------------------------------------
define('SMTP_HOST', getenv('SMTP_HOST'));   // smtp.gmail.com
define('SMTP_USER', getenv('SMTP_USER'));   // Gmail アドレス
define('SMTP_PASS', getenv('SMTP_PASS'));   // アプリパスワード
define('SMTP_PORT', getenv('SMTP_PORT'));   // 587

?>