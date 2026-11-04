<?php
// Online payments through Stripe: Apple Pay, Google Pay and cards share one PaymentIntent per order.

function stripe_ready(): bool
{
    return cfg('stripe_secret_key') !== '' && cfg('stripe_publishable_key') !== '';
}

function stripe_test_mode(): bool
{
    return str_starts_with(cfg('stripe_secret_key'), 'sk_test_');
}

function pay_online_available(): bool
{
    return setting('pay_online') === '1' && stripe_ready();
}

/** Paying later is always offered when online payment is unavailable, so checkout never has zero options. */
function pay_later_available(): bool
{
    return setting('pay_later') === '1' || !pay_online_available();
}

function stripe_currency(): string
{
    return strtolower(setting('currency_code') ?: 'gbp');
}

/**
 * Calls the Stripe REST API and returns the decoded response.
 * @throws RuntimeException with Stripe's message when the request fails.
 */
function stripe_api(string $method, string $path, array $params = [], ?string $idempotencyKey = null): array
{
    $ch = curl_init('https://api.stripe.com/v1/' . ltrim($path, '/') . ($method === 'GET' && $params ? '?' . http_build_query($params) : ''));
    $headers = ['Authorization: Bearer ' . cfg('stripe_secret_key'), 'Stripe-Version: 2024-06-20'];
    if ($idempotencyKey) $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    if ($method !== 'GET') curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($body === false) throw new RuntimeException('Could not reach Stripe: ' . $error);
    $data = json_decode($body, true) ?: [];
    if ($status >= 400) throw new RuntimeException('Stripe: ' . ($data['error']['message'] ?? "HTTP $status"));
    return $data;
}

/** Creates (or returns the existing) PaymentIntent for an order awaiting payment. */
function order_payment_intent(array $order): array
{
    if ($order['payment_intent_id']) {
        $intent = stripe_api('GET', 'payment_intents/' . $order['payment_intent_id']);
        if ((int)$intent['amount'] === (int)$order['total_minor']) return $intent;
    }
    $customer = one('SELECT email FROM users WHERE id = ?', [$order['customer_id']]);
    $intent = stripe_api('POST', 'payment_intents', [
        'amount'                    => (int)$order['total_minor'],
        'currency'                  => stripe_currency(),
        'automatic_payment_methods' => ['enabled' => 'true'],
        'description'               => setting('name') . ' order ' . $order['reference'],
        'receipt_email'             => $customer['email'] ?? null,
        'metadata'                  => ['order_id' => $order['id'], 'reference' => $order['reference']],
    ], 'order-' . $order['id'] . '-' . $order['total_minor']);
    q("UPDATE orders SET payment_intent_id = ?, payment_status = 'pending', updated_at = ? WHERE id = ?", [$intent['id'], now(), $order['id']]);
    return $intent;
}

/**
 * Releases a paid order to the kitchen. Safe to call from both the return page and the webhook:
 * only the first caller whose intent really succeeded moves the order on.
 */
