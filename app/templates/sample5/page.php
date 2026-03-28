<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// echo "userId: " . ($_SESSION['user_id'] ?? 'なし') . "<br>";
// echo "userName: " . ($_SESSION['username'] ?? 'なし') . "<br>";
// echo "userMail: " . ($_SESSION['email'] ?? 'なし') . "<br>";


// DB 接続
require_once APP_ROOT . '/app/dbconfig.php';

// PHPMailer 読み込み
require_once APP_ROOT . '/app/lib/PHPMailer/src/PHPMailer.php';
require_once APP_ROOT . '/app/lib/PHPMailer/src/SMTP.php';
require_once APP_ROOT . '/app/lib/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ★ header.php と同じキーでログイン判定
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color:red;'>ログインしていません。</p>";
    return;
}

// ★ header.php と同じキーでユーザー情報を取得
$userId = $_SESSION['user_id'];
$userName = $_SESSION['username'];
$userMail = $_SESSION['email'];

// 送信ボタン押下時
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = 'tls';
        $mail->Port = SMTP_PORT;

        // ★ 日本語が文字化けしないための設定
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        $mail->setFrom(ADMIN_EMAIL, 'ログインユーザー情報送信');
        $mail->addAddress($userMail);

        $mail->Subject = 'ログイン中ユーザー情報';
        $mail->Body =
            "ユーザーID: $userId\n" .
            "名前: $userName\n" .
            "メール: $userMail\n";

        $mail->send();

        echo "<p style='color:green;'>ログイン中ユーザー情報をメール送信しました。</p>";

    } catch (Exception $e) {
        echo "<p style='color:red;'>メール送信に失敗しました: {$mail->ErrorInfo}</p>";
    }
}
?>

<h2>サンプル5のページ</h2>

<form method="post">
    <button type="submit">ログイン中ユーザー情報を送信</button>
</form>