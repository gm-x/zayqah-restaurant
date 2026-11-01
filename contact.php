<?php
require __DIR__ . '/app/bootstrap.php';

$u = user();
$title = 'Contact us';
$errors = [];
$subjects = ['order' => 'A question about my order', 'booking' => 'Table booking or event', 'feedback' => 'Feedback', 'other' => 'Something else'];

if (post()) {
    $name = $u ? $u['full_name'] : val('full_name');
    $email = $u ? $u['email'] : strtolower(val('email'));
    $phone = $u ? $u['phone'] : val('phone');
    $subject = val('subject');
    $message = val('message');
    if (!text_valid($name, 2, 120)) $errors[] = 'Enter your name.';
    if (!email_valid($email)) $errors[] = 'Enter a valid email address so we can reply.';
    if ($phone !== '' && !phone_valid($phone)) $errors[] = 'Enter a valid phone number or leave it blank.';
    if (!isset($subjects[$subject])) $errors[] = 'Choose what your message is about.';
    if (!text_valid($message, 10, 4000)) $errors[] = 'Write a message of 10 to 4000 characters.';
    if (val('website') !== '') $errors[] = 'Your message could not be sent.'; // honeypot for bots
    if (!$errors && !limit('contact', 5, 3600)) $errors[] = 'You have sent several messages. Please try again in an hour.';
    if (!$errors) {
        q('INSERT INTO contact_messages (user_id, full_name, email, phone, subject, message, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$u['id'] ?? null, $name, $email, $phone, $subject, $message, now()]);
        $id = (int)db()->lastInsertId();
        notify_owners('New message from ' . $name, $subjects[$subject], 'owner/messages.php#message-' . $id, 'contact:' . $id);
        flash('Thanks! Your message has been sent and we will reply soon.');
        go('contact.php');
    }
}

require ROOT . '/app/views/header.php';
?>
<main id="main">
    <section class="page-hero">
        <div class="container"><span class="eyebrow">Get in touch</span><h1>Contact us</h1><p class="lead">Questions about an order, a booking or anything else — we'd love to hear from you.</p></div>
    </section>
    <section class="section section-tight">
        <div class="container contact-layout">
            <div class="card">
                <h2>Send us a message</h2>
                <?php errors($errors); ?>
                <form method="post">
                    <?= csrf() ?>
                    <?php if ($u): ?>
                        <p class="hint">Sending as <strong><?= e($u['full_name']) ?></strong> (<?= e($u['email']) ?>).</p>
                    <?php else: ?>
                        <div class="form-grid">
                            <?php field('full_name', 'Name', 'text', 'required maxlength="120" autocomplete="name"'); ?>
                            <?php field('email', 'Email', 'email', 'required maxlength="254" autocomplete="email"'); ?>
                        </div>
                        <?php field('phone', 'Phone (optional)', 'tel', 'maxlength="25" autocomplete="tel"'); ?>
                    <?php endif ?>
                    <div class="field">
                        <label for="subject">Subject</label>
                        <select id="subject" name="subject" required>
                            <?php foreach ($subjects as $key => $label): ?><option value="<?= e($key) ?>" <?= val('subject') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach ?>
                        </select>
                    </div>
                    <?php area('message', 'Message', val('message'), 'required minlength="10" maxlength="4000" rows="5"'); ?>
                    <div class="hp" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
                    <button class="btn btn-primary" type="submit">Send message</button>
                </form>
            </div>
            <div class="contact-info">
                <div class="card">
                    <h2>Visit us</h2>
                    <p><?= icon('pin', 16) ?> <?= nl2br(e(setting('address'))) ?></p>
                    <p><?= icon('phone', 16) ?> <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', setting('phone'))) ?>"><?= e(setting('phone')) ?></a></p>
                    <p><?= icon('mail', 16) ?> <a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a></p>
                    <?php if (setting('latitude') !== '' && setting('longitude') !== ''): ?>
                        <div class="map map-view" data-view-map data-lat="<?= e(setting('latitude')) ?>" data-lng="<?= e(setting('longitude')) ?>"></div>
                    <?php endif ?>
                    <a class="btn btn-ghost btn-sm" href="https://www.google.com/maps/search/?api=1&amp;query=<?= e(rawurlencode(setting('address'))) ?>" target="_blank" rel="noopener noreferrer"><?= icon('external', 15) ?> Get directions</a>
                </div>
                <div class="card">
                    <h2>Opening hours</h2>
                    <ul class="hours-list">
                        <?php foreach (opening_hours() as $day => $h): ?>
                            <li <?= (int)local_now()->format('N') === (int)$day ? 'class="today"' : '' ?>><span><?= e(WEEKDAYS[$day]) ?></span><span><?= !empty($h['closed']) ? 'Closed' : e(format_hour($h['open']) . ' – ' . format_hour($h['close'])) ?></span></li>
                        <?php endforeach ?>
                    </ul>
                </div>
            </div>
        </div>
    </section>
</main>
<?php require ROOT . '/app/views/footer.php'; ?>
