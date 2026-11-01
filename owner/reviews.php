<?php
require __DIR__ . '/../app/bootstrap.php';

require_owner();
if (post() && val('action') === 'delete') {
    q('DELETE FROM dish_reviews WHERE id = ?', [(int)val('id')]);
    flash('Review removed.');
    go('owner/reviews.php');
}

$reviews = all('SELECT r.*, u.full_name, u.email, m.name AS dish_name FROM dish_reviews r JOIN users u ON u.id = r.user_id JOIN menu_items m ON m.id = r.menu_item_id ORDER BY r.updated_at DESC LIMIT 200');
$overall = one('SELECT AVG(rating) AS average, COUNT(*) AS total FROM dish_reviews');
$title = 'Dish reviews';

require ROOT . '/app/views/owner-start.php';
?>
<div class="owner-head">
    <div><h1>Dish reviews</h1><p class="muted">Reviews from customers who ordered the dish. Remove anything inappropriate.</p></div>
    <div><?= stars((float)$overall['average'], (int)$overall['total']) ?></div>
</div>
<?php if (!$reviews): ?><section class="card"><div class="empty-state small"><?= icon('star', 28) ?><p>No reviews yet. Customers can review dishes after their order is completed.</p></div></section><?php endif ?>
<div class="stack">
    <?php foreach ($reviews as $r): ?>
        <article class="card">
            <div class="card-head">
                <div><a href="<?= e(url('dish.php?id=' . $r['menu_item_id'])) ?>" target="_blank"><strong><?= e($r['dish_name']) ?></strong></a> <?= stars((float)$r['rating'], 0, false) ?><br>
                    <small class="muted"><?= e($r['full_name']) ?> · <?= e($r['email']) ?> · <?= e(local_time($r['updated_at'])) ?></small></div>
                <form method="post" data-confirm="Remove this review?"><?= csrf() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-ghost btn-sm btn-danger-text" type="submit"><?= icon('trash', 15) ?> Remove</button></form>
            </div>
            <p><?= nl2br(e($r['review_text'])) ?></p>
        </article>
    <?php endforeach ?>
</div>
<?php require ROOT . '/app/views/owner-end.php'; ?>
