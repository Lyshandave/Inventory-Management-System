<?php
/**
 * cashier_report.php
 * Admin-only page. Shows per-cashier sales breakdown by shift and day.
 */

require_once '../config.php';
require_login('admin');
session_write_close(); // Release session lock — read-only AJAX

$conn = db_connect();

// ── AJAX mode — return shift detail for a specific shift ──────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'shift') {
    header('Content-Type: application/json');
    $shiftId = valid_id($_GET['shift_id'] ?? 0);
    if (!$shiftId) { $conn->close(); json_error('Invalid shift ID.'); }

    $stmt = $conn->prepare(
        "SELECT t.id, t.quantity_sold, t.unit_price, t.total_price, t.status, t.sale_date,
                p.product_name, p.brand, p.category
         FROM transactions t
         JOIN products p ON t.product_id = p.id
         WHERE t.shift_id = ?
         ORDER BY t.sale_date DESC"
    );
    $stmt->bind_param('i', $shiftId);
    $stmt->execute();
    $rows   = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();
    json_success('OK', ['transactions' => $rows]);
}

// ── Filter params ─────────────────────────────────────────────────────────────
$filterUser = valid_id($_GET['user_id'] ?? 0);
$filterDate = clean($_GET['date'] ?? date('Y-m-d'));

// ── All cashiers for the filter dropdown ──────────────────────────────────────
$cashiers = [];
$cRes = $conn->query(
    "SELECT id, full_name FROM users WHERE role='cashier' ORDER BY full_name"
);
while ($row = $cRes->fetch_assoc()) $cashiers[] = $row;

// ── Shifts matching the filter ────────────────────────────────────────────────
$where  = 'WHERE DATE(s.started_at) = ?';
$params = [$filterDate];
$types  = 's';

if ($filterUser > 0) {
    $where  .= ' AND s.user_id = ?';
    $params[] = $filterUser;
    $types   .= 'i';
}

$stmt = $conn->prepare(
    "SELECT s.id AS shift_id, s.started_at, s.ended_at,
            u.id AS user_id, u.full_name,
            COUNT(t.id)                                                          AS total_transactions,
            COALESCE(SUM(CASE WHEN t.status='Sold' THEN t.quantity_sold END),0) AS total_items,
            COALESCE(SUM(CASE WHEN t.status='Sold' THEN t.total_price  END),0)  AS total_revenue,
            COALESCE((SELECT SUM(sl.quantity) FROM stock_logs sl WHERE sl.shift_id = s.id),0) AS stock_added
     FROM shifts s
     JOIN users u ON u.id = s.user_id
     LEFT JOIN transactions t ON t.shift_id = s.id
     {$where}
     GROUP BY s.id, s.started_at, s.ended_at, u.id, u.full_name
     ORDER BY s.started_at DESC"
);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$shifts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Daily totals for selected date ────────────────────────────────────────────
$stmt2 = $conn->prepare(
    "SELECT u.full_name,
            COUNT(DISTINCT s.id)                                                  AS total_shifts,
            COALESCE(SUM(CASE WHEN t.status='Sold' THEN t.quantity_sold END),0)  AS total_items,
            COALESCE(SUM(CASE WHEN t.status='Sold' THEN t.total_price  END),0)   AS total_revenue,
            COALESCE((SELECT SUM(sl.quantity)
                      FROM stock_logs sl
                      JOIN shifts ss ON ss.id = sl.shift_id
                      WHERE ss.user_id = u.id
                        AND DATE(sl.logged_at) = ?),0)                           AS stock_added
     FROM shifts s
     JOIN users u ON u.id = s.user_id
     LEFT JOIN transactions t ON t.shift_id = s.id
     WHERE DATE(s.started_at) = ?
     GROUP BY u.id, u.full_name
     ORDER BY total_revenue DESC"
);
$stmt2->bind_param('ss', $filterDate, $filterDate);
$stmt2->execute();
$dailySummary = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1d4ed8">
    <title>Cashier Report — Shoes Inventory</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php include '_navbar.php'; ?>

