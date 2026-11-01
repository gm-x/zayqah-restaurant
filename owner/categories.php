<?php
require __DIR__ . '/../app/bootstrap.php';

require_owner();
$errors = [];

if (post()) {
    $action = val('action');
    $id = (int)val('id');
    if ($action === 'save') {
        $name = val('name');
        $description = val('description');
        if (!text_valid($name, 2, 80)) $errors[] = 'Category names need 2–80 characters.';
        if (!text_valid($description, 0, 255)) $errors[] = 'Keep the description under 255 characters.';
        if (!$errors && one('SELECT id FROM categories WHERE name = ? AND id <> ?', [$name, $id])) $errors[] = 'A category with that name already exists.';
        if (!$errors) {
            if ($id) {
                q('UPDATE categories SET name = ?, description = ?, sort_order = ? WHERE id = ?', [$name, $description, (int)val('sort_order'), $id]);
                flash('Category updated.');
            } else {
                q('INSERT INTO categories (name, description, sort_order) VALUES (?, ?, ?)', [$name, $description, (int)scalar('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM categories')]);
                flash('Category added.');
            }
            go('owner/categories.php');
        }
    } elseif ($action === 'toggle') {
        q('UPDATE categories SET active = 1 - active WHERE id = ?', [$id]);
        flash('Category visibility changed.');
        go('owner/categories.php');
    } elseif ($action === 'delete') {
        if (scalar('SELECT COUNT(*) FROM menu_items WHERE category_id = ?', [$id])) {
            flash('Move or delete the dishes in this category first.', 'error');
        } else {
            q('DELETE FROM categories WHERE id = ?', [$id]);
            flash('Category deleted.');
        }
        go('owner/categories.php');
    }
}

$categories = all('SELECT c.*, (SELECT COUNT(*) FROM menu_items m WHERE m.category_id = c.id) AS item_count FROM categories c ORDER BY sort_order, name');
$editing = param('edit') !== '' ? one('SELECT * FROM categories WHERE id = ?', [(int)param('edit')]) : null;
$title = 'Categories';

require ROOT . '/app/views/owner-start.php';
?>
<div class="owner-head"><div><h1>Categories</h1><p class="muted">Group your menu into sections. Hidden categories hide all their dishes.</p></div></div>
<?php errors($errors); ?>
<div class="owner-grid">
    <section class="card">
        <?php if (!$categories): ?><div class="empty-state small"><p>No categories yet.</p></div><?php endif ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Order</th><th>Category</th><th>Dishes</th><th>Visible</th><th><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                <?php foreach ($categories as $c): ?>
                    <tr>
                        <td><?= (int)$c['sort_order'] ?></td>
                        <td><strong><?= e($c['name']) ?></strong><?php if ($c['description']): ?><br><small class="muted"><?= e($c['description']) ?></small><?php endif ?></td>
                        <td><a href="<?= e(url('owner/menu.php?category=' . $c['id'])) ?>"><?= (int)$c['item_count'] ?></a></td>
                        <td>
                            <form method="post" class="inline"><?= csrf() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                <button class="switch-btn <?= $c['active'] ? 'is-on' : '' ?>" type="submit" role="switch" aria-checked="<?= $c['active'] ? 'true' : 'false' ?>" aria-label="Show <?= e($c['name']) ?> on the menu"><span></span></button>
                            </form>
                        </td>
                        <td class="actions">
                            <a class="icon-btn" href="?edit=<?= (int)$c['id'] ?>" aria-label="Edit <?= e($c['name']) ?>"><?= icon('edit', 18) ?></a>
                            <form method="post" class="inline" data-confirm="Delete the <?= e($c['name']) ?> category?"><?= csrf() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="icon-btn btn-danger-text" type="submit" aria-label="Delete <?= e($c['name']) ?>" <?= $c['item_count'] ? 'disabled title="Has dishes"' : '' ?>><?= icon('trash', 18) ?></button></form>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </section>
    <section class="card">
        <h2><?= $editing ? 'Edit category' : 'Add a category' ?></h2>
        <form method="post">
            <?= csrf() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
            <?php field('name', 'Name', 'text', 'required minlength="2" maxlength="80"', old('name', $editing['name'] ?? '')); ?>
            <?php field('description', 'Short description (optional)', 'text', 'maxlength="255"', old('description', $editing['description'] ?? '')); ?>
            <?php if ($editing): ?><?php field('sort_order', 'Position', 'number', 'min="0" max="9999"', old('sort_order', $editing['sort_order']), 'Lower numbers appear first on the menu.'); ?><?php endif ?>
            <div class="row-gap">
                <button class="btn btn-primary" type="submit"><?= $editing ? 'Save' : 'Add category' ?></button>
                <?php if ($editing): ?><a class="btn btn-ghost" href="<?= e(url('owner/categories.php')) ?>">Cancel</a><?php endif ?>
            </div>
        </form>
    </section>
</div>
<?php require ROOT . '/app/views/owner-end.php'; ?>
