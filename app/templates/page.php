<?php
// PageController から渡された $page['sections'] を順番に処理
foreach ($page['sections'] as $section): ?>

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

    <?php elseif ($section['type'] === 'php'): ?>
        <div class="php-content">
            <?php 
            // dbconfig.php で定義した TEMPLATE_PATH を使用
            $phpFile = TEMPLATE_PATH . $section['file'];
            if (file_exists($phpFile)) {
                include $phpFile;
            } else {
                echo "<p style='color:red;'>Error: ファイル「" . htmlspecialchars($section['file']) . "」が見つかりません。</p>";
            }
            ?>
        </div>

    <?php endif; ?>

<?php endforeach; ?>