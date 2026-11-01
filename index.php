<?php
require __DIR__ . '/app/bootstrap.php';

$title = setting('tagline');
$bodyClass = 'home';
$slides = all('SELECT * FROM slides WHERE active = 1 ORDER BY sort_order, id');
$featured = visible_items('m.featured = 1', [], 'm.sort_order, m.name');
if (!$featured) $featured = visible_items('1 = 1', [], 'm.updated_at DESC');
$featured = array_slice($featured, 0, 6);
$categories = menu_categories();
$status = opening_status();
$heroHeading = $slides[0]['heading'] ?? setting('tagline');
$heroText = $slides[0]['subheading'] ?? 'Freshly prepared in our kitchen every day. Order online for collection or delivery.';

require ROOT . '/app/views/header.php';
?>
<main id="main">
    <section class="hero">
        <div class="container hero-grid">
            <div class="hero-copy">
                <span class="open-pill <?= $status['open'] ? 'is-open' : '' ?>"><?= icon('clock', 15) ?> <?= e($status['label']) ?></span>
                <h1 data-hero-heading><?= e($heroHeading) ?></h1>
                <p class="lead" data-hero-text><?= e($heroText) ?></p>
                <div class="hero-actions">
                    <a class="btn btn-primary btn-lg" href="<?= e(url('menu.php')) ?>">View menu &amp; order <?= icon('arrow', 18) ?></a>
                    <a class="btn btn-ghost btn-lg" href="<?= e(url('contact.php')) ?>">Find us</a>
                </div>
                <ul class="hero-facts">
                    <?php if (setting('pickup_enabled') === '1'): ?><li><?= icon('bag', 18) ?> Collection</li><?php endif ?>
                    <?php if (setting('delivery_enabled') === '1'): ?><li><?= icon('truck', 18) ?> Delivery <?= (int)setting('delivery_fee') ? money((int)setting('delivery_fee')) : 'free' ?></li><?php endif ?>
                    <li><?= icon('clock', 18) ?> Ready in ~<?= (int)setting('prep_minutes') ?> min</li>
                </ul>
            </div>
            <div class="hero-visual" <?php if (count($slides) > 1): ?>data-carousel aria-roledescription="carousel" aria-label="Featured"<?php endif ?>>
                <?php if ($slides): ?>
                    <?php foreach ($slides as $i => $slide): ?>
                        <figure class="hero-slide <?= $i ? '' : 'is-active' ?>" data-heading="<?= e($slide['heading']) ?>" data-text="<?= e($slide['subheading']) ?>" aria-roledescription="slide" aria-label="<?= $i + 1 ?> of <?= count($slides) ?>">
                            <img src="<?= e(image_url($slide['photo_id'])) ?>" alt="<?= e($slide['heading']) ?>" <?= $i ? 'loading="lazy"' : 'fetchpriority="high"' ?>>
                        </figure>
                    <?php endforeach ?>
                    <?php if (count($slides) > 1): ?>
                        <div class="carousel-dots">
                            <?php foreach ($slides as $i => $slide): ?>
                                <button type="button" data-slide="<?= $i ?>" aria-label="Show slide <?= $i + 1 ?>" <?= $i ? '' : 'aria-current="true"' ?>></button>
                            <?php endforeach ?>
                        </div>
                    <?php endif ?>
                <?php else: ?>
                    <figure class="hero-slide is-active"><img src="https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=1200&q=80" alt="Inside our restaurant" fetchpriority="high"></figure>
                <?php endif ?>
            </div>
        </div>
    </section>

    <?php if ($categories): ?>
        <section class="section section-tight">
            <div class="container">
                <div class="category-strip">
                    <?php foreach ($categories as $c): ?>
                        <a class="category-pill" href="<?= e(url('menu.php#cat-' . $c['id'])) ?>"><?= e($c['name']) ?></a>
                    <?php endforeach ?>
                </div>
            </div>
        </section>
    <?php endif ?>

    <?php if ($featured): ?>
        <section class="section">
            <div class="container">
                <div class="section-head">
                    <div><span class="eyebrow">From our kitchen</span><h2>Chef's recommendations</h2></div>
                    <a class="link-arrow" href="<?= e(url('menu.php')) ?>">Full menu <?= icon('arrow', 16) ?></a>
                </div>
                <div class="dish-grid">
                    <?php foreach ($featured as $item) require ROOT . '/app/views/dish-card.php'; ?>
                </div>
            </div>
        </section>
    <?php endif ?>

    <section class="section section-alt">
        <div class="container split">
            <div>
                <span class="eyebrow">Our story</span>
                <h2>Cooked fresh, served with care</h2>
                <?php foreach (array_slice(preg_split('/\n\s*\n/', setting('about')), 0, 2) as $paragraph): ?>
                    <p><?= e($paragraph) ?></p>
                <?php endforeach ?>
                <a class="btn btn-ghost" href="<?= e(url('about.php')) ?>">More about us</a>
            </div>
            <div class="info-cards">
                <div class="info-card"><?= icon('utensils', 22) ?><strong>Made to order</strong><p>Every dish is cooked when you order it, never reheated.</p></div>
                <div class="info-card"><?= icon('leaf', 22) ?><strong>Fresh ingredients</strong><p>Produce delivered daily and allergens listed on every dish.</p></div>
                <div class="info-card"><?= icon('truck', 22) ?><strong>Collect or deliver</strong><p>Pick up from the restaurant or have it brought to your door.</p></div>
                <div class="info-card"><?= icon('clock', 22) ?><strong>Live tracking</strong><p>Follow your order from our kitchen until it reaches you.</p></div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container cta-band">
            <div>
                <h2>Hungry? We're ready when you are.</h2>
                <p><?= e(setting('address')) ?> · <?= e(setting('phone')) ?></p>
            </div>
            <a class="btn btn-light btn-lg" href="<?= e(url('menu.php')) ?>">Start your order <?= icon('arrow', 18) ?></a>
        </div>
    </section>
</main>
<?php require ROOT . '/app/views/footer.php'; ?>
