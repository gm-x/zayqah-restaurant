<?php
require __DIR__ . '/app/bootstrap.php';

$u = require_user(false);
$reference = strtoupper(param('reference'));
$order = null;
$errors = [];

if ($reference !== '') {
    if (!limit('tracking:' . $u['id'], 240, 900)) {
        $errors[] = 'Too many tracking requests. Please wait a few minutes.';
    } elseif (!preg_match('/^[A-Z0-9-]{3,20}$/', $reference)) {
        $errors[] = 'Enter the full order number from your confirmation.';
    } else {
        $order = is_owner($u)
            ? one('SELECT * FROM orders WHERE reference = ?', [$reference])
            : one('SELECT * FROM orders WHERE reference = ? AND customer_id = ?', [$reference, $u['id']]);
        if (!$order) $errors[] = 'We could not find that order in your account.';
    }
}

if (param('format') === 'json') {
    if (!$order) json_out(['error' => $errors[0] ?? 'Order not found.'], 404);
    json_out([
        'status' => $order['status'],
        'html'   => tracking_html($order),
        'rider'  => $order['rider_latitude'] !== null ? [(float)$order['rider_latitude'], (float)$order['rider_longitude']] : null,
    ]);
}

$title = 'Track your order';
require ROOT . '/app/views/header.php';
?>
<main id="main" class="section">
    <div class="container narrow">
        <h1>Track your order</h1>
        <p class="lead">Enter the order number from your confirmation email, or open it from <a href="<?= e(url('orders.php')) ?>">My orders</a>.</p>
        <form class="card inline-form" method="get">
            <label class="sr-only" for="reference">Order number</label>
            <input id="reference" name="reference" required maxlength="20" placeholder="OR-XXXXXXXX" value="<?= e($reference) ?>">
            <button class="btn btn-primary" type="submit">Track</button>
        </form>
        <?php errors($errors); ?>
        <?php if ($order): ?>
            <div class="card" data-track data-track-url="<?= e(url('track-order.php?format=json&reference=' . rawurlencode($order['reference']))) ?>" data-status="<?= e($order['status']) ?>"><?= tracking_html($order) ?></div>
            <?php if ($order['fulfillment'] === 'delivery'): ?><div class="card"><?php order_map($order); ?></div><?php endif ?>
            <a class="btn btn-ghost" href="<?= e(url((is_owner($u) ? 'owner/' : '') . 'order.php?id=' . $order['id'])) ?>">View order details</a>
        <?php endif ?>
    </div>
</main>
<?php require ROOT . '/app/views/footer.php'; ?>
