<div style="max-width: 300px; margin: 50px auto; padding: 20px;">
  <h2 style="text-align: center;">新しいパスワード設定</h2>

  <form action="index.php?page=forgot_password_reset" method="POST">
    <input type="hidden" name="email" value="<?= htmlspecialchars($_GET['email'] ?? '', ENT_QUOTES) ?>">

    <div style="margin-bottom: 10px;">
      <label>新しいパスワード</label>
      <input type="password" name="new_password" style="width: 100%;" required>
    </div>

    <div style="margin-bottom: 10px;">
      <label>新しいパスワード（確認）</label>
      <input type="password" name="confirm_password" style="width: 100%;" required>
    </div>

    <button type="submit" style="width: 100%; padding: 10px;">
      パスワードを更新
    </button>
  </form>
</div>