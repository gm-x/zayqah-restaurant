<?php
require __DIR__ . '/app/bootstrap.php';

$title = 'Menu';
$search = mb_substr(param('q'), 0, 80);
$vegOnly = param('veg') === '1';
$where = '1 = 1';
$args = [];
if ($search !== '') {
    $where .= ' AND (m.name LIKE ? OR m.description LIKE ? OR m.ingredients LIKE ?)';
    array_push($args, "%$search%", "%$search%", "%$search%");
}
if ($vegOnly) $where .= ' AND m.vegetarian = 1';

$sections = [];
foreach (visible_items($where, $args) as $item) $sections[$item['category_id']][] = $item;
$categories = array_values(array_filter(menu_categories(), fn($c) => isset($sections[$c['id']])));
$cart = cart_data();
$status = opening_status();

require ROOT . '/app/views/header.php';
?>
<main id="main">
    <section class="page-hero">
        <div class="container">
            <span class="eyebrow">Order online</span>
            <h1>Our menu</h1>
            <p class="lead">Prices include VAT. Allergen information is listed on every dish — ask us if you need help choosing.</p>
            <?php if (!ordering_open()): ?>
                <div class="alert alert-info"><?= icon('clock', 18) ?> <?= e($status['label']) ?>. You can browse the menu and place your order once we're open.</div>
            <?php endif ?>
        </div>
    </section>

    <div class="menu-toolbar">
        <div class="container">
            <form class="menu-search" method="get" role="search">
                <?= icon('search', 18) ?>
                <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search dishes or ingredients" aria-label="Search the menu">
                <label class="switch"><input type="checkbox" name="veg" value="1" <?= $vegOnly ? 'checked' : '' ?> data-autosubmit> Vegetarian</label>
                <?php if ($search !== '' || $vegOnly): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('menu.php')) ?>">Clear</a><?php endif ?>
            </form>
            <?php if ($categories): ?>
                <nav class="category-tabs" aria-label="Menu categories" data-scrollspy>
                    <?php foreach ($categories as $c): ?>
                        <a href="#cat-<?= (int)$c['id'] ?>"><?= e($c['name']) ?></a>
                    <?php endforeach ?>
                </nav>
            <?php endif ?>
        </div>
    </div>

    <div class="container menu-layout">
        <div class="menu-sections">
            <?php if (!$categories): ?>
                <div class="empty-state">
                    <?= icon('search', 32) ?>
                    <h2><?= $search !== '' || $vegOnly ? 'No dishes match your search' : 'Our menu is being prepared' ?></h2>
                    <p><?= $search !== '' || $vegOnly ? 'Try a different word or clear the filters.' : 'Please check back soon.' ?></p>
                </div>
            <?php endif ?>
            <?php foreach ($categories as $c): ?>
                <section class="menu-section" id="cat-<?= (int)$c['id'] ?>">
                    <div class="menu-section-head">
                        <h2><?= e($c['name']) ?></h2>
                        <?php if ($c['description']): ?><p><?= e($c['description']) ?></p><?php endif ?>
                    </div>
                    <div class="dish-grid dish-grid-2">
                        <?php foreach ($sections[$c['id']] as $item) require ROOT . '/app/views/dish-card.php'; ?>
                    </div>
                </section>
            <?php endforeach ?>
        </div>
        <aside class="order-panel" data-cart-panel>
            <?php require ROOT . '/app/views/cart-panel.php'; ?>
        </aside>
    </div>
</main>
<?php require ROOT . '/app/views/footer.php'; ?>
