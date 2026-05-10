<?php
// ステータスコードを正しく403として返す
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <title>403 Forbidden</title>
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
    <div class="error-code">403</div>
    <div class="message">アクセス権限がありません</div>
    <p>お探しのページは、アクセスが許可されていないか、一時的に利用できない可能性があります。</p>
    <a href="/sample/index.php" class="btn">トップページへ戻る</a>
</body>

</html>