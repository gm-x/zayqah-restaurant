<?php
require __DIR__ . '/app/bootstrap.php';

if (!post()) go('cart.php');
$return = local_return(val('return'), 'cart.php');
$id = (int)val('id');
$message = '';

switch (val('action')) {
    case 'add':
        $item = visible_item($id);
        if (!$item || item_unavailable_reason($item)) {
            $message = 'Sorry, that dish is not available right now.';
            break;
        }
        $qty = max(1, min(20, (int)(val('quantity') ?: 1)));
        cart_set($id, (int)($_SESSION['cart'][$id] ?? 0) + $qty);
        $message = $item['name'] . ' added to your order.';
        break;
    case 'set':
        cart_set($id, (int)val('quantity'));
        break;
    case 'clear':
        $_SESSION['cart'] = [];
        $message = 'Your order was cleared.';
        break;
}

if (wants_json()) {
    // The panel's own forms should return to the page the shopper is on, not to this endpoint.
    $_SERVER['REQUEST_URI'] = url($return);
    $cart = cart_data();
    ob_start();
    require ROOT . '/app/views/cart-panel.php';
    json_out(['count' => $cart['count'], 'message' => $message, 'panel' => ob_get_clean()]);
}
if ($message) flash($message, str_starts_with($message, 'Sorry') ? 'error' : 'success');
go($return);
