<?php
require __DIR__ . '/../app/bootstrap.php';

$u = require_owner();

if (post() && val('action') === 'toggle-ordering') {
    $pausing = setting('accepting_orders') === '1';
    save_settings(['accepting_orders' => $pausing ? '0' : '1']);
    flash($pausing ? 'Online ordering paused.' : 'Online ordering is open again.');
    go('owner/index.php');
}

// "Today" is the restaurant's local day, converted to UTC for the query.
$dayStart = local_now()->setTime(0, 0)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
$weekStart = local_now()->setTime(0, 0)->modify('-6 days')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
// Unpaid online orders never reached the kitchen, so they are left out of the figures.
$sales = "SELECT COUNT(*) AS orders, COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN total_minor END), 0) AS revenue,
    COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_minor END), 0) AS paid_online FROM orders WHERE created_at >= ? AND status <> 'awaiting_payment'";
$today = one($sales, [$dayStart]);
$week = one($sales, [$weekStart]);
$active = all("SELECT * FROM orders WHERE status IN ('placed', 'confirmed', 'preparing', 'ready') ORDER BY FIELD(status, 'placed', 'confirmed', 'preparing', 'ready'), created_at");
$topDishes = all("SELECT i.name, SUM(i.quantity) AS qty, SUM(i.total_minor) AS revenue FROM order_items i JOIN orders o ON o.id = i.order_id
    WHERE o.created_at >= ? AND o.status <> 'cancelled' GROUP BY i.name ORDER BY qty DESC LIMIT 5", [$weekStart]);
$soldOut = (int)scalar("SELECT COUNT(*) FROM menu_items WHERE status = 'sold_out'");
$newMessages = (int)scalar("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'");
$title = 'Dashboard';

require ROOT . '/app/views/owner-start.php';
?>
<div class="owner-head">
    <div><h1>Good <?= (int)local_now()->format('G') < 12 ? 'morning' : ((int)local_now()->format('G') < 18 ? 'afternoon' : 'evening') ?>, <?= e(explode(' ', $u['full_name'])[0]) ?></h1><p class="muted"><?= e(local_now()->format('l j F')) ?></p></div>
    <form method="post"><?= csrf() ?><input type="hidden" name="action" value="toggle-ordering">
        <button class="btn <?= setting('accepting_orders') === '1' ? 'btn-ghost' : 'btn-primary' ?>" type="submit"><?= setting('accepting_orders') === '1' ? 'Pause online orders' : 'Resume online orders' ?></button>
    </form>
</div>

<div class="stat-grid" data-autorefresh="30">
    <div class="stat-card"><span>Orders today</span><strong><?= (int)$today['orders'] ?></strong></div>
    <div class="stat-card"><span>Sales today</span><strong><?= money((int)$today['revenue']) ?></strong><?php if ((int)$today['paid_online']): ?><small><?= money((int)$today['paid_online']) ?> paid online</small><?php endif ?></div>
    <div class="stat-card"><span>Last 7 days</span><strong><?= money((int)$week['revenue']) ?></strong><small><?= (int)$week['orders'] ?> order<?= (int)$week['orders'] === 1 ? '' : 's' ?></small></div>
    <div class="stat-card <?= $active ? 'is-hot' : '' ?>"><span>Open orders</span><strong><?= count($active) ?></strong></div>
</div>

<div class="owner-grid">
    <section class="card">
        <div class="card-head"><h2>Live orders</h2><a class="link-arrow" href="<?= e(url('owner/orders.php')) ?>">All orders <?= icon('arrow', 14) ?></a></div>
        <?php if (!$active): ?>
            <div class="empty-state small"><?= icon('receipt', 28) ?><p>No open orders right now. New orders appear here automatically.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Order</th><th>Customer</th><th>Type</th><th>Total</th><th>Status</th><th>Placed</th></tr></thead>
                    <tbody>
                    <?php foreach ($active as $o): ?>
                        <tr class="row-link" data-href="<?= e(url('owner/order.php?id=' . $o['id'])) ?>">
                            <td><a href="<?= e(url('owner/order.php?id=' . $o['id'])) ?>"><strong><?= e($o['reference']) ?></strong></a></td>
                            <td><?= e($o['customer_name']) ?></td>
                            <td><?= $o['fulfillment'] === 'delivery' ? icon('truck', 16) . ' Delivery' : icon('bag', 16) . ' Collection' ?></td>
                            <td><?= money((int)$o['total_minor']) ?> <?= $o['payment_status'] === 'paid' ? '<span class="badge badge-completed">Paid</span>' : '' ?></td>
                            <td><?= status_badge($o) ?></td>
                            <td><?= e(local_time($o['created_at'], 'g:i a')) ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </section>
    <div class="stack">
        <section class="card">
            <h2>Best sellers · 7 days</h2>
            <?php if (!$topDishes): ?><p class="muted">No sales yet this week.</p><?php endif ?>
            <ol class="rank-list">
                <?php foreach ($topDishes as $d): ?><li><span><?= e($d['name']) ?></span><b><?= (int)$d['qty'] ?> sold</b></li><?php endforeach ?>
            </ol>
        </section>
        <section class="card">
            <h2>Needs attention</h2>
            <a class="list-link" href="<?= e(url('owner/messages.php')) ?>">Unread messages<span><?= $newMessages ?> <?= icon('arrow', 14) ?></span></a>
            <a class="list-link" href="<?= e(url('owner/menu.php?status=sold_out')) ?>">Dishes marked sold out<span><?= $soldOut ?> <?= icon('arrow', 14) ?></span></a>
            <a class="list-link" href="<?= e(url('owner/settings.php#hours')) ?>">Opening hours<span><?= icon('arrow', 14) ?></span></a>
        </section>
    </div>
</div>
<?php require ROOT . '/app/views/owner-end.php'; ?>
