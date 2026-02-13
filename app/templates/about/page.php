<h2><?= htmlspecialchars($page['title']) ?></h2>

<?php foreach ($page['sections'] as $section): ?>

    <?php if ($section['type'] === 'about'): ?>
        <section class="about">
            <h3><?= htmlspecialchars($section['headline']) ?></h3>
            <p><?= htmlspecialchars($section['subtext']) ?></p>
        </section>

    <?php elseif ($section['type'] === 'text'): ?>
        <p><?= nl2br(htmlspecialchars($section['content'])) ?></p>

    <?php endif; ?>

<?php endforeach; ?>