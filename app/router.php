<?php
// セッションがまだ開始されてなければ開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// コントローラーの読み込み
require_once __DIR__ . '/controllers/PageController.php';
foreach (glob(__DIR__ . '/controllers/*Controller.php') as $filename) {
    require_once $filename;
}

// 認証チェック関数
function checkAuthentication($page)
{
    // ログインしていなくても見れるページ
    $public_pages = [
        'login',
        'register',
        'forgot_password',
        'reset_password',
        'autologin',
        'life360MapX',

        //パスワードを忘れた人向けの新しいページ
        'forgot_password_request',
        'forgot_password_send',
        'forgot_password_verify',
        'forgot_password_reset'
    ];
    // --- 追加：すでにログインしている場合の挙動 ---
    if (isset($_SESSION['user_id'])) {
        // ログイン済みユーザーが、ログイン・登録系ページにアクセスしたらダッシュボードへ
        if (in_array($page, ['login', 'register', 'autologin'])) {
            header("Location: index.php?page=home");
            exit;
        }
    }

    // --- 既存：ログインしていない場合の挙動 ---
    if (!in_array($page, $public_pages)) {
        if (!isset($_SESSION['user_id'])) {
            header("Location: index.php?page=login");
            exit;
        }
    }
}

function route($page)
{
    checkAuthentication($page);

    // --- Auth系 ---
    if (in_array($page, ['login', 'register', 'logout', 'forgot_password', 'reset_password', 'autologin'])) {
        $auth = new AuthController();

        if ($page === 'logout')
            return $auth->logout();

        if ($_SERVER['REQUEST_METHOD'] === 'POST')
            return $auth->$page();

        if ($page === 'autologin') {
            return $auth->autologin();
        }

    }

    // パスワード再設定
    if ($page === 'forgot_password') {
        include __DIR__ . '/templates/auth/forgot_password.php';
        return;
    }

    if ($page === 'reset_password') {
        include __DIR__ . '/templates/auth/reset_password.php';
        return;
    }

    // --- PDF生成の振り分け ---
    if ($page === 'createPDF') {
        $controller = new CreatePDFController();

        // URLに &action=generate がついている時だけ PDF生成(generate)を実行
        if (isset($_GET['action']) && $_GET['action'] === 'generate') {
            return $controller->generate();
        }

        // それ以外の時は、通常の画面表示(show)を実行
        return $controller->show();
    }

    if ($page === 'gemini_proxy') {
        // PageControllerを通さず、直接ファイルを読み込む
        // これにより、余計なヘッダーHTMLが読み込まれるのを防ぎます
        require __DIR__ . '/gemini_proxy.php';
        return;
    }

    // --- Google認証の振り分け ---
    if ($page === 'google_auth') {
        // 認証開始：auth.php へリダイレクト
        // public/auth.php を直接読み込むか、headerで飛ばす
        header("Location: /../public/auth.php");
        exit;
    }

    if ($page === 'google_callback') {
        // 認証後の戻り先：auth-callback.php を読み込む
        require __DIR__ . '/../public/auth-callback.php';
        return;
    }

    // --- アンケートの振り分け ---
    if ($page === 'survey') {
        $controller = new SurveyController();

        // POST送信（保存ボタンが押された時）は store を実行
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return $controller->store();
        }

        // 普通にアクセスした時は show を実行
        return $controller->show();
    }

    // --- パスワード再設定（パスワードを忘れた人向け） ---
    if ($page === 'forgot_password_request') {
        include __DIR__ . '/templates/auth/forgot_password_request.php';
        return;
    }

    if ($page === 'forgot_password_send') {
        $auth = new AuthController();
        return $auth->forgot_password_send();
    }

    if ($page === 'forgot_password_verify') {
        $auth = new AuthController();
        return $auth->forgot_password_verify();
    }

    if ($page === 'forgot_password_reset') {
        $auth = new AuthController();
        return $auth->forgot_password_reset();
    }

    // --- 通常の表示（render） ---
    return (new PageController())->render($page);
}
?>