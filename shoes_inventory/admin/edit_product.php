<?php
/**
 * edit_product.php
 * Displays and processes the Edit Product form.
 */

require_once '../config.php';
require_login('admin'); // only admins can edit products

$productId = valid_id($_GET['id'] ?? 0);
if (!$productId) redirect_to('admin.php');

$conn = db_connect();

$stmt = $conn->prepare(
    'SELECT id, product_name, brand, category, size, color, gender,
            cost_price, price, quantity, min_stock, max_stock, description, image, is_archived
     FROM products WHERE id = ?'
);
$stmt->bind_param('i', $productId);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    $conn->close();
    redirect_to('admin.php');
}

$errors = [];
$gender = $product['gender'] ?? 'Unisex';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'product_name' => clean($_POST['product_name'] ?? ''),
        'brand'        => clean($_POST['brand']        ?? ''),
        'category'     => clean($_POST['category']     ?? ''),
        'size'         => clean($_POST['size']         ?? ''),
        'color'        => clean($_POST['color']        ?? ''),
        'description'  => clean($_POST['description']  ?? ''),
    ];
    $gender = in_array($_POST['gender'] ?? '', ['Men','Women','Unisex'], true) ? $_POST['gender'] : ($product['gender'] ?? 'Unisex');
    $price       = floatval($_POST['price']      ?? 0);
    $costPrice   = floatval($_POST['cost_price'] ?? 0);
    $quantity    = intval($_POST['quantity']     ?? 0);
    $minStock    = intval($_POST['min_stock']  ?? 5);
    $maxStock    = intval($_POST['max_stock']  ?? 100);
    $removeImage = isset($_POST['remove_image']);

    // Validate
    foreach (['product_name' => 'Product name', 'brand' => 'Brand', 'category' => 'Category', 'size' => 'Size', 'color' => 'Color'] as $key => $label) {
        if ($fields[$key] === '') $errors[] = "{$label} is required.";
    }
    if ($price    <= 0)            $errors[] = 'Price must be greater than 0.';
    if ($costPrice < 0)            $errors[] = 'Cost price cannot be negative.';
    if ($quantity < 0)             $errors[] = 'Stock quantity cannot be negative.';
    if ($minStock < 0)             $errors[] = 'Minimum stock cannot be negative.';
    if ($maxStock <= $minStock)    $errors[] = 'Maximum stock must be greater than minimum stock.';
    if ($quantity > 0 && $quantity < $minStock) {
        $errors[] = "Stock ({$quantity}) cannot be less than the minimum stock limit ({$minStock}).";
    }
    if ($quantity > $maxStock) {
        $errors[] = "Stock ({$quantity}) cannot exceed the maximum stock limit ({$maxStock}).";
    }

    // Handle image
    $imageName = $product['image'];
    if ($removeImage && $imageName) {
        delete_image($imageName);
        $imageName = null;
    }
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        [$newImage, $uploadError] = save_image($_FILES['image']);
        if ($uploadError) {
            $errors[] = $uploadError;
        } else {
            if ($imageName) delete_image($imageName);
            $imageName = $newImage;
        }
    }

    if (empty($errors)) {
        $stmt = $conn->prepare(
            'UPDATE products
             SET product_name=?, brand=?, category=?, size=?, color=?, gender=?,
                 cost_price=?, price=?, quantity=?, min_stock=?, max_stock=?, description=?, image=?
             WHERE id=?'
        );
        $stmt->bind_param(
            'ssssssddiiissi',
            $fields['product_name'], $fields['brand'], $fields['category'],
            $fields['size'], $fields['color'], $gender,
            $costPrice, $price, $quantity, $minStock, $maxStock,
            $fields['description'], $imageName,
            $productId
        );

        if ($stmt->execute()) {
            $stmt->close();
            $conn->close();
            redirect_to('admin.php?updated=1');
        }

        $errors[] = 'Database error: ' . $stmt->error;
        $stmt->close();
    } else {
        // Validation failed — delete any newly uploaded image to avoid orphaned files
        if (isset($newImage) && $newImage && $newImage !== $product['image']) {
            delete_image($newImage);
            $imageName = $product['image']; // revert to original
        }
    }

    // Re-populate product with submitted values for re-display
    $product = array_merge($product, $fields, [
        'cost_price' => $costPrice,
        'price'      => $price,
        'quantity'   => $quantity,
        'min_stock'  => $minStock,
        'max_stock'  => $maxStock,
        'image'      => $imageName,
    ]);
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1d4ed8">
    <title>Edit Product — Shoes Inventory</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php include '_navbar.php'; ?>

