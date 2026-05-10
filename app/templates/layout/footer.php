</main>

<style>
    /* フッターのデザイン調整 */
    footer {
        background-color: #f8f9fa;
        padding: 20px 10px;
        text-align: center;
        border-top: 1px solid #dee2e6;
        margin-top: 40px;
        font-size: 14px;
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
        font-size: 12px;
        color: #856404;
        background-color: #fff3cd;
        display: inline-block;
        padding: 2px 8px;
        border-radius: 4px;
        margin-top: 5px;
    }
</style>

<footer>
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