<?php
/**
 * add_product.php
 * Displays and processes the Add Product form.
 */

require_once '../config.php';
require_login('admin'); // only admins can add products

$errors  = [];
$success = '';
$gender  = '';
$fields  = ['product_name' => '', 'brand' => '', 'category' => '', 'size' => '', 'color' => '', 'description' => ''];
$numbers = ['price' => '', 'quantity' => '0', 'min_stock' => '5', 'max_stock' => '100'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect text fields
    foreach ($fields as $key => $_) {
        $fields[$key] = clean($_POST[$key] ?? '');
    }
    $gender    = in_array($_POST['gender'] ?? '', ['Men','Women','Unisex'], true) ? $_POST['gender'] : 'Unisex';
    $price     = floatval($_POST['price']      ?? 0);
    $costPrice = floatval($_POST['cost_price'] ?? 0);
    $quantity  = intval($_POST['quantity']     ?? 0);
    $minStock = intval($_POST['min_stock']   ?? 5);
    $maxStock = intval($_POST['max_stock']   ?? 100);

    // Preserve number values for re-display on error
    $numbers = [
        'price'      => $_POST['price']      ?? '',
        'cost_price' => $_POST['cost_price'] ?? '0.00',
        'quantity'   => $_POST['quantity']   ?? '0',
        'min_stock'  => $_POST['min_stock']  ?? '5',
        'max_stock'  => $_POST['max_stock']  ?? '100',
    ];

    // Validate
    foreach (['product_name' => 'Product name', 'brand' => 'Brand', 'category' => 'Category', 'size' => 'Size', 'color' => 'Color'] as $key => $label) {
        if ($fields[$key] === '') $errors[] = "{$label} is required.";
    }
    if (!$gender)                  $errors[] = 'Gender is required.';
    if ($price    <= 0)            $errors[] = 'Price must be greater than 0.';
    if ($costPrice < 0)            $errors[] = 'Cost price cannot be negative.';
    if ($quantity < 0)             $errors[] = 'Initial stock cannot be negative.';
    if ($minStock < 0)             $errors[] = 'Minimum stock cannot be negative.';
    if ($maxStock <= $minStock)    $errors[] = 'Maximum stock must be greater than minimum stock.';
    if ($quantity > 0 && $quantity < $minStock) {
        $errors[] = "Initial stock ({$quantity}) cannot be less than the minimum stock limit ({$minStock}).";
    }
    if ($quantity > $maxStock) {
        $errors[] = "Initial stock ({$quantity}) cannot exceed the maximum stock limit ({$maxStock}).";
    }

    // Handle image upload
    $imageName = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        [$imageName, $uploadError] = save_image($_FILES['image']);
        if ($uploadError) $errors[] = $uploadError;
    }

    if (empty($errors)) {
        $conn = db_connect();
        $stmt = $conn->prepare(
            'INSERT INTO products (product_name, brand, category, size, color, gender, cost_price, price, quantity, min_stock, max_stock, description, image)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'ssssssddiiiss',
            $fields['product_name'], $fields['brand'], $fields['category'],
            $fields['size'], $fields['color'], $gender,
            $costPrice, $price, $quantity, $minStock, $maxStock,
            $fields['description'], $imageName
        );

        if ($stmt->execute()) {
            $stmt->close();
            $conn->close();
            // Stay on this page so the admin can immediately add another product.
            // Reset all fields to blank so the form is ready for the next entry.
            $success = 'Product "' . $fields['product_name'] . '" added successfully.';
            $fields  = ['product_name' => '', 'brand' => '', 'category' => '', 'size' => '', 'color' => '', 'description' => ''];
            $gender  = '';
            $numbers = ['price' => '', 'cost_price' => '0.00', 'quantity' => '0', 'min_stock' => '5', 'max_stock' => '100'];
        } else {
            $errors[] = 'Database error: ' . $stmt->error;
            $stmt->close();
            $conn->close();
            if ($imageName) delete_image($imageName);
        }
    } elseif ($imageName) {
        delete_image($imageName); // Clean up uploaded file if validation failed
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1d4ed8">
    <title>Add Product — Shoes Inventory</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php include '_navbar.php'; ?>

<main class="main-container">

    <div class="page-header">
        <h1><i class="fas fa-plus-circle page-header-icon" aria-hidden="true"></i> Add New Product</h1>
        <p>Fill in the details below to add a new shoe to the inventory.</p>
    </div>

    <?php if ($errors): ?>
    <div class="alert alert-error" role="alert">
        <i class="fas fa-times-circle" aria-hidden="true"></i>
        <ul class="alert-list">
            <?php foreach ($errors as $err): ?>
                <li><?= h($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success" role="alert">
        <i class="fas fa-check-circle" aria-hidden="true"></i>
        <?= h($success) ?> <a href="admin.php" class="alert-link">View in Dashboard</a>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h2>Product Information</h2>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" data-form="add-product" novalidate>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="product_name">Product Name <span class="required" aria-hidden="true">*</span></label>
                        <input type="text" id="product_name" name="product_name" class="form-control"
                               value="<?= h($fields['product_name']) ?>" placeholder="e.g., Air Max 90"
                               required autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="brand">Brand <span class="required" aria-hidden="true">*</span></label>
                        <input type="text" id="brand" name="brand" class="form-control"
                               value="<?= h($fields['brand']) ?>" placeholder="e.g., Nike"
                               required autocomplete="off">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="category">Category <span class="required" aria-hidden="true">*</span></label>
                        <input type="text" id="category" name="category" class="form-control"
                               list="categoryOptions" value="<?= h($fields['category']) ?>"
                               placeholder="e.g., Running" required autocomplete="off">
                        <datalist id="categoryOptions">
                            <?php foreach (SHOE_CATEGORIES as $cat): ?>
                                <option value="<?= h($cat) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="size">Size <span class="required" aria-hidden="true">*</span></label>
                        <input type="text" id="size" name="size" class="form-control"
                               value="<?= h($fields['size']) ?>" placeholder="e.g., 10 or 10.5" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="color">Color <span class="required" aria-hidden="true">*</span></label>
                        <input type="text" id="color" name="color" class="form-control"
                               value="<?= h($fields['color']) ?>" placeholder="e.g., Black/White" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="gender">Gender <span class="required" aria-hidden="true">*</span></label>
                        <select id="gender" name="gender" class="form-control" required>
                            <option value="">— Select —</option>
                            <?php foreach (['Men', 'Women', 'Unisex'] as $g): ?>
                                <option value="<?= $g ?>" <?= $gender === $g ? 'selected' : '' ?>><?= $g ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="cost_price">Cost Price (₱)</label>
                        <input type="number" id="cost_price" name="cost_price" class="form-control"
                               value="<?= h($numbers['cost_price'] ?? '0.00') ?>" placeholder="0.00"
                               step="0.01" min="0">
                        <small class="form-hint">Purchase/supplier price. Used to calculate profit.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="price">Selling Price (₱) <span class="required" aria-hidden="true">*</span></label>
                        <input type="number" id="price" name="price" class="form-control"
                               value="<?= h($numbers['price']) ?>" placeholder="0.00"
                               step="0.01" min="0.01" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Profit per Unit</label>
                        <div class="profit-preview" id="profitPreview">—</div>
                        <small class="form-hint">Selling price minus cost price.</small>
                    </div>
                </div>

                <div class="form-row form-row-3">
                    <div class="form-group">
                        <label class="form-label" for="quantity">Initial Stock</label>
                        <input type="number" id="quantity" name="quantity" class="form-control"
                               value="<?= h($numbers['quantity']) ?>" min="0">
                        <small class="form-hint" id="qtyHint">Must be 0 or within min–max range.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="min_stock">Min Stock</label>
                        <input type="number" id="min_stock" name="min_stock" class="form-control"
                               value="<?= h($numbers['min_stock']) ?>" min="0">
                        <small class="form-hint">Low-stock warning threshold.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="max_stock">Max Stock</label>
                        <input type="number" id="max_stock" name="max_stock" class="form-control"
                               value="<?= h($numbers['max_stock']) ?>" min="1">
                        <small class="form-hint">Stock cannot exceed this value.</small>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="image">
                        Product Image <span class="form-label-opt">(optional — JPG or PNG, max 5 MB)</span>
                    </label>
                    <input type="file" id="image" name="image" class="form-control"
                           accept="image/jpeg,image/jpg,image/png"
                           data-action="preview-image"
                           data-preview-target="imagePreview"
                           data-preview-wrap="imagePreviewWrap">
                </div>

                <div class="form-group hidden" id="imagePreviewWrap">
                    <label class="form-label">Image Preview</label>
                    <img id="imagePreview" src="" alt="Preview" class="img-preview">
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Description <span class="form-label-opt">(optional)</span></label>
                    <textarea id="description" name="description" class="form-control" rows="3"
                              placeholder="Enter product description..."><?= h($fields['description']) ?></textarea>
                </div>

                <div class="form-buttons">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save" aria-hidden="true"></i> Save Product
                    </button>
                    <a href="admin.php" class="btn btn-secondary">
                        <i class="fas fa-times" aria-hidden="true"></i> Cancel
                    </a>
                </div>

            </form>
        </div>
    </div>

</main>

<script src="../assets/js/main.js"></script>
</body>
</html>
