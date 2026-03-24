<?php
/**
 * order-status.php — Real-time order status for customer
 * Polls /api/live.php?order_id=X every 3 seconds via LivePoller
 * Supports ?token= or ?order_id= lookup
 */
require_once __DIR__ . '/core/bootstrap.php';

$db         = Database::getInstance();
$orderId    = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$orderToken = isset($_GET['token']) ? trim($_GET['token']) : '';

if ($orderToken) {
    $order = $db->fetchOne(
        'SELECT o.*, t.name AS table_name FROM orders o
         LEFT JOIN `tables` t ON t.id = o.table_id
         WHERE o.token = ?',
        [$orderToken]
    );
    if ($order) $orderId = (int)$order['id'];
} elseif ($orderId) {
    $order = $db->fetchOne(
        'SELECT o.*, t.name AS table_name FROM orders o
         LEFT JOIN `tables` t ON t.id = o.table_id
         WHERE o.id = ?',
        [$orderId]
    );
    if ($order) $orderToken = $order['token'];
}

if (!$orderId || empty($order)) {
    header('Location: index.php');
    exit;
}

// Pre-load order items for the receipt (PHP-rendered, always visible)
$orderItems = $db->fetchAll(
    'SELECT oi.name, oi.quantity, oi.unit_price FROM order_items oi WHERE oi.order_id = ?',
    [$orderId]
);
$orderTotal = array_reduce($orderItems, fn($s, $i) => $s + $i['unit_price'] * $i['quantity'], 0);
$customerName = trim((string)($order['customer_name'] ?? ''));
$customerPhone = trim((string)($order['customer_phone'] ?? ''));
$customerEmail = trim((string)($order['customer_email'] ?? ''));
$customerAddressNotes = trim((string)($order['customer_address_notes'] ?? ''));
$hasCustomerDetails = $customerName !== '' || $customerPhone !== '' || $customerEmail !== '' || $customerAddressNotes !== '';

// QR code pointing back to this page via token (stable, shareable URL)
$baseUrl   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
           . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
           . dirname($_SERVER['SCRIPT_NAME']);
$statusUrl = $baseUrl . '/order-status.php?token=' . urlencode($orderToken);
$qrSrc     = QrCode::url($statusUrl, 160);
$qrDataUri = null;
try {
    $qrBinary = @file_get_contents($qrSrc);
    if ($qrBinary !== false) {
        $qrDataUri = 'data:image/png;base64,' . base64_encode($qrBinary);
    }
} catch (Throwable $e) {
    $qrDataUri = null;
}

