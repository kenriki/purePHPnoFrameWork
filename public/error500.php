<?php
// ステータスコードを正しく500として返す
http_response_code(500);
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <title>500 Internal Server Error</title>
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
    <div class="error-code">500</div>
    <div class="message">500 Internal Server Error</div>
    <p>サーバーで問題が発生したため、ページを表示することができません。
現在、復旧作業を行っております。しばらく時間をおいてから、再度アクセスをお試しください。</p>
    <a href="/sample/index.php" class="btn">トップページへ戻る</a>
</body>

</html>