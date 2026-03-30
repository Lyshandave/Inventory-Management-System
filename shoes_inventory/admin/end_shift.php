<?php
/**
 * end_shift.php
 * Ends the cashier's current shift and immediately starts a new one.
 * Returns: shift summary (transactions, items, revenue, time-out) as JSON.
 */

require_once '../config.php';
require_login();

// The shift_id in $_SESSION is read here, then a NEW shift_id is written back.
// session_write_close() must come AFTER we read the old shift id but we still
// need to write the new shift_id into the session, so we cannot close the
// session early on this endpoint. Session stays open intentionally.

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed.', 405);

$user       = current_user();
$oldShiftId = current_shift_id();

if ($oldShiftId <= 0) {
    json_error('No active shift found.');
}

// ── Close the current shift ───────────────────────────────────────────────────
end_shift($oldShiftId);

// ── Fetch summary + ended_at for the closed shift ─────────────────────────────
$conn = db_connect();

$stmt = $conn->prepare(
    "SELECT
        s.ended_at,
        COUNT(t.id)                                                               AS total_transactions,
        COALESCE(SUM(CASE WHEN t.status = 'Sold' THEN t.quantity_sold ELSE 0 END), 0) AS total_items,
        COALESCE(SUM(CASE WHEN t.status = 'Sold' THEN t.total_price  ELSE 0 END), 0) AS total_revenue
     FROM shifts s
     LEFT JOIN transactions t ON t.shift_id = s.id
     WHERE s.id = ?
     GROUP BY s.id"
);
$stmt->bind_param('i', $oldShiftId);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

$endedAt = $summary['ended_at'] ?? date('Y-m-d H:i:s');

// ── If called from the logout flow, don't start a new shift ──────────────────
$isLogout = !empty($_POST['logout']);

if ($isLogout) {
    // Cashier is logging out — clear shift from session, no new shift created
    unset($_SESSION['shift_id']);

    json_success('Shift ended.', [
        'ended_shift'        => $oldShiftId,
        'new_shift'          => null,
        'ended_at'           => $endedAt,
        'total_transactions' => (int)$summary['total_transactions'],
        'total_items'        => (int)$summary['total_items'],
        'total_revenue'      => (float)$summary['total_revenue'],
    ]);
}

// ── Start a fresh shift — cashier stays logged in ─────────────────────────────
$newShiftId           = start_shift((int)$user['id']);
$_SESSION['shift_id'] = $newShiftId;

json_success('Shift ended. A new shift has started.', [
    'ended_shift'        => $oldShiftId,
    'new_shift'          => $newShiftId,
    'ended_at'           => $endedAt,
    'total_transactions' => (int)$summary['total_transactions'],
    'total_items'        => (int)$summary['total_items'],
    'total_revenue'      => (float)$summary['total_revenue'],
]);
