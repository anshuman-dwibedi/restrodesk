<?php
require_once dirname(__DIR__) . '/core/bootstrap.php';
Auth::requireRole('admin', 'login.php');
$user = Auth::user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Restrodesk</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../core/ui/devcore.css">
    <link rel="stylesheet" href="../../../core/ui/parts/_icons.css">
    <style>:root{--dc-accent:#e8a838;--dc-accent-2:#f0c060;--dc-accent-glow:rgba(232,168,56,0.2);}</style>
    <style>
        .page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:28px; flex-wrap:wrap; gap:12px; }
        .page-title  { font-size:1.5rem; font-weight:700; }
        .stats-grid  { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:16px; margin-bottom:24px; }
        .charts-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px; }
        .chart-wrap  { height:260px; position:relative; }
        .doughnut-col{ grid-column:1 / -1; }
        .feed-row    { display:flex; align-items:center; justify-content:space-between; padding:12px 0; border-bottom:1px solid var(--dc-border); flex-wrap:wrap; gap:8px; }
        .feed-row:last-child { border-bottom:none; }
        .feed-items  { font-size:0.8rem; color:var(--dc-text-3); }
        @media (max-width:768px) { .charts-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>

<!-- Sidebar -->
<aside class="dc-sidebar">
    <div class="dc-sidebar__logo"><i class="dc-icon dc-icon-utensils"></i> <span>Restrodesk</span></div>
    <div class="dc-sidebar__section">Management</div>
    <a href="dashboard.php" class="dc-sidebar__link active"><i class="dc-icon dc-icon-bar-chart"></i> Dashboard</a>
    <a href="orders.php"    class="dc-sidebar__link"><i class="dc-icon dc-icon-receipt"></i> Orders</a>
    <a href="menu.php"      class="dc-sidebar__link"><i class="dc-icon dc-icon-clipboard"></i> Menu Items</a>
    <a href="qr-generator.php" class="dc-sidebar__link"><i class="dc-icon dc-icon-qr-code"></i> QR Codes</a>
    <div class="dc-sidebar__section" style="margin-top:auto">Account</div>
    <a href="logout.php"    class="dc-sidebar__link"><i class="dc-icon dc-icon-log-out"></i> Logout</a>
</aside>

<div class="dc-with-sidebar">
    <div class="dc-container dc-section">

        <div class="page-header">
            <div>
                <div class="page-title">Dashboard</div>
                <div class="dc-text-dim" style="font-size:0.875rem">Welcome back, <?= htmlspecialchars($user['name']) ?></div>
            </div>
            <div class="dc-live">
                <span class="dc-live__dot"></span>
                Live
            </div>
        </div>

        <!-- KPI Stat Cards (populated by JS) -->
        <div class="stats-grid" id="kpiGrid">
            <div class="dc-stat">
                <div class="dc-stat__icon"><i class="dc-icon dc-icon-receipt"></i></div>
                <div class="dc-stat__value" id="kpi-orders">—</div>
                <div class="dc-stat__label">Orders Today</div>
            </div>
            <div class="dc-stat">
                <div class="dc-stat__icon"><i class="dc-icon dc-icon-dollar"></i></div>
                <div class="dc-stat__value" id="kpi-revenue">—</div>
                <div class="dc-stat__label">Revenue Today</div>
            </div>
            <div class="dc-stat">
                <div class="dc-stat__icon"><i class="dc-icon dc-icon-activity"></i></div>
                <div class="dc-stat__value" id="kpi-avg">—</div>
                <div class="dc-stat__label">Avg Order Value</div>
            </div>
            <div class="dc-stat">
                <div class="dc-stat__icon"><i class="dc-icon dc-icon-chair"></i></div>
                <div class="dc-stat__value" id="kpi-tables">—</div>
                <div class="dc-stat__label">Active Tables</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="dc-card">
                <div style="font-weight:600; margin-bottom:16px;">Orders per Day <span class="dc-text-dim" style="font-size:0.8rem">(last 14 days)</span></div>
                <div class="chart-wrap"><canvas id="chartOrders"></canvas></div>
            </div>
            <div class="dc-card">
                <div style="font-weight:600; margin-bottom:16px;">Revenue per Day <span class="dc-text-dim" style="font-size:0.8rem">(last 14 days)</span></div>
                <div class="chart-wrap"><canvas id="chartRevenue"></canvas></div>
            </div>
            <div class="dc-card doughnut-col">
                <div style="font-weight:600; margin-bottom:16px;">Top 5 Menu Items by Order Count</div>
                <div style="max-width:400px; margin:0 auto;">
                    <div class="chart-wrap"><canvas id="chartDoughnut"></canvas></div>
                </div>
            </div>
        </div>

        <!-- Live Order Feed -->
        <div class="dc-card">
            <div class="dc-flex-between dc-items-center" style="margin-bottom:16px;">
                <div style="font-weight:600;">Recent Orders</div>
                <div class="dc-live"><span class="dc-live__dot"></span>Live Feed</div>
            </div>
            <div id="orderFeed">
                <div class="dc-text-dim dc-text-center" style="padding:24px;">Loading...</div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="../../../core/ui/devcore.js"></script>
<script>
let chartsBuilt = false;
let ordersChart, revenueChart, doughnutChart;

const statusBadge = {
    pending:   '<span class="dc-badge dc-badge-warning">Pending</span>',
    preparing: '<span class="dc-badge dc-badge-info">Preparing</span>',
    ready:     '<span class="dc-badge dc-badge-success">Ready</span>',
    delivered: '<span class="dc-badge dc-badge-neutral">Delivered</span>',
    completed: '<span class="dc-badge dc-badge-neutral">Completed</span>',
};

function formatCurrency(v) {
    return '$' + parseFloat(v).toFixed(2);
}

function buildFeed(orders) {
    if (!orders.length) {
        document.getElementById('orderFeed').innerHTML =
            '<div class="dc-text-dim dc-text-center" style="padding:24px;">No recent orders.</div>';
        return;
    }
    document.getElementById('orderFeed').innerHTML = orders.map(o => {
        const itemsStr = o.items.map(i => `${i.name} ×${i.quantity}`).join(', ');
        const time     = new Date(o.created_at).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'});
        return `<div class="feed-row dc-animate-fade-in">
            <div>
                <strong>${o.table_name}</strong>
                <div class="feed-items">${itemsStr}</div>
            </div>
            <div class="dc-flex dc-items-center" style="gap:12px;">
                <span class="dc-text-dim" style="font-size:0.8rem">${time}</span>
                ${statusBadge[o.status] || ''}
                <strong>${formatCurrency(o.total)}</strong>
            </div>
        </div>`;
    }).join('');
}

function buildCharts(data) {
    if (chartsBuilt) {
        // Update existing charts
        const days1    = data.orders_by_day.map(d => d.date);
        const counts   = data.orders_by_day.map(d => d.count);
        const days2    = data.revenue_by_day.map(d => d.date);
        const revenues = data.revenue_by_day.map(d => d.total);
        ordersChart.data.labels       = days1;
        ordersChart.data.datasets[0].data = counts;
        ordersChart.update();
        revenueChart.data.labels      = days2;
        revenueChart.data.datasets[0].data = revenues;
        revenueChart.update();
        return;
    }
    chartsBuilt = true;

    // Orders line chart
    ordersChart = DCChart.line(
        'chartOrders',
        data.orders_by_day.map(d => d.date),
        [{ label: 'Orders', data: data.orders_by_day.map(d => parseInt(d.count)) }]
    );

    // Revenue bar chart
    revenueChart = DCChart.bar(
        'chartRevenue',
        data.revenue_by_day.map(d => d.date),
        [{ label: 'Revenue ($)', data: data.revenue_by_day.map(d => parseFloat(d.total)) }]
    );

    // Doughnut top items
    doughnutChart = DCChart.doughnut(
        'chartDoughnut',
        data.top_items.map(i => i.name),
        data.top_items.map(i => parseInt(i.count))
    );
}

// LivePoller calls _tick() immediately on start(), then every 30s
const dashPoller = new LivePoller('../api/analytics.php', (res) => {
    const d = res.data;
    document.getElementById('kpi-orders').textContent  = d.kpi.orders_today;
    document.getElementById('kpi-revenue').textContent = formatCurrency(d.kpi.revenue_today);
    document.getElementById('kpi-avg').textContent     = formatCurrency(d.kpi.avg_order);
    document.getElementById('kpi-tables').textContent  = d.kpi.active_tables;
    buildFeed(d.recent_orders);
    buildCharts(d);
}, 30000);
dashPoller.start();
</script>
</body>
</html>
