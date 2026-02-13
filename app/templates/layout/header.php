<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page['title']) ?></title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header>
    <h1><?= htmlspecialchars($page['title']) ?></h1>
</header>
<main>
    <nav>
        <ul>
            <li><a href="/index.php?page=home">Home</a></li>
            <li><a href="/index.php?page=sample">Sample</a></li>
            <li><a href="/index.php?page=about">About</a></li>
            <li><a href="/index.php?page=etc">etc</a></li>
        </ul>
    </nav>