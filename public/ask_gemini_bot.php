<?php
ini_set('display_errors', 0); // 画面にエラーを出さない
require_once dirname(__DIR__) . '/app/controllers/MemoController.php';
require_once dirname(__DIR__) . '/app/dbconfig.php';

session_start();
header('Content-Type: application/json');

// 1. セッションチェック
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'セッションが無効です。']);
    exit;
}

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$userQuestion = $input['question'] ?? '';

try {
    error_log("UserID: " . $userId . " の処理を開始します");
    $controller = new MemoController();
    $pdo = $pdo = getDB();

    // 2. ログインユーザーの名前を取得 (挨拶用)
    $stmtUser = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $userRow = $stmtUser->fetch();
    $loginUserName = $userRow['username'] ?? '';

    // 3. 管理者(ID: 2)のAPIキーを取得 (共通キー)
    // ※管理者IDが異なる場合はここを調整してください
    $adminId = 2;
    $stmtAdmin = $pdo->prepare("SELECT gemini_api_key FROM users WHERE id = ?");
    $stmtAdmin->execute([$adminId]);
    $adminRow = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

    $apiKey = $adminRow['gemini_api_key'] ?? '';
    if (empty($apiKey)) {
        throw new Exception("管理者のAPIキーが設定されていません。");
    }

    // 4. ログインユーザーの直近メモを取得 (コンテキスト用)
    $memos = $controller->getRecentMemosAll($loginUserName, 30);
    $contextText = implode("\n", $memos);

    if (empty($memos)) {
        throw new Exception("デバッグ: メモが1件も取得できていません。ユーザー名を確認してください: " . $loginUserName);
    }

    // 5. Gemini API プロンプト構築
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

    // $prompt = "あなたは有能なアシスタントです。ユーザー名は {$loginUserName} さんです。\n";
    // $prompt .= "以下のユーザーの過去のメモを参考にして、質問に答えてください。\n\n";
    // $prompt .= "--- 過去のメモ ---\n{$contextText}\n\n";
    // $prompt .= "--- 質問 ---\n{$userQuestion}\n\n";
    // $prompt .= "回答は「こんにちは、{$loginUserName}さん！」から始めてください。";

    // プロンプト構築の改善例
    $prompt = "あなたは有能なパーソナルアシスタントです。ユーザー名は {$loginUserName} さんです。\n";
    $prompt .= "役割: 過去のメモを分析し、ユーザーの質問に対して具体的かつ建設的なアドバイスを行ってください。\n";
    $prompt .= "制約事項:\n";
    $prompt .= "1. 回答は必ず「こんにちは、{$loginUserName}さん！」から始めてください。\n";
    $prompt .= "2. メモにない情報は「推測ですが」と前置きするか、事実のみを述べてください。\n";
    $prompt .= "3. 論理的な回答を心がけてください。\n\n";
    $prompt .= "--- 過去のメモ (直近30件) ---\n{$contextText}\n\n";
    $prompt .= "--- ユーザーからの質問 ---\n{$userQuestion}";

    $data = [
        "contents" => [
            [
                "parts" => [
                    [
                        "text" => $prompt
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.1,    // 0.2よりさらに低くして、JSON構造を壊さないようにする
            'topP' => 0.95,
            'maxOutputTokens' => 4096 // ここを4倍に増やす
            //'responseMimeType' => 'application/json'
        ]
    ];

    // 6. CURL実行
    // --- 6. CURL実行部分を整理 ---
    $ch = curl_init($apiUrl);

    // JSONデータを一度だけ生成
    $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData); // ここでセット
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Gemini API Error: HTTP " . $httpCode . " - " . $response);
    }

    echo $response;

    // } catch (Exception $e) {
//     http_response_code(500);
//     //echo json_encode(['error' => $e->getMessage()]);
//     echo "エラー詳細: " . $e->getMessage();
//     exit;
// }
// } catch (Exception $e) {
//     // 500エラーを返さず、あえて200でエラー内容をJSONとして返す
//     echo json_encode([
//         'candidates' => [
//             [
//                 'content' => [
//                     'parts' => [
//                         ['text' => "【デバッグ】" . $e->getMessage()]
//                     ]
//                 ]
//             ]
//         ]
//     ], JSON_UNESCAPED_UNICODE);
//     exit;
// }
} catch (Exception $e) {
    // エラーメッセージが長すぎる場合は先頭100文字程度に制限する
    $shortMsg = mb_strimwidth($e->getMessage(), 0, 200, "...");
    echo json_encode([
        'candidates' => [
            [
                'content' => [
                    'parts' => [
                        ['text' => "【システムエラー】" . $shortMsg]
                    ]
                ]
            ]
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}