<?php
require __DIR__ . '/../app/bootstrap.php';

require_owner();
$statuses = ['available' => 'Available', 'sold_out' => 'Sold out', 'hidden' => 'Hidden'];

if (post()) {
    $item = one('SELECT * FROM menu_items WHERE id = ?', [(int)val('id')]);
    if ($item && val('action') === 'status' && isset($statuses[val('status')])) {
        q('UPDATE menu_items SET status = ?, updated_at = ? WHERE id = ?', [val('status'), now(), $item['id']]);
        flash($item['name'] . ' is now ' . strtolower($statuses[val('status')]) . '.');
    } elseif ($item && val('action') === 'feature') {
        q('UPDATE menu_items SET featured = 1 - featured, updated_at = ? WHERE id = ?', [now(), $item['id']]);
        flash($item['featured'] ? $item['name'] . ' removed from chef\'s picks.' : $item['name'] . ' added to chef\'s picks.');
    } elseif ($item && val('action') === 'delete') {
        q('DELETE FROM menu_items WHERE id = ?', [$item['id']]);
        flash($item['name'] . ' was deleted. Past orders keep their record of it.');
    }
    go('owner/menu.php?' . http_build_query(['category' => param('category'), 'status' => param('status')]));
}

$categoryFilter = (int)param('category');
$statusFilter = isset($statuses[param('status')]) ? param('status') : '';
$where = '1 = 1';
$args = [];
if ($categoryFilter) { $where .= ' AND m.category_id = ?'; $args[] = $categoryFilter; }
if ($statusFilter) { $where .= ' AND m.status = ?'; $args[] = $statusFilter; }
$items = all("SELECT m.*, c.name AS category_name, c.active AS category_active FROM menu_items m JOIN categories c ON c.id = m.category_id WHERE $where ORDER BY c.sort_order, c.name, m.sort_order, m.name", $args);
$categories = all('SELECT * FROM categories ORDER BY sort_order, name');
$title = 'Menu';

require ROOT . '/app/views/owner-start.php';
?>
<div class="owner-head">
    <div><h1>Menu</h1><p class="muted"><?= count($items) ?> dish<?= count($items) === 1 ? '' : 'es' ?></p></div>
    <a class="btn btn-primary" href="<?= e(url('owner/menu-edit.php')) ?>"><?= icon('plus', 18) ?> Add dish</a>
</div>
<form class="toolbar" method="get">
    <div class="row-gap">
        <select name="category" aria-label="Category" data-autosubmit>
            <option value="">All categories</option>
            <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $categoryFilter === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach ?>
        </select>
        <select name="status" aria-label="Status" data-autosubmit>
            <option value="">Any status</option>
            <?php foreach ($statuses as $key => $label): ?><option value="<?= e($key) ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach ?>
        </select>
    </div>
</form>
<section class="card">
    <?php if (!$items): ?>
        <div class="empty-state small"><?= icon('utensils', 28) ?><p>No dishes yet. Add your first dish to start taking orders.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table menu-table">
                <thead><tr><th>Dish</th><th>Category</th><th>Price</th><th>Availability</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <div class="dish-cell">
                                <img src="<?= e(image_url($item['photo_id'])) ?>" alt="" width="48" height="48">
                                <div><strong><?= e($item['name']) ?></strong>
                                    <small class="muted"><?= e(implode(' · ', array_keys(array_filter(['★ Chef\'s pick' => $item['featured'], 'Veg' => $item['vegetarian'], 'Spicy' => $item['spicy']])))) ?></small></div>
                            </div>
                        </td>
                        <td><?= e($item['category_name']) ?><?= $item['category_active'] ? '' : ' <small class="muted">(hidden)</small>' ?></td>
                        <td><?= money((int)$item['price_minor']) ?></td>
                        <td><small><?= e(availability_label($item)) ?></small></td>
                        <td>
                            <form method="post" class="inline">
                                <?= csrf() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                <select name="status" class="status-select status-<?= e($item['status']) ?>" aria-label="Status of <?= e($item['name']) ?>" data-autosubmit>
                                    <?php foreach ($statuses as $key => $label): ?><option value="<?= e($key) ?>" <?= $item['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach ?>
                                </select>
                            </form>
                        </td>
                        <td class="actions">
                            <form method="post" class="inline"><?= csrf() ?><input type="hidden" name="action" value="feature"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="icon-btn <?= $item['featured'] ? 'is-on' : '' ?>" type="submit" title="Chef's pick" aria-label="Toggle chef's pick"><?= icon('star', 18) ?></button></form>
                            <a class="icon-btn" href="<?= e(url('owner/menu-edit.php?id=' . $item['id'])) ?>" title="Edit" aria-label="Edit <?= e($item['name']) ?>"><?= icon('edit', 18) ?></a>
                            <form method="post" class="inline" data-confirm="Delete <?= e($item['name']) ?>? This cannot be undone."><?= csrf() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="icon-btn btn-danger-text" type="submit" title="Delete" aria-label="Delete <?= e($item['name']) ?>"><?= icon('trash', 18) ?></button></form>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>
<?php require ROOT . '/app/views/owner-end.php'; ?>
