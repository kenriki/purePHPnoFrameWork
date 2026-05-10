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

    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <?php
    global $isTest;
    if (isset($isTest) && $isTest === true):
        ?>
        <style>
            html,
            body {
                touch-action: pan-x pan-y;
            }

            body {
                border-top: 8px solid #ff4500 !important;
                background-color: #fdf5e6 !important;
            }

            .test-env-banner {
                background-color: #ff4500;
                color: white;
                text-align: center;
                font-weight: bold;
                font-size: 12px;
                padding: 3px 0;
                position: fixed;
                top: 0;
                width: 100%;
                z-index: 10000;
            }
        </style>
        <div class="test-env-banner">TEST ENVIRONMENT - テスト検証用</div>
    <?php endif; ?>

    <style>
        /* --- 基本レイアウト --- */
        html,
        body {
            margin: 0;
            padding: 0;
            /* カレンダーの高さ計算を邪魔しないよう min-height に変更 */
            min-height: 100vh;
            background: #f9fafb;
            font-family: "Helvetica Neue", Arial, "Hiragino Kaku Gothic ProN", "Hiragino Sans", Meiryo, sans-serif;
            /* iOSのスワイプ戻り防止 */
            overscroll-behavior-y: none;
            touch-action: manipulation;
        }

        body {
            display: flex;
            flex-direction: column;
        }

        /* --- スケルトン＆くるくるローディング --- */
        #skeleton-screen {
            position: fixed;
            /* 画面全体を覆う */
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #f9fafb;
            z-index: 9999;
            /* 最前面 */
            padding: 20px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            transition: opacity 0.4s ease;
        }

        .skeleton {
            background-color: #e5e7eb;
            background-image: linear-gradient(90deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.6), rgba(255, 255, 255, 0));
            background-size: 200px 100%;
            background-repeat: no-repeat;
            border-radius: 8px;
            animation: skeleton-shimmer 1.5s infinite;
        }

        @keyframes skeleton-shimmer {
            0% {
                background-position: -200px 0;
            }

            100% {
                background-position: calc(200px + 100%) 0;
            }
        }

        .sk-header {
            height: 30px;
            width: 180px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sk-icon {
            height: 24px;
            width: 24px;
            border-radius: 4px;
        }

        .sk-card {
            height: 250px;
            width: 100%;
            margin-bottom: 20px;
            border: 1px solid #e5e7eb;
            background: #fff;
        }

        .loading-center-area {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 20px;
        }

        .spinner-icon {
            font-size: 3.5rem;
            color: #4f46e5;
            animation: fa-spin 1.5s infinite linear;
        }

        .loading-status-container {
            text-align: center;
            margin-bottom: 40px;
            padding: 20px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        #loading-error-msg {
            display: none;
            color: #ef4444;
            margin-top: 15px;
            font-size: 13px;
        }

        /* --- UIパーツ --- */
        header {
            padding: 15px 20px;
            background: #fff;
            border-bottom: 1px solid #ddd;
            flex-shrink: 0;
        }

        .greeting-title {
            margin: 5px 0 0 0;
            font-size: 1.2rem;
            color: #4f46e5;
            font-weight: bold;
        }

        .scroll-nav {
            background: #fff;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            flex-shrink: 0;
        }

        .scroll-nav ul {
            display: flex;
            overflow-x: auto;
            white-space: nowrap;
            list-style: none;
            margin: 0;
            padding: 10px;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }

        .scroll-nav ul::-webkit-scrollbar {
            display: none;
        }

        .scroll-nav li {
            margin-right: 10px;
        }

        .scroll-nav a {
            text-decoration: none;
            color: #666;
            font-size: 13px;
            padding: 6px 12px;
            border-radius: 20px;
            display: inline-block;
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
            padding: 0 5px;
        }

        main {
            flex-grow: 1;
            position: relative;
            width: 100%;
        }

        #real-content {
            visibility: hidden;
            display: none;
            width: 100%;
        }
    </style>
</head>

