<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 設定・共通クラス読み込み
require_once APP_ROOT . '/app/dbconfig.php';
require_once APP_ROOT . '/app/utils/MailUtil.php';

// ログイン判定
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color:red;'>ログインしていません。</p>";
    return;
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['username'];
$userMail = $_SESSION['email'];

// 送信ボタン押下時
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = 'ログイン中ユーザー情報';
    $body = "ユーザーID: $userId\n" .
        "名前: $userName\n" .
        "メール: $userMail\n";

    // 共通クラスを使って送信
    $result = MailUtil::sendMail($userMail, $subject, $body, 'ログインユーザー情報送信');

    if ($result === true) {
        echo "<p style='color:green;'>ログイン中ユーザー情報をメール送信しました。</p>";
    } else {
        echo "<p style='color:red;'>メール送信に失敗しました: {$result}</p>";
    }
}
?>

<h2>サンプル5のページ</h2>

<form method="post">
    <button type="submit">ログイン中ユーザー情報を送信</button>
</form>