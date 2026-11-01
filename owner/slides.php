<?php
require __DIR__ . '/../app/bootstrap.php';

require_owner();
$errors = [];

if (post()) {
    $action = val('action');
    $id = (int)val('id');
    $heading = val('heading');
    $subheading = val('subheading');
    if (in_array($action, ['add', 'save'], true)) {
        if (!text_valid($heading, 2, 160)) $errors[] = 'Add a headline of 2–160 characters.';
        if (!text_valid($subheading, 0, 240)) $errors[] = 'Keep the supporting text under 240 characters.';
    }
    if (!$errors) {
        try {
            if ($action === 'add') {
                $photo = upload('photo', 'slide');
                q('INSERT INTO slides (photo_id, heading, subheading, sort_order, created_at) VALUES (?, ?, ?, ?, ?)',
                    [$photo, $heading, $subheading, (int)scalar('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM slides'), now()]);
                flash('Slide added to the home page.');
            } elseif ($action === 'save') {
                q('UPDATE slides SET heading = ?, subheading = ?, sort_order = ? WHERE id = ?', [$heading, $subheading, (int)val('sort_order'), $id]);
                flash('Slide updated.');
            } elseif ($action === 'toggle') {
                q('UPDATE slides SET active = 1 - active WHERE id = ?', [$id]);
                flash('Slide visibility changed.');
            } elseif ($action === 'delete') {
                q('DELETE FROM slides WHERE id = ?', [$id]);
                flash('Slide deleted.');
            }
            go('owner/slides.php');
        } catch (InvalidArgumentException $ex) {
            $errors[] = $ex->getMessage();
        }
    }
}

$slides = all('SELECT * FROM slides ORDER BY sort_order, id');
$title = 'Homepage';

require ROOT . '/app/views/owner-start.php';
?>
<div class="owner-head"><div><h1>Homepage slides</h1><p class="muted">The first visible slide's text is the home page headline. Slides rotate every few seconds.</p></div></div>
<?php errors($errors); ?>
<div class="owner-grid">
    <div class="stack">
        <?php if (!$slides): ?><section class="card"><div class="empty-state small"><?= icon('image', 28) ?><p>No slides yet — the home page shows a default photo.</p></div></section><?php endif ?>
        <?php foreach ($slides as $s): ?>
            <section class="card slide-row <?= $s['active'] ? '' : 'is-muted' ?>">
                <img src="<?= e(image_url($s['photo_id'])) ?>" alt="" width="200" height="130">
                <form method="post" class="slide-form">
                    <?= csrf() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                    <div class="field"><label for="heading-<?= (int)$s['id'] ?>">Headline</label><input id="heading-<?= (int)$s['id'] ?>" name="heading" required maxlength="160" value="<?= e($s['heading']) ?>"></div>
                    <div class="field"><label for="sub-<?= (int)$s['id'] ?>">Supporting text</label><input id="sub-<?= (int)$s['id'] ?>" name="subheading" maxlength="240" value="<?= e($s['subheading']) ?>"></div>
                    <div class="row-gap">
                        <input class="small-number" type="number" name="sort_order" value="<?= (int)$s['sort_order'] ?>" aria-label="Position" min="0" max="999">
                        <button class="btn btn-primary btn-sm" type="submit">Save</button>
                    </div>
                </form>
                <div class="slide-actions">
                    <form method="post"><?= csrf() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit"><?= $s['active'] ? 'Hide' : 'Show' ?></button></form>
                    <form method="post" data-confirm="Delete this slide?"><?= csrf() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-ghost btn-sm btn-danger-text" type="submit">Delete</button></form>
                </div>
            </section>
        <?php endforeach ?>
    </div>
    <section class="card">
        <h2>Add a slide</h2>
        <form method="post" enctype="multipart/form-data">
            <?= csrf() ?><input type="hidden" name="action" value="add">
            <img class="photo-preview" src="<?= e(url('assets/img/dish-placeholder.svg')) ?>" alt="" data-photo-preview>
            <?php field('photo', 'Photo', 'file', 'required accept="image/jpeg,image/png,image/webp" data-photo-input', '', 'A wide, bright food or dining-room photo works best.'); ?>
            <?php field('heading', 'Headline', 'text', 'required maxlength="160" placeholder="Slow-cooked, full of flavour"', is_post() && val('action') === 'add' ? val('heading') : ''); ?>
            <?php field('subheading', 'Supporting text', 'text', 'maxlength="240"', is_post() && val('action') === 'add' ? val('subheading') : ''); ?>
            <button class="btn btn-primary btn-block" type="submit">Add slide</button>
        </form>
    </section>
</div>
<?php require ROOT . '/app/views/owner-end.php'; ?>
