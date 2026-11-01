<?php
require __DIR__ . '/app/bootstrap.php';

$title = 'Your order';
$cart = cart_data();
$minOrder = (int)setting('min_order');

require ROOT . '/app/views/header.php';
?>
<main id="main" class="section">
    <div class="container narrow">
        <h1>Your order</h1>
        <?php if (!$cart['items']): ?>
            <div class="empty-state">
                <?= icon('bag', 36) ?>
                <h2>Your order is empty</h2>
                <p>Browse our menu and add a few dishes to get started.</p>
                <a class="btn btn-primary" href="<?= e(url('menu.php')) ?>">View menu</a>
            </div>
        <?php else: ?>
            <div class="card">
                <ul class="cart-list">
                    <?php foreach ($cart['items'] as $item): ?>
                        <li>
                            <img src="<?= e(image_url($item['photo_id'])) ?>" alt="" width="72" height="72">
                            <div class="cart-item-info">
                                <a href="<?= e(url('dish.php?id=' . $item['id'])) ?>"><strong><?= e($item['name']) ?></strong></a>
                                <small><?= money((int)$item['price_minor']) ?> each</small>
                                <?php if ($item['unavailable']): ?><small class="text-danger"><?= e($item['unavailable']) ?> — please remove it to continue.</small><?php endif ?>
                            </div>
                            <div class="qty">
                                <?= cart_qty_button($item, (int)$item['quantity'] - 1, 'minus', 'Remove one') ?>
                                <span><?= (int)$item['quantity'] ?></span>
                                <?= cart_qty_button($item, (int)$item['quantity'] + 1, 'plus', 'Add one') ?>
                            </div>
                            <strong class="cart-line-total"><?= money((int)$item['line_total']) ?></strong>
                        </li>
                    <?php endforeach ?>
                </ul>
                <div class="summary-line total"><span>Subtotal</span><span><?= money($cart['subtotal']) ?></span></div>
                <?php if ($minOrder && $cart['subtotal'] < $minOrder): ?>
                    <div class="alert alert-info">The minimum online order is <?= money($minOrder) ?>. Add <?= money($minOrder - $cart['subtotal']) ?> more to check out.</div>
                <?php endif ?>
                <div class="row-between">
                    <form method="post" action="<?= e(url('cart-action.php')) ?>"><?= csrf() ?><input type="hidden" name="action" value="clear"><input type="hidden" name="return" value="cart.php"><button class="btn btn-ghost btn-sm" type="submit"><?= icon('trash', 16) ?> Clear order</button></form>
                    <div class="row-gap">
                        <a class="btn btn-ghost" href="<?= e(url('menu.php')) ?>">Add more</a>
                        <a class="btn btn-primary" href="<?= e(url('checkout.php')) ?>">Checkout <?= icon('arrow', 18) ?></a>
                    </div>
                </div>
            </div>
        <?php endif ?>
    </div>
</main>
<?php require ROOT . '/app/views/footer.php'; ?>
