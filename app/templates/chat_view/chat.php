<?php
// app/templates/chat_view/chat.php

// =======================================================
// 1. 初期化・セッション・権限チェック（ロジック層）
// =======================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?page=login');
    exit;
}

require_once __DIR__ . '/../../dbconfig.php';
require_once __DIR__ . '/../../models/Message.php';

// PDOの安全な初期化
if (!isset($pdo) || !($pdo instanceof PDO)) {
    try {
        $pdo = getDB();
    } catch (Exception $e) {
        die("<h1>Error: Database connection failed.</h1>");
    }
}

// モデルのインスタンス化
$messageModel = new Message($pdo);

// 基本変数の取得
$current_user_id = (int) $_SESSION['user_id'];
$receiver_id = isset($_GET['receiver_id']) && $_GET['receiver_id'] !== '' ? (int) $_GET['receiver_id'] : null;
$room_id = isset($_GET['room_id']) && $_GET['room_id'] !== '' ? (int) $_GET['room_id'] : null;
$is_deleted_page = isset($_GET['deleted']);

// グループアクセス権限チェック
if ($room_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM room_members WHERE room_id = ? AND user_id = ?");
    $stmt->execute([$room_id, $current_user_id]);
    if ($stmt->fetchColumn() == 0) {
        die("このグループへのアクセス権限がありません。");
    }
}

