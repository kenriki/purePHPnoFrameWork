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
                <div>
                    <button type="button" id="quickTodoSubmitBtn"
                        style="background-color: #00b0ff; color: #ffffff; border: none; border-radius: 20px; padding: 8px 24px; font-weight: bold; font-size: 0.95rem; cursor: pointer; box-shadow: 0 2px 5px rgba(0, 176, 255, 0.3);">
                        追加
                    </button>
                </div>
            </div>
        </form>
    </div>
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