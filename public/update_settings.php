<?php
// update_settings.php
session_start();
require_once '../app/dbconfig.php'; // getDB()関数が含まれているファイル

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['api_key'])) {

    // ユーザーIDの取得
    $userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;
    $newKey = $_POST['api_key'];

    if (!$userId) {
        echo "<script>alert('セッションが切れています。ログインし直してください。'); window.location.href='index.php';</script>";
        exit;
    }

    try {
        // getDB() 関数を呼び出してDB接続を取得
        $db = getDB();

        // DBのusersテーブルにキーを保存
        $stmt = $db->prepare("UPDATE users SET gemini_api_key = ? WHERE id = ?");

        if ($stmt->execute([$newKey, $userId])) {
            // ★ここがポイント：JSのアラートを出してからリダイレクト
            echo "<script>
                alert('あなたのユーザ情報にキーを保存しました');
                window.location.href = 'index.php'; 
            </script>";
            exit;
        } else {
            echo "<script>alert('保存に失敗しました。'); window.history.back();</script>";
        }
    } catch (PDOException $e) {
        $errorMsg = addslashes($e->getMessage()); // エラーメッセージに引用符があっても大丈夫なように
        echo "<script>alert('データベースエラー: {$errorMsg}'); window.history.back();</script>";
    }
}
?>