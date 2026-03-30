<?php
/**
 * archive_product.php
 * Archives or restores a product.
 * Accepts POST: product_id, action (archive|restore)
 */

require_once '../config.php';
require_login('admin');
session_write_close(); // Release session lock — session data is not modified here

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed.', 405);

$productId = valid_id($_POST['product_id'] ?? 0);
$action    = in_array($_POST['action'] ?? '', ['archive', 'restore'], true) ? $_POST['action'] : '';

if (!$productId) json_error('Invalid product ID.');
if (!$action)    json_error('Invalid action. Must be "archive" or "restore".');

$conn = db_connect();

$stmt = $conn->prepare('SELECT product_name, is_archived FROM products WHERE id = ?');
$stmt->bind_param('i', $productId);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    $conn->close();
    json_error('Product not found.');
}

$isArchived = (bool)$product['is_archived'];

if ($action === 'archive' && $isArchived)  { $conn->close(); json_error('Product is already archived.'); }
if ($action === 'restore' && !$isArchived) { $conn->close(); json_error('Product is not archived.'); }

$newState = $action === 'archive' ? 1 : 0;

$stmt = $conn->prepare('UPDATE products SET is_archived = ? WHERE id = ?');
$stmt->bind_param('ii', $newState, $productId);
$ok = $stmt->execute();
$err = $stmt->error;
$stmt->close();
$conn->close();

if (!$ok) json_error('Database error: ' . $err);

$verb = $action === 'archive' ? 'archived' : 'restored';
json_success("\"" . $product['product_name'] . "\" has been {$verb}.");
