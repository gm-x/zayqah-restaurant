<?php
// Page for the delivery rider, opened from a temporary link the owner shares. No account is needed.
require __DIR__ . '/app/bootstrap.php';

$token = param('token');
$findOrder = fn() => preg_match('/^[a-f0-9]{64}$/', $token) ? one("SELECT o.* FROM delivery_links l JOIN orders o ON o.id = l.order_id
    WHERE l.token_hash = ? AND l.expires_at > ? AND o.fulfillment = 'delivery' AND o.status NOT IN ('completed', 'cancelled')", [hash('sha256', $token), now()]) : null;
$order = $findOrder();
if (!$order) fail(404, 'This rider link has expired, was revoked, or the order is closed.');
$errors = [];

if (post()) {
    $point = coordinates(val('latitude'), val('longitude'));
    if (!$point) $errors[] = 'Use your location or tap the map to choose a point.';
    if (!limit('rider:' . $order['id'], 60, 3600)) $errors[] = 'Location sharing is limited to 60 updates per hour.';
    if (!$errors) {
        q('UPDATE orders SET rider_latitude = ?, rider_longitude = ?, rider_updated_at = ? WHERE id = ?', [$point[0], $point[1], now(), $order['id']]);
        notify((int)$order['customer_id'], 'Your rider is on the way', 'Order ' . $order['reference'] . ' · see where they are', 'order.php?id=' . $order['id'], 'rider:' . $order['id']);
        flash('Location shared with the customer.');
        go('delivery-location.php?token=' . $token);
    }
}

$title = 'Delivery ' . $order['reference'];
$items = order_items((int)$order['id']);
require ROOT . '/app/views/header.php';
?>
<main id="main" class="section">
    <div class="container narrow">
        <span class="eyebrow">Delivery</span>
        <h1><?= e($order['reference']) ?></h1>
        <?php errors($errors); ?>
        <div class="card">
            <h2>Deliver to</h2>
            <p><strong><?= e($order['customer_name']) ?></strong> · <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $order['customer_phone'])) ?>"><?= e($order['customer_phone']) ?></a></p>
            <p><?= nl2br(e($order['delivery_address'])) ?></p>
            <?php order_map($order); ?>
            <a class="btn btn-primary" href="https://www.google.com/maps/dir/?api=1&amp;destination=<?= e($order['latitude'] . ',' . $order['longitude']) ?>" target="_blank" rel="noopener noreferrer"><?= icon('external', 16) ?> Navigate</a>
            <p class="hint">Collect <?= money((int)$order['total_minor']) ?> on delivery · <?= array_sum(array_column($items, 'quantity')) ?> items</p>
        </div>
        <div class="card">
            <h2>Share your location</h2>
            <p class="hint">This shares a single pin with the customer. It does not track you continuously.</p>
            <form method="post">
                <?= csrf() ?>
                <?php location_picker('', '', 'Your current position'); ?>
                <button class="btn btn-primary" type="submit">Share location</button>
            </form>
        </div>
    </div>
</main>
<?php require ROOT . '/app/views/footer.php'; ?>
