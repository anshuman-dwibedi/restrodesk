-- ============================================================
-- Smart Restaurant QR Ordering System — database.sql
-- Import: mysql -u root -p restaurant_qr < database.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS restrodesk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE restrodesk;

-- ─── TABLES (physical restaurant tables) ──────────────────────
CREATE TABLE IF NOT EXISTS `tables` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(50)  NOT NULL,
    `qr_token`   VARCHAR(64)  NOT NULL UNIQUE,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── CATEGORIES ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `categories` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(80)  NOT NULL,
    `sort_order` TINYINT      NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── MENU ITEMS ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `menu_items` (
    `id`          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `category_id` INT UNSIGNED   NOT NULL,
    `name`        VARCHAR(120)   NOT NULL,
    `description` TEXT,
    `price`       DECIMAL(8,2)   NOT NULL,
    `image_url`   VARCHAR(500)   DEFAULT NULL,
    `available`   TINYINT(1)     NOT NULL DEFAULT 1,
    `created_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_menu_cat` (`category_id`),
    CONSTRAINT `fk_menu_cat` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── ORDERS ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `orders` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `table_id`   INT UNSIGNED  NOT NULL,
    `token`      VARCHAR(64)   NOT NULL UNIQUE,
    `status`     ENUM('pending','preparing','ready','delivered','completed') NOT NULL DEFAULT 'pending',
    `total`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `note`       TEXT,
    `customer_name` VARCHAR(100) DEFAULT NULL,
    `customer_phone` VARCHAR(30) DEFAULT NULL,
    `customer_email` VARCHAR(160) DEFAULT NULL,
    `customer_address_notes` TEXT,
    `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_order_table` (`table_id`),
    CONSTRAINT `fk_order_table` FOREIGN KEY (`table_id`) REFERENCES `tables` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── ORDER ITEMS ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `order_items` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `order_id`     INT UNSIGNED  NOT NULL,
    `menu_item_id` INT UNSIGNED  NOT NULL,
    `quantity`     TINYINT       NOT NULL DEFAULT 1,
    `unit_price`   DECIMAL(8,2)  NOT NULL,
    `name`         VARCHAR(120)  NOT NULL,
    PRIMARY KEY (`id`),
    KEY `fk_oi_order` (`order_id`),
    KEY `fk_oi_item`  (`menu_item_id`),
    CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`)     REFERENCES `orders`     (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_oi_item`  FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── ADMINS ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `admins` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(80)  NOT NULL,
    `email`      VARCHAR(160) NOT NULL UNIQUE,
    `password`   VARCHAR(255) NOT NULL,
    `role`       VARCHAR(30)  NOT NULL DEFAULT 'admin',
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- SAMPLE DATA
-- ============================================================

-- ─── 10 TABLES ────────────────────────────────────────────────
INSERT INTO `tables` (`name`, `qr_token`) VALUES
('Table 1',  'tok_t1_a3f9b2c1d4e5'),
('Table 2',  'tok_t2_b4g0c3d5f6a1'),
('Table 3',  'tok_t3_c5h1d4e6g7b2'),
('Table 4',  'tok_t4_d6i2e5f7h8c3'),
('Table 5',  'tok_t5_e7j3f6g8i9d4'),
('Table 6',  'tok_t6_f8k4g7h9j0e5'),
('Table 7',  'tok_t7_g9l5h8i0k1f6'),
('Table 8',  'tok_t8_h0m6i9j1l2g7'),
('Table 9',  'tok_t9_i1n7j0k2m3h8'),
('Table 10', 'tok_t10_j2o8k1l3n4i9');

-- ─── 4 CATEGORIES ─────────────────────────────────────────────
INSERT INTO `categories` (`name`, `sort_order`) VALUES
('Starters',  1),
('Mains',     2),
('Desserts',  3),
('Drinks',    4);

-- ─── 16 MENU ITEMS ────────────────────────────────────────────
INSERT INTO `menu_items` (`category_id`, `name`, `description`, `price`, `image_url`, `available`) VALUES
-- Starters (cat 1)
(1, 'Crispy Calamari',       'Lightly battered squid rings with lemon aioli and house marinara',  9.50,  'https://images.unsplash.com/photo-1604909052743-94e838986d24?w=400&q=80', 1),
(1, 'Bruschetta al Pomodoro','Grilled sourdough topped with heirloom tomatoes, basil & aged balsamic', 8.00, 'https://images.unsplash.com/photo-1572695157366-5e585ab2b69f?w=400&q=80', 1),
(1, 'Burrata & Prosciutto',  'Creamy burrata, San Daniele prosciutto, rocket, toasted pine nuts',  13.50, 'https://images.unsplash.com/photo-1549060279-7e168fcee0c2?w=400&q=80', 1),
(1, 'French Onion Soup',     'Slow-cooked onion broth, crouton, melted Gruyère crust',             10.00, 'https://images.unsplash.com/photo-1547592166-23ac45744acd?w=400&q=80', 1),
-- Mains (cat 2)
(2, 'Truffle Mushroom Risotto',   'Arborio rice, wild mushrooms, parmesan, fresh truffle oil',     22.00, 'https://images.unsplash.com/photo-1476124369491-e7addf5db371?w=400&q=80', 1),
(2, 'Grilled Salmon Fillet',      '220g Atlantic salmon, herb butter, seasonal greens, new potatoes', 26.00, 'https://images.unsplash.com/photo-1467003909585-2f8a72700288?w=400&q=80', 1),
(2, '8oz Sirloin Steak',          'Grass-fed sirloin, peppercorn sauce, hand-cut fries, watercress', 34.00, 'https://images.unsplash.com/photo-1546833999-b9f581a1996d?w=400&q=80', 1),
(2, 'Margherita Pizza',           'San Marzano tomato, fior di latte, fresh basil, extra virgin olive oil', 17.50, 'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=400&q=80', 1),
(2, 'Pan-Seared Duck Breast',     'Confit duck leg, cherry jus, dauphinoise potatoes, tenderstem broccoli', 31.00, 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=400&q=80', 1),
(2, 'Pappardelle Bolognese',      'Slow-braised beef ragù, fresh egg pasta, parmesan, basil',     19.00, 'https://images.unsplash.com/photo-1551183053-bf91798d765?w=400&q=80', 1),
-- Desserts (cat 3)
(3, 'Dark Chocolate Fondant',     'Warm chocolate cake, salted caramel core, vanilla bean ice cream', 9.50, 'https://images.unsplash.com/photo-1606313564200-e75d5e30476c?w=400&q=80', 1),
(3, 'Crème Brûlée',               'Classic vanilla custard, caramelised sugar crust, fresh berries', 8.50, 'https://images.unsplash.com/photo-1470124182917-cc6e71b22ecc?w=400&q=80', 1),
-- Drinks (cat 4)
(4, 'Aperol Spritz',              'Aperol, Prosecco, soda, orange slice, over ice',                 11.00, 'https://images.unsplash.com/photo-1551538827-9c037cb4f32a?w=400&q=80', 1),
(4, 'Artisan Lemonade',           'Freshly squeezed lemon, house-made elderflower syrup, sparkling water', 5.50, 'https://images.unsplash.com/photo-1621263764928-df1444c5e859?w=400&q=80', 1),
(4, 'Cold Brew Coffee',           'Single-origin Ethiopian cold brew, slow-steeped 18hrs',          5.00, 'https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?w=400&q=80', 1),
(4, 'Sparkling Mineral Water',    '750ml San Pellegrino',                                           3.50, 'https://images.unsplash.com/photo-1548839140-29a749e1cf4d?w=400&q=80', 1);

-- ─── 1 ADMIN (password: admin123 bcrypt) ──────────────────────
INSERT INTO `admins` (`name`, `email`, `password`, `role`) VALUES
('Admin User', 'admin@restaurant.com',
 '$2a$12$ZNzRQvODic4uFOoRa2EA.eTsw9FhqMtVukxqSOEablxjkrMP4QSLW',
 'admin');

-- ─── 20 SAMPLE ORDERS (spread across last 14 days) ────────────
-- We insert orders with manually-set created_at to populate chart data
INSERT INTO `orders` (`table_id`, `status`, `total`, `created_at`) VALUES
(1,  'delivered', 47.50,  DATE_SUB(NOW(), INTERVAL 13 DAY)),
(3,  'delivered', 31.00,  DATE_SUB(NOW(), INTERVAL 13 DAY)),
(2,  'delivered', 62.00,  DATE_SUB(NOW(), INTERVAL 12 DAY)),
(5,  'delivered', 24.50,  DATE_SUB(NOW(), INTERVAL 11 DAY)),
(7,  'delivered', 88.00,  DATE_SUB(NOW(), INTERVAL 11 DAY)),
(4,  'delivered', 41.00,  DATE_SUB(NOW(), INTERVAL 10 DAY)),
(6,  'delivered', 55.50,  DATE_SUB(NOW(), INTERVAL 9  DAY)),
(8,  'delivered', 37.00,  DATE_SUB(NOW(), INTERVAL 8  DAY)),
(2,  'delivered', 73.00,  DATE_SUB(NOW(), INTERVAL 7  DAY)),
(10, 'delivered', 29.50,  DATE_SUB(NOW(), INTERVAL 7  DAY)),
(1,  'delivered', 96.00,  DATE_SUB(NOW(), INTERVAL 6  DAY)),
(9,  'delivered', 43.50,  DATE_SUB(NOW(), INTERVAL 5  DAY)),
(3,  'delivered', 68.00,  DATE_SUB(NOW(), INTERVAL 5  DAY)),
(5,  'delivered', 52.00,  DATE_SUB(NOW(), INTERVAL 4  DAY)),
(7,  'delivered', 34.50,  DATE_SUB(NOW(), INTERVAL 4  DAY)),
(6,  'delivered', 81.00,  DATE_SUB(NOW(), INTERVAL 3  DAY)),
(4,  'delivered', 47.00,  DATE_SUB(NOW(), INTERVAL 2  DAY)),
(2,  'delivered', 59.50,  DATE_SUB(NOW(), INTERVAL 1  DAY)),
(8,  'preparing', 38.00,  NOW()),
(1,  'pending',   66.50,  NOW());

-- ─── ORDER ITEMS for sample orders ────────────────────────────
-- Order 1
INSERT INTO `order_items` (`order_id`, `menu_item_id`, `quantity`, `unit_price`, `name`) VALUES
(1, 1, 2, 9.50,  'Crispy Calamari'),
(1, 6, 1, 26.00, 'Grilled Salmon Fillet'),
(1, 14, 1, 2.50, 'Sparkling Mineral Water');
-- Order 2
INSERT INTO `order_items` (`order_id`, `menu_item_id`, `quantity`, `unit_price`, `name`) VALUES
(2, 9, 1, 31.00, 'Pan-Seared Duck Breast');
-- Order 3
INSERT INTO `order_items` (`order_id`, `menu_item_id`, `quantity`, `unit_price`, `name`) VALUES
(3, 7, 1, 34.00, '8oz Sirloin Steak'),
(3, 4, 1, 10.00, 'French Onion Soup'),
(3, 13, 1, 11.00, 'Aperol Spritz'),
(3, 15, 1, 5.00,  'Cold Brew Coffee');
-- Order 4
INSERT INTO `order_items` (`order_id`, `menu_item_id`, `quantity`, `unit_price`, `name`) VALUES
(4, 8, 1, 17.50, 'Margherita Pizza'),
(4, 14, 1, 5.50, 'Artisan Lemonade');
-- Order 5
INSERT INTO `order_items` (`order_id`, `menu_item_id`, `quantity`, `unit_price`, `name`) VALUES
(5, 7, 2, 34.00, '8oz Sirloin Steak'),
(5, 11, 1, 9.50, 'Dark Chocolate Fondant'),
(5, 13, 1, 11.00,'Aperol Spritz');
-- Order 6
INSERT INTO `order_items` (`order_id`, `menu_item_id`, `quantity`, `unit_price`, `name`) VALUES
(6, 5, 1, 22.00, 'Truffle Mushroom Risotto'),
(6, 3, 1, 13.50, 'Burrata & Prosciutto'),
(6, 16, 1, 3.50, 'Sparkling Mineral Water');
-- Order 7
INSERT INTO `order_items` (`order_id`, `menu_item_id`, `quantity`, `unit_price`, `name`) VALUES
(7, 9, 1, 31.00, 'Pan-Seared Duck Breast'),
(7, 12, 1, 8.50, 'Crème Brûlée'),
(7, 15, 1, 5.00, 'Cold Brew Coffee');
-- Order 8
INSERT INTO `order_items` (`order_id`, `menu_item_id`, `quantity`, `unit_price`, `name`) VALUES
(8, 10, 1, 19.00,'Pappardelle Bolognese'),
(8, 2,  2, 8.00, 'Bruschetta al Pomodoro'),
(8, 16, 1, 3.50, 'Sparkling Mineral Water');
-- Order 19 (preparing)
INSERT INTO `order_items` (`order_id`, `menu_item_id`, `quantity`, `unit_price`, `name`) VALUES
(19, 6, 1, 26.00, 'Grilled Salmon Fillet'),
(19, 1, 1, 9.50,  'Crispy Calamari'),
(19, 14,1, 5.50,  'Artisan Lemonade');
-- Order 20 (pending)
INSERT INTO `order_items` (`order_id`, `menu_item_id`, `quantity`, `unit_price`, `name`) VALUES
(20, 7, 1, 34.00, '8oz Sirloin Steak'),
(20, 5, 1, 22.00, 'Truffle Mushroom Risotto'),
(20, 11,1, 9.50,  'Dark Chocolate Fondant');
