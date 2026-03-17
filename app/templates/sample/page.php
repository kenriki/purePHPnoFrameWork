<h2><?= htmlspecialchars($page['title']) ?></h2>

<?php foreach ($page['sections'] as $section): ?>

    <?php if ($section['type'] === 'sample'): ?>
        <section class="sample">
            <h3><?= htmlspecialchars($section['headline']) ?></h3>
            <p><?= htmlspecialchars($section['subtext']) ?></p>
        </section>

    <?php elseif ($section['type'] === 'text'): ?>
        <p><?= nl2br(htmlspecialchars($section['content'])) ?></p>

    <?php endif; ?>

<?php endforeach; ?>

<?php
// --- 1. SQLの準備 ---
//$sql = "
//WITH T1 AS (
//    SELECT *, ROW_NUMBER() OVER(PARTITION BY SerialNo ORDER BY id DESC) as rn 
//    FROM DummyTable1
//),
//T2 AS (
//    /* ... 略 ... */
//)
//SELECT m.*, T1.*, T2.* FROM MainTable m
//LEFT JOIN T1 ON m.SerialNo = T1.SerialNo
//LEFT JOIN T2 ON m.SerialNo = T2.SerialNo
//";

// --- 2. 実行文（現場はこの3行） ---
/* 
$stmt = $pdo->prepare($sql);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
*/

// --- 1. ダミーデータ作成（10テーブル分のカラムがある状態） ---
$rows = [];
for ($i = 1; $i <= 5; $i++) {
    $data = [
        // MainTableのカラム
        'A-SerialNo' => "A-100{$i}",
        'B-SerialNo' => "B-200{$i}",
        'id' => $i, // 除外対象
        'delete_flg' => 0, // 除外対象
    ];

    // DummyTable1〜10のカラム（A〜Kなど）をループで追加
    for ($t = 1; $t <= 10; $t++) {
        $char = chr(64 + $t); // A, B, C...
        $data["Table{$t}_Col{$char}"] = "Data-{$t}-{$i}";
        $data["rn"] = 1; // PARTITION BYで出る連番（除外対象）
    }
    $rows[] = $data;
}

// --- 2. 除外したいカラム（ブラックリスト） ---
$blackList = ['Table3_ColC', 'Table5_ColE', 'Table8_ColH','id', 'delete_flgss'];

// --- 3. PHPで不要なカラムを削除（アスタリスクで取った後の処理） ---
foreach ($rows as &$row) {
    foreach ($blackList as $target) {
        unset($row[$target]);
    }
}
unset($row);
?>

<hr>
<h2>テーブルのサンプル</h2>
<p>PHPでダミーデータとして表示しています</p>
<table>
    <thead>
        <tr>
            <?php 
            if (!empty($rows)) {
                // カラム名（ヘッダー）を自動抽出
                foreach (array_keys($rows[0]) as $header) {
                    echo "<th style='background:green'>" . htmlspecialchars($header) . "</th>";
                }
            }
            ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <?php foreach ($row as $value): ?>
                    <td><?php echo htmlspecialchars($value); ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<hr>
<h2>tfpdfのサンプル</h2>
<p>jsonデータとしてPDFの中身を作成しています</p>
<a href="/index.php?page=createPDF">PDF作成</a>