<?php
/**
 * GET /api/live.php
 * Real-time polling endpoint — called every 3s by LivePoller
 *
 * ?order_id=X   → single order status (for customer order-status page)
 * (no param)    → kitchen overview: active order count + recent orders
 */
require_once dirname(__DIR__, 3) . '/core/bootstrap.php';

$db = Database::getInstance();

// ─── Single order status (customer polling) ───────────────────
if (!empty($_GET['order_id'])) {
    $orderId = (int)$_GET['order_id'];
    $order = $db->fetchOne(
        "SELECT o.id, o.status, o.total, o.created_at, t.name AS table_name
         FROM orders o
         JOIN `tables` t ON t.id = o.table_id
         WHERE o.id = ?",
        [$orderId]
    );

    if (!$order) {
        Api::error('Order not found', 404);
    }

    $order['items'] = $db->fetchAll(
        'SELECT name, quantity, unit_price FROM order_items WHERE order_id = ?',
        [$orderId]
    );
    $order['total'] = (float)$order['total'];
    $order['id']    = (int)$order['id'];

    Api::success(['order' => $order]);
}

// ─── Kitchen live feed (admin polling) ───────────────────────
// Require admin session for kitchen feed
Auth::requireRole('admin', '/admin/login.php');

$analytics = new Analytics();

// Active table sessions: still occupying tables until explicitly completed.
$activeOrders = $db->fetchAll(
        "SELECT o.id, t.name AS table_name, o.status, o.total, o.note,
                        o.customer_name, o.customer_phone, o.customer_email, o.created_at
     FROM orders o
     JOIN `tables` t ON t.id = o.table_id
    WHERE o.status IN ('pending','preparing','ready')
       OR (o.status = 'delivered' AND o.created_at >= DATE_SUB(NOW(), INTERVAL 90 MINUTE))
     ORDER BY o.created_at ASC"
);
foreach ($activeOrders as &$ao) {
    $ao['items']  = $db->fetchAll(
        'SELECT name, quantity, unit_price FROM order_items WHERE order_id = ?',
        [$ao['id']]
    );
    $ao['total']  = (float)$ao['total'];
    $ao['id']     = (int)$ao['id'];
}

// Counts per status
$counts = $db->fetchAll(
    "SELECT status, COUNT(*) AS cnt FROM orders
    WHERE status IN ('pending','preparing','ready')
       OR (status = 'delivered' AND created_at >= DATE_SUB(NOW(), INTERVAL 90 MINUTE))
     GROUP BY status"
);
$statusCounts = ['pending' => 0, 'preparing' => 0, 'ready' => 0, 'delivered' => 0];
foreach ($counts as $c) {
    $statusCounts[$c['status']] = (int)$c['cnt'];
}

// Orders placed in last 5 minutes (for highlighting new arrivals)
$newCount = $analytics->recentCount('orders', 'created_at', 5);

Api::success([
    'active_orders' => $activeOrders,
    'counts'        => $statusCounts,
    'new_in_5min'   => $newCount,
    'timestamp'     => date('c'),
]);
