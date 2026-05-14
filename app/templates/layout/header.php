<?php
/**
 * ======================================================================================
 * 共通ヘッダー (header.php) - クラスエラー対策＆ローディング同期修正版
 * ======================================================================================
 */

// 1. セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. 認証・同期チェック
$isLoggedIn = !empty($_SESSION['user_id']);
$isGoogleLinked = false;
$userRole = $_SESSION['role'] ?? '';

if ($isLoggedIn) {
    try {
        $pdo = getDB();

        // 【解決】現在の app/templates/layout から 2つ上がって app/utils を指定
        $syncClassPath = dirname(__DIR__, 2) . '/utils/GoogleCalendarSync.php';

        if (file_exists($syncClassPath)) {
            require_once $syncClassPath;

            // 完全修飾名でクラスの存在を確認
            if (class_exists('\app\utils\GoogleCalendarSync')) {
                $checkSync = new \app\utils\GoogleCalendarSync($pdo);

                // 【解決】login_idがNULLの場合に備え、usernameも参照するフォールバック
                $targetUser = $_SESSION['login_id'] ?? ($_SESSION['username'] ?? '');

                $token = $checkSync->getAccessToken($targetUser);
                $isGoogleLinked = ($token !== false);
            } else {
                error_log("Namespace Error: Class \app\utils\GoogleCalendarSync not found.");
            }
        } else {
            // パスが合っているかログで確認可能にする
            error_log("File Not Found: " . $syncClassPath);
        }
    } catch (\Exception $e) {
        error_log("Header Sync Error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page['title'] ?? 'システム') ?></title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex, nofollow">
    <meta name="mobile-web-app-capable" content="yes">
    <!-- PWA設定の読み込み -->
    <link rel="manifest" href="manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">

    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* --- 基本レイアウト --- */
        html,
        body {
            margin: 0;
            padding: 0;
            /* カレンダーの高さ計算を邪魔しないよう min-height に変更 */
            min-height: 100vh;
            background: #f9fafb;
            font-family: sans-serif;
            width: 100%;
            overflow-x: hidden;
        }

        /* --- 引っ張り更新を完全に禁止 --- */
        html,
        body {
            height: 100%;
            overflow: hidden;
            /* 全体の揺れを防止 */
            overscroll-behavior-y: none;
            /* Chrome/Safariの引張リロードを禁止 */
        }

        body {
            display: flex;
            flex-direction: column;
            position: fixed;
            width: 100%;
        }

        /* --- コンテンツエリアのみスクロールを許可 --- */
        main {
            flex: 1;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* --- 390行(real-content)を最初から全開にする --- */
        #real-content {
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
            width: 100%;
        }

        /* カレンダーの横幅修正（必須） */
        main,
        #real-content {
            width: 100% !important;
            max-width: 100vw !important;
            box-sizing: border-box !important;
        }

        .fc {
            width: 100% !important;
            max-width: 100% !important;
        }

        @media (max-width: 768px) {
            .fc .fc-toolbar {
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
                gap: 5px !important;
                padding: 5px !important;
            }

            .fc-toolbar-chunk {
                display: flex !important;
                justify-content: center !important;
                gap: 4px !important;
            }

            .fc-toolbar-title {
                font-size: 1rem !important;
            }

            .fc .fc-button {
                padding: 4px 8px !important;
                font-size: 0.75rem !important;
            }
        }

        /* --- UIパーツ --- */
        header {
            padding: 2px 3px;
            background: #fff;
            border-bottom: 1px solid #ddd;
        }

        .scroll-nav {
            background: #24bd4a;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
        }

        .scroll-nav ul {
            display: flex;
            overflow-x: auto;
            list-style: none;
            margin: 0;
            padding: 10px;
            scrollbar-width: none;
        }

        .scroll-nav a {
            text-decoration: none;
            color: #666;
            font-size: 13px;
            padding: 6px 12px;
            border-radius: 20px;
            white-space: nowrap;
        }

        .scroll-nav a.active {
            background: #4f46e5;
            color: #fff;
        }

        .nav-arrow {
            background: none;
            border: none;
            font-size: 18px;
            color: #ccc;
            cursor: pointer;
            padding: 0 10px;
        }
    </style>
</head>

<body>

    <header>
        <h1 style="font-size: 0.8rem; color: #888; margin: 0;">My System</h1>
        <h2 class="greeting-title"><?= htmlspecialchars($page['title'] ?? 'Dashboard') ?></h2>
    </header>

    <?php if ($isLoggedIn): ?>
        <nav class="scroll-nav">
            <button class="nav-arrow left">‹</button>
            <ul>
                <?php
                $menuData = (defined('DATA_PATH') && file_exists(DATA_PATH)) ? json_decode(file_get_contents(DATA_PATH), true) : [];
                foreach ($menuData as $id => $content):
                    $title = $content['title'] ?? '';
                    if (
                        $id === 'home' || $title === 'メモ' || $title === '地図アプリ'
                        || $title === 'メモ(Excelダウンロード)'
                        || (strpos($title, 'サンプル') !== false && ($userRole ?? '') === 'admin')
                    ): ?>
                        <li><a href="/index.php?page=<?= htmlspecialchars($id) ?>"
                                class="<?= (($pageId ?? '') === $id) ? 'active' : '' ?>"><?= htmlspecialchars($title) ?></a></li>
                    <?php endif;
                endforeach; ?>
                <li><a href="/index.php?page=logout" style="color:red;">ログアウト</a></li>
            </ul>
            <button class="nav-arrow right">›</button>
        </nav>
    <?php endif; ?>

    <script>
        // 1. グローバル変数の初期化（最優先）
        window.myId = '<?php echo $_SESSION['user_id'] ?? ""; ?>';
        window.myName = '<?php echo $_SESSION['user_name'] ?? "ゲスト"; ?>';
        console.log("Global Initialize - myId:", window.myId);
        let friendTrackingInterval = null;

        // 2. 関数定義（すべて function 宣言に統一し、依存関係を解消）

        function startFriendTracking(targetId) {
            if (friendTrackingInterval) clearInterval(friendTrackingInterval);

            const updateTask = async () => {
                try {
                    const response = await fetch(`index.php?page=get_friend_location&uid=${targetId}`);
                    const data = await response.json();
                    if (data && data.lat && data.lng) {
                        if (typeof addOrUpdateMarker === 'function') {
                            addOrUpdateMarker(targetId, data.lat, data.lng, data.u_name, data.time);
                        }
                    }
                } catch (e) {
                    console.warn("Tracking update skipped.");
                }
            };

            updateTask();
            friendTrackingInterval = setInterval(updateTask, 30000);
        }

        // 検索ボタンから呼ばれる関数
        function searchUser() {
            const tid = document.getElementById('search_tel')?.value.trim();
            if (tid) {
                console.log("Searching for:", tid);
                startFriendTracking(tid);
                // ServiceWorker連携
                if ('serviceWorker' in navigator) {
                    registerLocationSync(tid);
                }
            }
        }

        // 権限バッジ更新
        async function checkGeoPermission() {
            const badge = document.getElementById('geo_badge');
            if (!badge || !navigator.permissions) return;
            try {
                const result = await navigator.permissions.query({ name: 'geolocation' });
                const updateBadge = (status) => {
                    const styles = {
                        granted: { text: "位置情報：ON", bg: "#28a745" },
                        prompt: { text: "許可待ち", bg: "#ffc107" },
                        denied: { text: "位置情報：OFF", bg: "#dc3545" }
                    };
                    const s = styles[status] || { text: "不明", bg: "#6c757d" };
                    badge.innerText = s.text;
                    badge.style.background = s.bg;
                };
                updateBadge(result.state);
                result.onchange = () => updateBadge(result.state);
            } catch (e) { console.error(e); }
        }

        // バックグラウンド同期登録
        async function registerLocationSync(tid) {
            try {
                const reg = await navigator.serviceWorker.ready;
                if ('periodicSync' in reg) {
                    await reg.periodicSync.register('update-location-bg', {
                        minInterval: 60 * 1000
                    });
                    console.log("Background Sync registered.");
                }
            } catch (e) { console.warn("Periodic Sync not available."); }
        }

        // 3. 実行（DOMContentLoadedで一括管理）
        document.addEventListener('DOMContentLoaded', () => {
            // 1. まず ID が取れているか再確認（ログに出ているのでここはクリア）
            const currentMyId = window.myId || null;
            console.log("DOM Ready - myId:", currentMyId);

            // 2. バッジを更新する（要素が見つかるまで少し待つか、即実行）
            const updateBadgeStatus = () => {
                const badge = document.getElementById('geo_badge');
                if (badge) {
                    checkGeoPermission(); // ここで権限チェックを実行
                } else {
                    // もし要素がまだなければ、0.1秒後に再試行（念のため）
                    setTimeout(updateBadgeStatus, 100);
                }
            };

            updateBadgeStatus();
        });
    </script>

    <main>
        <div id="real-content">