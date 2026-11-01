<?php /** @var array $cart */ ?>
<div class="card order-card">
    <h2>Your order</h2>
    <?php if (!$cart['items']): ?>
        <div class="order-empty"><?= icon('bag', 28) ?><p>Your order is empty.<br>Add a dish to get started.</p></div>
    <?php else: ?>
        <ul class="mini-cart">
            <?php foreach ($cart['items'] as $item): ?>
                <li>
                    <div>
                        <strong><?= e($item['name']) ?></strong>
                        <?php if ($item['unavailable']): ?><small class="text-danger"><?= e($item['unavailable']) ?></small><?php endif ?>
                    </div>
                    <div class="qty">
                        <?= cart_qty_button($item, (int)$item['quantity'] - 1, 'minus', 'Remove one') ?>
                        <span aria-label="Quantity"><?= (int)$item['quantity'] ?></span>
                        <?= cart_qty_button($item, (int)$item['quantity'] + 1, 'plus', 'Add one') ?>
                    </div>
                    <span class="mini-price"><?= money((int)$item['line_total']) ?></span>
                </li>
            <?php endforeach ?>
        </ul>
        <div class="summary-line total"><span>Subtotal</span><span><?= money($cart['subtotal']) ?></span></div>
        <a class="btn btn-primary btn-block" href="<?= e(url('checkout.php')) ?>">Checkout <?= icon('arrow', 18) ?></a>
        <a class="btn btn-ghost btn-block btn-sm" href="<?= e(url('cart.php')) ?>">Review order</a>
    <?php endif ?>
</div>
