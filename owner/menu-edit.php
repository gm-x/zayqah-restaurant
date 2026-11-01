<?php
require __DIR__ . '/../app/bootstrap.php';

require_owner();
$item = param('id') !== '' ? one('SELECT * FROM menu_items WHERE id = ?', [(int)param('id')]) : null;
if (param('id') !== '' && !$item) fail(404, 'Dish not found.');
$categories = all('SELECT * FROM categories ORDER BY sort_order, name');
$errors = [];

if (post()) {
    $name = val('name');
    $description = val('description');
    $ingredients = val('ingredients');
    $price = parse_money(val('price'));
    $categoryId = (int)val('category_id');
    $status = val('status');
    $days = 0;
    foreach (is_array($_POST['days'] ?? null) ? $_POST['days'] : [] as $day) {
        if (is_string($day) && isset(WEEKDAYS[(int)$day])) $days |= 1 << ((int)$day - 1);
    }
    $limited = val('time_limited') === '1';
    $from = $limited ? val('available_from') : '';
    $until = $limited ? val('available_until') : '';

    if (!text_valid($name, 2, 120)) $errors[] = 'Enter a dish name (2–120 characters).';
    if (!text_valid($description, 10, 3000)) $errors[] = 'Describe the dish in 10–3000 characters.';
    if (!text_valid($ingredients, 3, 2000)) $errors[] = 'List the ingredients and allergens.';
    if ($price === null || $price < 1) $errors[] = 'Enter a valid price.';
    if (!one('SELECT id FROM categories WHERE id = ?', [$categoryId])) $errors[] = 'Choose a category.';
    if (!in_array($status, ['available', 'sold_out', 'hidden'], true)) $errors[] = 'Choose a status.';
    if (!$days) $errors[] = 'Choose at least one day the dish is served.';
    if ($limited && (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $from) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $until) || $from === $until)) {
        $errors[] = 'Choose a valid serving time window.';
    }

    if (!$errors) {
        try {
            db()->beginTransaction();
            $photo = upload('photo', 'menu', false) ?? ($item['photo_id'] ?? null);
            if (val('remove_photo') === '1' && empty($_FILES['photo']['size'])) $photo = null;
            $values = [$categoryId, $photo, $name, $description, $ingredients, $price, $status,
                val('featured') === '1' ? 1 : 0, val('vegetarian') === '1' ? 1 : 0, val('spicy') === '1' ? 1 : 0,
                $days, $from ?: null, $until ?: null, (int)val('sort_order'), now()];
            if ($item) {
                q('UPDATE menu_items SET category_id = ?, photo_id = ?, name = ?, description = ?, ingredients = ?, price_minor = ?, status = ?,
                   featured = ?, vegetarian = ?, spicy = ?, available_days = ?, available_from = ?, available_until = ?, sort_order = ?, updated_at = ? WHERE id = ?',
                    [...$values, $item['id']]);
            } else {
                q('INSERT INTO menu_items (category_id, photo_id, name, description, ingredients, price_minor, status, featured, vegetarian, spicy,
                   available_days, available_from, available_until, sort_order, updated_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [...$values, now()]);
            }
            db()->commit();
            flash($item ? 'Dish updated.' : 'Dish added to the menu.');
            go('owner/menu.php');
        } catch (InvalidArgumentException $ex) {
            if (db()->inTransaction()) db()->rollBack();
            $errors[] = $ex->getMessage();
        }
    }
}

$daysMask = is_post() ? array_reduce(is_array($_POST['days'] ?? null) ? $_POST['days'] : [], fn($m, $d) => $m | (1 << ((int)$d - 1)), 0) : (int)($item['available_days'] ?? 127);
$isLimited = is_post() ? val('time_limited') === '1' : !empty($item['available_from']);
$checked = fn(string $key) => (is_post() ? val($key) === '1' : !empty($item[$key])) ? 'checked' : '';
$title = $item ? 'Edit ' . $item['name'] : 'Add a dish';
$ownerActive = 'menu.php';

