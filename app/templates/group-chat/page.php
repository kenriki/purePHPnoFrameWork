<?php
// group-chat/page.php
require_once __DIR__ . '/../dbconfig.php';
$pdo = getDB();

$room_id = $_GET['room_id'] ?? null;
$room = null;
$messages = [];

if ($room_id) {
    // 1. グループ名取得
    $stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
    $stmt->execute([$room_id]);
    $room = $stmt->fetch();

    // 2. メッセージ取得 (room_id指定)
    $stmt = $pdo->prepare("SELECT m.*, u.username FROM messages m 
                           JOIN users u ON m.sender_id = u.id 
                           WHERE m.room_id = ? ORDER BY created_at ASC");
    $stmt->execute([$room_id]);
    $messages = $stmt->fetchAll();
}
?>

<div class="chat-main">
    <h3><?= htmlspecialchars($room['name'] ?? 'グループ未選択') ?></h3>
    <div class="chat-timeline">
        <?php foreach ($messages as $msg): ?>
            <div class="msg-row <?= ($msg['sender_id'] == $_SESSION['user_id']) ? 'me' : 'partner' ?>">
                <div class="msg-bubble">
                    <small><?= htmlspecialchars($msg['username']) ?></small><br>
                    <?= nl2br(htmlspecialchars($msg['message'])) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <form method="POST" action="send_group_msg.php">
        <input type="hidden" name="room_id" value="<?= $room_id ?>">
        <textarea name="message"></textarea>
        <button type="submit">送信</button>
    </form>
</div>