<?php
// ステータスコードを正しく404として返す
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <title>404 Not Found</title>
    <style>
        body {
            text-align: center;
            padding: 50px;
            font-family: sans-serif;
        }

        .error-code {
            font-size: 80px;
            color: #ccc;
        }

        .message {
            font-size: 24px;
            margin-bottom: 20px;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>

<body>
    <div class="error-code">404</div>
    <div class="message">404 Not Found</div>
    <p>アクセスしようとしたページは削除されたか、URLが変更された可能性があります。
お手数ですが、トップページからお探しいただくか、メニューより目的のコンテンツをお選びください。</p>
    <a href="/sample/index.php" class="btn">トップページへ戻る</a>
</body>

</html>