<?php
// セッション開始
if (session_status() === PHP_SESSION_NONE)
    session_start();
set_time_limit(60);

$pythonPath = "C:\\Program Files\\Python314\\python.exe";
$scriptPath = __DIR__ . '/../../scripts/process_data.py';
$uploadDir = __DIR__ . '/../../data/uploads/';

$csvInfo = null;
$chartData = null;
$error = null;
$tmpFile = $_POST['tmp_file_path'] ?? '';

// --- 1. ファイルアップロード (初期読み込み) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
    // if (!is_dir($uploadDir))
    //     mkdir($uploadDir, 0777, true);
    //$tmpFile = $uploadDir . 'upload_' . session_id() . '_' . time() . '.csv';

    if (move_uploaded_file($_FILES['csv_file']['tmp_name'], $tmpFile)) {
        $cmd = escapeshellarg($pythonPath) . " " . escapeshellarg($scriptPath) . " " . escapeshellarg($tmpFile);
        $output = shell_exec($cmd);
        $csvInfo = json_decode($output, true);
        if (!$csvInfo) {
            $error = "初期読み込みエラー: Pythonの応答を確認してください。";
            if (file_exists($tmpFile))
                unlink($tmpFile);
        }
    }
}

// --- 2. 解析・グラフ描画処理 ---
if (isset($_POST['generate_chart']) && !empty($tmpFile)) {
    $labelCol = $_POST['label_column'] ?? '';
    $valueCol = $_POST['value_column'] ?? '';

    // カスタムカテゴリ・ルールの取得
    $rules = [];
    if (isset($_POST['rules'])) {
        foreach ($_POST['rules'] as $rule) {
            if (!empty($rule['keywords'])) {
                $rules[] = [
                    'keywords' => $rule['keywords'],
                    'category' => $rule['category'] ?: '未分類'
                ];
            }
        }
    }
    $rulesJson = json_encode($rules, JSON_UNESCAPED_UNICODE);

    $command = escapeshellarg($pythonPath) . " " . escapeshellarg($scriptPath) . " " .
        escapeshellarg($tmpFile) . " " .
        escapeshellarg($labelCol) . " " .
        escapeshellarg($valueCol) . " " .
        escapeshellarg($rulesJson);

    $output = shell_exec($command);
    $chartData = json_decode($output, true);

    // 解析後にファイルを削除 (情報漏洩対策)
    if (file_exists($tmpFile))
        unlink($tmpFile);

    if (isset($chartData['error'])) {
        $error = $chartData['error'];
        $chartData = null;
    }

    // エラー回避のため、カラム情報を hidden から復元
    $csvInfo = ['columns' => $_POST['all_columns'] ?? []];
}
?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <title>CSV統計解析ツール</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.3.0/exceljs.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>

    <style>
        body {
            background: #f8f9fa;
            font-family: sans-serif;
            padding: 20px;
        }

        .card {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            border-left: 5px solid #007bff;
            padding-left: 10px;
            margin-bottom: 15px;
            font-weight: bold;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-weight: bold;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-excel {
            background: #1D6F42;
            color: white;
        }

        .rule-row {
            display: flex;
            gap: 5px;
            margin-bottom: 8px;
        }

        .error-msg {
            color: #d9534f;
            background: #f2dede;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>

    <div class="card">
        <div class="section-title">1. CSVアップロード</div>
        <form action="" method="post" enctype="multipart/form-data">
            <input type="file" name="csv_file" accept=".csv" required>
            <button type="submit" class="btn btn-primary">読み込み</button>
        </form>
    </div>

    <?php if ($error): ?>
        <div class="error-msg"><strong>エラー:</strong> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($csvInfo && !$chartData): ?>
        <form action="" method="post">
            <input type="hidden" name="tmp_file_path" value="<?= htmlspecialchars($tmpFile) ?>">
            <?php foreach ($csvInfo['columns'] as $c): ?>
                <input type="hidden" name="all_columns[]" value="<?= htmlspecialchars($c) ?>">
            <?php endforeach; ?>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="card">
                    <div class="section-title">2. 軸の設定</div>
                    <p>横軸 (ラベル):</p>
                    <select name="label_column" style="width:100%; padding:8px;">
                        <?php foreach ($csvInfo['columns'] as $col): ?>
                            <option value="<?= htmlspecialchars($col) ?>"><?= htmlspecialchars($col) ?></option>
                        <?php endforeach; ?>
                        <option value="曜日">曜日 (自動作成)</option>
                        <option value="自動カテゴリ">自動カテゴリ (下記ルール適用)</option>
                    </select>

                    <p>縦軸 (数値合計/件数):</p>
                    <select name="value_column" style="width:100%; padding:8px;">
                        <?php foreach ($csvInfo['columns'] as $col): ?>
                            <option value="<?= htmlspecialchars($col) ?>"><?= htmlspecialchars($col) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="card">
                    <div class="section-title">3. カスタムルール (カテゴリ分け)</div>
                    <div id="rules-container">
                        <div class="rule-row">
                            <input type="text" name="rules[0][keywords]" placeholder="例: 会議,打合せ" style="flex:2;">
                            <input type="text" name="rules[0][category]" placeholder="カテゴリ名" style="flex:1;">
                        </div>
                    </div>
                    <button type="button" class="btn" onclick="addRule()">+ ルールを追加</button>
                </div>
            </div>

            <div style="text-align: center; margin-top: 20px;">
                <button type="submit" name="generate_chart" class="btn btn-primary" style="width:250px;">データを解析して描画</button>
            </div>
        </form>
    <?php endif; ?>

    <?php if ($chartData): ?>
        <div style="text-align: right; margin-bottom: 15px;">
            <button id="excelExportBtn" class="btn btn-excel">📊 グラフ付きExcelをダウンロード</button>
        </div>

        <div class="card">
            <div class="section-title">解析結果グラフ</div>
            <div style="height: 400px;">
                <canvas id="myChart"></canvas>
            </div>
        </div>

        <div class="card">
            <div class="section-title">データ詳細</div>
            <table id="resTable" class="display" style="width:100%">
                <thead>
                    <tr><?php foreach ($chartData['columns'] as $col): ?>
                            <th><?= htmlspecialchars($col) ?></th><?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($chartData['raw_data'] as $row): ?>
                        <tr><?php foreach ($chartData['columns'] as $col): ?>
                                <td><?= htmlspecialchars($row[$col] ?? '') ?></td><?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <script>
            // --- Chart.js ---
            const ctx = document.getElementById('myChart');
            const myChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($chartData['labels']) ?>,
                    datasets: [{
                        label: <?= json_encode($chartData['title']) ?>,
                        data: <?= json_encode($chartData['values']) ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.7)',
                        borderColor: 'rgb(54, 162, 235)',
                        borderWidth: 1
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });

            // --- DataTables ---
            $(document).ready(function () {
                $('#resTable').DataTable({
                    language: { url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/ja.json" },
                    pageLength: 25,
                    scrollX: true
                });
            });

            // --- ExcelJS: グラフと表をまとめたExcel出力 ---
            document.getElementById('excelExportBtn').addEventListener('click', async () => {
                const workbook = new ExcelJS.Workbook();
                const worksheet = workbook.addWorksheet('解析レポート');

                // 1. グラフを画像として追加
                const base64Image = myChart.toBase64Image();
                const imageId = workbook.addImage({ base64: base64Image, extension: 'png' });
                worksheet.addImage(imageId, {
                    tl: { col: 0.2, row: 1 },
                    ext: { width: 700, height: 350 }
                });

                // 2. データの書き込み開始行 (グラフの下)
                const startRow = 22;
                const columns = <?= json_encode($chartData['columns']) ?>;
                const rows = <?= json_encode($chartData['raw_data']) ?>;

                // ヘッダー
                const header = worksheet.getRow(startRow);
                columns.forEach((col, i) => {
                    const cell = header.getCell(i + 1);
                    cell.value = col;
                    cell.font = { bold: true };
                    cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF2F2F2' } };
                });

                // データ
                rows.forEach((rowData, rIdx) => {
                    const row = worksheet.getRow(startRow + 1 + rIdx);
                    columns.forEach((colName, cIdx) => {
                        row.getCell(cIdx + 1).value = rowData[colName];
                    });
                });

                // 保存
                const buffer = await workbook.xlsx.writeBuffer();
                saveAs(new Blob([buffer]), `Report_${new Date().getTime()}.xlsx`);
            });
        </script>
    <?php endif; ?>

    <script>
        // カテゴリ追加UI用
        let ruleIdx = 1;
        function addRule() {
            const container = document.getElementById('rules-container');
            const div = document.createElement('div');
            div.className = 'rule-row';
            div.innerHTML = `
                <input type="text" name="rules[${ruleIdx}][keywords]" placeholder="キーワード" style="flex:2;">
                <input type="text" name="rules[${ruleIdx}][category]" placeholder="カテゴリ名" style="flex:1;">
                <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; cursor:pointer;">❌</button>
            `;
            container.appendChild(div);
            ruleIdx++;
        }
    </script>
</body>

</html>