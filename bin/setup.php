<?php
// Creates the database tables, the storage folder and the default categories. Safe to run again.
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../app/bootstrap.php';

if (!is_dir(ROOT . '/storage/uploads')) mkdir(ROOT . '/storage/uploads', 0750, true);
db()->exec(file_get_contents(ROOT . '/database/schema.sql'));

// Columns added after the first release; CREATE TABLE IF NOT EXISTS does not add them to existing tables.
$added = [
    'orders' => [
        'payment_method'    => "VARCHAR(20) NOT NULL DEFAULT 'on_delivery' AFTER status",
        'payment_status'    => "VARCHAR(20) NOT NULL DEFAULT 'unpaid' AFTER payment_method",
        'payment_intent_id' => 'VARCHAR(64) NULL UNIQUE AFTER payment_status',
        'paid_at'           => 'DATETIME NULL AFTER payment_intent_id',
        'refunded_at'       => 'DATETIME NULL AFTER paid_at',
    ],
];
foreach ($added as $table => $columns) {
    foreach ($columns as $column => $definition) {
        $exists = scalar('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$table, $column]);
        if (!$exists) db()->exec("ALTER TABLE $table ADD COLUMN $column $definition");
    }
}

foreach (setting_defaults() as $name => $value) {
    q('INSERT IGNORE INTO settings (name, value) VALUES (?, ?)', [$name, $value]);
}
if (!scalar('SELECT COUNT(*) FROM categories')) {
    $defaults = [
        ['Starters', 'Small plates to begin your meal'],
        ['Mains', 'Our signature dishes'],
        ['Grills', 'Cooked over open flame'],
        ['Sides', 'The perfect accompaniments'],
        ['Desserts', 'Something sweet to finish'],
        ['Drinks', 'Soft drinks, juices and hot drinks'],
    ];
    foreach ($defaults as $i => [$name, $description]) {
        q('INSERT INTO categories (name, description, sort_order) VALUES (?, ?, ?)', [$name, $description, $i]);
    }
}
echo "Database ready.\nNext: create the owner account with bin/create-owner.php\n";
