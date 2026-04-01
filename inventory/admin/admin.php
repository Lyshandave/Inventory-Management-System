<?php
/**
 * admin.php
 * Dashboard — shows summary stats and the products table.
 */

require_once '../config.php';
require_login(); // any logged-in user can view the dashboard

$conn = db_connect();

// ── Toast from redirect ────────────────────────────────────────────────────────
$toast = null;
if (isset($_GET['added']))        $toast = ['type' => 'success', 'title' => 'Product Added',   'text' => 'The product was added successfully.'];
if (isset($_GET['updated']))      $toast = ['type' => 'success', 'title' => 'Product Updated', 'text' => 'The product was updated successfully.'];
if (isset($_GET['error']) && $_GET['error'] === 'access_denied') {
    $toast = ['type' => 'error', 'title' => 'Access Denied', 'text' => 'You do not have permission to access that page.'];
}

// ── Single query for all summary stats ────────────────────────────────────────
$stats = $conn->query(
    "SELECT
        COUNT(CASE WHEN is_archived = 0 THEN 1 END)                                             AS total_products,
        COALESCE(SUM(CASE WHEN is_archived = 0 THEN quantity END), 0)                           AS total_stock,
        COUNT(CASE WHEN is_archived = 0 AND quantity = 0 THEN 1 END)                            AS out_of_stock,
        COUNT(CASE WHEN is_archived = 0 AND quantity > 0 AND quantity <= min_stock THEN 1 END)  AS low_stock,
        COUNT(CASE WHEN is_archived = 1 THEN 1 END)                                             AS archived_count
     FROM products"
)->fetch_assoc();

