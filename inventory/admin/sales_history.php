<?php
/**
 * sales_history.php
 * Shows sales history with AJAX-powered range filtering.
 * PHP renders summary stat cards instantly. JS fills the table via AJAX.
 */

require_once '../config.php';
require_login('admin');
session_write_close(); // Release session lock — AJAX requests are read-only

// ── AJAX mode — return filtered sales data as JSON ────────────────────────────
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode(fetch_sales_data());
    exit;
}

// ── Page load — PHP renders everything instantly (no flash on reload) ────────
// fetch_sales_data() returns both stats AND table rows for "all time" range.
// JS only takes over when switching range tabs (Today / Week / Month / Custom).
$initial     = fetch_sales_data();   // range defaults to 'all' via $_GET fallback

$initSales   = (int)$initial['totalSales'];
$initRecords = (int)$initial['totalRecords'];
$initItems   = (int)$initial['totalItems'];
$initRev     = (float)$initial['totalRevenue'];
$initRefund  = (float)$initial['totalRefund'];
$initNet     = $initRev - $initRefund;
$initProfit  = (float)$initial['totalProfit'];
$initRows    = $initial['sales'];   // full transaction rows for the table
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1d4ed8">
    <title>Sales History — Shoes Inventory</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body data-sales-url="sales_history.php">

<?php include '_navbar.php'; ?>

