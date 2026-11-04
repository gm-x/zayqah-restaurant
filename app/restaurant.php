<?php
// Restaurant profile, opening hours, menu availability and the session cart.

const WEEKDAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

function setting_defaults(): array
{
    $hours = [];
    foreach (WEEKDAYS as $day => $name) $hours[$day] = ['open' => '11:00', 'close' => '22:00', 'closed' => false];
    return [
        'name'             => 'Zayqah',
        'tagline'          => 'Fresh flavours, cooked to order',
        'about'            => "Every dish on our menu is prepared in our own kitchen by our chefs, using fresh ingredients delivered every morning.\n\nOrder online for collection or delivery, or visit us and enjoy your meal at the restaurant.",
        'address'          => '12 High Street, London',
        'phone'            => '+44 20 0000 0000',
        'email'            => 'hello@example.com',
        'currency'         => '£',
        'currency_code'    => 'gbp', // ISO code Stripe charges in
        'timezone'         => 'Europe/London',
        'accepting_orders' => '1',
        'pay_online'       => '1',   // Apple Pay, Google Pay and cards via Stripe (needs Stripe keys)
        'pay_later'        => '1',   // cash or card on collection/delivery
        'pickup_enabled'   => '1',
        'delivery_enabled' => '1',
        'delivery_fee'     => '250',
        'min_order'        => '0',
        'prep_minutes'     => '25',
        'latitude'         => '',
        'longitude'        => '',
        'logo_id'          => '',
        'hours'            => json_encode($hours),
    ];
}

function settings(): array
{
    static $settings;
    if ($settings !== null) return $settings;
    $settings = setting_defaults();
    try {
        foreach (all('SELECT name, value FROM settings') as $row) $settings[$row['name']] = $row['value'];
    } catch (PDOException) {
        // Before bin/setup.php runs the defaults keep pages renderable.
    }
    return $settings;
}

function setting(string $name): string
{
    return (string)(settings()[$name] ?? '');
}

function save_settings(array $values): void
{
    foreach ($values as $name => $value) {
        q('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)', [$name, (string)$value]);
    }
}

function restaurant_tz(): DateTimeZone
{
    try {
        return new DateTimeZone(setting('timezone') ?: 'UTC');
    } catch (Exception) {
        return new DateTimeZone('UTC');
    }
}

function local_now(): DateTimeImmutable
{
    return new DateTimeImmutable('now', restaurant_tz());
}

function local_time(?string $utc, string $format = 'j M Y, g:i a'): string
{
    if (!$utc) return '—';
    return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(restaurant_tz())->format($format);
}

function money(int $minor): string
{
    return setting('currency') . number_format($minor / 100, 2);
}

/** Parses a decimal amount like "12.50" into minor units, or null when invalid. */
function parse_money(string $value, float $max = 99999.99): ?int
{
    if (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $value) || (float)$value > $max) return null;
    return (int)round((float)$value * 100);
}

function format_hour(string $hhmm): string
{
    return DateTimeImmutable::createFromFormat('H:i', $hhmm)?->format('g:i a') ?? $hhmm;
}

/* ---------- Opening hours ---------- */

function opening_hours(): array
{
    $hours = json_decode(setting('hours'), true);
    $defaults = json_decode(setting_defaults()['hours'], true);
    return is_array($hours) ? $hours + $defaults : $defaults;
}

/** Whether a HH:MM window contains the given time; a close at or before the open time runs past midnight. */
function in_window(string $time, string $from, string $until): bool
{
    if ($until > $from) return $time >= $from && $time < $until;
    return $time >= $from || $time < $until;
}

function open_now(?DateTimeImmutable $at = null): bool
{
    $at ??= local_now();
    $hours = opening_hours();
    $time = $at->format('H:i');
    $today = $hours[(int)$at->format('N')] ?? null;
    if ($today && empty($today['closed'])) {
        if ($today['close'] > $today['open'] && $time >= $today['open'] && $time < $today['close']) return true;
        if ($today['close'] <= $today['open'] && $time >= $today['open']) return true;
    }
    // Yesterday's late session may continue past midnight.
    $yesterday = $hours[(int)$at->modify('-1 day')->format('N')] ?? null;
    return $yesterday && empty($yesterday['closed']) && $yesterday['close'] <= $yesterday['open'] && $time < $yesterday['close'];
}

