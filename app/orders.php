<?php
// Order lifecycle, notifications and tracking views.

const ORDER_TRANSITIONS = [
    'placed'    => ['confirmed', 'cancelled'],
    'confirmed' => ['preparing', 'cancelled'],
    'preparing' => ['ready', 'cancelled'],
    'ready'     => ['completed'],
    'completed' => [],
    'cancelled' => [],
];

const OPEN_STATUSES = ['placed', 'confirmed', 'preparing', 'ready'];

function status_label(array $order, ?string $status = null): string
{
    $status ??= $order['status'];
    $pickup = $order['fulfillment'] === 'pickup';
    return match ($status) {
        'placed'    => 'Order placed',
        'confirmed' => 'Confirmed',
        'preparing' => 'Being prepared',
        'ready'     => $pickup ? 'Ready for collection' : 'Out for delivery',
        'completed' => $pickup ? 'Collected' : 'Delivered',
        'cancelled' => 'Cancelled',
        default     => ucfirst($status),
    };
}

function status_badge(array $order): string
{
    return '<span class="badge badge-' . e($order['status']) . '">' . e(status_label($order)) . '</span>';
}

/** Loads an order the given user may see: their own, or any order for the owner. */
function order_for(int $id, array $u): array
{
    $order = one('SELECT * FROM orders WHERE id = ?', [$id]);
    if (!$order || (!is_owner($u) && (int)$order['customer_id'] !== (int)$u['id'])) fail(404, 'Order not found.');
    return $order;
}

