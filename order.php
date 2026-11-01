<?php
require __DIR__ . '/app/bootstrap.php';

$u = require_user();
$order = order_for((int)param('id'), $u);
if (is_owner($u) && (int)$order['customer_id'] !== (int)$u['id']) go('owner/order.php?id=' . $order['id']);

if (post() && val('action') === 'cancel') {
    // Customers may cancel only before the kitchen confirms the order.
    if ($order['status'] !== 'placed') {
        flash('This order has already been confirmed. Please call us to make changes.', 'error');
    } elseif (!change_order_status($order, 'cancelled')) {
        notify_owners('Order cancelled by customer', $order['reference'] . ' · ' . $order['customer_name'], 'owner/order.php?id=' . $order['id'], 'customer-cancel:' . $order['id']);
        flash('Your order was cancelled.');
    }
    go('order.php?id=' . $order['id']);
}

$items = order_items((int)$order['id']);
$reviewed = array_column(all('SELECT menu_item_id FROM dish_reviews WHERE user_id = ?', [$u['id']]), 'menu_item_id', 'menu_item_id');
$title = 'Order ' . $order['reference'];

require ROOT . '/app/views/header.php';
?>
<main id="main" class="section">
    <div class="container">
        <a class="link-back" href="<?= e(url('orders.php')) ?>"><?= icon('back', 16) ?> My orders</a>
        <?php if (param('placed') === '1'): ?>
            <div class="success-banner">
                <span class="success-icon"><?= icon('check', 28) ?></span>
                <div><h1>Thank you, <?= e(explode(' ', $order['customer_name'])[0]) ?>!</h1><p>We've received your order and will confirm it shortly. A copy has been sent to your email.</p></div>
            </div>
        <?php else: ?>
            <h1>Order <?= e($order['reference']) ?></h1>
        <?php endif ?>
        <div class="order-layout">
            <div>
                <div class="card" data-track data-track-url="<?= e(url('track-order.php?format=json&reference=' . rawurlencode($order['reference']))) ?>" data-status="<?= e($order['status']) ?>">
                    <?= tracking_html($order) ?>
                </div>
                <?php if ($order['fulfillment'] === 'delivery'): ?>
                    <div class="card"><h2>Delivery to</h2><p><?= nl2br(e($order['delivery_address'])) ?></p><?php order_map($order); ?></div>
                <?php else: ?>
                    <div class="card"><h2>Collect from</h2><p><?= e(setting('name')) ?><br><?= nl2br(e(setting('address'))) ?></p><p><a href="https://www.google.com/maps/search/?api=1&amp;query=<?= e(rawurlencode(setting('address'))) ?>" target="_blank" rel="noopener noreferrer"><?= icon('external', 15) ?> Open in maps</a></p></div>
                <?php endif ?>
            </div>
            <aside>
                <div class="card">
                    <h2>Summary</h2>
                    <p class="hint">Placed <?= e(local_time($order['created_at'])) ?></p>
                    <?php order_summary($order, $items); ?>
                    <?php if ($order['note']): ?><p><strong>Your note:</strong> <?= nl2br(e($order['note'])) ?></p><?php endif ?>
                    <?php if ($order['status'] === 'placed'): ?>
                        <form method="post" data-confirm="Cancel this order?" data-cancel-order><?= csrf() ?><input type="hidden" name="action" value="cancel"><button class="btn btn-ghost btn-block btn-danger-text" type="submit">Cancel order</button></form>
                    <?php endif ?>
                </div>
                <?php if ($order['status'] === 'completed'): ?>
                    <div class="card">
                        <h2>Enjoyed your meal?</h2>
                        <p class="hint">Tell others what you thought of each dish.</p>
                        <?php foreach ($items as $item): if (!$item['menu_item_id']) continue; ?>
                            <a class="list-link" href="<?= e(url('dish.php?id=' . $item['menu_item_id'] . '#reviews')) ?>"><?= e($item['name']) ?><span><?= isset($reviewed[$item['menu_item_id']]) ? 'Edit review' : 'Review' ?> <?= icon('arrow', 14) ?></span></a>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>
            </aside>
        </div>
    </div>
</main>
<?php require ROOT . '/app/views/footer.php'; ?>
