<?php
require __DIR__ . '/../app/bootstrap.php';

require_owner();
$tabs = ['active' => 'Active', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'all' => 'All'];
$tab = isset($tabs[param('tab')]) ? param('tab') : 'active';
$search = mb_substr(param('q'), 0, 60);
$page = max(1, (int)param('page'));

$where = match ($tab) {
    'active'    => "status IN ('placed', 'confirmed', 'preparing', 'ready')",
    'completed' => "status = 'completed'",
    'cancelled' => "status = 'cancelled'",
    default     => '1 = 1',
};
$args = [];
if ($search !== '') {
    $where .= ' AND (reference LIKE ? OR customer_name LIKE ? OR customer_phone LIKE ?)';
    array_push($args, "%$search%", "%$search%", "%$search%");
}
$total = (int)scalar("SELECT COUNT(*) FROM orders WHERE $where", $args);
$pages = max(1, (int)ceil($total / 25));
$order = $tab === 'active' ? 'created_at ASC' : 'created_at DESC';
$orders = all("SELECT o.*, (SELECT SUM(quantity) FROM order_items WHERE order_id = o.id) AS item_count FROM orders o WHERE $where ORDER BY $order LIMIT 25 OFFSET " . (($page - 1) * 25), $args);
$title = 'Orders';

require ROOT . '/app/views/owner-start.php';
?>
<div class="owner-head"><h1>Orders</h1></div>
<div class="toolbar">
    <nav class="tabs" aria-label="Order filters">
        <?php foreach ($tabs as $key => $label): ?>
            <a href="?tab=<?= e($key) ?>" <?= $tab === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach ?>
    </nav>
    <form class="search-box" method="get"><input type="hidden" name="tab" value="<?= e($tab) ?>"><?= icon('search', 16) ?><input type="search" name="q" value="<?= e($search) ?>" placeholder="Order number, name or phone" aria-label="Search orders"></form>
</div>
<section class="card" <?= $tab === 'active' && $search === '' ? 'data-autorefresh="30"' : '' ?>>
    <?php if (!$orders): ?>
        <div class="empty-state small"><?= icon('receipt', 28) ?><p><?= $tab === 'active' ? 'No open orders. New orders appear here automatically.' : 'No orders found.' ?></p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Order</th><th>Customer</th><th>Items</th><th>Type</th><th>Total</th><th>Payment</th><th>Status</th><th>Placed</th></tr></thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr class="row-link" data-href="<?= e(url('owner/order.php?id=' . $o['id'])) ?>">
                        <td><a href="<?= e(url('owner/order.php?id=' . $o['id'])) ?>"><strong><?= e($o['reference']) ?></strong></a></td>
                        <td><?= e($o['customer_name']) ?><br><small class="muted"><?= e($o['customer_phone']) ?></small></td>
                        <td><?= (int)$o['item_count'] ?></td>
                        <td><?= $o['fulfillment'] === 'delivery' ? 'Delivery' : 'Collection' ?></td>
                        <td><?= money((int)$o['total_minor']) ?></td>
                        <td><?= payment_badge($o) ?></td>
                        <td><?= status_badge($o) ?></td>
                        <td><?= e(local_time($o['created_at'], 'j M, g:i a')) ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>
<?php if ($pages > 1): ?>
    <nav class="pagination" aria-label="Pages">
        <?php if ($page > 1): ?><a class="btn btn-ghost btn-sm" href="?<?= e(http_build_query(['tab' => $tab, 'q' => $search, 'page' => $page - 1])) ?>">Previous</a><?php endif ?>
        <span>Page <?= $page ?> of <?= $pages ?></span>
        <?php if ($page < $pages): ?><a class="btn btn-ghost btn-sm" href="?<?= e(http_build_query(['tab' => $tab, 'q' => $search, 'page' => $page + 1])) ?>">Next</a><?php endif ?>
    </nav>
<?php endif ?>
<?php require ROOT . '/app/views/owner-end.php'; ?>
