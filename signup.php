<?php
require __DIR__ . '/app/bootstrap.php';

if (user()) go('index.php');
$title = 'Create an account';
$errors = [];

if (post()) {
    $name = val('full_name');
    $email = strtolower(val('email'));
    $phone = val('phone');
    if (!limit('signup', 5, 3600)) $errors[] = 'Too many sign-ups from this connection. Please try again later.';
    if (!text_valid($name, 2, 120)) $errors[] = 'Enter your full name.';
    if (!email_valid($email)) $errors[] = 'Enter a valid email address.';
    if (!phone_valid($phone)) $errors[] = 'Enter a valid phone number.';
    $errors = array_merge($errors, password_errors(raw('password'), raw('password_confirm')));
    if (val('terms') !== 'yes') $errors[] = 'Please accept the terms and privacy policy.';
    if (!$errors && one('SELECT id FROM users WHERE email = ?', [$email])) $errors[] = 'An account with this email already exists. Try signing in.';

    if (!$errors) {
        db()->beginTransaction();
        q("INSERT INTO users (full_name, email, phone, password_hash, role, created_at) VALUES (?, ?, ?, ?, 'customer', ?)",
            [$name, $email, $phone, password_hash(raw('password'), PASSWORD_DEFAULT), now()]);
        $u = one('SELECT * FROM users WHERE id = ?', [(int)db()->lastInsertId()]);
        verification_mail($u);
        db()->commit();
        mail_after_commit();
        sign_in($u);
        go('verify-email.php');
    }
}

require ROOT . '/app/views/header.php';
?>
<main id="main" class="auth">
    <div class="auth-card card">
        <h1>Create your account</h1>
        <p class="muted">Order online in seconds and follow every order live.</p>
        <?php errors($errors); ?>
        <form method="post">
            <?= csrf() ?>
            <?php field('full_name', 'Full name', 'text', 'required minlength="2" maxlength="120" autocomplete="name"'); ?>
            <?php field('email', 'Email', 'email', 'required maxlength="254" autocomplete="email"'); ?>
            <?php field('phone', 'Phone', 'tel', 'required maxlength="25" autocomplete="tel"', null, 'We only call about your orders.'); ?>
            <div class="form-grid">
                <?php field('password', 'Password', 'password', 'required minlength="8" maxlength="72" autocomplete="new-password"'); ?>
                <?php field('password_confirm', 'Confirm password', 'password', 'required minlength="8" maxlength="72" autocomplete="new-password"'); ?>
            </div>
            <label class="check"><input type="checkbox" name="terms" value="yes" required <?= val('terms') === 'yes' ? 'checked' : '' ?>> I agree to the <a href="<?= e(url('terms.php')) ?>" target="_blank">terms</a> and <a href="<?= e(url('privacy.php')) ?>" target="_blank">privacy policy</a>.</label>
            <button class="btn btn-primary btn-block btn-lg" type="submit">Create account</button>
        </form>
        <p class="auth-switch">Already have an account? <a href="<?= e(url('login.php')) ?>">Sign in</a></p>
    </div>
</main>
<?php require ROOT . '/app/views/footer.php'; ?>
