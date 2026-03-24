<?php
/**
 * checkout.php — Customer confirms and places order
 * Cart data comes from sessionStorage (JS) via a hidden form POST
 * or from the ?data= URL param (fallback for direct links)
 */
require_once __DIR__ . '/core/bootstrap.php';

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/checkout.php'));
$assetBase = rtrim($scriptDir, '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout — Restrodesk</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars($assetBase . '/core/ui/devcore.css', ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($assetBase . '/core/ui/parts/_icons.css', ENT_QUOTES, 'UTF-8') ?>">
    <style>
        :root { --dc-accent:#e8a838; --dc-accent-2:#f0c060; --dc-accent-glow:rgba(232,168,56,0.2); }

        .checkout-wrap  { max-width:580px; margin:40px auto; padding:0 20px 60px; }
        .section-title  { font-family:var(--dc-font-display); font-size:1rem; font-weight:700; margin-bottom:14px; padding-bottom:8px; border-bottom:1px solid var(--dc-border); }
        .summary-row    { display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--dc-border); }
        .summary-row:last-of-type { border-bottom:none; }
        .summary-name   { font-size:0.9rem; font-weight:600; }
        .summary-qty    { font-size:0.8rem; color:var(--dc-text-3); }
        .summary-price  { font-weight:700; font-size:0.9rem; color:var(--dc-text-2); }
        .total-row      { display:flex; justify-content:space-between; font-size:1.1rem; font-weight:800; padding-top:16px; }
        .total-amt      { font-family:var(--dc-font-display); color:var(--dc-accent-2); }
        .empty-notice   { text-align:center; padding:48px 16px; color:var(--dc-text-3); }
        .empty-notice a { color:var(--dc-accent-2); }
        .step-bar       { display:flex; align-items:center; gap:0; margin-bottom:32px; }
        .step           { display:flex; align-items:center; gap:6px; font-size:0.8rem; font-weight:600; color:var(--dc-text-3); }
        .step.active    { color:var(--dc-accent-2); }
        .step.done      { color:var(--dc-success); }
        .step-sep       { flex:1; height:2px; background:var(--dc-border); margin:0 8px; }
        .step-sep.done  { background:var(--dc-success); }
        .step-dot       { width:28px; height:28px; border-radius:50%; border:2px solid currentColor; display:flex; align-items:center; justify-content:center; font-size:0.75rem; flex-shrink:0; }
        .btn-primary-gold {
            background:var(--dc-accent)!important; border-color:var(--dc-accent)!important;
            color:#000!important; font-weight:700!important;
        }
        .btn-primary-gold:hover { background:var(--dc-accent-2)!important; border-color:var(--dc-accent-2)!important; }
        .required-mark { color:var(--dc-danger); margin-left:4px; }
        .customer-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }

        @media (max-width:640px) {
            .customer-grid { grid-template-columns:1fr; }
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
        <span class="dc-text-dim" style="font-size:0.875rem;">Checkout</span>
    </div>
</nav>

<div class="checkout-wrap">

    <!-- Step progress bar -->
    <div class="step-bar">
        <div class="step done"><div class="step-dot"><i class="dc-icon dc-icon-check"></i></div>Menu</div>
        <div class="step-sep done"></div>
        <div class="step active"><div class="step-dot">2</div>Review</div>
        <div class="step-sep"></div>
        <div class="step"><div class="step-dot">3</div>Confirmed</div>
    </div>

    <div id="checkoutContent">
        <!-- JS renders content here -->
        <div class="dc-text-center dc-text-dim" style="padding:40px;">Loading your order...</div>
    </div>
</div>

<script src="<?= htmlspecialchars($assetBase . '/core/ui/devcore.js', ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars($assetBase . '/core/utils/helpers.js', ENT_QUOTES, 'UTF-8') ?>"></script>
<script>
let cartData = null;

function escHtml(str) {
    if (window.DCHelpers && typeof window.DCHelpers.escHtml === 'function') {
        return window.DCHelpers.escHtml(str);
    }
    return String(str || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function loadCartData() {
    // Try sessionStorage first (preferred)
    const stored = sessionStorage.getItem('rqo_cart');
    if (stored) {
        try { cartData = JSON.parse(stored); } catch(e) {}
    }
    // Fallback: URL param
    if (!cartData) {
        const urlParam = new URLSearchParams(window.location.search).get('data');
        if (urlParam) {
            try { cartData = JSON.parse(decodeURIComponent(urlParam)); } catch(e) {}
        }
    }
    renderCheckout();
}

function renderCheckout() {
    const content = document.getElementById('checkoutContent');

    if (!cartData || !cartData.items || !cartData.items.length) {
        content.innerHTML = `
            <div class="empty-notice">
                <div style="font-size:2.5rem;margin-bottom:12px;"><i class="dc-icon dc-icon-shopping-cart"></i></div>
                <div style="font-size:1rem;font-weight:600;margin-bottom:8px;">Your cart is empty</div>
                <p>Go back to the <a href="index.php">menu</a> and add some items.</p>
            </div>`;
        return;
    }

    const total = cartData.items.reduce((s,i) => s + i.price * i.quantity, 0);
    const tableName = cartData.table_name || (cartData.table_id ? `Table ${cartData.table_id}` : 'Walk-in');

    const rows = cartData.items.map(i => `
        <div class="summary-row">
            <div>
                <div class="summary-name">${i.name}</div>
                <div class="summary-qty">×${i.quantity}</div>
            </div>
            <div class="summary-price">$${(i.price * i.quantity).toFixed(2)}</div>
        </div>`).join('');

    content.innerHTML = `
        <div class="dc-card" style="margin-bottom:20px;">
            <div class="section-title"><i class="dc-icon dc-icon-chair"></i> Table</div>
            <div style="font-size:1rem;font-weight:700;">${tableName}</div>
            <div class="dc-text-dim" style="font-size:0.825rem;">Your order will be delivered to this table</div>
        </div>

        <div class="dc-card" style="margin-bottom:20px;">
            <div class="section-title"><i class="dc-icon dc-icon-user"></i> Customer Details</div>
            <div class="customer-grid">
                <div>
                    <label class="dc-label" for="customerName">Full Name <span class="required-mark">*</span></label>
                    <input id="customerName" class="dc-input" type="text" maxlength="100" placeholder="Your full name" value="${escHtml(cartData.customer_name || '')}">
                </div>
                <div>
                    <label class="dc-label" for="customerPhone">Phone Number <span class="required-mark">*</span></label>
                    <input id="customerPhone" class="dc-input" type="tel" maxlength="30" placeholder="e.g. +1 555 123 4567" value="${escHtml(cartData.customer_phone || '')}">
                </div>
                <div>
                    <label class="dc-label" for="customerEmail">Email (optional)</label>
                    <input id="customerEmail" class="dc-input" type="email" maxlength="160" placeholder="you@example.com" value="${escHtml(cartData.customer_email || '')}">
                </div>
                <div>
                    <label class="dc-label" for="customerAddressNotes">Address/Notes (optional)</label>
                    <input id="customerAddressNotes" class="dc-input" type="text" maxlength="500" placeholder="Apartment, landmark, or delivery notes" value="${escHtml(cartData.customer_address_notes || '')}">
                </div>
            </div>
        </div>

        <div class="dc-card" style="margin-bottom:20px;">
            <div class="section-title"><i class="dc-icon dc-icon-clipboard"></i> Order Summary</div>
            ${rows}
            <hr style="border:none;border-top:1px solid var(--dc-border);margin:12px 0;">
            <div class="total-row">
                <span>Total</span>
                <span>$${total.toFixed(2)}</span>
            </div>
        </div>

        ${cartData.note ? `
        <div class="dc-card" style="margin-bottom:20px;">
            <div class="section-title"><i class="dc-icon dc-icon-file-text"></i> Special Instructions</div>
            <div style="font-size:0.875rem;color:var(--dc-text-2);">${cartData.note}</div>
        </div>` : ''}

        <div class="dc-card" style="margin-bottom:20px;">
            <div class="section-title"><i class="dc-icon dc-icon-receipt"></i> Add a Note (optional)</div>
            <textarea id="finalNote" class="dc-textarea" rows="2"
                      placeholder="Any last-minute requests?">${cartData.note || ''}</textarea>
        </div>

        <button class="dc-btn dc-btn-primary dc-btn-full dc-btn-lg btn-primary-gold" onclick="placeOrder()" id="placeBtn">
            Place Order <i class="dc-icon dc-icon-arrow-right dc-icon-sm"></i>
        </button>
        <a href="index.php${cartData.table_id ? '?table='+cartData.table_id : ''}"
           class="dc-btn dc-btn-ghost dc-btn-full" style="margin-top:10px;">
            <i class="dc-icon dc-icon-arrow-left dc-icon-sm"></i> Back to Menu
        </a>`;

    bindCustomerDraftInputs();
}

function getCustomerDraftFromInputs() {
    return {
        customer_name: document.getElementById('customerName')?.value?.trim() || '',
        customer_phone: document.getElementById('customerPhone')?.value?.trim() || '',
        customer_email: document.getElementById('customerEmail')?.value?.trim() || '',
        customer_address_notes: document.getElementById('customerAddressNotes')?.value?.trim() || '',
    };
}

function saveCustomerDraft() {
    if (!cartData) return;
    Object.assign(cartData, getCustomerDraftFromInputs());
    sessionStorage.setItem('rqo_cart', JSON.stringify(cartData));
}

function bindCustomerDraftInputs() {
    ['customerName', 'customerPhone', 'customerEmail', 'customerAddressNotes'].forEach((id) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('input', saveCustomerDraft);
        el.addEventListener('blur', saveCustomerDraft);
    });
}

async function placeOrder() {
    const btn = document.getElementById('placeBtn');
    if (!btn) return;
    // UI validation: Require table selection
    if (!cartData.table_id) {
        btn.classList.remove('loading');
        btn.textContent = 'Place Order';
        alert('Please select a table before placing your order.');
        return;
    }
    btn.classList.add('loading');
    btn.textContent = 'Placing order…';

    const note = document.getElementById('finalNote')?.value || '';
    const customer = getCustomerDraftFromInputs();

    if (!customer.customer_name) {
        Toast.error('Please enter your full name.');
        btn.classList.remove('loading');
        btn.textContent = 'Place Order';
        return;
    }
    if (!customer.customer_phone) {
        Toast.error('Please enter your phone number.');
        btn.classList.remove('loading');
        btn.textContent = 'Place Order';
        return;
    }

    const phoneDigits = customer.customer_phone.replace(/\D+/g, '');
    if (phoneDigits.length < 7 || phoneDigits.length > 15) {
        Toast.error('Phone number must contain 7 to 15 digits.');
        btn.classList.remove('loading');
        btn.textContent = 'Place Order';
        return;
    }

    if (customer.customer_email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(customer.customer_email)) {
        Toast.error('Please enter a valid email address or leave it empty.');
        btn.classList.remove('loading');
        btn.textContent = 'Place Order';
        return;
    }

    const payload = {
        table_id: cartData.table_id,
        note,
        customer_name: customer.customer_name,
        customer_phone: customer.customer_phone,
        customer_email: customer.customer_email,
        customer_address_notes: customer.customer_address_notes,
        items: cartData.items.map(i => ({
            menu_item_id: i.menu_item_id,
            quantity:     i.quantity,
        })),
    };

    try {
        const res = await DC.post('api/orders.php', payload);
        const orderId = res.data.order_id;
        sessionStorage.removeItem('rqo_cart');
        // Redirect to order confirmation page
        window.location.href = `order-confirmation.php?order_id=${orderId}`;
    } catch (e) {
        Toast.error(e.message || 'Failed to place order. Please try again.');
        btn.classList.remove('loading');
        btn.textContent = 'Place Order →';
    }
}

loadCartData();
</script>
</body>
</html>
