<?php
// chat_view/add_member.php
require_once __DIR__ . '/../../dbconfig.php';
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_id = $_POST['room_id'] ?? 0;
    $name = $_POST['new_member_name'] ?? '';

    // ユーザー検索と追加処理
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$name]);
    $user = $stmt->fetch();

    if ($user) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO room_members (room_id, user_id) VALUES (?, ?)");
        $stmt->execute([$room_id, $user['id']]);
    }
    
    // 完了後、チャット画面に戻る
    header("Location: ../../index.php?page=chat&room_id=" . $room_id);
    exit;
}
?>