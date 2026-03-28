<div style="max-width: 300px; margin: 50px auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px;">
    <h2 style="text-align: center;">パスワード変更</h2>

    <form action="index.php?page=reset_password" method="POST" accept-charset="UTF-8">
        <div style="margin-bottom: 10px;">
            <label>メールアドレス</label>
            <input type="email" name="email" style="width: 100%; padding: 8px;" required>
        </div>

        <div style="margin-bottom: 10px; position: relative;">
            <label>現在のパスワード</label>
            <input type="password" id="current_password" name="current_password" style="width: 100%; padding: 8px;"
                required>
            <span onclick="togglePassword('current_password')"
                style="position: absolute; right: 10px; top: 35px; cursor: pointer;">👁</span>
        </div>

        <div style="margin-bottom: 10px; position: relative;">
            <label>新しいパスワード</label>
            <input type="password" id="new_password" name="new_password" style="width: 100%; padding: 8px;" required>
            <span onclick="togglePassword('new_password')"
                style="position: absolute; right: 10px; top: 35px; cursor: pointer;">👁</span>
        </div>

        <div style="margin-bottom: 15px; position: relative;">
            <label>新しいパスワード（確認）</label>
            <input type="password" id="confirm_password" name="confirm_password" style="width: 100%; padding: 8px;"
                required>
            <span onclick="togglePassword('confirm_password')"
                style="position: absolute; right: 10px; top: 35px; cursor: pointer;">👁</span>
        </div>

        <button type="submit"
            style="width: 100%; padding: 10px; background: #333; color: #fff; border: none; cursor: pointer;">
            パスワードを更新
        </button>
    </form>
</div>

<script>
    function togglePassword(id) {
        const input = document.getElementById(id);
        input.type = (input.type === "password") ? "text" : "password";
    }
</script>