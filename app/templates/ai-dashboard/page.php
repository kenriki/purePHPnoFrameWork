<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<?php
// ==========================================================================
// 1. 名前空間・インポート定義（use文はファイルの最先頭に配置）
// ==========================================================================
use app\utils\GoogleCalendarSync;

// エラー出力をすべて抑制（WarningやNoticeによるJSONの破壊を100%防止）
ini_set('display_errors', '0');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==========================================================================
// 2. バックエンド処理：Fetch通信（AJAX）の判定とAPIリクエスト
// ==========================================================================
if (isset($_GET['action']) && $_GET['action'] === 'ask') {
    // 既存の出力バッファを完全にクリアし、余計な改行やBOM文字が混ざるのを徹底防止
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    // レスポンスヘッダーを純粋なJSONに固定
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');

    try {
        // 1. セッションチェック
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'response' => 'セッションが無効です。再度ログインしてください。'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $userId = $_SESSION['user_id'];
        $userQuestion = trim($_GET['prompt'] ?? '');
        $targetSection = $_GET['target_section'] ?? null;

        // 2. パス解決用のベースディレクトリ設定
        $baseDir = dirname(__DIR__, 2);
        $dbConfigPath = file_exists($baseDir . '/app/dbconfig.php') ? $baseDir . '/app/dbconfig.php' : dirname(__DIR__, 3) . '/app/dbconfig.php';
        $memoControllerPath = file_exists($baseDir . '/app/controllers/MemoController.php') ? $baseDir . '/app/controllers/MemoController.php' : dirname(__DIR__, 3) . '/app/controllers/MemoController.php';
        $calendarSyncPath = file_exists($baseDir . '/app/utils/GoogleCalendarSync.php') ? $baseDir . '/app/utils/GoogleCalendarSync.php' : dirname(__DIR__, 3) . '/app/utils/GoogleCalendarSync.php';

        if (!file_exists($dbConfigPath) || !file_exists($memoControllerPath)) {
            throw new Exception("必要なシステムファイルが見つかりません。");
        }

        // 3. 依存ファイルの読み込み
        require_once $dbConfigPath;
        require_once $memoControllerPath;
        require_once $calendarSyncPath;
        require_once __DIR__ . '/AiManager.php';

        $pdo = getDB();

        // 4. ログインユーザー名取得
        $stmtUser = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmtUser->execute([$userId]);
        $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
        $loginUserName = $userRow['username'] ?? '';

        // 5. 管理者APIキー取得（ID: 2）
        $apiKey = '';

        // まずログイン中ユーザーのキーを確認
        $stmtKey = $pdo->prepare("SELECT gemini_api_key FROM users WHERE id = ?");
        $stmtKey->execute([$userId]);
        $userKeyRow = $stmtKey->fetch(PDO::FETCH_ASSOC);
        $apiKey = $userKeyRow['gemini_api_key'] ?? '';

        // もし空なら、テーブル内でキーが設定されている最初のユーザーから拝借する
        if (empty($apiKey)) {
            $stmtFallback = $pdo->query("SELECT gemini_api_key FROM users WHERE gemini_api_key IS NOT NULL AND gemini_api_key != '' LIMIT 1");
            $fallbackRow = $stmtFallback->fetch(PDO::FETCH_ASSOC);
            $apiKey = $fallbackRow['gemini_api_key'] ?? '';
        }

        if (empty($apiKey)) {
            throw new Exception("有効なGemini APIキーを持つユーザーがデータベース内に見つかりません。");
        }

        // 6. ユーザーの発言を履歴テーブルへ記録
        if ($userQuestion !== '') {
            $stmtSaveUser = $pdo->prepare("INSERT INTO ai_chat_histories (user_id, role, message) VALUES (?, 'user', ?)");
            $stmtSaveUser->execute([$userId, $userQuestion]);
        }

        // 7. 直近の対話履歴を最大6件取得してコンテキスト化
        $stmtHistory = $pdo->prepare("SELECT role, message FROM ai_chat_histories WHERE user_id = ? ORDER BY id DESC LIMIT 6");
        $stmtHistory->execute([$userId]);
        $historyRows = array_reverse($stmtHistory->fetchAll(PDO::FETCH_ASSOC));

        $historyContext = "";
        foreach ($historyRows as $h) {
            $roleLabel = ($h['role'] === 'user') ? 'ユーザー' : 'AI';
            $historyContext .= "{$roleLabel}: {$h['message']}\n";
        }

        // 8. メモデータの取得
        $controller = new MemoController();
        $rawMemos = $controller->getRecentMemosAll($loginUserName, 15);
        $memos = is_array($rawMemos) ? $rawMemos : [];
        $sanitizedMemos = [];

        /** @var array<mixed> $memos */
        foreach ($memos as $memoItem) {
            /** @var mixed $item */
            $item = $memoItem;
            $rawMemo = '';

            if (is_string($item)) {
                $rawMemo = $item;
            } elseif (is_array($item)) {
                $rawMemo = isset($item['memo']) ? (string) $item['memo'] : '';
            } elseif (is_object($item)) {
                $rawMemo = isset($item->memo) ? (string) $item->memo : '';
            }

            $trimmed = mb_strimwidth(strip_tags($rawMemo), 0, 150, "...");
            if (!empty($trimmed)) {
                $sanitizedMemos[] = "・" . $trimmed;
            }
        }
        $contextText = !empty($sanitizedMemos) ? implode("\n", $sanitizedMemos) : "過去のメモなし";

        // 9. Googleカレンダー予定の取得
        $calendarText = "直近7日間の予定なし\n";
        if (class_exists('\app\utils\GoogleCalendarSync')) {
            $calendarSync = new GoogleCalendarSync($pdo);
            $startDate = date('Y-m-d');
            $endDate = date('Y-m-d', strtotime('+7 days'));
            $targetLoginId = $_SESSION['login_id'] ?? $loginUserName;
            $googleEvents = $calendarSync->getEventsForFullCalendar($targetLoginId, $startDate, $endDate);

            if (!empty($googleEvents) && is_array($googleEvents)) {
                $calendarText = "";
                foreach ($googleEvents as $event) {
                    $dateStr = $event['start'] ?? '';
                    $titlePart = $event['title'] ?? '(無題)';
                    $calendarText .= "{$dateStr}: {$titlePart}\n";
                }
            }
        }

        // 10. プロンプトの構築
        $isContinuation = (mb_strpos($userQuestion, '続き') !== false || mb_strpos($userQuestion, 'つづき') !== false);

        if ($isContinuation && !empty($targetSection)) {
            $sectionNames = [
                'p1' => '第1区画: 現状分析と総括',
                'p2' => '第2区画: 業務・開発アドバイス',
                'p3' => '第3区画: スケジュールと学習戦略',
                'p4' => '第4区画: 今日からのアクション'
            ];
            $targetName = $sectionNames[$targetSection] ?? '該当セクション';

            $prompt = "ユーザー名: {$loginUserName}\n\n--- 直近の対話履歴 ---\n{$historyContext}\n";
            $prompt .= "【最重要指示】\n現在ユーザーはダッシュボードの「{$targetName}」を選択して「続き」を求めています。\nこれまでの文脈を引き継ぎ、この特定の枠に【新しく追加・追記する具体的なアドバイス（3行程度）】のみを出力してください。見出しや「---」などの区切り線、装飾文字（**など）は一切不要です。純粋な文章のみで構築してください。\n\n--- 補足データ ---\n[過去のメモ]\n{$contextText}\n\n[スケジュール]\n{$calendarText}\n";
        } else {
            $prompt = "ユーザー名: {$loginUserName}\n質問: 「{$userQuestion}」\n\n--- 直近の対話履歴 ---\n{$historyContext}\n";
            $prompt .= "【最重要指示】上記の質問内容と履歴を踏まえ、以下の4つのセクションについて、要点だけを「3行程度の簡潔な箇条書き」で回答を作成してください。各セクションの間には、必ず「---」という区切り行を1行だけ挟んでください。装飾文字（**など）は使用禁止です。\n\nセクション1: 現状分析と総括 (冒頭に「こんにちは、{$loginUserName}さん！」を含める)\n---\nセクション2: 業務・開発アドバイス\n---\nセクション3: スケジュールと学習戦略\n---\nセクション4: 今日からのアクション\n\n--- 補足データ ---\n[過去のメモ]\n{$contextText}\n\n[スケジュール情報]\n{$calendarText}\n";
        }

        // 11. API呼び出し
        $manager = new AiManager($apiKey);
        $result = $manager->ask('gemini', $prompt);

        // レスポンス文字列の抽出
        $responseText = '';
        if (is_string($result)) {
            $responseText = $result;
        } elseif (isset($result['response'])) {
            $responseText = $result['response'];
        } elseif (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $responseText = $result['candidates'][0]['content']['parts'][0]['text'];
        } else {
            $responseText = json_encode($result, JSON_UNESCAPED_UNICODE);
        }

        $responseText = trim($responseText);

        // 12. AIの応答を履歴テーブルへ記録
        $stmtSaveAi = $pdo->prepare("INSERT INTO ai_chat_histories (user_id, role, message) VALUES (?, 'model', ?)");
        $stmtSaveAi->execute([$userId, $responseText]);

        // 13. クリーンにJSONを返却して強制終了
        echo json_encode([
            'success' => true,
            'response' => $responseText
        ], JSON_UNESCAPED_UNICODE);
        exit;

    } catch (\Throwable $e) {
        echo json_encode([
            'success' => false,
            'response' => '[システムエラー] ' . $e->getMessage() . ' (Line: ' . $e->getLine() . ')'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
// ==========================================================================\
// 予備回線用バックエンド処理：メインが429のときにフロントから自動転送される受け皿
// ==========================================================================\
if (isset($_GET['action']) && $_GET['action'] === 'ask_backup') {
    // 既存の出力バッファを完全にクリアし、余計なエラーや空白の混入を100%防止
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    // レスポンスヘッダーを純粋なJSONに固定
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');

    try {
        // 1. セッションチェック
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'response' => 'セッションが切れています。再度ログインしてください。'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $userId = $_SESSION['user_id'];
        $userQuestion = trim($_POST['prompt'] ?? '');
        $targetSection = $_POST['section'] ?? null;

        // 通常(action=ask)側と同期した、フロントから渡される選択エリアのテキスト
        $currentContent = isset($_POST['current_content']) ? trim($_POST['current_content']) : '';

        if (empty($userQuestion)) {
            echo json_encode([
                'success' => false,
                'response' => 'プロンプトが空です。'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 2. パス解決用のベースディレクトリ設定
        $baseDir = dirname(__DIR__, 2);
        $dbConfigPath = file_exists($baseDir . '/app/dbconfig.php') ? $baseDir . '/app/dbconfig.php' : dirname(__DIR__, 3) . '/app/dbconfig.php';
        $memoControllerPath = file_exists($baseDir . '/app/controllers/MemoController.php') ? $baseDir . '/app/controllers/MemoController.php' : dirname(__DIR__, 3) . '/app/controllers/MemoController.php';
        $calendarSyncPath = file_exists($baseDir . '/app/utils/GoogleCalendarSync.php') ? $baseDir . '/app/utils/GoogleCalendarSync.php' : dirname(__DIR__, 3) . '/app/utils/GoogleCalendarSync.php';

        if (!file_exists($dbConfigPath) || !file_exists($memoControllerPath)) {
            throw new Exception("必要なシステムファイルが見つかりません。");
        }

        // 3. 依存ファイルの読み込み
        require_once $dbConfigPath;
        require_once $memoControllerPath;
        require_once $calendarSyncPath;
        require_once __DIR__ . '/AiManager.php';

        $pdo = getDB();

        // 4. ログインユーザー名取得
        $stmtUser = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmtUser->execute([$userId]);
        $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
        $loginUserName = $userRow['username'] ?? '';

        // 5. 【予備回線専用】ユーザーID: 4 から gemini_api_key を取得
        $backupUserId = 4;
        $stmtKey = $pdo->prepare("SELECT gemini_api_key FROM users WHERE id = ?");
        $stmtKey->execute([$backupUserId]);
        $backupUserRow = $stmtKey->fetch(PDO::FETCH_ASSOC);
        $geminiKey = $backupUserRow['gemini_api_key'] ?? '';

        if (empty($geminiKey)) {
            throw new Exception("バックアップ用のAPI回線キー（ユーザーID:4）が設定されていません。");
        }

        // 6. ユーザーの発言を履歴テーブルへ記録
        if ($userQuestion !== '') {
            $stmtSaveUser = $pdo->prepare("INSERT INTO ai_chat_histories (user_id, role, message) VALUES (?, 'user', ?)");
            $stmtSaveUser->execute([$userId, $userQuestion]);
        }

        // 7. 直近の対話履歴を最大6件取得してコンテキスト化
        $stmtHistory = $pdo->prepare("SELECT role, message FROM ai_chat_histories WHERE user_id = ? ORDER BY id DESC LIMIT 6");
        $stmtHistory->execute([$userId]);
        $historyRows = array_reverse($stmtHistory->fetchAll(PDO::FETCH_ASSOC));

        $historyContext = "";
        foreach ($historyRows as $h) {
            $roleLabel = ($h['role'] === 'user') ? 'ユーザー' : 'AI';
            $historyContext .= "{$roleLabel}: {$h['message']}\n";
        }

        // 8. user_memo から直近15件のメモデータをリクエスト・取得
        $controller = new MemoController();
        $rawMemos = $controller->getRecentMemosAll($loginUserName, 15);
        $memos = is_array($rawMemos) ? $rawMemos : [];
        $sanitizedMemos = [];

        /** @var array<mixed> $memos */
        foreach ($memos as $memoItem) {
            /** @var mixed $item */
            $item = $memoItem;
            $rawMemo = '';

            if (is_string($item)) {
                $rawMemo = $item;
            } elseif (is_array($item)) {
                $rawMemo = isset($item['memo']) ? (string) $item['memo'] : '';
            } elseif (is_object($item)) {
                $rawMemo = isset($item->memo) ? (string) $item->memo : '';
            }

            $trimmed = mb_strimwidth(strip_tags($rawMemo), 0, 150, "...");
            if (!empty($trimmed)) {
                $sanitizedMemos[] = "・" . $trimmed;
            }
        }

        $contextText = !empty($sanitizedMemos) ? implode("\n", $sanitizedMemos) : "過去のメモなし";

        // 9. Googleカレンダー予定の取得
        $calendarText = "直近7日間の予定なし\n";
        if (class_exists('\app\utils\GoogleCalendarSync')) {
            $calendarSync = new GoogleCalendarSync($pdo);
            $startDate = date('Y-m-d');
            $endDate = date('Y-m-d', strtotime('+7 days'));
            $targetLoginId = $_SESSION['login_id'] ?? $loginUserName;
            $googleEvents = $calendarSync->getEventsForFullCalendar($targetLoginId, $startDate, $endDate);

            if (!empty($googleEvents) && is_array($googleEvents)) {
                $calendarText = "";
                foreach ($googleEvents as $event) {
                    $dateStr = $event['start'] ?? '';
                    $titlePart = $event['title'] ?? '(無題)';
                    $calendarText .= "{$dateStr}: {$titlePart}\n";
                }
            }
        }

        // 10. 通常側と完全に同一のロジックでプロンプトを構築
        $isContinuation = (mb_strpos($userQuestion, '続き') !== false || mb_strpos($userQuestion, 'つづき') !== false || $targetSection !== null);

        // JavaScript側から特定の区分（p1〜p4）の指定、または current_content（選択中テキスト）が来ている場合
        if ($isContinuation && (!empty($targetSection) || $currentContent !== '')) {
            $sectionNames = [
                'p1' => '第1区画: 現状分析と総括',
                'p2' => '第2区画: 業務・開発アドバイス',
                'p3' => '第3区画: スケジュールと学習戦略',
                'p4' => '第4区画: 今日からのアクション'
            ];
            $targetName = $sectionNames[$targetSection] ?? '該当セクション';

            $prompt = "ユーザー名: {$loginUserName}\n\n--- 直近の対話履歴 ---\n{$historyContext}\n";

            // 画面上に選択された既存のメモ内容がある場合はコンテキストとして最優先結合
            if ($currentContent !== '') {
                $prompt .= "--- 現在選択されている区画の既存メモテキスト ---\n{$currentContent}\n-------------------------------------------\n\n";
            }

            $prompt .= "【最重要指示】\n現在ユーザーはダッシュボードの「{$targetName}」を選択して、追記や修正、あるいは「続き」を求めています。\nこれまでの対話履歴および提示された既存メモテキストの文脈を100%引き継ぎ、この特定の枠に【新しく追加・追記する具体的なアドバイス（3行程度）】のみを出力してください。見出しや「---」などの区切り線、装飾文字（**など）は一切不要です。純粋な文章のみで構築してください。プライベートなファイルへのアクセス権限等に関するシステム的なお断り・挨拶文は一切含めないでください。\n\n--- 補足データ ---\n[過去のメモ履歴]\n{$contextText}\n\n[スケジュール]\n{$calendarText}\n";
        } else {
            // 新規の4分割生成時のプロンプト
            $prompt = "ユーザー名: {$loginUserName}\n質問: 「{$userQuestion}」\n\n--- 直近の対話履歴 ---\n{$historyContext}\n";
            $prompt .= "【最重要指示】上記の質問内容と履歴を踏まえ、以下の4つのセクションについて、要点だけを「3行程度の簡潔な箇条書き」で回答を作成してください。各セクションの間には、必ず「---」という区切り行を1行だけ挟んでください。装飾文字（**など）は使用禁止です。\n\nセクション1: 現状分析と総括 (冒頭に「こんにちは、{$loginUserName}さん！」を含める)\n---\nセクション2: 業務・開発アドバイス\n---\nセクション3: スケジュールと学習戦略\n---\nセクション4: 今日からのアクション\n\n--- 補足データ ---\n[過去のメモ]\n{$contextText}\n\n[スケジュール情報]\n{$calendarText}\n";
        }

        // 11. 予備APIキーで呼び出し
        $aiManager = new AiManager($geminiKey);
        $result = $aiManager->ask('gemini', $prompt);

        // レスポンス文字列の抽出
        $responseText = '';
        if (is_string($result)) {
            $responseText = $result;
        } elseif (isset($result['response'])) {
            $responseText = $result['response'];
        } elseif (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $responseText = $result['candidates'][0]['content']['parts'][0]['text'];
        } else {
            $responseText = json_encode($result, JSON_UNESCAPED_UNICODE);
        }

        $responseText = trim($responseText);

        if (isset($result['error'])) {
            throw new Exception($result['error']);
        }

        // 12. AIの応答を履歴テーブルへ記録
        $stmtSaveAi = $pdo->prepare("INSERT INTO ai_chat_histories (user_id, role, message) VALUES (?, 'model', ?)");
        $stmtSaveAi->execute([$userId, $responseText]);

        // 13. フロントへ正常返却
        echo json_encode([
            'success' => true,
            'response' => $responseText
        ], JSON_UNESCAPED_UNICODE);
        exit;

    } catch (\Throwable $e) {
        echo json_encode([
            'success' => false,
            'response' => '[システムエラー] ' . $e->getMessage() . ' (Line: ' . $e->getLine() . ')'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ==========================================================================
// Excelダウンロード処理
// ==========================================================================
if (isset($_GET['action']) && $_GET['action'] === 'download_excel') {
    if (ob_get_length())
        ob_end_clean();

    try {
        if (empty($_SESSION['user_id'])) {
            throw new Exception("セッションが切れています。");
        }

        $userId = $_SESSION['user_id'];
        $baseDir = dirname(__DIR__, 2);
        $dbConfigPath = file_exists($baseDir . '/app/dbconfig.php') ? $baseDir . '/app/dbconfig.php' : dirname(__DIR__, 3) . '/app/dbconfig.php';

        require_once $dbConfigPath;
        $pdo = getDB();

        $stmt = $pdo->prepare("SELECT message, created_at FROM ai_chat_histories WHERE user_id = ? AND role = 'model' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$userId]);
        $lastAiRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lastAiRow) {
            throw new Exception("対象となるAI解析結果が見つかりません。");
        }

        $exportData = [
            'id' => (string) $userId,
            'created_at' => $lastAiRow['created_at'],
            'content' => $lastAiRow['message']
        ];

        $tmpJsonFile = tempnam(sys_get_temp_dir(), 'ai_xl_');
        $tmpExcelFile = tempnam(sys_get_temp_dir(), 'ai_out_') . '.xlsx';
        file_put_contents($tmpJsonFile, json_encode($exportData, JSON_UNESCAPED_UNICODE));

        $pythonScriptPath = $baseDir . '/app/scripts/python_excel_gen.py';
        if (!file_exists($pythonScriptPath)) {
            $pythonScriptPath = dirname(__DIR__, 3) . '/app/scripts/python_excel_gen.py';
        }

        $escapedScript = escapeshellarg($pythonScriptPath);
        $escapedJson = escapeshellarg($tmpJsonFile);
        $escapedExcel = escapeshellarg($tmpExcelFile);

        $output = [];
        $returnVar = 0;
        exec("python $escapedScript $escapedJson $escapedExcel 2>&1", $output, $returnVar);

        if ($returnVar !== 0) {
            throw new Exception("Pythonエラー: " . implode("\n", $output));
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="AI_Report_' . date('Ymd_His') . '.xlsx"');
        header('Content-Length: ' . filesize($tmpExcelFile));
        readfile($tmpExcelFile);

        if (file_exists($tmpJsonFile))
            @unlink($tmpJsonFile);
        if (file_exists($tmpExcelFile))
            @unlink($tmpExcelFile);
        exit;

    } catch (\Throwable $e) {
        header('Content-Type: text/html; charset=utf-8');
        echo "<script>alert('Excel出力に失敗しました: " . addslashes($e->getMessage()) . "'); window.close();</script>";
        exit;
    }
}
?>

<style>
    :root {
        --bg-primary: #f7f7fa;
        --bg-surface: #afcab8;
        --bg-surface-hover: #fafafd;
        --text-main: #0d0d0e;
        --text-muted: #94a3b8;
        --accent: #3b82f6;
        --accent-hover: #2563eb;
        --border: #f3f4f5;
        --success: #10b981;
        --success-hover: #059669;
    }

    body {
        background-color: var(--bg-primary);
        color: var(--text-main);
        font-family: 'Segoe UI', Meiryo, sans-serif;
        margin: 0;
        padding: 20px;
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
    }

    .header-panel {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid var(--border);
        padding-bottom: 15px;
        margin-bottom: 25px;
    }

    h1 {
        font-size: 1.5rem;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .btn {
        background-color: var(--accent);
        color: white;
        border: none;
        padding: 10px 18px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: bold;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: background 0.2s;
        text-decoration: none;
        font-size: 0.9rem;
    }

    .btn:hover {
        background-color: var(--accent-hover);
    }

    .btn-success {
        background-color: var(--success);
    }

    .btn-success:hover {
        background-color: var(--success-hover);
    }

    .btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin-bottom: 25px;
    }

    .panel-box {
        background-color: var(--bg-surface);
        border: 2px solid var(--border);
        border-radius: 8px;
        padding: 20px;
        cursor: pointer;
        transition: all 0.2s ease;
        position: relative;
        height: 250px;
        /* min-height を height に変更し、高さを一律固定にする */
        overflow: hidden;
        /* ボックス自体から中身がはみ出るのを防ぐ */
        display: flex;
        /* 中身を縦に綺麗に並べるための設定 */
        flex-direction: column;
    }

    .panel-box:hover {
        border-color: var(--accent);
        background-color: var(--bg-surface-hover);
    }

    .panel-box.selected {
        border-color: var(--success);
        box-shadow: 0 0 12px rgba(16, 185, 129, 0.2);
    }

    .panel-title {
        font-size: 1.05rem;
        font-weight: bold;
        margin-top: 0;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        color: #022950;
    }

    .title-left {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .btn-speak {
        background: transparent;
        border: none;
        color: var(--text-muted);
        cursor: pointer;
        font-size: 1.1rem;
        padding: 4px 8px;
        border-radius: 4px;
        transition: all 0.2s;
    }

    .btn-speak:hover {
        color: var(--accent);
        background-color: rgba(59, 130, 246, 0.1);
    }

    .panel-content {
        font-size: 0.95rem;
        line-height: 1.6;
        color: var(--text-main);
        white-space: pre-wrap;
        flex: 1;
        /* ボックス内の残りの高さを目一杯使う */
        overflow-y: auto;
        /* 文字が溢れた時だけ、自動で縦スクロールバーを出す */
        padding-right: 5px;
        /* スクロールバーと文字が被らないための余白 */
    }

    .selection-badge {
        position: absolute;
        bottom: 15px;
        right: 15px;
        font-size: 0.75rem;
        padding: 3px 8px;
        border-radius: 4px;
        background: var(--border);
        color: var(--text-muted);
    }

    .panel-box.selected .selection-badge {
        background: var(--success);
        color: white;
    }

    .input-area {
        background-color: var(--bg-surface);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 20px;
    }

    .input-row {
        display: flex;
        gap: 15px;
    }

    textarea {
        flex: 1;
        background-color: var(--bg-primary);
        color: var(--text-main);
        border: 1px solid var(--border);
        border-radius: 6px;
        padding: 12px;
        font-size: 0.95rem;
        resize: vertical;
        height: 54px;
    }

    textarea:focus {
        outline: none;
        border-color: var(--accent);
    }

    .tip-text {
        font-size: 0.8rem;
        color: var(--text-muted);
        margin-top: 8px;
        margin-bottom: 0;
    }
</style>

<div class="container">
    <div class="header-panel">
        <h1><i class="fa-solid fa-chart-pie" style="color:#3b82f6;"></i> AI 状況分析ダッシュボード</h1>
        <div>
            <button id="btn-export-excel" class="btn btn-success" title="最新のAI分析結果をExcelで出力します">
                <i class="fas fa-file-excel"></i> 分析結果をExcel出力
            </button>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="panel-box" id="box-p1" onclick="handleBoxClick(event, 'p1')">
            <span class="selection-badge">未選択</span>
            <div class="panel-title">
                <div class="title-left"><i class="fa-solid fa-clipboard-check" style="color:#60a5fa;"></i> 現状分析と総括</div>
                <button class="btn-speak" onclick="speakSection(event, 'content-p1')" title="このセクションを読み上げる">
                    <i class="fa-solid fa-volume-high"></i>
                </button>
            </div>
            <div class="panel-content" id="content-p1"><span
                    style="color:var(--text-muted);">質問を入力してダッシュボードを生成してください。</span></div>
        </div>

        <div class="panel-box" id="box-p2" onclick="handleBoxClick(event, 'p2')">
            <span class="selection-badge">未選択</span>
            <div class="panel-title">
                <div class="title-left"><i class="fa-solid fa-code" style="color:#34d399;"></i> 業務・開発アドバイス</div>
                <button class="btn-speak" onclick="speakSection(event, 'content-p2')" title="このセクションを読み上げる">
                    <i class="fa-solid fa-volume-high"></i>
                </button>
            </div>
            <div class="panel-content" id="content-p2"><span style="color:var(--text-muted);">---</span></div>
        </div>

        <div class="panel-box" id="box-p3" onclick="handleBoxClick(event, 'p3')">
            <span class="selection-badge">未選択</span>
            <div class="panel-title">
                <div class="title-left"><i class="fa-solid fa-calendar-days" style="color:#fbbf24;"></i> スケジュールと学習戦略
                </div>
                <button class="btn-speak" onclick="speakSection(event, 'content-p3')" title="このセクションを読み上げる">
                    <i class="fa-solid fa-volume-high"></i>
                </button>
            </div>
            <div class="panel-content" id="content-p3"><span style="color:var(--text-muted);">---</span></div>
        </div>

        <div class="panel-box" id="box-p4" onclick="handleBoxClick(event, 'p4')">
            <span class="selection-badge">未選択</span>
            <div class="panel-title">
                <div class="title-left"><i class="fa-solid fa-bolt" style="color:#f87171;"></i> 今日からのアクション</div>
                <button class="btn-speak" onclick="speakSection(event, 'content-p4')" title="このセクションを読み上げる">
                    <i class="fa-solid fa-volume-high"></i>
                </button>
            </div>
            <div class="panel-content" id="content-p4"><span style="color:var(--text-muted);">---</span></div>
        </div>
    </div>

    <div class="input-area">
        <div class="input-row">
            <input type="hidden" id="target-section-val" value="">
            <textarea id="prompt-input" placeholder="質問文を入力するか、特定の区画を選択して『続きをください』と入力してください..."></textarea>
            <button class="btn" id="btn-submit" onclick="askDashboardSingle()">
                <i class="fa-solid fa-paper-plane"></i> 送信
            </button>
        </div>
        <p class="tip-text">
            💡 <strong>通常会話:</strong> 自由にメッセージを入力して送信すると全体が更新されます。<br>
            💡 <strong>続きの追記:</strong> いずれかの区画をクリックして選択状態にし、「続きをください」と入力して送信するとその枠だけに情報が追記されます。
        </p>
    </div>
    <form action="update_settings.php" method="POST" onsubmit="return confirmApiKeySave()">
        <label>Gemini API Key:</label>
        <input type="password" name="api_key" value="<?= htmlspecialchars($user['gemini_api_key'] ?? '') ?>"
            placeholder="AIza...">
        <p style="font-size: 0.8rem; color: #666;">
            ※キーは <a href="https://aistudio.google.com/" target="_blank">Google AI Studio</a> で取得してください。たくさん使いたい人は、自身のキーを登録してください。
        </p>
        <button type="submit">設定を保存</button>
    </form>
</div>
<input type="hidden" id="raw_ai_data"
    value="<?php echo htmlspecialchars($_SESSION['latest_ai_response'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
<script>
    let currentSelectedSection = null;

    function handleBoxClick(event, sectionId) {
        if (event.target.closest('.btn-speak')) {
            return;
        }
        selectSection(sectionId);
    }

    function confirmApiKeySave() {
        // ユーザーに確認を求めるダイアログを表示
        const result = confirm("Gemini APIキーを変更します。よろしいですか？\n※誤ったキーを登録すると、ダッシュボードのAI分析機能が動作しなくなります。");

        // 「OK」なら true を返して送信、「キャンセル」なら false を返して送信を中止
        return result;
    }

    function selectSection(sectionId) {
        const hiddenInput = document.getElementById('target-section-val');

        if (currentSelectedSection === sectionId) {
            document.getElementById(`box-${sectionId}`).classList.remove('selected');
            document.getElementById(`box-${sectionId}`).querySelector('.selection-badge').innerText = '未選択';
            currentSelectedSection = null;
            hiddenInput.value = '';
            return;
        }

        ['p1', 'p2', 'p3', 'p4'].forEach(id => {
            const box = document.getElementById(`box-${id}`);
            if (box) {
                box.classList.remove('selected');
                box.querySelector('.selection-badge').innerText = '未選択';
            }
        });

        const targetBox = document.getElementById(`box-${sectionId}`);
        if (targetBox) {
            targetBox.classList.add('selected');
            targetBox.querySelector('.selection-badge').innerText = '対象';
            currentSelectedSection = sectionId;
            hiddenInput.value = sectionId;
        }
    }

    function speakSection(event, elementId) {
        event.stopPropagation();

        const text = document.getElementById(elementId).innerText.trim();
        if (!text || text.includes('質問を入力して') || text === '---') {
            return;
        }

        if ('speechSynthesis' in window) {
            window.speechSynthesis.cancel();
        } else {
            alert('お使いのブラウザは音声読み上げ機能に対応していません。');
            return;
        }

        const utterance = new SpeechSynthesisUtterance(text);
        const voices = window.speechSynthesis.getVoices();
        const japaneseVoice = voices.find(voice => voice.lang === 'ja-JP' || voice.lang.includes('ja'));
        if (japaneseVoice) {
            utterance.voice = japaneseVoice;
        }

        utterance.lang = 'ja-JP';
        utterance.rate = 1.0;
        utterance.pitch = 1.0;

        window.speechSynthesis.speak(utterance);
    }

    async function askDashboardSingle() {
        const promptInput = document.getElementById('prompt-input');
        if (!promptInput) return;

        const prompt = promptInput.value.trim();
        if (!prompt) return;

        const p1 = document.getElementById('content-p1');
        const p2 = document.getElementById('content-p2');
        const p3 = document.getElementById('content-p3');
        const p4 = document.getElementById('content-p4');
        const allElements = [p1, p2, p3, p4];

        // 進行状況の可視化
        if (currentSelectedSection) {
            const targetEl = document.getElementById(`content-${currentSelectedSection}`);
            if (targetEl) {
                // 既存のHTMLを壊さないよう、末尾に安全に要素を追加する
                const spinner = document.createElement('div');
                spinner.id = 'temp-tracker-spinner';
                spinner.style.color = '#666';
                spinner.style.marginTop = '10px';
                spinner.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 追記を分析中...';
                targetEl.appendChild(spinner);
            }
        } else {
            allElements.forEach(el => {
                if (el) el.innerHTML = '<div style="color: #666;"><i class="fas fa-spinner fa-spin"></i> AIが分析中...</div>';
            });
        }

        try {
            const targetSection = currentSelectedSection || '';

            // パラメータの構成
            const params = new URLSearchParams();
            params.append('prompt', prompt);
            params.append('section', targetSection);
            params.append('current_content', targetSection ? document.getElementById(`content-${targetSection}`).innerText : '');

            // 1. まず試す通常用URL
            let targetUrl = `index.php?page=ai-dashboard&action=ask`;
            let response;
            let responseText = '';
            let isQuotaError = false;

            // 【1回目のリクエスト】通常URLへ送信
            response = await fetch(targetUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: params.toString()
            });

            // 429ステータス、または正常(200)だがPHPが429系の解析エラーテキストを返したかをチェック
            if (response.status === 429) {
                isQuotaError = true;
            } else if (response.ok) {
                responseText = await response.text();
                if (responseText.includes('429') || responseText.includes('quota') || responseText.includes('RESOURCE_EXHAUSTED')) {
                    isQuotaError = true;
                }
            }

            // 🔄 429制限を検知した場合：裏で自動的に「バックアップ用URL」へ切り替えて再実行！
            if (isQuotaError) {
                console.warn("メインAPIキーが制限（429）に達したため、バックアップURLへ切り替えて再試行します。");

                // 進行状況表示のテキストを「バックアップで再試行中」にマイルドに変更
                const tempSpinner = document.getElementById('temp-tracker-spinner');
                if (tempSpinner) {
                    tempSpinner.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 予備回線で追記を再分析中...';
                }

                // 予備のアクションURLに差し替え
                targetUrl = `index.php?page=ai-dashboard&action=ask_backup`;

                // 【2回目のリクエスト】自動リトライ
                response = await fetch(targetUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: params.toString()
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                responseText = await response.text();
            } else {
                if (!responseText) {
                    responseText = await response.text();
                }
            }

            if (!response.ok && !isQuotaError) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            // 🌟 テキストの受信が完了したので、描画する前にスピナーを画面から完全に消し去る
            const tempSpinner = document.getElementById('temp-tracker-spinner');
            if (tempSpinner) {
                tempSpinner.remove();
            }

            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error("JSONパース失敗。返却データ生テキスト:", responseText);
                allElements.forEach(el => {
                    if (el) el.innerHTML = `<span style="color: red; font-size:12px;">[解析エラー] データ構造が崩れています。<br>冒頭データ: ${responseText.substring(0, 80)}</span>`;
                });
                return;
            }

            if (!data.success) {
                const isBackupQuota = (String(data.response).includes('429') || String(data.response).includes('quota') || String(data.response).includes('RESOURCE_EXHAUSTED'));
                const errorMsg = isBackupQuota ? 'すべてのAPI無料利用枠を超過しました。しばらく時間を置いてから再度お試しください。' : data.response;

                if (currentSelectedSection) {
                    const activeEl = document.getElementById(`content-${currentSelectedSection}`);
                    if (activeEl) activeEl.innerHTML += `<br><br><span style="color: #ef4444; font-size: 13px; font-weight: bold;"><i class="fas fa-exclamation-triangle"></i> ${errorMsg}</span>`;
                } else {
                    allElements.forEach(el => {
                        if (el) el.innerHTML = `<span style="color: red; font-size:12px;">[エラー] ${errorMsg}</span>`;
                    });
                }
                return;
            }

            const aiResponse = data.response;

            // 4分割処理か追記処理かの判定
            if (aiResponse.includes('---') && !targetSection) {
                // 通常の新規4分割生成時
                const sections = aiResponse.split(/\s*---\s*/);
                allElements.forEach((el, index) => {
                    if (el && sections[index]) {
                        let content = sections[index].trim();
                        content = content.replace(/^セクション\s*\d+\s*:\s*.*$/mi, '');
                        content = content.replace(/^第\s*\d+\s*区画\s*:\s*.*$/mi, '');
                        el.innerHTML = content.trim().replace(/\n/g, '<br>');
                    }
                });

                // 🔄 吐き出された時点で、隠し要素とlocalStorageに生データを即時保存
                const rawAiInput = document.getElementById('raw_ai_data');
                if (rawAiInput) {
                    rawAiInput.value = aiResponse;
                }
                localStorage.setItem('latest_ai_dashboard_response', aiResponse);

            } else {
                // 部分追記処理時
                const activeEl = document.getElementById(`content-${targetSection}`);
                if (activeEl) {
                    if (activeEl.innerHTML.includes('AIが分析中...')) {
                        activeEl.innerHTML = '';
                    }
                    // [追記] として綺麗に結合
                    activeEl.innerHTML += (activeEl.innerHTML ? '<br><br><strong>[追記]:</strong><br>' : '') + aiResponse.replace(/\n/g, '<br>');

                    // 🔄 部分追記された場合、画面上の現在の各区画テキストを「---」で再結合して、生データ形式でlocalStorage/隠し要素を上書きアップデートする
                    const cleanSectionText = (text) => {
                        if (!text) return '';
                        return String(text)
                            .replace(/\[追記\]:/g, '')
                            .trim();
                    };

                    const updatedP1 = cleanSectionText(p1 ? p1.innerText : '');
                    const updatedP2 = cleanSectionText(p2 ? p2.innerText : '');
                    const updatedP3 = cleanSectionText(p3 ? p3.innerText : '');
                    const updatedP4 = cleanSectionText(p4 ? p4.innerText : '');

                    // 元の4分割パース構造に擬似再構成
                    const reconstructedRawData = `第1区画: 現状分析と総括\n${updatedP1}\n---\n第2区画: 業務・開発アドバイス\n${updatedP2}\n---\n第3区画: スケジュールと学習戦略\n${updatedP3}\n---\n第4区画: 今日からのアクション\n${updatedP4}`;

                    const rawAiInput = document.getElementById('raw_ai_data');
                    if (rawAiInput) {
                        rawAiInput.value = reconstructedRawData;
                    }
                    localStorage.setItem('latest_ai_dashboard_response', reconstructedRawData);
                } else {
                    if (p1) p1.innerHTML = aiResponse.replace(/\n/g, '<br>');
                }
            }

            promptInput.value = '';

        } catch (error) {
            console.error("Fetch Error:", error);
            const tempSpinner = document.getElementById('temp-tracker-spinner');
            if (tempSpinner) tempSpinner.remove();

            allElements.forEach(el => {
                if (el) el.innerHTML = `<span style="color: red; font-size:12px;">[通信エラー] サーバーと接続できませんでした。</span>`;
            });
        }
    }

    document.getElementById('prompt-input').addEventListener('keydown', function (e) {
        if (e.ctrlKey && e.key === 'Enter') {
            askDashboardSingle();
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        // ==========================================================================
        // 【復元ロジック】ページ読み込み時にlocalStorageから直前のデータを復元
        // ==========================================================================
        const savedAiData = localStorage.getItem('latest_ai_dashboard_response');
        if (savedAiData) {
            const rawAiInput = document.getElementById('raw_ai_data');
            if (rawAiInput) {
                rawAiInput.value = savedAiData;
            }

            const sections = savedAiData.split(/\s*---\s*/);
            const cleanSectionText = (text) => {
                if (!text) return '';
                return String(text)
                    .replace(/^セクション\s*\d+\s*:\s*.*$/mi, '')
                    .replace(/^第\s*\d+\s*区画\s*:\s*.*$/mi, '')
                    .trim();
            };

            for (let i = 1; i <= 4; i++) {
                const pEl = document.getElementById(`content-p${i}`);
                if (pEl && sections[i - 1]) {
                    const cleanText = cleanSectionText(sections[i - 1]);
                    // 改行コードを <br> にして流し込み
                    pEl.innerHTML = cleanText.replace(/\n/g, '<br>');
                }
            }
            console.log("ローカルストレージから直前のAI分析データを画面へ復元しました。");
        }

        const exportBtn = document.getElementById('btn-export-excel');

        // ==========================================================================
        // Excel出力処理：追記分も含めてすべてのテキストを確実に全回収する
        // ==========================================================================
        if (exportBtn) {
            exportBtn.addEventListener('click', async function (e) {
                e.preventDefault();

                // ボタンの二重クリック防止とローディング表示
                const originalHtml = exportBtn.innerHTML;
                exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>エクセル出力中...';
                exportBtn.style.pointerEvents = 'none';

                try {
                    // 隠し要素からPHP側が保持した生のAIレスポンスを取得
                    const rawAiInput = document.getElementById('raw_ai_data');
                    const aiResponseText = rawAiInput ? rawAiInput.value.trim() : '';

                    if (!aiResponseText) {
                        throw new Error('出力するAI分析データがありません。画面生成後に実行してください。');
                    }

                    // 通常時と同一のロジックで「---」で4つのセクションに分割
                    const sections = aiResponseText.split(/\s*---\s*/);

                    // 各区画のデータを抽出（見出しなどの余計なヘッダー行をトリムするだけのシンプルなルール）
                    const cleanSectionText = (text) => {
                        if (!text) return '';
                        return String(text)
                            .replace(/^セクション\s*\d+\s*:\s*.*$/mi, '')
                            .replace(/^第\s*\d+\s*区画\s*:\s*.*$/mi, '')
                            .trim();
                    };

                    // ★ 100% 純粋な文字列データとして抽出（型変換の徹底）
                    const p1Data = String(cleanSectionText(sections[0] || ''));
                    const p2Data = String(cleanSectionText(sections[1] || ''));
                    const p3Data = String(cleanSectionText(sections[2] || ''));
                    const p4Data = String(cleanSectionText(sections[3] || ''));

                    // ExcelJSワークブックの新規作成
                    const workbook = new ExcelJS.Workbook();
                    const worksheet = workbook.addWorksheet('AI分析レポート');

                    // グリッド線を表示
                    worksheet.views = [{ showGridLines: true }];

                    // A列〜D列の基本列幅を設定
                    worksheet.columns = [
                        { key: 'sec1', width: 45 },
                        { key: 'sec2', width: 45 },
                        { key: 'sec3', width: 45 },
                        { key: 'sec4', width: 45 }
                    ];

                    // スタイルの共通定義
                    const fontTitle = { name: 'Meiryo', size: 16, bold: true, color: { argb: 'FFFFFFFF' } };
                    const fontHeader = { name: 'Meiryo', size: 11, bold: true, color: { argb: 'FFFFFFFF' } };
                    const fontBody = { name: 'Meiryo', size: 10, color: { argb: 'FF333333' } };

                    const fillTitle = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF1F497D' } }; // 濃い青
                    const fillHeader = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF366092' } }; // 中濃の青
                    const fillBody = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF2F5F8' } };   // 薄いグレー青

                    const borderThin = {
                        top: { style: 'thin', color: { argb: 'FFD9D9D9' } },
                        left: { style: 'thin', color: { argb: 'FFD9D9D9' } },
                        bottom: { style: 'thin', color: { argb: 'FFD9D9D9' } },
                        right: { style: 'thin', color: { argb: 'FFD9D9D9' } }
                    };

                    const alignCenter = { vertical: 'middle', horizontal: 'center' };
                    const alignLeftTop = { vertical: 'top', horizontal: 'left', wrapText: true };

                    // --- 1行目: メインタイトル ---
                    worksheet.mergeCells('A1:D1');
                    const titleCell = worksheet.getCell('A1');
                    titleCell.value = 'AI 週次ビジネス analysis レポート';
                    titleCell.font = fontTitle;
                    titleCell.fill = fillTitle;
                    titleCell.alignment = alignCenter;
                    worksheet.getRow(1).height = 45;

                    // 空白行（2行目）
                    worksheet.getRow(2).height = 15;

                    // --- 3行目: ヘッダー行（各区画のタイトル） ---
                    const headers = [
                        '第1区画: 現状分析と総括',
                        '第2区画: 業務・開発アドバイス',
                        '第3区画: スケジュールと学習戦略',
                        '第4区画: 今日からのアクション'
                    ];

                    const headerRow = worksheet.getRow(3);
                    headerRow.height = 30;

                    for (let i = 1; i <= 4; i++) {
                        const cell = headerRow.getCell(i);
                        cell.value = headers[i - 1];
                        cell.font = fontHeader;
                        cell.fill = fillHeader;
                        cell.alignment = alignCenter;
                        cell.border = borderThin;
                    }

                    // --- 4行目: データ行（型崩れを起こさないシンプルな流し込み） ---
                    const dataRow = worksheet.getRow(4);
                    dataRow.height = 250; // ⭕ 迷子にならないよう、高さをあらかじめ固定値で安全に確保

                    // 4つのセルに、純粋な文字列変数を1つずつ代入
                    const contents = [p1Data, p2Data, p3Data, p4Data];
                    for (let i = 1; i <= 4; i++) {
                        const cell = dataRow.getCell(i);
                        cell.value = contents[i - 1]; // ⭕ [object Object]化を防ぎ、文字列のまま確実に書き込み
                        cell.font = fontBody;
                        cell.fill = fillBody;
                        cell.alignment = alignLeftTop;
                        cell.border = borderThin;
                    }

                    // Excelデータのバイナリ（Blob）を生成
                    const uint8Array = await workbook.xlsx.writeBuffer();
                    const blob = new Blob([uint8Array], {
                        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                    });

                    // ダウンロード発火
                    const blobUrl = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = blobUrl;

                    const now = new Date();
                    const ymdhis = now.getFullYear() +
                        String(now.getMonth() + 1).padStart(2, '0') +
                        String(now.getDate()).padStart(2, '0') + '_' +
                        String(now.getHours()).padStart(2, '0') +
                        String(now.getMinutes()).padStart(2, '0') +
                        String(now.getSeconds()).padStart(2, '0');

                    a.download = 'AI_Report_' + ymdhis + '.xlsx';
                    document.body.appendChild(a);
                    a.click();

                    setTimeout(() => {
                        document.body.removeChild(a);
                        window.URL.revokeObjectURL(blobUrl);
                    }, 1000);

                } catch (error) {
                    console.error('Excel出力エラー:', error);
                    alert(error.message || 'Excel出力中にエラーが発生しました。');
                } finally {
                    // ボタンの状態を復元
                    exportBtn.innerHTML = originalHtml;
                    exportBtn.style.pointerEvents = 'auto';
                }
            });
        }
    });
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
