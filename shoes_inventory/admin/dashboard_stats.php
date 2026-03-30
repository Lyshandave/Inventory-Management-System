<?php
/**
 * dashboard_stats.php
 * Returns live dashboard summary stats as JSON.
 */

require_once '../config.php';
require_login();

// Read session data BEFORE session_write_close() to avoid re-acquiring the lock.
$isAdmin = is_admin();
$shiftId = current_shift_id();

session_write_close(); // Release session lock — all session reads are done above

header('Content-Type: application/json');

$conn    = db_connect();

// ── Product stats ─────────────────────────────────────────────────────────────
$products = $conn->query(
    "SELECT
        COUNT(*)                                                           AS total_products,
        COALESCE(SUM(quantity), 0)                                         AS total_stock,
        COUNT(CASE WHEN quantity = 0 THEN 1 END)                           AS out_of_stock,
        COUNT(CASE WHEN quantity > 0 AND quantity <= min_stock THEN 1 END) AS low_stock
     FROM products WHERE is_archived = 0"
)->fetch_assoc();

$archived = (int)$conn->query(
    'SELECT COUNT(*) c FROM products WHERE is_archived = 1'
)->fetch_assoc()['c'];

// ── Transaction stats ─────────────────────────────────────────────────────────
// Admins see all-time totals; cashiers see current shift only
if ($isAdmin) {
    $txn = $conn->query(
        "SELECT
            COUNT(CASE WHEN status = 'Sold' THEN 1 END)                                     AS total_sales,
            COALESCE(SUM(CASE WHEN status = 'Sold' THEN quantity_sold END), 0)              AS total_items,
            COALESCE(SUM(CASE WHEN status = 'Sold' THEN total_price END), 0)                AS total_revenue,
            COALESCE(SUM(CASE WHEN status = 'Sold' THEN total_cost  END), 0)                AS total_cost,
            COALESCE(SUM(CASE WHEN status = 'Sold' THEN total_price - total_cost END), 0)   AS total_profit
         FROM transactions"
    )->fetch_assoc();
} else {
    $stmtTxn = $conn->prepare(
        "SELECT
            COUNT(CASE WHEN status = 'Sold' THEN 1 END)                                     AS total_sales,
            COALESCE(SUM(CASE WHEN status = 'Sold' THEN quantity_sold END), 0)              AS total_items,
            COALESCE(SUM(CASE WHEN status = 'Sold' THEN total_price END), 0)                AS total_revenue,
            COALESCE(SUM(CASE WHEN status = 'Sold' THEN total_cost  END), 0)                AS total_cost,
            COALESCE(SUM(CASE WHEN status = 'Sold' THEN total_price - total_cost END), 0)   AS total_profit
         FROM transactions
         WHERE shift_id = ?"
    );
    $stmtTxn->bind_param('i', $shiftId);
    $stmtTxn->execute();
    $txn = $stmtTxn->get_result()->fetch_assoc();
    $stmtTxn->close();
}

// ── Best sellers ──────────────────────────────────────────────────────────────
$bestSellerIds = [];
$bs = $conn->query(
    "SELECT p.id, COALESCE(SUM(t.quantity_sold), 0) AS total_sold
     FROM products p
     LEFT JOIN transactions t ON t.product_id = p.id AND t.status = 'Sold'
     WHERE p.is_archived = 0
     GROUP BY p.id ORDER BY total_sold DESC LIMIT 5"
);
$rank = 0;
while ($r = $bs->fetch_assoc()) {
    if ((int)$r['total_sold'] > 0) {
        $bestSellerIds[(string)$r['id']] = ++$rank;
    }
}

$conn->close();

$response = [
    'success'       => true,
    'totalProducts' => (int)$products['total_products'],
    'totalStock'    => (int)$products['total_stock'],
    'outOfStock'    => (int)$products['out_of_stock'],
    'lowStockCount' => (int)$products['low_stock'],
    'archivedCount' => $archived,
    'totalSales'    => (int)$txn['total_sales'],
    'totalItems'    => (int)$txn['total_items'],
    'totalRevenue'  => (float)$txn['total_revenue'],
    'bestSellerIds' => $bestSellerIds,
];

// Only expose profit data to admins
if ($isAdmin) {
    $response['totalProfit'] = (float)$txn['total_profit'];
}

echo json_encode($response);