<main class="main-container">

    <div class="page-header">
        <h1><i class="fas fa-user-clock page-header-icon" aria-hidden="true"></i> Cashier Report</h1>
        <p>View daily sales by cashier and shift.</p>
    </div>

    <!-- Filter Bar — auto-submits on date/cashier change -->
    <div class="card no-print">
        <div class="card-body">
            <form method="GET" id="reportFilterForm" class="report-filter-bar report-filter-centered">
                <div class="form-group">
                    <label class="form-label" for="date">Date</label>
                    <input type="date" id="date" name="date" class="form-control"
                           value="<?= h($filterDate) ?>"
                           data-action="auto-submit-report">
                </div>
                <div class="form-group">
                    <label class="form-label" for="user_id">Cashier</label>
                    <select id="user_id" name="user_id" class="form-control"
                            data-action="auto-submit-report">
                        <option value="">All Cashiers</option>
                        <?php foreach ($cashiers as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"
                                <?= $filterUser === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= h($c['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="report-filter-print">
                    <button type="button" class="btn btn-secondary btn-icon" data-action="print"
                            title="Print" aria-label="Print">
                        <i class="fas fa-print" aria-hidden="true"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Print-only header (hidden on screen) -->
    <div class="cashier-print-header">
        <div class="cashier-print-header-left">
            <div class="cashier-print-title">Shoes Inventory — Cashier Report</div>
            <div class="cashier-print-sub">
                <?= date('F d, Y', strtotime($filterDate)) ?>
                <?php if ($filterUser > 0):
                    $cashierName = '';
                    foreach ($cashiers as $c) {
                        if ((int)$c['id'] === $filterUser) { $cashierName = $c['full_name']; break; }
                    }
                ?>
                — <?= h($cashierName) ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="cashier-print-meta">
            Printed: <?= date('M d, Y h:i A') ?>
        </div>
    </div>

    <!-- Daily Summary (per cashier) -->
    <?php if ($dailySummary): ?>
    <div class="card cashier-report-section">
        <div class="card-header">
            <h2>
                <i class="fas fa-chart-bar" aria-hidden="true"></i>
                Daily Summary —
                <?= date('F d, Y', strtotime($filterDate)) ?>
            </h2>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Cashier</th>
                        <th scope="col" class="col-center">Shifts</th>
                        <th scope="col" class="col-center">Items Sold</th>
                        <th scope="col" class="col-center">Stock Added</th>
                        <th scope="col" class="col-right">Total Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dailySummary as $row): ?>
                    <tr>
                        <td><strong><?= h($row['full_name']) ?></strong></td>
                        <td class="col-center"><?= (int)$row['total_shifts'] ?></td>
                        <td class="col-center"><?= (int)$row['total_items'] ?></td>
                        <td class="col-center"><?= (int)$row['stock_added'] ?></td>
                        <td class="col-right price-cell"><?= currency((float)$row['total_revenue']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Shift Breakdown -->
    <div class="card cashier-report-section">
        <div class="card-header">
            <h2>Shift Breakdown</h2>
        </div>
        <?php if (empty($shifts)): ?>
        <div class="card-body">
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-calendar-times"></i></div>
                <p class="empty-title">No Shifts Found</p>
                <p class="empty-sub">No cashier shifts recorded for this date.</p>
            </div>
        </div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Cashier</th>
                        <th scope="col">Shift Start</th>
                        <th scope="col">Shift End</th>
                        <th scope="col" class="col-center">Transactions</th>
                        <th scope="col" class="col-center">Items Sold</th>
                        <th scope="col" class="col-center">Stock Added</th>
                        <th scope="col" class="col-right">Revenue</th>
                        <th scope="col">Status</th>
                        <th scope="col">Detail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shifts as $s): ?>
                    <tr>
                        <td><strong><?= h($s['full_name']) ?></strong></td>
                        <td><?= date('h:i A', strtotime($s['started_at'])) ?></td>
                        <td>
                            <?= $s['ended_at']
                                ? date('h:i A', strtotime($s['ended_at']))
                                : '<span class="badge badge-success">Active</span>' ?>
                        </td>
                        <td class="col-center"><?= (int)$s['total_transactions'] ?></td>
                        <td class="col-center"><?= (int)$s['total_items'] ?></td>
                        <td class="col-center"><?= (int)$s['stock_added'] ?></td>
                        <td class="col-right price-cell"><?= currency((float)$s['total_revenue']) ?></td>
                        <td>
                            <?php if ($s['ended_at']): ?>
                                <?php
                                $mins = round((strtotime($s['ended_at']) - strtotime($s['started_at'])) / 60);
                                $hrs  = floor($mins / 60);
                                $rem  = $mins % 60;
                                ?>
                                <span class="text-muted">
                                    <?= $hrs > 0 ? "{$hrs}h {$rem}m" : "{$rem}m" ?>
                                </span>
                            <?php else: ?>
                                <span class="badge badge-success">On shift</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-info btn-icon"
                                    data-action="view-shift"
                                    data-shift-id="<?= (int)$s['shift_id'] ?>"
                                    data-cashier="<?= h($s['full_name']) ?>"
                                    title="View Shift" aria-label="View Shift">
                                <i class="fas fa-list" aria-hidden="true"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</main>

<!-- Shift Detail Modal -->
<div class="modal-overlay hidden" id="shiftDetailModal" role="dialog" aria-modal="true" aria-labelledby="shiftDetailTitle">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 class="modal-title" id="shiftDetailTitle">Shift Transactions</h3>
            <button class="modal-close" data-action="close-modal" aria-label="Close">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <div class="modal-body" id="shiftDetailBody">
            <div class="loading-cell">
                <i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Loading...
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" data-action="close-modal">Close</button>
        </div>
    </div>
</div>

<script src="../assets/js/main.js"></script>
<script src="../assets/js/cashier_report.js"></script>
</body>
</html>