function order_items(int $orderId): array
{
    return all('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$orderId]);
}

function notify(int $userId, string $title, string $body, string $path, string $eventKey): void
{
    q('INSERT IGNORE INTO notifications (user_id, title, body, path, event_key, created_at) VALUES (?, ?, ?, ?, ?, ?)',
        [$userId, mb_substr($title, 0, 160), mb_substr($body, 0, 400), $path, $eventKey, now()]);
}

function notify_owners(string $title, string $body, string $path, string $eventKey): void
{
    foreach (all("SELECT id FROM users WHERE role = 'owner'") as $owner) {
        notify((int)$owner['id'], $title, $body, $path, $eventKey . ':' . $owner['id']);
    }
}

/** Saves a status step in the timeline and tells the customer (and the owner, for new orders). */
function record_order_event(array $order, string $status): void
{
    q('INSERT IGNORE INTO order_events (order_id, status, created_at) VALUES (?, ?, ?)', [$order['id'], $status, now()]);
    $label = status_label($order, $status);
    notify((int)$order['customer_id'], $label, 'Order ' . $order['reference'], 'order.php?id=' . $order['id'], 'order:' . $order['id'] . ':' . $status);
    if ($status === 'placed') {
        notify_owners('New ' . $order['fulfillment'] . ' order', $order['reference'] . ' · ' . money((int)$order['total_minor']) . ' · ' . $order['customer_name'],
            'owner/order.php?id=' . $order['id'], 'new-order:' . $order['id']);
    }
}

/** Moves an order to the next status if that step is allowed; returns an error message or null. */
function change_order_status(array $order, string $next): ?string
{
    if (!in_array($next, ORDER_TRANSITIONS[$order['status']] ?? [], true)) return 'That status change is not available.';
    db()->beginTransaction();
    $changed = q('UPDATE orders SET status = ?, updated_at = ? WHERE id = ? AND status = ?', [$next, now(), $order['id'], $order['status']])->rowCount();
    if ($changed !== 1) {
        db()->rollBack();
        return 'This order was updated somewhere else. Reload and try again.';
    }
    if (in_array($next, ['completed', 'cancelled'], true)) q('DELETE FROM delivery_links WHERE order_id = ?', [$order['id']]);
    record_order_event($order, $next);
    db()->commit();
    $customer = one('SELECT email, full_name FROM users WHERE id = ?', [$order['customer_id']]);
    if ($customer && in_array($next, ['confirmed', 'ready', 'cancelled'], true)) {
        queue_mail($customer['email'], setting('name') . ' · ' . status_label($order, $next) . ' · ' . $order['reference'],
            "Hello {$customer['full_name']},\n\nYour order {$order['reference']} is now: " . status_label($order, $next) . ".\n\nTrack it here: " . url('order.php?id=' . $order['id']) . "\n\n" . setting('name'));
        mail_after_commit();
    }
    return null;
}

function tracking_html(array $order): string
{
    $times = array_column(all('SELECT status, created_at FROM order_events WHERE order_id = ? ORDER BY id', [$order['id']]), 'created_at', 'status');
    $steps = ['placed', 'confirmed', 'preparing', 'ready', 'completed'];
    $position = array_search($order['status'], $steps, true);
    ob_start();
    ?>
    <div class="tracker <?= $order['status'] === 'cancelled' ? 'is-cancelled' : '' ?>">
        <div class="tracker-head">
            <div>
                <span class="eyebrow">Order <?= e($order['reference']) ?></span>
                <h2><?= e(status_label($order)) ?></h2>
            </div>
            <?= status_badge($order) ?>
        </div>
        <?php if ($order['status'] === 'cancelled'): ?>
            <p class="tracker-note">This order was cancelled. Call us on <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', setting('phone'))) ?>"><?= e(setting('phone')) ?></a> if you have any questions.</p>
        <?php else: ?>
            <ol class="steps">
                <?php foreach ($steps as $i => $step):
                    $done = $position !== false && $i <= $position; ?>
                    <li class="<?= $done ? 'done' : '' ?> <?= $order['status'] === $step ? 'current' : '' ?>" <?= $order['status'] === $step ? 'aria-current="step"' : '' ?>>
                        <span class="step-dot" aria-hidden="true"><?= $done ? icon('check', 14) : $i + 1 ?></span>
                        <span class="step-label"><?= e(status_label($order, $step)) ?></span>
                        <small><?= isset($times[$step]) ? e(local_time($times[$step], 'g:i a')) : '' ?></small>
                    </li>
                <?php endforeach ?>
            </ol>
            <?php if (in_array($order['status'], OPEN_STATUSES, true)): ?>
                <p class="tracker-note">Updates appear here automatically. Last change <?= e(local_time($order['updated_at'], 'g:i a')) ?>.</p>
            <?php endif ?>
        <?php endif ?>
    </div>
    <?php
    return ob_get_clean();
}

function order_map(array $order): void
{
    if ($order['fulfillment'] !== 'delivery' || $order['latitude'] === null) return;
    ?>
    <div class="map map-view" data-view-map data-lat="<?= e($order['latitude']) ?>" data-lng="<?= e($order['longitude']) ?>"
        <?php if ($order['rider_latitude'] !== null): ?>data-rider-lat="<?= e($order['rider_latitude']) ?>" data-rider-lng="<?= e($order['rider_longitude']) ?>"<?php endif ?>></div>
    <?php if ($order['rider_updated_at']): ?>
        <small class="hint">Rider shared their location at <?= e(local_time($order['rider_updated_at'], 'g:i a')) ?>.</small>
    <?php endif;
}

function order_summary(array $order, array $items): void
{
    ?>
    <div class="summary">
        <?php foreach ($items as $item): ?>
            <div class="summary-line">
                <span><b><?= (int)$item['quantity'] ?>×</b> <?= e($item['name']) ?></span>
                <span><?= money((int)$item['total_minor']) ?></span>
            </div>
        <?php endforeach ?>
        <div class="summary-line muted"><span>Subtotal</span><span><?= money((int)$order['subtotal_minor']) ?></span></div>
        <div class="summary-line muted"><span><?= $order['fulfillment'] === 'delivery' ? 'Delivery' : 'Collection' ?></span><span><?= $order['delivery_fee_minor'] ? money((int)$order['delivery_fee_minor']) : 'Free' ?></span></div>
        <div class="summary-line total"><span>Total</span><span><?= money((int)$order['total_minor']) ?></span></div>
        <p class="hint">Pay on <?= $order['fulfillment'] === 'delivery' ? 'delivery' : 'collection' ?> by cash or card.</p>
    </div>
    <?php
}
