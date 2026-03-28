<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン</title>
    <style>
        body {
            font-family: "Helvetica Neue", Arial, "Hiragino Kaku Gothic ProN", "Hiragino Sans", Meiryo, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background: #f0f2f5;
            flex-direction: column;
        }

        .box {
            background: white;
            padding: 40px 30px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 320px;
            text-align: center;
        }

        h2 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 24px;
        }

        .form-group {
            text-align: left;
            margin-bottom: 15px;
        }

        label {
            display: block;
            font-size: 14px;
            margin-bottom: 5px;
            font-weight: bold;
        }

        /* 入力欄の基本スタイル */
        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 16px;
        }

        /* パスワード欄を包むコンテナ（👁の位置基準） */
        .input-container {
            position: relative;
            width: 100%;
        }

        /* 👁アイコンの配置 */
        .toggle-eye {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            user-select: none;
            color: #666;
            font-size: 18px;
        }

        /* ログインボタン（画像に合わせた濃い色） */
        button {
            width: 100%;
            padding: 12px;
            background: #333;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            margin-top: 10px;
        }

        button:hover {
            background: #000;
        }

        /* 下部のリンクエリア */
        .links {
            margin-top: 20px;
            font-size: 13px;
            line-height: 1.8;
        }

        .links a {
            color: #4b0082;
            /* 紫っぽい色 */
            text-decoration: none;
        }

        .links a:hover {
            text-decoration: underline;
        }

        footer {
            margin-top: 30px;
            font-size: 12px;
            color: #666;
            width: 100%;
            max-width: 380px;
            text-align: left;
        }
    </style>
</head>

<body>
    <div class="box">
        <h2>ログイン</h2>
        <form action="?page=login" method="POST">
            <div class="form-group">
                <label>ユーザー名</label>
                <input type="text" name="username" required>
            </div>

            <div class="form-group">
                <label>パスワード</label>
                <div class="input-container">
                    <input type="password" name="password" id="login_password" required>
                    <span class="toggle-eye" id="eye_icon" onclick="togglePassword()">👁</span>
                </div>
            </div>

            <button type="submit">ログイン</button>
        </form>

        <div class="links">
            アカウントをお持ちでない方は <a href="?page=register">新規登録</a><br>
            <a href="?page=reset">パスワードをお忘れの方はこちら</a>
        </div>
    </div>

    <footer>
        © 2026 Sample Site
    </footer>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('login_password');
            const eyeIcon = document.getElementById('eye_icon');

            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                eyeIcon.innerText = "🔓"; // 見えている時のアイコン（お好みで）
            } else {
                passwordInput.type = "password";
                eyeIcon.innerText = "👁";
            }
        }
    </script>
</body>

</html>