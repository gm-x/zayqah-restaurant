<?php
require __DIR__ . '/app/bootstrap.php';

if (post()) {
    $cart = $_SESSION['cart'] ?? [];
    session_regenerate_id(true);
    $_SESSION = ['cart' => $cart];
    flash('You have been signed out.');
}
go('index.php');
