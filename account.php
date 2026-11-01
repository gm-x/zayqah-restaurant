<?php
require __DIR__ . '/app/bootstrap.php';

$u = require_user(false);
$title = 'Account settings';
$errors = [];
$action = is_post() ? val('action') : '';

if (post()) {
    if ($action === 'profile') {
        $name = val('full_name');
        $phone = val('phone');
        $address = val('delivery_address');
        $point = coordinates(val('latitude'), val('longitude'));
        if (!text_valid($name, 2, 120)) $errors[] = 'Enter your full name.';
        if (!phone_valid($phone)) $errors[] = 'Enter a valid phone number.';
        if (!text_valid($address, 0, 1000)) $errors[] = 'Keep your address under 1000 characters.';
        if (!$errors) {
            q('UPDATE users SET full_name = ?, phone = ?, delivery_address = ?, latitude = ?, longitude = ? WHERE id = ?',
                [$name, $phone, $address ?: null, $point[0] ?? null, $point[1] ?? null, $u['id']]);
            flash('Your details were saved.');
            go('account.php');
        }
    } elseif ($action === 'email') {
        $email = strtolower(val('new_email'));
        if (!limit('change-email:' . $u['id'], 5, 3600)) $errors[] = 'Too many email change requests. Please try again in an hour.';
        if (!email_valid($email)) $errors[] = 'Enter a valid email address.';
        elseif ($email === $u['email']) $errors[] = 'That is already your email address.';
        elseif (one('SELECT id FROM users WHERE email = ?', [$email])) $errors[] = 'That email address is already registered.';
        elseif (one('SELECT user_id FROM email_changes WHERE new_email = ? AND user_id <> ? AND expires_at > ?', [$email, $u['id'], now()])) $errors[] = 'That email address is waiting to be confirmed by another account.';
        if (!password_verify(raw('email_password'), $u['password_hash'])) $errors[] = 'Your current password is incorrect.';
        if (!$errors) {
            $token = bin2hex(random_bytes(32));
            q('DELETE FROM email_changes WHERE user_id = ? OR (new_email = ? AND expires_at <= ?)', [$u['id'], $email, now()]);
            q('INSERT INTO email_changes (user_id, new_email, old_email, session_version, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$u['id'], $email, $u['email'], $u['session_version'], hash('sha256', $token), gmdate('Y-m-d H:i:s', time() + 3600), now()]);
            queue_mail($email, 'Confirm your new email · ' . setting('name'),
                "Hello {$u['full_name']},\n\nConfirm your new email address within one hour:\n" . url('confirm-email.php?token=' . $token) . "\n\nYour current email stays active until you confirm.");
            mail_after_commit();
            flash('Check your new inbox for a confirmation link.');
            go('account.php#email');
        }
    } elseif ($action === 'cancel-email') {
        q('DELETE FROM email_changes WHERE user_id = ?', [$u['id']]);
        flash('Pending email change cancelled.');
        go('account.php#email');
    } elseif ($action === 'password') {
        if (!limit('change-password:' . $u['id'], 5, 3600)) $errors[] = 'Too many attempts. Please try again in an hour.';
        if (!password_verify(raw('current_password'), $u['password_hash'])) $errors[] = 'Your current password is incorrect.';
        $errors = array_merge($errors, password_errors(raw('password'), raw('password_confirm')));
        if (!$errors) {
            q('UPDATE users SET password_hash = ?, session_version = session_version + 1 WHERE id = ?', [password_hash(raw('password'), PASSWORD_DEFAULT), $u['id']]);
            q('DELETE FROM email_changes WHERE user_id = ?', [$u['id']]);
            $_SESSION['version'] = (int)$u['session_version'] + 1;
            session_regenerate_id(true);
            flash('Password updated. Your other sessions were signed out.');
            go('account.php#password');
        }
    }
}

$pending = one('SELECT new_email, expires_at FROM email_changes WHERE user_id = ? AND expires_at > ?', [$u['id'], now()]);
$isProfile = $action === 'profile';
require ROOT . '/app/views/header.php';
?>
<main id="main" class="section">
    <div class="container">
        <h1>Account settings</h1>
        <?php errors($errors); ?>
        <div class="settings-layout">
            <nav class="settings-nav" aria-label="Settings sections">
                <a href="#profile"><?= icon('user', 16) ?> Personal details</a>
                <a href="#email"><?= icon('mail', 16) ?> Email address</a>
                <a href="#password"><?= icon('settings', 16) ?> Password</a>
                <a href="<?= e(url('orders.php')) ?>"><?= icon('receipt', 16) ?> My orders</a>
            </nav>
            <div class="settings-body">
                <section class="card" id="profile">
                    <h2>Personal details</h2>
                    <form method="post">
                        <?= csrf() ?><input type="hidden" name="action" value="profile">
                        <div class="form-grid">
                            <?php field('full_name', 'Full name', 'text', 'required maxlength="120" autocomplete="name"', $isProfile ? val('full_name') : $u['full_name']); ?>
                            <?php field('phone', 'Phone', 'tel', 'required maxlength="25" autocomplete="tel"', $isProfile ? val('phone') : $u['phone']); ?>
                        </div>
                        <?php area('delivery_address', 'Saved delivery address (optional)', $isProfile ? val('delivery_address') : (string)$u['delivery_address'], 'rows="2" maxlength="1000" autocomplete="street-address"'); ?>
                        <?php location_picker($isProfile ? val('latitude') : (string)$u['latitude'], $isProfile ? val('longitude') : (string)$u['longitude'], 'Saved delivery pin'); ?>
                        <button class="btn btn-primary" type="submit">Save details</button>
                    </form>
                </section>
                <section class="card" id="email">
                    <h2>Email address</h2>
                    <p>Current email: <strong><?= e($u['email']) ?></strong> <?= $u['verified_at'] ? '<span class="badge badge-completed">Verified</span>' : '<span class="badge badge-placed">Not verified</span>' ?></p>
                    <?php if ($pending): ?>
                        <div class="alert alert-info">Waiting for confirmation of <strong><?= e($pending['new_email']) ?></strong> (link expires <?= e(local_time($pending['expires_at'], 'g:i a')) ?>).
                            <form method="post" class="inline"><?= csrf() ?><input type="hidden" name="action" value="cancel-email"><button class="btn btn-ghost btn-sm" type="submit">Cancel</button></form>
                        </div>
                    <?php endif ?>
                    <form method="post">
                        <?= csrf() ?><input type="hidden" name="action" value="email">
                        <div class="form-grid">
                            <?php field('new_email', 'New email', 'email', 'required maxlength="254" autocomplete="email"', $action === 'email' ? val('new_email') : ''); ?>
                            <?php field('email_password', 'Current password', 'password', 'required maxlength="72" autocomplete="current-password"'); ?>
                        </div>
                        <button class="btn btn-primary" type="submit">Send confirmation link</button>
                    </form>
                </section>
                <section class="card" id="password">
                    <h2>Password</h2>
                    <form method="post">
                        <?= csrf() ?><input type="hidden" name="action" value="password">
                        <?php field('current_password', 'Current password', 'password', 'required maxlength="72" autocomplete="current-password"'); ?>
                        <div class="form-grid">
                            <?php field('password', 'New password', 'password', 'required minlength="8" maxlength="72" autocomplete="new-password"'); ?>
                            <?php field('password_confirm', 'Confirm new password', 'password', 'required minlength="8" maxlength="72" autocomplete="new-password"'); ?>
                        </div>
                        <button class="btn btn-primary" type="submit">Update password</button>
                    </form>
                </section>
            </div>
        </div>
    </div>
</main>
<?php require ROOT . '/app/views/footer.php'; ?>
