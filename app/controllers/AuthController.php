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
            $pass = $_POST['password'] ?? '';

            $token = bin2hex(random_bytes(32));
            $hashed_password = password_hash($pass, PASSWORD_DEFAULT);

            try {
                // ここで切り出したメソッドをコールします
                $new_id = $this->getNextUserId($db);

                // INSERT文の準備（? は 5 つ）
                $stmt = $db->prepare("INSERT INTO users (id, username, email, password, login_token) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$new_id, $user, $email, $hashed_password, $token]);

                $autoLoginUrl = "http://{$_SERVER['HTTP_HOST']}/index.php?page=autologin&token={$token}";

                $subject = "【Sample Site】会員登録完了のお知らせ";
                $body = "{$user} 様\n\n登録完了しました。\n\n"
                    . "■あなたのログイン情報\n"
                    . "ユーザー名: {$user}\n"
                    . "パスワード: {$pass}\n\n"
                    . "▼自動ログインURL \n"
                    . "{$autoLoginUrl}\n\n"
                    . "※通常のログインはこちら：\n"
                    . "http://{$_SERVER['HTTP_HOST']}/index.php?page=login";

                MailUtil::sendMail($email, $subject, $body);

                echo "<div style='text-align:center; padding:100px 20px;'><h1>登録完了！</h1><p><a href='index.php?page=login'>ログイン画面へ</a></p></div>";
                exit;

            } catch (PDOException $e) {
                die("エラー：" . $e->getMessage());
            }
        }
    }

    // 自動ログイン
    public function autologin()
    {
        // セッション開始
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        $token = $_GET['token'] ?? '';
        if (!$token) {
            die("トークンがありません。");
        }

        $db = getDB();
        $stmt = $db->prepare("SELECT id, username FROM users WHERE login_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            // ログイン情報をセッションにセット
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

            // ★修正ポイント：リダイレクト先を home に変更し、JSで確実に遷移させる
            echo "<script>location.href = 'index.php?page=home';</script>";
            exit;
        } else {
            die("無効なログインリンクです。");
        }
    }

    public function reset_password()
    {
        // 入力値の取得（trimを追加して余計なスペースを削除）
        $email = trim($_POST['email'] ?? '');
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // 1. 新しいパスワードの一致確認
        if ($new_password !== $confirm_password) {
            echo "<script>alert('新しいパスワードが一致しません'); history.back();</script>";
            return;
        }

        $db = getDB();

        // 2. ユーザーの存在確認
        $stmt = $db->prepare("SELECT id, password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            echo "<script>alert('メールアドレスが登録されていません'); history.back();</script>";
            exit;
        }

        // 3. 現在のパスワード照合
        if (!password_verify($current_password, $user['password'])) {
            echo "<script>alert('現在のパスワードが違います'); history.back();</script>";
            exit;
        }

        // --------------------------------------------------
        // 4. パスワード更新 ＆ 自動ログイン用トークンの生成
        // --------------------------------------------------
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);

        // 新しい自動ログイン用トークンを発行（既存のものを上書きして安全性を高める）
        $newToken = bin2hex(random_bytes(32));

        // DB更新（パスワードとトークンの両方をセット）
        $stmt = $db->prepare("UPDATE users SET password = ?, login_token = ?, update_date = NOW() WHERE id = ?");
        $stmt->execute([$hashed, $newToken, $user['id']]);

        // 自動ログインURLの組み立て
        $autoLoginUrl = "http://{$_SERVER['HTTP_HOST']}/index.php?page=autologin&token={$newToken}";

        // ---------------------------
        // メール送信（PHPMailer）
        // ---------------------------
        require_once APP_ROOT . '/app/lib/PHPMailer/src/PHPMailer.php';
        require_once APP_ROOT . '/app/lib/PHPMailer/src/SMTP.php';
        require_once APP_ROOT . '/app/lib/PHPMailer/src/Exception.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        try {
            // SMTP設定
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = 'tls';
            $mail->Port = SMTP_PORT;

            // 文字化け対策
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';

            // 送信元・宛先
            $mail->setFrom(ADMIN_EMAIL, 'Sample Site 管理者');
            if (!empty($email)) {
                $mail->addAddress($email);
            } else {
                throw new Exception('宛先メールアドレスが正しく取得できていません。');
            }

            // 件名
            $mail->Subject = '【Sample Site】パスワード更新完了のお知らせ';

            // 本文（自動ログインURLを追記）
            $mail->Body = "パスワードの更新が完了しました。\n\n"
                . "■新しいパスワード： " . $new_password . "\n\n"
                . "▼こちらから自動ログインできます：\n"
                . $autoLoginUrl . "\n\n"
                . "※このメールに心当たりがない場合は、至急管理者へご連絡ください。";

            $mail->send();

            echo "<p style='color:green; text-align:center; padding:50px;'>パスワードを更新し、通知メールを送信しました。<br><a href='index.php?page=home'>ホームへ戻る</a></p>";

        } catch (Exception $e) {
            // パスワード更新自体は成功しているので、その旨を伝える
            echo "<p style='color:orange;'>パスワードは更新されましたが、メール送信に失敗しました: {$mail->ErrorInfo}</p>";
        }
    }

    /**
     * usersテーブルから、現在空いている最小のIDを取得する
     */
    private function getNextUserId()
    {
        $db = getDB();

        // 1から順に「存在しない番号」の最小値を探します。
        // 全くデータがない場合は 1、1,2,4 とあれば 3 を返します。
        $sql = "
            SELECT min_id + 1 AS next_id
            FROM (
                SELECT 0 AS min_id
                UNION ALL
                SELECT id FROM users
            ) AS existing_ids
            WHERE NOT EXISTS (
                SELECT 1 FROM users 
                WHERE id = existing_ids.min_id + 1
            )
            ORDER BY next_id ASC
            LIMIT 1
        ";

        $stmt = $db->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? (int) $result['next_id'] : 1;
    }

    // 認証コード送信
    public function forgot_password_send()
    {
        $email = trim($_POST['email'] ?? '');

        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            echo "<script>alert('メールアドレスが登録されていません'); history.back();</script>";
            return;
        }

        // 6桁コード生成
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

        // DB保存
        $stmt = $db->prepare("UPDATE users SET reset_code=?, reset_expires=? WHERE id=?");
        $stmt->execute([$code, $expires, $user['id']]);

        // メール送信
        MailUtil::sendMail(
            $email,
            "【Sample Site】パスワード再設定コード",
            "認証コード：{$code}\n有効期限：1時間"
        );

        header("Location: index.php?page=forgot_password_verify&email={$email}");
        exit;
    }

    public function forgot_password_verify()
    {
        $email = $_POST['email'] ?? $_GET['email'] ?? '';
        $code = $_POST['code'] ?? '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = getDB();
            $stmt = $db->prepare("SELECT id, reset_code, reset_expires FROM users WHERE email=?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || $user['reset_code'] !== $code) {
                echo "<script>alert('認証コードが違います'); history.back();</script>";
                return;
            }

            if (new DateTime() > new DateTime($user['reset_expires'])) {
                echo "<script>alert('認証コードの有効期限が切れています'); history.back();</script>";
                return;
            }

            header("Location: index.php?page=forgot_password_reset&email={$email}");
            exit;
        }

        include TEMPLATE_PATH . 'auth/forgot_password_verify.php';
    }

    public function forgot_password_reset()
    {
        $email = $_POST['email'] ?? $_GET['email'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $conf = $_POST['confirm_password'] ?? '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            if ($new !== $conf) {
                echo "<script>alert('パスワードが一致しません'); history.back();</script>";
                return;
            }

            $db = getDB();
            $hashed = password_hash($new, PASSWORD_DEFAULT);

            $stmt = $db->prepare("
                UPDATE users 
                SET password=?, reset_code=NULL, reset_expires=NULL, update_date = NOW()
                WHERE email=?
            ");
            $stmt->execute([$hashed, $email]);

            echo "
            <div style='max-width:300px; margin:50px auto; padding:20px; text-align:center;'>
                <p>パスワードを更新しました。</p>
                <p><a href='index.php?page=login'>ログイン画面へ戻る</a></p>
            </div>
            ";
            return;
        }

        include TEMPLATE_PATH . 'auth/forgot_password_reset.php';
    }


}
?>