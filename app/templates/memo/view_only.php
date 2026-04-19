<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>共有されたメモ - <?= htmlspecialchars($memo['title'] ?? '無題') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Noto Sans JP', sans-serif;
            color: #333;
        }

        .memo-card {
            border-radius: 15px;
            border: none;
            overflow: hidden;
        }

        .memo-content {
            white-space: pre-wrap;
            /* 改行を保持 */
            word-wrap: break-word;
            /* 長い英単語の折り返し */
            line-height: 1.8;
            font-size: 1.05rem;
            background-color: #ffffff;
            padding: 20px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
        }

        .share-badge {
            font-size: 0.75rem;
            vertical-align: middle;
        }
    </style>
</head>

<body>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="d-flex align-items-center mb-4">
                    <i class="far fa-file-alt fa-2x text-primary me-3"></i>
                    <h2 class="h4 mb-0">共有されたメモ</h2>
                    <span class="badge bg-danger ms-auto share-badge">期間限定公開</span>
                </div>

                <div class="card shadow-sm memo-card">
                    <div class="card-body p-4">
                        <h1 class="h3 border-bottom pb-3 mb-4 text-dark fw-bold">
                            <?= htmlspecialchars($memo['title'] ?? '無題') ?>
                        </h1>

                        <div class="memo-content mb-4 text-secondary">
                            <?php if (!empty($decryptedContent)): ?>
                                <?= htmlspecialchars($decryptedContent) ?>
                            <?php else: ?>
                                <span class="text-muted italic">本文はありません。</span>
                            <?php endif; ?>
                        </div>

                        <div class="bg-light p-3 rounded text-muted small">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span><i class="far fa-user me-1"></i> 作成者: 管理ユーザー</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <i class="far fa-clock me-1"></i>
                                <span>有効期限:
                                    <strong class="text-danger">
                                        <?= htmlspecialchars(date('Y/m/d H:i', strtotime($memo['share_expires_at']))) ?>
                                    </strong>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <p class="text-muted small">※このページは24時間経過すると自動的に閲覧できなくなります。</p>
                </div>
            </div>
        </div>
    </div>

</body>

</html>