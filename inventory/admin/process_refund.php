<?php
/**
 * process_refund.php
 * Processes a full or partial refund for a sold transaction.
 * Accepts POST: transaction_id, condition (resellable|damaged), reason, refund_qty
 */

require_once '../config.php';
require_login();
session_write_close();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed.', 405);

$txnId     = valid_id($_POST['transaction_id'] ?? 0);
$condition = in_array($_POST['condition'] ?? '', ['resellable', 'damaged'], true) ? $_POST['condition'] : '';
$reason    = clean($_POST['reason'] ?? '');
$refundQty = valid_qty($_POST['refund_qty'] ?? 0, 1, 9999);

if (!$txnId)     json_error('Invalid transaction ID.');
if (!$condition) json_error('Please select the item condition.');

$conn = db_connect();
$conn->begin_transaction();

try {
    $stmt = $conn->prepare(
        'SELECT id, product_id, quantity_sold, unit_price, unit_cost,
                total_price, total_cost, shift_id, sold_by, status, sale_date
         FROM transactions
         WHERE id = ?
         FOR UPDATE'
    );
    $stmt->bind_param('i', $txnId);
    $stmt->execute();
    $txn = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$txn)                     throw new RuntimeException('Transaction not found.');
    if ($txn['status'] !== 'Sold') throw new RuntimeException('This transaction has already been refunded.');

    $saleDate      = new DateTime(date('Y-m-d', strtotime($txn['sale_date'])));
    $today         = new DateTime(date('Y-m-d'));
    $daysSinceSale = (int)$today->diff($saleDate)->days;
    if ($daysSinceSale > WARRANTY_DAYS) {
        throw new RuntimeException(
            'Warranty expired. Refunds are only accepted within ' . WARRANTY_DAYS . ' days of purchase.'
        );
    }

    $soldQty = (int)$txn['quantity_sold'];
    if (!$refundQty) $refundQty = $soldQty;
    if ($refundQty > $soldQty) {
        throw new RuntimeException("Cannot refund more than sold quantity ({$soldQty}).");
    }

    $isPartial = $refundQty < $soldQty;
    $newStatus = $condition === 'resellable' ? 'Refunded (Restocked)' : 'Refunded (Damaged)';
    $unitPrice = (float)$txn['unit_price'];
    $unitCost  = $soldQty > 0 ? (float)$txn['total_cost'] / $soldQty : 0.0;

    if ($isPartial) {
        $remainQty   = $soldQty - $refundQty;
        $remainTotal = $unitPrice * $remainQty;
        $remainCost  = $unitCost  * $remainQty;

        $stmt = $conn->prepare(
            'UPDATE transactions SET quantity_sold = ?, total_price = ?, total_cost = ? WHERE id = ?'
        );
        $stmt->bind_param('iddi', $remainQty, $remainTotal, $remainCost, $txnId);
        if (!$stmt->execute()) throw new RuntimeException('Failed to update original transaction.');
        $stmt->close();

        $refundTotal = $unitPrice * $refundQty;
        $refundCost  = $unitCost  * $refundQty;

        $stmt = $conn->prepare(
            'INSERT INTO transactions
             (product_id, sold_by, shift_id, quantity_sold, unit_price, unit_cost,
              total_price, total_cost, status, refund_reason, sale_date)
             SELECT product_id, sold_by, shift_id, ?, unit_price, ?,
                    ?, ?, ?, ?, sale_date
             FROM transactions WHERE id = ?'
        );
        $stmt->bind_param('idddssi',
            $refundQty, $unitCost,
            $refundTotal, $refundCost,
            $newStatus, $reason,
            $txnId
        );
        if (!$stmt->execute()) throw new RuntimeException('Failed to create refund record.');
        $stmt->close();

    } else {
        $stmt = $conn->prepare('UPDATE transactions SET status = ?, refund_reason = ? WHERE id = ?');
        $stmt->bind_param('ssi', $newStatus, $reason, $txnId);
        if (!$stmt->execute()) throw new RuntimeException('Failed to update transaction.');
        $stmt->close();
    }

    if ($condition === 'resellable') {
        $stmt = $conn->prepare('UPDATE products SET quantity = quantity + ?, updated_at = NOW() WHERE id = ?');
        $stmt->bind_param('ii', $refundQty, $txn['product_id']);
        if (!$stmt->execute()) throw new RuntimeException('Failed to restock product.');
        $stmt->close();
    }

    $conn->commit();

    $message = $condition === 'resellable'
        ? "Refund processed. {$refundQty} unit(s) have been restocked."
        : "Refund processed. {$refundQty} unit(s) marked as damaged — stock not restored.";

    json_success($message, ['new_status' => $newStatus, 'is_partial' => $isPartial]);

} catch (RuntimeException $e) {
    $conn->rollback();
    json_error($e->getMessage());
} finally {
    $conn->close();
}
