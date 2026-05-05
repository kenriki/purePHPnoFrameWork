<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        details summary::-webkit-details-marker {
            display: none;
        }

        details summary::before {
            content: '▶ ';
            font-size: 0.8em;
        }

        details[open] summary::before {
            content: '▼ ';
        }

        .code-block {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 12px;
            border-radius: 4px;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 0.85rem;
            position: relative;
        }

        .copy-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            font-size: 0.75rem;
        }
    </style>
</head>

<body class="bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0"><?= htmlspecialchars($title) ?></h2>
            <span class="badge bg-secondary"><?= htmlspecialchars($_SERVER['SERVER_NAME'] ?? 'localhost') ?></span>
        </div>

        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-primary text-white">新規API追加</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Method</label>
                            <select name="method" class="form-select">
                                <option value="GET">GET</option>
                                <option value="POST">POST</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Endpoint</label>
                            <input name="endpoint" class="form-control" placeholder="例: status" required
                                pattern="[a-zA-Z0-9_-]+">
                            <div class="form-text">?page=api&api=xxx になります</div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Response JSON</label>
                            <textarea name="response_json" class="form-control" rows="2"
                                placeholder='{"message":"ok","time":"2026-01"}' required></textarea>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button class="btn btn-primary w-100">追加</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header">登録済みAPI一覧</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:100px">Method</th>
                            <th>Endpoint</th>
                            <th>Response</th>
                            <th style="width:120px">テスト</th>
                            <th style="width:80px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($apis)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    APIがまだ登録されていません<br>
                                    <small>上から追加してください</small>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($apis as $api): ?>
                                <?php
                                $json = $api['response_json'];
                                $decoded = json_decode($json, true);
                                if ($decoded !== null) {
                                    $json = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                                }
                                $len = mb_strlen($api['response_json']);
                                $baseUrl = ($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . $_SERVER['HTTP_HOST'];
                                $apiUrl = $baseUrl . '/?page=api&api=' . urlencode($api['endpoint']);
                                ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-<?= $api['method'] === 'GET' ? 'success' : 'primary' ?>">
                                            <?= htmlspecialchars($api['method']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <code>?page=api&api=<?= htmlspecialchars($api['endpoint']) ?></code>
                                    </td>
                                    <td>
                                        <details>
                                            <summary class="text-primary" style="cursor:pointer;user-select:none;">
                                                JSONを表示 / <?= number_format($len) ?> 文字
                                            </summary>
                                            <pre class="mt-2 mb-0 p-2 bg-light border rounded small text-wrap"
                                                style="max-height:300px;overflow:auto;"><code><?= htmlspecialchars($json) ?></code></pre>
                                        </details>
                                    </td>
                                    <td>
                                        <?php if ($api['method'] === 'GET'): ?>
                                            <a href="?page=api&api=<?= urlencode($api['endpoint']) ?>" target="_blank"
                                                class="btn btn-sm btn-outline-success">
                                                GET実行
                                            </a>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                onclick="postApi('<?= htmlspecialchars($api['endpoint']) ?>', '<?= htmlspecialchars($apiUrl) ?>')">
                                                POST実行
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST"
                                            onsubmit="return confirm('<?= htmlspecialchars($api['endpoint']) ?> を削除しますか?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $api['id'] ?>">
                                            <button class="btn btn-sm btn-danger">削除</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="alert alert-info mt-4">
            <h6 class="alert-heading">使い方</h6>
            <div class="row">
                <div class="col-md-6">
                    <strong>GET APIの場合</strong><br>
                    1. 上のフォームで <code>endpoint</code> と返すJSONを登録<br>
                    2.
                    <code><?= htmlspecialchars($_SERVER['REQUEST_SCHEME'] ?? 'https') ?>://<?= htmlspecialchars($_SERVER['HTTP_HOST']) ?>/?page=api&api=エンドポイント名</code>
                    にアクセスでJSON取得<br>
                    3. 「GET実行」ボタンでもテスト可能
                </div>
                <div class="col-md-6">
                    <strong>POST APIの場合</strong><br>
                    1. MethodをPOSTにして登録<br>
                    2. 「POST実行」ボタンでテスト<br>
                    3. 外部からはcurlやfetchで叩く ↓
                </div>
            </div>
        </div>

        <div class="card mt-4 shadow-sm">
            <div class="card-header bg-dark text-white">
                POST実行例 - ポケモン検索APIのサンプル
            </div>
            <div class="card-body">
                <p class="mb-2">例えば <code>Endpoint: pokemon_search</code> <code>Method: POST</code> で登録した場合：</p>

                <h6 class="mt-3">1. curlコマンド</h6>
                <div class="code-block">
                    <button class="btn btn-sm btn-secondary copy-btn" onclick="copyCode(this)">コピー</button>
                    <pre id="curl-example">curl -X POST "<?= htmlspecialchars($_SERVER['REQUEST_SCHEME'] ?? 'https') ?>://<?= htmlspecialchars($_SERVER['HTTP_HOST']) ?>/?page=api&api=pokemon_search" \
  -H "Content-Type: application/json" \
  -d '{"name":"pikachu"}'</pre>
                </div>

                <h6 class="mt-3">2. JavaScript fetch</h6>
                <div class="code-block">
                    <button class="btn btn-sm btn-secondary copy-btn" onclick="copyCode(this)">コピー</button>
                    <pre id="fetch-example">fetch('<?= htmlspecialchars($_SERVER['REQUEST_SCHEME'] ?? 'https') ?>://<?= htmlspecialchars($_SERVER['HTTP_HOST']) ?>/?page=api&api=pokemon_search', {
  method: 'POST',
  headers: {'Content-Type': 'application/json'},
  body: JSON.stringify({name: 'pikachu'})
})
.then(r => r.json())
.then(data => console.log(data));</pre>
                </div>

                <h6 class="mt-3">3. PHPで叩く場合</h6>
                <div class="code-block">
                    <button class="btn btn-sm btn-secondary copy-btn" onclick="copyCode(this)">コピー</button>
                    <pre id="php-example">$url = '<?= htmlspecialchars($_SERVER['REQUEST_SCHEME'] ?? 'https') ?>://<?= htmlspecialchars($_SERVER['HTTP_HOST']) ?>/?page=api&api=pokemon_search';
$data = json_encode(['name' => 'pikachu']);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
curl_close($ch);

echo $result;</pre>
                </div>

                <div class="alert alert-warning mt-3 mb-0">
                    <strong>注意:</strong> 今のAPIは固定JSONしか返さないので、送ったPOSTデータは無視されます。
                    データを受け取って処理したい場合は <code>ApiController.php</code> を改造してください。
                </div>
            </div>
        </div>

        <div class="text-muted text-center mt-4 small">
            現場PC: <?= htmlspecialchars(gethostname()) ?> |
            PHP <?= PHP_VERSION ?>
        </div>
    </div>

    <script>
        document.querySelector('textarea[name="response_json"]').addEventListener('blur', function (e) {
            try {
                const obj = JSON.parse(e.target.value);
                e.target.value = JSON.stringify(obj, null, 2);
                e.target.classList.remove('is-invalid');
            } catch (err) {
                e.target.classList.add('is-invalid');
            }
        });

        function postApi(endpoint, fullUrl) {
            const result = confirm('POST ' + endpoint + ' を実行しますか？\n\nURL: ' + fullUrl);
            if (!result) return;

            fetch('?page=api&api=' + endpoint, { method: 'POST' })
                .then(r => r.text())
                .then(t => {
                    try {
                        const json = JSON.parse(t);
                        alert('POST成功:\n' + JSON.stringify(json, null, 2));
                    } catch (e) {
                        alert('Response:\n' + t);
                    }
                })
                .catch(e => alert('Error: ' + e));
        }

        function copyCode(btn) {
            const pre = btn.nextElementSibling;
            const text = pre.innerText;
            navigator.clipboard.writeText(text).then(() => {
                const original = btn.textContent;
                btn.textContent = 'コピー完了';
                btn.classList.remove('btn-secondary');
                btn.classList.add('btn-success');
                setTimeout(() => {
                    btn.textContent = original;
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-secondary');
                }, 1500);
            });
        }
    </script>
</body>

</html>