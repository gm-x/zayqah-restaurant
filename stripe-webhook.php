<?php
// Stripe webhook endpoint. In the Stripe dashboard add https://YOUR-DOMAIN/stripe-webhook.php with the events
// payment_intent.succeeded, payment_intent.payment_failed and charge.refunded, and put its signing secret in STRIPE_WEBHOOK_SECRET.
// This confirms paid orders even if the customer closes the browser before returning to the site.
require __DIR__ . '/app/bootstrap.php';

header('Content-Type: application/json');
$payload = file_get_contents('php://input') ?: '';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !stripe_signature_valid($payload, $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '')) {
    http_response_code(400);
    exit('{"error":"invalid signature"}');
}

$event = json_decode($payload, true) ?: [];
$object = $event['data']['object'] ?? [];

switch ($event['type'] ?? '') {
    case 'payment_intent.succeeded':
    case 'payment_intent.payment_failed':
    case 'payment_intent.canceled':
        // Re-read from Stripe so the decision never rests on the webhook body alone.
        apply_payment_intent(stripe_api('GET', 'payment_intents/' . ($object['id'] ?? '')));
        break;
    case 'charge.refunded':
        // A refund made from the Stripe dashboard instead of by cancelling in the owner panel.
        if (!empty($object['refunded']) && !empty($object['payment_intent'])) {
            q("UPDATE orders SET payment_status = 'refunded', refunded_at = COALESCE(refunded_at, ?), updated_at = ? WHERE payment_intent_id = ? AND payment_status = 'paid'",
                [now(), now(), $object['payment_intent']]);
        }
        break;
}
echo '{"received":true}';
