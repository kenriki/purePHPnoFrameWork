<?php
// app/templates/chat_view/chat.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?page=login');
    exit;
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

$current_user_id = $_SESSION['user_id'] ?? null;
// ★ここで URL から安全に ID を取得する
$receiver_id = isset($_GET['receiver_id']) && $_GET['receiver_id'] !== '' ? (int) $_GET['receiver_id'] : null;
$room_id = isset($_GET['room_id']) && $_GET['room_id'] !== '' ? (int) $_GET['room_id'] : null;

if ($room_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM room_members WHERE room_id = ? AND user_id = ?");
    $stmt->execute([$room_id, $current_user_id]);

    if ($stmt->fetchColumn() == 0) {
        die("このグループへのアクセス権限がありません。");
    }
}

// ここでURLパラメータからIDを受け取って「確実に」既読にする
// 1. まず既読処理を確定させる
if ($current_user_id) {
    $pdo = getDB();

    // 【重要】グループチャットの場合は「ルーム内の未読を全件既読にする」
    if (!empty($_GET['room_id'])) {
        $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE room_id = ? AND is_read = 0");
        $stmt->execute([(int) $_GET['room_id']]);

        // 念のためキャッシュさせないためのヘッダー（必要に応じて）
        // header("Cache-Control: no-cache, must-revalidate");
    } elseif (!empty($_GET['receiver_id'])) {
        // 個人用：receiver_id を指定
        $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND is_read = 0");
        $stmt->execute([$current_user_id, (int) $_GET['receiver_id']]);
    }
}

// 2. その後に履歴を取得（これにより、最新の既読状態が反映される）
if ($receiver_id) {
    $messages = $messageModel->getChatHistory($current_user_id, $receiver_id);
} elseif ($room_id) {
    $messages = $messageModel->getRoomMessages($room_id);
}

require_once __DIR__ . '/../../models/Message.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?page=login');
    exit;
}

// // --- デバッグ用：POSTの有無をチェック ---
// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     echo "POSTデータが届いています:<br>";
//     var_dump($_POST);
//     echo "<br>---------------------<br>";
//     // ここで一旦処理を止めて中身を確認する
//     exit; 
// }

$current_user_id = $_SESSION['user_id'];
$receiver_id = isset($_GET['receiver_id']) && $_GET['receiver_id'] !== '' ? (int) $_GET['receiver_id'] : null;
$room_id = isset($_GET['room_id']) && $_GET['room_id'] !== '' ? (int) $_GET['room_id'] : null; // 追加

// メッセージ取得用
$messages = [];
if ($receiver_id) {
    $messages = $messageModel->getChatHistory($current_user_id, $receiver_id);
} elseif ($room_id) {
    // グループ用メッセージ取得処理（Messageモデルにあると仮定）
    $messages = $messageModel->getRoomMessages($room_id);
}

$messageModel = new Message($pdo);

// =======================================================
// 1. 削除処理 (POST時)
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $partner_id = isset($_POST['partner_id']) ? (int) $_POST['partner_id'] : null;
    $message_id = isset($_POST['message_id']) ? (int) $_POST['message_id'] : null;

    if ($message_id) {
        // 条件を極限までシンプルにする（IDだけで更新）
        $stmt = $pdo->prepare("UPDATE messages SET deleted_by_user_id = :my_id WHERE id = :id");
        $result = $stmt->execute([':my_id' => $current_user_id, ':id' => $message_id]);

        // 実際に更新されたか確認
        if ($stmt->rowCount() === 0) {
            error_log("更新対象なし: ID " . $message_id);
        }

        header("Location: index.php?page=chat&receiver_id=" . $receiver_id . "&deleted=1");
        exit;
    } elseif ($partner_id) {
        $stmt = $pdo->prepare("UPDATE messages SET deleted_by_user_id = :my_id 
                                WHERE (sender_id = :my_id AND receiver_id = :partner_id) 
                                   OR (sender_id = :partner_id AND receiver_id = :my_id)");
        $stmt->execute([':my_id' => $current_user_id, ':partner_id' => $partner_id]);

        header("Location: index.php?page=chat&deleted=1");
        exit;
    }
}

