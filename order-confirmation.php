<?php
require_once __DIR__ . '/core/bootstrap.php';

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/order-confirmation.php'));
$assetBase = rtrim($scriptDir, '/');

$orderId = (int)($_GET['order_id'] ?? 0);
if (!$orderId) {
    header('Location: index.php');
    exit;
}

$db    = Database::getInstance();
$order = $db->fetchOne(
    'SELECT o.*, t.name AS table_name
     FROM orders o
     LEFT JOIN `tables` t ON t.id = o.table_id
     WHERE o.id = ?',
    [$orderId]
);
if (!$order) {
    header('Location: index.php');
    exit;
}

$orderToken = $order['token'];
$orderItems = $db->fetchAll(
    'SELECT oi.*, m.name FROM order_items oi
     JOIN menu_items m ON m.id = oi.menu_item_id
     WHERE oi.order_id = ?',
    [$orderId]
);
$total = array_reduce($orderItems, fn($s, $i) => $s + $i['unit_price'] * $i['quantity'], 0);
$customerName = trim((string)($order['customer_name'] ?? ''));
$customerPhone = trim((string)($order['customer_phone'] ?? ''));
$customerEmail = trim((string)($order['customer_email'] ?? ''));
$customerAddressNotes = trim((string)($order['customer_address_notes'] ?? ''));
$hasCustomerDetails = $customerName !== '' || $customerPhone !== '' || $customerEmail !== '' || $customerAddressNotes !== '';

// Build the QR code URL pointing to order-status with the token
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
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

