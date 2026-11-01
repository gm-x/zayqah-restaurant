<?php
// Creates the database tables, the storage folder and the default categories. Safe to run again.
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../app/bootstrap.php';

if (!is_dir(ROOT . '/storage/uploads')) mkdir(ROOT . '/storage/uploads', 0750, true);
db()->exec(file_get_contents(ROOT . '/database/schema.sql'));

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
