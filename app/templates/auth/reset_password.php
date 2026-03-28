<?php
// 環境設定（UTF-8固定）
mb_internal_encoding("UTF-8");
mb_http_output("UTF-8");
header('Content-Type: text/html; charset=UTF-8');

require_once APP_ROOT . '/app/dbconfig.php';
require_once APP_ROOT . '/app/utils/MailUtil.php';

// POSTデータ取得
$email = $_POST['email'] ?? '';
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// 1. 新しいパスワードの不一致チェック
if ($new_password !== $confirm_password) {
    echo "<script>alert('新しいパスワードが一致しません。'); history.back();</script>";
    exit;
}

// 2. 現在のパスワード照合
$sql = "SELECT password FROM users WHERE email = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$email]);
$user = $stmt->fetch();

// ★ここが最大の防衛ラインです
// ユーザーが見つからない、またはハッシュ値が一致しない場合
if (!$user || !password_verify($current_password, $user['password'])) {
    // 【重要】ここで exit しないと、下の UPDATE 以降が実行されてしまいます
    echo "<script>alert('現在のパスワードが正しくありません。更新は中止されました。'); history.back();</script>";
    exit;
}

// --- これより下は「現在のパスワードが100%一致した時」のみ到達します ---

// 3. パスワード更新実行
$hash = password_hash($new_password, PASSWORD_BCRYPT);
$update_sql = "UPDATE users SET password = ? WHERE email = ?";
$update_stmt = $pdo->prepare($update_sql);
$update_stmt->execute([$hash, $email]);

// 4. 更新が物理的に行われた場合のみメール送信
if ($update_stmt->rowCount() > 0) {

    $subject = "パスワード更新完了通知";
    $body_lines = [
        "パスワードの変更が完了しました。",
        "",
        "新しいパスワード：[" . (string) $new_password . "]",
        "",
        "このパスワードでログインしてください。"
    ];
    $body = implode("\r\n", $body_lines);

    $result = MailUtil::sendMail($email, $subject, $body, 'Sample Site');

    if ($result === true) {
        echo "<script>alert('パスワードを更新しました。'); location.href='index.php?page=login';</script>";
    } else {
        echo "<script>alert('更新は完了しましたが、通知メールの送信に失敗しました。'); location.href='index.php?page=login';</script>";
    }

} else {
    // 新旧が全く同じパスワードだった場合など
    echo "<script>alert('新しいパスワードが現在と同じため、更新の必要はありません。'); history.back();</script>";
}
?>