// Pre-render item rows for the receipt (used both on-page and in print HTML)
$itemRowsHtml = '';
foreach ($orderItems as $item) {
    $name  = htmlspecialchars($item['name']);
    $qty   = (int)$item['quantity'];
    $price = number_format($item['unit_price'] * $item['quantity'], 2);
    $itemRowsHtml .= "<div class=\"item-row\">
        <span>{$name} <span class=\"qty\">×{$qty}</span></span>
        <span>\${$price}</span>
    </div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmed — Restrodesk</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars($assetBase . '/core/ui/devcore.css', ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($assetBase . '/core/ui/parts/_icons.css', ENT_QUOTES, 'UTF-8') ?>">
    <style>
        :root { --dc-accent:#e8a838; --dc-accent-2:#f0c060; --dc-accent-glow:rgba(232,168,56,0.2); }

        .page-wrap     { max-width:640px; margin:0 auto; padding:48px 24px 80px; }

        /* animated check */
        .check-circle  {
            width:80px; height:80px; border-radius:50%;
            background:rgba(34,211,160,0.12); border:3px solid var(--dc-success);
            display:flex; align-items:center; justify-content:center;
            margin:0 auto 24px;
            animation:checkPop 0.6s var(--dc-ease) both;
        }
        @keyframes checkPop {
            from { transform:scale(0) rotate(-45deg); opacity:0; }
            80%  { transform:scale(1.15); }
            to   { transform:scale(1); opacity:1; }
        }

        /* receipt card */
        .receipt-card  {
            background:var(--dc-bg-2);
            border:1px solid var(--dc-border-2);
            border-radius:var(--dc-radius-xl);
            overflow:hidden;
            max-width:520px;
            margin:0 auto 24px;
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
            padding:20px 28px;
            border-bottom:1px solid var(--dc-border);
            display:flex; align-items:flex-start; justify-content:space-between;
            gap:12px;
        }
        .receipt-title-label { font-size:0.74rem; color:var(--dc-text-3); font-weight:700; letter-spacing:0.08em; text-transform:uppercase; }
        .receipt-title-main { font-family:var(--dc-font-display); font-size:1.25rem; font-weight:700; margin-top:3px; }
        .receipt-meta-right { text-align:right; display:flex; flex-direction:column; gap:6px; align-items:flex-end; }
        .receipt-meta-right small { color:var(--dc-text-3); font-size:0.74rem; }

        .receipt-qr-row {
            display:flex; align-items:flex-start; gap:24px; flex-wrap:wrap;
            padding:20px 28px; border-bottom:1px solid var(--dc-border);
            background:linear-gradient(180deg,rgba(255,255,255,0.03),transparent);
        }
        .qr-box {
            background:#fff; border-radius:12px; padding:12px;
            box-shadow:0 2px 12px rgba(0,0,0,0.08); flex-shrink:0;
            text-align:center;
            border:1px solid #ececec;
        }
        .qr-box img    { display:block; border-radius:6px; }
        .qr-box p      { margin:8px 0 0; font-size:0.75rem; color:#888; }
        .token-box     { flex:1; min-width:160px; display:flex; flex-direction:column; gap:6px; }
        .token-label   { font-size:0.7rem; color:var(--dc-text-3); margin-bottom:6px; }
        .token-value   {
            font-family:monospace; font-size:1.02rem;
            background:rgba(255,255,255,0.06); border:1px solid var(--dc-border);
            color:var(--dc-text); padding:8px 14px; border-radius:8px;
            letter-spacing:0.06em; display:inline-block; word-break:break-all;
        }
        .receipt-customer {
            padding:14px 28px;
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
        .receipt-items { padding:20px 28px; }
        .item-row      { display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--dc-border); gap:12px; font-size:0.9rem; }
        .item-row:last-child { border-bottom:none; }
        .item-row .qty { color:var(--dc-text-3); }
        .total-row     { display:flex; justify-content:space-between; font-weight:800; font-size:1rem; padding-top:14px; border-top:1px solid var(--dc-border); margin-top:4px; }
        .total-amt     { font-family:var(--dc-font-display); color:var(--dc-accent-2); }

        /* action buttons */
        .action-row    { display:flex; gap:10px; flex-wrap:wrap; justify-content:center; padding:16px 28px 24px; border-top:1px solid var(--dc-border); }
        .dc-btn-status { background:var(--dc-accent); border-color:var(--dc-accent); color:#000; font-weight:700; }
        .dc-btn-status:hover { background:var(--dc-accent-2); border-color:var(--dc-accent-2); }

        /* ── PRINT STYLES ── */
        @media print {
            /* hide everything except the receipt */
            body > *:not(.page-wrap) { display:none !important; }
            .page-wrap > *:not(.receipt-card) { display:none !important; }
            .action-row, .receipt-actions { display:none !important; }
            /* force white backgrounds & dark text */
            body, .page-wrap, .receipt-card,
            .receipt-header, .receipt-qr-row,
            .receipt-items { background:#fff !important; color:#111 !important; }
            .receipt-card  { border:2px solid #ccc !important; box-shadow:none !important; }
            .token-value   { background:#f5f5f5 !important; color:#111 !important; border-color:#ccc !important; }
            .item-row      { border-bottom-color:#ddd !important; }
            .total-row     { border-top-color:#ddd !important; }
            .total-amt     { color:#b8860b !important; }
            .qty           { color:#666 !important; }
            /* show all QR image */
            .qr-box        { box-shadow:none !important; border:1px solid #ddd; }
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
</nav>

<div class="page-wrap">

    <!-- Success heading -->
    <div style="text-align:center; margin-bottom:28px;" class="dc-animate-fade-up">
        <div class="check-circle">
            <i class="dc-icon dc-icon-check dc-icon-xl" style="color:var(--dc-success);"></i>
        </div>
        <h1 class="dc-h2" style="margin-bottom:8px;">Order Confirmed!</h1>
        <p style="color:var(--dc-text-2);">Your order has been placed. We'll start preparing it right away!</p>
    </div>

    <!-- THE RECEIPT CARD — everything inside prints cleanly -->
    <div class="receipt-card dc-animate-fade-up" id="receiptCard" style="animation-delay:0.08s;">

        <!-- Header -->
        <div class="receipt-header">
            <div>
                <div class="receipt-title-label">Order Receipt</div>
                <div class="receipt-title-main">Table: <?= htmlspecialchars($order['table_name'] ?? '—') ?></div>
            </div>
            <div class="receipt-meta-right">
                <span class="dc-badge dc-badge-success">Confirmed</span>
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
                <p>Scan to track order</p>
            </div>
            <div class="token-box">
                <div class="token-label">Order Token</div>
                <div class="token-value"><?= htmlspecialchars($orderToken) ?></div>
                <div class="dc-text-dim" style="margin-top:10px; font-size:0.8rem;">
                    Show this token or QR code to staff if needed.
                </div>
            </div>
        </div>

        <!-- Items -->
        <div class="receipt-items">
            <?= $itemRowsHtml ?>
            <div class="total-row">
                <span>Total</span>
                <span class="total-amt">$<?= number_format($total, 2) ?></span>
            </div>
        </div>

        <!-- Action buttons (hidden on print) -->
        <div class="action-row receipt-actions">
            <button class="dc-btn dc-btn-primary" onclick="printReceipt()">
                <i class="dc-icon dc-icon-printer dc-icon-sm"></i> Print Receipt
            </button>
            <button class="dc-btn dc-btn-ghost" onclick="downloadPDF()">
                <i class="dc-icon dc-icon-download dc-icon-sm"></i> Save as PDF
            </button>
        </div>
    </div>

    <!-- Track order CTA -->
    <div class="dc-animate-fade-up receipt-actions" style="text-align:center;animation-delay:0.14s;">
        <a href="order-status.php?token=<?= urlencode($orderToken) ?>" class="dc-btn dc-btn-status dc-btn-lg">
            <i class="dc-icon dc-icon-eye dc-icon-sm"></i> Track Order Status
        </a>
    </div>

</div><!-- /page-wrap -->

<!-- jsPDF only — no html2canvas needed -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="<?= htmlspecialchars($assetBase . '/core/ui/devcore.js', ENT_QUOTES, 'UTF-8') ?>"></script>
<script>
// ── Print ──────────────────────────────────────────────────────
// Simply call window.print() — @media print CSS handles hiding
// everything except the receipt card cleanly.
function printReceipt() {
    window.print();
}

// ── PDF via jsPDF text (no html2canvas = no dark bg issue) ─────
function downloadPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });

    const accentColor = [184, 134, 11];
    const textColor   = [28, 28, 28];
    const mutedColor  = [104, 104, 104];
    const lineColor   = [218, 218, 218];
    const softFill    = [248, 244, 235];

    let y = 18;
        const qrDataUri = <?= json_encode($qrDataUri) ?>;
    const L = 16;
    const R = 194;
    const W = R - L;

    doc.setDrawColor(222, 222, 222);
    doc.roundedRect(L, y, W, 250, 4, 4, 'S');

    doc.setFillColor(...softFill);
    doc.roundedRect(L + 2, y + 2, W - 4, 24, 3, 3, 'F');
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(15);
    doc.setTextColor(...textColor);
    doc.text('Restrodesk Receipt', L + 7, y + 11);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(9);
    doc.setTextColor(...mutedColor);
    doc.text('Order #<?= (int)$orderId ?>', R - 7, y + 8, { align: 'right' });
    doc.text('Table: <?= addslashes(htmlspecialchars_decode($order['table_name'] ?? '—')) ?>', L + 7, y + 19);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(8);
    doc.setTextColor(...accentColor);
    doc.text('CONFIRMED', R - 7, y + 19, { align: 'right' });
    y += 32;

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(8);
    doc.setTextColor(...mutedColor);
    doc.text('CUSTOMER DETAILS', L + 7, y);
    y += 5;
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(9);
    doc.setTextColor(...textColor);
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

    doc.setDrawColor(...lineColor);
    doc.line(L + 6, y + 1, R - 6, y + 1);
    y += 8;

    doc.setFillColor(252, 252, 252);
    doc.roundedRect(L + 6, y - 2, W - 12, 18, 2, 2, 'F');
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(8);
    doc.setTextColor(...mutedColor);
    doc.text('ORDER TOKEN', L + 9, y + 3);
    doc.setFont('courier', 'bold');
    doc.setFontSize(12);
    doc.setTextColor(...textColor);
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
    doc.setTextColor(...mutedColor);
    doc.text('Scan the QR code on your receipt page to track this order.', L + 7, y);
    y += 8;

    doc.setFillColor(246, 246, 246);
    doc.rect(L + 6, y - 4, W - 12, 8, 'F');
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(8);
    doc.setTextColor(...mutedColor);
    doc.text('ITEM', L + 9, y + 1);
    doc.text('AMOUNT', R - 9, y + 1, { align: 'right' });
    y += 8;

    doc.setFont('helvetica', 'normal');
    doc.setFontSize(10);
    doc.setTextColor(...textColor);
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
    doc.setTextColor(...textColor);
    doc.text('Total', L + 9, y);
    doc.setTextColor(...accentColor);
    doc.text('$<?= number_format($total, 2) ?>', R - 9, y, { align: 'right' });
    y += 14;

    doc.setFont('helvetica', 'normal');
    doc.setFontSize(8);
    doc.setTextColor(...mutedColor);
    doc.text('Thank you for dining with us! • Restrodesk', L + W / 2, y, { align: 'center' });

    doc.save('order-<?= $orderToken ?>-receipt.pdf');
    Toast.success('Receipt downloaded!');
}
</script>
</body>
</html>