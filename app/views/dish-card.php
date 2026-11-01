<?php
/** @var array $item */
$reason = item_unavailable_reason($item);
?>
<article class="dish-card <?= $reason ? 'is-unavailable' : '' ?>">
    <a class="dish-media" href="<?= e(url('dish.php?id=' . $item['id'])) ?>" tabindex="-1" aria-hidden="true">
        <img src="<?= e(image_url($item['photo_id'])) ?>" alt="" loading="lazy" width="400" height="300">
        <?php if ($item['featured']): ?><span class="chip chip-accent">Chef's pick</span><?php endif ?>
    </a>
    <div class="dish-body">
        <div class="dish-title">
            <h3><a href="<?= e(url('dish.php?id=' . $item['id'])) ?>"><?= e($item['name']) ?></a></h3>
            <span class="price"><?= money((int)$item['price_minor']) ?></span>
        </div>
        <p class="dish-desc"><?= e(mb_strimwidth($item['description'], 0, 110, '…')) ?></p>
        <div class="dish-foot">
            <div class="dish-tags">
                <?php if ($item['vegetarian']): ?><span class="tag tag-veg" title="Vegetarian"><?= icon('leaf', 14) ?> Veg</span><?php endif ?>
                <?php if ($item['spicy']): ?><span class="tag tag-spicy" title="Spicy"><?= icon('flame', 14) ?> Spicy</span><?php endif ?>
                <?php if ($reason): ?><span class="tag"><?= e($reason) ?></span><?php endif ?>
            </div>
            <?php if (!$reason): ?>
                <form method="post" action="<?= e(url('cart-action.php')) ?>" data-add-to-cart>
                    <?= csrf() ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                    <input type="hidden" name="return" value="<?= e($_SERVER['REQUEST_URI'] ?? '') ?>">
                    <button class="btn btn-primary btn-sm btn-round" type="submit" aria-label="Add <?= e($item['name']) ?> to your order"><?= icon('plus', 18) ?></button>
                </form>
            <?php endif ?>
        </div>
    </div>
</article>
