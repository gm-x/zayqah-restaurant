<?php
require __DIR__ . '/app/bootstrap.php';

$token = param('token');
if ($token !== '') {
    $record = consume_token($token, 'verify');
    if (!$record) fail(400, 'This verification link has expired or was already used. Sign in to request a new one.');
    q('UPDATE users SET verified_at = COALESCE(verified_at, ?) WHERE id = ?', [now(), $record['user_id']]);
    flash('Your email is verified. Welcome!');
    go(user() ? 'menu.php' : 'login.php');
}

$u = require_user(false);
if ($u['verified_at']) go('index.php');
if (post()) {
    if (limit('resend-verify:' . $u['id'], 3, 3600)) {
        verification_mail($u);
        mail_after_commit();
        flash('A new verification link is on its way.');
    } else {
        flash('Please wait a while before requesting another link.', 'error');
    }
    go('verify-email.php');
}
$title = 'Verify your email';

require ROOT . '/app/views/header.php';
?>
<main id="main" class="auth">
    <div class="auth-card card center">
        <span class="success-icon"><?= icon('mail', 28) ?></span>
        <h1>Check your inbox</h1>
        <p class="muted">We sent a verification link to <strong><?= e($u['email']) ?></strong>. Open it to activate your account and start ordering.</p>
        <form method="post"><?= csrf() ?><button class="btn btn-ghost" type="submit">Send a new link</button></form>
        <p class="hint">Wrong address? You can change it in <a href="<?= e(url('account.php')) ?>">account settings</a>.</p>
    </div>
</main>
<?php require ROOT . '/app/views/footer.php'; ?>
