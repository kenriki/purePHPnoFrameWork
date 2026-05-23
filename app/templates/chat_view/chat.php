<?php
// app/templates/chat_view/chat.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. セッションとDB接続の初期化
if (session_status() === PHP_SESSION_NONE)
    session_start();
require_once __DIR__ . '/../../dbconfig.php';

// $pdoが未定義の場合の安全な初期化
if (!isset($pdo) || !($pdo instanceof PDO)) {
    try {
        $pdo = getDB();
    } catch (Exception $e) {
        die("<h1>Error: Database connection failed.</h1>");
    }
}

require_once __DIR__ . '/../../models/Message.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?page=login');
    exit;
}

$current_user_id = $_SESSION['user_id'];
$receiver_id = isset($_GET['receiver_id']) && $_GET['receiver_id'] !== '' ? (int) $_GET['receiver_id'] : null;

$messageModel = new Message($pdo);

// =======================================================
// 【追加】新機能：メッセージ削除処理 (POST / action=delete)
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $delete_msg_id = isset($_POST['message_id']) ? (int) $_POST['message_id'] : null;

    if ($delete_msg_id) {
        try {
            // 不正な削除（他人のメッセージを消すなど）を防ぐため、送信者が自分であるレコードのみ削除
            $stmtDel = $pdo->prepare("DELETE FROM messages WHERE id = ? AND sender_id = ?");
            $stmtDel->execute([$delete_msg_id, $current_user_id]);
        } catch (Exception $e) {
            error_log("Failed to delete message: " . $e->getMessage());
        }

        // 削除後に画面をきれいに再読み込み
        $redirect_url = "index.php?page=chat" . ($receiver_id ? "&receiver_id=" . $receiver_id : "");
        header("Location: " . $redirect_url);
        exit;
    }
}

// 1. メッセージ送信処理 (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $msg_text = trim($_POST['message']);
    if ($msg_text !== '' && $receiver_id) {
        $messageModel->sendMessage($current_user_id, $receiver_id, $msg_text);
        header("Location: index.php?page=chat&receiver_id=" . $receiver_id);
        exit;
    }
}

// 2. 既読処理 & チャット履歴取得
$messages = [];
$receiver = null;

$user_column = 'name';
try {
    $pdo->query("SELECT name FROM users LIMIT 1");
} catch (Exception $e) {
    $user_column = 'username';
}

if ($receiver_id) {
    // 【重要】自分が受信者(current_user_id)で、相手(receiver_id)から届いた未読メッセージを既読にする
    $messageModel->markAsRead($receiver_id, $current_user_id);

    // 履歴を取得
    $messages = $messageModel->getChatHistory($current_user_id, $receiver_id);

    // 相手のユーザー情報を取得
    $stmtUser = $pdo->prepare("SELECT id, {$user_column} AS name FROM users WHERE id = :id");
    $stmtUser->execute([':id' => $receiver_id]);
    $receiver = $stmtUser->fetch(PDO::FETCH_ASSOC);
}

// 3. ユーザー検索処理
$search_keyword = $_GET['search_name'] ?? null;
$search_results = null;

// if ($search_keyword !== null && trim($search_keyword) !== '') {
//     $stmtSearch = $pdo->prepare("SELECT id, {$user_column} AS name FROM users WHERE {$user_column} LIKE :keyword AND id != :my_id");
//     $stmtSearch->execute([
//         ':keyword' => '%' . $search_keyword . '%',
//         ':my_id' => $current_user_id
//     ]);
//     $search_results = $stmtSearch->fetchAll(PDO::FETCH_ASSOC);
// }
// 1. キーワードを整形（空白削除）
// 1. キーワードを整形（空白削除）
$keyword = trim($search_keyword ?? '');

if ($keyword !== '') {
    // 2. username も email も `=`（完全一致）にする
    // どちらかがキーワードと完全に一致した場合のみ結果に含めます
    $sql = "SELECT id, {$user_column} AS name 
            FROM users 
            WHERE (username = :keyword OR email = :keyword) 
            AND id != :my_id";

    $stmtSearch = $pdo->prepare($sql);

    // 3. 完全一致なので、% は一切付与しません
    $stmtSearch->execute([
        ':keyword' => $keyword,
        ':my_id' => $current_user_id
    ]);

    $search_results = $stmtSearch->fetchAll(PDO::FETCH_ASSOC);
} else {
    $search_results = [];
}

