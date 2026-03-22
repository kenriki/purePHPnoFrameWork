<form method="POST" action="index.php?page=survey">
    <h3>サイトの満足度を教えてください</h3>
    <input type="radio" name="rating" value="5" required> 満足
    <input type="radio" name="rating" value="3"> 普通
    <input type="radio" name="rating" value="1"> 不満
    <br>
    <textarea name="comment" placeholder="ご意見をお願いします"></textarea>
    <br>
    <button type="submit">回答を送信する</button>
</form>