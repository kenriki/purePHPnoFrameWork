<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>共有メモの閲覧</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .memo-content {
            white-space: pre-wrap;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
    </style>
</head>

<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="mb-3 d-flex justify-content-between align-items-center">
                    <h2 class="h4 mb-0">📄 共有されたメモ</h2>
                    <span class="badge bg-danger">期間限定公開</span>
                </div>

                <div class="card shadow-sm">
                    <div class="card-body">
                        <h1 class="h3 border-bottom pb-2 mb-3">
                            <?= htmlspecialchars($memo['title'] ?? '無題') ?>
                        </h1>

                        <div class="memo-content mb-4">
                            <?= htmlspecialchars($memo['content'] ?? '') ?>
                        </div>

                        <div class="text-muted small">
                            <p class="mb-0">作成者: 管理ユーザー</p>
                            <p>このリンクの有効期限:
                                <?= htmlspecialchars($memo['share_expires_at']) ?>
                            </p>
                        </div>
                    </div>
                </div>

                <p class="text-center mt-4 text-muted small">
                    &copy;
                    <?= date('Y') ?> Memo APP
                </p>
            </div>
        </div>
    </div>
</body>

</html>