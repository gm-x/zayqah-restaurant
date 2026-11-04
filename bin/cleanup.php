<?php
// Removes expired tokens, old rate-limit records and uploads no longer referenced. Run daily from cron.
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../app/bootstrap.php';
q('DELETE FROM tokens WHERE expires_at <= ?', [now()]);
q('DELETE FROM email_changes WHERE expires_at <= ?', [now()]);
q('DELETE FROM delivery_links WHERE expires_at <= ?', [now()]);
q('DELETE FROM attempts WHERE created_at < ?', [gmdate('Y-m-d H:i:s', time() - 86400)]);
// Online orders left unpaid for two hours are cancelled, unless Stripe says they were paid after all.
$expired = 0;
foreach (all("SELECT * FROM orders WHERE status = 'awaiting_payment' AND created_at < ?", [gmdate('Y-m-d H:i:s', time() - 7200)]) as $order) {
    if ($order['payment_intent_id'] && stripe_ready()) {
        try {
            $order = apply_payment_intent(stripe_api('GET', 'payment_intents/' . $order['payment_intent_id'])) ?? $order;
        } catch (RuntimeException $ex) {
            fwrite(STDERR, $ex->getMessage() . "\n");
            continue; // try again on the next run rather than cancel an order that may be paid
        }
    }
    if ($order['status'] === 'awaiting_payment' && !change_order_status($order, 'cancelled')) $expired++;
}

$removed = 0;
foreach (glob(ROOT . '/storage/uploads/*.{jpg,png,webp}', GLOB_BRACE) as $file) {
    $id = pathinfo($file, PATHINFO_FILENAME);
    if (filemtime($file) < time() - 86400 && !one('SELECT id FROM files WHERE id = ?', [$id])) {
        unlink($file);
        $removed++;
    }
}
echo "Cleanup complete. Cancelled $expired unpaid online order(s); removed $removed unused upload(s).\n";
