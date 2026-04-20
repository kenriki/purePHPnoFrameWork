<div
    style="max-width: 300px; margin: 50px auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px; background: #fff;">
    <h2 style="text-align: center;">新規会員登録</h2>
    <form action="?page=register" method="POST">
        <!-- ユーザー名 -->
        <div style="margin-bottom: 10px;">
            <label>ユーザー名</label>
            <input type="text" name="username" style="width: 100%; padding: 8px; box-sizing: border-box;" required
                placeholder="ユーザー名を入力">
        </div>

        <!-- メールアドレス -->
        <div style="margin-bottom: 10px;">
            <label>メールアドレス</label>
            <input type="email" name="email" style="width: 100%; padding: 8px; box-sizing: border-box;" required
                placeholder="example@mail.com">
        </div>

        <!-- パスワード -->
        <div style="margin-bottom: 10px; position: relative;">
            <label>パスワード</label>
            <input type="password" id="password" name="password"
                style="width: 100%; padding: 8px; padding-right: 40px; box-sizing: border-box;" required>
            <span id="togglePassword" style="position: absolute; right: 10px; top: 32px; cursor: pointer;">👁️</span>
        </div>

        <!-- パスワード（確認） -->
        <div style="margin-bottom: 15px; position: relative;">
            <label>パスワード（確認）</label>
            <input type="password" id="password_conf" name="password_conf"
                style="width: 100%; padding: 8px; padding-right: 40px; box-sizing: border-box;" required>
            <span id="togglePasswordConf"
                style="position: absolute; right: 10px; top: 32px; cursor: pointer;">👁️</span>
        </div>

        <!-- 登録ボタン -->
        <button type="submit"
            style="width: 100%; padding: 10px; background: #28a745; color: #fff; border: none; cursor: pointer; border-radius: 4px;">
            登録する
        </button>
    </form>

    <p style="text-align: center; margin-top: 15px; font-size: 0.9em;">
        すでにお持ちの方は <a href="?page=login">ログイン</a>
    </p>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.querySelector('form');
        const submitBtn = form.querySelector('button[type="submit"]');

        // 1. バリデーションルールと対象要素の定義
        const fields = {
            username: {
                el: form.querySelector('[name="username"]'),
                pattern: /^[a-zA-Z0-9]{4,}$/,
                msg: "英数字4文字以上で入力してください"
            },
            // 末尾が2〜4文字の英字であることを条件に加える
            email: {
                el: form.querySelector('[name="email"]'),
                // 正規表現を強化: ドメイン末尾（TLD）が2〜6文字の英字であることを必須に
                pattern: /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}$/,
                msg: "有効なメールアドレスを入力してください"
            },
            password: {
                el: document.getElementById('password'),
                pattern: /^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9]).{8,}$/,
                msg: "8文字以上かつ、英大文字・小文字・数字を含めてください"
            },
            confirm: {
                el: document.getElementById('password_conf'),
                msg: "パスワードが一致しません"
            }
        };

        // 2. エラーメッセージ表示用の要素を動的に生成
        Object.keys(fields).forEach(key => {
            const field = fields[key];
            const errorEl = document.createElement('div');
            errorEl.id = `err-${key}`;
            // デザインに合わせたスタイル設定
            errorEl.style.cssText = "color: red; font-size: 0.75em; margin-top: 2px; min-height: 1.2em; display: block;";
            field.el.parentNode.appendChild(errorEl);
        });

        /**
         * バリデーションのメインロジック
         */
        function validate() {
            let isAllValid = true;
            let isAllFilled = true;

            Object.keys(fields).forEach(key => {
                const field = fields[key];
                const val = field.el.value;
                const errorEl = document.getElementById(`err-${key}`);
                let isValid = true;

                if (val === "") {
                    // 未入力時はエラーを出さず、枠線をグレーに戻す
                    isValid = true;
                    isAllFilled = false;
                    errorEl.textContent = "";
                    field.el.style.borderColor = "#ccc";
                } else {
                    // 入力がある場合のチェック
                    if (key === 'confirm') {
                        // パスワード一致チェック
                        isValid = (val === fields.password.el.value);
                    } else {
                        // 正規表現チェック
                        isValid = field.pattern.test(val);
                    }

                    // UI更新
                    if (isValid) {
                        errorEl.textContent = "";
                        field.el.style.borderColor = "#28a745"; // 成功時は緑
                    } else {
                        errorEl.textContent = field.msg;
                        field.el.style.borderColor = "red"; // 失敗時は赤
                        isAllValid = false;
                    }
                }
            });

            // 3. ボタンの活性・非活性切り替え
            const canSubmit = isAllValid && isAllFilled;
            submitBtn.disabled = !canSubmit;
            submitBtn.style.opacity = canSubmit ? "1" : "0.5";
            submitBtn.style.cursor = canSubmit ? "pointer" : "not-allowed";
            submitBtn.style.background = canSubmit ? "#28a745" : "#6c757d"; // 色の変化で直感的に
        }

        /**
         * パスワード表示切り替え
         * @param {string} buttonId - 切り替えボタンのID
         * @param {string} inputId  - 入力フィールドのID
         */
        function setupToggle(buttonId, inputId) {
            const toggle = document.getElementById(buttonId);
            const input = document.getElementById(inputId);
            if (toggle && input) {
                toggle.addEventListener('click', function () {
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    this.textContent = type === 'password' ? '👁️' : '🔒';
                });
            }
        }

        // イベントリスナー登録
        Object.values(fields).forEach(field => {
            field.el.addEventListener('input', validate);
        });

        // パスワードトグル実行
        setupToggle('togglePassword', 'password');
        setupToggle('togglePasswordConf', 'password_conf');

        // 初期状態のチェックを実行
        validate();
    });

</script>