if (file_exists(__DIR__ . '/../layout/header.php')) {
    if (!headers_sent() && !defined('HEADER_INCLUDED')) {
        include __DIR__ . '/../layout/header.php';
        define('HEADER_INCLUDED', true);
    }
}
?>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ユーザーチャット</title>
<style>
    /* ベースレイアウト */
    .chat-container {
        max-width: 100%;
        margin: 0 auto;
        padding: 10px;
        font-family: sans-serif;
        box-sizing: border-box;
    }

    .chat-layout {
        display: flex;
        gap: 15px;
        margin-top: 10px;
    }

    .chat-sidebar {
        width: 280px;
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        border: 1px solid #dee2e6;
        box-sizing: border-box;
        flex-shrink: 0;
    }

    .chat-main {
        flex: 1;
        background: #fff;
        padding: 15px;
        border-radius: 8px;
        border: 1px solid #dee2e6;
        box-sizing: border-box;
        min-width: 0;
    }

    /* 検索ボックス */
    .search-box {
        display: flex;
        gap: 5px;
        margin-bottom: 15px;
    }

    .search-box input {
        flex: 1;
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 14px;
    }

    .search-box button {
        padding: 8px 12px;
        background: #6c757d;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
    }

    .result-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .result-list li {
        padding: 10px 8px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .result-list a {
        text-decoration: none;
        color: #007bff;
        font-size: 13px;
        font-weight: bold;
    }

    /* タイムライン */
    .chat-timeline {
        height: 350px;
        overflow-y: auto;
        border: 1px solid #eee;
        padding: 10px;
        margin-bottom: 15px;
        background: #fdfdfd;
        border-radius: 4px;
    }

    .msg-row {
        display: flex;
        margin-bottom: 12px;
        align-items: flex-end;
        gap: 6px;
    }

    .msg-row.me {
        justify-content: flex-end;
    }

    .msg-row.partner {
        justify-content: flex-start;
    }

    /* 既読・未読バッジのスタイル */
    .msg-status {
        font-size: 11px;
        color: #28a745;
        font-weight: bold;
        user-select: none;
        margin-bottom: 3px;
    }

    .msg-status.unread {
        color: #ccc;
        font-weight: normal;
    }

    /* 吹き出し */
    .msg-bubble {
        max-width: 75%;
        padding: 10px 14px;
        border-radius: 16px;
        font-size: 14px;
        line-height: 1.4;
        word-break: break-all;
        position: relative;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    .msg-row.me .msg-bubble {
        background: #007bff;
        color: white;
        border-bottom-right-radius: 2px;
        padding-right: 32px;
        /* 削除ボタン用の余白 */
    }

    .msg-row.partner .msg-bubble {
        background: #e9ecef;
        color: #333;
        border-bottom-left-radius: 2px;
    }

    /* 【追加】メッセージ削除ボタンのスタイル */
    .msg-delete-form {
        position: absolute;
        top: 6px;
        right: 8px;
        margin: 0;
        padding: 0;
        line-height: 1;
    }

    .btn-delete-msg {
        background: none;
        border: none;
        color: rgba(255, 255, 255, 0.6);
        cursor: pointer;
        font-size: 13px;
        padding: 2px;
        transition: color 0.2s;
    }

    .btn-delete-msg:hover {
        color: #ffc107;
        /* ホバー時に黄色にハイライト */
    }

    .msg-meta {
        font-size: 10px;
        color: rgba(0, 0, 0, 0.4);
        margin-top: 4px;
        display: block;
        text-align: right;
    }

    .msg-row.me .msg-meta {
        color: rgba(255, 255, 255, 0.7);
    }

    /* 入力エリア */
    .chat-input-area {
        display: flex;
        gap: 10px;
    }

    .chat-input-area textarea {
        flex: 1;
        height: 42px;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        resize: none;
        font-size: 14px;
        box-sizing: border-box;
    }

    .chat-input-area button {
        padding: 0 20px;
        background: #28a745;
        color: white;
        border: none;
        border-radius: 4px;
        font-weight: bold;
        cursor: pointer;
        font-size: 14px;
    }

    /* 📱 スマホ環境（画面幅768px以下）用のレスポンシブデザイン */
    @media (max-width: 768px) {
        .chat-layout {
            flex-direction: column;
        }

        .chat-sidebar {
            width: 100%;
            margin-bottom: 10px;
        }

        .chat-main {
            width: 100%;
        }

        .chat-timeline {
            height: 280px;
        }
    }
</style>


<div class="chat-container">
    <div class="chat-layout">
        <div class="chat-sidebar">
            <h4 style="margin-top:0; margin-bottom:10px;">ユーザー検索</h4>
            <form action="index.php" method="GET" class="search-box">
                <input type="hidden" name="page" value="chat">
                <input type="text" name="search_name"
                    value="<?php echo htmlspecialchars($search_keyword ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    placeholder="ユーザー名を入力...">
                <button type="submit">検索</button>
            </form>

            <?php if ($search_results !== null): ?>
                <ul class="result-list">
                    <?php if (count($search_results) > 0): ?>
                        <?php foreach ($search_results as $row): ?>
                            <li>
                                <span><?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <a href="index.php?page=chat&receiver_id=<?php echo $row['id']; ?>">チャットを開く</a>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li style="color: #999; font-size: 13px;">該当ユーザーなし</li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="chat-main">
            <?php if ($receiver): ?>
                <h4 style="margin-top:0; margin-bottom:15px; border-bottom: 2px solid #f4f4f4; padding-bottom: 8px;">
                    <?php echo htmlspecialchars($receiver['name'], ENT_QUOTES, 'UTF-8'); ?> さんとのチャット
                </h4>

                <div class="chat-timeline" id="chatTimeline">
                    <?php if (count($messages) > 0): ?>
                        <?php foreach ($messages as $msg): ?>
                            <?php $is_me = ((int) $msg['sender_id'] === (int) $current_user_id); ?>
                            <div class="msg-row <?php echo $is_me ? 'me' : 'partner'; ?>">

                                <?php if ($is_me): ?>
                                    <?php if ((int) $msg['is_read'] === 1): ?>
                                        <span class="msg-status">既読</span>
                                    <?php else: ?>
                                        <span class="msg-status unread">未読</span>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <div class="msg-bubble">
                                    <?php if ($is_me && isset($msg['id'])): ?>
                                        <form action="index.php?page=chat&receiver_id=<?php echo $receiver_id; ?>" method="POST"
                                            class="msg-delete-form" onsubmit="return confirmDelete();">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                            <button type="submit" class="btn-delete-msg" title="メッセージを削除">🗑️</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php echo nl2br(htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8')); ?>
                                    <span class="msg-meta"><?php echo date('m/d H:i', strtotime($msg['created_at'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: #bbb; text-align: center; margin-top: 120px; font-size: 13px;">メッセージはまだありません</p>
                    <?php endif; ?>
                </div>

                <form action="index.php?page=chat&receiver_id=<?php echo $receiver_id; ?>" method="POST"
                    class="chat-input-area">
                    <textarea name="message" placeholder="メッセージを入力..." required></textarea>
                    <button type="submit">送信</button>
                </form>
            <?php else: ?>
                <div style="text-align: center; color: #999; margin-top: 100px; padding: 20px;">
                    <p style="font-size: 15px; font-weight: bold; margin-bottom: 5px;">トーク相手が未選択です</p>
                    <p style="font-size: 12px; color: #bbb;">左側の検索窓から相手を探して「チャットを開く」を押してください。</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    window.addEventListener('DOMContentLoaded', () => {
        const t = document.getElementById('chatTimeline');
        if (t) { t.scrollTop = t.scrollHeight; }
    });

    // 【追加】削除前の確認ダイアログ
    function confirmDelete() {
        return confirm("このメッセージを削除してもよろしいですか？\n(送信相手の画面からも消去されます)");
    }
</script>

<?php
if (file_exists(__DIR__ . '/../layout/footer.php') && !defined('FOOTER_INCLUDED')) {
    include __DIR__ . '/../layout/footer.php';
    define('FOOTER_INCLUDED', true);
}
?>