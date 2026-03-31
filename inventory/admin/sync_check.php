<?php
/**
 * sync_check.php
 * Long-poll endpoint for real-time cross-device sync.
 *
 * GET ?since=0  → returns current server timestamp as baseline (no hold)
 * GET ?since=TS → holds up to 25s, responds instantly when data changes
 *
 * Detects changes via:
 *   products.updated_at     → stock add, edit, archive/restore
 *   transactions.updated_at → new sale OR refund (falls back to sale_date
 *                             if updated_at column doesn't exist yet)
 */

require_once '../config.php';
require_login();

// ── CRITICAL: release the session file lock immediately ───────────────────────
// PHP file-based sessions lock the session file for the entire request duration.
// Without this, the 25-second long-poll would block ALL other page requests
// (navigation, button clicks, AJAX calls) from the same browser tab/user
// because they all wait for the session lock to be released.
session_write_close();

header('Content-Type: application/json');
header('Cache-Control: no-store');

$since = isset($_GET['since']) ? (float)$_GET['since'] : 0;

// First call (since=0): return server clock baseline so JS never uses browser clock.
if ($since <= 0) {
    $conn = db_connect();
    $ts   = (float)$conn->query('SELECT UNIX_TIMESTAMP() ts')->fetch_assoc()['ts'];
    $conn->close();
    echo json_encode(['changed' => false, 'ts' => $ts]);
    exit;
}

// Open a single persistent connection for the entire long-poll loop.
// Previously a new connection was opened and closed on every 100ms iteration,
// which is wasteful and unnecessary — mysqli connections are cheap to reuse.
$conn = db_connect();

// Detect whether transactions.updated_at exists (migration may not have run yet).
$hasUpdatedAt = false;
$colCheck = $conn->query(
    "SELECT 1 FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'transactions'
       AND COLUMN_NAME  = 'updated_at'
     LIMIT 1"
);
if ($colCheck && $colCheck->num_rows > 0) {
    $hasUpdatedAt = true;
}

$txnTsCol = $hasUpdatedAt ? 'updated_at' : 'sale_date';
$deadline = microtime(true) + 25;
$changed  = false;
$newTs    = $since;

$sql = "SELECT GREATEST(
            COALESCE((SELECT UNIX_TIMESTAMP(MAX(updated_at)) FROM products),      0),
            COALESCE((SELECT UNIX_TIMESTAMP(MAX({$txnTsCol})) FROM transactions), 0)
         ) AS latest_ts";

while (microtime(true) < $deadline) {
    $result   = $conn->query($sql);
    $latestTs = $result ? (float)($result->fetch_assoc()['latest_ts'] ?? 0) : 0;

    if ($latestTs > $since) {
        $changed = true;
        $newTs   = $latestTs;
        break;
    }

    usleep(100000); // 100ms between checks — near-instant cross-device updates
}

$conn->close();

echo json_encode(['changed' => $changed, 'ts' => $newTs]);
