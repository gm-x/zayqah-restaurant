<?php
// Stripe sends the customer back here after Apple Pay, Google Pay, card or 3-D Secure.
// The query string is not trusted: the order's own PaymentIntent is fetched from Stripe and checked.
require __DIR__ . '/app/bootstrap.php';

$u = require_user();
$order = order_for((int)param('id'), $u);
if (!$order['payment_intent_id']) go('order.php?id=' . $order['id']);

try {
    $order = apply_payment_intent(stripe_api('GET', 'payment_intents/' . $order['payment_intent_id'])) ?? $order;
} catch (RuntimeException $ex) {
    error_log($ex->getMessage());
    flash('We could not confirm your payment yet. If you were charged, your order will update in a moment.', 'error');
    go('order.php?id=' . $order['id']);
}

if ($order['payment_status'] === 'paid') go('order.php?id=' . $order['id'] . '&placed=1');
flash('Your payment did not go through. Please try again or use another method.', 'error');
go('pay.php?id=' . $order['id']);
