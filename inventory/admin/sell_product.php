<?php
/**
 * sell_product.php
 * Records a sale and decrements stock.
 * Accepts POST: product_id, quantity
 */

require_once '../config.php';
require_login();

// Read session data BEFORE session_write_close() — after closing the write handle,
// session_init() inside current_user/current_shift_id would see PHP_SESSION_NONE
// and call session_start() again, re-acquiring the lock we just released.
$user    = current_user();
$shiftId = current_shift_id() ?: null;

session_write_close(); // Release session lock — all session reads are done above

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed.', 405);

$productId = valid_id($_POST['product_id'] ?? 0);
$quantity  = valid_qty($_POST['quantity']   ?? 0);

if (!$productId) json_error('Invalid product ID.');
if (!$quantity)  json_error('Quantity must be between 1 and 9999.');

$conn    = db_connect();
$conn->begin_transaction();

try {
    $stmt = $conn->prepare(
        'SELECT product_name, price, cost_price, quantity
         FROM products
         WHERE id = ? AND is_archived = 0
         FOR UPDATE'
    );
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$product) throw new RuntimeException('Product not found or is archived.');

    $stock     = (int)$product['quantity'];
    $price     = (float)$product['price'];
    $costPrice = (float)$product['cost_price'];

    if ($stock <= 0)        throw new RuntimeException('This product is out of stock.');
    if ($quantity > $stock) throw new RuntimeException("Insufficient stock. Only {$stock} unit(s) available.");

    $newStock   = $stock - $quantity;
    $totalPrice = round($quantity * $price,     2);
    $totalCost  = round($quantity * $costPrice, 2);

    $stmt = $conn->prepare('UPDATE products SET quantity = ?, updated_at = NOW() WHERE id = ?');
    $stmt->bind_param('ii', $newStock, $productId);
    if (!$stmt->execute()) throw new RuntimeException('Failed to update stock.');
    $stmt->close();

    $soldBy  = $user['id'] ?: null;

    $stmt = $conn->prepare(
        'INSERT INTO transactions
            (product_id, sold_by, shift_id, quantity_sold, unit_price, unit_cost, total_price, total_cost, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, "Sold")'
    );
    $stmt->bind_param('iiiidddd',
        $productId, $soldBy, $shiftId, $quantity,
        $price, $costPrice, $totalPrice, $totalCost
    );
    if (!$stmt->execute()) throw new RuntimeException('Failed to record transaction.');
    $stmt->close();

    $conn->commit();

    json_success(
        "Sold {$quantity} unit(s) of \"{$product['product_name']}\" for " . currency($totalPrice) . '.',
        ['new_stock' => $newStock, 'total' => $totalPrice]
    );

} catch (RuntimeException $e) {
    $conn->rollback();
    json_error($e->getMessage());
} finally {
    $conn->close();
}
