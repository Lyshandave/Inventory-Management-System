<?php
/**
 * products_sync.php
 * Returns current stock levels for all active products + best-seller ranks.
 * Used by the dashboard to keep the product table live across devices.
 */

require_once '../config.php';
require_login();
session_write_close(); // Release session lock — this endpoint is read-only

header('Content-Type: application/json');

$conn = db_connect();

// Stock levels for all non-archived products
$rows = $conn->query(
    'SELECT id, quantity FROM products WHERE is_archived = 0'
);
$stock = [];
while ($r = $rows->fetch_assoc()) {
    $stock[(int)$r['id']] = (int)$r['quantity'];
}

// Best-seller ranks (top 5 by units sold, active products only)
$bs = $conn->query(
    "SELECT p.id, COALESCE(SUM(t.quantity_sold), 0) AS total_sold
     FROM products p
     LEFT JOIN transactions t ON t.product_id = p.id AND t.status = 'Sold'
     WHERE p.is_archived = 0
     GROUP BY p.id
     ORDER BY total_sold DESC, p.product_name ASC
     LIMIT 5"
);
$bestSellers = [];
$rank = 0;
while ($r = $bs->fetch_assoc()) {
    if ((int)$r['total_sold'] > 0) {
        $bestSellers[(string)$r['id']] = ++$rank;
    }
}

$conn->close();

echo json_encode([
    'success'     => true,
    'stock'       => $stock,
    'bestSellers' => $bestSellers,
]);
