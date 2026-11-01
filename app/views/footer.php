<?php $status = opening_status(); ?>
<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <a class="brand brand-light" href="<?= e(url()) ?>"><span class="brand-mark" aria-hidden="true"><?= e(mb_substr(setting('name'), 0, 1)) ?></span><span><?= e(setting('name')) ?></span></a>
            <p><?= e(setting('tagline')) ?>.</p>
            <span class="open-pill <?= $status['open'] ? 'is-open' : '' ?>"><?= e($status['label']) ?></span>
        </div>
        <div>
            <h3>Visit us</h3>
            <p><?= icon('pin', 16) ?> <?= nl2br(e(setting('address'))) ?></p>
            <p><?= icon('phone', 16) ?> <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', setting('phone'))) ?>"><?= e(setting('phone')) ?></a></p>
            <p><?= icon('mail', 16) ?> <a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a></p>
        </div>
        <div>
            <h3>Opening hours</h3>
            <ul class="hours-list">
                <?php foreach (opening_hours() as $day => $h): ?>
                    <li <?= (int)local_now()->format('N') === (int)$day ? 'class="today"' : '' ?>><span><?= e(substr(WEEKDAYS[$day], 0, 3)) ?></span><span><?= !empty($h['closed']) ? 'Closed' : e(format_hour($h['open']) . ' – ' . format_hour($h['close'])) ?></span></li>
                <?php endforeach ?>
            </ul>
        </div>
        <div>
            <h3>Explore</h3>
            <a href="<?= e(url('menu.php')) ?>">Our menu</a>
            <a href="<?= e(url('track-order.php')) ?>">Track an order</a>
            <a href="<?= e(url('about.php')) ?>">About us</a>
            <a href="<?= e(url('contact.php')) ?>">Contact</a>
            <a href="<?= e(url('privacy.php')) ?>">Privacy</a>
            <a href="<?= e(url('terms.php')) ?>">Terms</a>
        </div>
    </div>
    <div class="container footer-bottom">© <?= date('Y') ?> <?= e(setting('name')) ?>. All rights reserved.</div>
</footer>
</body>
</html>
