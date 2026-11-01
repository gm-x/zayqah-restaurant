<?php
require __DIR__ . '/../app/bootstrap.php';

require_owner();
$errors = [];
$action = is_post() ? val('action') : '';
$timePattern = '/^([01]\d|2[0-3]):[0-5]\d$/';

if (post()) {
    if ($action === 'profile') {
        $values = [
            'name'    => val('name'),
            'tagline' => val('tagline'),
            'about'   => val('about'),
            'address' => val('address'),
            'phone'   => val('phone'),
            'email'   => strtolower(val('email')),
        ];
        if (!text_valid($values['name'], 2, 80)) $errors[] = 'Enter the restaurant name (2–80 characters).';
        if (!text_valid($values['tagline'], 2, 120)) $errors[] = 'Enter a short tagline (2–120 characters).';
        if (!text_valid($values['about'], 20, 5000)) $errors[] = 'Write an "About us" text of 20–5000 characters.';
        if (!text_valid($values['address'], 5, 300)) $errors[] = 'Enter the restaurant address.';
        if (!phone_valid($values['phone'])) $errors[] = 'Enter a valid phone number.';
        if (!email_valid($values['email'])) $errors[] = 'Enter a valid contact email.';
        $point = coordinates(val('latitude'), val('longitude'));
        $values['latitude'] = $point ? (string)$point[0] : '';
        $values['longitude'] = $point ? (string)$point[1] : '';
        if (!$errors) {
            try {
                $logo = upload('logo', 'logo', false);
                if ($logo) $values['logo_id'] = $logo;
                if (val('remove_logo') === '1' && !$logo) $values['logo_id'] = '';
                save_settings($values);
                flash('Restaurant profile saved.');
                go('owner/settings.php');
            } catch (InvalidArgumentException $ex) {
                $errors[] = $ex->getMessage();
            }
        }
    } elseif ($action === 'ordering') {
        $fee = parse_money(val('delivery_fee'));
        $min = parse_money(val('min_order'));
        $prep = (int)val('prep_minutes');
        $values = [
            'accepting_orders' => val('accepting_orders') === '1' ? '1' : '0',
            'pickup_enabled'   => val('pickup_enabled') === '1' ? '1' : '0',
            'delivery_enabled' => val('delivery_enabled') === '1' ? '1' : '0',
            'currency'         => val('currency'),
            'timezone'         => val('timezone'),
        ];
        if ($values['pickup_enabled'] === '0' && $values['delivery_enabled'] === '0') $errors[] = 'Enable collection, delivery or both.';
        if ($fee === null) $errors[] = 'Enter a valid delivery fee (0 for free delivery).';
        if ($min === null) $errors[] = 'Enter a valid minimum order (0 for none).';
        if ($prep < 5 || $prep > 240) $errors[] = 'Preparation time must be between 5 and 240 minutes.';
        if (!text_valid($values['currency'], 1, 4)) $errors[] = 'Enter a currency symbol such as £, $ or €.';
        if (!in_array($values['timezone'], DateTimeZone::listIdentifiers(), true)) $errors[] = 'Choose a valid time zone.';
        if (!$errors) {
            save_settings($values + ['delivery_fee' => $fee, 'min_order' => $min, 'prep_minutes' => $prep]);
            flash('Ordering settings saved.');
            go('owner/settings.php#ordering');
        }
    } elseif ($action === 'hours') {
        $hours = [];
        foreach (WEEKDAYS as $d => $dayName) {
            $open = val("open_$d");
            $close = val("close_$d");
            $closed = val("closed_$d") === '1';
            if (!$closed && (!preg_match($timePattern, $open) || !preg_match($timePattern, $close) || $open === $close)) {
                $errors[] = "Enter valid opening and closing times for $dayName.";
            }
            $hours[$d] = ['open' => $open ?: '11:00', 'close' => $close ?: '22:00', 'closed' => $closed];
        }
        if (!$errors) {
            save_settings(['hours' => json_encode($hours)]);
            flash('Opening hours saved.');
            go('owner/settings.php#hours');
        }
    }
}

$s = settings();
$hours = opening_hours();
$v = fn(string $key, string $section) => $action === $section ? val($key) : (string)$s[$key];
$title = 'Restaurant settings';

