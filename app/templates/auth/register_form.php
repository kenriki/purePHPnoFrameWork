<div style="max-width: 300px; margin: 50px auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px; background: #fff;">
    <h2 style="text-align: center;">新規会員登録</h2>
    <form action="?page=register" method="POST">
        <div style="margin-bottom: 10px;">
            <label>ユーザー名</label>
            <input type="text" name="username" style="width: 100%; padding: 8px; box-sizing: border-box;" required>
        </div>

        <div class="form-group">
            <label>メールアドレス</label>
            <input type="email" name="email" required placeholder="example@mail.com">
        </div>
        
        <div style="margin-bottom: 10px; position: relative;">
            <label>パスワード</label>
            <input type="password" id="password" name="password" style="width: 100%; padding: 8px; padding-right: 40px; box-sizing: border-box;" required>
            <span id="togglePassword" style="position: absolute; right: 10px; top: 32px; cursor: pointer;">👁️</span>
        </div>

        <div style="margin-bottom: 15px; position: relative;">
            <label>パスワード（確認）</label>
            <input type="password" id="password_conf" name="password_conf" style="width: 100%; padding: 8px; padding-right: 40px; box-sizing: border-box;" required>
            <span id="togglePasswordConf" style="position: absolute; right: 10px; top: 32px; cursor: pointer;">👁️</span>
        </div>

        <button type="submit" style="width: 100%; padding: 10px; background: #28a745; color: #fff; border: none; cursor: pointer; border-radius: 4px;">
            登録する
        </button>
    </form>
    <p style="text-align: center; margin-top: 15px; font-size: 0.9em;">
        すでにお持ちの方は <a href="?page=login">ログイン</a>
    </p>
</div>

<script>
    // パスワード表示切り替えのJavaScript
    function setupToggle(buttonId, inputId) {
        const toggle = document.getElementById(buttonId);
        const input = document.getElementById(inputId);

        toggle.addEventListener('click', function() {
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            this.textContent = type === 'password' ? '👁️' : '🔒';
        });
    }

    setupToggle('togglePassword', 'password');
    setupToggle('togglePasswordConf', 'password_conf');
</script>