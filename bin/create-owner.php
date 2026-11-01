<?php
// Creates (or promotes) the restaurant owner account. Pass details as environment variables:
//   OWNER_NAME="..." OWNER_EMAIL="..." OWNER_PASSWORD="..." OWNER_PHONE="..." php bin/create-owner.php
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../app/bootstrap.php';

$name = trim((string)getenv('OWNER_NAME'));
$email = strtolower(trim((string)getenv('OWNER_EMAIL')));
$password = (string)getenv('OWNER_PASSWORD');
$phone = trim((string)getenv('OWNER_PHONE')) ?: setting('phone');

if (!text_valid($name, 2, 120) || !email_valid($email) || strlen($password) < 12 || strlen($password) > 72) {
    fwrite(STDERR, "Set OWNER_NAME, OWNER_EMAIL and OWNER_PASSWORD (12–72 characters).\n");
    exit(1);
}
$existing = one('SELECT id FROM users WHERE email = ?', [$email]);
if ($existing) {
    q("UPDATE users SET role = 'owner', full_name = ?, password_hash = ?, verified_at = COALESCE(verified_at, ?), session_version = session_version + 1 WHERE id = ?",
        [$name, password_hash($password, PASSWORD_DEFAULT), now(), $existing['id']]);
    echo "Existing account $email is now the restaurant owner.\n";
} else {
    q("INSERT INTO users (full_name, email, phone, password_hash, role, verified_at, created_at) VALUES (?, ?, ?, ?, 'owner', ?, ?)",
        [$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT), now(), now()]);
    echo "Owner account created for $email. Sign in at " . url('login.php') . "\n";
}
