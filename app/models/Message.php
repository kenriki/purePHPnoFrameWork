<?php
// app/models/Message.php

class Message
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * メッセージを送信（保存）する
     * * @param int $sender_id   送信者のユーザーID
     * @param int $receiver_id 受信者のユーザーID
     * @param string $message  メッセージ本文
     * @return bool
     */
    public function sendMessage($sender_id, $receiver_id, $message)
    {
        $sql = "INSERT INTO messages (sender_id, receiver_id, message, is_read, created_at) 
                VALUES (:sender_id, :receiver_id, :message, 0, NOW())";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':sender_id' => $sender_id,
            ':receiver_id' => $receiver_id,
            ':message' => $message
        ]);
    }

    /**
     * 特定の2人同士のチャット履歴を取得する
     * * @param int $user_id1
     * @return array
     */
    // public function getChatHistory($my_id, $partner_id)
    // {
    //     $stmt = $this->pdo->prepare("
    //     SELECT * FROM messages 
    //     WHERE ((sender_id = :my_id AND receiver_id = :partner_id) 
    //        OR (sender_id = :partner_id AND receiver_id = :my_id))
    //     AND (deleted_by_user_id IS NULL OR deleted_by_user_id != :my_id)
    //     ORDER BY created_at ASC
    // ");
    //     $stmt->execute([':my_id' => $my_id, ':partner_id' => $partner_id]);
    //     return $stmt->fetchAll(PDO::FETCH_ASSOC);
    // }
    public function getChatHistory($my_id, $partner_id) {
        // 💡 SQLのSELECT項目に、総いいね数と、自分がいいねしたかのフラグを追加します
        $sql = "SELECT m.*, u.username AS sender_name,
                    (SELECT COUNT(*) FROM message_likes WHERE message_id = m.id) AS like_count,
                    (SELECT COUNT(*) FROM message_likes WHERE message_id = m.id AND user_id = :my_id) AS is_liked
                FROM messages m
                JOIN users u ON m.sender_id = u.id
                WHERE ((m.sender_id = :my_id AND m.receiver_id = :partner_id)
                OR (m.sender_id = :partner_id AND m.receiver_id = :my_id))
                AND (m.deleted_by_user_id IS NULL OR m.deleted_by_user_id != :my_id)
                ORDER BY m.created_at ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':my_id' => $my_id,
            ':partner_id' => $partner_id
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * メッセージを既読にする
     * チャット画面を開いた際、相手から自分宛ての未読メッセージをすべて既読(is_read = 1)にします
     * * @param int $partner_id 相手のユーザーID（送信者）
     * @param int $my_id      自分のユーザーID（受信者）
     * @return bool
     */
    public function markAsRead($partner_id, $my_id)
    {
        $sql = "UPDATE messages 
                SET is_read = 1 
                WHERE sender_id = :partner_id 
                  AND receiver_id = :my_id 
                  AND is_read = 0";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':partner_id' => $partner_id,
            ':my_id' => $my_id
        ]);
    }

    /**
     * 自分宛ての総未読メッセージ数を取得する
     * * @param int $my_id 自分のユーザーID
     * @return int 未読件数
     */
    public function getUnreadCount($my_id)
    {
        $sql = "SELECT COUNT(*) FROM messages WHERE receiver_id = :my_id AND is_read = 0";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':my_id' => $my_id]);
        return (int) $stmt->fetchColumn();
    }
    /**
     * チャット相手ごとの最新メッセージを取得する
     */
    public function getChatList($my_id)
    {
        // 相手のIDを特定し、そのユーザー名を取得するSQL
        $sql = "SELECT m.*, 
                   u.username as sender_name,
                   CASE WHEN m.sender_id = :my_id THEN m.receiver_id ELSE m.sender_id END as partner_id
            FROM messages m
            JOIN users u ON u.id = (CASE WHEN m.sender_id = :my_id THEN m.receiver_id ELSE m.sender_id END)
            WHERE m.id IN (
                SELECT MAX(id) 
                FROM messages 
                WHERE (sender_id = :my_id OR receiver_id = :my_id)
                AND (deleted_by_user_id IS NULL OR deleted_by_user_id != :my_id)
                GROUP BY CASE WHEN sender_id = :my_id THEN receiver_id ELSE sender_id END
            )
            ORDER BY m.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':my_id' => $my_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 特定の相手とのチャット履歴をすべて削除する
     * @param int $my_id 自分のID
     * @param int $partner_id 相手のID
     */
    public function deleteChatHistory($my_id, $partner_id)
    {
        // 物理削除ではなく、deleted_by_user_id を更新する
        $sql = "UPDATE messages 
                SET deleted_by_user_id = :my_id 
                WHERE (sender_id = :my_id AND receiver_id = :partner_id)
                   OR (sender_id = :partner_id AND receiver_id = :my_id)";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':my_id' => $my_id,
            ':partner_id' => $partner_id
        ]);
    }

    /**
     * グループチャット用にメッセージを送信する
     */
    public function sendGroupMessage($sender_id, $room_id, $message)
    {
        $sql = "INSERT INTO messages (sender_id, room_id, message, is_read, created_at) 
                VALUES (:sender_id, :room_id, :message, 0, NOW())";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':sender_id' => $sender_id,
            ':room_id' => $room_id,
            ':message' => $message
        ]);
    }

    /**
     * グループのチャット履歴を取得
     */
    public function getGroupChatHistory($room_id, $my_id)
    {
        $stmt = $this->pdo->prepare("
            SELECT m.*, u.username as sender_name 
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.room_id = :room_id
            AND (m.deleted_by_user_id IS NULL OR m.deleted_by_user_id != :my_id)
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([':room_id' => $room_id, ':my_id' => $my_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** グループ一覧を取得 */
    public function getGroupList($my_id)
    {
        $sql = "SELECT r.id, r.name 
                FROM rooms r
                JOIN room_members rm ON r.id = rm.room_id
                WHERE rm.user_id = :my_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':my_id' => $my_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRoomMessages($room_id, $my_id) {
        // JOIN を LEFT JOIN に変更し、sender_id が 0 や NULL のシステムメッセージも取得可能にします
        $sql = "SELECT m.*, 
                       COALESCE(u.username, 'システム') AS sender_name,
                       (SELECT COUNT(*) FROM message_likes WHERE message_id = m.id) AS like_count,
                       (SELECT COUNT(*) FROM message_likes WHERE message_id = m.id AND user_id = :my_id) AS is_liked
                FROM messages m
                LEFT JOIN users u ON m.sender_id = u.id
                WHERE m.room_id = :room_id
                AND (m.deleted_by_user_id IS NULL OR m.deleted_by_user_id = 0 OR m.deleted_by_user_id != :my_id)
                ORDER BY m.created_at ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':room_id' => $room_id,
            ':my_id'   => $my_id
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // グループチャットの未読数
    public function getUnreadGroupCount($user_id)
    {
        $sql = "SELECT COUNT(m.id) 
            FROM messages m
            JOIN room_members rm ON m.room_id = rm.room_id
            WHERE rm.user_id = :user_id 
            AND m.is_read = 0 
            AND m.sender_id != :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);
        return (int) $stmt->fetchColumn();
    }

    function generateUniqueRoomCode($pdo)
    {
        do {
            // 8桁のランダムな英数字を生成
            $code = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8);

            // 重複チェック
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM rooms WHERE room_code = ?");
            $stmt->execute([$code]);
        } while ($stmt->fetchColumn() > 0); // 重複があれば再生成

        return $code;
    }
    /**
     * メッセージにいいねを追加・削除（トグル）
     */
    public function toggleLike($message_id, $user_id)
    {
        // 既にいいねしているか確認
        $stmt = $this->pdo->prepare("SELECT id FROM message_likes WHERE message_id = ? AND user_id = ?");
        $stmt->execute([$message_id, $user_id]);

        if ($stmt->fetch()) {
            // あれば削除
            $stmt = $this->pdo->prepare("DELETE FROM message_likes WHERE message_id = ? AND user_id = ?");
            return $stmt->execute([$message_id, $user_id]);
        } else {
            // なければ追加
            $stmt = $this->pdo->prepare("INSERT INTO message_likes (message_id, user_id) VALUES (?, ?)");
            return $stmt->execute([$message_id, $user_id]);
        }
    }

    /**
     * ルーム内のメッセージに既読を記録（グループ用）
     */
    public function markRoomMessageAsRead($room_id, $user_id)
    {
        // まだ既読テーブルにないレコードのみを追加
        $sql = "INSERT INTO read_receipts (message_id, user_id)
            SELECT m.id, ? FROM messages m
            WHERE m.room_id = ?
            AND NOT EXISTS (SELECT 1 FROM read_receipts rr WHERE rr.message_id = m.id AND rr.user_id = ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$user_id, $room_id, $user_id]);
    }

}