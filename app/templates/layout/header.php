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
            <li class="logout-item">
                <a href="/index.php?page=logout" style="color: #ff4d4d; font-weight: bold;">
                    ログアウト (
                    <?= htmlspecialchars($_SESSION['username'] ?? 'ユーザー') ?>)
                </a>
            </li>
            <nav class="scroll-nav">
                <button class="nav-arrow left">‹</button>
                <ul>
                    <?php
                    // 1. JSONファイルを読み込む
                    $menuData = json_decode(file_get_contents(DATA_PATH), true);

                    // 2. ループで li タグを生成
                    if ($menuData):
                        foreach ($menuData as $id => $content):
                            // 1. 表示名（title）を取得
                            $title = $content['title'] ?? '';

                            // 2. 表示名が以下のいずれかに一致したら除外する
                            $exclude_titles = ['ログイン', '新規会員登録', 'ログアウト', 'createPDF','アンケート送信完了','パスワード再設定','パスワード更新', '自動ログイン中...'];

                            if (in_array($title, $exclude_titles)) {
                                continue;
                            }
                            ?>
                            <li>
                                <a href="/index.php?page=<?= htmlspecialchars($id) ?>">
                                    <?= htmlspecialchars($title) ?>
                                </a>
                            </li>
                            <?php
                        endforeach;
                    endif;

                    // 最後にログアウトボタンだけ別枠で追加
                    if (isset($_SESSION['user_id'])): ?>
                        <li class="logout-item">
                            <a href="/index.php?page=logout" style="color: #ff4d4d;">ログアウト</a>
                        </li>
                    <?php endif; ?>
                </ul>
                <button class="nav-arrow right">›</button>
            </nav>
        <?php endif; ?>