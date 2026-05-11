<?php
/**
 * ======================================================================================
 * 共通ヘッダー (header.php) - シンプル最速版
 * ======================================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = !empty($_SESSION['user_id']);
$isGoogleLinked = false;

if ($isLoggedIn) {
    try {
        $pdo = getDB();
        $syncClassPath = dirname(__DIR__, 2) . '/utils/GoogleCalendarSync.php';
        if (file_exists($syncClassPath)) {
            require_once $syncClassPath;
            if (class_exists('\app\utils\GoogleCalendarSync')) {
                $checkSync = new \app\utils\GoogleCalendarSync($pdo);
                $targetUser = $_SESSION['login_id'] ?? ($_SESSION['username'] ?? '');
                $token = $checkSync->getAccessToken($targetUser);
                $isGoogleLinked = ($token !== false);
            }
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
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* --- 基本設定 --- */
        html,
        body {
            margin: 0;
            padding: 0;
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

        /* ナビゲーション */
        header {
            padding: 12px 20px;
            background: #fff;
            border-bottom: 1px solid #ddd;
        }

        .scroll-nav {
            background: #fff;
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
        <h2 style="margin:0; font-size:1.1rem; color:#4f46e5;"><?= htmlspecialchars($page['title'] ?? 'Dashboard') ?>
        </h2>
    </header>

    <?php if ($isLoggedIn): ?>
        <nav class="scroll-nav">
            <button class="nav-arrow left">‹</button>
            <ul>
                <?php
                $menuData = (defined('DATA_PATH') && file_exists(DATA_PATH)) ? json_decode(file_get_contents(DATA_PATH), true) : [];
                foreach ($menuData as $id => $content):
                    $title = $content['title'] ?? '';
                    if ($id === 'home' || $title === 'メモ' || $title === 'メモ(Excelダウンロード)'): ?>
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
        // 余計な処理はせず、ページが読み込まれたらカレンダーのサイズを1回だけ整える
        window.addEventListener('load', function () {
            if (window.mainCalendar) {
                window.mainCalendar.updateSize();
            }

            // ナビスクロール
            const navUl = document.querySelector(".scroll-nav ul");
            const leftBtn = document.querySelector(".nav-arrow.left");
            const rightBtn = document.querySelector(".nav-arrow.right");
            if (navUl && leftBtn && rightBtn) {
                leftBtn.onclick = () => navUl.scrollBy({ left: -150, behavior: "smooth" });
                rightBtn.onclick = () => navUl.scrollBy({ left: 150, behavior: "smooth" });
            }
        });

        // 1. エラー防止用：古いローディングスクリプトが参照する変数を空で定義しておく
        var skeleton = { style: {} };
        var content = { style: {} };
        var stepText = { innerText: "" };
        var bar = { style: {} };

        // 2. 引っ張りリロードをJSレベルで最終防御
        document.addEventListener('touchmove', function (e) {
            if (e.target.closest('main')) return; // メインエリアのスクロールは許可
            e.preventDefault();
        }, { passive: false });

        // 3. 読み込み時の処理
        window.addEventListener('load', function () {
            if (window.mainCalendar) {
                window.mainCalendar.updateSize();
            }

            // ナビスクロール
            const navUl = document.querySelector(".scroll-nav ul");
            const leftBtn = document.querySelector(".nav-arrow.left");
            const rightBtn = document.querySelector(".nav-arrow.right");
            if (navUl && leftBtn && rightBtn) {
                leftBtn.onclick = () => navUl.scrollBy({ left: -150, behavior: "smooth" });
                rightBtn.onclick = () => navUl.scrollBy({ left: 150, behavior: "smooth" });
            }
        });
    </script>

    <main>
        <div id="real-content">