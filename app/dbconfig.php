<?php
// DB接続設定
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3307');
define('DB_NAME', 'sample_db');
define('DB_USER', 'root');
define('DB_PASS', 'P@ss12345');

// 追加：PageController が必要とするパスの定義
define('DATA_PATH', __DIR__ . '/data/pages.json'); // JSONファイルの場所
define('TEMPLATE_PATH', __DIR__ . '/templates/');    // テンプレートの場所

function getDB() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        return new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        die("DB接続失敗: " . $e->getMessage());
    }
}
?>