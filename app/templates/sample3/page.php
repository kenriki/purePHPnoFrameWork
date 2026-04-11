<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();
set_time_limit(60);

// パス設定（環境に合わせて修正してください）
$pythonPath = "C:\\Program Files\\Python314\\python.exe";
$scriptPath = __DIR__ . '/../../scripts/process_data.py';
$uploadDir = __DIR__ . '/../../data/uploads/';

$csvInfo = null;
$chartData = null;
$error = null;

// 現在保持している一時ファイルパス
$tmpFile = $_POST['tmp_file_path'] ?? '';

// --- 1. 新しいCSVがアップロードされた場合 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
    if (!is_dir($uploadDir))
        mkdir($uploadDir, 0777, true);

    // 古い一時ファイルがあれば削除してリセット
    if (!empty($tmpFile) && file_exists($tmpFile))
        unlink($tmpFile);

    // 新しいファイル名を作成
    $tmpFile = $uploadDir . 'upload_' . session_id() . '_' . time() . '.csv';

    if (move_uploaded_file($_FILES['csv_file']['tmp_name'], $tmpFile)) {
        // Pythonを呼び出してカラム一覧だけ取得
        $cmd = escapeshellarg($pythonPath) . " " . escapeshellarg($scriptPath) . " " . escapeshellarg($tmpFile);
        $output = shell_exec($cmd);
        $csvInfo = json_decode($output, true);

        if (!$csvInfo) {
            $error = "CSVの読み込みに失敗しました。形式を確認してください。";
        }
    } else {
        $error = "ファイルの移動に失敗しました。フォルダの権限を確認してください。";
    }
}

