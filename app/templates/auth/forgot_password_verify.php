<div style="max-width: 300px; margin: 50px auto; padding: 20px;">
  <h2 style="text-align: center;">認証コード入力</h2>

  <form action="index.php?page=forgot_password_verify" method="POST">
    <input type="hidden" name="email" value="<?= htmlspecialchars($_GET['email'] ?? '', ENT_QUOTES) ?>">

    <div style="margin-bottom: 10px;">
      <label>認証コード（6桁）</label>
      <input type="text" name="code" maxlength="6" style="width: 100%;" required>
    </div>

    <button type="submit" style="width: 100%; padding: 10px;">
      認証する
    </button>
  </form>
</div>