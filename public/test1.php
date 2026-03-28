<?php
// ★ ブレークポイントを置くならここ
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = $_POST["name"] ?? "";
    $price = (int) ($_POST["price"] ?? 0);
    $taxRate = (int) ($_POST["tax"] ?? 10);

    // 税額計算
    $result = calcTax($price, $taxRate);

    // 配列をオブジェクト（stdClass）にキャストする
    $obj = (object) $result;

    // ★ デバッグしやすい配列
    $result = [
        "name" => $name,
        "price" => $obj->price,
        "taxRate" => $taxRate,
        "taxAmount" => $obj->taxAmount,
        "total" => $obj->total
    ];

}
/**
 * Summary of calcTax
 * @param mixed $price
 * @param mixed $taxRate
 * @return array<float|mixed>
 */
function calcTax($price, $taxRate): array
{
    // 税額計算
    $taxAmount = floor($price * ($taxRate / 100));
    $total = $price + $taxAmount;
    // 配列にまとめて返す
    return [
        "price" => $price,
        "taxAmount" => $taxAmount,
        "total" => $total
    ];
}
?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <title>消費税計算</title>
</head>

<body>

    <h2>消費税計算フォーム</h2>

    <form method="post">
        <label>品名：<br>
            <input type="text" name="name" required>
        </label>
        <br><br>

        <label>金額：<br>
            <input type="number" name="price" required>
        </label>
        <br><br>

        <label>税率：<br>
            <select name="tax">
                <option value="8">8%</option>
                <option value="10" selected>10%</option>
            </select>
        </label>
        <br><br>

        <button type="submit">計算</button>
    </form>

    <?php if (!empty($result)): ?>
        <hr>
        <h3>計算結果</h3>
        <p>品名：<?= htmlspecialchars($result["name"]) ?></p>
        <p>金額：<?= $result["price"] ?> 円</p>
        <p>税率：<?= $result["taxRate"] ?>%</p>
        <p>税額：<?= $result["taxAmount"] ?> 円</p>
        <p><strong>合計：<?= $result["total"] ?> 円</strong></p>
    <?php endif; ?>

</body>

</html>