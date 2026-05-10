<?php
/**
 * ======================================================================================
 * 共通ヘッダー (header.php) - 完全版
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
        /* --- 1. アプリライクな操作制限 --- */
        html,
        body {
            /* 戻る・進むスワイプ、引っ張って更新を物理的に無効化 */
            overscroll-behavior: none;
            /* ピンチズーム、ダブルタップズームを禁止 */
            touch-action: pan-x pan-y;
            /* iOS特有の長押しメニュー等を禁止 */
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            user-select: none;
        }

        body {
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            margin: 0;
            height: 100%;
            background: #f9fafb;
            font-family: "Helvetica Neue", Arial, "Hiragino Kaku Gothic ProN", "Hiragino Sans", Meiryo, sans-serif;
        }

        /* フォーム要素は入力を許可 */
        input,
        textarea,
        [contenteditable="true"] {
            -webkit-user-select: text;
            user-select: text;
        }

        /* --- 2. スケルトンスクリーン スタイル --- */
        #skeleton-screen {
            padding: 20px;
            background: #f9fafb;
            min-height: 100vh;
        }

        .skeleton {
            background-color: #e5e7eb;
            background-image: linear-gradient(90deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.6), rgba(255, 255, 255, 0));
            background-size: 200px 100%;
            background-repeat: no-repeat;
            border-radius: 8px;
            display: block;
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

        /* 進捗メッセージ */
        .loading-status-container {
            text-align: center;
            margin-top: 20px;
            padding: 15px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .loading-main-text {
            font-weight: bold;
            color: #111827;
            margin-bottom: 5px;
            font-size: 16px;
        }

        .loading-sub-text {
            color: #6b7280;
            font-size: 13px;
            min-height: 1.2em;
        }

        #loading-error-msg {
            display: none;
            color: #ef4444;
            margin-top: 15px;
            font-size: 13px;
        }

        /* コンテンツの表示制御 */
        #real-content {
            display: none;
        }

        /* --- 3. UIパーツ基本スタイル --- */
        header {
            padding: 20px;
            background: #fff;
            border-bottom: 1px solid #ddd;
        }

        .greeting-title {
            margin: 10px 0 0 0;
            font-size: 1.25rem;
            color: #4f46e5;
            font-weight: bold;
        }

        /* スクロールナビ */
        .scroll-nav {
            position: relative;
            display: flex;
            align-items: center;
            background: #fff;
            border-bottom: 1px solid #eee;
        }

        .scroll-nav ul {
            display: flex;
            overflow-x: auto;
            white-space: nowrap;
            list-style: none;
            margin: 0;
            padding: 10px;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .scroll-nav ul::-webkit-scrollbar {
            display: none;
        }

        .scroll-nav li {
            margin-right: 15px;
        }

        .scroll-nav a {
            text-decoration: none;
            color: #666;
            font-size: 14px;
            padding: 8px 12px;
            border-radius: 20px;
        }

        .scroll-nav a.active {
            background: #4f46e5;
            color: #fff;
        }

        .nav-arrow {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            padding: 0 10px;
            color: #ccc;
        }
    </style>
</head>

<body>
    <script>
        // PHPからJSへ認証状態を安全に渡す
        const IS_GOOGLE_LINKED = <?= $isGoogleLinked ? 'true' : 'false' ?>;
    </script>

    <header>
        <h1 style="font-size: 0.85rem; color: #888; margin: 0; font-weight: normal;">My System</h1>
        <h2 class="greeting-title"><?= htmlspecialchars($page['title'] ?? 'Dashboard') ?></h2>
    </header>

    <main>
        <?php if ($isLoggedIn): ?>
            <nav class="scroll-nav">
                <button class="nav-arrow left">‹</button>
                <ul>
                    <?php
                    // メニューデータの読み込み
                    if (defined('DATA_PATH') && file_exists(DATA_PATH)) {
                        $menuData = json_decode(file_get_contents(DATA_PATH), true);
                    } else {
                        $menuData = [];
                    }
                    $userRole = $_SESSION['role'] ?? 'user';

                    foreach ($menuData as $id => $content):
                        $origTitle = $content['title'] ?? '';
                        $shouldShow = false;

                        // 表示権限の判定ロジック
                        if ($id === 'home' || $origTitle === 'メモ' || $origTitle === 'メモ(Excelダウンロード)') {
                            $shouldShow = true;
                        } elseif (strpos($origTitle, 'サンプル') !== false) {
                            if ($userRole === 'admin')
                                $shouldShow = true;
                        }

                        if ($shouldShow): ?>
                            <li>
                                <a href="/index.php?page=<?= htmlspecialchars($id) ?>"
                                    class="<?= (($pageId ?? '') === $id) ? 'active' : '' ?>">
                                    <?= htmlspecialchars($origTitle) ?>
                                </a>
                            </li>
                        <?php endif;
                    endforeach; ?>

                    <li class="logout-item">
                        <a href="/index.php?page=logout" style="color: #ff4d4d; font-weight: bold;">
                            <i class="fa-solid fa-right-from-bracket"></i>
                            ログアウト (<?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>)
                        </a>
                    </li>
                </ul>
                <button class="nav-arrow right">›</button>
            </nav>
        <?php endif; ?>

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
                <div class="loading-main-text">Please wait loading...</div>
                <div id="loading-step-text" class="loading-sub-text">準備しています...</div>
                <div id="loading-error-msg">
                    処理に時間がかかっています。<br>
                    <a href="index.php?page=home" style="text-decoration:underline;">こちらをクリックして再試行</a>
                </div>
            </div>
        </div>

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

                    // --- 2. Googleカレンダー自動同期 (1セッション1回) ---
                    if (IS_GOOGLE_LINKED && !sessionStorage.getItem('calendar_synced')) {
                        fetch('sync_calendar.php', {
                            method: 'GET',
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        })
                            .then(response => response.json())
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

                    // --- 3. タイムアウト監視 (10秒) ---
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
                            if (skeleton) skeleton.style.display = 'none';
                            if (content) content.style.display = 'block';
                        }, 600);
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