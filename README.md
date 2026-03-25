# RestroDesk - Restaurant QR Ordering System

A complete QR code-based restaurant ordering system where customers scan QR codes at tables to order directly from their phones. Features live kitchen dashboard, real-time order tracking, table management, and admin analytics.

Perfect for modern restaurants, cafes, and food courts looking to streamline ordering and payment processing.

Built on the DevCore Shared Library with secure payment handling and kitchen workflow optimization.

## Live Deployment

- Production Website: https://restrodesk.42web.io

**Part of the DevCore Suite** — a collection of business-ready web applications sharing a common core library.

---

## Features

| Feature | Description |
|---------|-------------|
| QR Table Menu | Each restaurant table has unique QR code linking to its menu |
| Mobile Ordering | Customers scan QR, browse menu, customize items, place order from phone |
| Live Menu Display | Kitchen displays live incoming orders with status (received, preparing, ready, served) |
| Menu Categories | Organize menu items by appetizers, mains, sides, drinks, desserts, etc. |
| Item Customization | Customers can customize items (spicy level, preferences, special requests) |
| Order Tracking | Customers track order status in real-time (received → preparing → ready → served) |
| Table Management | Admin manages table status (vacant, occupied, cleaning, reserved) |
| Order History | Admin views all orders with timeline, customer details, amounts |
| Analytics Dashboard | Daily revenue, item popularity, orders by hour, table occupancy trends |
| Kitchen Workflow | Kitchen staff see orders in real-time, mark items as ready, coordinate with servers |

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.1+ with DevCore framework |
| Database | MySQL 8 / MariaDB 10.6+ |
| Frontend | Vanilla JavaScript ES2022 + DevCore UI library |
| Charts | Chart.js via DevCore wrapper |
| QR Codes | qrserver.com API for table codes |
| Image Storage | Local filesystem for menu item images |
| Real-Time | Client-side polling for order updates |
| Shared Core | DevCore Shared Library (git submodule at ./core/) |

---

## Project Structure

```
restrodesk/
├── index.php                   Customer menu (accessed via table QR)
├── checkout.php                Customer order confirmation + payment
├── order-status.php            Customer order tracking page
├── order-confirmation.php      Post-order summary
├── config.example.php          Configuration template
├── database.sql                Schema + sample menu items
├── .env.example                Environment variables
│
├── api/
│   ├── menu.php                GET menu items (public, by table)
│   ├── menu-admin.php          POST create, PUT update, DELETE menu items (admin)
│   ├── orders.php              POST place, GET list/view, PUT change status
│   ├── tables-list.php         GET all tables with occupancy (admin)
│   ├── live.php                GET real-time orders for kitchen (polling)
│   └── analytics.php           GET dashboard stats (admin only)
│
├── admin/
│   ├── login.php               Staff authentication
│   ├── dashboard.php           Analytics + live order feed
│   ├── menu.php                Menu management (add/edit/delete items + images)
│   ├── orders.php              Order management + kitchen view
│   ├── qr-generator.php        Generate printable QR codes for tables
│   └── logout.php              Session logout
│
└── core/                       DevCore shared library (git submodule)
    ├── bootstrap.php           Autoloader + config loader
    ├── backend/                PHP classes (Database, Api, Auth, etc.)
    └── ui/                     CSS framework + JavaScript utilities
```

---

## Setup Instructions

### 1. Clone DevCore Shared Library

```bash
git clone https://github.com/anshuman-dwibedi/devcore-shared.git core
```

Or using submodule:
```bash
git clone --recursive https://github.com/anshuman-dwibedi/restrodesk.git
```

### 2. Create Database

```bash
mysql -u root -p -e "CREATE DATABASE restaurant_qr CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p restaurant_qr < database.sql
```

Database includes sample menu items and table configurations.

### 3. Configure Application

```bash
cp config.example.php config.php
```

Edit `config.php`:

```php
return [
    'db_host'    => 'localhost',
    'db_name'    => 'restaurant_qr',
    'db_user'    => 'root',
    'db_pass'    => 'your_password',
    'app_name'   => 'RestroDesk',
    'app_url'    => 'http://localhost/restrodesk',
    'debug'      => true,  // set false in production
    'api_secret' => 'your-secure-random-string',
];
```

### 4. Start Web Server

Using PHP built-in server:
```bash
php -S localhost:8000
```

Or configure Apache/Nginx to point to project root.

### 5. Access Application

- **Customer Menu (scan QR):** http://localhost:8000/restrodesk/index.php?token=TABLE_01
- **Admin Dashboard:** http://localhost:8000/restrodesk/admin/login.php

**Default Admin Credentials:**
```
Email: admin@restaurant.com
Password: admin123
```

> Change immediately in production.

### 6. Print QR Codes for Tables

1. Admin → QR Generator
2. Click "Print All QR Codes"
3. Print on sticker paper and place on each table

---

## Configuration

### config.example.php

Database credentials, app URL, and other settings.

Sample menu items in database:
- Starters (appetizers)
- Main courses
- Sides (rice, bread, vegetables)
- Beverages (soft drinks, tea, coffee)
- Desserts

Sample tables: 10 tables (Table 1 through Table 10)

---

## How It Works

### Customer Ordering Flow

