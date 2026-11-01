<?php
require __DIR__ . '/../app/bootstrap.php';

require_owner();
$search = mb_substr(param('q'), 0, 60);
$where = "u.role = 'customer'";
$args = [];
if ($search !== '') {
    $where .= ' AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    array_push($args, "%$search%", "%$search%", "%$search%");
}
$customers = all("SELECT u.id, u.full_name, u.email, u.phone, u.verified_at, u.created_at,
        COUNT(o.id) AS order_count, COALESCE(SUM(CASE WHEN o.status = 'completed' THEN o.total_minor END), 0) AS spent, MAX(o.created_at) AS last_order
    FROM users u LEFT JOIN orders o ON o.customer_id = u.id WHERE $where GROUP BY u.id ORDER BY last_order IS NULL, last_order DESC, u.id DESC LIMIT 200", $args);
$title = 'Customers';

require ROOT . '/app/views/owner-start.php';
?>
<div class="owner-head"><div><h1>Customers</h1><p class="muted"><?= (int)scalar("SELECT COUNT(*) FROM users WHERE role = 'customer'") ?> registered customers</p></div></div>
<div class="toolbar"><form class="search-box" method="get"><?= icon('search', 16) ?><input type="search" name="q" value="<?= e($search) ?>" placeholder="Name, email or phone" aria-label="Search customers"></form></div>
<section class="card">
    <?php if (!$customers): ?><div class="empty-state small"><?= icon('users', 28) ?><p>No customers found.</p></div><?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Customer</th><th>Phone</th><th>Orders</th><th>Spent</th><th>Last order</th><th>Joined</th></tr></thead>
                <tbody>
                <?php foreach ($customers as $c): ?>
                    <tr>
                        <td><strong><?= e($c['full_name']) ?></strong><br><small class="muted"><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a><?= $c['verified_at'] ? '' : ' · unverified' ?></small></td>
                        <td><?= e($c['phone']) ?></td>
                        <td><?= (int)$c['order_count'] ?></td>
                        <td><?= money((int)$c['spent']) ?></td>
                        <td><?= e(local_time($c['last_order'], 'j M Y')) ?></td>
                        <td><?= e(local_time($c['created_at'], 'j M Y')) ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>
<?php require ROOT . '/app/views/owner-end.php'; ?>
