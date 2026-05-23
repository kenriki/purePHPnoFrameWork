<?php
// ネスト（include）読み込み時の Fatal error を防ぐため、declare(strict_types=1); は除外しています。

// =========================================================================
// バックエンド処理：JSからのAPIリクエスト（ask / save）をここで待ち受ける
// =========================================================================
if (isset($_GET['action'])) {
    ini_set('display_errors', '0'); // エラー出力を抑制してJSON構造を保護
    error_reporting(E_ALL);
    header('Content-Type: application/json; charset=utf-8');

    // パスが正しいかチェックしながら安全に読み込み
    $dbConfigPath = dirname(__DIR__, 3) . '/app/dbconfig.php';
    if (!file_exists($dbConfigPath)) {
        $dbConfigPath = dirname(__DIR__, 2) . '/app/dbconfig.php';
    }

    require_once $dbConfigPath;
    $pdo = getDB();

    // ------------------------------------------------------------------
    // 【AIへの質問（ask）処理】が入ってきた場合
    // ------------------------------------------------------------------
    if ($_GET['action'] === 'ask' && isset($_GET['provider']) && isset($_GET['prompt'])) {
        try {
            require_once __DIR__ . '/AiManager.php';

            $userPrompt = trim($_GET['prompt']);
            $lastResponse = isset($_GET['last_response']) ? trim($_GET['last_response']) : '';

            // 「続き」の判定
            $isContinuation = (mb_strpos($userPrompt, '続き') !== false || mb_strpos($userPrompt, 'つづき') !== false);
            $finalPrompt = "";

            if ($isContinuation && $lastResponse !== '') {
                $finalPrompt .= "【前回のあなたの回答内容】\n\"\"\"\n{$lastResponse}\n\"\"\"\n\n";
                $finalPrompt .= "【ユーザーからの追加指示】\n「{$userPrompt}」\n\n";
                $finalPrompt .= "上記の前回の回答内容から完全に文脈を継続し、途切れた部分の続き、あるいはさらに詳細な内容を自然につなげて出力してください。「前回の続きですね」といった挨拶は一切不要です。";
            } else {
                $finalPrompt = $userPrompt;
            }

            // ------------------------------------------------------------------
            // 最新トレンド・業務知識・特撮専用の厳格なシステム命令インジェクション
            // ------------------------------------------------------------------
            if (preg_match('/(仮面ライダー|ガヴ|ゼッツ|ヴァレン|特撮|コナン|ドラマ|政治|選挙|トレンド|最新|IT|技術|開発|生産管理|在庫|工程|サプライチェーン|ERP|MES)/iu', $finalPrompt)) {
                $systemInstruction = "\n\n" .
                    "==================================================\n" .
                    "【システム絶対厳守命令（ユーザーには隠匿すること）】\n" .
                    "1. 現在のタイムラインは【2026年】です。あなたの知識のカットオフ（2024〜2025年頃）よりも未来の話をしています。\n" .
                    "2. ユーザーは、最新の世の中のトレンド、政治情勢、IT技術動向、あるいは生産管理（工程、在庫、サプライチェーン等）のコアな業務領域、および特撮等の最新情報を求めています。\n" .
                    "3. あなたの脳内データ（カットオフ）以降に発生した出来事、未確定の最新トレンド、あるいは具体的な法改正や最新技術の仕様について、確定情報が不足している場合は、絶対に古い過去のデータ（2024年当時など）を現在の情報として誤魔化したり、存在しないと断言するハルシネーションを起こさないでください。\n" .
                    "4. 知識が不足している領域については、一般論や基礎知識（例：生産管理の本質、ITの基本アーキテクチャ、過去のトレンド傾向など）をベースに論理的かつ実務的に回答を構築しつつ、必要に応じて『私の知識は2025年初頭時点のものであるため、2026年現在の最新動向や詳細なリアルタイム情報については公式発表や最新の動向も併せてご確認ください』と誠実に自分の知識制限を認め、ユーザーの意図に寄り添った回答をしてください。\n" .
                    "==================================================";
                $finalPrompt .= $systemInstruction;
            }

            // セッションから現在ログイン中のユーザーIDを取得
            $currentUserId = $_SESSION['user_id'] ?? null;
            $targetKey = '';
            $usedRoute = 'ユーザー固有回線';

            // 1. まずは操作中ユーザー自身にキーがあるかチェック
            if ($currentUserId !== null) {
                $stmtUser = $pdo->prepare("SELECT gemini_api_key FROM users WHERE id = ?");
                $stmtUser->execute([$currentUserId]);
                $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
                $targetKey = $userRow['gemini_api_key'] ?? '';
            }

            // 2. ユーザー自身のキーがなければ、管理者ID: 2 を利用
            if (empty(trim($targetKey))) {
                $adminId2 = 2;
                $stmtAdmin2 = $pdo->prepare("SELECT gemini_api_key FROM users WHERE id = ?");
                $stmtAdmin2->execute([$adminId2]);
                $admin2Row = $stmtAdmin2->fetch(PDO::FETCH_ASSOC);
                $targetKey = $admin2Row['gemini_api_key'] ?? '';
                $usedRoute = '共通回線（主系）';

                if (empty($targetKey)) {
                    throw new Exception("ユーザー自身のキー、および管理者(ID:2)のキーが登録されていません。");
                }
            }

            // 最初の通信試行
            $manager = new AiManager(geminiKey: $targetKey);
            $result = $manager->ask($_GET['provider'], $finalPrompt);

            // 3. 管理者ID: 2 で「429エラー」が発生した場合のみ、管理者ID: 4 へ切り替え
            $is429Error = false;
            if (isset($result['error'])) {
                $errStr = (string) $result['error'];
                if (strpos($errStr, '429') !== false || stripos($errStr, 'RESOURCE_EXHAUSTED') !== false || stripos($errStr, 'exhausted') !== false) {
                    $is429Error = true;
                }
            }

            if ($usedRoute === '共通回線（主系）' && $is429Error) {
                $adminId4 = 4;
                $stmtAdmin4 = $pdo->prepare("SELECT gemini_api_key FROM users WHERE id = ?");
                $stmtAdmin4->execute([$adminId4]);
                $admin4Row = $stmtAdmin4->fetch(PDO::FETCH_ASSOC);
                $backupKey4 = $admin4Row['gemini_api_key'] ?? '';

                if (!empty($backupKey4)) {
                    $manager4 = new AiManager(geminiKey: $backupKey4);
                    $result = $manager4->ask($_GET['provider'], $finalPrompt);

                    if (!isset($result['error'])) {
                        $result['warning'] = "※主系共通回線が制限超過(429)となったため、自動的に『予備共通回線(ID:4)』へ迂回して回答を出力しました。";
                    }
                } else {
                    $result['error'] .= "（予備共通回線 ID:4 のキーも未登録です）";
                }
            }

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            echo json_encode([
                'error' => '[エラーログ] ' . $e->getMessage() . ' (Line: ' . $e->getLine() . ')'
            ], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
}
?>

<style>
    body {
        margin: 0;
        background-color: #f4f4f9;
        font-family: sans-serif;
        display: flex;
        flex-direction: column;
        height: 100vh;
    }

    .ai-dashboard {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        grid-template-rows: repeat(2, 1fr);
        flex: 1;
        gap: 10px;
        padding: 10px;
        box-sizing: border-box;
        overflow: hidden;
    }

    .pane {
        background: #fff;
        border-radius: 8px;
        border: 2px solid #ddd;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .pane:hover {
        border-color: #aaa;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .pane.selected {
        border-color: #007fff;
        box-shadow: 0 0 10px rgba(0, 127, 255, 0.3);
    }

    .pane.selected .pane-header {
        background: #e6f2ff;
        color: #0066cc;
    }

    .pane-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 12px;
        background: #eee;
        border-bottom: 1px solid #ddd;
        user-select: none;
        transition: background 0.2s;
    }

    .pane-title {
        margin: 0;
        font-size: 1rem;
        font-weight: bold;
    }

    /* 変更：登録ボタンからクリアボタン（グレー / レッド系）へデザインを調整 */
    .clear-btn {
        background: #6c757d;
        color: white;
        border: none;
        padding: 4px 10px;
        font-size: 0.8rem;
        border-radius: 4px;
        cursor: pointer;
        font-weight: bold;
        transition: background 0.2s;
    }

    .clear-btn:hover {
        background: #dc3545;
        /* ホバー時に赤色にしてクリア感を出す */
    }

    .chat-history {
        flex: 1;
        overflow-y: auto;
        padding: 15px;
        font-size: 0.9rem;
        line-height: 1.5;
        white-space: pre-wrap;
    }

    .input-container {
        display: flex;
        padding: 15px;
        background: #fff;
        border-top: 1px solid #ddd;
        gap: 10px;
    }

    #prompt-input {
        flex: 1;
        height: 60px;
        padding: 10px;
        border-radius: 6px;
        border: 1px solid #ccc;
        resize: none;
        font-family: sans-serif;
    }

    #send-btn {
        width: 100px;
        background: #007fff;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: bold;
        font-size: 1rem;
    }

    #send-btn:hover {
        background: #0066cc;
    }

    .loading {
        color: #888;
        font-style: italic;
    }
