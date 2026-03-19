<?php
/** 現場で使うSQL文の定義 */
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

/** 家でのテスト用 DB シミュレーター */
function simulateDbExecute($sql) {
    $dummyRows = [];
    for ($i = 1; $i <= 5; $i++) {
        $data = [
            'A-SerialNo' => "A-100{$i}",
            'B-SerialNo' => "B-200{$i}",
            'id' => $i,
            'delete_flg' => 0,
        ];
        for ($t = 1; $t <= 10; $t++) {
            $char = chr(64 + $t);
            $data["Table{$t}_Col{$char}"] = "Data-{$t}-{$i}";
            $data["rn"] = 1; 
        }
        $dummyRows[] = $data;
    }
    return $dummyRows;
}

// ★ 非表示にしたいカラム（ブラックリスト）
/* ============================================================
 *  ▼ 除外パターン（ブラックリスト方式）
 *  SELECT * で大量に取った後、不要なカラムだけ削除する方式
 *  → カラムが多い場合は管理が大変
 * ============================================================ */
$rowsBlack = simulateDbExecute($sql);

$blackList = ['Table3_ColC', 'Table5_ColE', 'Table8_ColH', 'id', 'rn', 'delete_flg'];

foreach ($rowsBlack as &$row) {
    foreach ($blackList as $target) {
        unset($row[$target]);
    }
}
unset($row);

// ★ 表示したいカラム（ホワイトリスト）
/* ============================================================
 *  ▼ 表示したいパターン（ホワイトリスト方式）
 *  必要なカラム名だけを指定し、それ以外はすべて除外する方式
 *  → カラムが多い場合はこちらの方が安全で管理しやすい
 * ============================================================ */
$rowsWhite = simulateDbExecute($sql);

$whiteList = ['A-SerialNo', 'B-SerialNo', 'Table1_ColA', 'Table2_ColB'];

foreach ($rowsWhite as &$row) {
    $row = array_intersect_key($row, array_flip($whiteList));
}
unset($row);
?>

<h3>ブラックリスト版（除外したいカラムを削除）</h3>
<?php if (!empty($rowsBlack)): ?>
<table class="dataTable">
    <thead>
        <!-- 1行目：カラム名 -->
        <tr>
            <?php foreach (array_keys($rowsBlack[0]) as $header): ?>
                <th class="sortable"><?php echo htmlspecialchars($header); ?></th>
            <?php endforeach; ?>
        </tr>
        <!-- 2行目：検索窓 -->
        <tr>
            <?php foreach (array_keys($rowsBlack[0]) as $header): ?>
                <th>
                    <input
                        type="text"
                        class="filter-input"
                        placeholder="検索"
                        style="width: 90%;"
                    >
                </th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rowsBlack as $row): ?>
            <tr>
                <?php foreach ($row as $value): ?>
                    <td><?php echo htmlspecialchars($value); ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<hr>

<h3>ホワイトリスト版（表示したいカラムだけ）</h3>
<?php if (!empty($rowsWhite)): ?>
<table class="dataTable">
    <thead>
        <!-- 1行目：カラム名 -->
        <tr>
            <?php foreach (array_keys($rowsWhite[0]) as $header): ?>
                <th class="sortable"><?php echo htmlspecialchars($header); ?></th>
            <?php endforeach; ?>
        </tr>
        <!-- 2行目：検索窓 -->
        <tr>
            <?php foreach (array_keys($rowsWhite[0]) as $header): ?>
                <th>
                    <input
                        type="text"
                        class="filter-input"
                        placeholder="検索"
                        style="width: 90%;"
                    >
                </th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rowsWhite as $row): ?>
            <tr>
                <?php foreach ($row as $value): ?>
                    <td><?php echo htmlspecialchars($value); ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<script>
document.addEventListener("DOMContentLoaded", () => {

    // 複数テーブル対応
    document.querySelectorAll(".dataTable").forEach(table => {
        const tbody = table.querySelector("tbody");
        const headerCells = table.querySelectorAll("thead tr:first-child th");
        const filterInputs = table.querySelectorAll(".filter-input");

        // ▼ フィルタ
        filterInputs.forEach((input, colIndex) => {
            input.addEventListener("input", () => {
                const filters = Array.from(filterInputs).map(i => i.value.trim().toLowerCase());

                Array.from(tbody.querySelectorAll("tr")).forEach(row => {
                    let visible = true;

                    Array.from(row.children).forEach((cell, idx) => {
                        const keyword = filters[idx];
                        if (keyword && !cell.innerText.toLowerCase().includes(keyword)) {
                            visible = false;
                        }
                    });

                    row.style.display = visible ? "" : "none";
                });
            });
        });

        // ▼ ソート
        headerCells.forEach((th, colIndex) => {
            th.style.cursor = "pointer";

            th.addEventListener("click", () => {
                const rows = Array.from(tbody.querySelectorAll("tr"));
                const asc = th.dataset.sortOrder !== "asc";
                th.dataset.sortOrder = asc ? "asc" : "desc";

                headerCells.forEach(h => {
                    if (h !== th) h.textContent = h.textContent.replace(/[▲▼]/g, "");
                });

                th.textContent = th.textContent.replace(/[▲▼]/g, "") + (asc ? " ▲" : " ▼");

                rows.sort((a, b) => {
                    const A = a.children[colIndex].innerText.trim();
                    const B = b.children[colIndex].innerText.trim();

                    if (!isNaN(A) && !isNaN(B)) return asc ? A - B : B - A;
                    if (!isNaN(Date.parse(A)) && !isNaN(Date.parse(B)))
                        return asc ? new Date(A) - new Date(B) : new Date(B) - new Date(A);

                    return asc ? A.localeCompare(B) : B.localeCompare(A);
                });

                rows.forEach(row => tbody.appendChild(row));
            });
        });
    });

});
</script>