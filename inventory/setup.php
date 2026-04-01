<?php
/**
 * setup.php
 * Run this ONCE to create the database tables and default user accounts.
 * Access via: http://localhost/shoes_inventory/setup.php
 *
 * SECURITY: Delete or rename this file after initial setup in production.
 */

// ── Production guard ──────────────────────────────────────────────────────────
// Prevent accidental re-run on live servers.
// Remove this block (or the file entirely) only when running initial setup.
if (file_exists(__DIR__ . '/uploads/.setup_done')) {
    http_response_code(403);
    die('<h1>403 Forbidden</h1><p>Setup has already been completed. Delete this file or the <code>uploads/.setup_done</code> flag to re-run.</p>');
}

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'shoes_inventory');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
if ($conn->connect_error) {
    die('<pre>Connection failed: ' . htmlspecialchars($conn->connect_error) . '</pre>');
}
$conn->set_charset('utf8mb4');

$steps  = [];
$errors = [];

function run(mysqli $conn, string $sql, string $label, array &$steps, array &$errors): void
{
    if ($conn->query($sql)) {
        $steps[] = '✅ ' . $label;
    } else {
        $errors[] = '❌ ' . $label . ': ' . $conn->error;
    }
}

// ── Create database ───────────────────────────────────────────────────────────
run($conn,
    "CREATE DATABASE IF NOT EXISTS shoes_inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
    'Database created', $steps, $errors
);
run($conn, "USE shoes_inventory", 'Database selected', $steps, $errors);

