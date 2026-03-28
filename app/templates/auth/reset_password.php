<?php
// 設定・共通クラス読み込み
require_once APP_ROOT . '/app/dbconfig.php';
require_once APP_ROOT . '/app/utils/MailUtil.php';

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

// ★ メール送信処理 (MailUtilを使用)
$subject = "パスワード再設定が完了しました。";
$body = "以下のパスワードに更新されました：\n" .
    "[" . $new_password . "] ログイン後、必要に応じてパスワードを変更してください。";

$result = MailUtil::sendMail($email, $subject, $body, 'パスワード再設定');

if ($result === true) {
    echo "パスワードを更新し、メールを送信しました。ログイン画面に戻ってください。";
} else {
    echo "パスワードは更新されましたが、メール送信に失敗しました: {$result}";
}
?>