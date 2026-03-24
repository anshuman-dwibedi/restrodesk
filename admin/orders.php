<?php
require_once dirname(__DIR__) . '/core/bootstrap.php';
Auth::requireRole('admin', '/restaurant-qr-ordering/admin/login.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders — Kitchen View</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../core/ui/devcore.css">
    <link rel="stylesheet" href="../../../core/ui/parts/_icons.css">
    <style>:root{--dc-accent:#e8a838;--dc-accent-2:#f0c060;--dc-accent-glow:rgba(232,168,56,0.2);}</style>
    <style>
        .page-header   { display:flex; align-items:center; justify-content:space-between; margin-bottom:28px; flex-wrap:wrap; gap:12px; }
        .page-title    { font-size:1.5rem; font-weight:700; }
        .status-tabs   { display:flex; gap:8px; margin-bottom:24px; flex-wrap:wrap; }
        .status-tab    { padding:8px 16px; border-radius:20px; font-size:0.85rem; font-weight:600; cursor:pointer; border:1px solid var(--dc-border); background:var(--dc-bg-2); color:var(--dc-text-2); transition:all 0.15s; }
        .status-tab.active { background:rgba(108,99,255,0.15); color:var(--dc-accent-2); border-color:rgba(108,99,255,0.3); }
        .orders-grid   { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:16px; }
        .order-card    { display:flex; flex-direction:column; gap:12px; }
        .order-card-header { display:flex; align-items:center; justify-content:space-between; }
        .order-table   { font-size:1.1rem; font-weight:700; }
        .order-items   { list-style:none; padding:0; margin:0; }
        .order-items li{ display:flex; justify-content:space-between; padding:4px 0; font-size:0.875rem; border-bottom:1px solid var(--dc-border); }
        .order-items li:last-child { border:none; }
        .order-footer  { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; margin-top:4px; }
        .order-time    { font-size:0.8rem; color:var(--dc-text-3); }
        .order-total   { font-weight:700; font-size:1rem; }
        .order-actions { display:flex; gap:8px; }
        .order-note    { background:rgba(245,166,35,0.08); border-left:3px solid var(--dc-warning); padding:8px 12px; border-radius:0 8px 8px 0; font-size:0.8rem; color:var(--dc-text-2); }
        .empty-state   { text-align:center; padding:60px 24px; color:var(--dc-text-3); }
        .empty-state .icon { font-size:3rem; display:block; margin-bottom:12px; }
        .count-badge   { display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; border-radius:50%; font-size:0.7rem; font-weight:700; background:var(--dc-accent); color:#fff; margin-left:6px; }
    </style>
</head>
<body>

<aside class="dc-sidebar">
    <div class="dc-sidebar__logo"><i class="dc-icon dc-icon-utensils"></i> <span>Restrodesk</span></div>
    <div class="dc-sidebar__section">Management</div>
    <a href="dashboard.php"    class="dc-sidebar__link"><i class="dc-icon dc-icon-bar-chart"></i> Dashboard</a>
    <a href="orders.php"       class="dc-sidebar__link active"><i class="dc-icon dc-icon-receipt"></i> Orders</a>
    <a href="menu.php"         class="dc-sidebar__link"><i class="dc-icon dc-icon-clipboard"></i> Menu Items</a>
    <a href="qr-generator.php" class="dc-sidebar__link"><i class="dc-icon dc-icon-qr-code"></i> QR Codes</a>
    <div class="dc-sidebar__section" style="margin-top:auto">Account</div>
    <a href="logout.php"       class="dc-sidebar__link"><i class="dc-icon dc-icon-log-out"></i> Logout</a>
</aside>

<div class="dc-with-sidebar">
    <div class="dc-container" style="padding-top:32px; padding-bottom:48px;">

        <div class="page-header">
            <div>
                <div class="page-title">Kitchen Orders</div>
                <div style="color:var(--dc-text-3);font-size:0.875rem">Live incoming orders — updates every 3 seconds</div>
            </div>
            <div class="dc-live">
                <span class="dc-live__dot"></span>
                Live
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="status-tabs">
            <button class="status-tab active" data-filter="all">All Active <span class="count-badge" id="cnt-all">0</span></button>
            <button class="status-tab" data-filter="pending">Pending <span class="count-badge" id="cnt-pending">0</span></button>
            <button class="status-tab" data-filter="preparing">Preparing <span class="count-badge" id="cnt-preparing">0</span></button>
            <button class="status-tab" data-filter="ready">Ready <span class="count-badge" id="cnt-ready">0</span></button>
            <button class="status-tab" data-filter="delivered">Delivered <span class="count-badge" id="cnt-delivered">0</span></button>
        </div>

        <!-- Orders Grid -->
        <div class="orders-grid" id="ordersGrid">
            <div style="grid-column:1/-1;">
                <div class="empty-state">
                    <i class="dc-icon dc-icon-loader dc-icon-spin dc-icon-xl"></i>
                    <div style="margin-top:16px;color:var(--dc-text-3);">Loading orders...</div>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="../../../core/ui/devcore.js"></script>
<script src="../../../core/utils/helpers.js"></script>
<script>
let currentFilter = 'all';
let knownOrderIds = new Set();

const nextStatus = {
    pending:   'preparing',
    preparing: 'ready',
    ready:     'delivered',
    delivered: 'completed',
};
const nextLabel = {
    pending:   '<i class="dc-icon dc-icon-fire"></i> Start Preparing',
    preparing: '<i class="dc-icon dc-icon-check"></i> Mark Ready',
    ready:     '<i class="dc-icon dc-icon-check"></i> Mark Delivered',
    delivered: '<i class="dc-icon dc-icon-check"></i> Clear Table',
};
const statusBadgeClass = {
    pending:   'dc-badge-warning',
    preparing: 'dc-badge-info',
    ready:     'dc-badge-success',
    delivered: 'dc-badge-neutral',
};
const statusLabel = {
    pending:   'Pending',
    preparing: 'Preparing',
    ready:     'Ready',
    delivered: 'Delivered',
};

function timeAgo(dateStr) {
    if (window.DCHelpers && typeof window.DCHelpers.timeAgo === 'function') {
        return window.DCHelpers.timeAgo(dateStr);
    }
    const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
    if (diff < 60)   return `${diff}s ago`;
    if (diff < 3600) return `${Math.floor(diff/60)}m ago`;
    return `${Math.floor(diff/3600)}h ago`;
}

function renderOrders(orders) {

    const filtered = currentFilter === 'all'
        ? orders
        : orders.filter(o => o.status === currentFilter);

    const grid = document.getElementById('ordersGrid');
    // Always clear grid before rendering
    grid.innerHTML = "";

    if (!filtered.length) {
        grid.innerHTML = `<div style="grid-column:1/-1;">
            <div class="empty-state">
                <span class="icon"><i class="dc-icon dc-icon-check"></i></span>
                No active orders${currentFilter !== 'all' ? ' with status "' + currentFilter + '"' : ''}
            </div>
        </div>`;
        return;
    }

    // Add new cards / update existing ones
    const existingIds = new Set([...grid.querySelectorAll('[data-order-id]')].map(el => el.dataset.orderId));

    filtered.forEach(order => {
        const isNew = !knownOrderIds.has(order.id);
        const card  = document.querySelector(`[data-order-id="${order.id}"]`);

        const itemsList = order.items.map(i =>
            `<li><span>${i.name}</span><span style="color:var(--dc-text-3)">×${i.quantity}</span></li>`
        ).join('');

        const noteHtml = order.note
            ? `<div class="order-note"><i class="dc-icon dc-icon-note"></i> ${order.note}</div>` : '';

        const customerParts = [order.customer_name, order.customer_phone, order.customer_email]
            .filter(Boolean)
            .map(v => String(v));
        const customerHtml = customerParts.length
            ? `<div style="font-size:0.8rem;color:var(--dc-text-3);margin-top:2px;">${customerParts.join(' · ')}</div>`
            : '';

        const html = `
            <div class="order-card-header">
                <div>
                    <div class="order-table">${order.table_name}</div>
                    ${customerHtml}
                </div>
                <span class="dc-badge ${statusBadgeClass[order.status]}">${statusLabel[order.status]}</span>
            </div>
            <ul class="order-items">${itemsList}</ul>
            ${noteHtml}
            <div class="order-footer">
                <div>
                    <div class="order-total">$${parseFloat(order.total).toFixed(2)}</div>
                    <div class="order-time">${timeAgo(order.created_at)}</div>
                </div>
                <div class="order-actions">
                    ${nextStatus[order.status] ? `
                    <button class="dc-btn dc-btn-sm dc-btn-primary"
                            onclick="updateStatus(${order.id}, '${nextStatus[order.status]}')">
                        ${nextLabel[order.status]}
                    </button>` : ''}
                </div>
            </div>`;

        if (card) {
            card.innerHTML = html;
        } else {
            const div = document.createElement('div');
            div.className = `dc-card order-card${isNew && knownOrderIds.size > 0 ? ' dc-animate-fade-up' : ''}`;
            div.dataset.orderId = order.id;
            div.innerHTML = html;
            grid.appendChild(div);
            if (isNew && knownOrderIds.size > 0) {
                Toast.info(`New order from ${order.table_name}!`);
            }
        }
        knownOrderIds.add(order.id);
    });

    // Remove cards no longer in active list
    [...grid.querySelectorAll('[data-order-id]')].forEach(el => {
        const id = parseInt(el.dataset.orderId);
        if (!filtered.find(o => o.id === id)) el.remove();
    });
}

function updateCounts(counts) {
    const total = (counts.pending || 0) + (counts.preparing || 0) + (counts.ready || 0) + (counts.delivered || 0);
    document.getElementById('cnt-all').textContent      = total;
    document.getElementById('cnt-pending').textContent  = counts.pending   || 0;
    document.getElementById('cnt-preparing').textContent= counts.preparing || 0;
    document.getElementById('cnt-ready').textContent    = counts.ready     || 0;
    document.getElementById('cnt-delivered').textContent= counts.delivered || 0;
}

async function updateStatus(orderId, status) {
    try {
        await DC.put('../api/orders.php', { order_id: orderId, status });
        Toast.success(`Order #${orderId} marked as ${status}`);
    } catch (e) {
        Toast.error('Failed to update status: ' + e.message);
    }
}

// Wire up filter tabs
document.querySelectorAll('.status-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.status-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        currentFilter = tab.dataset.filter;
    });
});

// Start live polling
const poller = new LivePoller('../api/live.php', (res) => {
    const d = res.data;
    renderOrders(d.active_orders);
    updateCounts(d.counts);
}, 3000);

poller.start();
</script>
</body>
</html>
