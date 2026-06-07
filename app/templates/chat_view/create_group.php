<?php
// index.php ですでにセッションが開始されているため、session_start() は不要です。
require_once __DIR__ . '/../../dbconfig.php';
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 必須チェック
    if (empty($_POST['room_name']) || !isset($_SESSION['user_id'])) {
        die("エラー: 必要な情報が不足しています。");
    }

    $pdo->beginTransaction();
    try {
        // 1. グループを作成（admin_id に作成者のIDを入れる）
        $stmt = $pdo->prepare("INSERT INTO rooms (name, admin_id) VALUES (?, ?)");
        $stmt->execute([$_POST['room_name'], $_SESSION['user_id']]);
        
        $new_room_id = $pdo->lastInsertId();

        // 2. メンバーを追加（選択されたユーザー + 作成者自身）
        $members = $_POST['user_ids'] ?? [];
        $members[] = $_SESSION['user_id']; // 自分自身もメンバーに追加
        
        $stmt = $pdo->prepare("INSERT INTO room_members (room_id, user_id) VALUES (?, ?)");
        
        // 重複を避けるためにarray_uniqueを使用
        foreach (array_unique($members) as $uid) {
            $stmt->execute([$new_room_id, $uid]);
        }
        
        $pdo->commit();
        
        // 作成成功後はチャット画面へ遷移
        header("Location: index.php?page=chat&room_id=" . $new_room_id);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "グループ作成に失敗しました: " . htmlspecialchars($e->getMessage());
    }
} else {
    // POST以外でのアクセスは弾く
    header("Location: index.php?page=chat");
    exit;
}