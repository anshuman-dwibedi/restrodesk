# ðŸ½ï¸ Smart Restaurant QR Ordering System

> **Part of the DevCore Portfolio Suite** â€” built on the [DevCore Shared Library](https://github.com/anshuman-dwibedi/devcore-shared)

---

## ðŸ“¸ What It Looks Like

**Customer Menu Page** â€” A clean dark-themed restaurant menu with category pills at the top (Starters, Mains, Desserts, Drinks). Menu items are displayed in a responsive grid of cards, each with a high-quality food photo, name, description and price. A sticky cart sidebar sits on the right (desktop) or a floating cart button on mobile. The top nav shows the table number detected from the QR code.

**Checkout Page** â€” A step-progress bar (Menu â†’ Review â†’ Confirmed), a clear order summary card with items and prices, a special instructions field, and a prominent "Place Order" button.

**Order Status Page** â€” A 4-step visual progress tracker (Received â†’ Preparing â†’ Ready â†’ Delivered) with animated icons, a live status card that updates in real time every 3 seconds, and a full order summary.

**Admin Dashboard** â€” A dark sidebar layout with 4 KPI stat cards (Orders Today, Revenue, Avg Order Value, Active Tables), three Chart.js charts (line, bar, doughnut), and a live feed of recent orders with status badges.

**Kitchen Orders View** â€” A card grid of all active orders, each showing the table, item list, elapsed time, and action buttons (Start Preparing / Mark Ready / Mark Delivered). Cards animate in when new orders arrive. A live pulsing indicator shows the feed is active.

**QR Generator** â€” A grid of all 10 tables, each with its unique QR code image, the encoded URL, and Download + Preview buttons. A "Print All" button opens a print-optimised page.

---

## âœ¨ Features

- ðŸ“± **QR Code Per Table** â€” Each table gets a unique token URL; scanning opens the menu pre-tagged with the table number
- âš¡ **Real-Time Order Updates** â€” Kitchen view and customer status page both poll `/api/live.php` every 3 seconds via `LivePoller`
- ðŸ“Š **Analytics Dashboard** â€” KPIs, line/bar/doughnut charts, live order feed â€” all from one analytics API call
- ðŸ›’ **Frictionless Cart** â€” Sticky sidebar on desktop, bottom-sheet modal on mobile, persisted in `sessionStorage`
- ðŸ” **Session Auth** â€” Admin protected by `Auth::requireRole('admin')` on every page
- âœ… **Server-Side Validation** â€” All inputs run through `Validator::make()` before any DB write
- ðŸ’¬ **Toast Notifications** â€” Every user action (add to cart, status change, error) fires a `Toast` notification
- ðŸ–¼ï¸ **Storage-Backed Image Uploads** â€” Drag-and-drop menu item photos directly in the admin; driver-switchable between Local, AWS S3, and Cloudflare R2 with a single config change
- ðŸ–¨ï¸ **Print-Ready QR Page** â€” One click opens a print-optimised layout of all table QR codes
- ðŸŽ¨ **DevCore Design System** â€” 100% `dc-*` CSS classes â€” no custom duplicate styles

---

## ðŸ›  Tech Stack

| Layer      | Technology                              |
|------------|------------------------------------------|
| Backend    | PHP 8.1+ (no framework)                  |
| Database   | MySQL 8.0+                               |
| Auth       | PHP sessions via `Auth` class            |
| Storage    | DevCore `Storage` facade â€” Local / AWS S3 / Cloudflare R2 |
| Frontend   | Vanilla JS + DevCore UI (devcore.js/css) |
| Charts     | Chart.js 4.4 (via CDN)                   |
| QR Codes   | qrserver.com API (free, no key needed)   |
| Design     | DevCore CSS design system                |

---

## ðŸš€ Setup Instructions

### 1. Clone / Copy the project

Place the project folder so it lives **alongside** the `core/` shared library:

```
devcore/
â”œâ”€â”€ core/               â† shared library (from devcore-shared-library.zip)
â”‚   â”œâ”€â”€ bootstrap.php
â”‚   â”œâ”€â”€ backend/
â”‚   â””â”€â”€ ui/
â”œâ”€â”€ config.php          â† your config (copy from config.example.php)
â””â”€â”€ restrodesk/   â† this project
    â”œâ”€â”€ index.php
    â”œâ”€â”€ admin/
    â”œâ”€â”€ api/
    â””â”€â”€ ...
```

### 2. Create `config.php`

Copy `config.example.php` to `devcore/config.php` and fill in your database credentials:

```php
return [
    'db_host'    => 'localhost',
    'db_name'    => 'restaurant_qr',
    'db_user'    => 'root',
    'db_pass'    => '',
    'app_name'   => 'Restrodesk',
    'app_url'    => 'http://localhost',
    'debug'      => true,
    'api_secret' => 'your-secret-here',
];
```

### 3. Configure Storage for image uploads

Copy `restrodesk/config.example.php` to `devcore/config.php` (or add the `storage` block to your existing config). The default `local` driver works with no extra setup:

```php
'storage' => [
    'driver' => 'local',   // 'local' | 's3' | 'r2'
    'local'  => [
        'root'     => __DIR__ . '/uploads',
        'base_url' => 'http://localhost/uploads',
    ],
],
```

**To switch to S3:** set `driver => 's3'` and fill in `key`, `secret`, `bucket`, `region`.  
**To switch to R2:** set `driver => 'r2'` and fill in `account_id`, `key`, `secret`, `bucket`, `base_url`.  
See `config.example.php` for the full reference with inline comments.

### 4. Create the database and import SQL

```bash
mysql -u root -p -e "CREATE DATABASE restaurant_qr CHARACTER SET utf8mb4;"
mysql -u root -p restaurant_qr < restrodesk/database.sql
```

### 5. Start a PHP server (dev)

```bash
cd devcore
php -S localhost:8000
```

### 6. Open in your browser

| URL | Description |
|-----|-------------|
| `http://localhost:8000/restrodesk/` | Customer menu (walk-in) |
| `http://localhost:8000/restrodesk/index.php?table=1` | Customer menu for Table 1 |
| `http://localhost:8000/restrodesk/admin/login.php` | Admin login |

**Admin credentials:**
- Email: `admin@restaurant.com`
- Password: `admin123`

---

## ðŸ“± How the QR System Works

1. **Admin visits** `admin/qr-generator.php` â€” sees a QR code for every table
2. **Each QR code encodes** a unique URL like:
   `http://yoursite.com/restrodesk/index.php?token=tok_t5_e7j3f6g8i9d4`
3. **Admin prints and places** the QR codes on the physical tables
4. **Customer scans** the QR with their phone camera
5. **Browser opens** `index.php?token=tok_t5_...` â€” the PHP resolves the token to `Table 5`
6. **Menu loads** with "Table 5" displayed in the nav badge
7. **Customer adds items** â€” table number is silently carried through the cart
8. **On checkout**, the order is submitted with `table_id: 5` automatically
9. **Kitchen sees** Table 5 on the orders screen instantly via live polling

---

## âš¡ How Real-Time Works

The system uses **short-interval HTTP polling** â€” a simple, robust approach with no WebSocket infrastructure required.

### Kitchen Orders (`admin/orders.php`)
```javascript
const poller = new LivePoller('../api/live.php', (res) => {
    renderOrders(res.data.active_orders);
    updateCounts(res.data.counts);
}, 3000);
poller.start();
```
Every 3 seconds, `LivePoller` (from `devcore.js`) calls `GET /api/live.php`, which queries the database for all active orders. New order cards animate in with `dc-animate-fade-up` and a toast notification fires.

### Customer Status Page (`order-status.php`)
```javascript
const poller = new LivePoller(
    `api/live.php?order_id=${ORDER_ID}`,
    handlePoll,
    3000
);
```
The customer's page polls for their specific order. When the status changes (e.g., `pending` â†’ `preparing`), the progress tracker updates and a toast notification fires. Polling stops automatically when status reaches `delivered`.

---

## ðŸ“ Project Structure

```
restrodesk/
â”œâ”€â”€ index.php              Customer menu â€” QR landing page
â”œâ”€â”€ checkout.php           Order review & confirmation
â”œâ”€â”€ order-status.php       Live order tracking for customer
â”œâ”€â”€ admin/
â”‚   â”œâ”€â”€ login.php          Admin authentication
â”‚   â”œâ”€â”€ dashboard.php      KPIs + charts + live feed
â”‚   â”œâ”€â”€ orders.php         Kitchen view â€” live order management
â”‚   â”œâ”€â”€ menu.php           Menu item CRUD (add/edit/delete)
â”‚   â”œâ”€â”€ qr-generator.php   QR code generation + print
â”‚   â””â”€â”€ logout.php
â”œâ”€â”€ api/
â”‚   â”œâ”€â”€ menu.php           GET menu (public)
â”‚   â”œâ”€â”€ menu-admin.php     POST/PUT/DELETE menu items (admin)
â”‚   â”œâ”€â”€ orders.php         POST create / GET list / PUT status
â”‚   â”œâ”€â”€ analytics.php      GET dashboard stats (admin)
â”‚   â””â”€â”€ live.php           GET real-time polling endpoint
â”œâ”€â”€ database.sql           Full schema + sample data
â””â”€â”€ README.md
```

---

## ðŸ”— DevCore Shared Library

This project is built on the **DevCore Shared Library**, providing:

- `Database` â€” singleton PDO wrapper
- `Api` â€” standardised JSON response helper
- `Auth` â€” session-based authentication
- `Analytics` â€” reusable query helpers
- `QrCode` â€” QR code URL generator
- `Validator` â€” input validation
- `Storage` â€” driver-switchable file storage facade (Local / S3 / R2)
- `devcore.css` â€” full dark-mode design system
- `devcore.js` â€” Toast, Modal, LivePoller, DCChart, DC.get/post

> **Part of the DevCore Portfolio Suite** â€” a collection of production-ready PHP projects sharing a common core library.

