<?php
set_time_limit(60);

$pythonPath = "C:\\Program Files\\Python314\\python.exe";
$scriptPath = __DIR__ . '/../../scripts/process_data.py';
$uploadDir = __DIR__ . '/../../data/uploads/';

$csvInfo = null;
$chartData = null;
$error = null;
$tmpFile = $_POST['tmp_file_path'] ?? '';

// 1. アップロード処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    if (!is_dir($uploadDir))
        mkdir($uploadDir, 0777, true);
    $tmpFile = $uploadDir . 'upload_' . session_id() . '.csv';

    if (move_uploaded_file($_FILES['csv_file']['tmp_name'], $tmpFile)) {
        $output = shell_exec(escapeshellarg($pythonPath) . " " . escapeshellarg($scriptPath) . " " . escapeshellarg($tmpFile));
        $csvInfo = json_decode(mb_convert_encoding($output, 'UTF-8', 'SJIS,CP932,auto'), true);
    } else {
        $error = "ファイルの移動に失敗しました。";
    }
}

// 2. グラフ・テーブル生成
if (isset($_POST['generate_chart']) && !empty($tmpFile)) {
    $labelCol = $_POST['label_column'];
    $valueCol = $_POST['value_column'];
    $rules = [];
    if (isset($_POST['rules'])) {
        foreach ($_POST['rules'] as $rule) {
            if (!empty($rule['keywords']))
                $rules[] = $rule;
        }
    }
    $rulesJson = json_encode($rules, JSON_UNESCAPED_UNICODE);

    $command = escapeshellarg($pythonPath) . " " . escapeshellarg($scriptPath) . " " .
        escapeshellarg($tmpFile) . " " . escapeshellarg($labelCol) . " " .
        escapeshellarg($valueCol) . " " . escapeshellarg($rulesJson);

    $output = shell_exec($command . " 2>&1");
    $chartData = json_decode(mb_convert_encoding($output, 'UTF-8', 'SJIS,CP932,auto'), true);

    if (!$chartData)
        $error = "解析エラー: " . $output;
    $csvInfo = ['columns' => $_POST['all_columns'] ?? []];
}
?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <title>CSV Analyzer</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .card {
            background: #fff;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        body {
            background: #f4f7f6;
            color: #333;
        }

        h3 {
            margin-top: 0;
        }
    </style>
</head>

<body>

    <div class="app-wrapper" style="padding: 20px; max-width: 1200px; margin: 0 auto; font-family: sans-serif;">

        <section class="card" style="background: #f8f9fa;">
            <h3>1. CSVアップロード</h3>
            <form action="" method="post" enctype="multipart/form-data">
                <input type="file" name="csv_file" accept=".csv" required>
                <button type="submit" style="padding: 5px 15px; cursor:pointer;">読み込み</button>
            </form>
        </section>

        <?php if ($csvInfo): ?>
            <form action="" method="post">
                <input type="hidden" name="tmp_file_path" value="<?= htmlspecialchars($tmpFile) ?>">
                <?php foreach ($csvInfo['columns'] as $c): ?>
                    <input type="hidden" name="all_columns[]" value="<?= htmlspecialchars($c) ?>">
                <?php endforeach; ?>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <section class="card" style="background: #e3f2fd; border-color: #bbdefb;">
                        <h3>2. 軸の選択</h3>
                        <p><label>横軸（ラベル）:</label><br>
                            <select name="label_column" style="width:100%; padding: 5px;">
                                <?php foreach ($csvInfo['columns'] as $col): ?>
                                    <option value="<?= htmlspecialchars($col) ?>" <?= ($_POST['label_column'] ?? '') == $col ? 'selected' : '' ?>><?= htmlspecialchars($col) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p><label>縦軸（数値/件数）:</label><br>
                            <select name="value_column" style="width:100%; padding: 5px;">
                                <?php foreach ($csvInfo['columns'] as $col): ?>
                                    <option value="<?= htmlspecialchars($col) ?>" <?= ($_POST['value_column'] ?? '') == $col ? 'selected' : '' ?>><?= htmlspecialchars($col) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                    </section>

                    <section class="card" style="background: #fff3e0; border-color: #ffe0b2;">
                        <h3>3. カテゴリ設定（部分一致）</h3>
                        <div id="rules-area">
                            <?php
                            $existingRules = $_POST['rules'] ?? [['keywords' => '', 'category' => '']];
                            foreach ($existingRules as $i => $r): ?>
                                <div class="rule-row" style="margin-bottom: 10px; display: flex; gap: 5px;">
                                    <input type="text" name="rules[<?= $i ?>][keywords]"
                                        value="<?= htmlspecialchars($r['keywords']) ?>" placeholder="キーワード(A,B)"
                                        style="flex:2; padding:5px;">
                                    <input type="text" name="rules[<?= $i ?>][category]"
                                        value="<?= htmlspecialchars($r['category']) ?>" placeholder="カテゴリ名"
                                        style="flex:1; padding:5px;">
                                    <button type="button" onclick="this.parentElement.remove()">×</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" onclick="addRuleRow()" style="margin-top:5px;">+ ルールを追加</button>
                    </section>
                </div>

                <div style="text-align: center; margin-bottom: 20px;">
                    <button type="submit" name="generate_chart"
                        style="background:#2196f3; color:white; padding:12px 50px; border:none; border-radius:5px; font-size:1.1em; cursor:pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
                        データを解析して描画
                    </button>
                </div>
            </form>
        <?php endif; ?>

        <?php if ($error): ?>
            <div
                style="color:red; background:#fee; padding:15px; border-radius:5px; border:1px solid red; margin-bottom:20px;">
                <?= nl2br(htmlspecialchars($error)) ?>
            </div>
        <?php endif; ?>

        <?php if ($chartData): ?>
            <div class="card" style="height:450px;">
                <h3>解析結果グラフ</h3>
                <canvas id="mainChart"></canvas>
            </div>

            <div class="card">
                <h3>データ詳細一覧 (DataTables)</h3>
                <div style="overflow-x: auto;">
                    <table id="myDataTable" class="display" style="width:100%">
                        <thead>
                            <tr>
                                <?php foreach ($chartData['columns'] as $col): ?>
                                    <th><?= htmlspecialchars($col) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($chartData['raw_data'] as $row): ?>
                                <tr>
                                    <?php foreach ($chartData['columns'] as $col): ?>
                                        <td><?= htmlspecialchars($row[$col] ?? '') ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <script>
                // Chart.js
                new Chart(document.getElementById('mainChart'), {
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
                    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
                });

                // DataTables
                $(document).ready(function () {
                    $('#myDataTable').DataTable({
                        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/ja.json" },
                        "pageLength": 10,
                        "order": [] // 初期の自動ソートを無効化
                    });
                });
            </script>
        <?php endif; ?>
    </div>

    <script>
        let ruleIdx = <?= count($_POST['rules'] ?? [0]) ?>;
        function addRuleRow() {
            const area = document.getElementById('rules-area');
            const div = document.createElement('div');
            div.className = 'rule-row';
            div.style = 'margin-bottom: 10px; display: flex; gap: 5px;';
            div.innerHTML = `<input type="text" name="rules[${ruleIdx}][keywords]" placeholder="キーワード" style="flex:2; padding:5px;">
                         <input type="text" name="rules[${ruleIdx}][category]" placeholder="カテゴリ名" style="flex:1; padding:5px;">
                         <button type="button" onclick="this.parentElement.remove()">×</button>`;
            area.appendChild(div);
            ruleIdx++;
        }
    </script>

</body>

</html>