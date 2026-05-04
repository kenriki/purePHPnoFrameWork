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
$action = $_GET['action'] ?? '';

/**
 * 3. ログイン・アクティブ状態の管理とGoogle連携チェック
 */
$isGoogleLinked = false;
if (isset($_SESSION['user_id'])) {
    try {
        $db = getDB();

        // アクティブ時間の更新
        $stmtActive = $db->prepare("UPDATE users SET last_active_at = NOW() WHERE id = ?");
        $stmtActive->execute([$_SESSION['user_id']]);

        // Google連携状態の確認 (トークンの有無)
        $stmtToken = $db->prepare("SELECT user_name FROM google_tokens WHERE user_name = ?");
        $userName = $_SESSION['user_id'] ?? 'guest';
        $stmtToken->execute([$userName]);
        if ($stmtToken->fetch()) {
            $isGoogleLinked = true;
        }

    } catch (Exception $e) {
        error_log("Session/Token check failed: " . $e->getMessage());
    }
}

/**
 * 4. Google認証・連携専用ルーティング
 * 連携ボタンから page=google_auth でアクセスされた場合
 */
if ($page === 'google_auth') {
    // auth.php へリダイレクト
    header("Location: auth.php");
    exit;
}

/**
 * 5. 共有メモ閲覧（ログイン不要）
 */
if ($page === 'view_share') {
    require_once __DIR__ . '/../app/controllers/MemoController.php';
    $controller = new MemoController();
    $controller->view_share();
    exit;
}

/**
 * 6. 共有URL生成処理
 */
if ($page === 'generate_share_url') {
    require_once __DIR__ . '/../app/controllers/MemoController.php';
    $controller = new MemoController();
    $controller->generate_share_url();
    exit;
}

/**
 * 7. 合言葉（guest_name）のセッション保存処理
 */
if ($page === 'memo' && $action === 'set_guest_name') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $_SESSION['guest_name'] = $_POST['guest_name'] ?? '';
    }
    header("Location: /index.php?page=memo&action=list");
    exit;
}

/**
 * 8. 特定のページ（memo）に対するカスタムルーティング
 */
if ($page === 'memo') {
    $pageId = 'memo';
    require_once __DIR__ . '/../app/controllers/MemoController.php';

    // Google未連携の状態でカレンダー操作をしようとした場合の警告用フラグを渡す
    $controller = new MemoController();
    $data = $controller->handleRequest();

    // データの展開（$isGoogleLinked をビューで使えるようにする）
    $data['isGoogleLinked'] = $isGoogleLinked;
    extract($data);

    include __DIR__ . '/../app/templates/memo/page.php';
    exit;
}

/**
 * 9. マイメモ一覧（memo_list）に対するカスタムルーティング
 */
if ($page === 'memo_list') {
    require_once __DIR__ . '/../app/controllers/PageController.php';

    $controller = new PageController();
    $pageData = $controller->showMemoList();

    $pageId = 'memo_list';

    include __DIR__ . '/../app/templates/layout/header.php';
    include __DIR__ . '/../app/templates/memo_list/page.php';
    include __DIR__ . '/../app/templates/layout/footer.php';
    exit;
}

/**
 * 10. 既存のルーティングの実行（HOMEなど）
 */
route($page);
?>