function apply_payment_intent(array $intent): ?array
{
    $order = one('SELECT * FROM orders WHERE payment_intent_id = ?', [$intent['id'] ?? '']);
    if (!$order) return null;

    if (($intent['status'] ?? '') === 'succeeded') {
        if ((int)$intent['amount_received'] !== (int)$order['total_minor'] || strtolower($intent['currency']) !== stripe_currency()) {
            error_log("Payment amount mismatch for order {$order['reference']}");
            return $order;
        }
        db()->beginTransaction();
        $released = q("UPDATE orders SET status = 'placed', payment_status = 'paid', paid_at = ?, updated_at = ? WHERE id = ? AND status = 'awaiting_payment'",
            [now(), now(), $order['id']])->rowCount() === 1;
        if (!$released && $order['status'] === 'cancelled' && $order['payment_status'] !== 'refunded') {
            // Paid after the order was cancelled (e.g. a very late wallet confirmation): give the money back.
            q("UPDATE orders SET payment_status = 'paid', paid_at = ? WHERE id = ?", [now(), $order['id']]);
            db()->commit();
            refund_order(one('SELECT * FROM orders WHERE id = ?', [$order['id']]));
            return one('SELECT * FROM orders WHERE id = ?', [$order['id']]);
        }
        $order = one('SELECT * FROM orders WHERE id = ?', [$order['id']]);
        if ($released) record_order_event($order, 'placed');
        db()->commit();
        if ($released) {
            order_received_mail($order);
            mail_after_commit();
        }
    } elseif (in_array($intent['status'] ?? '', ['requires_payment_method', 'canceled'], true) && $order['status'] === 'awaiting_payment') {
        q("UPDATE orders SET payment_status = 'failed', updated_at = ? WHERE id = ? AND payment_status <> 'paid'", [now(), $order['id']]);
        $order = one('SELECT * FROM orders WHERE id = ?', [$order['id']]);
    }
    return $order;
}

/** Refunds a paid order in full. Returns an error message, or null on success. */
function refund_order(array $order): ?string
{
    if ($order['payment_status'] !== 'paid' || !$order['payment_intent_id']) return null;
    try {
        stripe_api('POST', 'refunds', ['payment_intent' => $order['payment_intent_id'], 'metadata' => ['order_id' => $order['id']]], 'refund-' . $order['id']);
    } catch (RuntimeException $ex) {
        error_log('Refund failed for ' . $order['reference'] . ': ' . $ex->getMessage());
        return 'The order was cancelled, but the refund failed: ' . $ex->getMessage() . ' Refund it from your Stripe dashboard.';
    }
    q("UPDATE orders SET payment_status = 'refunded', refunded_at = ?, updated_at = ? WHERE id = ?", [now(), now(), $order['id']]);
    notify((int)$order['customer_id'], 'Refund issued', money((int)$order['total_minor']) . ' for ' . $order['reference'] . ' is on its way back to you.', 'order.php?id=' . $order['id'], 'refund:' . $order['id']);
    return null;
}

/** Stops an unpaid PaymentIntent so it can no longer be charged. */
function cancel_payment_intent(array $order): void
{
    if (!$order['payment_intent_id'] || $order['payment_status'] === 'paid') return;
    try {
        stripe_api('POST', 'payment_intents/' . $order['payment_intent_id'] . '/cancel', [], 'cancel-' . $order['id']);
    } catch (RuntimeException $ex) {
        error_log('Could not cancel payment for ' . $order['reference'] . ': ' . $ex->getMessage());
    }
}

/** Checks the Stripe-Signature header (HMAC-SHA256 over "timestamp.payload") against the webhook secret. */
function stripe_signature_valid(string $payload, string $header, int $tolerance = 300): bool
{
    $secret = cfg('stripe_webhook_secret');
    if ($secret === '' || $header === '') return false;
    $timestamp = null;
    $signatures = [];
    foreach (explode(',', $header) as $part) {
        [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
        if ($key === 't') $timestamp = (int)$value;
        if ($key === 'v1') $signatures[] = $value;
    }
    if (!$timestamp || abs(time() - $timestamp) > $tolerance) return false;
    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    foreach ($signatures as $signature) {
        if (hash_equals($expected, $signature)) return true;
    }
    return false;
}

function payment_badge(array $order): string
{
    [$class, $label] = match (true) {
        $order['payment_status'] === 'paid'     => ['completed', 'Paid online'],
        $order['payment_status'] === 'refunded' => ['cancelled', 'Refunded'],
        $order['payment_method'] === 'online'   => ['placed', $order['payment_status'] === 'failed' ? 'Payment failed' : 'Payment pending'],
        default                                 => ['confirmed', 'Pay on ' . ($order['fulfillment'] === 'delivery' ? 'delivery' : 'collection')],
    };
    return '<span class="badge badge-' . $class . '">' . e($label) . '</span>';
}
