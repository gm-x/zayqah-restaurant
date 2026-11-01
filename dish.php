<?php
require __DIR__ . '/app/bootstrap.php';

$item = visible_item((int)param('id'));
if (!$item) fail(404, 'This dish is not on our menu any more.');
$viewer = user();
$errors = [];

// Customers who received this dish in a completed order may review it once (and edit their review).
$canReview = $viewer && (bool)one("SELECT o.id FROM orders o JOIN order_items i ON i.order_id = o.id
    WHERE o.customer_id = ? AND o.status = 'completed' AND i.menu_item_id = ? LIMIT 1", [$viewer['id'], $item['id']]);
$mine = $viewer ? one('SELECT * FROM dish_reviews WHERE menu_item_id = ? AND user_id = ?', [$item['id'], $viewer['id']]) : null;

if (post() && val('action') === 'review') {
    if (!$canReview) fail(403, 'Only customers who ordered this dish can review it.');
    $rating = (int)val('rating');
    $text = val('review_text');
    if ($rating < 1 || $rating > 5) $errors[] = 'Choose a rating from 1 to 5 stars.';
    if (!text_valid($text, 5, 1000)) $errors[] = 'Write a review of 5 to 1000 characters.';
    if (!$errors) {
        q('INSERT INTO dish_reviews (menu_item_id, user_id, rating, review_text, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE rating = VALUES(rating), review_text = VALUES(review_text), updated_at = VALUES(updated_at)',
            [$item['id'], $viewer['id'], $rating, $text, now(), now()]);
        notify_owners('New dish review', $item['name'] . ' · ' . $rating . '★', 'owner/reviews.php', 'review:' . $item['id'] . ':' . $viewer['id'] . ':' . time());
        flash('Thanks for your review!');
        go('dish.php?id=' . $item['id'] . '#reviews');
    }
}

$rating = dish_rating((int)$item['id']);
$reviews = all('SELECT r.*, u.full_name FROM dish_reviews r JOIN users u ON u.id = r.user_id WHERE r.menu_item_id = ? ORDER BY r.updated_at DESC LIMIT 30', [$item['id']]);
$related = array_slice(visible_items('m.category_id = ? AND m.id <> ?', [$item['category_id'], $item['id']]), 0, 3);
$reason = item_unavailable_reason($item);
$title = $item['name'];

require ROOT . '/app/views/header.php';
?>
<main id="main" class="section">
    <div class="container">
        <a class="link-back" href="<?= e(url('menu.php#cat-' . $item['category_id'])) ?>"><?= icon('back', 16) ?> Back to <?= e($item['category_name']) ?></a>
        <div class="dish-detail">
            <div class="dish-detail-media"><img src="<?= e(image_url($item['photo_id'])) ?>" alt="<?= e($item['name']) ?>"></div>
            <div class="dish-detail-info">
                <span class="eyebrow"><?= e($item['category_name']) ?></span>
                <h1><?= e($item['name']) ?></h1>
                <div class="dish-meta">
                    <?= stars($rating['average'], $rating['count']) ?>
                    <?php if ($item['vegetarian']): ?><span class="tag tag-veg"><?= icon('leaf', 14) ?> Vegetarian</span><?php endif ?>
                    <?php if ($item['spicy']): ?><span class="tag tag-spicy"><?= icon('flame', 14) ?> Spicy</span><?php endif ?>
                </div>
                <p class="price price-lg"><?= money((int)$item['price_minor']) ?></p>
                <p class="lead"><?= nl2br(e($item['description'])) ?></p>
                <div class="allergens"><strong>Ingredients &amp; allergens</strong><p><?= nl2br(e($item['ingredients'])) ?></p></div>
                <?php if ($item['available_days'] != 127 || $item['available_from']): ?><p class="hint"><?= icon('clock', 15) ?> <?= e(availability_label($item)) ?></p><?php endif ?>
                <?php if ($reason): ?>
                    <div class="alert alert-info"><?= e($reason) ?></div>
                <?php else: ?>
                    <form class="add-row" method="post" action="<?= e(url('cart-action.php')) ?>" data-add-to-cart>
                        <?= csrf() ?>
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                        <input type="hidden" name="return" value="dish.php?id=<?= (int)$item['id'] ?>">
                        <div class="stepper" data-stepper>
                            <button type="button" data-step="-1" aria-label="Fewer"><?= icon('minus', 16) ?></button>
                            <input type="number" name="quantity" value="1" min="1" max="20" aria-label="Quantity">
                            <button type="button" data-step="1" aria-label="More"><?= icon('plus', 16) ?></button>
                        </div>
                        <button class="btn btn-primary btn-lg" type="submit">Add to order</button>
                    </form>
                <?php endif ?>
            </div>
        </div>

        <section class="reviews" id="reviews">
            <div class="section-head"><h2>What customers say</h2><?= stars($rating['average'], $rating['count']) ?></div>
            <?php errors($errors); ?>
            <?php if ($canReview): ?>
                <form class="card review-form" method="post">
                    <?= csrf() ?>
                    <input type="hidden" name="action" value="review">
                    <h3><?= $mine ? 'Update your review' : 'Review this dish' ?></h3>
                    <fieldset class="star-input">
                        <legend>Your rating</legend>
                        <?php $current = (int)old('rating', $mine['rating'] ?? 0); ?>
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" <?= $current === $i ? 'checked' : '' ?> required>
                            <label for="star<?= $i ?>" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>"><?= icon('star', 26) ?></label>
                        <?php endfor ?>
                    </fieldset>
                    <?php area('review_text', 'Your review', old('review_text', $mine['review_text'] ?? ''), 'required minlength="5" maxlength="1000" rows="3"'); ?>
                    <button class="btn btn-primary" type="submit"><?= $mine ? 'Update review' : 'Post review' ?></button>
                </form>
            <?php endif ?>
            <?php if (!$reviews): ?>
                <p class="muted">No reviews yet. Customers can review a dish after their order is completed.</p>
            <?php endif ?>
            <div class="review-list">
                <?php foreach ($reviews as $r): ?>
                    <article class="review">
                        <div class="review-head"><strong><?= e(explode(' ', $r['full_name'])[0]) ?></strong><?= stars((float)$r['rating'], 0, false) ?><small><?= e(local_time($r['updated_at'], 'j M Y')) ?></small></div>
                        <p><?= nl2br(e($r['review_text'])) ?></p>
                    </article>
                <?php endforeach ?>
            </div>
        </section>

        <?php if ($related): ?>
            <section class="section-tight">
                <h2>More <?= e($item['category_name']) ?></h2>
                <div class="dish-grid">
                    <?php foreach ($related as $item) require ROOT . '/app/views/dish-card.php'; ?>
                </div>
            </section>
        <?php endif ?>
    </div>
</main>
<?php require ROOT . '/app/views/footer.php'; ?>
