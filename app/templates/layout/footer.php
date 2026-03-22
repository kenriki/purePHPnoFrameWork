</main>
<footer>
    <p>&copy; <?= date('Y') ?> Sample Site</p>
</footer>
<script>
document.addEventListener("DOMContentLoaded", () => {
    const nav = document.querySelector(".scroll-nav ul");
    const leftBtn = document.querySelector(".nav-arrow.left");
    const rightBtn = document.querySelector(".nav-arrow.right");

    // スクロール量（px）
    const scrollAmount = 150;

    leftBtn.addEventListener("click", () => {
        nav.scrollBy({ left: -scrollAmount, behavior: "smooth" });
    });

    rightBtn.addEventListener("click", () => {
        nav.scrollBy({ left: scrollAmount, behavior: "smooth" });
    });
});
</script>
</body>
</html>