// Pre-render item rows (used both on-page and in PDF generation)
$itemRowsHtml = '';
foreach ($orderItems as $item) {
    $name  = htmlspecialchars($item['name']);
    $qty   = (int)$item['quantity'];
    $price = number_format($item['unit_price'] * $item['quantity'], 2);
    $itemRowsHtml .= "<div class=\"receipt-item-row\">
        <span>{$name} <span class=\"item-qty\">×{$qty}</span></span>
        <span>\${$price}</span>
    </div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Status — Restrodesk</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/devcore-suite/core/ui/devcore.css">
    <link rel="stylesheet" href="/devcore-suite/core/ui/parts/_icons.css">
    <style>
        :root { --dc-accent:#e8a838; --dc-accent-2:#f0c060; --dc-accent-glow:rgba(232,168,56,0.2); }

        /* ── layout ── */
        .page-wrap     { max-width:640px; margin:0 auto; padding:32px 20px 80px; }

        /* ── receipt card ── */
        .receipt-card  {
            background:var(--dc-bg-2);
            border:1px solid var(--dc-border-2);
            border-radius:var(--dc-radius-xl);
            overflow:hidden;
            margin-bottom:24px;
            box-shadow:0 18px 38px rgba(0,0,0,0.22);
            position:relative;
        }
        .receipt-card::before {
            content:'';
            position:absolute;
            top:0;
            left:0;
            width:100%;
            height:4px;
            background:linear-gradient(90deg,var(--dc-accent),var(--dc-accent-2));
        }
        .receipt-header {
            background:linear-gradient(135deg,rgba(232,168,56,0.12),rgba(232,168,56,0.02));
            padding:18px 24px; border-bottom:1px solid var(--dc-border);
            display:flex; align-items:flex-start; justify-content:space-between;
            gap:12px;
        }
        .receipt-title-label { font-size:0.74rem; color:var(--dc-text-3); font-weight:700; letter-spacing:0.08em; text-transform:uppercase; }
        .receipt-title-main { font-family:var(--dc-font-display); font-size:1.2rem; font-weight:700; margin-top:3px; }
        .receipt-meta-right { text-align:right; display:flex; flex-direction:column; gap:6px; align-items:flex-end; }
        .receipt-meta-right small { color:var(--dc-text-3); font-size:0.74rem; }

        .receipt-qr-row {
            display:flex; align-items:flex-start; gap:20px; flex-wrap:wrap;
            padding:18px 24px; border-bottom:1px solid var(--dc-border);
            background:linear-gradient(180deg,rgba(255,255,255,0.03),transparent);
        }
        .qr-box {
            background:#fff; border-radius:10px; padding:10px;
            box-shadow:0 2px 10px rgba(0,0,0,0.07); flex-shrink:0; text-align:center;
            border:1px solid #ececec;
        }
        .qr-box img    { display:block; border-radius:6px; }
        .qr-box small  { display:block; margin-top:6px; font-size:0.72rem; color:#888; }
        .token-box     { flex:1; min-width:140px; display:flex; flex-direction:column; gap:6px; }
        .token-label   { font-size:0.7rem; color:var(--dc-text-3); margin-bottom:5px; }
        .token-value   {
            font-family:monospace; font-size:1.02rem; letter-spacing:0.06em;
            background:rgba(255,255,255,0.06); border:1px solid var(--dc-border);
            color:var(--dc-text); padding:7px 12px; border-radius:8px;
            display:inline-block; word-break:break-all;
        }
        .receipt-customer {
            padding:14px 24px;
            border-bottom:1px solid var(--dc-border);
            background:rgba(232,168,56,0.04);
        }
        .customer-row {
            display:flex;
            gap:8px;
            font-size:0.84rem;
            margin-top:4px;
            color:var(--dc-text-2);
            flex-wrap:wrap;
        }
        .customer-label {
            min-width:78px;
            color:var(--dc-text-3);
            font-weight:600;
        }
        .receipt-items { padding:16px 24px; }
        .receipt-item-row {
            display:flex; justify-content:space-between; align-items:center;
            padding:8px 0; border-bottom:1px solid var(--dc-border); gap:12px;
            font-size:0.9rem;
        }
        .receipt-item-row:last-child { border-bottom:none; }
        .item-qty      { color:var(--dc-text-3); }
        .receipt-total { display:flex; justify-content:space-between; font-weight:800; font-size:1rem; padding-top:12px; border-top:1px solid var(--dc-border); margin-top:4px; }
        .total-amt     { font-family:var(--dc-font-display); color:var(--dc-accent-2); }
        .receipt-actions { display:flex; gap:10px; flex-wrap:wrap; justify-content:center; padding:14px 24px 20px; border-top:1px solid var(--dc-border); }

        /* ── progress tracker ── */
        .progress-track { display:flex; align-items:flex-start; margin:28px 0 20px; }
        .progress-step  { display:flex; flex-direction:column; align-items:center; flex:1; text-align:center; position:relative; }
        .progress-step:not(:last-child)::after {
            content:''; position:absolute; top:20px;
            left:calc(50% + 21px); width:calc(100% - 42px);
            height:3px; background:var(--dc-border); z-index:0; transition:background 0.4s;
        }
        .progress-step.done:not(:last-child)::after   { background:var(--dc-success); }
        .progress-step.active:not(:last-child)::after { background:linear-gradient(90deg,var(--dc-success) 0%,var(--dc-border) 60%); }
        .step-icon      {
            width:42px; height:42px; border-radius:50%;
            border:3px solid var(--dc-border); background:var(--dc-bg-2);
            display:flex; align-items:center; justify-content:center;
            position:relative; z-index:1; transition:all 0.4s;
        }
        .progress-step.done .step-icon   { border-color:var(--dc-success); background:rgba(34,211,160,0.12); }
        .progress-step.active .step-icon { border-color:var(--dc-accent); background:var(--dc-accent-glow); box-shadow:0 0 0 6px var(--dc-accent-glow); }
        .step-label     { font-size:0.72rem; font-weight:600; margin-top:7px; color:var(--dc-text-3); transition:color 0.4s; }
        .progress-step.done .step-label   { color:var(--dc-success); }
        .progress-step.active .step-label { color:var(--dc-accent-2); }

        /* ── status hero card ── */
        .status-hero      { text-align:center; padding:28px 20px; }
        .status-hero h2   { font-family:var(--dc-font-display); font-size:1.35rem; font-weight:800; margin:12px 0 6px; }
        .status-hero p    { color:var(--dc-text-2); margin:0; font-size:0.9rem; }
        .status-emoji     { display:inline-flex; align-items:center; justify-content:center; color:var(--dc-accent-2); }
        .status-emoji .dc-icon { width:48px; height:48px; }
        @keyframes pulse-ready { 0%,100%{transform:scale(1)} 50%{transform:scale(1.06)} }
        .status-ready .status-emoji { animation:pulse-ready 1.5s ease-in-out infinite; }

        /* ── PRINT STYLES ── */
        @media print {
            /* show only the receipt card */
            .dc-nav, .progress-track, #statusCard, .page-bottom { display:none !important; }
            .receipt-actions { display:none !important; }
            body, .page-wrap, .receipt-card,
            .receipt-header, .receipt-qr-row,
            .receipt-items { background:#fff !important; color:#111 !important; }
            .receipt-card  { border:2px solid #ccc !important; box-shadow:none !important; }
            .token-value   { background:#f5f5f5 !important; color:#111 !important; border-color:#ccc !important; }
            .receipt-item-row { border-bottom-color:#ddd !important; }
            .receipt-total    { border-top-color:#ddd !important; }
            .total-amt        { color:#b8860b !important; }
            .item-qty         { color:#666 !important; }
            .qr-box           { box-shadow:none !important; border:1px solid #ddd; }
        }
    </style>
</head>
<body>
<nav class="dc-nav">
    <a href="index.php" style="text-decoration:none">
        <div class="dc-nav__brand">
            <i class="dc-icon dc-icon-utensils dc-icon-sm"></i> Restrodesk
        </div>
    </a>
    <div class="dc-nav__links">
        <div class="dc-live"><span class="dc-live__dot"></span>Live</div>
    </div>
</nav>

<div class="page-wrap">

    <!-- ── RECEIPT CARD (PHP-rendered, always visible, prints cleanly) ── -->
    <div class="receipt-card dc-animate-fade-up" id="receiptCard">

        <div class="receipt-header">
            <div>
                <div class="receipt-title-label">Order Receipt</div>
                <div class="receipt-title-main">Table: <?= htmlspecialchars($order['table_name'] ?? '—') ?></div>
            </div>
            <div class="receipt-meta-right">
                <span class="dc-badge dc-badge-accent" id="statusBadge">Loading…</span>
                <small>Order #<?= (int)$orderId ?></small>
            </div>
        </div>

        <?php if ($hasCustomerDetails): ?>
        <div class="receipt-customer">
            <div class="dc-label" style="margin-bottom:6px;">Customer Details</div>
            <?php if ($customerName !== ''): ?>
            <div class="customer-row"><span class="customer-label">Name</span><span><?= htmlspecialchars($customerName) ?></span></div>
            <?php endif; ?>
            <?php if ($customerPhone !== ''): ?>
            <div class="customer-row"><span class="customer-label">Phone</span><span><?= htmlspecialchars($customerPhone) ?></span></div>
            <?php endif; ?>
            <?php if ($customerEmail !== ''): ?>
            <div class="customer-row"><span class="customer-label">Email</span><span><?= htmlspecialchars($customerEmail) ?></span></div>
            <?php endif; ?>
            <?php if ($customerAddressNotes !== ''): ?>
            <div class="customer-row"><span class="customer-label">Address/Notes</span><span><?= htmlspecialchars($customerAddressNotes) ?></span></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- QR + Token -->
        <div class="receipt-qr-row">
            <div class="qr-box">
                <img src="<?= htmlspecialchars($qrSrc) ?>" alt="Order QR Code" width="120" height="120">
                <small>Scan to track order</small>
            </div>
            <div class="token-box">
                <div class="token-label">Order Token</div>
                <div class="token-value"><?= htmlspecialchars($orderToken) ?></div>
                    <div class="dc-text-dim" style="margin-top:8px; font-size:0.78rem;">
                    Show this to staff if needed
                </div>
            </div>
        </div>

        <!-- Items (PHP-rendered — always populated, no JS required) -->
        <div class="receipt-items">
            <?= $itemRowsHtml ?>
            <div class="receipt-total">
                <span>Total</span>
                <span class="total-amt">$<?= number_format($orderTotal, 2) ?></span>
            </div>
        </div>

        <!-- Action buttons (hidden on print) -->
        <div class="receipt-actions">
            <button class="dc-btn dc-btn-primary" onclick="printReceipt()">
                <i class="dc-icon dc-icon-printer dc-icon-sm"></i> Print Receipt
            </button>
            <button class="dc-btn dc-btn-ghost" onclick="downloadPDF()">
                <i class="dc-icon dc-icon-download dc-icon-sm"></i> Save as PDF
            </button>
        </div>
    </div>

    <!-- ── LIVE STATUS TRACKER ── -->
    <div class="page-bottom">
        <!-- Step Progress -->
        <div class="progress-track" id="progressTrack">
            <div class="progress-step" id="step-pending">
                <div class="step-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
                </div>
                <div class="step-label">Received</div>
            </div>
            <div class="progress-step" id="step-preparing">
                <div class="step-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 13.87A4 4 0 0 1 7.41 6a5.11 5.11 0 0 1 1.05-1.54 5 5 0 0 1 7.08 0A5.11 5.11 0 0 1 16.59 6 4 4 0 0 1 18 13.87V21H6Z"/><path d="M6 17h12"/></svg>
                </div>
                <div class="step-label">Preparing</div>
            </div>
            <div class="progress-step" id="step-ready">
                <div class="step-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div class="step-label">Ready</div>
            </div>
            <div class="progress-step" id="step-delivered">
                <div class="step-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"/><circle cx="12" cy="10" r="3"/></svg>
                </div>
                <div class="step-label">Delivered</div>
            </div>
        </div>

        <!-- Status Hero -->
        <div class="dc-card" id="statusCard">
            <div class="status-hero">
                <span class="status-emoji"><i class="dc-icon dc-icon-clock"></i></span>
                <h2>Loading your order…</h2>
                <p>Please wait</p>
            </div>
        </div>

        <a href="index.php" class="dc-btn dc-btn-ghost dc-btn-full" style="margin-top:16px;">
            <i class="dc-icon dc-icon-arrow-left dc-icon-sm"></i> Order More Items
        </a>
    </div>

</div><!-- /page-wrap -->

<!-- jsPDF only — pure text PDF, no dark-bg issues -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="/devcore-suite/core/ui/devcore.js"></script>
<script>
// ── Print ──────────────────────────────────────────────────────
// @media print CSS hides everything except the receipt card.
function printReceipt() {
    window.print();
}

// ── PDF (pure jsPDF text — no html2canvas, no dark-bg capture) ─
function downloadPDF() {
    const { jsPDF } = window.jspdf;
    const doc  = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
    const gold = [184, 134, 11];
    const dark = [28, 28, 28];
    const mute = [104, 104, 104];
    const line = [218, 218, 218];
    const soft = [248, 244, 235];
    const L = 16, R = 194, W = R - L;
    let y = 18;
    const qrDataUri = <?= json_encode($qrDataUri) ?>;

    doc.setDrawColor(222, 222, 222);
    doc.roundedRect(L, y, W, 250, 4, 4, 'S');

    doc.setFillColor(...soft);
    doc.roundedRect(L + 2, y + 2, W - 4, 24, 3, 3, 'F');
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(15);
    doc.setTextColor(...dark);
    doc.text('Restrodesk Receipt', L + 7, y + 11);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(9);
    doc.setTextColor(...mute);
    doc.text('Order #<?= (int)$orderId ?>', R - 7, y + 8, { align: 'right' });
    doc.text('Table: <?= addslashes(htmlspecialchars_decode($order['table_name'] ?? '—')) ?>', L + 7, y + 19);
    y += 32;

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(8);
    doc.setTextColor(...mute);
    doc.text('CUSTOMER DETAILS', L + 7, y);
    y += 5;
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(9);
    doc.setTextColor(...dark);
    <?php if ($customerName !== ''): ?>
    doc.text('Name: <?= addslashes(htmlspecialchars_decode($customerName)) ?>', L + 7, y); y += 5;
    <?php endif; ?>
    <?php if ($customerPhone !== ''): ?>
    doc.text('Phone: <?= addslashes(htmlspecialchars_decode($customerPhone)) ?>', L + 7, y); y += 5;
    <?php endif; ?>
    <?php if ($customerEmail !== ''): ?>
    doc.text('Email: <?= addslashes(htmlspecialchars_decode($customerEmail)) ?>', L + 7, y); y += 5;
    <?php endif; ?>
    <?php if ($customerAddressNotes !== ''): ?>
    doc.text('Address/Notes: <?= addslashes(htmlspecialchars_decode($customerAddressNotes)) ?>', L + 7, y); y += 5;
    <?php endif; ?>

    doc.setDrawColor(...line);
    doc.line(L + 6, y + 1, R - 6, y + 1);
    y += 8;

    doc.setFillColor(252, 252, 252);
    doc.roundedRect(L + 6, y - 2, W - 12, 18, 2, 2, 'F');
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(8);
    doc.setTextColor(...mute);
    doc.text('ORDER TOKEN', L + 9, y + 3);
    doc.setFont('courier', 'bold');
    doc.setFontSize(12);
    doc.setTextColor(...dark);
    doc.text('<?= addslashes($orderToken) ?>', L + 9, y + 11);

    if (qrDataUri) {
        try {
            doc.setDrawColor(230, 230, 230);
            doc.roundedRect(R - 41, y - 1, 30, 30, 2, 2, 'S');
            doc.addImage(qrDataUri, 'PNG', R - 39, y + 1, 26, 26);
        } catch (e) {
            // Keep text receipt usable even if QR image embedding fails.
        }
    }
    y += 31;

    doc.setFont('helvetica', 'normal');
    doc.setFontSize(8);
    doc.setTextColor(...mute);
    doc.text('Scan the QR code on your receipt page to track this order.', L + 7, y);
    y += 8;

    doc.setFillColor(246, 246, 246);
    doc.rect(L + 6, y - 4, W - 12, 8, 'F');
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(8);
    doc.setTextColor(...mute);
    doc.text('ITEM', L + 9, y + 1);
    doc.text('AMOUNT', R - 9, y + 1, { align: 'right' });
    y += 8;

    doc.setFont('helvetica', 'normal');
    doc.setFontSize(10);
    doc.setTextColor(...dark);
    <?php foreach ($orderItems as $item): ?>
    doc.text('<?= addslashes(htmlspecialchars_decode($item['name'])) ?> ×<?= (int)$item['quantity'] ?>', L + 9, y);
    doc.text('$<?= number_format($item['unit_price'] * $item['quantity'], 2) ?>', R - 9, y, { align: 'right' });
    y += 7;
    doc.setDrawColor(235, 235, 235);
    doc.line(L + 8, y - 2, R - 8, y - 2);
    <?php endforeach; ?>
    y += 3;

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(12);
    doc.setTextColor(...dark);
    doc.text('Total', L + 9, y);
    doc.setTextColor(...gold);
    doc.text('$<?= number_format($orderTotal, 2) ?>', R - 9, y, { align: 'right' });
    y += 12;

    const badge = document.getElementById('statusBadge');
    if (badge) {
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(9);
        doc.setTextColor(...mute);
        doc.text('Status at time of download: ' + badge.textContent.trim(), L + 7, y);
        y += 8;
    }

    doc.setFontSize(8);
    doc.setTextColor(...mute);
    doc.text('Thank you for dining with us! • Restrodesk', L + W / 2, y + 4, { align: 'center' });

    doc.save('receipt-<?= $orderToken ?>.pdf');
    Toast.success('Receipt downloaded!');
}

// ── Live polling ────────────────────────────────────────────────
const ORDER_ID = <?= json_encode($orderId) ?>;
let lastStatus = null;

const statusConfig = {
    pending: {
        emoji: '<i class="dc-icon dc-icon-inbox"></i>',
        title: 'Order Received!',
        desc:  "We've got your order. The kitchen will start on it shortly.",
        badge: 'Received',
        badgeClass: 'dc-badge-warning',
        cls: '',
    },
    preparing: {
        emoji: '<i class="dc-icon dc-icon-chef-hat"></i>',
        title: "We're Cooking!",
        desc:  'Your food is being prepared fresh. Sit tight!',
        badge: 'Preparing',
        badgeClass: 'dc-badge-info',
        cls: '',
    },
    ready: {
        emoji: '<i class="dc-icon dc-icon-check"></i>',
        title: 'Your Order is Ready!',
        desc:  'Your food is on its way to your table right now.',
        badge: 'Ready ✓',
        badgeClass: 'dc-badge-success',
        cls: 'status-ready',
    },
    delivered: {
        emoji: '<i class="dc-icon dc-icon-utensils"></i>',
        title: 'Enjoy Your Meal!',
        desc:  'Your order has been delivered. Bon appétit!',
        badge: 'Delivered',
        badgeClass: 'dc-badge-neutral',
        cls: '',
    },
    completed: {
        emoji: '<i class="dc-icon dc-icon-check"></i>',
        title: 'Session Closed',
        desc:  'This table session has been closed by staff. Thank you!',
        badge: 'Completed',
        badgeClass: 'dc-badge-neutral',
        cls: '',
    },
};

const stepOrder = ['pending', 'preparing', 'ready', 'delivered'];

function updateProgress(status) {
    const idx = stepOrder.indexOf(status);
    stepOrder.forEach((s, i) => {
        const el = document.getElementById('step-' + s);
        if (!el) return;
        el.classList.remove('done', 'active');
        if (i < idx)       el.classList.add('done');
        else if (i === idx) el.classList.add('active');
    });
}

function updateStatusCard(status) {
    const cfg  = statusConfig[status] || statusConfig.pending;
    const card = document.getElementById('statusCard');
    card.className = `dc-card dc-animate-fade-in ${cfg.cls}`;
    card.innerHTML = `
        <div class="status-hero">
            <span class="status-emoji">${cfg.emoji}</span>
            <h2>${cfg.title}</h2>
            <p>${cfg.desc}</p>
        </div>`;

    // Update receipt badge to reflect current status
    const badge = document.getElementById('statusBadge');
    if (badge) {
        badge.className = `dc-badge ${cfg.badgeClass}`;
        badge.textContent = cfg.badge;
    }

    if ((status === 'delivered' || status === 'completed') && window._poller) {
        window._poller.stop();
    }
}

function handlePoll(res) {
    const status = res.data.order.status;
    if (status !== lastStatus) {
        lastStatus = status;
        updateProgress(status);
        updateStatusCard(status);
        if (status === 'ready')     Toast.success('Your order is ready!', 6000);
        if (status === 'preparing') Toast.info('The kitchen has started your order!');
    }
}

const poller = new LivePoller(`api/live.php?order_id=${ORDER_ID}`, handlePoll, 3000);
window._poller = poller;
poller.start();
</script>
</body>
</html>