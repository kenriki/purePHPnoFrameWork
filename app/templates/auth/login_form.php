<div style="max-width: 300px; margin: 50px auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px;">
    <h2 style="text-align: center;">ログイン</h2>
    <form action="?page=login" method="POST">
        <div style="margin-bottom: 10px;">
            <label>ユーザー名またはメールアドレス</label>
            <input type="text" name="username" style="width: 100%; padding: 8px;" required>
        </div>
        <div style="margin-bottom: 15px;">
            <label>パスワード</label>
            <input type="password" name="password" style="width: 100%; padding: 8px;" required>
        </div>
        <button type="submit"
            style="width: 100%; padding: 10px; background: #333; color: #fff; border: none; cursor: pointer;">
            ログイン
        </button>
    </form>
    <p style="text-align: center; margin-top: 15px; font-size: 0.9em;">
        アカウントをお持ちでない方は <a href="?page=register">新規登録</a>
    </p>
    <p style="text-align: center; margin-top: 10px; font-size: 0.9em;">
        <a href="index.php?page=forgot_password">パスワードをお忘れの方はこちら</a>
    </p>
</div>