1. Customer arrives at restaurant table
2. Scans QR code sticker on table via phone camera
3. Lands on `/index.php?token=TABLE_01`
4. Browses menu items grouped by category
5. Taps item to see details and customization options:
   - Spice level (mild, medium, hot)
   - Substitutions / special requests
   - Quantity
6. Adds to order
7. Reviews order summary on cart page
8. Proceeds to checkout
9. Provides name and phone number (payment optional in basic setup)
10. Submits order → order kitchen gets notified

### Kitchen Display System

Kitchen staff terminal shows:

```
ORDER #1: Table 5
  - Biryani x2 (extra spicy)
  - Naan x2 (butter)
  - Raita x1

Status: RECEIVED → [Mark as Cooking] → COOKING → [Mark as Ready] → READY
```

Real-time updates every 3 seconds via `/api/live.php`. Kitchen staff:
1. See new orders as they come in
2. Mark items as "Cooking" (communicate start to kitchen)
3. Mark items as "Ready" (server will pick up)
4. System notifies customer order is ready

### Order Tracking (Customer View)

Customer can view order status on their phone via order tracking page:

```
Your Order #1234
├─ Status: Received → Cooking → Ready ← (You are here)
├─ Estimated Time: 15 minutes
├─ Items: Biryani (2), Naan (2), Raita (1)
└─ [Print Receipt] [Pay Now (if enabled)]
```

Updates every 3 seconds via polling.

### Table Management

Admin can manage table status:
- **Vacant** — empty, ready for customers
- **Occupied** — customer is seated
- **Cleaning** — staff cleaning table
- **Reserved** — reserved for walk-in

QR Generator shows which tables are vacant vs occupied, helps staff manage seating chart.

### Analytics

Dashboard shows:
- Daily revenue (real-time)
- Orders placed today / this month
- Average order value
- Popular menu items (bar chart)
- Orders by hour (line chart)
- Table utilization (occupied vs vacant)
- Peak hours analysis

---

## API Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | /api/menu.php?token=TABLE_01 | No | Get menu items for table (public) |
| POST | /api/menu-admin.php | Admin | Create menu item with image |
| PUT | /api/menu-admin.php?id=X | Admin | Update menu item |
| DELETE | /api/menu-admin.php?id=X | Admin | Delete menu item and image |
| POST | /api/orders.php | No | Create new order from customer |
| GET | /api/orders.php | Admin | List all orders with filters |
| GET | /api/orders.php?id=X | No/Admin | Get order details (public via token, admin via session) |
| PUT | /api/orders.php?id=X | Admin | Update order status (received, cooking, ready, served, canceled) |
| GET | /api/tables-list.php | Admin | Get all tables and occupancy status |
| GET | /api/live.php | No/Admin | Real-time orders for kitchen display (unfiltered) or customer tracking (by token) |
| GET | /api/analytics.php | Admin | Dashboard statistics and charts |

---

## Troubleshooting

**Database not found**
- Create: `mysql -u root -p -e "CREATE DATABASE restaurant_qr;"`
- Import: `mysql -u root -p restaurant_qr < database.sql`
- Verify database name in config.php

**"Cannot include core/bootstrap.php"**
- Clone: `git clone https://github.com/anshuman-dwibedi/devcore-shared.git core`
- Or: `git submodule update --init`

**QR codes not scanning**
- QR codes generated via qrserver.com API (requires internet)
- Verify URL in QR code is accessible: http://localhost:8000/restrodesk/index.php?token=TABLE_01
- Test: Scan generated QR and check if it opens menu

**Kitchen orders not updating live**
- Check browser console for JS errors
- Verify `/api/live.php` returns JSON with current orders
- Polling default interval: 3 seconds (configurable)

**Menu items not showing for customer**
- Verify items in database: `SELECT COUNT(*) FROM menu_items;`
- Check items are marked active: `SELECT COUNT(*) FROM menu_items WHERE active = 1;`
- Verify table token is valid in URL: `SELECT token FROM tables;`

**Admin login not working**
- Verify users table: `SELECT COUNT(*) FROM users;`
- Check session handling in php.ini
- Reset password via database if needed

**Images not uploading**
- Create uploads folder: `mkdir -p uploads && chmod 755 uploads`
- Verify folder is writable: `touch uploads/test && rm uploads/test`

**Order status not changing**
- Verify `/api/orders.php` PUT endpoint is accessible
- Check admin permissions in database: `SELECT role FROM users WHERE email = 'admin@restaurant.com';`

---

## Environment Variables

Create `.env` or configure in config.php:

| Variable | Purpose |
|----------|---------|
| DB_HOST | MySQL hostname |
| DB_NAME | Database name |
| DB_USER | Database username |
| DB_PASS | Database password |
| APP_NAME | Restaurant name in UI |
| APP_URL | Public base URL for customer QR links |
| DEBUG | Debug mode (true/false) |
| API_SECRET | API bearer token secret |
| KITCHEN_POLLING_INTERVAL | Kitchen display refresh interval (milliseconds, default: 3000) |
| CUSTOMER_POLLING_INTERVAL | Customer order tracking refresh interval (default: 3000) |
| ORDER_READY_NOTIFICATION | Enable SMS/notification when order ready (true/false) |
| PAYMENT_ENABLED | Enable payment collection (true/false, default: false) |

---

## License

MIT License — see LICENSE file.

---

**Questions?** Visit [DevCore Shared Library](https://github.com/anshuman-dwibedi/devcore-shared) repository.
