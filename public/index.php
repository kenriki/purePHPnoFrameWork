<?php
session_start();
date_default_timezone_set('Asia/Tokyo');
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding("UTF-8");

/**
 * 1. 設定・共通ファイルの読み込み
 */
require_once __DIR__ . '/../app/dbconfig.php';
require_once __DIR__ . '/../app/router.php';

// 2. ページパラメータの取得
$page = $_GET['page'] ?? 'home';
$action = $_GET['action'] ?? ''; // actionを追加取得

/**
 * 3. 【追加】合言葉（guest_name）のセッション保存処理
 * フォームから 'set_guest_name' が送られてきたら、ここでセッションに焼く
 */
if ($page === 'memo' && $action === 'set_guest_name') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // 入力された値をセッションに保存
        $_SESSION['guest_name'] = $_POST['guest_name'] ?? '';
    }
    // 保存後、パラメータを綺麗にして一覧画面へリダイレクト（二重送信防止）
    header("Location: /index.php?page=memo&action=list");
    exit;
}

/**
 * 4. 特定のページ（memo）に対するカスタムルーティング
 */
if ($page === 'memo') {
    $pageId = 'memo';
    require_once __DIR__ . '/../app/controllers/MemoController.php';

    $controller = new MemoController();
    $data = $controller->handleRequest();

    // データの展開
    extract($data);

    // ビューの表示
    include __DIR__ . '/../app/templates/memo/page.php';

    exit;
}

/**
 * マイメモ一覧（memo_list）に対するカスタムルーティング
 */
if ($page === 'memo_list') {
    require_once __DIR__ . '/../app/controllers/PageController.php';

    $controller = new PageController();
    // メソッドを実行してデータを取得
    $page = $controller->showMemoList(); 

    // ヘッダーが期待している変数をセットしてエラーを回避
    $pageId = 'memo_list';

    // ヘッダーを読み込む
    include __DIR__ . '/../app/templates/layout/header.php'; 
    // コンテンツ本体を読み込む
    include __DIR__ . '/../app/templates/memo_list/page.php';
    // フッターが必要な場合はここに追加
    include __DIR__ . '/../app/templates/layout/footer.php';
    exit;
}

/**
 * 5. 既存のルーティングの実行（memo 以外）
 */
route($page);
?>