function ordering_open(): bool
{
    return setting('accepting_orders') === '1' && open_now();
}

/** A short human status such as "Open now · until 10:00 pm". */
function opening_status(): array
{
    $at = local_now();
    if (setting('accepting_orders') !== '1') return ['open' => false, 'label' => 'Online ordering is paused'];
    $hours = opening_hours();
    if (open_now($at)) {
        $today = $hours[(int)$at->format('N')];
        $close = !empty($today['closed']) || ($today['close'] <= $today['open'] && $at->format('H:i') < $today['open'])
            ? $hours[(int)$at->modify('-1 day')->format('N')]['close']
            : $today['close'];
        return ['open' => true, 'label' => 'Open now · until ' . format_hour($close)];
    }
    for ($i = 0; $i < 7; $i++) {
        $day = $at->modify("+$i day");
        $h = $hours[(int)$day->format('N')] ?? null;
        if (!$h || !empty($h['closed'])) continue;
        if ($i === 0 && $at->format('H:i') >= $h['open']) continue;
        $when = $i === 0 ? 'today' : ($i === 1 ? 'tomorrow' : $day->format('l'));
        return ['open' => false, 'label' => 'Closed · opens ' . $when . ' at ' . format_hour($h['open'])];
    }
    return ['open' => false, 'label' => 'Closed'];
}

/* ---------- Menu ---------- */

function menu_categories(bool $withItemsOnly = true): array
{
    $sql = 'SELECT c.* FROM categories c WHERE c.active = 1';
    if ($withItemsOnly) $sql .= " AND EXISTS (SELECT 1 FROM menu_items m WHERE m.category_id = c.id AND m.status <> 'hidden')";
    return all($sql . ' ORDER BY c.sort_order, c.name');
}

