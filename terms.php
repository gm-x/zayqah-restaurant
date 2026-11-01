<?php
require __DIR__ . '/app/bootstrap.php';

$title = 'Terms of service';
require ROOT . '/app/views/header.php';
?>
<main id="main" class="section">
    <div class="container narrow prose">
        <h1>Terms of service</h1>
        <p class="hint">Template text — have this reviewed before you launch.</p>
        <h2>Ordering</h2>
        <p>An order is accepted once we confirm it. Prices are shown on the menu and at checkout, including any delivery fee. You can cancel an order online until we confirm it; after that, please call us.</p>
        <h2>Payment</h2>
        <p>Payment is taken on collection or delivery by cash or card.</p>
        <h2>Allergens</h2>
        <p>Ingredients and allergens are listed on each dish. Our kitchen handles common allergens, so we cannot guarantee any dish is completely free from traces. Please tell us about allergies in your order notes.</p>
        <h2>Delivery</h2>
        <p>Delivery times are estimates. Please make sure your address and map pin are accurate and that someone is available to receive the order.</p>
        <h2>Contact</h2>
        <p><?= e(setting('name')) ?>, <?= e(setting('address')) ?> · <?= e(setting('phone')) ?> · <?= e(setting('email')) ?></p>
    </div>
</main>
<?php require ROOT . '/app/views/footer.php'; ?>
