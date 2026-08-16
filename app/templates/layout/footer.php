</main>

<style>
    /* フッターのデザイン調整 */
    footer {
        background-color: #f8f9fa;
        text-align: center;
        border-top: 1px solid #dee2e6;
        margin-top: 10px;
        font-size: 10px;
        color: #6c757d;
    }

    .footer-links {
        margin-bottom: 10px;
    }

    .footer-links a {
        color: #007bff;
        text-decoration: none;
        margin: 0 10px;
    }

    .footer-links a:hover {
        text-decoration: underline;
    }

    .backup-notice {
        font-size: 9px;
        color: #856404;
        background-color: #fff3cd;
        display: inline-block;
        border-radius: 4px;
        margin-top: 5px;
    }
</style>

<footer>
    <!-- ▼ これから何をする？（ポップアップ通知付き） -->
    <?php if (($pageId ?? '') === 'home'): ?>
        <div
            style="background: #ffffff; border: 1px solid #e0e0e0; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
            <form id="quickTodoForm" style="display: flex; flex-direction: column; gap: 15px;">
                <!-- 新規追加であることを示すための隠しフィールド -->
                <input type="hidden" name="action" value="add">

                <!-- タスク内容入力 -->
                <div>
                    <input type="text" name="content" id="todoContentInput" placeholder="これから何をする？" required
                        style="width: 100%; border: none; border-bottom: 1px solid #e0e0e0; padding: 8px 0; font-size: 1rem; outline: none; background: transparent;">
                </div>

                <!-- 日付選択と追加ボタンの行 -->
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <input type="date" name="due_date" id="todoDateInput" value="<?php echo date('Y-m-d'); ?>"
                            style="border: 1px solid #dcdcdc; border-radius: 6px; padding: 6px 10px; font-size: 0.9rem; color: #555; outline: none;">
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <!-- ★ Google風の枠なしマイクアイコンボタン -->
                        <button type="button" id="micBtn" onclick="startQuickSpeech()" title="音声入力"
                            style="background: transparent; border: none; padding: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: background 0.2s;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#5f6368" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path>
                                <path d="M19 10v1a7 7 0 0 1-14 0v-1"></path>
                                <line x1="12" y1="19" x2="12" y2="23"></line>
                                <line x1="8" y1="23" x2="16" y2="23"></line>
                            </svg>
                        </button>
                        <div>
                            <button type="button" id="quickTodoSubmitBtn"
                                style="background-color: #00b0ff; color: #ffffff; border: none; border-radius: 20px; padding: 8px 24px; font-weight: bold; font-size: 0.95rem; cursor: pointer; box-shadow: 0 2px 5px rgba(0, 176, 255, 0.3);">
                                追加
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- 音声入力を動作させるためのJavaScriptスクリプト -->
    <script>
        function startQuickSpeech() {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

            if (!SpeechRecognition) {
                alert("お使いのブラウザは音声認識に対応していません。Google Chrome等をご利用ください。");
                return;
            }

            const recognition = new SpeechRecognition();
            recognition.lang = 'ja-JP';       // 日本語設定
            recognition.interimResults = true; // リアルタイム反映
            recognition.continuous = false;    // 短発話ごとに区切る

            const inputField = document.getElementById('todoContentInput');
            const micBtn = document.getElementById('micBtn');
            const micIconSvg = micBtn ? micBtn.querySelector('svg') : null;

            // 録音開始時
            recognition.onstart = function () {
                console.log("音声認識が開始されました。お話しください。");
                if (micIconSvg) micIconSvg.setAttribute('stroke', '#ea4335'); // Googleレッド
                if (micBtn) micBtn.style.background = '#fce8e6';
            };

            // 音声認識結果の取得
            recognition.onresult = function (event) {
                let transcript = '';
                for (let i = 0; i < event.results.length; ++i) {
                    transcript += event.results[i][0].transcript;
                }
                console.log("認識結果:", transcript);
                if (inputField) {
                    inputField.value = transcript;
                    inputField.dispatchEvent(new Event('input', { bubbles: true }));
                }
            };

            // エラー発生時
            recognition.onerror = function (event) {
                console.error("音声認識エラー詳細: ", event.error);

                // ★ no-speech（音声未検出）の場合はアラートを出さずに静かに終わる
                if (event.error === 'no-speech') {
                    console.log("音声が検出されませんでした。もう一度お試しください。");
                } else {
                    alert("音声認識エラーが発生しました: " + event.error);
                }
                resetMicButton();
            };

            // 録音終了時
            recognition.onend = function () {
                console.log("音声認識が終了しました。");
                resetMicButton();
            };

            function resetMicButton() {
                if (micIconSvg) micIconSvg.setAttribute('stroke', '#5f6368');
                if (micBtn) micBtn.style.background = 'transparent';
            }

            // 録音スタート
            try {
                recognition.start();
            } catch (e) {
                console.error("起動失敗: ", e);
            }
        }
    </script>
    <!-- ▼ ポップアップ（トースト通知）用のスタイルとスクリプト -->
    <div id="todoToast"
        style="display: none; position: fixed; bottom: 30px; right: 30px; background: #333; color: #fff; padding: 12px 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); z-index: 9999; font-size: 0.95rem; transition: opacity 0.3s ease;">
        タスクが追加されました。ホーム画面上部にあるTODOアイコンクリックで確認お願いします。
    </div>

    <script>
        document.getElementById('quickTodoSubmitBtn').addEventListener('click', function () {
            const contentInput = document.getElementById('todoContentInput');
            const dateInput = document.getElementById('todoDateInput');

            if (!contentInput.value.trim()) {
                alert('タスク内容を入力してください。');
                contentInput.focus();
                return;
            }

            const formData = {
                action: 'add',
                content: contentInput.value,
                due_date: dateInput.value
            };

            fetch('manage_todo.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(formData)
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // ポップアップを表示
                        const toast = document.getElementById('todoToast');
                        toast.style.display = 'block';

                        // 1.5秒後に画面をリロードして最新化
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        alert('タスクの追加に失敗しました。');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('通信エラーが発生しました。');
                });
        });
    </script>
    <div class="footer-links">
        <a href="privacy.php">プライバシーポリシー</a>
        <a href="https://kenriki.static.jp/" target="_blank" rel="noopener noreferrer">緊急時バックアップ(CSV/JSON)</a>
    </div>
    <p>&copy; <?= date('Y') ?> カレンダーメモアプリケーション</p>
    <div class="backup-notice">
        ※メインサーバー停止時は上記のバックアップサイトをご利用ください
    </div>
</footer>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        const nav = document.querySelector(".scroll-nav ul");
        const leftBtn = document.querySelector(".nav-arrow.left");
        const rightBtn = document.querySelector(".nav-arrow.right");

        // スクロールボタンが存在する場合のみ実行
        if (nav && leftBtn && rightBtn) {
            const scrollAmount = 150;

            leftBtn.addEventListener("click", () => {
                nav.scrollBy({ left: -scrollAmount, behavior: "smooth" });
            });

            rightBtn.addEventListener("click", () => {
                nav.scrollBy({ left: scrollAmount, behavior: "smooth" });
            });
        }
    });
</script>
</body>

</html>