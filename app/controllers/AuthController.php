<?php
require_once __DIR__ . '/../utils/MailUtil.php';

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
            $email = $_POST['email'] ?? '';
            $pass = $_POST['password'] ?? ''; // 生のパスワードを保持

            // 自動ログイン用トークン（1回限りにしないためDBに保持し続ける）
            $token = bin2hex(random_bytes(32));
            $hashed_password = password_hash($pass, PASSWORD_DEFAULT);

            try {
                $stmt = $db->prepare("INSERT INTO users (username, email, password, login_token) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user, $email, $hashed_password, $token]);

                $autoLoginUrl = "http://{$_SERVER['HTTP_HOST']}/index.php?page=autologin&token={$token}";

                $subject = "【Sample Site】会員登録完了のお知らせ";
                // ★本文にパスワードを追加
                $body = "{$user} 様\n\n登録完了しました。\n\n"
                    . "■あなたのログイン情報\n"
                    . "ユーザー名: {$user}\n"
                    . "パスワード: {$pass}\n\n" // 生パスワードを表示
                    . "▼自動ログイン\n"
                    . "{$autoLoginUrl}\n\n"
                    . "※通常のログインはこちら：\n"
                    . "http://{$_SERVER['HTTP_HOST']}/index.php?page=login";

                MailUtil::sendMail($email, $subject, $body);

                // 完了表示（image_4ad0a3.pngをベースに）
                echo "<div style='text-align:center; padding:100px 20px;'><h1>登録完了！</h1><p><a href='index.php?page=login'>ログイン画面へ</a></p></div>";
                exit;

            } catch (PDOException $e) {
                die("エラー：" . $e->getMessage());
            }
        }
    }

    // 自動ログイン処理（1回限りにしない版）
    public function autologin()
    {
        $token = $_GET['token'] ?? '';
        if (!$token)
            die("トークンがありません。");

        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE login_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            // セッション開始
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

            // ★トークンをNULLにしない（UPDATE処理を削除）ことで、何度でもURLが使えます

            header("Location: index.php?page=home"); // マイページへ
            exit;
        } else {
            die("無効なログインリンクです。");
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