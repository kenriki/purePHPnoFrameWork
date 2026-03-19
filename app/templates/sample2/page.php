<?php
/**
 * 現場で使うSQL文の定義
 */
$sql = "
WITH T1 AS (
    SELECT *, ROW_NUMBER() OVER(PARTITION BY SerialNo ORDER BY id DESC) as rn 
    FROM DummyTable1
),
T2 AS (
    SELECT *, ROW_NUMBER() OVER(PARTITION BY SerialNo ORDER BY id DESC) as rn 
    FROM DummyTable2
)
SELECT m.*, T1.*, T2.* 
FROM MainTable m
LEFT JOIN T1 ON m.SerialNo = T1.SerialNo
LEFT JOIN T2 ON m.SerialNo = T2.SerialNo
";

/**
 * 【家でのテスト用】DB実行をシミュレートする関数
 * 現場では $stmt = $pdo->prepare($sql); $stmt->execute(); $rows = ... に置き換える部分
 */
function simulateDbExecute($sql) {
    $dummyRows = [];
    for ($i = 1; $i <= 5; $i++) {
        $data = [
            'A-SerialNo' => "A-100{$i}",
            'B-SerialNo' => "B-200{$i}",
            'id' => $i,
            'delete_flg' => 0,
        ];
        // 10テーブル分のカラム A~J を生成
        for ($t = 1; $t <= 10; $t++) {
            $char = chr(64 + $t);
            $data["Table{$t}_Col{$char}"] = "Data-{$t}-{$i}";
            $data["rn"] = 1; 
        }
        $dummyRows[] = $data;
    }
    return $dummyRows;
}

// 1. 実行（現場では $pdo を使うが、家ではシミュレーターを使用）
$rows = simulateDbExecute($sql);

// 2. 非表示にしたいカラム（ブラックリスト）
/* ============================================================
 *  ▼ 除外パターン（ブラックリスト方式）
 *  SELECT * で大量に取った後、不要なカラムだけ削除する方式
 *  → カラムが多い場合は管理が大変
 * ============================================================ */

$blackList = ['Table3_ColC', 'Table5_ColE', 'Table8_ColH', 'id', 'rn', 'delete_flg'];

// 3. PHPで不要なカラムを削除（アスタリスクで取った後の後処理）
foreach ($rows as &$row) {
    foreach ($blackList as $target) {
        if (array_key_exists($target, $row)) {
            unset($row[$target]);
        }
    }
}
unset($row);

// --- 4. 表示用のHTML ---
?>
<h3>除外したいカラムについて</h3>
<h4>除外したいカラムを除外して、SQL実行結果の画面流し込み（不要カラム削除済み）</h4>
<p>実行されたSQLの一部: <pre><code><?php echo htmlspecialchars(substr($sql, 0, 100)); ?>...</code></pre></p>

<table>
    <thead>
        <tr>
            <?php if (!empty($rows)): ?>
                <?php foreach (array_keys($rows[0]) as $header): ?>
                    <th><?php echo htmlspecialchars($header); ?></th>
                <?php endforeach; ?>
            <?php endif; ?>
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

<h3>表示したいカラムについて</h3>
<?php
// 2. 表示したいカラム（ホワイトリスト）
/* ============================================================
 *  ▼ 表示したいパターン（ホワイトリスト方式）
 *  必要なカラム名だけを指定し、それ以外はすべて除外する方式
 *  → カラムが多い場合はこちらの方が安全で管理しやすい
 * ============================================================ */

$whiteList = ['A-SerialNo', 'B-SerialNo', 'Table1_ColA', 'Table2_ColB'];

// 3. PHPでホワイトリストにあるカラムだけ残す
foreach ($rows as &$row) {
    $row = array_intersect_key($row, array_flip($whiteList));
}
unset($row);
?>
<table>
    <thead>
        <tr>
            <?php if (!empty($rows)): ?>
                <?php foreach (array_keys($rows[0]) as $header): ?>
                    <th><?php echo htmlspecialchars($header); ?></th>
                <?php endforeach; ?>
            <?php endif; ?>
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
