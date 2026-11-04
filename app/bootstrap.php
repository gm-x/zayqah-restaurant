<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
$config = require __DIR__ . '/config.php';
date_default_timezone_set('UTC'); // stored times are UTC; local_time() converts for display

if ($config['env'] === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

function cfg(string $key): mixed
{
    global $config;
    return $config[$key] ?? null;
}

if (PHP_SAPI !== 'cli') {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('restaurant_session');
    session_set_cookie_params([
        'httponly' => true,
        'secure'   => str_starts_with(cfg('url'), 'https://'),
        'samesite' => 'Lax',
        'path'     => parse_url(cfg('url'), PHP_URL_PATH) ?: '/',
    ]);
    session_start();
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // Stripe (js.stripe.com) hosts the Apple Pay / Google Pay / card fields in its own frames.
    header("Content-Security-Policy: default-src 'self'; img-src 'self' https://images.unsplash.com https://*.tile.openstreetmap.org https://tile.openstreetmap.org https://*.stripe.com data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self' https://js.stripe.com https://*.js.stripe.com; frame-src https://js.stripe.com https://*.js.stripe.com https://hooks.stripe.com; connect-src 'self' https://api.stripe.com; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    header('Cache-Control: no-store');
}

/* ---------- Database ---------- */

function db(): PDO
{
    static $pdo;
    if ($pdo) return $pdo;
    $dsn = 'mysql:host=' . cfg('db_host') . ';dbname=' . cfg('db_name') . ';charset=utf8mb4';
    $pdo = new PDO($dsn, cfg('db_user'), cfg('db_password'), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function one(string $sql, array $params = []): ?array
{
    return q($sql, $params)->fetch() ?: null;
}

function all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function scalar(string $sql, array $params = []): mixed
{
    return q($sql, $params)->fetchColumn();
}

function now(): string
{
    return gmdate('Y-m-d H:i:s');
}

/* ---------- Requests and output ---------- */

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return cfg('url') . '/' . ltrim($path, '/');
}

function go(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function val(string $key): string
{
    return is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
}

function raw(string $key): string
{
    return is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
}

function param(string $key): string
{
    return is_string($_GET[$key] ?? null) ? trim($_GET[$key]) : '';
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** True for a POST with a valid CSRF token; stops the request for a POST without one. */
function post(): bool
{
    if (!is_post()) return false;
    $sent = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    if (empty($_SESSION['csrf']) || !is_string($sent) || !hash_equals($_SESSION['csrf'], $sent)) {
        fail(403, 'Your session expired. Go back, reload the page and try again.');
    }
    return true;
}

function wants_json(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function fail(int $status, string $message): never
{
    http_response_code($status);
    $title = $status === 404 ? 'Not found' : 'Something went wrong';
    require ROOT . '/app/views/header.php';
    echo '<main id="main" class="section"><div class="container narrow"><div class="empty-state">'
        . '<span class="empty-code">' . e($status) . '</span><h1>' . e($title) . '</h1><p>' . e($message) . '</p>'
        . '<a class="btn btn-primary" href="' . e(url()) . '">Back to home</a></div></div></main>';
    require ROOT . '/app/views/footer.php';
    exit;
}

/* ---------- Validation and form fields ---------- */

function email_valid(string $email): bool
{
    return strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function text_valid(string $text, int $min, int $max): bool
{
    return mb_strlen($text) >= $min && mb_strlen($text) <= $max;
}

function phone_valid(string $phone): bool
{
    return (bool)preg_match('/^\+?[0-9 ()-]{8,25}$/', $phone) && strlen(preg_replace('/\D/', '', $phone)) >= 8;
}

function password_errors(string $password, string $confirm): array
{
    $errors = [];
    if (strlen($password) < 8 || strlen($password) > 72) $errors[] = 'Use a password between 8 and 72 characters.';
    if ($password !== $confirm) $errors[] = 'The passwords do not match.';
    return $errors;
}

function errors(array $errors): void
{
    if (!$errors) return;
    echo '<div class="alert alert-error" role="alert"><ul>';
    foreach ($errors as $error) echo '<li>' . e($error) . '</li>';
    echo '</ul></div>';
}

/** Value for a form field: the submitted value after a POST, otherwise the stored fallback. */
function old(string $key, mixed $fallback = ''): string
{
    return is_post() ? val($key) : (string)($fallback ?? '');
}

function field(string $name, string $label, string $type = 'text', string $attrs = '', ?string $value = null, string $hint = ''): void
{
    $value ??= $type === 'password' ? '' : val($name);
    echo '<div class="field"><label for="' . e($name) . '">' . e($label) . '</label>'
        . '<input id="' . e($name) . '" name="' . e($name) . '" type="' . e($type) . '" value="' . e($value) . '" ' . $attrs . '>'
        . ($hint ? '<small class="hint">' . e($hint) . '</small>' : '') . '</div>';
}

function area(string $name, string $label, string $value = '', string $attrs = '', string $hint = ''): void
{
    echo '<div class="field"><label for="' . e($name) . '">' . e($label) . '</label>'
        . '<textarea id="' . e($name) . '" name="' . e($name) . '" ' . $attrs . '>' . e($value) . '</textarea>'
        . ($hint ? '<small class="hint">' . e($hint) . '</small>' : '') . '</div>';
}

/* ---------- Accounts ---------- */

function user(): ?array
{
    static $cached = false;
    if ($cached !== false) return $cached;
    if (empty($_SESSION['user_id'])) return $cached = null;
    $u = one('SELECT * FROM users WHERE id = ?', [$_SESSION['user_id']]);
    if (!$u || (int)$u['session_version'] !== (int)($_SESSION['version'] ?? -1)) {
        unset($_SESSION['user_id'], $_SESSION['version']);
        return $cached = null;
    }
    return $cached = $u;
}

function is_owner(?array $u = null): bool
{
    $u ??= user();
    return $u !== null && $u['role'] === 'owner';
}

function require_user(bool $verified = true): array
{
    $u = user();
    if (!$u) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        go('login.php');
    }
    if ($verified && !$u['verified_at']) go('verify-email.php');
    return $u;
}

function require_owner(): array
{
    $u = require_user();
    if (!is_owner($u)) fail(403, 'This area is for the restaurant owner.');
    return $u;
}

function sign_in(array $u): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$u['id'];
    $_SESSION['version'] = (int)$u['session_version'];
}

/** Database-backed rate limit; does not trust forwarded client IP headers. */
function limit(string $scope, int $max = 10, int $seconds = 900): bool
{
    $key = hash('sha256', $scope . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'cli'));
    q('DELETE FROM attempts WHERE created_at < ?', [gmdate('Y-m-d H:i:s', time() - 86400)]);
    $count = (int)scalar('SELECT COUNT(*) FROM attempts WHERE rate_key = ? AND created_at > ?', [$key, gmdate('Y-m-d H:i:s', time() - $seconds)]);
    if ($count >= $max) return false;
    q('INSERT INTO attempts (rate_key, created_at) VALUES (?, ?)', [$key, now()]);
    return true;
}

function token_issue(int $userId, string $purpose): string
{
    $token = bin2hex(random_bytes(32));
    q('DELETE FROM tokens WHERE user_id = ? AND purpose = ?', [$userId, $purpose]);
    q('INSERT INTO tokens (user_id, purpose, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?, ?)', [
        $userId, $purpose, hash('sha256', $token), gmdate('Y-m-d H:i:s', time() + ($purpose === 'verify' ? 86400 : 3600)), now(),
    ]);
    return $token;
}

function token_record(string $token, string $purpose): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    return one('SELECT * FROM tokens WHERE token_hash = ? AND purpose = ? AND expires_at > ?', [hash('sha256', $token), $purpose, now()]);
}

function consume_token(string $token, string $purpose): ?array
{
    $record = token_record($token, $purpose);
    if (!$record) return null;
    // The conditional delete keeps concurrent use of one token to a single success.
    return q('DELETE FROM tokens WHERE id = ? AND expires_at > ?', [$record['id'], now()])->rowCount() === 1 ? $record : null;
}

/* ---------- Email ---------- */

function queue_mail(string $to, string $subject, string $body): void
{
    q('INSERT INTO outbox (recipient, subject, body, status, created_at) VALUES (?, ?, ?, ?, ?)', [$to, $subject, $body, 'pending', now()]);
}

function send_outbox(): void
{
    if (cfg('env') === 'production' && cfg('mail_transport') !== 'mail') {
        throw new RuntimeException('Production requires MAIL_TRANSPORT=mail and a configured server mail service.');
    }
    foreach (all("SELECT * FROM outbox WHERE status = 'pending' ORDER BY id LIMIT 30") as $m) {
        if (q("UPDATE outbox SET status = 'sending' WHERE id = ? AND status = 'pending'", [$m['id']])->rowCount() !== 1) continue;
        if (cfg('mail_transport') === 'log') {
            $ok = file_put_contents(ROOT . '/storage/mail.log', "\nTO: {$m['recipient']}\n{$m['subject']}\n{$m['body']}\n", FILE_APPEND | LOCK_EX) !== false;
            $status = $ok ? 'logged' : 'failed';
        } else {
            $ok = mail($m['recipient'], $m['subject'], $m['body'], ['From' => cfg('mail_from'), 'Content-Type' => 'text/plain; charset=UTF-8']);
            $status = $ok ? 'sent' : 'failed';
        }
        q('UPDATE outbox SET status = ? WHERE id = ?', [$status, $m['id']]);
    }
}

function mail_after_commit(): void
{
    try {
        send_outbox();
    } catch (Throwable $ex) {
        error_log('Mail delivery failed: ' . $ex->getMessage());
    }
}

function verification_mail(array $u): void
{
    $token = token_issue((int)$u['id'], 'verify');
    queue_mail($u['email'], 'Verify your email for ' . setting('name'),
        "Hello {$u['full_name']},\n\nConfirm your email within 24 hours:\n" . url('verify-email.php?token=' . $token) . "\n\nIf this was not you, ignore this email.");
}

/* ---------- Uploads ---------- */

function upload(string $field, string $kind, bool $required = true): ?string
{
    $f = $_FILES[$field] ?? null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        if ($required) throw new InvalidArgumentException('Please choose an image to upload.');
        return null;
    }
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Images must upload successfully and be under 5 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    $size = @getimagesize($f['tmp_name']);
    if (!$ext || !$size || $size[0] > 8000 || $size[1] > 8000) {
        throw new InvalidArgumentException('Choose a JPG, PNG or WebP image up to 8000 × 8000 pixels.');
    }
    $id = bin2hex(random_bytes(24));
    $path = $id . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], ROOT . '/storage/uploads/' . $path)) {
        throw new RuntimeException('The upload could not be saved.');
    }
    chmod(ROOT . '/storage/uploads/' . $path, 0640);
    q('INSERT INTO files (id, kind, path, mime, created_at) VALUES (?, ?, ?, ?, ?)', [$id, $kind, $path, $mime, now()]);
    return $id;
}

function image_url(?string $id): string
{
    return $id ? url('media.php?id=' . $id) : url('assets/img/dish-placeholder.svg');
}

/* ---------- Errors ---------- */

set_exception_handler(function (Throwable $ex): void {
    try {
        if (db()->inTransaction()) db()->rollBack();
    } catch (Throwable) {
    }
    error_log((string)$ex);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $ex->getMessage() . "\n");
        exit(1);
    }
    fail(500, 'The request could not be completed. Please try again.');
});

require_once ROOT . '/app/restaurant.php';
require_once ROOT . '/app/orders.php';
require_once ROOT . '/app/payments.php';
require_once ROOT . '/app/icons.php';
