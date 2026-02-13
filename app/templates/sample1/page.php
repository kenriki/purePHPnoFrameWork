<?php
   // 1. ユーザーの手を取得（フォーム送信時のみ実行）
   $userSelect = isset($_POST['userSelect']) ? $_POST['userSelect'] : "";

   // 2. CPUの手を決定
   $ranNumber =  random_int(1, 3);
   $cpuHand = "";
   if($ranNumber == 1) $cpuHand = "グー";
   if($ranNumber == 2) $cpuHand = "チョキ";
   if($ranNumber == 3) $cpuHand = "パー";

   // 3. 勝敗判定ロジック
   $message = "";
   if ($userSelect !== "") {
       if ($userSelect === $cpuHand) {
           $message = "あいこです！";
       } elseif (
           ($userSelect === "グー" && $cpuHand === "チョキ") ||
           ($userSelect === "チョキ" && $cpuHand === "パー") ||
           ($userSelect === "パー" && $cpuHand === "グー")
       ) {
           $message = "あなたの勝ちです！";
       } else {
           $message = "あなたの負けです...";
       }
   }
?>
<!-- フォームの送信先を自分自身、メソッドをPOSTに指定 -->
<form action="" method="POST">
    <select name="userSelect">
        <option value="グー">グー</option>
        <option value="チョキ">チョキ</option>
        <option value="パー">パー</option>
    </select>
    <button type="submit">じゃんけんポン！</button>
</form>

<?php if ($userSelect !== ""): ?>
    <hr>
    <p>あなた: <?php echo htmlspecialchars($userSelect); ?></p>
    <p>CPU: <?php echo $cpuHand; ?></p>
    <h3>結果: <?php echo $message; ?></h3>
<?php endif; ?>
