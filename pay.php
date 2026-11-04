<?php
require __DIR__ . '/app/bootstrap.php';

$u = require_user();
$order = order_for((int)param('id'), $u);
if ((int)$order['customer_id'] !== (int)$u['id']) fail(404, 'Order not found.');
if ($order['status'] !== 'awaiting_payment') go('order.php?id=' . $order['id']);
if (!stripe_ready()) fail(503, 'Online payment is not available right now. Please call us on ' . setting('phone') . '.');

try {
    $intent = order_payment_intent($order);
} catch (RuntimeException $ex) {
    error_log($ex->getMessage());
    fail(502, 'We could not start the payment. Please try again in a moment.');
}
if ($intent['status'] === 'succeeded') {
    apply_payment_intent($intent); // e.g. paid in another tab before the webhook arrived
    go('order.php?id=' . $order['id'] . '&placed=1');
}

$items = order_items((int)$order['id']);
$title = 'Pay for order ' . $order['reference'];
require ROOT . '/app/views/header.php';
?>
<main id="main" class="section">
    <div class="container">
        <a class="link-back" href="<?= e(url('order.php?id=' . $order['id'])) ?>"><?= icon('back', 16) ?> Order <?= e($order['reference']) ?></a>
        <h1>Pay for your order</h1>
        <div class="checkout">
            <div class="card pay-card" data-pay
                 data-key="<?= e(cfg('stripe_publishable_key')) ?>"
                 data-secret="<?= e($intent['client_secret']) ?>"
                 data-return="<?= e(url('payment-return.php?id=' . $order['id'])) ?>">
                <?php if (stripe_test_mode()): ?>
                    <div class="alert alert-info">Test mode — use card 4242 4242 4242 4242, any future date and any CVC. No real money is taken.</div>
                <?php endif ?>
                <div class="wallets" data-wallets>
                    <h2>Express checkout</h2>
                    <div id="express-checkout" data-express></div>
                    <div class="divider"><span>or pay by card</span></div>
                </div>
                <form data-card-form>
                    <div id="payment-element" data-payment-element></div>
                    <div class="alert alert-error" role="alert" data-pay-error hidden></div>
                    <button class="btn btn-primary btn-block btn-lg" type="submit" data-pay-button disabled>Pay <?= money((int)$order['total_minor']) ?></button>
                </form>
                <p class="hint center"><?= icon('check', 15) ?> Payments are processed securely by Stripe. We never see your card details.</p>
                <noscript><div class="alert alert-error">Online payment needs JavaScript. You can cancel this order and choose to pay on <?= $order['fulfillment'] === 'delivery' ? 'delivery' : 'collection' ?> instead.</div></noscript>
            </div>
            <aside class="checkout-aside">
                <div class="card sticky">
                    <h2>Order <?= e($order['reference']) ?></h2>
                    <?php order_summary($order, $items); ?>
                    <p class="hint"><?= $order['fulfillment'] === 'delivery' ? 'Delivery to ' . e($order['delivery_address']) : 'Collection from ' . e(setting('address')) ?></p>
                </div>
            </aside>
        </div>
    </div>
</main>
<script src="https://js.stripe.com/v3/"></script>
<script defer src="<?= e(url('assets/js/pay.js?v=1')) ?>"></script>
<?php require ROOT . '/app/views/footer.php'; ?>
