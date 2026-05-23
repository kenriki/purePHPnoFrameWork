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
     * @param int $user_id2
     * @return array
     */
    public function getChatHistory($user_id1, $user_id2)
    {
        $sql = "SELECT * FROM messages 
                WHERE (sender_id = :u1 AND receiver_id = :u2) 
                   OR (sender_id = :u2 AND receiver_id = :u1) 
                ORDER BY created_at ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':u1' => $user_id1,
            ':u2' => $user_id2
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
        $sql = "SELECT m.*, u.username as sender_name 
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.id IN (
                SELECT MAX(id) 
                FROM messages 
                WHERE sender_id = :my_id OR receiver_id = :my_id 
                GROUP BY 
                    CASE WHEN sender_id = :my_id THEN receiver_id ELSE sender_id END
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
        $sql = "DELETE FROM messages 
            WHERE (sender_id = :my_id AND receiver_id = :partner_id)
               OR (sender_id = :partner_id AND receiver_id = :my_id)";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':my_id' => $my_id,
            ':partner_id' => $partner_id
        ]);
    }
}