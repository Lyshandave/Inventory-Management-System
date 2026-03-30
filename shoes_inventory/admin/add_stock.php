<?php
/**
 * add_stock.php
 * Adds inventory to an existing active product.
 * Accepts POST: product_id, quantity
 */

require_once '../config.php';
require_login();
$_currentUser  = current_user();
$_currentShift = current_shift_id();
session_write_close(); // Release session lock — session data is not modified here

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed.', 405);

$productId = valid_id($_POST['product_id'] ?? 0);
$quantity  = valid_qty($_POST['quantity']   ?? 0);

if (!$productId) json_error('Invalid product ID.');
if (!$quantity)  json_error('Quantity must be between 1 and 9999.');

$conn = db_connect();
$conn->begin_transaction();

try {
    $stmt = $conn->prepare(
        'SELECT product_name, quantity, max_stock
         FROM products
         WHERE id = ? AND is_archived = 0
         FOR UPDATE'
    );
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$product) throw new RuntimeException('Product not found or is archived.');

    $currentStock = (int)$product['quantity'];
    $maxStock     = (int)$product['max_stock'];
    $newStock     = $currentStock + $quantity;

    if ($newStock > $maxStock) {
        $canAdd = $maxStock - $currentStock;
        if ($canAdd <= 0) {
            throw new RuntimeException("Stock is already at the maximum ({$maxStock} units). Edit the product to raise the limit.");
        }
        throw new RuntimeException("Cannot add {$quantity} unit(s). Only {$canAdd} more unit(s) can be added before hitting the maximum ({$maxStock}).");
    }

    $stmt = $conn->prepare('UPDATE products SET quantity = ?, updated_at = NOW() WHERE id = ?');
    $stmt->bind_param('ii', $newStock, $productId);
    if (!$stmt->execute()) throw new RuntimeException('Failed to update stock.');
    $stmt->close();

    // Log the stock addition for cashier reports
    $addedBy = $_currentUser['id'] ?: null;
    $shiftId = $_currentShift ?: null;
    $logStmt = $conn->prepare('INSERT INTO stock_logs (product_id, added_by, shift_id, quantity) VALUES (?, ?, ?, ?)');
    $logStmt->bind_param('iiii', $productId, $addedBy, $shiftId, $quantity);
    $logStmt->execute();
    $logStmt->close();

    $conn->commit();

    json_success("Added {$quantity} unit(s) to \"{$product['product_name']}\".", [
        'new_stock' => $newStock,
    ]);

} catch (RuntimeException $e) {
    $conn->rollback();
    json_error($e->getMessage());
} finally {
    $conn->close();
}
