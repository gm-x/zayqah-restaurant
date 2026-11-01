<?php
// Adds a sample menu (without photos) so a fresh install has something to show. Skips if dishes exist.
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../app/bootstrap.php';
if (scalar('SELECT COUNT(*) FROM menu_items')) exit("The menu already has dishes; nothing added.\n");

$cat = array_column(all('SELECT id, name FROM categories'), 'id', 'name');
$dishes = [
    ['Starters', 'Chicken Tikka Skewers', 'Tender chicken thigh marinated overnight in yoghurt and spices, finished in the tandoor.', 'Chicken, yoghurt (milk), garlic, ginger, chilli, lemon.', 650, 1, 0, 1],
    ['Starters', 'Vegetable Samosas', 'Crisp pastry filled with spiced potato and peas, served with tamarind chutney.', 'Wheat flour, potato, peas, cumin, coriander. Contains gluten.', 450, 0, 1, 0],
    ['Mains', 'Lamb Karahi', 'Slow-cooked lamb in a rich tomato, ginger and green chilli sauce, cooked in a traditional karahi.', 'Lamb, tomato, ginger, garlic, green chilli, ghee (milk).', 1450, 1, 0, 1],
    ['Mains', 'Butter Chicken', 'Charred chicken in a velvety tomato and butter sauce with a hint of fenugreek.', 'Chicken, tomato, butter (milk), cream (milk), cashew (nuts), fenugreek.', 1250, 1, 0, 0],
    ['Mains', 'Paneer Makhani', 'Soft paneer cubes simmered in our creamy makhani sauce.', 'Paneer (milk), tomato, cream (milk), cashew (nuts), spices.', 1150, 0, 1, 0],
    ['Grills', 'Mixed Grill Platter', 'Seekh kebab, chicken tikka and lamb chops with salad and mint sauce.', 'Lamb, chicken, yoghurt (milk), onion, spices, mint.', 1950, 1, 0, 1],
    ['Grills', 'Seekh Kebab', 'Minced lamb with herbs and spices, grilled on skewers.', 'Lamb, onion, coriander, green chilli, spices.', 850, 0, 0, 1],
    ['Sides', 'Garlic Naan', 'Freshly baked naan brushed with garlic butter.', 'Wheat flour, yoghurt (milk), butter (milk), garlic. Contains gluten.', 350, 0, 1, 0],
    ['Sides', 'Pilau Rice', 'Fragrant basmati rice with whole spices.', 'Basmati rice, cumin, cardamom, bay leaf.', 375, 0, 1, 0],
    ['Desserts', 'Gulab Jamun', 'Warm milk dumplings soaked in rose and cardamom syrup.', 'Milk powder (milk), wheat flour, sugar, rose water, cardamom.', 495, 0, 1, 0],
    ['Desserts', 'Mango Kulfi', 'Traditional slow-churned mango ice cream.', 'Milk, mango, sugar, pistachio (nuts).', 450, 1, 1, 0],
    ['Drinks', 'Mango Lassi', 'Chilled yoghurt drink blended with Alphonso mango.', 'Yoghurt (milk), mango, sugar.', 395, 0, 1, 0],
];
foreach ($dishes as $i => [$category, $name, $description, $ingredients, $price, $featured, $veg, $spicy]) {
    q('INSERT INTO menu_items (category_id, name, description, ingredients, price_minor, featured, vegetarian, spicy, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$cat[$category], $name, $description, $ingredients, $price, $featured, $veg, $spicy, $i, now(), now()]);
}
echo count($dishes) . " sample dishes added. Add photos from the owner panel.\n";