// ── Users table ───────────────────────────────────────────────────────────────
run($conn, "
CREATE TABLE IF NOT EXISTS users (
    id           INT            AUTO_INCREMENT PRIMARY KEY,
    username     VARCHAR(60)    NOT NULL UNIQUE,
    password     VARCHAR(255)   NOT NULL,
    full_name    VARCHAR(120)   NOT NULL,
    role         ENUM('admin','cashier') NOT NULL DEFAULT 'cashier',
    is_active    TINYINT(1)     NOT NULL DEFAULT 1,
    created_at   TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
", 'Users table', $steps, $errors);

// ── Products table ────────────────────────────────────────────────────────────
run($conn, "
CREATE TABLE IF NOT EXISTS products (
    id           INT            AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(255)   NOT NULL,
    brand        VARCHAR(100)   NOT NULL,
    category     VARCHAR(100)   NOT NULL,
    size         VARCHAR(20)    NOT NULL,
    color        VARCHAR(50)    NOT NULL,
    cost_price   DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    price        DECIMAL(10,2)  NOT NULL,
    quantity     INT            NOT NULL DEFAULT 0,
    min_stock    INT            NOT NULL DEFAULT 5,
    max_stock    INT            NOT NULL DEFAULT 100,
    description  TEXT,
    image        VARCHAR(255)   DEFAULT NULL,
    is_archived  TINYINT(1)     NOT NULL DEFAULT 0,
    created_at   TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_archived (is_archived),
    INDEX idx_category (category),
    INDEX idx_brand    (brand)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
", 'Products table', $steps, $errors);

// ── Shifts table ─────────────────────────────────────────────────────────────
run($conn, "
CREATE TABLE IF NOT EXISTS shifts (
    id            INT            AUTO_INCREMENT PRIMARY KEY,
    user_id       INT            NOT NULL,
    started_at    TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    ended_at      TIMESTAMP      NULL DEFAULT NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_started (started_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
", 'Shifts table', $steps, $errors);

// ── Transactions table ────────────────────────────────────────────────────────
run($conn, "
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
", 'Transactions table', $steps, $errors);

// ── Migration: add updated_at to transactions if it doesn't exist ─────────────
run($conn, "
ALTER TABLE transactions
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
", 'Transactions: add updated_at (migration)', $steps, $errors);

// ── Default users (only if table is empty) ────────────────────────────────────
$count = (int)$conn->query('SELECT COUNT(*) c FROM users')->fetch_assoc()['c'];
if ($count === 0) {
    $adminHash   = password_hash('admin123',   PASSWORD_BCRYPT, ['cost' => 12]);
    $cashierHash = password_hash('cashier123', PASSWORD_BCRYPT, ['cost' => 12]);

    $stmt = $conn->prepare(
        'INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)'
    );
    $pairs = [
        ['admin',   $adminHash,   'Store Administrator', 'admin'],
        ['cashier', $cashierHash, 'Store Cashier',       'cashier'],
    ];
    foreach ($pairs as [$user, $hash, $name, $role]) {
        $stmt->bind_param('ssss', $user, $hash, $name, $role);
        if ($stmt->execute()) {
            $steps[]  = "✅ Default user created: {$user}";
        } else {
            $errors[] = "❌ Failed to create user {$user}: " . $stmt->error;
        }
    }
    $stmt->close();
} else {
    $steps[] = 'ℹ️ Users already exist — skipped default user creation.';
}

// ── Sample Products — Nike Philippines (only if products table is empty) ───────
$productCount = (int)$conn->query('SELECT COUNT(*) c FROM products')->fetch_assoc()['c'];
if ($productCount === 0) {
    $sampleProducts = [
        // Running
        ['Nike Air Zoom Pegasus 40',     'Nike','Running',    '9',  'Black/White',              3800.00,  5495.00, 25, 5, 60, 'Men',    'The iconic Nike Air Zoom Pegasus 40 delivers versatile everyday running performance. Features Zoom Air cushioning for a responsive, snappy ride.'],
        ['Nike Air Zoom Pegasus 40',     'Nike','Running',    '7',  'Pink/White',               3800.00,  5495.00, 18, 5, 50, 'Women',  'Womens Nike Air Zoom Pegasus 40 in bold Pink/White. Trusted by runners for its cushioned, responsive feel on every run.'],
        ['Nike Invincible Run 3',        'Nike','Running',   '10',  'White/Pure Platinum',      4500.00,  7995.00, 12, 3, 40, 'Men',    'Maximal cushioning for easy days. Features ZoomX foam for a super-soft, bouncy ride that keeps your legs fresh.'],
        ['Nike React Infinity Run FK 3', 'Nike','Running',    '8',  'Thunder Blue/White',       4200.00,  6995.00, 15, 5, 50, 'Men',    'Designed to help reduce injury with a rocker-shaped sole and soft React foam. Keeps you running comfortably.'],
        ['Nike Free RN 5.0 Next Nature', 'Nike','Running',  '7.5',  'Pale Ivory/Melon',         2800.00,  4495.00, 20, 5, 55, 'Women',  'A barefoot-inspired design using at least 20% recycled material. The flexible outsole moves with your foot for a natural feel.'],
        // Casual
        ["Nike Air Force 1 '07",         'Nike','Casual',   '10',  'White/White',              3200.00,  5295.00, 30, 8, 80, 'Men',    'The radiance lives on in the Nike Air Force 1 07. Classic leather upper with Air cushioning. A timeless icon of street style.'],
        ["Nike Air Force 1 '07",         'Nike','Casual',    '7',  'White/White',              3200.00,  5295.00, 28, 8, 70, 'Women',  "Women's Nike Air Force 1 07 in iconic all-white. The classic basketball-inspired silhouette made for everyday street wear."],
        ["Nike Air Force 1 '07",         'Nike','Casual',    '9',  'Black/Black',              3200.00,  5295.00, 22, 5, 60, 'Unisex', 'The all-black AF1 — sleek, bold, and versatile. Premium leather upper with Air cushioning for all-day comfort.'],
        ['Nike Cortez',                  'Nike','Casual',    '9',  'White/University Red',     2600.00,  3995.00, 20, 5, 50, 'Unisex', "Nike's original running shoe is back as a retro lifestyle icon. Features a classic leather upper and chunky sole."],
        ["Nike Blazer Mid '77",          'Nike','Casual',   '10',  'White/Black',              3000.00,  4895.00, 18, 5, 50, 'Men',    'Vintage basketball style meets everyday comfort. High-top silhouette with retro branding.'],
        // Sneakers
        ['Nike Air Max 270',             'Nike','Sneakers', '10',  'Black/Anthracite',         4000.00,  6995.00, 15, 5, 45, 'Men',    "Nike's first lifestyle Air unit — the tallest heel Air bag yet. The Air Max 270 delivers all-day comfort with a bold, modern look."],
        ['Nike Air Max 90',              'Nike','Sneakers',  '8',  'White/Wolf Grey',          3800.00,  6495.00, 18, 5, 50, 'Men',    'Nothing as fly, nothing as comfortable, nothing as proven. The Air Max 90 keeps a fresh face with the same classic Max Air cushioning.'],
        ['Nike Air Max 97',              'Nike','Sneakers',  '9',  'Silver Bullet',            4500.00,  7995.00, 10, 3, 35, 'Unisex', 'Inspired by Japanese bullet trains. Features full-length Air cushioning and reflective piping. A collector\'s classic.'],
        ['Nike Dunk Low Retro',          'Nike','Sneakers',  '9',  'Panda (Black/White)',      3500.00,  5995.00, 12, 5, 40, 'Unisex', 'Created for the hardwood but later taken to the streets. Returns with classic colors and clean leather overlays.'],
        ['Nike Air Jordan 1 Retro High', 'Nike','Sneakers', '10',  'Chicago (Red/White/Black)',7000.00, 13995.00,  6, 2, 20, 'Unisex', 'The shoe that started it all. The Air Jordan 1 Retro High OG in the iconic Chicago colorway.'],
        // Athletic / Training
        ['Nike Metcon 8',                'Nike','Athletic',  '10',  'Black/Volt',              4200.00,  6495.00, 15, 5, 45, 'Men',    'Built for the hardest workouts. Features a stable heel for lifting and a flexible forefoot for sprints and jumps.'],
        ['Nike Free Metcon 5',           'Nike','Athletic',   '7',  'White/Pink Spell',        3600.00,  5995.00, 12, 4, 40, 'Women',  'Versatile training shoe designed for dynamic movement. Pairs flexibility with stability for any gym session.'],
        ['Nike Air Zoom SuperRep 3',     'Nike','Athletic',   '9',  'Photon Dust/White',       3200.00,  5295.00, 20, 5, 55, 'Unisex', 'Designed for interval training, HIIT, and high-rep workouts. Heel clip for extra lockdown during high-intensity moves.'],
        // Basketball
        ['Nike LeBron XXII',             'Nike','Athletic',  '11',  'Black/Gold',             6500.00, 11995.00,  8, 3, 30, 'Men',    "LeBron James' latest signature shoe. Features Nike Air Max cushioning and a supportive midfoot strap for explosive court performance."],
        ['Nike Zoom Freak 5',            'Nike','Athletic',  '10',  'White/Volt',             4800.00,  8495.00, 10, 3, 35, 'Men',    "Giannis Antetokounmpo's signature shoe. Wide-based design with Zoom Air cushioning built for the Greek Freak's powerful game."],
    ];

    $stmtP = $conn->prepare(
        'INSERT INTO products (product_name, brand, category, size, color, cost_price, price, quantity, min_stock, max_stock, gender, description)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $inserted = 0;
    foreach ($sampleProducts as $p) {
        $stmtP->bind_param(
            'sssssddiiss',
            $p[0], $p[1], $p[2], $p[3], $p[4],
            $p[5], $p[6], $p[7], $p[8], $p[9],
            $p[10], $p[11]
        );
        if ($stmtP->execute()) {
            $inserted++;
        } else {
            $errors[] = 'Failed to insert product "' . htmlspecialchars($p[0]) . '": ' . $stmtP->error;
        }
    }
    $stmtP->close();

    if ($inserted > 0) {
        $steps[] = "✅ {$inserted} Nike Philippines sample products inserted.";
    }
} else {
    $steps[] = 'ℹ️ Products already exist — skipped sample product insertion.';
}

// ── Create uploads folder ─────────────────────────────────────────────────────
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
    $steps[] = '✅ Uploads folder created.';
} else {
    $steps[] = 'ℹ️ Uploads folder already exists.';
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup — Shoes Inventory</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 560px; margin: 3rem auto; padding: 0 1rem; color: #111; }
        h1   { font-size: 1.5rem; margin-bottom: 1.5rem; }
        ul   { list-style: none; padding: 0; }
        li   { padding: .4rem 0; font-size: .95rem; border-bottom: 1px solid #f0f0f0; }
        .box { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: .75rem; padding: 1.5rem; margin-bottom: 1.5rem; }
        .err { background: #fee2e2; border-color: #fca5a5; }
        .creds { background: #dbeafe; border: 1px solid #93c5fd; border-radius: .5rem; padding: 1rem; font-size: .9rem; margin: 1rem 0; }
        .creds table { width: 100%; border-collapse: collapse; }
        .creds td { padding: .3rem .5rem; }
        .creds td:first-child { font-weight: 700; width: 100px; }
        a.btn { display: inline-block; margin-top: 1rem; padding: .6rem 1.5rem; background: #1d4ed8; color: #fff; border-radius: .5rem; text-decoration: none; font-weight: 600; }
        .warning { color: #dc2626; font-size: .85rem; margin-top: 1rem; font-weight: 600; }
    </style>
</head>
<body>
    <h1>🚀 Shoes Inventory — Setup</h1>

    <?php if (!empty($errors)): ?>
    <div class="box err">
        <strong>Errors occurred:</strong>
        <ul>
            <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="box">
        <strong>Setup results:</strong>
        <ul>
            <?php foreach ($steps as $s): ?><li><?= htmlspecialchars($s) ?></li><?php endforeach; ?>
        </ul>
    </div>

    <?php if (empty($errors)): ?>
    <?php @file_put_contents(__DIR__ . '/uploads/.setup_done', date('Y-m-d H:i:s')); ?>
    <p>✅ <strong>Setup complete!</strong> Default login credentials:</p>
    <div class="creds">
        <table>
            <tr><td>Admin:</td>   <td>username: <strong>admin</strong> &nbsp; password: <strong>admin123</strong></td></tr>
            <tr><td>Cashier:</td> <td>username: <strong>cashier</strong> &nbsp; password: <strong>cashier123</strong></td></tr>
        </table>
    </div>
    <a class="btn" href="admin/admin.php">Go to Dashboard →</a>
    <p class="warning">⚠️ Delete or rename this file (setup.php) after setup is complete.</p>
    <?php endif; ?>
</body>
</html>
