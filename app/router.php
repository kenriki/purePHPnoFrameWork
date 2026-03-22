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
    $public_pages = ['login', 'register'];

    if (!in_array($page, $public_pages)) {
        if (!isset($_SESSION['user_id'])) {
            // ログインしていない場合はログイン画面へ強制移動
            header("Location: index.php?page=login");
            exit;
        }
    }
}
function route($page)
{
    checkAuthentication($page);

    // --- Auth系 ---
    if (in_array($page, ['login', 'register', 'logout'])) {
        $auth = new AuthController();
        if ($page === 'logout')
            return $auth->logout();
        if ($_SERVER['REQUEST_METHOD'] === 'POST')
            return $auth->$page();
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

    // --- 通常の表示（render） ---
    return (new PageController())->render($page);
}
?>