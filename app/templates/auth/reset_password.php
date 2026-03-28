<?php
require_once __DIR__ . '/../../dbconfig.php';

// PHPMailer 読み込み
require_once __DIR__ . '/../../lib/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../lib/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../../lib/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// POSTデータ取得
$email = $_POST['email'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// パスワード一致チェック
if ($new_password !== $confirm_password) {
    echo "パスワードが一致しません。戻って再入力してください。";
    exit;
}

// メールアドレス存在チェック
$sql = "SELECT id FROM users WHERE email = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    echo "このメールアドレスは登録されていません。";
    exit;
}

// パスワードをハッシュ化
$hash = password_hash($new_password, PASSWORD_BCRYPT);

// パスワード更新
$update_sql = "UPDATE users SET password = ? WHERE email = ?";
$update_stmt = $pdo->prepare($update_sql);
$update_stmt->execute([$hash, $email]);

// ★ メール送信処理
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    $mail->SMTPSecure = 'tls';
    $mail->Port = SMTP_PORT;

    // ★ 日本語メール完全対応（Gmailで100%文字化けしない）
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';

    // ★ 件名（ISO-2022-JP → Base64）
    $subject = 'Password Reset Notification';
    $mail->Subject = '=?ISO-2022-JP?B?' . base64_encode(mb_convert_encoding($subject, 'ISO-2022-JP')) . '?=';

    // ★ From 名（ISO-2022-JP → Base64）
    $fromName = 'Sample Site';
    $mail->setFrom(
        ADMIN_EMAIL,
        '=?ISO-2022-JP?B?' . base64_encode(mb_convert_encoding($fromName, 'ISO-2022-JP')) . '?='
    );

    $mail->addAddress($email);

    // ★ 本文は UTF-8 のままでも OK（Gmail は本文は UTF-8 を正しく処理する）
    $mail->Body =
        "以下のパスワードに更新されました：\n" .
        "$new_password\n\n" .
        "ログイン後、必要に応じてパスワードを変更してください。";

    $mail->send();

    echo "パスワードを更新し、メールを送信しました。ログイン画面に戻ってください。";

} catch (Exception $e) {
    echo "パスワードは更新されましたが、メール送信に失敗しました: {$mail->ErrorInfo}";
}
?>