<main class="main-container">

    <div class="page-header">
        <h1><i class="fas fa-chart-line page-header-icon" aria-hidden="true"></i> Sales History</h1>
        <p>Track all transactions, revenue, and refunds.</p>
    </div>

    <!-- Summary Card + Range Tabs -->
    <div class="card no-print">
        <div class="card-header">
            <h2>Sales Summary</h2>
            <div class="range-tabs" role="tablist">
                <button class="range-tab active" role="tab" aria-selected="true"  data-range="all">All Time</button>
                <button class="range-tab"         role="tab" aria-selected="false" data-range="today">Today</button>
                <button class="range-tab"         role="tab" aria-selected="false" data-range="week">This Week</button>
                <button class="range-tab"         role="tab" aria-selected="false" data-range="month">This Month</button>
                <button class="range-tab"         role="tab" aria-selected="false" data-range="custom">Custom</button>
            </div>
        </div>
        <div class="card-body">

            <!-- Custom date picker (hidden until Custom tab selected) -->
            <div id="customDateRow" class="custom-date-row hidden">
                <div class="custom-date-inputs">
                    <div class="form-group">
                        <label class="form-label" for="startDate">Start Date</label>
                        <input type="date" id="startDate" class="form-control" data-action="auto-custom-range">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="endDate">End Date</label>
                        <input type="date" id="endDate" class="form-control" data-action="auto-custom-range">
                    </div>
                </div>
            </div>

            <!-- Stats (PHP-rendered for instant display, JS updates on range change) -->
            <div class="summary-cards">
                <div class="summary-card">
                    <div class="summary-card-icon icon-blue" aria-hidden="true"><i class="fas fa-receipt"></i></div>
                    <div class="summary-card-body">
                        <h2 class="summary-card-label">Total Sales</h2>
                        <p class="summary-card-value" id="statTotalSales"><?= number_format($initSales) ?></p>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-card-icon icon-green" aria-hidden="true"><i class="fas fa-shoe-prints"></i></div>
                    <div class="summary-card-body">
                        <h2 class="summary-card-label">Items Sold</h2>
                        <p class="summary-card-value" id="statItemsSold"><?= number_format($initItems) ?></p>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-card-icon icon-purple" aria-hidden="true"><i class="fas fa-peso-sign"></i></div>
                    <div class="summary-card-body">
                        <h2 class="summary-card-label">Total Revenue</h2>
                        <p class="summary-card-value" id="statRevenue"><?= currency_compact($initRev) ?></p>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-card-icon icon-red" aria-hidden="true"><i class="fas fa-undo"></i></div>
                    <div class="summary-card-body">
                        <h2 class="summary-card-label">Refunds</h2>
                        <p class="summary-card-value" id="statRefund"><?= currency_compact($initRefund) ?></p>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-card-icon icon-teal" aria-hidden="true"><i class="fas fa-chart-line"></i></div>
                    <div class="summary-card-body">
                        <h2 class="summary-card-label">Net Revenue</h2>
                        <p class="summary-card-value" id="statNet"><?= currency_compact($initNet) ?></p>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-card-icon icon-green" aria-hidden="true"><i class="fas fa-coins"></i></div>
                    <div class="summary-card-body">
                        <h2 class="summary-card-label">Net Profit</h2>
                        <p class="summary-card-value" id="statProfit"><?= currency_compact($initProfit) ?></p>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Transactions Table -->
    <div class="card">
        <div class="card-header">
            <h2>
                Transaction Records
                <span class="badge badge-info" id="recordCount"><?= number_format($initRecords) ?> records</span>
            </h2>
            <button class="btn btn-secondary no-print" data-action="print">
                <i class="fas fa-print" aria-hidden="true"></i> Print
            </button>
        </div>

        <!-- Print header (hidden on screen, shown when printing) -->
        <div class="print-header">
            <div class="print-header-left">
                <div class="print-title">Shoes Inventory — Sales Report</div>
                <div class="print-range" id="printRange"></div>
            </div>
            <div class="print-meta">Printed: <span id="printDate"></span></div>
        </div>

        <!-- Print summary stats row (hidden on screen) -->
        <div class="print-summary hidden" id="printSummary">
            <div class="print-sum-item"><span>Total Sales</span>   <strong id="pSales"></strong></div>
            <div class="print-sum-item"><span>Items Sold</span>    <strong id="pItems"></strong></div>
            <div class="print-sum-item"><span>Total Revenue</span> <strong id="pRevenue"></strong></div>
            <div class="print-sum-item"><span>Refunds</span>       <strong id="pRefund"></strong></div>
            <div class="print-sum-item"><span>Net Revenue</span>   <strong id="pNet"></strong></div>
            <div class="print-sum-item"><span>Net Profit</span>    <strong id="pProfit"></strong></div>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Date &amp; Time</th>
                        <th scope="col">Product</th>
                        <th scope="col">Brand</th>
                        <th scope="col">Category</th>
                        <th scope="col" class="col-center">Qty</th>
                        <th scope="col">Unit Price</th>
                        <th scope="col">Total</th>
                        <th scope="col">Profit</th>
                        <th scope="col">Sold By</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="no-print">Action</th>
                    </tr>
                </thead>
                <tbody id="salesBody">
                    <?php if (empty($initRows)): ?>
                    <tr id="salesEmptyRow">
                        <td colspan="11">
                            <div class="empty-state">
                                <div class="empty-icon"><i class="fas fa-receipt"></i></div>
                                <p class="empty-title">No Transactions Yet</p>
                                <p class="empty-sub">Sales will appear here once products are sold.</p>
                            </div>
                        </td>
                    </tr>
                    <?php else: foreach ($initRows as $row):
                        $isSold   = $row['status'] === 'Sold';
                        $profit   = $isSold ? (float)$row['total_price'] - (float)$row['total_cost'] : null;
                        $profCls  = $profit === null ? '' : ($profit > 0 ? 'profit-positive' : ($profit < 0 ? 'profit-negative' : 'profit-zero'));
                        $profStr  = $profit !== null ? currency($profit) : '—';
                        $statusBadge = match($row['status']) {
                            'Sold'                   => '<span class="badge badge-success">Sold</span>',
                            'Refunded (Restocked)'   => '<span class="badge badge-warning">Refunded</span>',
                            default                  => '<span class="badge badge-danger">Refunded</span>',
                        };
                        $saleDate = date('M j, Y, h:i A', strtotime($row['sale_date']));
                        $soldBy   = h($row['sold_by_name'] ?? '—');
                        $canRefund = $isSold;
                    ?>
                    <tr>
                        <td><?= $saleDate ?></td>
                        <td><strong><?= h($row['product_name']) ?></strong></td>
                        <td><?= h($row['brand']) ?></td>
                        <td><?= h($row['category']) ?></td>
                        <td class="col-center"><?= (int)$row['quantity_sold'] ?></td>
                        <td><?= currency((float)$row['unit_price']) ?></td>
                        <td><?= currency((float)$row['total_price']) ?></td>
                        <td class="<?= $profCls ?>"><?= $profStr ?></td>
                        <td class="text-muted"><?= $soldBy ?></td>
                        <td><?= $statusBadge ?></td>
                        <td class="no-print">
                            <?php if ($canRefund): ?>
                            <button class="btn btn-sm btn-warning btn-icon" data-action="open-refund"
                                    data-id="<?= (int)$row['id'] ?>"
                                    data-name="<?= h($row['product_name']) ?>"
                                    data-qty="<?= (int)$row['quantity_sold'] ?>"
                                    data-price="<?= h($row['unit_price']) ?>"
                                    data-date="<?= h($row['sale_date']) ?>"
                                    title="Refund" aria-label="Refund">
                                <i class="fas fa-undo" aria-hidden="true"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>

            </table>
        </div>

        <!-- Print footer (hidden on screen) -->
        <div class="print-footer hidden" id="printFooter">
            <div class="print-footer-grid">
                <div class="print-foot-cell">
                    <span class="print-foot-label">Total Transactions</span>
                    <span class="print-foot-value" id="pfSales"></span>
                </div>
                <div class="print-foot-cell">
                    <span class="print-foot-label">Items Sold</span>
                    <span class="print-foot-value" id="pfItems"></span>
                </div>
                <div class="print-foot-cell">
                    <span class="print-foot-label">Net Revenue</span>
                    <span class="print-foot-value" id="pfRevenue"></span>
                </div>
            </div>
        </div>
    </div>

