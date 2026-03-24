<?php
require_once dirname(__DIR__) . '/core/bootstrap.php';
Auth::requireRole('admin', 'login.php');

$db     = Database::getInstance();
$tables = $db->fetchAll('SELECT id, name, qr_token FROM `tables` ORDER BY id');

// Build the base URL dynamically
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
         . '://' . $_SERVER['HTTP_HOST'];
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/qr-generator.php'));
$appBase = rtrim(dirname($scriptDir), '/');
$menuBase = $baseUrl . $appBase . '/index.php?token=';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Generator — Restrodesk</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../core/ui/devcore.css">
    <link rel="stylesheet" href="../../../core/ui/parts/_icons.css">
    <style>:root{--dc-accent:#e8a838;--dc-accent-2:#f0c060;--dc-accent-glow:rgba(232,168,56,0.2);}</style>
    <style>
        .page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:28px; flex-wrap:wrap; gap:12px; }
        .page-title  { font-size:1.5rem; font-weight:700; }
        .qr-grid     { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:20px; }
        .qr-item     { text-align:center; padding:24px 20px; }
        .qr-item img { border-radius:12px; max-width:180px; height:180px; border:2px solid var(--dc-border); }
        .qr-table-name { font-weight:700; font-size:1rem; margin-top:12px; margin-bottom:4px; }
        .qr-url      { font-size:0.7rem; color:var(--dc-text-3); word-break:break-all; margin-bottom:12px; }
        .qr-actions  { display:flex; gap:8px; justify-content:center; }
    </style>
</head>
<body>

<aside class="dc-sidebar">
    <div class="dc-sidebar__logo"><i class="dc-icon dc-icon-utensils"></i> <span>Restrodesk</span></div>
    <div class="dc-sidebar__section">Management</div>
    <a href="dashboard.php"    class="dc-sidebar__link"><i class="dc-icon dc-icon-bar-chart"></i> Dashboard</a>
    <a href="orders.php"       class="dc-sidebar__link"><i class="dc-icon dc-icon-receipt"></i> Orders</a>
    <a href="menu.php"         class="dc-sidebar__link"><i class="dc-icon dc-icon-clipboard"></i> Menu Items</a>
    <a href="qr-generator.php" class="dc-sidebar__link active"><i class="dc-icon dc-icon-qr-code"></i> QR Codes</a>
    <div class="dc-sidebar__section" style="margin-top:auto">Account</div>
    <a href="logout.php"       class="dc-sidebar__link"><i class="dc-icon dc-icon-log-out"></i> Logout</a>
</aside>

<div class="dc-with-sidebar">
    <div class="dc-container" style="padding-top:32px; padding-bottom:48px;">

        <div class="page-header">
            <div>
                <div class="page-title">QR Code Generator</div>
                <div style="color:var(--dc-text-3);font-size:0.875rem">
                    Print and place these QR codes on each table
                </div>
            </div>
            <button class="dc-btn dc-btn-primary" onclick="printAll()"><i class="dc-icon dc-icon-printer"></i> Print All QR Codes</button>
        </div>

        <div class="dc-card" style="margin-bottom:24px; padding:16px 20px; display:flex; align-items:center; gap:12px;">
            <span style="font-size:1.25rem;"><i class="dc-icon dc-icon-info"></i></span>
            <div>
                <div style="font-weight:600; font-size:0.9rem;">How it works</div>
                <div style="font-size:0.825rem; color:var(--dc-text-3);">
                    Each QR code encodes a unique table URL. When a customer scans it, they land on the menu
                    with their table pre-selected. Their order is automatically tagged to the correct table.
                </div>
            </div>
        </div>

        <div class="qr-grid">
            <?php foreach ($tables as $table):
                $url = $menuBase . urlencode($table['qr_token']);
                $qrSrc = QrCode::url($url, 200);
            ?>
            <div class="dc-card qr-item" id="qr-<?= $table['id'] ?>">
                <img src="<?= htmlspecialchars($qrSrc) ?>"
                     alt="QR for <?= htmlspecialchars($table['name']) ?>"
                     loading="lazy">
                <div class="qr-table-name"><?= htmlspecialchars($table['name']) ?></div>
                <div class="qr-url"><?= htmlspecialchars($url) ?></div>
                <div class="qr-actions">
                    <a href="<?= htmlspecialchars($url) ?>" target="_blank"
                       class="dc-btn dc-btn-ghost dc-btn-sm">Preview</a>
                    <button class="dc-btn dc-btn-sm dc-btn-primary"
                            onclick="downloadQR(<?= $table['id'] ?>, '<?= htmlspecialchars(addslashes($table['name'])) ?>', '<?= htmlspecialchars($qrSrc) ?>')">
                        ↓ Download
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    </div>
</div>

<script src="../../../core/ui/devcore.js"></script>
<script>
function printAll() {
    const codes = [
        <?php foreach ($tables as $table):
            $url    = $menuBase . urlencode($table['qr_token']);
            $qrSrc  = QrCode::url($url, 250);
        ?>
        { data: <?= json_encode($qrSrc) ?>, label: <?= json_encode($table['name']) ?> },
        <?php endforeach; ?>
    ];

    const html = `<!DOCTYPE html><html><head>
    <style>
        body { font-family:sans-serif; display:flex; flex-wrap:wrap; gap:24px; padding:24px; }
        .qr-print-item { text-align:center; border:1px solid #ddd; padding:20px; border-radius:12px; }
        .qr-print-item img { display:block; margin:0 auto; }
        .qr-print-item p { margin:10px 0 0; font-weight:700; font-size:14px; }
        @media print { body { gap:12px; padding:12px; } }
    </style>
    </head><body>
    ${codes.map(c => `<div class="qr-print-item"><img src="${c.data}" width="220" height="220"><p>${c.label}</p></div>`).join('')}
    </body></html>`;

    const w = window.open('', '_blank');
    w.document.write(html);
    w.document.close();
    w.focus();
    setTimeout(() => w.print(), 600);
}

function downloadQR(id, name, src) {
    const a = document.createElement('a');
    a.href = src;
    a.download = `qr-${name.replace(/\s+/g, '-').toLowerCase()}.png`;
    a.target = '_blank';
    a.click();
    Toast.success(`Downloading QR for ${name}`);
}
</script>
</body>
</html>