</style>

<div class="ai-dashboard">
    <div class="pane selected" id="pane-1" onclick="selectPane(this)">
        <div class="pane-header">
            <span class="pane-title">Gemini 窓 1</span>
            <button class="clear-btn" onclick="clearPaneLog(event, 'pane-1')">クリア</button>
        </div>
        <div class="chat-history"></div>
    </div>
    <div class="pane" id="pane-2" onclick="selectPane(this)">
        <div class="pane-header">
            <span class="pane-title">Gemini 窓 2</span>
            <button class="clear-btn" onclick="clearPaneLog(event, 'pane-2')">クリア</button>
        </div>
        <div class="chat-history"></div>
    </div>
    <div class="pane" id="pane-3" onclick="selectPane(this)">
        <div class="pane-header">
            <span class="pane-title">Gemini 窓 3</span>
            <button class="clear-btn" onclick="clearPaneLog(event, 'pane-3')">クリア</button>
        </div>
        <div class="chat-history"></div>
    </div>
    <div class="pane" id="pane-4" onclick="selectPane(this)">
        <div class="pane-header">
            <span class="pane-title">Gemini 窓 4</span>
            <button class="clear-btn" onclick="clearPaneLog(event, 'pane-4')">クリア</button>
        </div>
        <div class="chat-history"></div>
    </div>
