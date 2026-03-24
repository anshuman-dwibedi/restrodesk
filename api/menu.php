<?php
/**
 * GET /api/menu.php?table=5
 * Returns all available menu items grouped by category
 * Also validates the table token if provided
 */
require_once dirname(__DIR__) . '/core/bootstrap.php';

$db = Database::getInstance();

// Resolve table from ?table= (table id) or ?token= (qr_token)
$tableId = null;
if (!empty($_GET['table'])) {
    $t = $db->fetchOne('SELECT id, name FROM `tables` WHERE id = ?', [(int)$_GET['table']]);
    if ($t) $tableId = (int)$t['id'];
}
if (!empty($_GET['token'])) {
    $t = $db->fetchOne('SELECT id, name FROM `tables` WHERE qr_token = ?', [$_GET['token']]);
    if ($t) $tableId = (int)$t['id'];
}

// Fetch categories
$categories = $db->fetchAll('SELECT id, name FROM categories ORDER BY sort_order ASC');

// Fetch all available menu items
$items = $db->fetchAll(
    'SELECT id, category_id, name, description, price, image_url
     FROM menu_items WHERE available = 1 ORDER BY category_id, name'
);

// Group items under their category
$grouped = [];
foreach ($categories as $cat) {
    $grouped[] = [
        'id'    => (int)$cat['id'],
        'name'  => $cat['name'],
        'items' => array_values(array_filter($items, fn($i) => (int)$i['category_id'] === (int)$cat['id'])),
    ];
}

// Remove empty categories
$grouped = array_values(array_filter($grouped, fn($c) => count($c['items']) > 0));

// Format prices as floats
foreach ($grouped as &$cat) {
    foreach ($cat['items'] as &$item) {
        $item['price'] = (float)$item['price'];
        $item['id']    = (int)$item['id'];
    }
}

Api::success([
    'categories' => $grouped,
    'table_id'   => $tableId,
]);
