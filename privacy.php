<?php
require __DIR__ . '/app/bootstrap.php';

$title = 'Privacy policy';
require ROOT . '/app/views/header.php';
?>
<main id="main" class="section">
    <div class="container narrow prose">
        <h1>Privacy policy</h1>
        <p class="hint">Template text — have this reviewed before you launch.</p>
        <h2>What we collect</h2>
        <p>When you create an account or order, we store your name, email, phone number and, for deliveries, your address and map pin. We also keep your order history and any reviews or messages you send us.</p>
        <h2>How we use it</h2>
        <p>We use your details only to prepare and deliver your orders, contact you about them, and answer your messages. We do not sell your information.</p>
        <h2>Cookies</h2>
        <p>We use a single essential session cookie to keep you signed in and remember your order. We do not use advertising or tracking cookies.</p>
        <h2>Your rights</h2>
        <p>You can update your details at any time in account settings. To request a copy or deletion of your data, contact us at <a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a>.</p>
    </div>
</main>
<?php require ROOT . '/app/views/footer.php'; ?>
