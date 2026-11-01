<?php
require __DIR__ . '/app/bootstrap.php';

if (user()) go(is_owner() ? 'owner/index.php' : 'index.php');
$title = 'Sign in';
$errors = [];

if (post()) {
    $email = strtolower(val('email'));
    if (!limit('login', 10, 900)) {
        $errors[] = 'Too many sign-in attempts. Please wait 15 minutes and try again.';
    } else {
        $u = one('SELECT * FROM users WHERE email = ?', [$email]);
        if (!$u || !password_verify(raw('password'), $u['password_hash'])) {
            $errors[] = 'That email and password combination is not correct.';
        } else {
            sign_in($u);
            if (!$u['verified_at']) go('verify-email.php');
            $next = local_return((string)($_SESSION['after_login'] ?? ''), $u['role'] === 'owner' ? 'owner/index.php' : 'index.php');
            unset($_SESSION['after_login']);
            go($next);
        }
    }
}

require ROOT . '/app/views/header.php';
?>
<main id="main" class="auth">
    <div class="auth-card card">
        <h1>Welcome back</h1>
        <p class="muted">Sign in to order, track deliveries and see your order history.</p>
        <?php errors($errors); ?>
        <form method="post">
            <?= csrf() ?>
            <?php field('email', 'Email', 'email', 'required maxlength="254" autocomplete="email" autofocus'); ?>
            <?php field('password', 'Password', 'password', 'required maxlength="72" autocomplete="current-password"'); ?>
            <div class="row-between"><span></span><a class="small-link" href="<?= e(url('forgot-password.php')) ?>">Forgot password?</a></div>
            <button class="btn btn-primary btn-block btn-lg" type="submit">Sign in</button>
        </form>
        <p class="auth-switch">New here? <a href="<?= e(url('signup.php')) ?>">Create an account</a></p>
    </div>
</main>
<?php require ROOT . '/app/views/footer.php'; ?>
