<?php
declare(strict_types=1);

/**
 * 共有データ（短縮ID）の登録・取得を行う軽量JSON APIエンドポイント。
 *
 * 静的サーバ側のフロントエンドからPOSTでペイロードを受け取り、
 * 一意な英数字IDを発行して `shares/<id>.json` に保存するとともに、
 * `../app/data/shares/` 配下へ日時付きJSONバックアップを出力する。
 * また、GETリクエスト（`?id=<id>`）に対しては保存済みJSONデータを返却する。
 *
 * @package ShareApi
 * @author  System
 * @since   1.0.0
 */

// 静的サーバ（別オリジン）からのfetchを許可する場合は適宜ドメインを絞る
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Request-Private-Network');
header('Access-Control-Allow-Private-Network: true');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$storageDir = __DIR__ . '/shares';
if (!is_dir($storageDir) && !@mkdir($storageDir, 0775, true)) {
    jsonResponse(['ok' => false, 'error' => '保存ディレクトリを作成できません'], 500);
}

function jsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function isValidId(string $id): bool
{
    return (bool)preg_match('/^[a-zA-Z0-9]{6,10}$/', $id);
}

function generateId(int $len = 7): string
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $max = strlen($chars) - 1;
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $chars[random_int(0, $max)];
    }
    return $out;
}

// 共有データ取得: GET share.php?id=xxxxxxx
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = (string)($_GET['id'] ?? '');
    if (!isValidId($id)) {
        jsonResponse(['ok' => false, 'error' => 'IDが不正です'], 400);
    }

    $file = $storageDir . '/' . $id . '.json';
    if (!is_file($file)) {
        jsonResponse(['ok' => false, 'error' => 'データが見つかりません'], 404);
    }

    $decoded = json_decode((string)file_get_contents($file), true);
    if (!is_array($decoded)) {
        jsonResponse(['ok' => false, 'error' => 'データが破損しています'], 500);
    }

    jsonResponse(['ok' => true, 'id' => $id, 'data' => $decoded]);
}

// 短縮URL発行: POST share.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    if ($raw === false || strlen($raw) > 524288) {
        jsonResponse(['ok' => false, 'error' => 'データサイズが大きすぎます'], 400);
    }

    $body = json_decode($raw, true);
    if (!is_array($body) || !isset($body['payload'])) {
        jsonResponse(['ok' => false, 'error' => 'payloadが不正です'], 400);
    }

    $id = '';
    for ($i = 0; $i < 10; $i++) {
        $cand = generateId(7);
        if (!file_exists($storageDir . '/' . $cand . '.json')) {
            $id = $cand;
            break;
        }
    }
    if ($id === '') {
        jsonResponse(['ok' => false, 'error' => 'ID生成に失敗しました'], 500);
    }

    // 静的サーバ側のURLが指定されていればそちらを短縮URLにする。未指定ならこのPHPのURLを返す
    $targetBaseUrl = '';
    if (!empty($body['targetUrl']) && is_string($body['targetUrl'])) {
        $parsed = parse_url($body['targetUrl']);
        if (isset($parsed['scheme'], $parsed['host']) && in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
            $targetBaseUrl = $parsed['scheme'] . '://' . $parsed['host']
                . (isset($parsed['port']) ? ':' . $parsed['port'] : '')
                . ($parsed['path'] ?? '/');
        }
    }

    if ($targetBaseUrl === '') {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443')
            || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $scheme = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/share.php', PHP_URL_PATH) ?: '/share.php';
        $targetBaseUrl = $scheme . '://' . $host . $path;
    }

    $separator = str_contains($targetBaseUrl, '?') ? '&' : '?';
    $shortUrl = $targetBaseUrl . $separator . 's=' . $id;

    $record = [
        'payload'   => $body['payload'],
        'createdAt' => gmdate('c'),
    ];
    $jsonRecord = json_encode($record, JSON_UNESCAPED_UNICODE);
    $mainFile = $storageDir . '/' . $id . '.json';

    if ($jsonRecord === false || file_put_contents($mainFile, $jsonRecord, LOCK_EX) === false) {
        jsonResponse(['ok' => false, 'error' => '保存に失敗しました'], 500);
    }

    // app/data/shares/ 配下へバックアップ（日付別フォルダにする場合は date('Ymd') を混ぜてもOK）
    $backupDir = __DIR__ . '/../app/data/shares';
    if (!is_dir($backupDir)) {
        @mkdir($backupDir, 0775, true);
    }
    if (is_dir($backupDir)) {
        $backupFile = $backupDir . '/' . date('Ymd_His') . '_' . $id . '.json';
        @file_put_contents($backupFile, $jsonRecord, LOCK_EX);

        // 30日（2592000秒）経過したバックアップファイルを削除
        $expireBefore = time() - (30 * 86400);
        foreach (glob($backupDir . '/*.json') ?: [] as $oldFile) {
            if (is_file($oldFile) && @filemtime($oldFile) < $expireBefore) {
                @unlink($oldFile);
            }
        }
    }

    jsonResponse([
        'ok'       => true,
        'id'       => $id,
        'shortUrl' => $shortUrl,
    ]);
}

jsonResponse(['ok' => false, 'error' => 'Method Not Allowed'], 405);