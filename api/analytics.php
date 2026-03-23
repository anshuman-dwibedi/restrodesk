<?php
/**
 * GET /api/analytics.php
 * Returns all KPI and chart data for the admin dashboard
 * Requires admin session
 */
require_once dirname(__DIR__, 3) . '/core/bootstrap.php';

Auth::requireRole('admin', '/admin/login.php');

$db        = Database::getInstance();
$analytics = new Analytics();

// ─── KPI Cards ───────────────────────────────────────────────────
$ordersToday = $db->fetchOne(
    "SELECT COUNT(*) AS cnt, COALESCE(SUM(total),0) AS rev
     FROM orders WHERE DATE(created_at) = CURDATE()"
);
$totalOrders = (int)$ordersToday['cnt'];
$revenueToday = (float)$ordersToday['rev'];
$avgOrder    = $totalOrders > 0 ? round($revenueToday / $totalOrders, 2) : 0;

$activeTables = $db->fetchOne(
    "SELECT COUNT(DISTINCT table_id) AS cnt
    FROM orders
    WHERE status IN ('pending','preparing','ready')
       OR (status = 'delivered' AND created_at >= DATE_SUB(NOW(), INTERVAL 90 MINUTE))"
);

// ─── Line chart: Orders per day (14 days) ────────────────────────
$ordersByDay = $analytics->countByDay('orders', 'created_at', 14);

// ─── Bar chart: Revenue per day (14 days) ────────────────────────
$revenueByDay = $analytics->sumByDay('orders', 'total', 'created_at', 14);

// ─── Doughnut: Top 5 menu items ──────────────────────────────────
$topItems = $db->fetchAll(
    "SELECT oi.name, SUM(oi.quantity) AS count
     FROM order_items oi
     GROUP BY oi.name
     ORDER BY count DESC
     LIMIT 5"
);

// ─── Recent 10 orders ────────────────────────────────────────────
$recentOrders = $db->fetchAll(
    "SELECT o.id, t.name AS table_name, o.status, o.total, o.created_at
     FROM orders o
     JOIN `tables` t ON t.id = o.table_id
     ORDER BY o.created_at DESC
     LIMIT 10"
);
foreach ($recentOrders as &$ro) {
    $ro['items'] = $db->fetchAll(
        'SELECT name, quantity FROM order_items WHERE order_id = ?',
        [$ro['id']]
    );
    $ro['total'] = (float)$ro['total'];
    $ro['id']    = (int)$ro['id'];
}

Api::success([
    'kpi' => [
        'orders_today'  => $totalOrders,
        'revenue_today' => $revenueToday,
        'avg_order'     => $avgOrder,
        'active_tables' => (int)$activeTables['cnt'],
    ],
    'orders_by_day'  => $ordersByDay,
    'revenue_by_day' => $revenueByDay,
    'top_items'      => $topItems,
    'recent_orders'  => $recentOrders,
]);
