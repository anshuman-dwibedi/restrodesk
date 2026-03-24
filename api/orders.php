<?php
/**
 * /api/orders.php
 * POST   — create new order  (public, from customer checkout)
 * GET    — list orders       (admin, requires session)
 * PUT    — update status     (admin, requires session)
 */
require_once dirname(__DIR__) . '/core/bootstrap.php';

$db     = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

function ensureCustomerColumns($db): void {
    $cols = $db->fetchAll('SHOW COLUMNS FROM orders');
    $fields = array_column($cols, 'Field');
    $alters = [];

    if (!in_array('customer_name', $fields, true)) {
        $alters[] = "ADD COLUMN `customer_name` VARCHAR(100) DEFAULT NULL AFTER `note`";
    }
    if (!in_array('customer_phone', $fields, true)) {
        $alters[] = "ADD COLUMN `customer_phone` VARCHAR(30) DEFAULT NULL AFTER `customer_name`";
    }
    if (!in_array('customer_email', $fields, true)) {
        $alters[] = "ADD COLUMN `customer_email` VARCHAR(160) DEFAULT NULL AFTER `customer_phone`";
    }
    if (!in_array('customer_address_notes', $fields, true)) {
        $alters[] = "ADD COLUMN `customer_address_notes` TEXT DEFAULT NULL AFTER `customer_email`";
    }

    if (!empty($alters)) {
        $db->query('ALTER TABLE orders ' . implode(', ', $alters));
    }
}

