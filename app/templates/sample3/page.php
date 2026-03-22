<?php
//echo shell_exec('whoami 2>&1');
// 1. Python実行設定
//$pythonPath = "C:\\Users\\user\\AppData\\Local\\Programs\\Python\\Python313\\python.exe";
$pythonPath = "C:\\Program Files\\Python314\\python.exe";
$scriptPath = __DIR__ . '/../../scripts/process_data.py';
$csvPath = __DIR__ . '/../../data/data.csv';

// 2. Python実行・データ取得
$command = escapeshellarg($pythonPath) . " " . escapeshellarg($scriptPath) . " " . escapeshellarg($csvPath) . " 2>&1";
$output = shell_exec($command);
$output = mb_convert_encoding($output, 'UTF-8', 'SJIS,CP932,auto');
$chartData = json_decode($output, true);
?>

<!-- 外部JSファイルの読み込み (public/assets/js/chart.js) -->
<script src="/assets/js/chart.js"></script>

<div class="chart-container">
    <h3>統計データ可視化 (外部JS連携)</h3>

    <?php if ($chartData && !isset($chartData['error'])): ?>
        <div style="position: relative; height:400px; width:100%">
            <canvas id="myCsvChart"></canvas>
        </div>

        <script>
            // DOMの読み込み完了後に外部JSの関数を呼び出す
            window.addEventListener('load', function() {
                const data = <?php echo json_encode($chartData); ?>;
                
                // 外部JSファイル(assets/js/chart.js)内で定義した関数を呼び出す
                // 第一引数: CanvasのID, 第二引数: グラフ用データ
                if (typeof renderMyChart === 'function') {
                    renderMyChart('myCsvChart', data);
                } else {
                    console.error('外部JSの関数 renderMyChart が見つかりません。');
                }
            });
        </script>
    <?php else: ?>
        <div style="color: red; padding: 10px; border: 1px solid red;">
            <strong>データ取得エラー:</strong><br>
            <?= nl2br(htmlspecialchars($output)) ?>
        </div>
    <?php endif; ?>
</div>

<script>

/**
 * PHPから呼ばれる描画用関数
 */
function renderMyChart(elementId, phpData) {
    const ctx = document.getElementById(elementId).getContext('2d');
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: phpData.labels,
            datasets: [{
                label: 'CSVスコア',
                data: phpData.values,
                backgroundColor: 'rgba(75, 192, 192, 0.6)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true } }
        }
    });
}
</script>
