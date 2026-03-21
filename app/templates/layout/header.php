<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page['title']) ?></title>
    <link rel="stylesheet" href="/assets/style.css">
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
    <nav>
        <ul>
            <li><a href="/index.php?page=home">Home</a></li>
            <li><a href="/index.php?page=sample">テーブル1Sample</a></li>
            <li><a href="/index.php?page=sample2">テーブル2Sample</a></li>
            <li><a href="/index.php?page=sample1">じゃんけん</a></li>
            <li><a href="/index.php?page=sample3">Python連携</a></li>
            <li><a href="/index.php?page=about">About</a></li>
            <li><a href="/index.php?page=etc">etc</a></li>
        </ul>
    </nav>
    <!-- <nav>
        <ul class="menu">
            <li><a href="/index.php?page=home">Home</a></li> -->

            <!-- ▼ Hover で開くテーブルメニュー -->
            <!-- <li class="dropdown">
                <a href="#">サンプルコード</a>
                <ul class="dropdown-menu">
                    <li><a href="/index.php?page=sample">テーブル1Sample</a></li>
                    <li><a href="/index.php?page=sample2">テーブル2Sample</a></li>
                    <li><a href="/index.php?page=sample1">じゃんけん</a></li>
                </ul>
            </li>

            <li><a href="/index.php?page=about">About</a></li>
            <li><a href="/index.php?page=etc">etc</a></li>
        </ul>
    </nav> -->