// ─── POST: Create order ──────────────────────────────────────────
if ($method === 'POST') {
    ensureCustomerColumns($db);

    $body = Api::body();

    $v = Validator::make($body, [
        'table_id'              => 'required|numeric',
        'items'                 => 'required',
        'customer_name'         => 'required|max:100',
        'customer_phone'        => 'required|max:30',
        'customer_email'        => 'email|max:160',
        'customer_address_notes'=> 'max:500',
    ]);
    if ($v->fails()) {
        Api::error('Validation failed', 422, $v->errors());
    }

    $customerName = trim((string)($body['customer_name'] ?? ''));
    $customerPhone = trim((string)($body['customer_phone'] ?? ''));
    $customerEmail = trim((string)($body['customer_email'] ?? ''));
    $customerAddressNotes = trim((string)($body['customer_address_notes'] ?? ''));

    $phoneDigits = preg_replace('/\D+/', '', $customerPhone);
    if (strlen($phoneDigits) < 7 || strlen($phoneDigits) > 15) {
        Api::error('customer_phone must contain 7 to 15 digits', 422);
    }

    $items = $body['items'] ?? [];
    if (empty($items) || !is_array($items)) {
        Api::error('Cart is empty', 422);
    }

    // Verify table exists
    $table = $db->fetchOne('SELECT id FROM `tables` WHERE id = ?', [(int)$body['table_id']]);
    if (!$table) {
        Api::error('Invalid table', 422);
    }

    // Fetch menu items to calculate prices server-side (never trust client prices)
    $ids         = array_map('intval', array_column($items, 'menu_item_id'));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $menuItems   = $db->fetchAll(
        "SELECT id, name, price FROM menu_items WHERE id IN ($placeholders) AND available = 1",
        $ids
    );
    $menuMap = [];
    foreach ($menuItems as $mi) {
        $menuMap[(int)$mi['id']] = $mi;
    }

    $total = 0.00;
    $lines = [];
    foreach ($items as $line) {
        $mid = (int)($line['menu_item_id'] ?? 0);
        $qty = max(1, (int)($line['quantity'] ?? 1));
        if (!isset($menuMap[$mid])) {
            Api::error("Menu item $mid not found or unavailable", 422);
        }
        $price   = (float)$menuMap[$mid]['price'];
        $total  += $price * $qty;
        $lines[] = [
            'menu_item_id' => $mid,
            'quantity'     => $qty,
            'unit_price'   => $price,
            'name'         => $menuMap[$mid]['name'],
        ];
    }

    // Insert order + items in a transaction
    $db->beginTransaction();
    try {
        // Generate unique order token
        $token = 'ord_' . bin2hex(random_bytes(8));
        $orderId = $db->insert('orders', [
            'table_id'               => (int)$body['table_id'],
            'token'                  => $token,
            'status'                 => 'pending',
            'total'                  => round($total, 2),
            'note'                   => substr((string)($body['note'] ?? ''), 0, 500),
            'customer_name'          => substr($customerName, 0, 100),
            'customer_phone'         => substr($customerPhone, 0, 30),
            'customer_email'         => $customerEmail === '' ? null : substr($customerEmail, 0, 160),
            'customer_address_notes' => $customerAddressNotes === '' ? null : substr($customerAddressNotes, 0, 500),
        ]);
        foreach ($lines as $line) {
            $db->insert('order_items', array_merge(['order_id' => $orderId], $line));
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        Api::error('Could not place order. Please try again.', 500);
    }

    Api::success(['order_id' => (int)$orderId, 'token' => $token, 'total' => round($total, 2)], 'Order placed successfully', 201);
}

// ─── GET: List orders ────────────────────────────────────────────
if ($method === 'GET') {
    Auth::requireRole('admin', '/admin/login.php');
    ensureCustomerColumns($db);

    $status  = $_GET['status']  ?? null;
    $tableId = $_GET['table_id'] ?? null;
    $limit   = min((int)($_GET['limit'] ?? 50), 200);
    $page    = max(1, (int)($_GET['page']  ?? 1));
    $offset  = ($page - 1) * $limit;

    $where  = ['1=1'];
    $params = [];

    if ($status) {
        $where[]  = 'o.status = ?';
        $params[] = $status;
    }
    if ($tableId) {
        $where[]  = 'o.table_id = ?';
        $params[] = (int)$tableId;
    }

    $whereStr = implode(' AND ', $where);

    $orders = $db->fetchAll(
        "SELECT o.id, o.table_id, t.name AS table_name, o.status, o.total, o.note,
            o.customer_name, o.customer_phone, o.customer_email, o.customer_address_notes,
            o.created_at
         FROM orders o
         JOIN `tables` t ON t.id = o.table_id
         WHERE $whereStr
         ORDER BY o.created_at DESC
         LIMIT $limit OFFSET $offset",
        $params
    );

    // Attach items
    foreach ($orders as &$order) {
        $order['items'] = $db->fetchAll(
            'SELECT name, quantity, unit_price FROM order_items WHERE order_id = ?',
            [$order['id']]
        );
        $order['total'] = (float)$order['total'];
        $order['id']    = (int)$order['id'];
    }

    $totalRow = $db->fetchOne(
        "SELECT COUNT(*) AS cnt FROM orders o WHERE $whereStr",
        $params
    );

    Api::paginated($orders, (int)$totalRow['cnt'], $page, $limit);
}

// ─── PUT: Update order status ────────────────────────────────────
if ($method === 'PUT') {
    Auth::requireRole('admin', '/admin/login.php');

    // Ensure legacy databases support the explicit terminal status.
    $statusCol = $db->fetchOne("SHOW COLUMNS FROM orders LIKE 'status'");
    if (!empty($statusCol['Type']) && strpos($statusCol['Type'], "'completed'") === false) {
        $db->query("ALTER TABLE orders MODIFY COLUMN status ENUM('pending','preparing','ready','delivered','completed') NOT NULL DEFAULT 'pending'");
    }

    $body = Api::body();
    $v    = Validator::make($body, [
        'order_id' => 'required|numeric',
        'status'   => 'required|in:pending,preparing,ready,delivered,completed',
    ]);
    if ($v->fails()) {
        Api::error('Validation failed', 422, $v->errors());
    }

    $updated = $db->update('orders',
        ['status' => $body['status']],
        'id = ?',
        [(int)$body['order_id']]
    );

    if (!$updated) {
        Api::error('Order not found', 404);
    }

    Api::success(['order_id' => (int)$body['order_id'], 'status' => $body['status']], 'Status updated');
}

Api::error('Method not allowed', 405);
