<?php
// セッションからユーザー名を取得（なければGuest）
$userName = $_SESSION['username'] ?? 'Guest';
?>

<h2>アンケート送信完了</h2>
<p><?= htmlspecialchars($userName) ?> さん、ご協力ありがとうございました！</p>
<p><a href="index.php?page=home">トップページへ戻る</a></p>