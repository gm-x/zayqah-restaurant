<?php
require __DIR__ . '/../app/bootstrap.php';

$u = require_owner();
$order = order_for((int)param('id'), $u);
$linkKey = 'rider_link_' . $order['id'];

if (post()) {
    $action = val('action');
    if ($action === 'status') {
        $error = change_order_status($order, val('status'));
        flash($error ?? 'Order updated: ' . status_label($order, val('status')) . '.', $error ? 'error' : 'success');
    } elseif ($action === 'rider-link' && $order['fulfillment'] === 'delivery' && in_array($order['status'], OPEN_STATUSES, true)) {
        $token = bin2hex(random_bytes(32));
        q('REPLACE INTO delivery_links (order_id, token_hash, expires_at) VALUES (?, ?, ?)', [$order['id'], hash('sha256', $token), gmdate('Y-m-d H:i:s', time() + 8 * 3600)]);
        $_SESSION[$linkKey] = $token; // the plain token is shown only to this session
        flash('Rider link created. Copy it and send it to your rider.');
    } elseif ($action === 'revoke-link') {
        q('DELETE FROM delivery_links WHERE order_id = ?', [$order['id']]);
        unset($_SESSION[$linkKey]);
        flash('Rider link revoked.');
    }
    go('owner/order.php?id=' . $order['id']);
}

$items = order_items((int)$order['id']);
$customer = one('SELECT id, email, created_at, (SELECT COUNT(*) FROM orders WHERE customer_id = users.id) AS order_count FROM users WHERE id = ?', [$order['customer_id']]);
$link = one('SELECT * FROM delivery_links WHERE order_id = ? AND expires_at > ?', [$order['id'], now()]);
$linkToken = $_SESSION[$linkKey] ?? '';
$showLink = $link && $linkToken && hash_equals($link['token_hash'], hash('sha256', $linkToken));
$next = ORDER_TRANSITIONS[$order['status']];
$title = 'Order ' . $order['reference'];
$ownerActive = 'orders.php';

require ROOT . '/app/views/owner-start.php';
?>
<a class="link-back" href="<?= e(url('owner/orders.php')) ?>"><?= icon('back', 16) ?> Orders</a>
<div class="owner-head">
    <div><h1><?= e($order['reference']) ?> <?= status_badge($order) ?> <?= payment_badge($order) ?></h1><p class="muted">Placed <?= e(local_time($order['created_at'])) ?> · <?= $order['fulfillment'] === 'delivery' ? 'Delivery' : 'Collection' ?></p></div>
    <div class="row-gap">
        <?php foreach ($next as $status): ?>
            <form method="post" <?= $status === 'cancelled' ? 'data-confirm="Cancel this order? The customer will be notified' . ($order['payment_status'] === 'paid' ? ' and refunded ' . e(money((int)$order['total_minor'])) : '') . '."' : '' ?>>
                <?= csrf() ?><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="<?= e($status) ?>">
                <button class="btn <?= $status === 'cancelled' ? 'btn-ghost btn-danger-text' : 'btn-primary' ?>" type="submit">
                    <?= e(match ($status) { 'confirmed' => 'Accept order', 'preparing' => 'Start preparing', 'ready' => $order['fulfillment'] === 'pickup' ? 'Ready for collection' : 'Send out for delivery', 'completed' => $order['fulfillment'] === 'pickup' ? 'Mark collected' : 'Mark delivered', 'cancelled' => 'Cancel order', default => ucfirst($status) }) ?>
                </button>
            </form>
        <?php endforeach ?>
    </div>
</div>

<div class="owner-grid">
    <div class="stack">
        <section class="card"><h2>Items</h2><?php order_summary($order, $items); ?>
            <?php if ($order['note']): ?><div class="alert alert-info"><strong>Customer note:</strong> <?= nl2br(e($order['note'])) ?></div><?php endif ?>
        </section>
        <section class="card" data-track data-track-url="<?= e(url('track-order.php?format=json&reference=' . rawurlencode($order['reference']))) ?>" data-status="<?= e($order['status']) ?>"><?= tracking_html($order) ?></section>
    </div>
    <div class="stack">
        <section class="card">
            <h2>Customer</h2>
            <p><strong><?= e($order['customer_name']) ?></strong></p>
            <p><?= icon('phone', 15) ?> <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $order['customer_phone'])) ?>"><?= e($order['customer_phone']) ?></a></p>
            <?php if ($customer): ?>
                <p><?= icon('mail', 15) ?> <a href="mailto:<?= e($customer['email']) ?>"><?= e($customer['email']) ?></a></p>
                <p class="hint"><?= (int)$customer['order_count'] ?> order<?= (int)$customer['order_count'] === 1 ? '' : 's' ?> · customer since <?= e(local_time($customer['created_at'], 'M Y')) ?></p>
            <?php endif ?>
        </section>
        <?php if ($order['fulfillment'] === 'delivery'): ?>
            <section class="card">
                <h2>Delivery</h2>
                <p><?= nl2br(e($order['delivery_address'])) ?></p>
                <?php order_map($order); ?>
                <a class="btn btn-ghost btn-sm" href="https://www.google.com/maps/dir/?api=1&amp;destination=<?= e($order['latitude'] . ',' . $order['longitude']) ?>" target="_blank" rel="noopener noreferrer"><?= icon('external', 15) ?> Directions</a>
                <?php if (in_array($order['status'], OPEN_STATUSES, true)): ?>
                    <h3>Rider link</h3>
                    <p class="hint">A temporary page for your rider showing the address and letting them share their location. Expires after 8 hours or when the order closes.</p>
                    <?php if ($showLink): ?>
                        <div class="copy-field"><input type="text" readonly value="<?= e(url('delivery-location.php?token=' . $linkToken)) ?>" aria-label="Rider link" data-copy-source><button class="btn btn-ghost btn-sm" type="button" data-copy><?= icon('link', 15) ?> Copy</button></div>
                    <?php elseif ($link): ?>
                        <p class="hint">A link is active but can only be shown once. Create a new one to copy it again.</p>
                    <?php endif ?>
                    <div class="row-gap">
                        <form method="post"><?= csrf() ?><input type="hidden" name="action" value="rider-link"><button class="btn btn-ghost btn-sm" type="submit"><?= $link ? 'New link' : 'Create rider link' ?></button></form>
                        <?php if ($link): ?><form method="post"><?= csrf() ?><input type="hidden" name="action" value="revoke-link"><button class="btn btn-ghost btn-sm btn-danger-text" type="submit">Revoke</button></form><?php endif ?>
                    </div>
                <?php endif ?>
            </section>
        <?php endif ?>
    </div>
</div>
<?php require ROOT . '/app/views/owner-end.php'; ?>
