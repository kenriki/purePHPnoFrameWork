<?php
class AuthController
{
    // 画面表示
    public function show()
    {
        // dbconfig.php で定義した TEMPLATE_PATH を使用
        include TEMPLATE_PATH . 'auth/login.php';
    }

    // ログイン処理
    public function login()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = getDB();
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            // password_verify でハッシュを照合
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                // var_dump($user);
                // exit;
                header("Location: index.php?page=home");
                exit;
            } else {
                echo "<script>alert('ユーザー名またはパスワードが違います'); location.href='?page=login';</script>";
                exit;
            }
        }
    }
    // AuthController クラスの中に追加
    public function logout()
    {
        // セッション変数をすべて解除
        $_SESSION = [];

        // セッションを破棄
        session_destroy();

        // ログイン画面へリダイレクト
        header("Location: index.php?page=login");
        exit;
    }

    // 登録処理
    public function register()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = getDB();
            $user = $_POST['username'] ?? '';
            $pass = $_POST['password'] ?? '';
            $pass_conf = $_POST['password_conf'] ?? '';

            if ($pass !== $pass_conf) {
                die("エラー：パスワードが一致しません。<a href='javascript:history.back()'>戻る</a>");
            }

            // パスワードをハッシュ化
            $hashed_password = password_hash($pass, PASSWORD_DEFAULT);

            try {
                $stmt = $db->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                $stmt->execute([$user, $hashed_password]);

                // 【重要】ここでメッセージを出し、処理を止める
                echo "<div style='text-align:center; padding:50px; font-family:sans-serif;'>";
                echo "<h2>✅ ユーザー「" . htmlspecialchars($user) . "」の登録が完了しました！</h2>";
                echo "<p><a href='index.php?page=login' style='display:inline-block; margin-top:20px; padding:10px 20px; background:#007bff; color:#fff; text-decoration:none; border-radius:5px;'>ログイン画面へ移動する</a></p>";
                echo "</div>";
                exit;
            } catch (PDOException $e) {
                die("エラー：そのユーザー名は既に使われています。<a href='javascript:history.back()'>戻る</a>");
            }
        }
    }

    public function reset_password()
    {
        $email = $_POST['email'] ?? '';
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if ($new_password !== $confirm_password) {
            echo "<p style='color:red;'>パスワードが一致しません。</p>";
            return;
        }

        $db = getDB();
        $stmt = $db->prepare("SELECT id, password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        //var_dump($user); exit;
        if (!$user || !password_verify($current_password, $user['password'])) {
            echo "<script>alert('現在のパスワードが違います'); history.back();</script>";
            exit;
        }

        if (!$user) {
            echo "<p style='color:red;'>メールアドレスが登録されていません。</p>";
            return;
        }

        // パスワード更新
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password = ?, update_date = NOW() WHERE id = ?");
        $stmt->execute([$hashed, $user['id']]);

        // ---------------------------
        // メール送信（PHPMailer）
        // ---------------------------
        require_once APP_ROOT . '/app/lib/PHPMailer/src/PHPMailer.php';
        require_once APP_ROOT . '/app/lib/PHPMailer/src/SMTP.php';
        require_once APP_ROOT . '/app/lib/PHPMailer/src/Exception.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer();

        try {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;        // smtp.gmail.com
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;    // Gmail アドレス
            $mail->Password = SMTP_PASS;    // アプリパスワード
            $mail->SMTPSecure = 'tls';
            $mail->Port = SMTP_PORT;        // 587

            // 送信元（管理メールアドレス）
            $mail->setFrom(ADMIN_EMAIL, 'Sample Site 管理者');

            // 送信先（ユーザー）
            $mail->addAddress($email);

            $mail->Subject = 'パスワードが更新されました';
            $mail->Body = "以下のパスワードに更新されました：\n\n" . $new_password;

            $mail->send();

            echo "<p style='color:green;'>パスワードを更新し、メールを送信しました。</p>";

        } catch (Exception $e) {
            echo "<p style='color:red;'>メール送信に失敗しました: {$mail->ErrorInfo}</p>";
        }
    }


}
?>