// 既読・復旧処理 (GET時)
// 【重要】削除直後 (URLに deleted=1 がある時) は、絶対に既読化（復旧）処理をスキップする
if ($receiver_id && !isset($_GET['deleted'])) {
    $messageModel->markAsRead($receiver_id, $current_user_id);
}

// メッセージ履歴取得
$messages = $messageModel->getChatHistory($current_user_id, $receiver_id);


// 1. メッセージ送信処理 (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $msg_text = trim($_POST['message']);

    // --- メール通知ロジック ---
    if ($msg_text !== '' && ($receiver_id || $room_id)) {

        // 1. 送信者のユーザー名を取得
        $stmt_user = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt_user->execute([$current_user_id]);
        $sender_name = $stmt_user->fetchColumn() ?: "送信者";

        // 動的なURL生成ベース
        $base_url = "http://" . $_SERVER['HTTP_HOST'] . "/index.php";

        if ($room_id) {
            // A. グループへ送信
            $messageModel->sendGroupMessage($current_user_id, $room_id, $msg_text);

            // ★1. グループ名を取得
            $stmt_room = $pdo->prepare("SELECT name FROM rooms WHERE id = ?");
            $stmt_room->execute([$room_id]);
            $room_name = $stmt_room->fetchColumn() ?: "グループチャット";

            // ★2. グループメンバー全員のメールアドレスを取得して通知
            $stmt = $pdo->prepare("
                SELECT u.email 
                FROM users u 
                JOIN room_members rm ON u.id = rm.user_id 
                WHERE rm.room_id = ? AND u.id != ?
            ");
            $stmt->execute([$room_id, $current_user_id]);
            $members = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($members)) {
                $chat_url = $base_url . "?page=chat&room_id=" . $room_id;

                // ★3. メール本文にグループ名と送信者名を含める
                $subject = "【{$room_name}】新しいメッセージがあります";
                $body = "{$room_name} にて、{$sender_name} さんからメッセージが届きました。\n\n" .
                    "------------------------------------------\n" .
                    "メッセージ内容:\n" . $msg_text . "\n\n" .
                    "------------------------------------------\n" .
                    "以下のリンクから確認してください。\n" . $chat_url;

                foreach ($members as $email) {
                    MailUtil::sendMail($email, $subject, $body);
                }
            }
            $redirect_url = "index.php?page=chat&room_id=" . $room_id;
        } else {
            // B. 個人へ送信
            $messageModel->sendMessage($current_user_id, $receiver_id, $msg_text);

            // 2B. 受信者のメールアドレスを取得
            $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
            $stmt->execute([$receiver_id]);
            if ($email = $stmt->fetchColumn()) {
                $chat_url = $base_url . "?page=chat&receiver_id=" . $current_user_id;
                $subject = "【お知らせ】新しいメッセージが届きました";
                $body = "{$sender_name} さんからメッセージが届きました。\n\n" .
                    "------------------------------------------\n" .
                    "メッセージ内容:\n" . $msg_text . "\n\n" .
                    "------------------------------------------\n" .
                    "以下のリンクからチャットを確認してください。\n" . $chat_url;

                MailUtil::sendMail($email, $subject, $body);
            }
            $redirect_url = "index.php?page=chat&receiver_id=" . $receiver_id;
        }

        header("Location: " . $redirect_url);
        exit;
    }
}

// 2. 既読処理 & チャット履歴取得
$messages = [];
$receiver = null;

$user_column = 'username';
try {
    $pdo->query("SELECT username FROM users LIMIT 1");
} catch (Exception $e) {
    $user_column = 'username';
}

