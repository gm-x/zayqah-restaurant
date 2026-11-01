<?php
require __DIR__ . '/app/bootstrap.php';

$u = require_user();
$title = 'Checkout';
$errors = [];
$cart = cart_data();
$pickupOn = setting('pickup_enabled') === '1';
$deliveryOn = setting('delivery_enabled') === '1';
$fee = (int)setting('delivery_fee');
$minOrder = (int)setting('min_order');

/** Snapshot of what the customer saw; if prices, fees or availability change the order must be re-confirmed. */
function checkout_fingerprint(array $cart): string
{
    $lines = array_map(fn($i) => [(int)$i['id'], (int)$i['quantity'], (int)$i['price_minor']], $cart['items']);
    return hash('sha256', json_encode([$lines, setting('delivery_fee'), setting('pickup_enabled'), setting('delivery_enabled')]));
}

$method = old('fulfillment', $pickupOn ? 'pickup' : 'delivery');
$name = old('customer_name', $u['full_name']);
$phone = old('customer_phone', $u['phone']);
$address = old('delivery_address', $u['delivery_address']);
$lat = old('latitude', $u['latitude']);
$lng = old('longitude', $u['longitude']);
$note = old('note');

if (post()) {
    $token = val('checkout_token');
    $requestKey = hash('sha256', $u['id'] . '|' . $token);
    $previous = preg_match('/^[a-f0-9]{48}$/', $token) ? one('SELECT id FROM orders WHERE request_key = ?', [$requestKey]) : null;
    if ($previous) go('order.php?id=' . $previous['id'] . '&placed=1'); // a repeated submit of the same checkout

    if (!hash_equals($_SESSION['checkout_token'] ?? '', $token)) $errors[] = 'Your checkout expired. Please review your order and try again.';
    if (!$cart['items']) $errors[] = 'Your order is empty.';
    if ($cart['blocked']) $errors[] = 'Some dishes in your order are not available right now. Please remove them first.';
    if (!ordering_open()) $errors[] = 'Sorry, we are not taking online orders right now. ' . opening_status()['label'] . '.';
    if ($minOrder && $cart['subtotal'] < $minOrder) $errors[] = 'The minimum online order is ' . money($minOrder) . '.';
    if (!in_array($method, ['pickup', 'delivery'], true) || ($method === 'pickup' && !$pickupOn) || ($method === 'delivery' && !$deliveryOn)) $errors[] = 'Choose collection or delivery.';
    if (!text_valid($name, 2, 120)) $errors[] = 'Enter the name for this order.';
    if (!phone_valid($phone)) $errors[] = 'Enter a valid phone number so we can reach you.';
    if (!text_valid($note, 0, 1000)) $errors[] = 'Keep your note under 1000 characters.';
    $point = null;
    if ($method === 'delivery') {
        if (!text_valid($address, 10, 1000)) $errors[] = 'Enter your full delivery address.';
        $point = coordinates($lat, $lng);
        if (!$point) $errors[] = 'Drop a pin on the map so our rider can find you.';
    }
    if (!hash_equals(val('fingerprint'), checkout_fingerprint($cart))) $errors[] = 'Prices or availability changed while you were checking out. Please review the updated total.';

    if (!$errors) {
        db()->beginTransaction();
        $deliveryFee = $method === 'delivery' ? $fee : 0;
        $reference = 'OR-' . strtoupper(bin2hex(random_bytes(4)));
        q('INSERT INTO orders (reference, request_key, customer_id, fulfillment, status, subtotal_minor, delivery_fee_minor, total_minor,
              customer_name, customer_phone, delivery_address, latitude, longitude, note, created_at, updated_at)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            $reference, $requestKey, $u['id'], $method, 'placed', $cart['subtotal'], $deliveryFee, $cart['subtotal'] + $deliveryFee,
            $name, $phone, $method === 'delivery' ? $address : null, $point[0] ?? null, $point[1] ?? null, $note ?: null, now(), now(),
        ]);
        $orderId = (int)db()->lastInsertId();
        foreach ($cart['items'] as $item) {
            q('INSERT INTO order_items (order_id, menu_item_id, name, quantity, unit_price_minor, total_minor) VALUES (?, ?, ?, ?, ?, ?)',
                [$orderId, $item['id'], $item['name'], $item['quantity'], $item['price_minor'], $item['line_total']]);
        }
        if ($method === 'delivery') q('UPDATE users SET delivery_address = ?, latitude = ?, longitude = ? WHERE id = ?', [$address, $point[0], $point[1], $u['id']]);
        $order = one('SELECT * FROM orders WHERE id = ?', [$orderId]);
        record_order_event($order, 'placed');
        $lines = implode("\n", array_map(fn($i) => $i['quantity'] . ' × ' . $i['name'] . '  ' . money((int)$i['line_total']), $cart['items']));
        queue_mail($u['email'], 'Order received · ' . $reference . ' · ' . setting('name'),
            "Hello {$u['full_name']},\n\nThank you for your order. We'll confirm it shortly.\n\n$lines\nTotal: " . money((int)$order['total_minor'])
            . "\n\n" . ($method === 'delivery' ? "Delivery to: $address" : 'Collect from: ' . setting('address'))
            . "\n\nTrack your order: " . url('order.php?id=' . $orderId) . "\n\n" . setting('name'));
        db()->commit();
        mail_after_commit();
        $_SESSION['cart'] = [];
        unset($_SESSION['checkout_token']);
        go('order.php?id=' . $orderId . '&placed=1');
    }
}

