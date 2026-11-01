<?php
require __DIR__ . '/app/bootstrap.php';

$token = param('token');
if (!preg_match('/^[a-f0-9]{64}$/', $token)) fail(400, 'This confirmation link is not valid.');

db()->beginTransaction();
$change = one('SELECT ec.* FROM email_changes ec JOIN users u ON u.id = ec.user_id
    WHERE ec.token_hash = ? AND ec.expires_at > ? AND ec.session_version = u.session_version AND ec.old_email = u.email FOR UPDATE',
    [hash('sha256', $token), now()]);
if (!$change) {
    db()->rollBack();
    fail(400, 'This confirmation link has expired or is no longer valid.');
}
if (one('SELECT id FROM users WHERE email = ? AND id <> ?', [$change['new_email'], $change['user_id']])) {
    q('DELETE FROM email_changes WHERE user_id = ?', [$change['user_id']]);
    db()->commit();
    fail(409, 'That email address is already used by another account.');
}
q('UPDATE users SET email = ?, verified_at = ?, session_version = session_version + 1 WHERE id = ?', [$change['new_email'], now(), $change['user_id']]);
q('DELETE FROM email_changes WHERE user_id = ?', [$change['user_id']]);
queue_mail($change['old_email'], 'Your email was changed · ' . setting('name'), "The email address on your account was changed to {$change['new_email']}.\n\nIf this wasn't you, contact us at " . setting('email') . '.');
db()->commit();
mail_after_commit();
$_SESSION = [];
session_regenerate_id(true);
flash('Your new email is confirmed. Please sign in with it.');
go('login.php');