// ── Revenue stats — admins see all-time, cashiers see current shift only ─────
if (is_admin()) {
    $revenue = $conn->query(
        "SELECT
            COUNT(CASE WHEN status = 'Sold' THEN 1 END)                                        AS total_sales,
            COALESCE(SUM(CASE WHEN status = 'Sold' THEN total_price END), 0)                   AS total_revenue,
            COALESCE(SUM(CASE WHEN status = 'Sold' THEN total_cost END), 0)                    AS total_cost,
            COALESCE(SUM(CASE WHEN status = 'Sold' THEN total_price - total_cost END), 0)      AS total_profit
         FROM transactions"
    )->fetch_assoc();
} else {
    $shiftId = current_shift_id();
    $stmt = $conn->prepare(
        "SELECT
            COUNT(CASE WHEN status = 'Sold' THEN 1 END)                                        AS total_sales,
            COALESCE(SUM(CASE WHEN status = 'Sold' THEN total_price END), 0)                   AS total_revenue,
            COALESCE(SUM(CASE WHEN status = 'Sold' THEN total_cost END), 0)                    AS total_cost,
            COALESCE(SUM(CASE WHEN status = 'Sold' THEN total_price - total_cost END), 0)      AS total_profit
         FROM transactions
         WHERE shift_id = ?"
    );
    $stmt->bind_param('i', $shiftId);
    $stmt->execute();
    $revenue = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// ── Best-seller IDs (top 5 active products by units sold) ─────────────────────
$bestSellerIds = [];
$bsResult = $conn->query(
    "SELECT p.id, COALESCE(SUM(t.quantity_sold), 0) AS total_sold
     FROM products p
     LEFT JOIN transactions t ON t.product_id = p.id AND t.status = 'Sold'
     WHERE p.is_archived = 0
     GROUP BY p.id
     ORDER BY total_sold DESC, p.product_name ASC
     LIMIT 5"
);
$rank = 0;
while ($row = $bsResult->fetch_assoc()) {
    if ((int)$row['total_sold'] > 0) {
        $bestSellerIds[(int)$row['id']] = ++$rank;
    }
}

// ── All products for the table ─────────────────────────────────────────────────
// Admins see active + archived. Cashiers see active products only.
$products = [];
$productWhere = is_admin() ? '' : 'WHERE p.is_archived = 0';
$result = $conn->query(
    "SELECT p.id, p.product_name, p.brand, p.category, p.size, p.color, p.gender,
            p.price, p.quantity, p.min_stock, p.max_stock, p.image, p.is_archived
     FROM products p
     {$productWhere}
     ORDER BY p.created_at DESC"
);
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

// ── Filter dropdown options (distinct values from active products) ─────────────
$categories = [];
$catResult = $conn->query('SELECT DISTINCT category FROM products WHERE is_archived = 0 ORDER BY category');
while ($row = $catResult->fetch_assoc()) $categories[] = $row['category'];

$brands = [];
$brandResult = $conn->query('SELECT DISTINCT brand FROM products WHERE is_archived = 0 ORDER BY brand');
while ($row = $brandResult->fetch_assoc()) $brands[] = $row['brand'];

$conn->close();

// ── Helpers (used in template only) ──────────────────────────────────────────
$bsBadges = [
    1 => ['bs-gold',   '🥇 Best Seller'],
    2 => ['bs-silver', '🥈 Top 2'],
    3 => ['bs-bronze', '🥉 Top 3'],
    4 => ['bs-top',    '⭐ Top 4'],
    5 => ['bs-top',    '⭐ Top 5'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1d4ed8">
    <title>Dashboard — Shoes Inventory</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body<?php if ($toast): ?>
    data-toast-type="<?= h($toast['type']) ?>"
    data-toast-title="<?= h($toast['title']) ?>"
    data-toast-text="<?= h($toast['text']) ?>"
<?php endif; ?>
    data-is-admin="<?= is_admin() ? '1' : '0' ?>"
    data-auto-lock="<?= is_admin() ? '0' : AUTO_LOCK_MINUTES ?>"
>

<?php include '_navbar.php'; ?>

<main class="main-container">

    <div class="page-header">
        <h1><i class="fas fa-chart-pie page-header-icon" aria-hidden="true"></i> Dashboard</h1>
        <p>Overview of your inventory and sales performance.</p>
    </div>

    <!-- Summary Cards -->
    <div class="summary-cards">
        <div class="summary-card">
            <div class="summary-card-icon icon-blue" aria-hidden="true"><i class="fas fa-box"></i></div>
            <div class="summary-card-body">
                <h2 class="summary-card-label">Products</h2>
                <p class="summary-card-value" id="dashTotalProducts"><?= number_format((int)$stats['total_products']) ?></p>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-card-icon icon-green" aria-hidden="true"><i class="fas fa-layer-group"></i></div>
            <div class="summary-card-body">
                <h2 class="summary-card-label">Total Stock</h2>
                <p class="summary-card-value" id="dashTotalStock"><?= number_format((int)$stats['total_stock']) ?></p>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-card-icon icon-orange" aria-hidden="true"><i class="fas fa-cash-register"></i></div>
            <div class="summary-card-body">
                <h2 class="summary-card-label">Total Sales</h2>
                <p class="summary-card-value" id="dashTotalSales"><?= number_format((int)$revenue['total_sales']) ?></p>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-card-icon icon-purple" aria-hidden="true"><i class="fas fa-peso-sign"></i></div>
            <div class="summary-card-body">
                <h2 class="summary-card-label">Revenue</h2>
                <p class="summary-card-value" id="dashTotalRevenue"><?= currency_compact((float)$revenue['total_revenue']) ?></p>
            </div>
        </div>
        <?php if (is_admin()): ?>
        <div class="summary-card">
            <div class="summary-card-icon icon-teal" aria-hidden="true"><i class="fas fa-chart-line"></i></div>
            <div class="summary-card-body">
                <h2 class="summary-card-label">Net Profit</h2>
                <p class="summary-card-value" id="dashTotalProfit"><?= currency_compact((float)$revenue['total_profit']) ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Low Stock Alert (hidden when no issues) -->
    <div id="lowStockAlert" class="alert alert-warning<?= ((int)$stats['low_stock'] === 0 && (int)$stats['out_of_stock'] === 0) ? ' hidden' : '' ?>"
         role="alert">
        <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
        <span id="lowStockAlertText">
            <?php if ((int)$stats['out_of_stock'] > 0): ?>
                <strong><?= (int)$stats['out_of_stock'] ?> product(s) are out of stock.</strong>
            <?php endif; ?>
            <?php if ((int)$stats['low_stock'] > 0): ?>
                <strong><?= (int)$stats['low_stock'] ?> product(s) are running low.</strong> Restock soon.
            <?php endif; ?>
        </span>
    </div>

    <!-- Products Card -->
    <div class="card">
        <div class="card-header">
            <h2>All Products</h2>
            <?php if (is_admin()): ?>
            <a href="add_product.php" class="btn btn-primary">
                <i class="fas fa-plus" aria-hidden="true"></i> Add Product
            </a>
            <?php endif; ?>
        </div>
        <div class="card-body">

            <!-- Search & Filters -->
            <div class="filter-bar">
                <div class="search-box">
                    <i class="fas fa-search search-icon" aria-hidden="true"></i>
                    <input type="search" id="searchInput" class="form-control"
                           placeholder="Search products..." aria-label="Search products">
                </div>
                <div class="filter-select">
                    <select id="categoryFilter" class="form-control" aria-label="Filter by category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= h(strtolower($cat)) ?>"><?= h($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-select">
                    <select id="brandFilter" class="form-control" aria-label="Filter by brand">
                        <option value="">All Brands</option>
                        <?php foreach ($brands as $brand): ?>
                            <option value="<?= h(strtolower($brand)) ?>"><?= h($brand) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-select">
                    <select id="genderFilter" class="form-control" aria-label="Filter by gender">
                        <option value="">All Genders</option>
                        <option value="men">Men</option>
                        <option value="women">Women</option>
                        <option value="unisex">Unisex</option>
                    </select>
                </div>
            </div>

            <!-- Brand stock info bar — shown when a brand filter is active -->
            <div id="brandStockInfo" class="brand-info-bar hidden" aria-live="polite"></div>

            <!-- Active / Archived Tabs (Archived only visible to admins) -->
            <div class="tab-bar" role="tablist">
                <button class="tab active" role="tab" aria-selected="true"
                        data-view="active" data-action="switch-view">
                    Active Products
                </button>
                <?php if (is_admin()): ?>
                <button class="tab" role="tab" aria-selected="false"
                        data-view="archived" data-action="switch-view">
                    Archived
                    <span class="tab-badge<?= (int)$stats['archived_count'] === 0 ? ' hidden' : '' ?>"
                          id="archivedCountBadge">
                        <?= (int)$stats['archived_count'] ?>
                    </span>
                </button>
                <?php endif; ?>
            </div>

            <!-- Products Table -->
            <div class="table-wrap">
                <table class="table" id="productsTable">
                    <thead>
                        <tr>
                            <th scope="col">Image</th>
                            <th scope="col">Product</th>
                            <th scope="col">Brand</th>
                            <th scope="col">Category</th>
                            <th scope="col">Size</th>
                            <th scope="col">Color</th>
                            <th scope="col">Gender</th>
                            <th scope="col">Price</th>
                            <th scope="col">Stock</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="productsBody">

                        <?php foreach ($products as $p):
                            $isArchived = (bool)$p['is_archived'];
                            $status     = stock_status((int)$p['quantity'], (int)$p['min_stock']);
                            $bsRank     = $bestSellerIds[$p['id']] ?? 0;
                        ?>
                        <tr data-id="<?= (int)$p['id'] ?>"
                            data-archived="<?= $isArchived ? '1' : '0' ?>"
                            data-category="<?= h(strtolower($p['category'])) ?>"
                            data-brand="<?= h(strtolower($p['brand'])) ?>"
                            data-color="<?= h(strtolower($p['color'])) ?>"
                            data-quantity="<?= (int)$p['quantity'] ?>"
                            data-min-stock="<?= (int)$p['min_stock'] ?>"
                            data-max-stock="<?= (int)$p['max_stock'] ?>"
                            data-price="<?= h($p['price']) ?>"
                            data-name="<?= h($p['product_name']) ?>"
                            data-gender="<?= h(strtolower($p['gender'] ?? 'unisex')) ?>"
                            data-bs-rank="<?= $bsRank ?>"
                            <?= $isArchived ? 'class="row-archived"' : '' ?>>

                            <!-- Image -->
                            <td>
                                <?php if ($p['image']): ?>
                                    <img src="<?= h(UPLOAD_URL_PATH . $p['image']) ?>"
                                         alt="<?= h($p['product_name']) ?>"
                                         class="product-thumb" loading="lazy">
                                <?php else: ?>
                                    <div class="product-thumb-placeholder" aria-hidden="true">👟</div>
                                <?php endif; ?>
                            </td>

                            <!-- Name + best-seller badge -->
                            <td class="name-cell">
                                <strong><?= h($p['product_name']) ?></strong>
                                <?php if ($bsRank >= 1 && $bsRank <= 5):
                                    [$bsCls, $bsText] = $bsBadges[$bsRank]; ?>
                                    <span class="bs-badge <?= h($bsCls) ?>"><?= h($bsText) ?></span>
                                <?php endif; ?>
                            </td>

                            <td><?= h($p['brand'])    ?></td>
                            <td><?= h($p['category']) ?></td>
                            <td><?= h($p['size'])     ?></td>
                            <td><?= h($p['color'])    ?></td>
                            <td>
                                <?php
                                    $g      = $p['gender'] ?? 'Unisex';
                                    $gClass = match($g) { 'Men' => 'badge-men', 'Women' => 'badge-women', default => 'badge-unisex' };
                                ?>
                                <span class="badge <?= $gClass ?>"><?= h($g) ?></span>
                            </td>

                            <td class="price-cell"><?= currency((float)$p['price']) ?></td>

                            <td class="stock-cell">
                                <span class="stock-qty"><?= (int)$p['quantity'] ?></span>
                                <small class="stock-min">min: <?= (int)$p['min_stock'] ?></small>
                            </td>

                            <td class="status-cell">
                                <?php if ($isArchived): ?>
                                    <span class="badge badge-gray">
                                        <i class="fas fa-archive" aria-hidden="true"></i> Archived
                                    </span>
                                <?php else: ?>
                                    <span class="badge <?= stock_badge_class($status) ?>">
                                        <span class="stock-dot <?= stock_dot_class($status) ?>" aria-hidden="true"></span>
                                        <?= stock_label($status) ?>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="actions-cell">
                                <?php if (!$isArchived): ?>
                                    <button class="btn btn-sm btn-success btn-icon" data-action="open-add-stock"
                                            data-id="<?= (int)$p['id'] ?>"
                                            data-name="<?= h($p['product_name']) ?>"
                                            data-stock="<?= (int)$p['quantity'] ?>"
                                            data-max="<?= (int)$p['max_stock'] ?>"
                                            title="Add Stock" aria-label="Add Stock">
                                        <i class="fas fa-plus" aria-hidden="true"></i>
                                    </button>

                                    <button class="btn btn-sm btn-info btn-icon<?= (int)$p['quantity'] <= 0 ? ' btn-disabled' : '' ?>"
                                            data-action="open-sell"
                                            data-id="<?= (int)$p['id'] ?>"
                                            data-name="<?= h($p['product_name']) ?>"
                                            data-price="<?= h($p['price']) ?>"
                                            data-stock="<?= (int)$p['quantity'] ?>"
                                            title="Sell" aria-label="Sell"
                                            <?= (int)$p['quantity'] <= 0 ? 'disabled aria-disabled="true"' : '' ?>>
                                        <i class="fas fa-cash-register" aria-hidden="true"></i>
                                    </button>

                                    <?php if (is_admin()): ?>
                                    <a href="edit_product.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-warning btn-icon"
                                       title="Edit" aria-label="Edit">
                                        <i class="fas fa-edit" aria-hidden="true"></i>
                                    </a>

                                    <button class="btn btn-sm btn-gray btn-icon" data-action="open-archive"
                                            data-id="<?= (int)$p['id'] ?>"
                                            data-name="<?= h($p['product_name']) ?>"
                                            title="Archive" aria-label="Archive">
                                        <i class="fas fa-archive" aria-hidden="true"></i>
                                    </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-teal btn-icon" data-action="restore"
                                            data-id="<?= (int)$p['id'] ?>"
                                            title="Restore" aria-label="Restore">
                                        <i class="fas fa-undo" aria-hidden="true"></i>
                                    </button>
                                <?php endif; ?>
                            </td>

                        </tr>
                        <?php endforeach; ?>

                        <!-- Empty state — JS controls visibility and text -->
                        <tr id="emptyRow" class="hidden">
                            <td colspan="11">
                                <div class="empty-state">
                                    <div class="empty-icon" aria-hidden="true">
                                        <i class="fas fa-inbox"></i>
                                    </div>
                                    <p class="empty-title">No Active Products</p>
                                    <p class="empty-sub">Add your first product to get started.</p>
                                </div>
                            </td>
                        </tr>

                    </tbody>
                </table>
            </div>
        </div>
    </div>

</main>

<!-- ── Add Stock Modal ──────────────────────────────────────────────────────── -->
<div class="modal-overlay hidden" id="addStockModal" role="dialog" aria-modal="true" aria-labelledby="addStockTitle">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="addStockTitle">Add Stock</h3>
            <button class="modal-close" data-action="close-modal" aria-label="Close">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <div class="modal-body">
            <dl class="info-list">
                <dt>Product</dt>       <dd id="stockProductName"></dd>
                <dt>Current Stock</dt> <dd id="stockCurrent"></dd>
                <dt>Maximum</dt>       <dd id="stockMax"></dd>
                <dt>Can Add</dt>       <dd id="stockCanAdd"></dd>
            </dl>
            <div class="form-group">
                <label class="form-label" for="addStockQty">Quantity to Add</label>
                <div class="qty-stepper">
                    <button type="button" class="qty-btn" data-action="step-qty" data-target="addStockQty" data-delta="-1" aria-label="Decrease">
                        <i class="fas fa-minus" aria-hidden="true"></i>
                    </button>
                    <input type="number" id="addStockQty" class="qty-input" value="1" min="1" max="9999">
                    <button type="button" class="qty-btn" data-action="step-qty" data-target="addStockQty" data-delta="1" aria-label="Increase">
                        <i class="fas fa-plus" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" data-action="close-modal">Cancel</button>
            <button class="btn btn-success" id="addStockBtn" data-action="submit-add-stock">
                <i class="fas fa-plus-circle" aria-hidden="true"></i> Add Stock
            </button>
        </div>
    </div>
</div>

<!-- ── Sell Modal ───────────────────────────────────────────────────────────── -->
<div class="modal-overlay hidden" id="sellModal" role="dialog" aria-modal="true" aria-labelledby="sellModalTitle">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="sellModalTitle">Sell Product</h3>
            <button class="modal-close" data-action="close-modal" aria-label="Close">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <div class="modal-body">
            <dl class="info-list">
                <dt>Product</dt>    <dd id="sellProductName"></dd>
                <dt>In Stock</dt>   <dd id="sellStock"></dd>
                <dt>Unit Price</dt> <dd id="sellUnitPrice"></dd>
            </dl>
            <div class="form-group">
                <label class="form-label" for="sellQty">Quantity to Sell</label>
                <div class="qty-stepper">
                    <button type="button" class="qty-btn" data-action="step-qty" data-target="sellQty" data-delta="-1" aria-label="Decrease">
                        <i class="fas fa-minus" aria-hidden="true"></i>
                    </button>
                    <input type="number" id="sellQty" class="qty-input" value="1" min="1">
                    <button type="button" class="qty-btn" data-action="step-qty" data-target="sellQty" data-delta="1" aria-label="Increase">
                        <i class="fas fa-plus" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div class="sell-total">
                <span>Total</span>
                <strong id="sellTotal">₱0.00</strong>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" data-action="close-modal">Cancel</button>
            <button class="btn btn-info" id="sellBtn" data-action="submit-sell">
                <i class="fas fa-cash-register" aria-hidden="true"></i> Confirm Sale
            </button>
        </div>
    </div>
</div>

<!-- ── Archive Modal ────────────────────────────────────────────────────────── -->
<div class="modal-overlay hidden" id="archiveModal" role="dialog" aria-modal="true" aria-labelledby="archiveModalTitle">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="archiveModalTitle">Archive Product</h3>
            <button class="modal-close" data-action="close-modal" aria-label="Close">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <div class="modal-body">
            <p>Archive <strong id="archiveName"></strong>?</p>
            <p class="text-muted">Archived products are hidden from selling. You can restore them anytime from the Archived tab.</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" data-action="close-modal">Cancel</button>
            <button class="btn btn-gray" id="archiveBtn" data-action="submit-archive">
                <i class="fas fa-archive" aria-hidden="true"></i> Archive
            </button>
        </div>
    </div>
</div>


<script src="../assets/js/main.js"></script>
</body>
</html>
