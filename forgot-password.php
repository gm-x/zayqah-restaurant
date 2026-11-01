<?php
require __DIR__ . '/app/bootstrap.php';

$title = 'Reset your password';
$errors = [];
$sent = false;

if (post()) {
    $email = strtolower(val('email'));
    if (!email_valid($email)) {
        $errors[] = 'Enter a valid email address.';
    } elseif (!limit('forgot', 5, 3600)) {
        $errors[] = 'Too many requests. Please try again in an hour.';
    } else {
        $u = one('SELECT * FROM users WHERE email = ?', [$email]);
        if ($u) {
            $token = token_issue((int)$u['id'], 'reset');
            queue_mail($u['email'], 'Reset your password · ' . setting('name'),
                "Hello {$u['full_name']},\n\nReset your password within one hour:\n" . url('reset-password.php?token=' . $token) . "\n\nIf you didn't ask for this, you can ignore this email.");
            mail_after_commit();
        }
        $sent = true; // the same message whether or not the account exists
    }
}

require ROOT . '/app/views/header.php';
?>
<main id="main" class="auth">
    <div class="auth-card card">
        <h1>Forgot your password?</h1>
        <?php if ($sent): ?>
            <div class="alert alert-success">If an account exists for that email, a reset link is on its way. It expires in one hour.</div>
        <?php else: ?>
            <p class="muted">Enter your email and we'll send you a link to choose a new password.</p>
            <?php errors($errors); ?>
            <form method="post">
                <?= csrf() ?>
                <?php field('email', 'Email', 'email', 'required maxlength="254" autocomplete="email" autofocus'); ?>
                <button class="btn btn-primary btn-block" type="submit">Send reset link</button>
            </form>
        <?php endif ?>
        <p class="auth-switch"><a href="<?= e(url('login.php')) ?>">Back to sign in</a></p>
    </div>
</main>
<?php require ROOT . '/app/views/footer.php'; ?>
