<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page['title']) ?></title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="/assets/css/style.css">
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
    </style>
</head>

<body>
    <header>
        <h1><?= htmlspecialchars($page['title']) ?></h1>
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
                            $title = $content['title'] ?? '';

                            // --- 表示判定 ---
                            $shouldShow = false;

                            // 1. 「メモ」は全員
                            if ($title === 'メモ') {
                                $shouldShow = true;
                            }
                            // 2. 「サンプル」が含まれる項目は admin のみ表示
                            // ※判定を 'サンプル' だけでなく 'サンプルページ' 等にも対応させる
                            elseif (strpos($title, 'サンプル') !== false && $userRole === 'admin') {
                                $shouldShow = true;
                            }

                            if ($shouldShow): ?>
                                <li>
                                    <a href="/index.php?page=<?= htmlspecialchars($id) ?>">
                                        <?= htmlspecialchars($title) ?>
                                    </a>
                                </li>
                            <?php endif;
                        endforeach;
                    endif;
                    ?>

                    <li class="logout-item">
                        <a href="/index.php?page=logout" style="color: #ff4d4d; font-weight: bold;">
                            ログアウト (<?= htmlspecialchars($_SESSION['username'] ?? 'ユーザー') ?>)
                        </a>
                    </li>
                </ul>
                <button class="nav-arrow right">›</button>
            </nav>
        <?php endif; ?>