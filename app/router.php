<?php

// コントローラーの読み込み
require_once __DIR__ . '/controllers/PageController.php';
foreach (glob(__DIR__ . '/controllers/*Controller.php') as $filename) {
    require_once $filename;
}

// 認証チェック関数
function checkAuthentication($page) {
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

function route($page) {
    checkAuthentication($page);

    // 認証系（ログイン・登録・ログアウト）の振り分け
    if (in_array($page, ['login', 'register', 'logout'])) {
        $controller = new AuthController();
        if ($page === 'logout') {
            return $controller->logout();
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return $controller->$page();
        }
    }
    // どの専用処理にも当てはまらない場合はページを表示
    return (new PageController())->render($page);
}
?>