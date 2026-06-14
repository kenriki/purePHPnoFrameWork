<?php
session_start();
$appEnv = $_ENV['APP_ENV'] ?? 'production';
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

// APIリクエスト処理
if ($api_request === 'equipment') {
    require_once __DIR__ . '/../app/api/api_equipment.php';
    exit;
}

// AIダッシュボード処理
if ($action === 'ask') {
    require_once __DIR__ . '/../app/templates/ai-dashboard/page.php';
    exit;
}

/**
 * 3. ログイン・アクティブ状態の管理とGoogle連携チェック
 */
$isGoogleLinked = false;
$pdo = null; // 共通PDO変数

if (isset($_SESSION['user_id'])) {
    try {
        $pdo = getDB();
        // アクティブ時間の更新
        $stmtActive = $pdo->prepare("UPDATE users SET last_active_at = NOW() WHERE id = ?");
        $stmtActive->execute([$_SESSION['user_id']]);

        // Google連携状態の確認
        $stmtToken = $pdo->prepare("SELECT user_name FROM google_tokens WHERE user_name = ?");
        $stmtToken->execute([$_SESSION['user_id']]);
        if ($stmtToken->fetch()) {
            $isGoogleLinked = true;
        }
    } catch (Exception $e) {
        error_log("Session/Token check failed: " . $e->getMessage());
    }
}

/**
 * 未読チャット件数の取得
 */
$GLOBALS['unread_count'] = 0;
if (isset($_SESSION['user_id'])) {
    $path = file_exists(__DIR__ . '/../app/models/Message.php') ? __DIR__ . '/../app/models/Message.php' : __DIR__ . '/../models/Message.php';
    if (file_exists($path)) {
        require_once $path;
        $messageModel = new Message($pdo ?? getDB());
        $GLOBALS['unread_count'] = $messageModel->getUnreadCount($_SESSION['user_id']);
    }
}

/**
 * ルーティング処理
 */
switch ($page) {
    case 'chat':
        include __DIR__ . '/../app/templates/chat_view/chat.php';
        exit;
        
    case 'like_message':
        // これより上部で出力されてしまったHTML（ヘッダー等）をすべてバッファから抹消
        if (ob_get_length()) ob_clean();
        
        // レスポンスを完全にJSONとして定義
        header('Content-Type: application/json; charset=utf-8');
        
        $like_script = __DIR__ . '/../app/templates/chat_view/like_message.php';
        if (file_exists($like_script)) {
            include $like_script;
        } else {
            header("HTTP/1.1 500 Internal Server Error");
            echo json_encode(['status' => 'error', 'message' => 'ファイルが見つかりません。']);
        }
        // index.phpの下部にあるフッターなどのHTMLに絶対に合流させないために即終了
        exit;

    case 'add_member':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $room_id = $_POST['room_id'] ?? 0;
            $name = $_POST['new_member_name'] ?? '';
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$name]);
            if ($user = $stmt->fetch()) {
                $check = $pdo->prepare("SELECT * FROM room_members WHERE room_id = ? AND user_id = ?");
                $check->execute([$room_id, $user['id']]);
                if (!$check->fetch()) {
                    $stmt = $pdo->prepare("INSERT INTO room_members (room_id, user_id) VALUES (?, ?)");
                    $stmt->execute([$room_id, $user['id']]);
                }
            }
            header("Location: index.php?page=chat&room_id=" . $room_id);
            exit;
        }
        break;

    case 'google_auth':
        header("Location: auth.php");
        exit;

    case 'view_share':
        require_once __DIR__ . '/../app/controllers/MemoController.php';
        (new MemoController())->view_share();
        exit;

    case 'generate_share_url':
        require_once __DIR__ . '/../app/controllers/MemoController.php';
        (new MemoController())->generate_share_url();
        exit;

    case 'memo':
        if ($action === 'set_guest_name' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $_SESSION['guest_name'] = $_POST['guest_name'] ?? '';
            header("Location: /index.php?page=memo&action=list");
            exit;
        }
        require_once __DIR__ . '/../app/controllers/MemoController.php';
        $controller = new MemoController();
        $data = $controller->handleRequest();
        $data['isGoogleLinked'] = $isGoogleLinked;
        extract($data);
        include __DIR__ . '/../app/templates/memo/page.php';
        exit;

    case 'memo_list':
        require_once __DIR__ . '/../app/controllers/PageController.php';
        $controller = new PageController();
        $pageData = $controller->showMemoList();
        $templatePath = "../app/templates/memo_list/page.php";
        if (!file_exists(__DIR__ . '/' . $templatePath))
            $templatePath = "../app/templates/home/page.php";
        include __DIR__ . '/../app/templates/layout/header.php';
        include __DIR__ . '/' . $templatePath;
        include __DIR__ . '/../app/templates/layout/footer.php';
        exit;

    case 'group_create':
        include __DIR__ . '/../app/templates/chat_view/create_group_form.php';
        exit;

    case 'do_create_group':
        require_once __DIR__ . '/../app/templates/chat_view/create_group.php';
        exit;

    case 'admin':
        require_once __DIR__ . '/../app/controllers/AdminController.php';
        $controller = new AdminController();
        $data = $controller->handleRequest();
        $data['isGoogleLinked'] = $isGoogleLinked;
        extract($data);
        include __DIR__ . '/../app/templates/admin/page.php';
        exit;

    case 'api':
        require_once __DIR__ . '/../app/controllers/ApiController.php';
        (new ApiController())->handleRequest($_GET['api'] ?? '');
        exit;

    case 'sampleTest-1':
        include __DIR__ . '/../app/templates/layout/header.php';
        include __DIR__ . "/../app/templates/sampleTest-1/page.php";
        include __DIR__ . '/../app/templates/layout/footer.php';
        exit;

}

// 既存ルーティングの実行
route($page);
?>

<script>
    // Wake Lock
    let wakeLock = null;
    async function requestWakeLock() {
        try {
            if ('wakeLock' in navigator) {
                wakeLock = await navigator.wakeLock.request('screen');
                console.log('スリープ防止：有効');
            }
        } catch (err) { console.log('スリープ防止エラー:', err); }
    }
    document.addEventListener('visibilitychange', () => {
        if (wakeLock !== null && document.visibilityState === 'visible') requestWakeLock();
    });
    requestWakeLock();

    // Service Worker
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').then(() => console.log('SW Registered'));
    }
</script>
<script src="main.js"></script>