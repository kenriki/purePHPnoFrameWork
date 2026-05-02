<?php
/**
 * gemini_proxy.php - 決定版
 * v1beta + 2.0-flash 構成
 */
set_time_limit(120);
ini_set('display_errors', 0);
error_reporting(0);
ob_start();

session_start();
header('Content-Type: application/json; charset=utf-8');

// 1. dbconfig.php の読み込み (getDB関数を使用)
require_once '../app/dbconfig.php';

// 2. セッションチェック
if (empty($_SESSION['user_id'])) {
    send_json_error('Unauthorized', 'セッションが無効です。', 401);
}

$userId = $_SESSION['user_id'];

try {
    // 3. getDB() を呼び出し、ユーザーのAPIキーを取得
    $pdo = getDB();
    //$stmt = $pdo->prepare("SELECT gemini_api_key FROM users WHERE id = ?");
    $stmt = $pdo->prepare("SELECT gemini_api_key FROM users WHERE id = 2");
    //$stmt->execute([$userId]);
    $stmt->execute(); // ログイン中の $userId はバインドせず、そのまま実行
    $row = $stmt->fetch();

    if (!$row || empty($row['gemini_api_key'])) {
        send_json_error('Not Found', 'データベースにAPIキーが見つかりません。', 404);
    }

    $apiKey = $row['gemini_api_key'];

} catch (Exception $e) {
    send_json_error('DB Error', 'データベース接続に失敗しました。', 500);
}

// 4. 解析メイン処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['receipt_image'])) {

    //$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $apiKey;
    //$apiUrl = "https://generativelanguage.googleapis.com/v1/models/gemini-1.5-flash:generateContent?key=" . $apiKey;
    //$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey;
    //$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey;
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite-preview:generateContent?key=" . $apiKey;

    $imageTmpPath = $_FILES['receipt_image']['tmp_name'];
    if (!is_uploaded_file($imageTmpPath)) {
        send_json_error('Bad Request', '画像がアップロードされていません。', 400);
    }

    $imageData = base64_encode(file_get_contents($imageTmpPath));
    $mimeType = $_FILES['receipt_image']['type'];

    // リクエストボディ（1.5 でも 2.0 でも共通の構造）
    // $payload のフルセット
    $payload = [
        "contents" => [
            [
                "parts" => [
                    [
                        "text" => "レシートを解析し、以下の項目を日本語で抽出してください。
                        不明な項目は「不明」と記載してください：
                        1. 店舗名
                        2. 電話番号（ハイフンあり）
                        3. 店番またはレジ番号（あれば）
                        4. 日付（YYYY/MM/DD形式）
                        5. 購入品目の一覧（品名、単価、数量、小計をすべて）
                        6. 合計金額"
                    ],
                    [
                        "inline_data" => [
                            "mime_type" => $mimeType,
                            "data" => $imageData
                        ]
                    ]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.2, // データの正確性を高めるために少し下げました
            "topP" => 0.95,
            "maxOutputTokens" => 1024
        ]
    ];

    // --- 修正版 cURL 設定 ---
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 基本はコメントアウト推奨（環境による）
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // 通信エラー（タイムアウトなど）の確認
    if ($response === false) {
        $curlError = curl_error($ch);
        curl_close($ch);
        send_json_error('Internal Server Error', 'cURL Error: ' . $curlError, 500);
    }

    $curlError = curl_error($ch);

    // 5. 出力バッファをクリアし、APIのレスポンスのみを返す
    ob_clean();

    if ($response === false) {
        send_json_error('Internal Server Error', '通信失敗: ' . $curlError, 500);
    }

    http_response_code($httpCode);
    echo $response;
    exit;

} else {
    send_json_error('Method Not Allowed', 'POSTメソッドが必要です。', 405);
}

/**
 * 共通エラーレスポンス
 */
function send_json_error($error, $debugMsg, $code = 500)
{
    if (ob_get_length())
        ob_clean();
    http_response_code($code);
    echo json_encode([
        'error' => $error,
        'debug' => $debugMsg
    ]);
    exit;
}