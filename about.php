<?php
require __DIR__ . '/app/bootstrap.php';

$title = 'About us';
$paragraphs = array_filter(array_map('trim', preg_split('/\n\s*\n/', setting('about'))));
$dishCount = (int)scalar("SELECT COUNT(*) FROM menu_items WHERE status <> 'hidden'");

require ROOT . '/app/views/header.php';
?>
<main id="main">
    <section class="page-hero">
        <div class="container"><span class="eyebrow">Our story</span><h1>About <?= e(setting('name')) ?></h1><p class="lead"><?= e(setting('tagline')) ?>.</p></div>
    </section>
    <section class="section section-tight">
        <div class="container split">
            <div class="prose">
                <?php foreach ($paragraphs as $p): ?><p><?= nl2br(e($p)) ?></p><?php endforeach ?>
                <a class="btn btn-primary" href="<?= e(url('menu.php')) ?>">Explore the menu</a>
            </div>
            <div class="info-cards">
                <div class="info-card"><strong class="stat"><?= $dishCount ?></strong><p>dishes on our menu</p></div>
                <div class="info-card"><strong class="stat">~<?= (int)setting('prep_minutes') ?> min</strong><p>average preparation time</p></div>
                <div class="info-card"><?= icon('pin', 22) ?><strong>Find us</strong><p><?= e(setting('address')) ?></p></div>
                <div class="info-card"><?= icon('phone', 22) ?><strong>Call us</strong><p><?= e(setting('phone')) ?></p></div>
            </div>
        </div>
    </section>
</main>
<?php require ROOT . '/app/views/footer.php'; ?>