require ROOT . '/app/views/owner-start.php';
?>
<a class="link-back" href="<?= e(url('owner/menu.php')) ?>"><?= icon('back', 16) ?> Menu</a>
<div class="owner-head"><h1><?= e($title) ?></h1></div>
<?php errors($errors); ?>
<?php if (!$categories): ?><div class="alert alert-info">Create a category first in <a href="<?= e(url('owner/categories.php')) ?>">Categories</a>.</div><?php endif ?>
<form class="owner-grid form-layout" method="post" enctype="multipart/form-data">
    <?= csrf() ?>
    <div class="stack">
        <section class="card">
            <h2>Dish details</h2>
            <?php field('name', 'Name', 'text', 'required minlength="2" maxlength="120"', old('name', $item['name'] ?? '')); ?>
            <div class="form-grid">
                <div class="field">
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id" required>
                        <option value="">Choose…</option>
                        <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= old('category_id', $item['category_id'] ?? param('category')) === (string)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach ?>
                    </select>
                </div>
                <?php field('price', 'Price (' . setting('currency') . ')', 'number', 'required min="0.01" max="99999.99" step="0.01" inputmode="decimal"', old('price', $item ? number_format($item['price_minor'] / 100, 2, '.', '') : '')); ?>
            </div>
            <?php area('description', 'Description', old('description', $item['description'] ?? ''), 'required minlength="10" maxlength="3000" rows="4"'); ?>
            <?php area('ingredients', 'Ingredients & allergens', old('ingredients', $item['ingredients'] ?? ''), 'required minlength="3" maxlength="2000" rows="3" placeholder="e.g. Chicken, yoghurt (milk), garlic. Contains milk."'); ?>
            <div class="check-row">
                <label class="check"><input type="checkbox" name="vegetarian" value="1" <?= $checked('vegetarian') ?>> Vegetarian</label>
                <label class="check"><input type="checkbox" name="spicy" value="1" <?= $checked('spicy') ?>> Spicy</label>
                <label class="check"><input type="checkbox" name="featured" value="1" <?= $checked('featured') ?>> Chef's pick (shown on home page)</label>
            </div>
        </section>
        <section class="card">
            <h2>When it's served</h2>
            <fieldset class="day-picker">
                <legend>Days</legend>
                <?php foreach (WEEKDAYS as $d => $dayName): ?>
                    <label><input type="checkbox" name="days[]" value="<?= $d ?>" <?= $daysMask & (1 << ($d - 1)) ? 'checked' : '' ?>><span><?= e(substr($dayName, 0, 3)) ?></span></label>
                <?php endforeach ?>
            </fieldset>
            <label class="check"><input type="checkbox" name="time_limited" value="1" <?= $isLimited ? 'checked' : '' ?> data-toggle-target="#time-window"> Only at certain times (e.g. breakfast or lunch specials)</label>
            <div class="form-grid" id="time-window" <?= $isLimited ? '' : 'hidden' ?>>
                <?php field('available_from', 'From', 'time', '', old('available_from', $item['available_from'] ?? '11:00')); ?>
                <?php field('available_until', 'Until', 'time', '', old('available_until', $item['available_until'] ?? '15:00')); ?>
            </div>
            <p class="hint">Outside these times the dish stays on the menu but can't be ordered.</p>
        </section>
    </div>
    <div class="stack">
        <section class="card">
            <h2>Photo</h2>
            <img class="photo-preview" src="<?= e(image_url($item['photo_id'] ?? null)) ?>" alt="" data-photo-preview>
            <?php field('photo', $item && $item['photo_id'] ? 'Replace photo' : 'Upload photo', 'file', 'accept="image/jpeg,image/png,image/webp" data-photo-input', '', 'JPG, PNG or WebP, up to 5 MB. Landscape 4:3 looks best.'); ?>
            <?php if ($item && $item['photo_id']): ?><label class="check"><input type="checkbox" name="remove_photo" value="1"> Remove current photo</label><?php endif ?>
        </section>
        <section class="card">
            <h2>Visibility</h2>
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <?php foreach (['available' => 'Available to order', 'sold_out' => 'Sold out (shown, not orderable)', 'hidden' => 'Hidden from menu'] as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= old('status', $item['status'] ?? 'available') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <?php field('sort_order', 'Position in category', 'number', 'min="0" max="9999"', old('sort_order', $item['sort_order'] ?? '0'), 'Lower numbers appear first.'); ?>
            <button class="btn btn-primary btn-block" type="submit" <?= $categories ? '' : 'disabled' ?>><?= $item ? 'Save changes' : 'Add dish' ?></button>
        </section>
    </div>
</form>
<?php require ROOT . '/app/views/owner-end.php'; ?>