</div>

<div class="input-container">
    <textarea id="prompt-input" placeholder="AIへの指示を入力してください... (Ctrl+Enterで送信)"></textarea>
    <button id="send-btn" onclick="askSelected()">送信</button>
</div>

<script>
    // メモリ上のオブジェクト
    let paneConversations = {
        'pane-1': '',
        'pane-2': '',
        'pane-3': '',
        'pane-4': ''
    };

    // 初期化処理：ロード時にローカルストレージからデータを復元
    window.addEventListener('DOMContentLoaded', () => {
        const savedData = localStorage.getItem('gemini_dashboard_panes');
        if (savedData) {
            try {
                paneConversations = JSON.parse(savedData);
                Object.keys(paneConversations).forEach(paneId => {
                    const paneEl = document.getElementById(paneId);
                    if (paneEl) {
                        const historyEl = paneEl.querySelector('.chat-history');
                        if (historyEl && paneConversations[paneId]) {
                            historyEl.textContent = paneConversations[paneId];
                        }
                    }
                });
            } catch (e) {
                console.error("LocalStorageデータのパースに失敗しました", e);
            }
        }
    });

    // 区画クリックで選択 ＆ クリップボード自動コピー
    async function selectPane(paneElement) {
        document.querySelectorAll('.pane').forEach(el => {
            el.classList.remove('selected');
        });
        paneElement.classList.add('selected');
        document.getElementById('prompt-input').focus();

        const paneId = paneElement.id;
        const textToCopy = paneConversations[paneId];

        if (textToCopy && textToCopy.trim() !== "") {
            try {
                await navigator.clipboard.writeText(textToCopy);

                const header = paneElement.querySelector('.pane-header');
                const originalBg = header.style.background;
                const originalColor = header.style.color;

                header.style.background = '#d4edda'; // 淡いグリーン
                header.style.color = '#155724';

                setTimeout(() => {
                    header.style.background = originalBg;
                    header.style.color = originalColor;
                }, 400);
            } catch (err) {
                console.error('クリップボードコピーに失敗しました:', err);
            }
        }
    }

    // 🚀【新機能】対象の窓の会話履歴をクリアする処理
    function clearPaneLog(event, paneId) {
        event.stopPropagation(); // 区画クリック（コピー＆選択）の暴発を防ぐ

        const paneEl = document.getElementById(paneId);
        const titleText = paneEl.querySelector('.pane-title').innerText;

        if (!confirm(`${titleText} の現在の回答内容をクリアしますか？`)) {
            return;
        }

        // 1. メモリ上のデータを空にする
        paneConversations[paneId] = '';

        // 2. 画面の表示をクリアする
        const historyEl = paneEl.querySelector('.chat-history');
        if (historyEl) {
            historyEl.textContent = '';
        }

        // 3. ローカルストレージに最新の空状態を即時反映（上書き保存）
        localStorage.setItem('gemini_dashboard_panes', JSON.stringify(paneConversations));
    }

    async function askSelected() {
        const inputEl = document.getElementById('prompt-input');
        const prompt = inputEl.value.trim();

        if (!prompt) return;

        const selectedPane = document.querySelector('.pane.selected');
        if (!selectedPane) {
            alert('出力を反映させたい窓をどれか1つクリックして選択してください。');
            return;
        }

        const paneId = selectedPane.id;
        const historyEl = selectedPane.querySelector('.chat-history');
        const lastResponseText = paneConversations[paneId] || '';

        inputEl.value = '';

        if (historyEl) {
            historyEl.innerHTML = '<div class="loading">gemini が考え中...</div>';
        }

        try {
            const res = await fetch(`index.php?page=ai-dashboard&action=ask&provider=gemini&prompt=${encodeURIComponent(prompt)}&last_response=${encodeURIComponent(lastResponseText)}`);

            if (!res.ok) {
                throw new Error(`HTTP error! status: ${res.status}`);
            }

            const data = await res.json();

            if (!historyEl) return;

            let finalReplyText = "";

            if (data.error) {
                historyEl.innerHTML = `<span style="color: red;">Error: ${data.error}</span>`;
                return;
            } else if (data.response) {
                finalReplyText = data.response;
            } else if (data.candidates && data.candidates[0] && data.candidates[0].content && data.candidates[0].content.parts) {
                finalReplyText = data.candidates[0].content.parts[0].text;
            } else {
                finalReplyText = "不明なレスポンス形式です";
            }

            historyEl.textContent = finalReplyText;

            paneConversations[paneId] = finalReplyText;

            // LocalStorageへ即時自動同期
            localStorage.setItem('gemini_dashboard_panes', JSON.stringify(paneConversations));

            if (data.warning) {
                const warnDiv = document.createElement('div');
                warnDiv.style.cssText = "margin-top: 12px; padding: 10px; background: #fff3cd; color: #856404; border: 1px solid #ffeeba; border-radius: 6px; font-size: 0.8rem; line-height: 1.4;";
                warnDiv.innerText = data.warning;
                historyEl.appendChild(warnDiv);
            }
        } catch (error) {
            if (historyEl) {
                historyEl.innerHTML = '<span style="color: red;">通信エラーが発生しました</span>';
            }
            console.error(error);
        }
    }

    document.getElementById('prompt-input').addEventListener('keydown', function (e) {
        if (e.ctrlKey && e.key === 'Enter') {
            askSelected();
        }
    });
</script>