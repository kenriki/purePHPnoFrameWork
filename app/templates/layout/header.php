<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page['title'] ?? 'システム') ?></title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* 既存の基本スタイル */
        table {
            border-collapse: collapse;
            width: 100%;
            font-size: 12px;
        }

        th,
        td {
            border: 1px solid #aaa;
            padding: 4px;
            text-align: center;
        }

        th {
            background: #eee;
        }

        header {
            padding: 20px;
            background: #fff;
            border-bottom: 1px solid #ddd;
        }

        /* 挨拶用のh2スタイル */
        .greeting-title {
            margin: 10px 0 0 0;
            font-size: 1.25rem;
            color: #4f46e5;
            font-weight: bold;
        }

        /* --- スケルトンスクリーンの高度なスタイル --- */
        #skeleton-screen {
            padding: 20px;
            background: #f9fafb;
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

        /* ダッシュボードを模したスケルトン配置 */
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
        }

        /* エラー発生時用メッセージ */
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
    </style>
</head>

<body>
    <header>
        <h1 style="font-size: 1rem; color: #888; margin: 0;">My System</h1>
        <h2 class="greeting-title"><?= htmlspecialchars($page['title'] ?? '') ?></h2>
    </header>

    <main>
        <?php if (isset($_SESSION['user_id'])): ?>
            <nav class="scroll-nav">
                <button class="nav-arrow left">‹</button>
                <ul>
                    <?php
                    $menuData = json_decode(file_get_contents(DATA_PATH), true);
                    $userRole = $_SESSION['role'] ?? 'user';

                    if ($menuData):
                        foreach ($menuData as $id => $content):
                            $origTitle = $content['title'] ?? '';
                            $shouldShow = false;

                            if ($id === 'home' || $origTitle === 'メモ' || $origTitle === 'メモ(Excelダウンロード)') {
                                $shouldShow = true;
                            } elseif (strpos($origTitle, 'サンプル') !== false && $userRole === 'admin') {
                                $shouldShow = true;
                            }
                            // B. タイトルに「サンプル」が含まれるページは admin のみ表示
                            elseif (strpos($origTitle, 'サンプル') !== false) {
                                if ($userRole === 'admin') {
                                    $shouldShow = true;
                                }
                            }
                            // elseif (strpos($origTitle, 'メモ一覧') !== false) {
                            //     if ($userRole === 'admin') {
                            //         $shouldShow = true;
                            //     }
                            // }
                            // C. それ以外（パスワード再設定、アンケート等）は $shouldShow が false のままなので表示されません
                
                            if ($shouldShow): ?>
                                <li>
                                    <a href="/index.php?page=<?= htmlspecialchars($id) ?>"
                                        class="<?= ($pageId === $id) ? 'active' : '' ?>">
                                        <?= htmlspecialchars($origTitle) ?>
                                    </a>
                                </li>
                            <?php endif;
                        endforeach;
                    endif;
                    ?>
                    <li class="logout-item">
                        <a href="/index.php?page=logout" style="color: #ff4d4d; font-weight: bold;">
                            <i class="fa-solid fa-right-from-bracket"></i>
                            ログアウト (<?= htmlspecialchars($_SESSION['username'] ?? 'ユーザー') ?>)
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
                <div id="loading-step-text" class="loading-sub-text">Googleカレンダーと同期しています...</div>
                <div id="loading-error-msg">
                    処理に時間がかかっています。<br>
                    <a href="index.php?page=home" style="text-decoration:underline;">こちらをクリックして再試行</a>してください。
                </div>
            </div>
        </div>

        <div id="real-content">
        </div>
    </main>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const skeleton = document.getElementById('skeleton-screen');
            const content = document.getElementById('real-content');
            const stepText = document.getElementById('loading-step-text');
            const errorMsg = document.getElementById('loading-error-msg');

            // 1. メッセージを動的に切り替えて「動いてる感」を出す
            const steps = [
                "Googleカレンダーと同期しています...",
                "データベースを更新しています...",
                "表示データを準備しています...",
                "まもなく完了します..."
            ];
            let stepIdx = 0;
            const stepInterval = setInterval(() => {
                if (stepIdx < steps.length - 1) {
                    stepIdx++;
                    stepText.innerText = steps[stepIdx];
                }
            }, 3000);

            // 2. 10秒経っても終わらない場合のエラー表示
            const errorTimer = setTimeout(() => {
                if (skeleton.style.display !== 'none') {
                    errorMsg.style.display = 'block';
                }
            }, 10000);

            // 3. 読み込み完了時の処理
            window.addEventListener('load', () => {
                setTimeout(() => {
                    clearInterval(stepInterval);
                    clearTimeout(errorTimer);
                    if (skeleton) skeleton.style.display = 'none';
                    if (content) content.style.display = 'block';
                }, 500);
            });

            // 4. ページ遷移時に再表示
            window.addEventListener('beforeunload', () => {
                if (skeleton) skeleton.style.display = 'block';
                if (content) content.style.display = 'none';
            });

            // ナビスクロール
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