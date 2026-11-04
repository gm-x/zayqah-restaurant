<?php
require __DIR__ . '/app/bootstrap.php';

$u = require_user();
$title = 'My orders';
$orders = all('SELECT o.*, (SELECT SUM(quantity) FROM order_items WHERE order_id = o.id) AS item_count FROM orders o WHERE customer_id = ? ORDER BY id DESC LIMIT 100', [$u['id']]);

require ROOT . '/app/views/header.php';
?>
<main id="main" class="section">
    <div class="container narrow">
        <h1>My orders</h1>
        <?php if (!$orders): ?>
            <div class="empty-state"><?= icon('receipt', 36) ?><h2>No orders yet</h2><p>When you place an order it will appear here.</p><a class="btn btn-primary" href="<?= e(url('menu.php')) ?>">View menu</a></div>
        <?php endif ?>
        <div class="list">
            <?php foreach ($orders as $o): ?>
                <a class="list-row card" href="<?= e(url('order.php?id=' . $o['id'])) ?>">
                    <div>
                        <strong><?= e($o['reference']) ?></strong>
                        <small><?= e(local_time($o['created_at'])) ?> · <?= (int)$o['item_count'] ?> item<?= (int)$o['item_count'] === 1 ? '' : 's' ?> · <?= $o['fulfillment'] === 'delivery' ? 'Delivery' : 'Collection' ?></small>
                    </div>
                    <div class="list-row-end"><?= $o['payment_status'] === 'paid' || $o['payment_status'] === 'refunded' ? payment_badge($o) : '' ?><?= status_badge($o) ?><strong><?= money((int)$o['total_minor']) ?></strong></div>
                </a>
            <?php endforeach ?>
        </div>
    </div>
</main>
<?php require ROOT . '/app/views/footer.php'; ?>