// --- 2. 解析ボタンが押された場合 ---
if (isset($_POST['generate_chart']) && !empty($tmpFile)) {
    if (!file_exists($tmpFile)) {
        $error = "ファイルが見つかりません。もう一度アップロードしてください。";
    } else {
        $labelCol = $_POST['label_column'] ?? '';
        $valueCol = $_POST['value_column'] ?? '';
        $rules = [];
        if (isset($_POST['rules'])) {
            foreach ($_POST['rules'] as $rule) {
                if (!empty($rule['keywords'])) {
                    $rules[] = ['keywords' => $rule['keywords'], 'category' => $rule['category'] ?: '未分類'];
                }
            }
        }
        $rulesJson = json_encode($rules, JSON_UNESCAPED_UNICODE);

        $command = escapeshellarg($pythonPath) . " " . escapeshellarg($scriptPath) . " " .
            escapeshellarg($tmpFile) . " " . escapeshellarg($labelCol) . " " .
            escapeshellarg($valueCol) . " " . escapeshellarg($rulesJson);

        $output = shell_exec($command);
        $chartData = json_decode($output, true);

        // 解析が終わったら即座にファイルを削除（他CSVとの混同・漏洩防止）
        unlink($tmpFile);
        $tmpFile = ""; // パスをクリア

        if (isset($chartData['error'])) {
            $error = "解析エラー: " . $chartData['error'];
            $chartData = null;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <title>CSV Multi-Analyzer</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.3.0/exceljs.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    <style>
        body {
            background: #f0f2f5;
            font-family: sans-serif;
            padding: 20px;
        }

        .card {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .btn {
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            border: none;
            font-weight: bold;
        }

        .btn-blue {
            background: #007bff;
            color: white;
        }

        .btn-green {
            background: #28a745;
            color: white;
        }

        .rule-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }

        input,
        select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>

<body>

    <div class="card">
        <h3>1. CSVファイルを読み込む</h3>
        <form action="" method="post" enctype="multipart/form-data">
            <input type="file" name="csv_file" accept=".csv" required>
            <button type="submit" class="btn btn-blue">ファイルを読み込み</button>
            <?php if ($chartData): ?>
                <a href="" style="margin-left: 15px; font-size: 0.9em;">リセットして別のファイルを読み込む</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($error): ?>
        <div class="card" style="background: #fff5f5; border: 1px solid #feb2b2; color: #c53030;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($csvInfo && !$chartData): ?>
        <form action="" method="post">
            <input type="hidden" name="tmp_file_path" value="<?= htmlspecialchars($tmpFile) ?>">
            <?php foreach ($csvInfo['columns'] as $c): ?>
                <input type="hidden" name="all_columns[]" value="<?= htmlspecialchars($c) ?>">
            <?php endforeach; ?>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="card">
                    <h3>2. 軸の設定</h3>
                    横軸（ラベル）:<br>
                    <select name="label_column" style="width:100%; margin-bottom:15px;">
                        <?php foreach ($csvInfo['columns'] as $col): ?>
                            <option value="<?= htmlspecialchars($col) ?>"><?= htmlspecialchars($col) ?></option>
                        <?php endforeach; ?>
                        <option value="曜日">曜日 (自動生成)</option>
                        <option value="自動カテゴリ">自動カテゴリ</option>
                    </select><br>
                    縦軸（数値/件数）:<br>
                    <select name="value_column" style="width:100%;">
                        <?php foreach ($csvInfo['columns'] as $col): ?>
                            <option value="<?= htmlspecialchars($col) ?>"><?= htmlspecialchars($col) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="card">
                    <h3>3. カテゴリ追加ルール</h3>
                    <div id="rules-box">
                        <div class="rule-row">
                            <input type="text" name="rules[0][keywords]" placeholder="キーワード" style="flex:2;">
                            <input type="text" name="rules[0][category]" placeholder="カテゴリ名" style="flex:1;">
                        </div>
                    </div>
                    <button type="button" class="btn" onclick="addRule()">+ ルールを追加</button>
                </div>
            </div>

            <div style="text-align: center;">
                <button type="submit" name="generate_chart" class="btn btn-blue"
                    style="width: 300px; font-size: 1.2em;">解析を実行して表示</button>
            </div>
        </form>
    <?php endif; ?>

    <?php if ($chartData): ?>
        <div style="text-align: right; margin-bottom: 10px;">
            <button id="btnExcel" class="btn btn-green">📊 グラフ付きExcelを保存</button>
        </div>

        <div class="card">
            <h3>解析グラフ</h3>
            <div style="height: 400px;"><canvas id="myChart"></canvas></div>
        </div>

        <div class="card">
            <h3>データ詳細</h3>
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

            $(document).ready(function () {
                $('#resTable').DataTable({ language: { url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/ja.json" }, pageLength: 10 });
            });

            document.getElementById('btnExcel').addEventListener('click', async () => {
                const workbook = new ExcelJS.Workbook();
                const worksheet = workbook.addWorksheet('Sheet1');
                const img = workbook.addImage({ base64: myChart.toBase64Image(), extension: 'png' });
                worksheet.addImage(img, { tl: { col: 0, row: 1 }, ext: { width: 800, height: 400 } });

                const startRow = 25;
                const cols = <?= json_encode($chartData['columns']) ?>;
                const data = <?= json_encode($chartData['raw_data']) ?>;

                const header = worksheet.getRow(startRow);
                cols.forEach((c, i) => { header.getCell(i + 1).value = c; header.getCell(i + 1).font = { bold: true }; });
                data.forEach((r, i) => {
                    const row = worksheet.getRow(startRow + 1 + i);
                    cols.forEach((c, j) => { row.getCell(j + 1).value = r[c]; });
                });

                const buf = await workbook.xlsx.writeBuffer();
                saveAs(new Blob([buf]), `Analysis_${Date.now()}.xlsx`);
            });
        </script>
    <?php endif; ?>

    <script>
        let ruleCount = 1;
        function addRule() {
            const box = document.getElementById('rules-box');
            const div = document.createElement('div');
            div.className = 'rule-row';
            div.innerHTML = `<input type="text" name="rules[${ruleCount}][keywords]" placeholder="キーワード" style="flex:2;">
                             <input type="text" name="rules[${ruleCount}][category]" placeholder="カテゴリ名" style="flex:1;">
                             <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; cursor:pointer;">❌</button>`;
            box.appendChild(div);
            ruleCount++;
        }
    </script>
</body>

</html>