<body>
    <script>
        // PHPからJSへ認証状態を安全に渡す
        const IS_GOOGLE_LINKED = <?= $isGoogleLinked ? 'true' : 'false' ?>;
        const IS_LOGGED_IN = <?= $isLoggedIn ? 'true' : 'false' ?>;
    </script>

    <div id="skeleton-screen">
        <div class="sk-header">
            <div class="skeleton sk-icon"></div>
            <div class="skeleton" style="height:20px; width:120px;"></div>
        </div>

        <div class="loading-center-area">
            <i class="fa-solid fa-circle-notch spinner-icon"></i>
            <div class="skeleton sk-card"></div>
        </div>

        <div class="loading-status-container">
            <div class="loading-main-text" style="font-weight:bold; font-size:16px;">System Loading</div>
            <div id="loading-step-text" style="color:#6b7280; font-size:13px;">データ準備しています...</div>
            <div id="loading-error-msg">
                読み込みに時間がかかっています。<br>
                <a href="/index.php" style="text-decoration:underline; color:#4f46e5;">トップへ戻る</a>
            </div>
        </div>
    </div>

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
                $userRole = $_SESSION['role'] ?? 'user';
                foreach ($menuData as $id => $content):
                    $origTitle = $content['title'] ?? '';
                    $shouldShow = ($id === 'home' || $origTitle === 'メモ' || $origTitle === 'メモ(Excelダウンロード)') || (strpos($origTitle, 'サンプル') !== false && $userRole === 'admin');
                    if ($shouldShow): ?>
                        <li><a href="/index.php?page=<?= htmlspecialchars($id) ?>"
                                class="<?= (($pageId ?? '') === $id) ? 'active' : '' ?>"><?= htmlspecialchars($origTitle) ?></a>
                        </li>
                    <?php endif;
                endforeach; ?>
                <li><a href="/index.php?page=logout" style="color:#ff4d4d;"><i
                            class="fa-solid fa-right-from-bracket"></i></a></li>
            </ul>
            <button class="nav-arrow right">›</button>
        </nav>
    <?php endif; ?>

    <main>
        <div id="real-content">
        </div>

        <script>
            (function () {
                const initLoading = () => {
                    const skeleton = document.getElementById('skeleton-screen');
                    const content = document.getElementById('real-content');
                    // ID名が 'loading-msg' であることを確認
                    const msg = document.getElementById('loading-msg');

                    // --- 1. 同期処理 ---
                    if (IS_GOOGLE_LINKED) {
                        // msg が存在する場合のみ text を書き換える (TypeError 対策)
                        if (msg) {
                            msg.innerText = "Googleカレンダー同期中...";
                        }

                        fetch('sync_calendar.php', {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        })
                            .then(r => r.json())
                            .then(data => {
                                console.log("Sync success:", data);
                            })
                            .catch(e => console.error("Sync failed:", e))
                            .finally(() => {
                                showMainContent();
                            });
                    } else {
                        showMainContent();
                    }

                    // --- 2. 表示切り替え ---
                    function showMainContent() {
                        const proceed = () => {
                            setTimeout(() => {
                                if (skeleton) {
                                    skeleton.style.opacity = '0';
                                    setTimeout(() => {
                                        skeleton.style.display = 'none';
                                        if (content) {
                                            content.style.visibility = 'visible';
                                            content.style.display = 'block';
                                        }
                                        // カレンダー再描画
                                        if (typeof mainCalendar !== 'undefined' && mainCalendar !== null) {
                                            mainCalendar.refetchEvents();
                                            mainCalendar.updateSize();
                                        }
                                    }, 400);
                                }
                            }, 500);
                        };

                        if (document.readyState === 'complete') {
                            proceed();
                        } else {
                            window.addEventListener('load', proceed);
                        }
                    }

                    // --- 3. ナビスクロール ---
                    const navUl = document.querySelector(".scroll-nav ul");
                    const leftBtn = document.querySelector(".nav-arrow.left");
                    const rightBtn = document.querySelector(".nav-arrow.right");
                    if (navUl && leftBtn && rightBtn) {
                        leftBtn.onclick = () => navUl.scrollBy({ left: -150, behavior: "smooth" });
                        rightBtn.onclick = () => navUl.scrollBy({ left: 150, behavior: "smooth" });
                    }
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initLoading);
                } else {
                    initLoading();
                }
            })();
        </script>