require ROOT . '/app/views/owner-start.php';
?>
<div class="owner-head"><div><h1>Restaurant settings</h1><p class="muted">Everything customers see about your restaurant.</p></div></div>
<?php errors($errors); ?>
<div class="settings-layout">
    <nav class="settings-nav" aria-label="Settings sections">
        <a href="#profile"><?= icon('store', 16) ?> Profile</a>
        <a href="#ordering"><?= icon('bag', 16) ?> Ordering &amp; delivery</a>
        <a href="#hours"><?= icon('clock', 16) ?> Opening hours</a>
    </nav>
    <div class="settings-body">
        <section class="card" id="profile">
            <h2>Restaurant profile</h2>
            <form method="post" enctype="multipart/form-data">
                <?= csrf() ?><input type="hidden" name="action" value="profile">
                <div class="form-grid">
                    <?php field('name', 'Restaurant name', 'text', 'required maxlength="80"', $v('name', 'profile')); ?>
                    <?php field('tagline', 'Tagline', 'text', 'required maxlength="120"', $v('tagline', 'profile')); ?>
                </div>
                <?php area('about', 'About us', $v('about', 'profile'), 'required rows="6" maxlength="5000"', 'Shown on the home and About pages. Leave a blank line between paragraphs.'); ?>
                <?php area('address', 'Address', $v('address', 'profile'), 'required rows="2" maxlength="300"'); ?>
                <div class="form-grid">
                    <?php field('phone', 'Phone', 'tel', 'required maxlength="25"', $v('phone', 'profile')); ?>
                    <?php field('email', 'Contact email', 'email', 'required maxlength="254"', $v('email', 'profile')); ?>
                </div>
                <?php location_picker($v('latitude', 'profile'), $v('longitude', 'profile'), 'Restaurant location (shown on the contact page)'); ?>
                <div class="logo-row">
                    <?php if ($s['logo_id']): ?><img src="<?= e(image_url($s['logo_id'])) ?>" alt="Current logo" width="64" height="64"><?php endif ?>
                    <?php field('logo', $s['logo_id'] ? 'Replace logo' : 'Logo (optional)', 'file', 'accept="image/jpeg,image/png,image/webp"', '', 'Square image, shown in the site header.'); ?>
                    <?php if ($s['logo_id']): ?><label class="check"><input type="checkbox" name="remove_logo" value="1"> Remove logo</label><?php endif ?>
                </div>
                <button class="btn btn-primary" type="submit">Save profile</button>
            </form>
        </section>

        <section class="card" id="ordering">
            <h2>Ordering &amp; delivery</h2>
            <form method="post">
                <?= csrf() ?><input type="hidden" name="action" value="ordering">
                <?php $chk = fn(string $key) => ($action === 'ordering' ? val($key) === '1' : $s[$key] === '1') ? 'checked' : ''; ?>
                <label class="check"><input type="checkbox" name="accepting_orders" value="1" <?= $chk('accepting_orders') ?>> Accept online orders (during opening hours)</label>
                <label class="check"><input type="checkbox" name="pickup_enabled" value="1" <?= $chk('pickup_enabled') ?>> Offer collection</label>
                <label class="check"><input type="checkbox" name="delivery_enabled" value="1" <?= $chk('delivery_enabled') ?>> Offer delivery</label>
                <div class="form-grid">
                    <?php field('delivery_fee', 'Delivery fee (' . $s['currency'] . ')', 'number', 'required min="0" max="999" step="0.01"', $action === 'ordering' ? val('delivery_fee') : number_format((int)$s['delivery_fee'] / 100, 2, '.', '')); ?>
                    <?php field('min_order', 'Minimum order (' . $s['currency'] . ')', 'number', 'required min="0" max="9999" step="0.01"', $action === 'ordering' ? val('min_order') : number_format((int)$s['min_order'] / 100, 2, '.', '')); ?>
                    <?php field('prep_minutes', 'Typical preparation time (minutes)', 'number', 'required min="5" max="240"', $v('prep_minutes', 'ordering')); ?>
                    <?php field('currency', 'Currency symbol', 'text', 'required maxlength="4"', $v('currency', 'ordering')); ?>
                </div>
                <div class="field">
                    <label for="timezone">Time zone</label>
                    <select id="timezone" name="timezone">
                        <?php foreach (DateTimeZone::listIdentifiers() as $tz): ?><option <?= $v('timezone', 'ordering') === $tz ? 'selected' : '' ?>><?= e($tz) ?></option><?php endforeach ?>
                    </select>
                </div>
                <button class="btn btn-primary" type="submit">Save ordering settings</button>
            </form>
        </section>

        <section class="card" id="hours">
            <h2>Opening hours</h2>
            <p class="hint">Online orders are accepted only during these hours. A closing time earlier than the opening time means after midnight.</p>
            <form method="post">
                <?= csrf() ?><input type="hidden" name="action" value="hours">
                <div class="hours-editor">
                    <?php foreach (WEEKDAYS as $d => $dayName):
                        $h = $hours[$d];
                        $closed = $action === 'hours' ? val("closed_$d") === '1' : !empty($h['closed']); ?>
                        <div class="hours-row">
                            <strong><?= e($dayName) ?></strong>
                            <input type="time" name="open_<?= $d ?>" value="<?= e($action === 'hours' ? val("open_$d") : $h['open']) ?>" aria-label="<?= e($dayName) ?> opens">
                            <span>to</span>
                            <input type="time" name="close_<?= $d ?>" value="<?= e($action === 'hours' ? val("close_$d") : $h['close']) ?>" aria-label="<?= e($dayName) ?> closes">
                            <label class="check"><input type="checkbox" name="closed_<?= $d ?>" value="1" <?= $closed ? 'checked' : '' ?>> Closed</label>
                        </div>
                    <?php endforeach ?>
                </div>
                <button class="btn btn-primary" type="submit">Save opening hours</button>
            </form>
        </section>
    </div>
</div>
<?php require ROOT . '/app/views/owner-end.php'; ?>
