-- database.sql
-- Shoes Inventory Management System
-- Import this file in phpMyAdmin or run: mysql -u root shoes_inventory < database.sql

CREATE DATABASE IF NOT EXISTS shoes_inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE shoes_inventory;

-- ── Users ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id           INT            AUTO_INCREMENT PRIMARY KEY,
    username     VARCHAR(60)    NOT NULL UNIQUE,
    password     VARCHAR(255)   NOT NULL,
    full_name    VARCHAR(120)   NOT NULL,
    role         ENUM('admin','cashier') NOT NULL DEFAULT 'cashier',
    is_active    TINYINT(1)     NOT NULL DEFAULT 1,
    created_at   TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default accounts (bcrypt cost=12, verified hashes)
-- admin   → password: admin123
-- cashier → password: cashier123
INSERT INTO users (username, password, full_name, role) VALUES
  ('admin',   '$2y$12$mEi5l3gDqKnsGuk2FVTSMeIq9AYwzERNj5F2pNgS7QWWwfIqFZpLG', 'Store Administrator', 'admin'),
  ('cashier', '$2y$12$Uls5bRn5yP/eClWk2YWQYuO4MThepcYDYEB1Cxb9VKQURP8Uf1zb2', 'Store Cashier',       'cashier');

-- ── Products ──────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS products (
    id           INT            AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(255)   NOT NULL,
    brand        VARCHAR(100)   NOT NULL,
    category     VARCHAR(100)   NOT NULL,
    size         VARCHAR(20)    NOT NULL,
    color        VARCHAR(50)    NOT NULL,
    cost_price   DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    price        DECIMAL(10,2)  NOT NULL CHECK (price > 0),
    quantity     INT            NOT NULL DEFAULT 0,
    min_stock    INT            NOT NULL DEFAULT 5,
    max_stock    INT            NOT NULL DEFAULT 100,
    gender       ENUM('Men','Women','Unisex') NOT NULL DEFAULT 'Unisex',
    description  TEXT,
    image        VARCHAR(255)   DEFAULT NULL,
    is_archived  TINYINT(1)     NOT NULL DEFAULT 0,
    created_at   TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_archived (is_archived),
    INDEX idx_category (category),
    INDEX idx_brand    (brand)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Sample Products — Nike Philippines ───────────────────────────────────────
-- Prices based on Nike PH official store (₱). All products are Nike brand.
-- Categories: Running, Casual, Athletic, Sneakers, Training, Basketball
-- (product_name, brand, category, size, color, cost_price, price, quantity, min_stock, max_stock, gender, description)
INSERT INTO products (product_name, brand, category, size, color, cost_price, price, quantity, min_stock, max_stock, gender, description) VALUES

-- ── Running ───────────────────────────────────────────────────────────────────
('Nike Air Zoom Pegasus 40',    'Nike', 'Running',    '9',    'Black/White',        3800.00,  5495.00, 25, 5, 60,
 'Men', 'The iconic Nike Air Zoom Pegasus 40 delivers versatile everyday running performance. Features Zoom Air cushioning for a responsive, snappy ride.'),

('Nike Air Zoom Pegasus 40',    'Nike', 'Running',    '7',    'Pink/White',         3800.00,  5495.00, 18, 5, 50,
 'Women', 'Womens Nike Air Zoom Pegasus 40 in bold Pink/White. Trusted by runners for its cushioned, responsive feel on every run.'),

('Nike Invincible Run 3',       'Nike', 'Running',    '10',   'White/Pure Platinum',4500.00,  7995.00, 12, 3, 40,
 'Men', 'Maximal cushioning for easy days. The Nike Invincible Run 3 features ZoomX foam for a super-soft, bouncy ride that keeps your legs fresh.'),

('Nike React Infinity Run FK 3','Nike', 'Running',    '8',    'Thunder Blue/White', 4200.00,  6995.00, 15, 5, 50,
 'Men', 'Designed to help reduce injury with a rocker-shaped sole and soft React foam. The Nike React Infinity Run FK 3 keeps you running comfortably.'),

('Nike Free RN 5.0 Next Nature', 'Nike','Running',    '7.5',  'Pale Ivory/Melon',   2800.00,  4495.00, 20, 5, 55,
 'Women', 'A barefoot-inspired design using at least 20% recycled material. The flexible outsole moves with your foot for a natural feel.'),

-- ── Casual / Lifestyle ────────────────────────────────────────────────────────
('Nike Air Force 1 \'07',       'Nike', 'Casual',     '10',   'White/White',        3200.00,  5295.00, 30, 8, 80,
 'Men', 'The radiance lives on in the Nike Air Force 1 07. Classic leather upper with Air cushioning. A timeless icon of street style.'),

('Nike Air Force 1 \'07',       'Nike', 'Casual',     '7',    'White/White',        3200.00,  5295.00, 28, 8, 70,
 'Women', 'Women''s Nike Air Force 1 07 in iconic all-white. The classic basketball-inspired silhouette made for everyday street wear.'),

('Nike Air Force 1 \'07',       'Nike', 'Casual',     '9',    'Black/Black',        3200.00,  5295.00, 22, 5, 60,
 'Unisex', 'The all-black AF1 — sleek, bold, and versatile. Premium leather upper with Air cushioning for all-day comfort.'),

('Nike Cortez',                 'Nike', 'Casual',     '9',    'White/University Red',2600.00, 3995.00, 20, 5, 50,
 'Unisex', 'Nike''s original running shoe is back as a retro lifestyle icon. The Nike Cortez features a classic leather upper and chunky sole.'),

('Nike Blazer Mid ''77',        'Nike', 'Casual',     '10',   'White/Black',        3000.00,  4895.00, 18, 5, 50,
 'Men', 'Vintage basketball style meets everyday comfort. The Nike Blazer Mid 77 features a high-top silhouette with retro branding.'),

-- ── Sneakers ──────────────────────────────────────────────────────────────────
('Nike Air Max 270',            'Nike', 'Sneakers',   '10',   'Black/Anthracite',   4000.00,  6995.00, 15, 5, 45,
 'Men', 'Nike''s first lifestyle Air unit — the tallest heel Air bag yet. The Air Max 270 delivers all-day comfort with a bold, modern look.'),

('Nike Air Max 90',             'Nike', 'Sneakers',   '8',    'White/Wolf Grey',    3800.00,  6495.00, 18, 5, 50,
 'Men', 'Nothing as fly, nothing as comfortable, nothing as proven. The Nike Air Max 90 keeps a fresh face with the same classic Max Air cushioning.'),

('Nike Air Max 97',             'Nike', 'Sneakers',   '9',    'Silver Bullet',      4500.00,  7995.00, 10, 3, 35,
 'Unisex', 'Inspired by Japanese bullet trains. The Air Max 97 features full-length Air cushioning and reflective piping. A collector''s classic.'),

('Nike Dunk Low Retro',         'Nike', 'Sneakers',   '9',    'Panda (Black/White)',3500.00,  5995.00, 12, 5, 40,
 'Unisex', 'Created for the hardwood but later taken to the streets. The Nike Dunk Low Retro returns with classic colors and clean leather overlays.'),

-- ── Athletic / Training ───────────────────────────────────────────────────────
('Nike Metcon 8',               'Nike', 'Athletic',   '10',   'Black/Volt',         4200.00,  6495.00, 15, 5, 45,
 'Men', 'Built for the hardest workouts. The Nike Metcon 8 features a stable heel for lifting and a flexible forefoot for sprints and jumps.'),

('Nike Free Metcon 5',          'Nike', 'Athletic',   '7',    'White/Pink Spell',   3600.00,  5995.00, 12, 4, 40,
 'Women', 'Versatile training shoe designed for dynamic movement. The Free Metcon 5 pairs flexibility with stability for any gym session.'),

('Nike Air Zoom SuperRep 3',    'Nike', 'Athletic',   '9',    'Photon Dust/White',  3200.00,  5295.00, 20, 5, 55,
 'Unisex', 'Designed for interval training, HIIT, and high-rep workouts. Heel clip for extra lockdown during high-intensity moves.'),

-- ── Basketball ────────────────────────────────────────────────────────────────
('Nike LeBron XXII',            'Nike', 'Athletic',   '11',   'Black/Gold',         6500.00, 11995.00,  8, 3, 30,
 'Men', 'LeBron James'' latest signature shoe. Features Nike Air Max cushioning and a supportive midfoot strap for explosive court performance.'),

('Nike Zoom Freak 5',           'Nike', 'Athletic',   '10',   'White/Volt',         4800.00,  8495.00, 10, 3, 35,
 'Men', 'Giannis Antetokounmpo''s signature shoe. Wide-based design with Zoom Air cushioning built for the Greek Freak''s powerful game.'),

('Nike Air Jordan 1 Retro High','Nike', 'Sneakers',   '10',   'Chicago (Red/White/Black)', 7000.00, 13995.00, 6, 2, 20,
 'Unisex', 'The shoe that started it all. The Air Jordan 1 Retro High OG in the iconic Chicago colorway — a grail for sneakerheads everywhere.');

-- ── Shifts ────────────────────────────────────────────────────────────────────
-- A shift is a work session for a cashier.
-- Starting a new shift does NOT log the cashier out.
-- Ending a shift records the end time — the cashier stays logged in and a new shift starts.
CREATE TABLE IF NOT EXISTS shifts (
    id           INT            AUTO_INCREMENT PRIMARY KEY,
    user_id      INT            NOT NULL,
    started_at   TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    ended_at     TIMESTAMP      NULL DEFAULT NULL,
    INDEX idx_user_id   (user_id),
    INDEX idx_started   (started_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Transactions ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS transactions (
    id             INT            AUTO_INCREMENT PRIMARY KEY,
    product_id     INT            NOT NULL,
    sold_by        INT            DEFAULT NULL,
    shift_id       INT            DEFAULT NULL,
    quantity_sold  INT            NOT NULL,
    unit_price     DECIMAL(10,2)  NOT NULL,
    unit_cost      DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    total_price    DECIMAL(10,2)  NOT NULL,
    total_cost     DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    status         ENUM('Sold','Refunded (Restocked)','Refunded (Damaged)') NOT NULL DEFAULT 'Sold',
    refund_reason  TEXT           DEFAULT NULL,
    sale_date      TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (sold_by)    REFERENCES users(id)    ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (shift_id)   REFERENCES shifts(id)   ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_product_id (product_id),
    INDEX idx_sold_by    (sold_by),
    INDEX idx_shift_id   (shift_id),
    INDEX idx_sale_date  (sale_date),
    INDEX idx_status     (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Stock Logs ────────────────────────────────────────────────────────────────
-- Tracks every stock addition (add_stock.php) for cashier report
CREATE TABLE IF NOT EXISTS stock_logs (
    id           INT            AUTO_INCREMENT PRIMARY KEY,
    product_id   INT            NOT NULL,
    added_by     INT            DEFAULT NULL,
    shift_id     INT            DEFAULT NULL,
    quantity     INT            NOT NULL,
    logged_at    TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (added_by)   REFERENCES users(id)    ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (shift_id)   REFERENCES shifts(id)   ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_sl_shift   (shift_id),
    INDEX idx_sl_user    (added_by),
    INDEX idx_sl_logged  (logged_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
