<?php foreach ($page['sections'] as $section): ?>

    <?php if ($section['type'] === 'text'): ?>
        <p><?= nl2br(htmlspecialchars($section['content'])) ?></p>

    <?php elseif ($section['type'] === 'hero'): ?>
        <section class="hero">
            <h2><?= htmlspecialchars($section['headline']) ?></h2>
            <p><?= htmlspecialchars($section['subtext']) ?></p>
        </section>

    <?php elseif ($section['type'] === 'sample'): ?>
        <section class="sample">
            <h2><?= htmlspecialchars($section['headline']) ?></h2>
            <p><?= htmlspecialchars($section['subtext']) ?></p>
        </section>

    <?php elseif ($section['type'] === 'notFound'): ?>
        <section class="not-found">
            <h2><?= htmlspecialchars($section['headline']) ?></h2>
            <p><?= htmlspecialchars($section['subtext']) ?></p>
        </section>

    <?php endif; ?>

<?php endforeach; ?>