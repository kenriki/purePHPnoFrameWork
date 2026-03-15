<h2><?= htmlspecialchars($page['sub_title']) ?></h2>

<?php foreach ($page['sections'] as $section): ?>

    <?php if ($section['type'] === 'hero'): ?>
        <section class="hero">
            <h3><?= htmlspecialchars($section['headline']) ?></h3>
            <p><?= htmlspecialchars($section['subtext']) ?></p>
        </section>

    <?php elseif ($section['type'] === 'text'): ?>
        <p><?= nl2br(htmlspecialchars($section['content'])) ?></p>

    <?php endif; ?>

<?php endforeach; ?>
<p>PDFを表示するには下のボタンを押してください。</p>

<a href="/index.php?page=createPDF&action=generate" target="_blank">
    インターネットとセキュリティのPDFを表示する
</a>