/** Menu items customers can see (available or sold out) in active categories. */
function visible_items(string $where = '1 = 1', array $params = [], string $order = 'c.sort_order, c.name, m.sort_order, m.name'): array
{
    return all("SELECT m.*, c.name AS category_name FROM menu_items m JOIN categories c ON c.id = m.category_id
        WHERE m.status <> 'hidden' AND c.active = 1 AND ($where) ORDER BY $order", $params);
}

function visible_item(int $id): ?array
{
    return visible_items('m.id = ?', [$id])[0] ?? null;
}

function item_in_schedule(array $item, ?DateTimeImmutable $at = null): bool
{
    $at ??= local_now();
    if (!((int)$item['available_days'] & (1 << ((int)$at->format('N') - 1)))) return false;
    if (!$item['available_from'] || !$item['available_until']) return true;
    return in_window($at->format('H:i'), $item['available_from'], $item['available_until']);
}

/** Why a dish cannot be ordered right now, or null when it can. */
function item_unavailable_reason(array $item): ?string
{
    if ($item['status'] === 'sold_out') return 'Sold out today';
    if ($item['status'] !== 'available') return 'Not available';
    if (!item_in_schedule($item)) return availability_label($item);
    return null;
}

function availability_label(array $item): string
{
    $mask = (int)$item['available_days'];
    $days = $mask === 127 ? '' : implode(', ', array_map(fn($d) => substr(WEEKDAYS[$d], 0, 3), array_filter(array_keys(WEEKDAYS), fn($d) => $mask & (1 << ($d - 1)))));
    $time = $item['available_from'] && $item['available_until'] ? format_hour($item['available_from']) . '–' . format_hour($item['available_until']) : '';
    $when = trim($days . ' ' . $time);
    return $when === '' ? 'Available' : 'Served ' . $when;
}

function dish_rating(int $itemId): array
{
    $r = one('SELECT AVG(rating) AS average, COUNT(*) AS total FROM dish_reviews WHERE menu_item_id = ?', [$itemId]);
    return ['average' => (float)($r['average'] ?? 0), 'count' => (int)($r['total'] ?? 0)];
}

function stars(float $average, int $count = 0, bool $withText = true): string
{
    $width = round(max(0, min(5, $average)) / 5 * 100);
    $html = '<span class="stars" role="img" aria-label="' . e(number_format($average, 1)) . ' out of 5">★★★★★<span class="stars-fill" style="width:' . $width . '%">★★★★★</span></span>';
    if ($withText) $html .= ' <span class="stars-text">' . ($count ? e(number_format($average, 1)) . ' (' . $count . ')' : 'No reviews yet') . '</span>';
    return $html;
}

/* ---------- Cart (session) ---------- */

/** Loads the session cart against the live menu, dropping dishes that no longer exist or are hidden. */
function cart_data(): array
{
    $raw = is_array($_SESSION['cart'] ?? null) ? array_slice($_SESSION['cart'], 0, 50, true) : [];
    $cart = ['items' => [], 'count' => 0, 'subtotal' => 0, 'blocked' => false];
    $ids = array_values(array_filter(array_map('intval', array_keys($raw)), fn($id) => $id > 0));
    $_SESSION['cart'] = [];
    if (!$ids) return $cart;
    $marks = implode(',', array_fill(0, count($ids), '?'));
    foreach (visible_items("m.id IN ($marks)", $ids) as $item) {
        $qty = max(1, min(20, (int)$raw[$item['id']]));
        $_SESSION['cart'][$item['id']] = $qty;
        $item['quantity'] = $qty;
        $item['line_total'] = $qty * (int)$item['price_minor'];
        $item['unavailable'] = item_unavailable_reason($item);
        if ($item['unavailable']) $cart['blocked'] = true;
        $cart['items'][] = $item;
        $cart['count'] += $qty;
        $cart['subtotal'] += $item['line_total'];
    }
    return $cart;
}

function cart_set(int $id, int $qty): void
{
    $_SESSION['cart'] ??= [];
    if ($qty <= 0) unset($_SESSION['cart'][$id]);
    else $_SESSION['cart'][$id] = min(20, $qty);
}

/** A small form that sets a cart line to the given quantity (0 removes it). */
function cart_qty_button(array $item, int $quantity, string $iconName, string $label): string
{
    return '<form method="post" action="' . e(url('cart-action.php')) . '" data-cart-update>' . csrf()
        . '<input type="hidden" name="action" value="set"><input type="hidden" name="id" value="' . (int)$item['id'] . '">'
        . '<input type="hidden" name="quantity" value="' . $quantity . '"><input type="hidden" name="return" value="' . e($_SERVER['REQUEST_URI'] ?? '') . '">'
        . '<button type="submit" aria-label="' . e($label . ' ' . $item['name']) . '">' . icon($iconName, 14) . '</button></form>';
}

/** A safe local path to return to after a form post. */
function local_return(string $value, string $fallback = 'menu.php'): string
{
    $parts = parse_url($value);
    if ($value === '' || $parts === false || isset($parts['scheme']) || isset($parts['host']) || str_starts_with($value, '//')) return $fallback;
    $path = ltrim($parts['path'] ?? '', '/');
    $base = ltrim((string)parse_url(cfg('url'), PHP_URL_PATH), '/');
    if ($base !== '' && str_starts_with($path, $base . '/')) $path = substr($path, strlen($base) + 1);
    if (!preg_match('/^[a-z0-9\-\/]*\.php$/', $path) && $path !== '') return $fallback;
    return $path . (isset($parts['query']) ? '?' . $parts['query'] : '') . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
}

function coordinates(string $lat, string $lng): ?array
{
    if (!is_numeric($lat) || !is_numeric($lng)) return null;
    $a = (float)$lat;
    $b = (float)$lng;
    if (!is_finite($a) || !is_finite($b) || abs($a) > 90 || abs($b) > 180) return null;
    return [round($a, 7), round($b, 7)];
}

function location_picker(string $lat = '', string $lng = '', string $title = 'Pin your delivery location'): void
{
    ?>
    <div class="location-picker" data-location-picker>
        <div class="location-picker-head">
            <strong><?= e($title) ?></strong>
            <button class="btn btn-ghost btn-sm" type="button" data-location-gps><?= icon('crosshair', 16) ?> Use my location</button>
        </div>
        <div class="map" data-location-map aria-label="Map — tap to place your pin"
             data-default-lat="<?= e(setting('latitude') ?: '51.5072') ?>" data-default-lng="<?= e(setting('longitude') ?: '-0.1276') ?>"></div>
        <input type="hidden" name="latitude" value="<?= e($lat) ?>" data-location-lat>
        <input type="hidden" name="longitude" value="<?= e($lng) ?>" data-location-lng>
        <small class="hint" role="status" data-location-status><?= $lat !== '' ? 'Pin loaded — drag it to adjust.' : 'Tap the map or use your location to drop a pin.' ?></small>
    </div>
    <?php
}
