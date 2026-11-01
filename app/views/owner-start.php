<?php
$viewer = user();
$current = basename($_SERVER['SCRIPT_NAME'] ?? '');
$openOrders = (int)scalar("SELECT COUNT(*) FROM orders WHERE status IN ('placed', 'confirmed', 'preparing', 'ready')");
$newMessages = (int)scalar("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'");
$unread = (int)scalar('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [$viewer['id']]);
$ownerNav = [
    'index.php'      => ['dashboard', 'Dashboard', 0],
    'orders.php'     => ['receipt', 'Orders', $openOrders],
    'menu.php'       => ['utensils', 'Menu', 0],
    'categories.php' => ['tag', 'Categories', 0],
    'slides.php'     => ['image', 'Homepage', 0],
    'messages.php'   => ['inbox', 'Messages', $newMessages],
    'reviews.php'    => ['star', 'Dish reviews', 0],
    'customers.php'  => ['users', 'Customers', 0],
    'settings.php'   => ['settings', 'Restaurant settings', 0],
];
$activeNav = $ownerActive ?? $current;
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e($title ?? 'Dashboard') ?> · Owner · <?= e(setting('name')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(url('assets/vendor/leaflet/leaflet.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css?v=2')) ?>">
    <script defer src="<?= e(url('assets/vendor/leaflet/leaflet.js')) ?>"></script>
    <script defer src="<?= e(url('assets/js/app.js?v=2')) ?>"></script>
</head>
<body class="owner">
<a class="skip-link" href="#main">Skip to content</a>
<div class="owner-shell">
    <aside class="owner-sidebar" id="owner-nav">
        <a class="brand" href="<?= e(url('owner/index.php')) ?>"><span class="brand-mark" aria-hidden="true"><?= e(mb_substr(setting('name'), 0, 1)) ?></span><span><?= e(setting('name')) ?></span></a>
        <nav aria-label="Owner">
            <?php foreach ($ownerNav as $path => [$ico, $label, $count]): ?>
                <a href="<?= e(url('owner/' . $path)) ?>" <?= $activeNav === $path ? 'aria-current="page"' : '' ?>>
                    <?= icon($ico, 18) ?><span><?= e($label) ?></span><?php if ($count): ?><b class="nav-count"><?= $count ?></b><?php endif ?>
                </a>
            <?php endforeach ?>
        </nav>
        <div class="owner-sidebar-foot">
            <a href="<?= e(url()) ?>" target="_blank"><?= icon('external', 16) ?> View website</a>
            <form method="post" action="<?= e(url('logout.php')) ?>"><?= csrf() ?><button type="submit"><?= icon('logout', 16) ?> Sign out</button></form>
        </div>
    </aside>
    <div class="owner-main">
        <header class="owner-topbar">
            <button class="icon-btn owner-nav-toggle" type="button" aria-controls="owner-nav" aria-expanded="false" aria-label="Open navigation"><?= icon('menu') ?></button>
            <div class="owner-topbar-status">
                <?php $st = opening_status(); ?>
                <span class="open-pill <?= $st['open'] ? 'is-open' : '' ?>"><?= e($st['label']) ?></span>
            </div>
            <div class="header-actions">
                <a class="icon-btn" href="<?= e(url('notifications.php')) ?>" aria-label="Notifications" data-notifications data-poll="<?= e(url('updates.php')) ?>">
                    <?= icon('bell') ?><span class="dot-count" data-unread <?= $unread ? '' : 'hidden' ?>><?= $unread ?></span>
                </a>
                <span class="avatar" title="<?= e($viewer['full_name']) ?>"><?= e(mb_strtoupper(mb_substr($viewer['full_name'], 0, 1))) ?></span>
            </div>
        </header>
        <?php if ($flash): ?>
            <div class="toast toast-<?= e($flash['type']) ?>" role="status" data-toast><?= icon($flash['type'] === 'error' ? 'x' : 'check', 18) ?><span><?= e($flash['message']) ?></span></div>
        <?php endif ?>
        <div class="toast" role="status" aria-live="polite" data-live-toast hidden></div>
        <main id="main" class="owner-content">
