<?php
/**
 * index.php — Public Menu Page (v2 — design aligned with real-estate-listings)
 * Customer scans QR → lands here with ?token=XXX or ?table=X
 * Menu loads with table pre-tagged for order submission
 *
 * FIX: removed PHP call to renderCartHtml() (PHP0417).
 *      Cart sidebar is now rendered entirely by JavaScript.
 */
require_once __DIR__ . '/core/bootstrap.php';

$db = Database::getInstance();

// Resolve table from token or id
$tableId   = null;
$tableName = 'Walk-in';

if (!empty($_GET['token'])) {
    $t = $db->fetchOne('SELECT id, name FROM `tables` WHERE qr_token = ?', [$_GET['token']]);
    if ($t) { $tableId = (int)$t['id']; $tableName = $t['name']; }
} elseif (!empty($_GET['table'])) {
    $t = $db->fetchOne('SELECT id, name FROM `tables` WHERE id = ?', [(int)$_GET['table']]);
    if ($t) { $tableId = (int)$t['id']; $tableName = $t['name']; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu — Restrodesk</title>
    <meta name="description" content="Browse our menu and order directly to your table.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/devcore-suite/core/ui/devcore.css">
    <link rel="stylesheet" href="/devcore-suite/core/ui/parts/_icons.css">
    <style>
        /* ── Accent override — warm gold matching real-estate ── */
        :root { --dc-accent:#e8a838; --dc-accent-2:#f0c060; --dc-accent-glow:rgba(232,168,56,0.2); }

        body {
            background:
                radial-gradient(900px 420px at 12% -10%, rgba(232,168,56,0.14), transparent 60%),
                radial-gradient(700px 380px at 92% 6%, rgba(255,255,255,0.06), transparent 58%),
                var(--dc-bg);
        }

        /* ── Layout ─────────────────────────────────────────── */
        .menu-layout     { display:grid; grid-template-columns:minmax(0,1fr) 340px; gap:28px; max-width:1240px; margin:0 auto; padding:24px 20px 60px; }
        .menu-main       { min-width:0; }
        .cart-sidebar    { position:sticky; top:80px; height:fit-content; }

        /* ── Hero — glass card (mirrors real-estate hero) ───── */
        .hero {
            background: var(--dc-bg-glass);
            border: 1px solid var(--dc-border);
            border-radius: var(--dc-radius-lg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            padding: 36px 28px;
            margin-bottom: 32px;
            display:grid;
            grid-template-columns:minmax(0,1fr) 260px;
            gap:18px;
            align-items:stretch;
        }
        .hero-copy { text-align:left; }
        .hero-badge {
            display:inline-flex; align-items:center; gap:8px;
            background:var(--dc-accent-glow); border:1px solid rgba(232,168,56,0.3);
            border-radius:var(--dc-radius-full);
            padding:6px 16px; font-size:0.8rem; font-weight:600;
            color:var(--dc-accent-2); margin-bottom:18px;
        }
        .hero h1 { font-family:var(--dc-font-display); font-size:2.1rem; font-weight:800; letter-spacing:-0.02em; margin:0 0 10px; line-height:1.1; }
        .hero p  { color:var(--dc-text-2); font-size:0.95rem; max-width:520px; margin:0 0 18px; }
        .hero-stats { display:flex; gap:10px; flex-wrap:wrap; }
        .hero-stat {
            background:rgba(255,255,255,0.04);
            border:1px solid var(--dc-border);
            border-radius:10px;
            padding:8px 12px;
            display:flex;
            align-items:center;
            gap:8px;
            font-size:0.82rem;
            color:var(--dc-text-2);
        }
        .hero-highlight {
            background:linear-gradient(165deg, rgba(232,168,56,0.16), rgba(255,255,255,0.03));
            border:1px solid rgba(232,168,56,0.35);
            border-radius:14px;
            padding:16px;
            display:flex;
            flex-direction:column;
            justify-content:center;
            gap:8px;
        }
        .hero-highlight h3 { margin:0; font-size:0.95rem; font-weight:700; }
        .hero-highlight p { margin:0; font-size:0.82rem; color:var(--dc-text-2); }

        .table-badge {
            display:inline-flex; align-items:center; gap:6px;
            background:var(--dc-accent-glow); color:var(--dc-accent-2);
            border:1px solid rgba(232,168,56,0.3);
            border-radius:var(--dc-radius-full); padding:5px 14px;
            font-size:0.825rem; font-weight:600; margin-bottom:14px;
        }

        /* ── Category nav ────────────────────────────────────── */
        .cat-wrap {
            background:var(--dc-bg-glass);
            border:1px solid var(--dc-border);
            border-radius:12px;
            padding:12px;
            margin-bottom:22px;
            position:sticky;
            top:76px;
            z-index:3;
            backdrop-filter:blur(10px);
            -webkit-backdrop-filter:blur(10px);
        }
        .cat-wrap-title { font-size:0.75rem; color:var(--dc-text-3); font-weight:600; margin:0 0 8px 2px; text-transform:uppercase; letter-spacing:0.08em; }
        .cat-nav  { display:flex; gap:8px; flex-wrap:wrap; }
        .cat-pill {
            padding:7px 18px; border-radius:var(--dc-radius-full);
            font-size:0.85rem; font-weight:600; cursor:pointer;
            border:1px solid var(--dc-border); background:var(--dc-bg-2);
            color:var(--dc-text-2); transition:all var(--dc-t-fast); white-space:nowrap;
        }
        .cat-pill.active, .cat-pill:hover {
            background:var(--dc-accent-glow); color:var(--dc-accent-2);
            border-color:rgba(232,168,56,0.3);
        }
        .cat-pill.active { cursor:default; }

        /* ── Category section ────────────────────────────────── */
        .cat-section { margin-bottom:40px; }
        .cat-title   { font-family:var(--dc-font-display); font-size:1.15rem; font-weight:700; margin-bottom:16px; padding-bottom:10px; border-bottom:1px solid var(--dc-border); }
        .menu-intro {
            display:flex;
            align-items:flex-end;
            justify-content:space-between;
            gap:12px;
            margin:8px 0 18px;
        }
        .menu-intro h2 { margin:0; font-family:var(--dc-font-display); font-size:1.35rem; }
        .menu-intro p { margin:4px 0 0; color:var(--dc-text-3); font-size:0.85rem; }

        /* ── Menu item cards (mirrors prop-card from real-estate) */
        .items-grid  { display:grid; grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); gap:16px; }
        .item-card   {
            display:flex; flex-direction:column; overflow:hidden; cursor:pointer;
            background:var(--dc-bg-glass); border:1px solid var(--dc-border);
            border-radius:var(--dc-radius-lg); backdrop-filter:blur(12px);
            transition:border-color var(--dc-t-med), box-shadow var(--dc-t-med), transform var(--dc-t-med);
        }
        .item-card:hover { transform:translateY(-3px); box-shadow:var(--dc-shadow); border-color:var(--dc-border-2); }
        .item-card.unavailable { opacity:0.4; pointer-events:none; }
        .item-img-wrap { overflow:hidden; flex-shrink:0; }
        .item-img    { width:100%; height:160px; object-fit:cover; background:var(--dc-bg-3); display:block; transition:transform 0.5s var(--dc-ease); }
        .item-card:hover .item-img { transform:scale(1.04); }
        .item-img-placeholder { width:100%; height:160px; background:var(--dc-bg-3); display:flex; align-items:center; justify-content:center; }
        .item-img-placeholder .dc-icon { width:40px; height:40px; color:var(--dc-text-3); opacity:0.3; }
        .item-body   { padding:14px; flex:1; display:flex; flex-direction:column; gap:6px; }
        .item-name   { font-weight:700; font-size:0.95rem; }
        .item-desc   { font-size:0.8rem; color:var(--dc-text-3); line-height:1.5; flex:1; }
        .item-footer { display:flex; align-items:center; justify-content:space-between; margin-top:8px; }
        .item-price  { font-family:var(--dc-font-display); font-weight:800; font-size:1.1rem; color:var(--dc-accent-2); letter-spacing:-0.01em; }
        .add-btn     {
            width:32px; height:32px; border-radius:50%;
            background:var(--dc-accent); color:#000; border:none;
            font-size:1.2rem; font-weight:700; cursor:pointer;
            display:flex; align-items:center; justify-content:center;
            transition:all var(--dc-t-fast);
        }
        .add-btn:hover { background:var(--dc-accent-2); transform:scale(1.1); }

        /* ── Cart sidebar ─────────────────────────────────────── */
        .cart-header  { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; }
        .cart-title   { font-family:var(--dc-font-display); font-weight:700; font-size:1rem; display:flex; align-items:center; }
        .cart-empty   { text-align:center; padding:32px 16px; color:var(--dc-text-3); font-size:0.875rem; }
        .cart-items   { display:flex; flex-direction:column; gap:10px; max-height:320px; overflow-y:auto; margin-bottom:16px; }
        .cart-row     { display:flex; align-items:center; gap:10px; }
        .cart-row-name{ flex:1; font-size:0.875rem; font-weight:600; }
        .cart-row-price{ font-size:0.875rem; color:var(--dc-text-2); white-space:nowrap; }
        .qty-ctrl     { display:flex; align-items:center; gap:6px; }
        .qty-btn      { width:26px; height:26px; border-radius:6px; border:1px solid var(--dc-border); background:var(--dc-bg-3); color:var(--dc-text); font-size:0.9rem; cursor:pointer; display:flex; align-items:center; justify-content:center; }
        .qty-btn:hover{ background:var(--dc-bg-glass); }
        .qty-num      { font-size:0.875rem; font-weight:700; min-width:16px; text-align:center; }
        .cart-divider { border:none; border-top:1px solid var(--dc-border); margin:12px 0; }
        .cart-total-row { display:flex; justify-content:space-between; font-size:1rem; font-weight:800; margin-bottom:16px; }
        .cart-total-amt { font-family:var(--dc-font-display); color:var(--dc-accent-2); }
        .cart-note    { margin-bottom:14px; }
        .cart-note label { display:block; font-size:0.8rem; font-weight:600; color:var(--dc-text-2); margin-bottom:5px; }
        .btn-checkout { background:var(--dc-accent)!important; border-color:var(--dc-accent)!important; color:#000!important; font-weight:700!important; }
        .btn-checkout:hover { background:var(--dc-accent-2)!important; border-color:var(--dc-accent-2)!important; }

        #desktopCartCard {
            background:linear-gradient(180deg, rgba(255,255,255,0.06), rgba(255,255,255,0.03));
            border:1px solid var(--dc-border-2);
        }

        /* ── Mobile FAB ──────────────────────────────────────── */
        .cart-fab       { display:none; position:fixed; bottom:20px; right:20px; z-index:100; }
        .cart-fab button { width:56px; height:56px; border-radius:50%; background:var(--dc-accent); color:#000; border:none; font-size:1.4rem; cursor:pointer; box-shadow:0 4px 20px var(--dc-accent-glow); position:relative; }
        .cart-fab-count { position:absolute; top:-4px; right:-4px; background:var(--dc-danger); color:#fff; width:20px; height:20px; border-radius:50%; font-size:0.7rem; font-weight:700; display:flex; align-items:center; justify-content:center; }

        @media (max-width:900px) {
            .menu-layout  { grid-template-columns:1fr; }
            .cart-sidebar { display:none; }
            .cart-fab     { display:block; }
            .hero { grid-template-columns:1fr; padding:30px 20px; }
            .hero-copy { text-align:center; }
            .hero p { margin:0 auto 16px; }
            .hero-stats { justify-content:center; }
            .cat-wrap { position:static; }
            .menu-intro { flex-direction:column; align-items:flex-start; }
        }
    </style>
</head>
<body>

<!-- Nav -->
<nav class="dc-nav">
    <div class="dc-nav__brand">
        <i class="dc-icon dc-icon-utensils dc-icon-sm"></i> Restrodesk
    </div>
    <div class="dc-nav__links">
        <div id="tableNavBadge"></div>
        <a href="admin/login.php" class="dc-nav__link">
            Admin <i class="dc-icon dc-icon-arrow-right dc-icon-sm" style="margin-left:2px"></i>
        </a>
    </div>
</nav>

<!-- Main layout -->
<div class="menu-layout">

    <!-- LEFT: Menu -->
    <div class="menu-main">
        <div class="hero">
            <div class="hero-copy">
                <div id="tableHeroBadge"></div>
                <div class="hero-badge"><i class="dc-icon dc-icon-fire dc-icon-sm"></i> Live Kitchen Flow</div>
                <h1>Order Your <span style="color:var(--dc-accent)">Perfect</span> Meal</h1>
                <p>Browse chef picks, add items to your cart, and place your order in seconds with table-aware service.</p>
                <div class="hero-stats">
                    <div class="hero-stat"><i class="dc-icon dc-icon-clock dc-icon-sm"></i> Freshly prepared</div>
                    <div class="hero-stat"><i class="dc-icon dc-icon-lock dc-icon-sm"></i> Secure checkout</div>
                    <div class="hero-stat"><i class="dc-icon dc-icon-receipt dc-icon-sm"></i> Live order tracking</div>
                </div>
            </div>
            <div class="hero-highlight">
                <h3>Chef Recommendations</h3>
                <p>Start with starters, follow with house favorites, then end with desserts for a complete table experience.</p>
                <div class="dc-text-dim" style="font-size:0.78rem;">Tip: You can change table before checkout.</div>
            </div>
        </div>

        <!-- Category Pills -->
        <div class="cat-wrap">
            <div class="cat-wrap-title">Jump To Category</div>
            <div class="cat-nav" id="catNav"></div>
        </div>

        <div class="menu-intro">
            <div>
                <h2>Today's Menu</h2>
                <p>Tap any card to add items instantly.</p>
            </div>
        </div>

        <!-- Menu sections (rendered by JS) -->
        <div id="menuSections">
            <div class="dc-grid dc-grid-3">
                <?php for ($i = 0; $i < 6; $i++): ?>
                <div class="item-card" style="pointer-events:none">
                    <div class="dc-skeleton" style="height:160px;border-radius:0"></div>
                    <div style="padding:14px">
                        <div class="dc-skeleton" style="height:18px;margin-bottom:8px;border-radius:4px"></div>
                        <div class="dc-skeleton" style="height:14px;width:55%;border-radius:4px"></div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <!-- RIGHT: Cart (desktop) — populated entirely by JS, no PHP function needed -->
    <div class="cart-sidebar">
        <div class="dc-card" id="desktopCartCard">
            <div class="cart-header">
                <div class="cart-title">
                    <i class="dc-icon dc-icon-shopping-cart dc-icon-sm" style="margin-right:6px"></i>Your Order
                </div>
                <?php if ($tableId): ?>
                <span class="dc-badge" style="background:var(--dc-accent-glow);color:var(--dc-accent-2);border:1px solid rgba(232,168,56,0.3)">
                    <?= htmlspecialchars($tableName) ?>
                </span>
                <?php endif; ?>
            </div>
            <div id="desktopCartInner">
                <div class="cart-empty">
                    <i class="dc-icon dc-icon-shopping-cart" style="opacity:0.3;display:block;margin-bottom:8px"></i>
                    Your cart is empty.<br>Tap any item to add it.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mobile FAB -->
<div class="cart-fab">
    <button onclick="Modal.open('modalCart')" aria-label="Open cart">
        <i class="dc-icon dc-icon-shopping-cart"></i>
        <span class="cart-fab-count" id="fabCount">0</span>
    </button>
</div>

<!-- Mobile Cart Modal -->
<div class="dc-modal-overlay" id="modalCart">
    <div class="dc-modal" style="max-width:480px;max-height:90vh;overflow-y:auto;">
        <div class="dc-modal__header">
            <h3 style="font-weight:700;font-family:var(--dc-font-display)">Your Cart</h3>
            <button class="dc-modal__close" data-modal-close="modalCart">
                <i class="dc-icon dc-icon-x"></i>
            </button>
        </div>
        <div id="mobileCartBody"></div>
    </div>
</div>

<!-- Table Selection Wizard Modal -->
<div class="dc-modal-overlay" id="tableWizardModal">
    <div class="dc-modal" style="max-width:400px;max-height:90vh;overflow-y:auto;">
        <div class="dc-modal__header">
            <h3 style="font-weight:700;font-family:var(--dc-font-display)">Select Your Table</h3>
        </div>
        <div id="tableWizardBody" style="padding:18px 0 8px 0;text-align:center;"></div>
    </div>
</div>

<footer style="border-top:1px solid var(--dc-border);padding:24px 0;text-align:center">
    <div class="dc-caption dc-text-dim">
        Restrodesk &middot; Part of the <strong>DevCore Portfolio Suite</strong>
    </div>
</footer>

<script src="/devcore-suite/core/ui/devcore.js"></script>
<script src="/devcore-suite/core/utils/helpers.js"></script>
<script>
function renderTableBadges() {
    // Nav badge
    const nav = document.getElementById('tableNavBadge');
    if (nav) {
        nav.innerHTML = `<div class="table-badge" style="margin:0;display:inline-flex;align-items:center;gap:8px;">
            <i class='dc-icon dc-icon-chair dc-icon-sm'></i>
            ${TABLE_ID && TABLE_ID !== 0 && TABLE_NAME !== 'Walk-in' ?
                `${TABLE_NAME} <button id='changeTableBtn' class='dc-btn dc-btn-xs dc-btn-ghost' style='margin-left:8px;padding:2px 10px;font-size:0.8em;' onclick='showTableWizard(true)'><i class='dc-icon dc-icon-edit dc-icon-xs'></i> Change</button>` :
                `No table selected <button id='selectTableBtn' class='dc-btn dc-btn-xs dc-btn-accent' style='margin-left:8px;padding:2px 10px;font-size:0.8em;' onclick='showTableWizard(true)'><i class='dc-icon dc-icon-plus dc-icon-xs'></i> Select Table</button>`
            }
        </div>`;
    }
    // Hero badge
    const hero = document.getElementById('tableHeroBadge');
    if (hero) {
        hero.innerHTML = `<div class="table-badge" style="display:inline-flex;align-items:center;gap:8px;">
            <i class='dc-icon dc-icon-chair dc-icon-sm'></i>
            ${TABLE_ID && TABLE_ID !== 0 && TABLE_NAME !== 'Walk-in' ?
                `${TABLE_NAME} <button id='changeTableBtnHero' class='dc-btn dc-btn-xs dc-btn-ghost' style='margin-left:8px;padding:2px 10px;font-size:0.8em;' onclick='showTableWizard(true)'><i class='dc-icon dc-icon-edit dc-icon-xs'></i> Change</button>` :
                `No table selected <button id='selectTableBtnHero' class='dc-btn dc-btn-xs dc-btn-accent' style='margin-left:8px;padding:2px 10px;font-size:0.8em;' onclick='showTableWizard(true)'><i class='dc-icon dc-icon-plus dc-icon-xs'></i> Select Table</button>`
            }
        </div>`;
    }
}

let TABLE_ID   = <?= json_encode($tableId) ?>;
let TABLE_NAME = <?= json_encode($tableName) ?>;

console.log('PHP values - TABLE_ID:', TABLE_ID, 'TABLE_NAME:', TABLE_NAME);

// If no table from PHP, try to get from sessionStorage (backup method)
if ((!TABLE_ID || TABLE_ID === 0 || TABLE_NAME === 'Walk-in')) {
    const cartDataStr = sessionStorage.getItem('rqo_cart');
    console.log('sessionStorage rqo_cart:', cartDataStr);
    
    if (cartDataStr) {
        try {
            const cartData = JSON.parse(cartDataStr);
            console.log('Parsed cartData:', cartData);
            if (cartData.table_id && cartData.table_name && cartData.table_name !== 'Walk-in') {
                TABLE_ID = cartData.table_id;
                TABLE_NAME = cartData.table_name;
                console.log('Restored from sessionStorage - TABLE_ID:', TABLE_ID, 'TABLE_NAME:', TABLE_NAME);
            }
        } catch (e) {
            console.error('Error parsing sessionStorage:', e);
        }
    }
}

console.log('Final after init - TABLE_ID:', TABLE_ID, 'TABLE_NAME:', TABLE_NAME);

let menuData     = [];
let cart         = {}; // { itemId: { name, price, qty } }
let tablePoller  = null;

// ── Load menu ──────────────────────────────────────────────
async function loadMenu() {
    try {
        const params = TABLE_ID && TABLE_ID !== 0 ? `?table=${TABLE_ID}` : '';
        console.log('Loading menu with TABLE_ID:', TABLE_ID, 'params:', params);
        const res    = await DC.get(`api/menu.php${params}`);
        console.log('Menu response:', res);
        if (res && res.data && res.data.categories) {
            menuData = res.data.categories;
            renderCatNav();
            renderMenu();
        } else {
            throw new Error('Invalid menu response structure');
        }
    } catch (e) {
        console.error('Menu load error:', e);
        document.getElementById('menuSections').innerHTML =
            '<div style="text-align:center;padding:40px;color:var(--dc-danger);">Failed to load menu. Please refresh.</div>';
    }
}

function renderCatNav() {
    const nav = document.getElementById('catNav');
    nav.innerHTML = menuData.map((c,i) =>
        `<button class="cat-pill${i===0?' active':''}" data-id="${c.id}"
                 onclick="scrollToCat(${c.id}, this)">${c.name}</button>`
    ).join('');
}

function renderMenu() {
    const container = document.getElementById('menuSections');
    if (!menuData.length) {
        container.innerHTML = '<div class="dc-text-center dc-text-dim" style="padding:40px;">No menu items available.</div>';
        return;
    }
    container.innerHTML = menuData.map(cat => `
        <div class="cat-section" id="cat-${cat.id}">
            <div class="cat-title">${cat.name}</div>
            <div class="items-grid">
                ${cat.items.map(item => itemCard(item)).join('')}
            </div>
        </div>
    `).join('');
}

function escStr(s) { return String(s || '').replace(/'/g, "\\'").replace(/"/g, '&quot;'); }
function escHtml(s) {
    if (window.DCHelpers && typeof window.DCHelpers.escHtml === 'function') {
        return window.DCHelpers.escHtml(s);
    }
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function renderTableBadges() {
    // Nav badge
    const nav = document.getElementById('tableNavBadge');
    if (nav) {
        nav.innerHTML = `<div class="table-badge" style="margin:0;display:inline-flex;align-items:center;gap:8px;">
            <i class='dc-icon dc-icon-chair dc-icon-sm'></i>
            ${TABLE_ID && TABLE_ID !== 0 && TABLE_NAME !== 'Walk-in' ?
                `${TABLE_NAME} <button id='changeTableBtn' class='dc-btn dc-btn-xs dc-btn-ghost' style='margin-left:8px;padding:2px 10px;font-size:0.8em;' onclick='showTableWizard(true)'><i class='dc-icon dc-icon-edit dc-icon-xs'></i> Change</button>` :
                `No table selected <button id='selectTableBtn' class='dc-btn dc-btn-xs dc-btn-accent' style='margin-left:8px;padding:2px 10px;font-size:0.8em;' onclick='showTableWizard(true)'><i class='dc-icon dc-icon-plus dc-icon-xs'></i> Select Table</button>`
            }
        </div>`;
    }
    // Hero badge
    const hero = document.getElementById('tableHeroBadge');
    if (hero) {
        hero.innerHTML = `<div class="table-badge" style="display:inline-flex;align-items:center;gap:8px;">
            <i class='dc-icon dc-icon-chair dc-icon-sm'></i>
            ${TABLE_ID && TABLE_ID !== 0 && TABLE_NAME !== 'Walk-in' ?
                `${TABLE_NAME} <button id='changeTableBtnHero' class='dc-btn dc-btn-xs dc-btn-ghost' style='margin-left:8px;padding:2px 10px;font-size:0.8em;' onclick='showTableWizard(true)'><i class='dc-icon dc-icon-edit dc-icon-xs'></i> Change</button>` :
                `No table selected <button id='selectTableBtnHero' class='dc-btn dc-btn-xs dc-btn-accent' style='margin-left:8px;padding:2px 10px;font-size:0.8em;' onclick='showTableWizard(true)'><i class='dc-icon dc-icon-plus dc-icon-xs'></i> Select Table</button>`
            }
        </div>`;
    }
}

TABLE_ID   = <?php echo json_encode($tableId ?? null); ?>;
TABLE_NAME = <?php echo json_encode($tableName ?? 'Walk-in'); ?>;

// Re-render badges after TABLE_ID/TABLE_NAME are initialized
renderTableBadges();
window.renderTableBadges = renderTableBadges;

function itemCard(item) {
    const imgHtml = item.image_url
        ? `<div class="item-img-wrap"><img class="item-img" src="${escStr(item.image_url)}" alt="${escStr(item.name)}" loading="lazy"></div>`
        : `<div class="item-img-placeholder"><i class="dc-icon dc-icon-utensils dc-icon-2xl"></i></div>`;
    return `
        <div class="item-card${item.available === false ? ' unavailable' : ''}"
             onclick="addToCart(${item.id}, '${escStr(item.name)}', ${item.price})">
            ${imgHtml}
            <div class="item-body">
                <div class="item-name">${escHtml(item.name)}</div>
                <div class="item-desc">${escHtml(item.description || '')}</div>
                <div class="item-footer">
                    <div class="item-price">$${parseFloat(item.price).toFixed(2)}</div>
                    <button class="add-btn" title="Add to cart"
                            onclick="event.stopPropagation();addToCart(${item.id},'${escStr(item.name)}',${item.price})">+</button>
                </div>
            </div>
        </div>`;
}

function scrollToCat(id, btn) {
    document.querySelectorAll('.cat-pill').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    const el = document.getElementById('cat-' + id);
    if (el) el.scrollIntoView({ behavior:'smooth', block:'start' });
}

// ── Cart logic ─────────────────────────────────────────────
function addToCart(id, name, price) {
    if (cart[id]) {
        cart[id].qty++;
    } else {
        cart[id] = { name, price: parseFloat(price), qty: 1 };
    }
    renderCart();
    Toast.success(`${name} added to cart`);
}

function changeQty(id, delta) {
    if (!cart[id]) return;
    cart[id].qty += delta;
    if (cart[id].qty <= 0) delete cart[id];
    renderCart();
}

function cartTotal() {
    return Object.values(cart).reduce((s, i) => s + i.price * i.qty, 0);
}

function cartCount() {
    return Object.values(cart).reduce((s, i) => s + i.qty, 0);
}

function renderCart() {
    const count = cartCount();
    document.getElementById('fabCount').textContent = count;
    renderCartInto('desktopCartInner');
    renderCartInto('mobileCartBody');
}

function renderCartInto(containerId) {
    const el = document.getElementById(containerId);
    if (!el) return;
    const items = Object.entries(cart);
    const total = cartTotal();

    if (!items.length) {
        el.innerHTML = `<div class="cart-empty">
            <i class="dc-icon dc-icon-shopping-cart" style="opacity:0.3;display:block;margin-bottom:8px"></i>
            Your cart is empty.<br>Tap any item to add it.
        </div>`;
        return;
    }

    const rows = items.map(([id, item]) => `
        <div class="cart-row">
            <div class="cart-row-name">${escHtml(item.name)}</div>
            <div class="dc-flex dc-items-center" style="gap:10px;">
                <div class="qty-ctrl">
                    <button class="qty-btn" onclick="changeQty(${id}, -1)">−</button>
                    <span class="qty-num">${item.qty}</span>
                    <button class="qty-btn" onclick="changeQty(${id}, +1)">+</button>
                </div>
                <div class="cart-row-price">$${(item.price * item.qty).toFixed(2)}</div>
            </div>
        </div>`).join('');

    el.innerHTML = `
        <div class="cart-items">${rows}</div>
        <hr class="cart-divider">
        <div class="cart-total-row">
            <span>Total</span>
            <span class="cart-total-amt">$${total.toFixed(2)}</span>
        </div>
        <div class="cart-note">
            <label for="noteField-${containerId}">Special Instructions (optional)</label>
            <textarea id="noteField-${containerId}" class="dc-textarea" rows="2"
                      placeholder="Allergies, preferences…">${escHtml(currentNote())}</textarea>
        </div>
        <a href="checkout.php?data=${encodeCartParam()}"
           class="dc-btn dc-btn-primary dc-btn-full btn-checkout"
           onclick="storeCartData()">
            Proceed to Checkout <i class="dc-icon dc-icon-arrow-right dc-icon-sm"></i>
        </a>`;
}

function currentNote() {
    return document.getElementById('noteField-desktopCartInner')?.value
        || document.getElementById('noteField-mobileCartBody')?.value
        || '';
}

function encodeCartParam() {
    return encodeURIComponent(JSON.stringify({
        table_id: TABLE_ID,
        items: Object.entries(cart).map(([id, i]) => ({
            menu_item_id: parseInt(id), quantity: i.qty
        })),
        note: currentNote(),
    }));
}

function storeCartData() {
    sessionStorage.setItem('rqo_cart', JSON.stringify({
        table_id:   TABLE_ID,
        table_name: TABLE_NAME,
        items: Object.entries(cart).map(([id, i]) => ({
            menu_item_id: parseInt(id), name: i.name, price: i.price, quantity: i.qty
        })),
        note: currentNote(),
    }));
}

function showTableWizard(force = false) {
    // Only show if not already selected, unless forced
    if (!force && TABLE_ID && TABLE_ID !== 0 && TABLE_NAME !== 'Walk-in') return;
    const modal = document.getElementById('tableWizardModal');
    const body = document.getElementById('tableWizardBody');
    modal.style.display = '';
    modal.classList.add('open');

    function fetchTables() {
        body.innerHTML = '<div class="dc-text-dim" style="padding:24px;">Loading tables...</div>';
        DC.get('api/tables-list.php').then(res => {
            const tables = res.data.tables;
            if (!tables.length) {
                body.innerHTML = '<div style="color:var(--dc-danger);">No tables found.</div>';
                return;
            }
            body.innerHTML = tables.map(t => {
                const isSelected = t.id === TABLE_ID;
                const isOccupied = t.occupied && !isSelected;
                let btnClass = 'dc-btn dc-btn-full';
                let btnStyle = 'margin-bottom:12px;';
                if (isSelected) {
                    btnClass += ' dc-btn-success';
                    btnStyle += 'font-weight:700;box-shadow:0 0 0 2px var(--dc-success);';
                }
                if (isOccupied) {
                    btnClass += ' dc-btn-disabled';
                    btnStyle += 'opacity:0.6;pointer-events:none;';
                }
                return `<button class="${btnClass}" style="${btnStyle}" ${isOccupied ? 'disabled' : ''} onclick="selectTable(${t.id}, '${t.name.replace(/'/g, "&#39;")}')">
                    <i class='dc-icon dc-icon-chair'></i> ${t.name} ${isSelected ? '<span style=\'font-size:0.8em;color:var(--dc-success);margin-left:6px\'>(Selected)</span>' : ''}
                    ${isOccupied && !isSelected ? '<span style=\'font-size:0.8em;color:#b91c1c;margin-left:6px\'>(Occupied)</span>' : ''}
                </button>`;
            }).join('');
        });
    }
    fetchTables();
    if (tablePoller) clearInterval(tablePoller);
    tablePoller = setInterval(fetchTables, 7000); // poll every 7 seconds
}

function selectTable(id, name) {
    console.log('selectTable called with id:', id, 'name:', name);
    
    // If user already has a table AND is changing it, warn them
    if (TABLE_ID && TABLE_ID !== 0 && TABLE_NAME !== 'Walk-in' && id !== TABLE_ID) {
        // Check if cart has items
        const cartHasItems = Object.keys(cart).length > 0;
        if (cartHasItems) {
            // Warn user: changing table will clear cart
            if (!confirm('Changing your table will clear your current cart. Continue?')) {
                return; // User cancelled
            }
            // Clear cart if user confirmed
            cart = {};
            renderCart();
        }
    }
    
    // Update global variables
    TABLE_ID = id;
    TABLE_NAME = name;
    console.log('Updated TABLE_ID:', TABLE_ID, 'TABLE_NAME:', TABLE_NAME);
    
    // Store in sessionStorage for persistence
    const cartData = {
        table_id: id,
        table_name: name,
        items: [],
        note: ''
    };
    sessionStorage.setItem('rqo_cart', JSON.stringify(cartData));
    console.log('Saved to sessionStorage:', cartData);
    console.log('Verify read from sessionStorage:', sessionStorage.getItem('rqo_cart'));
    
    // Update URL with ?table=ID so PHP sees it on next load
    const url = new URL(window.location.href);
    url.searchParams.set('table', id);
    console.log('New URL:', url.toString());
    window.history.replaceState({}, '', url.toString());
    
    // Close wizard modal
    const modal = document.getElementById('tableWizardModal');
    modal.classList.remove('open');
    modal.style.display = 'none';
    
    // Stop polling
    if (tablePoller) {
        clearInterval(tablePoller);
        tablePoller = null;
    }
    
    // Update UI badges
    renderTableBadges();
    
    // Reload page after a delay to ensure storage is written
    console.log('Reloading page in 200ms...');
    setTimeout(() => {
        console.log('Page reloading now');
        location.reload();
    }, 200);
}

// Auto-open table wizard - but ONLY if no table is selected
// Render badges FIRST to show current state
renderTableBadges();
window.renderTableBadges = renderTableBadges;

// Check if table is truly NOT selected (only then show wizard)
const hasTableSelected = TABLE_ID && TABLE_ID !== 0 && TABLE_NAME && TABLE_NAME !== 'Walk-in';
console.log('hasTableSelected:', hasTableSelected, 'TABLE_ID:', TABLE_ID, 'TABLE_NAME:', TABLE_NAME);

if (!hasTableSelected) {
    console.log('→ No table selected - showing wizard');
    showTableWizard(false);
} else {
    console.log('→ Table already selected [ ' + TABLE_NAME + ' ] - NOT showing wizard');
}

loadMenu();
renderCart();
</script>
</body>
</html>
