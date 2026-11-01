<?php
require __DIR__ . '/app/bootstrap.php';

$token = param('token');
if (!token_record($token, 'reset')) fail(400, 'This reset link has expired or was already used. Please request a new one.');
$title = 'Choose a new password';
$errors = [];

if (post()) {
    $errors = password_errors(raw('password'), raw('password_confirm'));
    if (!$errors) {
        db()->beginTransaction();
        $record = consume_token($token, 'reset');
        if (!$record) {
            db()->rollBack();
            fail(400, 'This reset link has expired or was already used.');
        }
        // Bumping the session version signs out every other session.
        q('UPDATE users SET password_hash = ?, session_version = session_version + 1, verified_at = COALESCE(verified_at, ?) WHERE id = ?',
            [password_hash(raw('password'), PASSWORD_DEFAULT), now(), $record['user_id']]);
        q('DELETE FROM email_changes WHERE user_id = ?', [$record['user_id']]);
        db()->commit();
        flash('Password updated. Please sign in.');
        go('login.php');
    }
}

require ROOT . '/app/views/header.php';
?>
<main id="main" class="auth">
    <div class="auth-card card">
        <h1>Choose a new password</h1>
        <?php errors($errors); ?>
        <form method="post">
            <?= csrf() ?>
            <?php field('password', 'New password', 'password', 'required minlength="8" maxlength="72" autocomplete="new-password" autofocus'); ?>
            <?php field('password_confirm', 'Confirm new password', 'password', 'required minlength="8" maxlength="72" autocomplete="new-password"'); ?>
            <button class="btn btn-primary btn-block" type="submit">Update password</button>
        </form>
    </div>
</main>
<?php require ROOT . '/app/views/footer.php'; ?>
