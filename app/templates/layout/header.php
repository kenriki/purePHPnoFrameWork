<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page['title']) ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        table { border-collapse: collapse; width: 100%; font-size: 12px; }
        th, td { border: 1px solid #aaa; padding: 4px; text-align: center; }
        th { background: #eee; }
    </style>
</head>
<body>
<header>
    <h1><?= htmlspecialchars($page['title']) ?></h1>
</header>
<main>
    <nav class="scroll-nav">
        <button class="nav-arrow left">‹</button>
        <ul>
            <?php
            // 1. JSONファイルを読み込む（PageControllerと同様の処理）
            $menuData = json_decode(file_get_contents(DATA_PATH), true);

            // 2. ループで li タグを生成
            if ($menuData):
                foreach ($menuData as $id => $content): 
                    // 特定のページを表示したくない場合はここで除外も可能
                    ?>
                    <li>
                        <a href="/index.php?page=<?= htmlspecialchars($id) ?>">
                            <?= htmlspecialchars($content['title'] ?? $id) ?>
                        </a>
                    </li>
                <?php 
                endforeach;
            endif; 
            ?>
        </ul>
        <button class="nav-arrow right">›</button>
    </nav>
