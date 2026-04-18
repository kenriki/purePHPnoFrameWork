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
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
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
                            // 判定には JSON から読み込んだ元のタイトルを使用する
                            $origTitle = $content['title'] ?? '';

                            // --- 表示判定ロジックの修正（ホワイトリスト方式） ---
                            $shouldShow = false;

                            // A. ホーム（IDで判定）または「メモ」という名前のページは全員に表示
                            if ($id === 'home' || $origTitle === 'メモ') {
                                $shouldShow = true;
                            }
                            // B. タイトルに「サンプル」が含まれるページは admin のみ表示
                            elseif (strpos($origTitle, 'サンプル') !== false) {
                                if ($userRole === 'admin') {
                                    $shouldShow = true;
                                }
                            }
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