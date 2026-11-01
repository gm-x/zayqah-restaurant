<?php
$viewer = user();
$title ??= setting('tagline');
$current = basename($_SERVER['SCRIPT_NAME'] ?? '');
$cartCount = array_sum(array_map('intval', is_array($_SESSION['cart'] ?? null) ? $_SESSION['cart'] : []));
$unread = $viewer ? (int)scalar('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [$viewer['id']]) : 0;
$navLinks = ['index.php' => 'Home', 'menu.php' => 'Menu', 'about.php' => 'About', 'contact.php' => 'Contact'];
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · <?= e(setting('name')) ?></title>
    <meta name="description" content="<?= e(setting('name') . ' — ' . setting('tagline') . '. Order online for collection or delivery.') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(url('assets/vendor/leaflet/leaflet.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css?v=2')) ?>">
    <script defer src="<?= e(url('assets/vendor/leaflet/leaflet.js')) ?>"></script>
    <script defer src="<?= e(url('assets/js/app.js?v=2')) ?>"></script>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= e(url()) ?>">
            <?php if (setting('logo_id')): ?>
                <img src="<?= e(image_url(setting('logo_id'))) ?>" alt="" width="36" height="36">
            <?php else: ?>
                <span class="brand-mark" aria-hidden="true"><?= e(mb_substr(setting('name'), 0, 1)) ?></span>
            <?php endif ?>
            <span><?= e(setting('name')) ?></span>
        </a>
        <nav class="main-nav" aria-label="Main">
            <?php foreach ($navLinks as $path => $label): ?>
                <a href="<?= e(url($path)) ?>" <?= $current === $path ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
            <?php endforeach ?>
        </nav>
        <div class="header-actions">
            <?php if ($viewer): ?>
                <a class="icon-btn" href="<?= e(url('notifications.php')) ?>" aria-label="Notifications" data-notifications data-poll="<?= e(url('updates.php')) ?>">
                    <?= icon('bell') ?><span class="dot-count" data-unread <?= $unread ? '' : 'hidden' ?>><?= $unread > 99 ? '99+' : $unread ?></span>
                </a>
            <?php endif ?>
            <a class="icon-btn" href="<?= e(url('cart.php')) ?>" aria-label="Your order">
                <?= icon('bag') ?><span class="dot-count" data-cart-count <?= $cartCount ? '' : 'hidden' ?>><?= $cartCount ?></span>
            </a>
            <?php if ($viewer): ?>
                <details class="dropdown">
                    <summary class="avatar" aria-label="Account menu"><?= e(mb_strtoupper(mb_substr($viewer['full_name'], 0, 1))) ?></summary>
                    <div class="dropdown-panel">
                        <div class="dropdown-person"><strong><?= e($viewer['full_name']) ?></strong><small><?= e($viewer['email']) ?></small></div>
                        <?php if (is_owner($viewer)): ?>
                            <a class="dropdown-highlight" href="<?= e(url('owner/index.php')) ?>"><?= icon('dashboard', 16) ?> Restaurant dashboard</a>
                        <?php endif ?>
                        <a href="<?= e(url('orders.php')) ?>"><?= icon('receipt', 16) ?> My orders</a>
                        <a href="<?= e(url('notifications.php')) ?>"><?= icon('bell', 16) ?> Notifications</a>
                        <a href="<?= e(url('account.php')) ?>"><?= icon('settings', 16) ?> Account settings</a>
                        <form method="post" action="<?= e(url('logout.php')) ?>"><?= csrf() ?><button type="submit"><?= icon('logout', 16) ?> Sign out</button></form>
                    </div>
                </details>
            <?php else: ?>
                <a class="btn btn-ghost btn-sm hide-sm" href="<?= e(url('login.php')) ?>">Sign in</a>
            <?php endif ?>
            <a class="btn btn-primary btn-sm hide-sm" href="<?= e(url('menu.php')) ?>">Order online</a>
            <button class="icon-btn menu-toggle" type="button" aria-controls="mobile-nav" aria-expanded="false" aria-label="Open menu"><?= icon('menu') ?></button>
        </div>
    </div>
    <nav class="mobile-nav" id="mobile-nav" aria-label="Mobile" hidden>
        <?php foreach ($navLinks as $path => $label): ?>
            <a href="<?= e(url($path)) ?>" <?= $current === $path ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach ?>
        <a href="<?= e(url('track-order.php')) ?>">Track an order</a>
        <?php if (!$viewer): ?>
            <a href="<?= e(url('login.php')) ?>">Sign in</a>
            <a href="<?= e(url('signup.php')) ?>">Create an account</a>
        <?php endif ?>
        <a class="btn btn-primary" href="<?= e(url('menu.php')) ?>">Order online</a>
    </nav>
</header>
<?php if ($flash): ?>
    <div class="toast toast-<?= e($flash['type']) ?>" role="status" data-toast><?= icon($flash['type'] === 'error' ? 'x' : 'check', 18) ?><span><?= e($flash['message']) ?></span></div>
<?php endif ?>
<div class="toast" role="status" aria-live="polite" data-live-toast hidden></div>
