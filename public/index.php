<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding("UTF-8");

/**
 * 1. 設定・共通ファイルの読み込み
 */
require_once __DIR__ . '/../app/dbconfig.php';
require_once __DIR__ . '/../app/router.php';

// 2. ページパラメータの取得
$page = $_GET['page'] ?? 'home';

/**
 * 3. 特定のページ（memo）に対するカスタムルーティング
 * router.php の route($page) を呼ぶ前に、新しいコントローラーを差し込みます。
 */
if ($page === 'memo') {
    // --- MemoController の実行 ---
    require_once __DIR__ . '/../app/controllers/MemoController.php';

    $controller = new MemoController();
    $data = $controller->handleRequest(); // ここでブレークポイントが止まるはず！

    // データの展開（Viewで $memos や $action を使えるようにする）
    extract($data);

    // ビューの表示（共通レイアウトを使っている場合は、ここで include する）
    // もし既存の共通ヘッダー等があるなら、それに合わせて include してください
    include __DIR__ . '/../app/templates/memo/page.php';

    exit; // memo の処理が終わったらここで終了（下の route($page) は実行させない）
}

/**
 * 4. 既存のルーティングの実行（memo 以外はこちらで処理）
 */
route($page);
?>