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
// index.php の冒頭
$pageId = $_GET['page'] ?? 'home'; // URLパラメータから取得、なければhome
$page = []; // 空の配列で初期化しておく
$page = $_GET['page'] ?? 'home';
$api_request = $_GET['api'] ?? '';
$pageId = $page; // pageIdを初期化
$action = $_GET['action'] ?? '';

if ($api_request === 'equipment') {
    require_once __DIR__ . '/../app/api/api_equipment.php';
    exit;
}
if ($action === 'ask') {
//if (isset($_GET['action']) && $_GET['action'] === 'ask') {
    require_once __DIR__ . '/../app/templates/ai-dashboard/page.php';
    exit;
}

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
 * =======================================================
 * 【重要・パス解決版】既存の処理を壊さず、未読チャット件数を安全にカウント
 * =======================================================
 */
$GLOBALS['unread_count'] = 0;
if (isset($_SESSION['user_id'])) {
    try {
        $chat_pdo = getDB();

        // 環境による階層の違いを吸収するため、2パターンのパスをチェックします
        $path_option1 = __DIR__ . '/../app/models/Message.php';
        $path_option2 = __DIR__ . '/../models/Message.php';
        $resolved_path = null;

        if (file_exists($path_option1)) {
            $resolved_path = $path_option1;
        } elseif (file_exists($path_option2)) {
            $resolved_path = $path_option2;
        }

        // ファイルが正しく見つかった場合のみMessageモデルを生成
        if ($chat_pdo instanceof PDO && $resolved_path !== null) {
            require_once $resolved_path;
            $messageModel = new Message($chat_pdo);
            $GLOBALS['unread_count'] = $messageModel->getUnreadCount($_SESSION['user_id']);
        } else {
            error_log("Message.php class file could not be found in expected paths.");
        }
    } catch (Exception $e) {
        error_log("Chat unread count failed: " . $e->getMessage());
    }
}

/**
 * 4. チャット画面専用のルーティング
 */
if ($page === 'chat') {
    // チャット画面の表示・データ処理
    include __DIR__ . '/../app/templates/chat_view/chat.php';
    exit;
}

/**
 * 5. Google認証・連携専用ルーティング
 * 連携ボタンから page=google_auth でアクセスされた場合
 */
if ($page === 'google_auth') {
    // auth.php へリダイレクト
    header("Location: auth.php");
    exit;
}

/**
 * 6. 共有メモ閲覧（ログイン不要）
 */
if ($page === 'view_share') {
    require_once __DIR__ . '/../app/controllers/MemoController.php';
    $controller = new MemoController();
    $controller->view_share();
    exit;
}

/**
 * 7. 共有URL生成処理
 */
if ($page === 'generate_share_url') {
    require_once __DIR__ . '/../app/controllers/MemoController.php';
    $controller = new MemoController();
    $controller->generate_share_url();
    exit;
}

/**
 * 8. 合言葉（guest_name）のセッション保存処理
 */
if ($page === 'memo' && $action === 'set_guest_name') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $_SESSION['guest_name'] = $_POST['guest_name'] ?? '';
    }
    header("Location: /index.php?page=memo&action=list");
    exit;
}

/**
 * 9. 特定のページ（memo）に対するカスタムルーティング
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
 * 10. マイメモ一覧（memo_list）に対するカスタムルーティング
 */
if ($page === 'memo_list') {
    require_once __DIR__ . '/../app/controllers/PageController.php';

    $controller = new PageController();
    $pageData = $controller->showMemoList();

    //$pageId = 'memo_list';
    // index.php 117行目付近
    $templatePath = "../app/templates/{$pageId}/page.php";

    // もし 404（memo_listなど）の場合は、共通の page.php を使うように強制する
    if ($pageId === 'memo_list' || !file_exists($templatePath)) {
        $templatePath = "../app/templates/home/page.php"; // あるいは共通の page.php
    }


    include __DIR__ . '/../app/templates/layout/header.php';
    include($templatePath);
    include __DIR__ . '/../app/templates/layout/footer.php';
    exit;
}

if ($page === 'admin') {
    $pageId = 'admin';
    require_once __DIR__ . '/../app/controllers/AdminController.php';

    $controller = new AdminController();
    $data = $controller->handleRequest();

    // 必要なら共通変数を渡す
    $data['isGoogleLinked'] = $isGoogleLinked ?? false;
    extract($data);

    include __DIR__ . '/../app/templates/admin/page.php';
    exit;

} elseif ($page === 'api') {
    require_once __DIR__ . '/../app/controllers/ApiController.php';

    $apiPath = $_GET['api'] ?? '';
    $controller = new ApiController();
    $controller->handleRequest($apiPath); // APIはJSON返すだけなのでexitは中でやる
    exit;
} elseif ($page === 'sampleTest-1') {
    // 1. ページIDを設定
    $pageId = 'sampleTest-1';

    // 2. レイアウトとテンプレートの読み込み（パスの結合を確実に！）
    include __DIR__ . '/../app/templates/layout/header.php';
    include __DIR__ . "/../app/templates/{$pageId}/page.php";
    include __DIR__ . '/../app/templates/layout/footer.php';
    exit;
}

/**
 * 11. 既存のルーティングの実行（HOMEなどのカレンダー表示はすべてここで安全に実行されます）
 */
route($page);
?>
<script>
    // 1. 画面スリープを防止する機能 (Wake Lock)
    let wakeLock = null;
    async function requestWakeLock() {
        try {
            if ('wakeLock' in navigator) {
                wakeLock = await navigator.wakeLock.request('screen');
                console.log('スリープ防止機能：有効');
            }
        } catch (err) {
            console.log('スリープ防止エラー:', err);
        }
    }

    // 2. ページ表示時に実行
    document.addEventListener('visibilitychange', () => {
        if (wakeLock !== null && document.visibilityState === 'visible') {
            requestWakeLock();
        }
    });

    // 初回起動時に実行
    requestWakeLock();

    // 3. サービスワーカーの登録（PWA化に必須）
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js').then(() => {
            console.log('Service Worker Registered');
        });
    }
</script>