// =======================================================
// 2. POST処理（メッセージ送信・削除）
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // A. 削除・非表示処理
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $partner_id = isset($_POST['partner_id']) ? (int) $_POST['partner_id'] : null;
        $message_id = isset($_POST['message_id']) ? (int) $_POST['message_id'] : null;

        if ($message_id) {
            $stmt = $pdo->prepare("UPDATE messages SET deleted_by_user_id = :my_id WHERE id = :id");
            $stmt->execute([':my_id' => $current_user_id, ':id' => $message_id]);

            $redirect_part = $room_id ? "&room_id=" . $room_id : "&receiver_id=" . $receiver_id;
            header("Location: index.php?page=chat" . $redirect_part . "&deleted=1");
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

    // B. メッセージ送信処理
    if (isset($_POST['message'])) {
        $msg_text = trim($_POST['message']);

        if ($msg_text !== '' && ($receiver_id || $room_id)) {
            // 送信者のユーザー名を取得
            $stmt_user = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt_user->execute([$current_user_id]);
            $sender_name = $stmt_user->fetchColumn() ?: "送信者";

            $base_url = "http://" . $_SERVER['HTTP_HOST'] . "/index.php";

            if ($room_id) {
                // グループ送信 & 通知
                $messageModel->sendGroupMessage($current_user_id, $room_id, $msg_text);

                $stmt_room = $pdo->prepare("SELECT name FROM rooms WHERE id = ?");
                $stmt_room->execute([$room_id]);
                $room_name = $stmt_room->fetchColumn() ?: "グループチャット";

                $stmt = $pdo->prepare("
                    SELECT u.email FROM users u 
                    JOIN room_members rm ON u.id = rm.user_id 
                    WHERE rm.room_id = ? AND u.id != ?
                ");
                $stmt->execute([$room_id, $current_user_id]);
                $members = $stmt->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($members)) {
                    $chat_url = $base_url . "?page=chat&room_id=" . $room_id;
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
                // 個人送信 & 通知
                $messageModel->sendMessage($current_user_id, $receiver_id, $msg_text);

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
}

// =======================================================
// 3. データ取得処理（表示用メッセージ・ユーザー情報の準備）
// =======================================================

// A. 既読処理の実行（削除直後でない場合のみ）
if (!$is_deleted_page) {
    if ($room_id) {
        $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE room_id = ? AND is_read = 0");
        $stmt->execute([$room_id]);
    } elseif ($receiver_id) {
        $messageModel->markAsRead($receiver_id, $current_user_id);
    }
}

// B. タイムライン用メッセージ履歴・チャット相手情報の取得
$messages = [];
$receiver = null;

if ($room_id) {
    $messages = $messageModel->getRoomMessages($room_id, $current_user_id);
    $stmt = $pdo->prepare("SELECT name FROM rooms WHERE id = ?");
    $stmt->execute([$room_id]);
    $receiver = ['name' => $stmt->fetchColumn() ?: 'グループチャット'];
    // 現在このルームに参加している全てのメンバー名を取得
    $stmt_rm = $pdo->prepare("
        SELECT u.username 
        FROM users u 
        JOIN room_members rm ON u.id = rm.user_id 
        WHERE rm.room_id = ?
        ORDER BY u.username ASC
    ");
    $stmt_rm->execute([$room_id]);
    $room_members_list = $stmt_rm->fetchAll(PDO::FETCH_COLUMN);
} elseif ($receiver_id) {
    $messages = $messageModel->getChatHistory($current_user_id, $receiver_id);
    $stmt = $pdo->prepare("SELECT id, username AS name FROM users WHERE id = :id");
    $stmt->execute([':id' => $receiver_id]);
    $receiver = $stmt->fetch(PDO::FETCH_ASSOC);
}

// C. サイドバー：ユーザー検索・やり取り一覧の取得
$search_keyword = $_GET['search_name'] ?? null;
$keyword = trim($search_keyword ?? '');

if ($keyword !== '') {
    $sql = "SELECT id, username AS name FROM users WHERE (username = :keyword OR email = :keyword) AND id != :my_id";
    $params = [':keyword' => $keyword, ':my_id' => $current_user_id];
} else {
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
    $params = [':my_id' => $current_user_id];
}

$stmtSearch = $pdo->prepare($sql);
$stmtSearch->execute($params);
$search_results = $stmtSearch->fetchAll(PDO::FETCH_ASSOC);

// =======================================================
// 4. 画面描画（HTMLビュー層）
// =======================================================
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
                    value="<?= htmlspecialchars($search_keyword ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    placeholder="ユーザー名を入力...">
                <button type="submit">検索</button>
            </form>

            <div class="chat-list" style="width: 100%; max-width: 300px; border-right: 1px solid #ccc; padding: 10px;">
                <h3>トーク一覧</h3>
                <?php
                // カスタムタイトルの取得
                $stmt_title = $pdo->prepare("SELECT target_id, custom_title FROM chat_settings WHERE user_id = :my_id");
                $stmt_title->execute([':my_id' => $current_user_id]);
                $titles = $stmt_title->fetchAll(PDO::FETCH_KEY_PAIR);

                // 所属グループ一覧の取得
                $stmtRooms = $pdo->prepare("SELECT r.id, r.name FROM rooms r JOIN room_members rm ON r.id = rm.room_id WHERE rm.user_id = :my_id");
                $stmtRooms->execute([':my_id' => $current_user_id]);
                $group_list = $stmtRooms->fetchAll(PDO::FETCH_ASSOC);

                foreach ($group_list as $group) {
                    echo '<div style="padding: 5px 0;"><a href="index.php?page=chat&room_id=' . $group['id'] . '">[G] ' . htmlspecialchars($group['name']) . '</a></div>';
                }

                // 個人チャット一覧のループ描画
                $chat_list = $messageModel->getChatList($current_user_id);
                foreach ($chat_list as $chat):
                    $partner_id = ($chat['sender_id'] == $current_user_id) ? $chat['receiver_id'] : $chat['sender_id'];
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
                                style="background: none; border: none; cursor: pointer; color: #ff4d4d; font-size: 1.2rem; padding: 5px;">🗑️</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($search_results !== null): ?>
                <ul class="result-list">
                    <?php if (count($search_results) > 0): ?>
                        <?php foreach ($search_results as $row): ?>
                            <li>
                                <span><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <a
                                    href="index.php?page=chat&receiver_id=<?= $row['id']; ?>&search_name=<?= urlencode($keyword); ?>">チャットを開く</a>
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
                    <!-- グループ表示のときだけタイトルの横に所属ユーザを出す -->
                    <?php if ($room_id && !empty($room_members_list)): ?>
                        <span class="room-members-inline"
                            style="font-size: 0.8rem; color: #777; font-weight: normal; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"
                            title="参加メンバー: <?= htmlspecialchars(implode(', ', $room_members_list), ENT_QUOTES, 'UTF-8'); ?>">
                            参加者:(
                            <?= htmlspecialchars(implode(', ', $room_members_list), ENT_QUOTES, 'UTF-8'); ?>)
                        </span>
                    <?php endif; ?>
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
                            $is_me = ((int) $msg['sender_id'] === $current_user_id);
                            $current_url = "index.php?page=chat" . ($room_id ? "&room_id=" . $room_id : "&receiver_id=" . $receiver_id);
                            ?>
                            <div class="msg-row <?= $is_me ? 'me' : 'partner'; ?>">

                                <?php if ($is_me): ?>
                                    <?php if (!empty($room_id)):
                                        // グループチャットの既読カウント
                                        $stmt_members = $pdo->prepare("
                                            SELECT u.username FROM users u 
                                            JOIN room_members rm ON u.id = rm.user_id 
                                            WHERE rm.room_id = ? AND u.id != ?
                                        ");
                                        $stmt_members->execute([$room_id, $current_user_id]);
                                        $read_users = $stmt_members->fetchAll(PDO::FETCH_COLUMN);
                                        $member_count = count($read_users);
                                        $hover_text = !empty($read_users) ? implode("\n", $read_users) : "既読メンバーはいません";
                                        ?>
                                        <span class="msg-status" title="<?= htmlspecialchars($hover_text, ENT_QUOTES, 'UTF-8'); ?>"
                                            style="cursor: help; display: inline-block; min-width: 40px;">
                                            既読: <?= (int) $member_count; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="msg-status <?= ((int) $msg['is_read'] === 1) ? '' : 'unread'; ?>">
                                            <?= ((int) $msg['is_read'] === 1) ? '既読' : '未読'; ?>
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <div class="msg-bubble">
                                    <?php if (!$is_me && $room_id && isset($msg['sender_name'])): ?>
                                        <div style="font-size: 10px; color: #666; margin-bottom: 2px;">
                                            <?= htmlspecialchars($msg['sender_name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($is_me && isset($msg['id'])): ?>
                                        <form action="<?= $current_url; ?>" method="POST" class="msg-delete-form"
                                            onsubmit="return confirmDelete();">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="message_id" value="<?= $msg['id']; ?>">
                                            <button type="submit" class="btn-delete-msg" title="メッセージを削除">🗑️</button>
                                        </form>
                                    <?php endif; ?>

                                    <?= nl2br(htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8')); ?>

                                    <div class="msg-actions"
                                        style="margin-top: 5px; border-top: 1px solid rgba(0,0,0,0.1); padding-top: 3px; display: flex; gap: 10px; font-size: 11px;">
                                        <?php $is_liked = !empty($msg['is_liked']); ?>
                                        <span class="like-btn" id="like-btn-<?= $msg['id']; ?>"
                                            onclick="toggleLike(<?= $msg['id']; ?>)"
                                            style="cursor:pointer; color: <?= $is_liked ? '#e91e63' : '#ccc'; ?>;">
                                            ❤️<span id="like-count-<?= $msg['id']; ?>"><?= $msg['like_count'] ?? 0; ?></span>
                                        </span>
                                    </div>

                                    <span class="msg-meta"><?= date('m/d H:i', strtotime($msg['created_at'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: #bbb; text-align: center; margin-top: 120px; font-size: 13px;">メッセージはまだありません</p>
                    <?php endif; ?>
                </div>

                <form action="index.php?page=chat<?= $room_id ? '&room_id=' . $room_id : '&receiver_id=' . $receiver_id; ?>"
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
        // 最下部へスクロール
        const t = document.getElementById('chatTimeline');
        if (t) { t.scrollTop = t.scrollHeight; }

        // 二重送信防止
        const chatForm = document.querySelector('.chat-input-area');
        if (chatForm) {
            chatForm.addEventListener('submit', function () {
                const btn = this.querySelector('button[type="submit"]');
                btn.disabled = true;
                btn.textContent = '送信中...';
            });
        }
    });

    function confirmDelete() {
        return confirm("このメッセージを自分の画面から削除しますか？\n(送信相手の画面には残り続けます)");
    }

    function searchUsers() {
        const keyword = document.getElementById('userSearchInput').value;
        fetch('app/templates/chat_view/search_users.php?q=' + encodeURIComponent(keyword))
            .then(response => response.json())
            .then(users => {
                const container = document.getElementById('userResults');
                container.innerHTML = '';

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
            .catch(error => console.error('エラー:', error));
    }

    /**
     * メッセージのいいね（Like）状態を切り替える非同期関数
     * @param {number|string} messageId - 対象メッセージのID
     */
    // function toggleLike(messageId) {
    //     if (!messageId) {
    //         console.warn('引数 messageId が指定されていません。');
    //         return;
    //     }

    //     // 現在のベースパス（例: /index.php）を取得し、クエリパラメータを組み立て
    //     const currentPath = window.location.pathname;
    //     // パスが重複しないよう、純粋なドメインルートからのパラメータにする
    //     const requestUrl = `/index.php?page=like_message&message_id=${encodeURIComponent(messageId)}`;

    //     // もしコントローラー(index.php)を経由せず、直接PHPファイルを叩く場合は以下に切り替えてください
    //     // const requestUrl = `app/templates/chat_view/like_message.php?message_id=${encodeURIComponent(messageId)}`;

    //     fetch(requestUrl, { 
    //         method: 'GET',
    //         headers: {
    //             'X-Requested-With': 'XMLHttpRequest' // サーバー側でAjax通信判定を行う場合に便利
    //         }
    //     })
    //     .then(response => {
    //         // ネットワーク的なステータスコード（200番台以外）のチェック
    //         if (!response.ok) { 
    //             throw new Error(`ネットワーク応答エラー (Status: ${response.status})`); 
    //         }
    //         return response.json();
    //     })
    //     .then(data => {
    //         // サーバー側の処理成否のチェック
    //         if (!data || (data.status !== 'liked' && data.status !== 'unliked')) {
    //             throw new Error(data.message || 'サーバー側で予期せぬエラーが発生しました。');
    //         }

    //         // HTML要素の取得
    //         const btnElem = document.getElementById(`like-btn-${messageId}`);
    //         const countElem = document.getElementById(`like-count-${messageId}`);

    //         if (!btnElem || !countElem) {
    //             throw new Error(`対象のDOM要素が見つかりません。 (ID: like-btn-${messageId} または like-count-${messageId})`);
    //         }

    //         // 現在のいいね数を取得（パース失敗時は0）
    //         const currentCount = parseInt(countElem.textContent, 10) || 0;

    //         // 状態（status）に応じたフロントエンドの表示更新
    //         if (data.status === 'liked') {
    //             btnElem.style.color = '#e91e63'; // ピンク
    //             countElem.textContent = currentCount + 1;
    //         } else if (data.status === 'unliked') {
    //             btnElem.style.color = '#ccc'; // グレー
    //             countElem.textContent = Math.max(0, currentCount - 1); // 0未満にならないよう防御
    //         }
    //     })
    //     .catch(error => {
    //         // ネットワークエラー、JSONパースエラー、throwしたエラーがすべてここに集約されます
    //         console.error('いいね処理でエラーが発生しました:', error.message || error);
    //         alert('いいね処理に失敗しました。時間をおいて再度お試しください。');
    //     });
    // }
    function toggleLike(messageId) {
        if (!messageId) return;

        // 現在のURLのパラメータ（?page=chat&receiver_id=6 など）を丸ごと取得
        const currentParams = window.location.search;

        // 基本のパラメータオブジェクトを作成
        const urlParams = new URLSearchParams(currentParams);

        // pageを強制的に 'like_message' に上書きし、message_id を追加する
        urlParams.set('page', 'like_message');
        urlParams.set('message_id', messageId);

        // 組み立てたパラメータでURLを作成
        const requestUrl = `/index.php?${urlParams.toString()}`;

        // デバッグ用（どんなURLで送られるかコンソールで確認できます）
        console.log("リクエストURL:", requestUrl);

        // 通常のFetch処理（パースのエラーハンドリング付き）
        fetch(requestUrl, { method: 'GET' })
            .then(response => {
                if (!response.ok) throw new Error('ネットワーク応答エラー');
                return response.json();
            })
            .then(data => {
                // サーバーから正常に 'liked' / 'unliked' が返ってきた場合の処理
                if (data.status === 'liked' || data.status === 'unliked') {
                    const btnElem = document.getElementById(`like-btn-${messageId}`);
                    const countElem = document.getElementById(`like-count-${messageId}`);
                    let currentCount = parseInt(countElem.textContent, 10) || 0;

                    if (data.status === 'liked') {
                        btnElem.style.color = '#e91e63';
                        countElem.textContent = currentCount + 1;
                    } else if (data.status === 'unliked') {
                        btnElem.style.color = '#ccc';
                        countElem.textContent = Math.max(0, currentCount - 1);
                    }
                } else {
                    // サーバー側が 'error' などを返してきた場合はそのメッセージを出す
                    alert('サーバーエラー: ' + (data.message || '処理に失敗しました'));
                }
            })
            .catch(error => {
                console.error('通信またはパースエラー:', error);
                alert('通信エラーが発生しました。');
            });
    }
</script>

<?php
if (file_exists(__DIR__ . '/../layout/footer.php') && !defined('FOOTER_INCLUDED')) {
    include __DIR__ . '/../layout/footer.php';
    define('FOOTER_INCLUDED', true);
}
?>