</main>

<!-- ── Refund Modal ─────────────────────────────────────────────────────────── -->
<div class="modal-overlay hidden" id="refundModal" role="dialog" aria-modal="true" aria-labelledby="refundModalTitle">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="refundModalTitle">Process Refund</h3>
            <button class="modal-close" data-action="close-modal" aria-label="Close">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <div class="modal-body">
            <dl class="info-list refund-info-list">
                <dt>Product</dt>    <dd id="refundProduct"></dd>
                <dt>Quantity</dt>
                <dd>
                    <div class="refund-qty-row">
                        <div class="qty-stepper qty-stepper--sm">
                            <button type="button" class="qty-btn" data-action="step-qty" data-target="refundQtyInput" data-delta="-1" aria-label="Decrease"><i class="fas fa-minus" aria-hidden="true"></i></button>
                            <input type="number" id="refundQtyInput" class="qty-input" value="1" min="1" max="1" aria-label="Refund quantity">
                            <button type="button" class="qty-btn" data-action="step-qty" data-target="refundQtyInput" data-delta="1" aria-label="Increase"><i class="fas fa-plus" aria-hidden="true"></i></button>
                        </div>
                        <span class="refund-qty-max" id="refundQtyMax"></span>
                    </div>
                </dd>
                <dt>Unit Price</dt> <dd id="refundUnitPrice"></dd>
                <dt>Total</dt>      <dd id="refundTotal" class="refund-total-value"></dd>
                <dt>Sale Date</dt>  <dd id="refundDate"></dd>
            </dl>
            <div class="form-group">
                <label class="form-label">Item Condition <span class="required" aria-hidden="true">*</span></label>
                <label class="radio-option">
                    <input type="radio" name="refundCondition" value="resellable">
                    <div>
                        <strong>Resellable</strong>
                        <small>Item is in good condition and will be returned to stock.</small>
                    </div>
                </label>
                <label class="radio-option">
                    <input type="radio" name="refundCondition" value="damaged">
                    <div>
                        <strong>Damaged</strong>
                        <small>Item cannot be resold. Stock will NOT be restored.</small>
                    </div>
                </label>
            </div>

            <div class="form-group">
                <label class="form-label" for="refundReason">Reason <span class="form-label-opt">(optional)</span></label>
                <textarea id="refundReason" class="form-control" rows="2" placeholder="Enter reason for refund..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" data-action="close-modal">Cancel</button>
            <button class="btn btn-warning" id="refundBtn" data-action="submit-refund">
                <i class="fas fa-undo" aria-hidden="true"></i> Confirm Refund
            </button>
        </div>
    </div>
</div>

<script src="../assets/js/main.js"></script>
</body>
</html>
