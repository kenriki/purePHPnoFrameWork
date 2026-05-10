<?php
/**
 * ======================================================================================
 * 共通ヘッダー (header.php) - レイアウト修正版
 * ======================================================================================
 */

// 1. セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. 認証チェック
$isLoggedIn = !empty($_SESSION['user_id']);
$isGoogleLinked = (isset($_SESSION['google_access_token']) && !empty($_SESSION['google_access_token']));

/**
 * 注意：カレンダー同期ロジックは本来 sync_calendar.php 側で完結させるべきですが、
 * ヘッダー読み込み時にサーバー側でも判定が必要な場合を考慮し、変数の整理のみ行います。
 */
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

    <style>
        /* --- 1. 基本レイアウトの修正 --- */
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

        /* フォーム入力時は操作可能にする */
        input,
        textarea,
        [contenteditable="true"] {
            user-select: text;
            -webkit-user-select: text;
        }

        /* --- 2. スケルトンスクリーン（オーバーレイ方式） --- */
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

        .sk-button {
            height: 45px;
            width: 100%;
            margin-bottom: 15px;
            border-radius: 8px;
        }

        .sk-line {
            height: 15px;
            width: 90%;
            margin-bottom: 10px;
        }

        .loading-status-container {
            text-align: center;
            margin-top: auto;
            margin-bottom: 40px;
            padding: 20px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        #loading-error-msg {
            display: none;
            color: #ef4444;
            margin-top: 15px;
            font-size: 13px;
        }

        /* --- 3. UIコンポーネント --- */
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

        /* コンテンツエリア */
        main {
            flex-grow: 1;
            position: relative;
            width: 100%;
        }

        #real-content {
            display: none;
            width: 100%;
        }

        /* カレンダー用微調整：コンテナを突き抜けないように */
        .fc {
            background: #fff;
            padding: 10px;
            border-radius: 8px;
        }
    </style>
</head>

<body>
    <script>
        // PHPからJSへ認証状態を安全に渡す
        const IS_GOOGLE_LINKED = <?= $isGoogleLinked ? 'true' : 'false' ?>;
    </script>

    <div id="skeleton-screen">
        <div class="sk-header">
            <div class="skeleton sk-icon"></div>
            <div class="skeleton" style="height:20px; width:120px;"></div>
        </div>
        <div class="skeleton sk-card"></div>
        <div class="skeleton sk-button"></div>
        <div class="skeleton sk-line"></div>
        <div class="skeleton sk-line" style="width: 70%;"></div>

        <div class="loading-status-container">
            <div class="loading-main-text" style="font-weight:bold; font-size:16px;">Please wait...</div>
            <div id="loading-step-text" style="color:#6b7280; font-size:13px;">準備しています...</div>
            <div id="loading-error-msg">
                処理に時間がかかっています。<br>
                <a href="index.php?page=home" style="text-decoration:underline; color:#4f46e5;">再試行する</a>
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
            <script>
                document.addEventListener("DOMContentLoaded", () => {
                    const skeleton = document.getElementById('skeleton-screen');
                    const content = document.getElementById('real-content');
                    const stepText = document.getElementById('loading-step-text');
                    const errorMsg = document.getElementById('loading-error-msg');

                    // --- 1. メッセージの動的アニメーション ---
                    const stepsLinked = [
                        "Googleカレンダーと同期しています...",
                        "最新の情報を取得中...",
                        "表示データを準備しています...",
                        "まもなく完了します..."
                    ];
                    const stepsNotLinked = [
                        "認証状態を確認しています...",
                        "システムデータを読み込み中...",
                        "表示データを準備しています...",
                        "まもなく完了します..."
                    ];

                    const activeSteps = IS_GOOGLE_LINKED ? stepsLinked : stepsNotLinked;
                    if (stepText) stepText.innerText = activeSteps[0];

                    let stepIdx = 0;
                    const stepInterval = setInterval(() => {
                        if (stepText && stepIdx < activeSteps.length - 1) {
                            stepIdx++;
                            stepText.innerText = activeSteps[stepIdx];
                        }
                    }, 2500);

                    // カレンダー同期リクエスト
                    if (IS_GOOGLE_LINKED && !sessionStorage.getItem('calendar_synced')) {
                        fetch('sync_calendar.php', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                            .then(r => r.json())
                            .then(data => {
                                if (data.status === 'success') {
                                    console.log('Sync Success:', data.count);
                                    sessionStorage.setItem('calendar_synced', 'true');
                                    if (typeof mainCalendar !== 'undefined' && mainCalendar) {
                                        mainCalendar.refetchEvents();
                                    }
                                }
                            })
                            .catch(error => console.error('Sync Error:', error));
                    }

                    // 10秒でエラー表示
                    const errorTimer = setTimeout(() => {
                        if (skeleton && skeleton.style.display !== 'none') {
                            if (errorMsg) errorMsg.style.display = 'block';
                        }
                    }, 10000);

                    // --- 4. 読み込み完了時の処理 ---
                    window.addEventListener('load', () => {
                        setTimeout(() => {
                            clearInterval(stepInterval);
                            clearTimeout(errorTimer);
                            skeleton.style.opacity = '0'; // フェードアウト
                            skeleton.style.transition = 'opacity 0.3s ease';
                            setTimeout(() => {
                                skeleton.style.display = 'none';
                                content.style.display = 'block';
                                // カレンダーのサイズ再計算（これ重要）
                                if (typeof mainCalendar !== 'undefined') mainCalendar.updateSize();
                            }, 300);
                        }, 500);
                    });

                    // --- 5. ページ遷移時に再表示 (UX向上) ---
                    window.addEventListener('beforeunload', () => {
                        if (skeleton) skeleton.style.display = 'block';
                        if (content) content.style.display = 'none';
                    });

                    // --- 6. ナビゲーションスクロール制御 ---
                    const nav = document.querySelector(".scroll-nav ul");
                    const leftBtn = document.querySelector(".nav-arrow.left");
                    const rightBtn = document.querySelector(".nav-arrow.right");
                    if (nav && leftBtn && rightBtn) {
                        const scrollAmount = 150;
                        leftBtn.addEventListener("click", () => { nav.scrollBy({ left: -scrollAmount, behavior: "smooth" }); });
                        rightBtn.addEventListener("click", () => { nav.scrollBy({ left: scrollAmount, behavior: "smooth" }); });
                    }
                });
            </script>