$_SESSION['checkout_token'] ??= bin2hex(random_bytes(24));
$deliveryFee = $method === 'delivery' ? $fee : 0;
require ROOT . '/app/views/header.php';
?>
<main id="main" class="section">
    <div class="container">
        <h1>Checkout</h1>
        <?php errors($errors); ?>
        <?php if (!$cart['items']): ?>
            <div class="empty-state"><?= icon('bag', 36) ?><h2>Your order is empty</h2><a class="btn btn-primary" href="<?= e(url('menu.php')) ?>">View menu</a></div>
        <?php else: ?>
            <?php if (!ordering_open()): ?><div class="alert alert-info"><?= icon('clock', 18) ?> <?= e(opening_status()['label']) ?>. You'll be able to place your order once we're open.</div><?php endif ?>
            <form class="checkout" method="post" data-checkout data-fee="<?= $fee ?>" data-subtotal="<?= $cart['subtotal'] ?>" data-currency="<?= e(setting('currency')) ?>">
                <?= csrf() ?>
                <input type="hidden" name="checkout_token" value="<?= e($_SESSION['checkout_token']) ?>">
                <input type="hidden" name="fingerprint" value="<?= e(checkout_fingerprint($cart)) ?>">
                <div class="checkout-main">
                    <section class="card">
                        <h2><span class="step-num">1</span> How would you like your food?</h2>
                        <div class="choice-grid">
                            <label class="choice <?= $pickupOn ? '' : 'is-disabled' ?>">
                                <input type="radio" name="fulfillment" value="pickup" <?= $method === 'pickup' ? 'checked' : '' ?> <?= $pickupOn ? '' : 'disabled' ?>>
                                <span><?= icon('bag', 22) ?><strong>Collection</strong><small><?= $pickupOn ? 'Ready in about ' . (int)setting('prep_minutes') . ' min · Free' : 'Not available' ?></small></span>
                            </label>
                            <label class="choice <?= $deliveryOn ? '' : 'is-disabled' ?>">
                                <input type="radio" name="fulfillment" value="delivery" <?= $method === 'delivery' ? 'checked' : '' ?> <?= $deliveryOn ? '' : 'disabled' ?>>
                                <span><?= icon('truck', 22) ?><strong>Delivery</strong><small><?= $deliveryOn ? ($fee ? money($fee) . ' delivery fee' : 'Free delivery') : 'Not available' ?></small></span>
                            </label>
                        </div>
                        <p class="hint" data-show-for="pickup"><?= icon('pin', 15) ?> Collect from <?= e(setting('address')) ?></p>
                    </section>

                    <section class="card">
                        <h2><span class="step-num">2</span> Your details</h2>
                        <div class="form-grid">
                            <?php field('customer_name', 'Name', 'text', 'required maxlength="120" autocomplete="name"', $name); ?>
                            <?php field('customer_phone', 'Phone', 'tel', 'required maxlength="25" autocomplete="tel"', $phone); ?>
                        </div>
                        <div data-show-for="delivery">
                            <?php area('delivery_address', 'Delivery address', (string)$address, 'rows="3" maxlength="1000" autocomplete="street-address" data-required-for="delivery"'); ?>
                            <?php location_picker((string)$lat, (string)$lng); ?>
                        </div>
                        <?php area('note', 'Notes for the kitchen (optional)', $note, 'rows="2" maxlength="1000" placeholder="Allergies, spice level, door code…"'); ?>
                    </section>
                </div>
                <aside class="checkout-aside">
                    <div class="card sticky">
                        <h2>Order summary</h2>
                        <div class="summary">
                            <?php foreach ($cart['items'] as $item): ?>
                                <div class="summary-line"><span><b><?= (int)$item['quantity'] ?>×</b> <?= e($item['name']) ?></span><span><?= money((int)$item['line_total']) ?></span></div>
                            <?php endforeach ?>
                            <div class="summary-line muted"><span>Subtotal</span><span><?= money($cart['subtotal']) ?></span></div>
                            <div class="summary-line muted"><span>Delivery</span><span data-fee-label><?= $deliveryFee ? money($deliveryFee) : 'Free' ?></span></div>
                            <div class="summary-line total"><span>Total</span><span data-total><?= money($cart['subtotal'] + $deliveryFee) ?></span></div>
                        </div>
                        <p class="hint"><?= icon('check', 15) ?> Pay by cash or card when you receive your order.</p>
                        <button class="btn btn-primary btn-block btn-lg" type="submit" <?= ordering_open() ? '' : 'disabled' ?>>Place order</button>
                        <a class="btn btn-ghost btn-block btn-sm" href="<?= e(url('cart.php')) ?>">Edit order</a>
                    </div>
                </aside>
            </form>
        <?php endif ?>
    </div>
</main>
<?php require ROOT . '/app/views/footer.php'; ?>