if ($room_id) {
    $messages = $messageModel->getRoomMessages($room_id);
    $stmt = $pdo->prepare("SELECT name FROM rooms WHERE id = ?");
    $stmt->execute([$room_id]);
    $receiver = ['name' => $stmt->fetchColumn() ?: 'グループチャット'];
} elseif ($receiver_id) {
    $messageModel->markAsRead($receiver_id, $current_user_id);
    $messages = $messageModel->getChatHistory($current_user_id, $receiver_id);
    $stmt = $pdo->prepare("SELECT id, username AS name FROM users WHERE id = :id");
    $stmt->execute([':id' => $receiver_id]);
    $receiver = $stmt->fetch(PDO::FETCH_ASSOC);
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
// 1. 変数の準備（SQLで安全に使うため）
$keyword = trim($search_keyword ?? '');
$my_id = $current_user_id;

// 2. SQLの切り替え
if ($keyword !== '') {
    // 【検索時】指定キーワードでユーザーを探す
    // ※ $user_column を使用せず、明確にカラム名を指定して安定させる
    $sql = "SELECT id, username AS name 
            FROM users 
            WHERE (username = :keyword OR email = :keyword) 
            AND id != :my_id";
    $params = [':keyword' => $keyword, ':my_id' => $my_id];
} else {
    // 【一覧時】やり取りのある相手を最新順に取得する
    // GROUP BY にエイリアス名を使わず、u.id で統一
    $sql = "SELECT u.id, 
                   COALESCE(cs.custom_title, u.username) AS name,
                   MAX(m.created_at) AS last_msg_time
            FROM users u
            JOIN messages m ON (u.id = m.sender_id OR u.id = m.receiver_id)
            LEFT JOIN chat_settings cs ON cs.target_id = u.id AND cs.user_id = :my_id
            WHERE (m.sender_id = :my_id OR m.receiver_id = :my_id)
            AND u.id != :my_id
            AND (m.deleted_by_user_id IS NULL OR m.deleted_by_user_id != :my_id)
            GROUP BY u.id, cs.custom_title, u.username
            ORDER BY last_msg_time DESC";
    $params = [':my_id' => $my_id];
}

// 3. 実行
$stmtSearch = $pdo->prepare($sql);
$stmtSearch->execute($params);
$search_results = $stmtSearch->fetchAll(PDO::FETCH_ASSOC);

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
<link rel="stylesheet" href="/assets/css/chat.css">

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
            <div class="chat-list" style="width: 100%; max-width: 300px; border-right: 1px solid #ccc; padding: 10px;">
                <h3>トーク一覧</h3>
                <?php
                // カスタムタイトルを取得するためのSQLをループ前に追加
                $stmt_title = $pdo->prepare("SELECT target_id, custom_title FROM chat_settings WHERE user_id = :my_id");
                $stmt_title->execute([':my_id' => $current_user_id]);
                $titles = $stmt_title->fetchAll(PDO::FETCH_KEY_PAIR);


                // グループ取得用SQLを追加
                $stmtRooms = $pdo->prepare("SELECT r.id, r.name FROM rooms r JOIN room_members rm ON r.id = rm.room_id WHERE rm.user_id = :my_id");
                $stmtRooms->execute([':my_id' => $current_user_id]);
                $group_list = $stmtRooms->fetchAll(PDO::FETCH_ASSOC);

                // グループを表示
                foreach ($group_list as $group) {
                    echo '<div style="padding: 5px 0;"><a href="index.php?page=chat&room_id=' . $group['id'] . '">[G] ' . htmlspecialchars($group['name']) . '</a></div>';
                }

                // 個人チャットを表示
                $chat_list = $messageModel->getChatList($current_user_id);
                foreach ($chat_list as $chat):
                    $partner_id = ($chat['sender_id'] == $current_user_id) ? $chat['receiver_id'] : $chat['sender_id'];
                    // カスタムタイトルがあればそれを使用、なければsender_nameを使用
                    $display_name = isset($titles[$partner_id]) ? $titles[$partner_id] : $chat['sender_name'];
                    ?>
                    <div style="display: flex; align-items: center; border-bottom: 1px solid #eee; padding: 5px 0;">
                        <a href="index.php?page=chat&receiver_id=<?= $partner_id ?>"
                            style="flex: 1; min-width: 0; text-decoration: none; color: #333; padding-right: 10px;">
                            <div style="font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                <?= htmlspecialchars($display_name) ?>
                            </div>
                            <div
                                style="font-size: 0.8rem; color: #666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                <?= htmlspecialchars(mb_strimwidth($chat['message'], 0, 20, '...')) ?>
                            </div>
                            <small
                                style="font-size: 0.7rem; color: #999;"><?= date('m/d H:i', strtotime($chat['created_at'])) ?></small>
                        </a>

                        <form action="index.php?page=chat" method="POST" onsubmit="return confirm('このトーク履歴を非表示にしますか？');"
                            style="margin: 0; flex-shrink: 0;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="partner_id" value="<?= $partner_id ?>">
                            <button type="submit"
                                style="background: none; border: none; cursor: pointer; color: #ff4d4d; font-size: 1.2rem; padding: 5px;">
                                🗑️
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($search_results !== null): ?>
                <ul class="result-list">
                    <?php if (count($search_results) > 0): ?>
                        <?php foreach ($search_results as $row): ?>
                            <li>
                                <span><?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <a
                                    href="index.php?page=chat&receiver_id=<?php echo $row['id']; ?>&search_name=<?php echo urlencode($keyword); ?>">チャットを開く</a>
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
                <h4 style="display: flex; justify-content: space-between; align-items: center;">
                    <span>
                        <?php
                        $displayName = htmlspecialchars($receiver['name'] ?? 'チャット', ENT_QUOTES, 'UTF-8');
                        echo ($room_id) ? $displayName : $displayName . ' さんとのチャット';
                        ?>
                    </span>
                    <?php if ($room_id): ?>
                        <button onclick="document.getElementById('addMemberModal').style.display='block'" class="btn-small">＋
                            メンバー追加</button>
                    <?php endif; ?>
                </h4>

                <details>
                    <summary
                        style="cursor:pointer; padding:5px; background:#f8f9fa; border:1px solid #ddd; margin-bottom:10px;">
                        グループを新規作成</summary>
                    <div class="group-create-area" style="padding: 10px; border: 1px solid #eee;">
                        <?php include __DIR__ . '/create_group_form.php'; ?>
                    </div>
                </details>

                <div id="addMemberModal"
                    style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
                    <div
                        style="background:#fff; width:300px; margin:100px auto; padding:20px; border-radius:8px; position:relative;">
                        <h5>メンバーを追加</h5>
                        <form action="index.php?page=add_member" method="POST">
                            <input type="hidden" name="room_id" value="<?= htmlspecialchars($room_id) ?>">
                            <input type="text" name="new_member_name" placeholder="ユーザー名を入力" required
                                style="width:100%; margin-bottom:10px;">
                            <button type="submit">追加実行</button>
                            <button type="button"
                                onclick="document.getElementById('addMemberModal').style.display='none'">閉じる</button>
                        </form>
                    </div>
                </div>

                <div class="chat-timeline" id="chatTimeline">
                    <?php if (count($messages) > 0): ?>
                        <?php foreach ($messages as $msg): ?>
                            <?php
                            $is_me = ((int) $msg['sender_id'] === (int) $current_user_id);
                            // 戻りURLを現在のGETパラメータに合わせて動的に作成
                            $current_url = "index.php?page=chat" . ($room_id ? "&room_id=" . $room_id : "&receiver_id=" . $receiver_id);
                            ?>
                            <div class="msg-row <?php echo $is_me ? 'me' : 'partner'; ?>">

                                <?php if ($is_me): ?>
                                    <?php
                                    // ループ内で確実に動作するよう、現在の状況からroom_idを取得
                                    $current_room_id = $_GET['room_id'] ?? $room_id ?? null;

                                    if ($current_room_id) {
                                        // 1. 自分以外のグループメンバーのユーザー名（一覧）を取得
                                        $stmt_members = $pdo->prepare("
                                            SELECT u.username 
                                            FROM users u 
                                            JOIN room_members rm ON u.id = rm.user_id 
                                            WHERE rm.room_id = ? AND u.id != ?
                                        ");
                                        $stmt_members->execute([(int)$current_room_id, $current_user_id]);
                                        $read_users = $stmt_members->fetchAll(PDO::FETCH_COLUMN);

                                        // 2. 既読の人数（配列の要素数）
                                        $member_count = count($read_users);

                                        // 3. ホバー時に改行区切りでユーザー名が出るように結合
                                        $hover_text = !empty($read_users) ? implode("\n", $read_users) : "既読メンバーはいません";
                                    }
                                    ?>

                                    <?php if (!empty($current_room_id)): ?>
                                        <span class="msg-status" title="<?php echo htmlspecialchars($hover_text, ENT_QUOTES, 'UTF-8'); ?>" style="cursor: help; display: inline-block; min-width: 40px;">
                                            既読: <?php echo (int)$member_count; ?>
                                        </span>
                                    <?php else: ?>
                                        <?php if ((int) $msg['is_read'] === 1): ?>
                                            <span class="msg-status">既読</span>
                                        <?php else: ?>
                                            <span class="msg-status unread">未読</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <div class="msg-bubble">
                                    <?php if (!$is_me && $room_id && isset($msg['sender_name'])): ?>
                                        <div style="font-size: 10px; color: #666; margin-bottom: 2px;">
                                            <?php echo htmlspecialchars($msg['sender_name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($is_me && isset($msg['id'])): ?>
                                        <form action="<?php echo $current_url; ?>" method="POST" class="msg-delete-form"
                                            onsubmit="return confirmDelete();">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                            <button type="submit" class="btn-delete-msg" title="メッセージを削除">🗑️</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php echo nl2br(htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8')); ?>

                                    <!-- <div class="msg-actions"
                                        style="margin-top: 5px; border-top: 1px solid rgba(0,0,0,0.1); padding-top: 3px; display: flex; gap: 10px; font-size: 11px;">
                                        <span class="like-btn" onclick="toggleLike(<?php echo $msg['id']; ?>)"
                                            style="cursor:pointer; color: #e91e63;">
                                            ❤️ <span
                                                id="like-count-<?php echo $msg['id']; ?>"><?php echo $msg['like_count'] ?? 0; ?></span>
                                        </span>
                                        <span class="read-info" style="color: #666;">
                                            既読: <?php echo $msg['read_count'] ?? 0; ?>
                                        </span>
                                    </div> -->

                                    <span class="msg-meta"><?php echo date('m/d H:i', strtotime($msg['created_at'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: #bbb; text-align: center; margin-top: 120px; font-size: 13px;">メッセージはまだありません</p>
                    <?php endif; ?>
                </div>

                <form
                    action="index.php?page=chat<?php echo $room_id ? '&room_id=' . $room_id : '&receiver_id=' . $receiver_id; ?>"
                    method="POST" class="chat-input-area">
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

        const chatForm = document.querySelector('.chat-input-area');
        if (chatForm) {
            chatForm.addEventListener('submit', function () {
                const btn = this.querySelector('button[type="submit"]');
                btn.disabled = true;
                btn.textContent = '送信中...';
            });
        }
    });

    // 【追加】削除前の確認ダイアログ
    function confirmDelete() {
        return confirm("このメッセージを自分の画面から削除しますか？\n(送信相手の画面には残り続けます)");
    }

    function toggleLike(messageId) {
        // ボタンの要素を取得
        const btn = document.getElementById('like-btn-' + messageId);

        fetch('like_message.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'message_id=' + messageId
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'liked') {
                    btn.style.color = 'red'; // いいねしたら赤くする
                } else {
                    btn.style.color = '#ccc'; // 解除したらグレーに戻す
                }
            })
            .catch(error => console.error('Error:', error));
    }

    function searchUsers() {
        const keyword = document.getElementById('userSearchInput').value;
        fetch('app/templates/chat_view/search_users.php?q=' + encodeURIComponent(keyword))
            .then(response => response.json())
            .then(users => {
                const container = document.getElementById('userResults');
                container.innerHTML = ''; // クリア

                users.forEach(user => {
                    const label = document.createElement('label');
                    label.innerHTML = `
                        <input type="checkbox" name="user_ids[]" value="${user.id}"> 
                        ${user.username}
                    `;
                    container.appendChild(label);
                    container.appendChild(document.createElement('br'));
                });
            })
            .catch(error => console.error('エラー:', error)); // エラーハンドリングがあると便利
    }
</script>

<?php
if (file_exists(__DIR__ . '/../layout/footer.php') && !defined('FOOTER_INCLUDED')) {
    include __DIR__ . '/../layout/footer.php';
    define('FOOTER_INCLUDED', true);
}
?>