<main class="main-container">

    <div class="page-header">
        <h1><i class="fas fa-edit page-header-icon" aria-hidden="true"></i> Edit Product</h1>
        <p>Updating: <strong><?= h($product['product_name']) ?></strong></p>
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

    <div class="card">
        <div class="card-header">
            <h2>Product Information</h2>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" data-form="edit-product" novalidate>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="product_name">Product Name <span class="required" aria-hidden="true">*</span></label>
                        <input type="text" id="product_name" name="product_name" class="form-control"
                               value="<?= h($product['product_name']) ?>" required autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="brand">Brand <span class="required" aria-hidden="true">*</span></label>
                        <input type="text" id="brand" name="brand" class="form-control"
                               value="<?= h($product['brand']) ?>" required autocomplete="off">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="category">Category <span class="required" aria-hidden="true">*</span></label>
                        <input type="text" id="category" name="category" class="form-control"
                               list="categoryOptions" value="<?= h($product['category']) ?>" required autocomplete="off">
                        <datalist id="categoryOptions">
                            <?php foreach (SHOE_CATEGORIES as $cat): ?>
                                <option value="<?= h($cat) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="size">Size <span class="required" aria-hidden="true">*</span></label>
                        <input type="text" id="size" name="size" class="form-control"
                               value="<?= h($product['size']) ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="color">Color <span class="required" aria-hidden="true">*</span></label>
                        <input type="text" id="color" name="color" class="form-control"
                               value="<?= h($product['color']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="gender">Gender <span class="required" aria-hidden="true">*</span></label>
                        <select id="gender" name="gender" class="form-control" required>
                            <?php foreach (['Men','Women','Unisex'] as $g): ?>
                                <option value="<?= $g ?>" <?= $gender === $g ? 'selected' : '' ?>><?= $g ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="cost_price">Cost Price (₱)</label>
                        <input type="number" id="cost_price" name="cost_price" class="form-control"
                               value="<?= h($product['cost_price'] ?? '0.00') ?>" step="0.01" min="0">
                        <small class="form-hint">Purchase/supplier price. Used to calculate profit.</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="price">Selling Price (₱) <span class="required" aria-hidden="true">*</span></label>
                        <input type="number" id="price" name="price" class="form-control"
                               value="<?= h($product['price']) ?>" step="0.01" min="0.01" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Profit per Unit</label>
                        <div class="profit-preview" id="profitPreview">—</div>
                        <small class="form-hint">Selling price minus cost price.</small>
                    </div>
                </div>

                <div class="form-row form-row-3">
                    <div class="form-group">
                        <label class="form-label" for="edit_quantity">Stock Quantity</label>
                        <input type="number" id="edit_quantity" name="quantity" class="form-control"
                               value="<?= h($product['quantity']) ?>" min="0">
                        <small class="form-hint" id="editQtyHint">
                            Must be 0 (out of stock), or between <?= (int)$product['min_stock'] ?> (min) and <?= (int)$product['max_stock'] ?> (max).
                        </small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_min_stock">Min Stock</label>
                        <input type="number" id="edit_min_stock" name="min_stock" class="form-control"
                               value="<?= h($product['min_stock']) ?>" min="0">
                        <small class="form-hint">Low-stock warning threshold.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_max_stock">Max Stock</label>
                        <input type="number" id="edit_max_stock" name="max_stock" class="form-control"
                               value="<?= h($product['max_stock']) ?>" min="1">
                        <small class="form-hint">Stock cannot exceed this value.</small>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="new_image">
                        New Image <span class="form-label-opt">(optional — leave blank to keep current)</span>
                    </label>
                    <input type="file" id="new_image" name="image" class="form-control"
                           accept="image/jpeg,image/jpg,image/png"
                           data-action="preview-image"
                           data-preview-target="newImgPreview"
                           data-preview-wrap="newImgWrap">
                </div>

                <?php if ($product['image']): ?>
                <div class="form-group" id="currentImgWrap">
                    <label class="form-label">Current Image</label>
                    <div class="img-with-remove">
                        <img src="<?= h(UPLOAD_URL_PATH . $product['image']) ?>"
                             alt="Current product image"
                             class="img-preview"
                             id="currentImg">
                        <label class="remove-image-label">
                            <input type="checkbox" name="remove_image" value="1"
                                   data-action="toggle-image-dim"
                                   data-target="currentImg">
                            Remove this image
                        </label>
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-group hidden" id="newImgWrap">
                    <label class="form-label">New Image Preview</label>
                    <img id="newImgPreview" src="" alt="New image preview" class="img-preview">
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Description <span class="form-label-opt">(optional)</span></label>
                    <textarea id="description" name="description" class="form-control" rows="3"
                              placeholder="Enter product description..."><?= h($product['description']) ?></textarea>
                </div>

                <div class="form-buttons">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save" aria-hidden="true"></i> Update Product
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
