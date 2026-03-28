<?php

// コントローラーの読み込み
require_once __DIR__ . '/controllers/PageController.php';
foreach (glob(__DIR__ . '/controllers/*Controller.php') as $filename) {
    require_once $filename;
}

// 認証チェック関数
function checkAuthentication($page)
{
    // ログインしていなくても見れるページ
    $public_pages = ['login', 'register', 'forgot_password', 'reset_password', 'autologin'];

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
    // ★ 'autologin' を配列に追加しました
    if (in_array($page, ['login', 'register', 'logout', 'forgot_password', 'reset_password', 'autologin'])) {
        $auth = new AuthController();

        if ($page === 'logout')
            return $auth->logout();

        // ★ 自動ログイン(GETアクセス)を処理するための分岐を追加
        if ($page === 'autologin') {
            return $auth->autologin();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST')
            return $auth->$page();
    }

    // パスワード再設定（表示用）
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

    // --- 通常の表示（render） ---
    return (new PageController())->render($page);
}
?>