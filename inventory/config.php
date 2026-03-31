<?php
/**
 * config.php
 * Central configuration and shared utilities for Shoes Inventory System.
 */

// ── Database ──────────────────────────────────────────────────────────────────
// ── Timezone — set to Philippine Standard Time (UTC+8) ───────────────────────
date_default_timezone_set('Asia/Manila');

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'shoes_inventory');

// ── File Upload ───────────────────────────────────────────────────────────────
define('UPLOAD_DIR',      __DIR__ . '/uploads/');
define('UPLOAD_URL_PATH', '../uploads/');
define('IMAGE_MAX_BYTES', 5 * 1024 * 1024);
define('IMAGE_ALLOWED_MIME', ['image/jpeg', 'image/jpg', 'image/png']);

// ── Business Rules ────────────────────────────────────────────────────────────
define('WARRANTY_DAYS',    30);
define('AUTO_LOCK_MINUTES', 5);   // Cashier screen auto-locks after this many minutes of inactivity
define('SHOE_CATEGORIES', ['Running', 'Casual', 'Athletic', 'Formal', 'Sneakers', 'Boots', 'Sandals']);

// ── Session ───────────────────────────────────────────────────────────────────

function session_init(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

/**
 * Require a valid logged-in session.
 * Returns JSON 401/403 for AJAX requests instead of redirecting.
 */
function require_login(string $requiredRole = ''): void
{
    session_init();

    $isAjax = (
        isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    ) || (
        isset($_SERVER['HTTP_ACCEPT']) &&
        str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')
    );

    if (empty($_SESSION['user_id'])) {
        if ($isAjax) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'success'  => false,
                'message'  => 'Session expired. Please log in again.',
                'redirect' => '../index.php',
            ]);
            exit;
        }
        redirect_to('../index.php');
    }

    if ($requiredRole && $_SESSION['user_role'] !== $requiredRole) {
        if ($isAjax) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            exit;
        }
        redirect_to('admin.php?error=access_denied');
    }
}

function current_user(): array
{
    session_init();
    return [
        'id'        => (int)($_SESSION['user_id']   ?? 0),
        'username'  => $_SESSION['username']         ?? '',
        'full_name' => $_SESSION['full_name']        ?? '',
        'role'      => $_SESSION['user_role']        ?? '',
    ];
}

function is_admin(): bool
{
    session_init();
    return ($_SESSION['user_role'] ?? '') === 'admin';
}

function current_shift_id(): int
{
    session_init();
    return (int)($_SESSION['shift_id'] ?? 0);
}

// ── Database ──────────────────────────────────────────────────────────────────

function db_connect(): mysqli
{
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        error_log('DB connection failed: ' . $conn->connect_error);
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'Database connection failed.']));
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

// ── Shift Management ──────────────────────────────────────────────────────────

/**
 * Opens a new shift for the given user. Returns the new shift ID.
 */
function start_shift(int $userId): int
{
    $conn = db_connect();
    $stmt = $conn->prepare('INSERT INTO shifts (user_id) VALUES (?)');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $id = (int)$conn->insert_id;
    $stmt->close();
    $conn->close();
    return $id;
}

/**
 * Closes the given shift by recording ended_at.
 */
function end_shift(int $shiftId): void
{
    $conn = db_connect();
    $stmt = $conn->prepare('UPDATE shifts SET ended_at = NOW() WHERE id = ? AND ended_at IS NULL');
    $stmt->bind_param('i', $shiftId);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

// ── Response Helpers ──────────────────────────────────────────────────────────

function json_success(string $message, array $data = []): never
{
    header('Content-Type: application/json');
    $response = ['success' => true, 'message' => $message];
    if ($data) $response['data'] = $data;
    echo json_encode($response);
    exit;
}

function json_error(string $message, int $status = 400): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function redirect_to(string $url): never
{
    header('Location: ' . $url);
    exit;
}

// ── Input / Validation ────────────────────────────────────────────────────────

function clean(string $value): string
{
    return trim(stripslashes($value));
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function valid_id(mixed $value): int
{
    $id = filter_var($value, FILTER_VALIDATE_INT);
    return ($id !== false && $id > 0) ? $id : 0;
}

function valid_qty(mixed $value, int $min = 1, int $max = 9999): int
{
    $qty = filter_var($value, FILTER_VALIDATE_INT);
    if ($qty === false || $qty < $min || $qty > $max) return 0;
    return $qty;
}

// ── Image Handling ────────────────────────────────────────────────────────────

function save_image(array $file): array
{
    if (!in_array($file['type'], IMAGE_ALLOWED_MIME, true)) {
        return [null, 'Only JPG and PNG images are allowed.'];
    }
    if ($file['size'] > IMAGE_MAX_BYTES) {
        return [null, 'Image must be smaller than 5 MB.'];
    }
    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'img_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $filename)) {
        return [null, 'Failed to save image. Check folder permissions.'];
    }
    return [$filename, null];
}

function delete_image(string $filename): void
{
    if (empty($filename)) return;
    $path = UPLOAD_DIR . $filename;
    if (file_exists($path)) unlink($path);
}

// ── Sales Data ─────────────────────────────────────────────────────────────────

function fetch_sales_data(): array
{
    $range     = $_GET['range']      ?? 'all';
    $startDate = $_GET['start_date'] ?? '';
    $endDate   = $_GET['end_date']   ?? '';

    $where  = '';
    $params = [];
    $types  = '';

    switch ($range) {
        case 'today':
            $where = 'WHERE DATE(t.sale_date) = CURDATE()';
            break;
        case 'week':
            $where = 'WHERE t.sale_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)';
            break;
        case 'month':
            $where = 'WHERE t.sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
            break;
        case 'custom':
            if ($startDate && $endDate) {
                $where  = 'WHERE DATE(t.sale_date) BETWEEN ? AND ?';
                $params = [$startDate, $endDate];
                $types  = 'ss';
            }
            break;
    }

    $conn = db_connect();

    $sql = "SELECT t.id, t.quantity_sold, t.unit_price, t.unit_cost,
                   t.total_price, t.total_cost,
                   t.status, t.refund_reason, t.sale_date,
                   p.product_name, p.brand, p.category,
                   u.full_name AS sold_by_name
            FROM transactions t
            JOIN products p ON t.product_id = p.id
            LEFT JOIN users u ON t.sold_by = u.id
            {$where}
            ORDER BY t.sale_date DESC";

    if ($types) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }

    $sales       = [];
    $soldCount   = 0;
    $totalRev    = 0.0;
    $totalItems  = 0;
    $totalRefund = 0.0;
    $totalProfit = 0.0;

    while ($row = $result->fetch_assoc()) {
        $sales[] = $row;
        if ($row['status'] === 'Sold') {
            $soldCount++;
            $totalRev    += (float)$row['total_price'];
            $totalItems  += (int)$row['quantity_sold'];
            $totalProfit += (float)$row['total_price'] - (float)$row['total_cost'];
        } elseif (str_starts_with($row['status'], 'Refunded')) {
            $totalRefund += (float)$row['total_price'];
        }
    }

    $conn->close();

    return [
        'success'       => true,
        'sales'         => $sales,
        'totalRecords'  => count($sales),
        'totalSales'    => $soldCount,
        'totalItems'    => $totalItems,
        'totalRevenue'  => $totalRev,
        'totalRefund'   => $totalRefund,
        'netRevenue'    => $totalRev - $totalRefund,
        'totalProfit'   => $totalProfit,
    ];
}

// ── Formatting ────────────────────────────────────────────────────────────────

function currency(float $amount): string
{
    return '₱' . number_format($amount, 2);
}

function currency_compact(float $amount): string
{
    $sign = $amount < 0 ? '-' : '';
    $abs  = abs($amount);

    if ($abs >= 1_000_000_000) {
        return '₱' . $sign . number_format($abs / 1_000_000_000, 2) . 'B';
    }
    if ($abs >= 1_000_000) {
        return '₱' . $sign . number_format($abs / 1_000_000, 2) . 'M';
    }
    if ($abs >= 1_000) {
        $k = $abs / 1_000;
        return '₱' . $sign . ($k == floor($k) ? number_format($k, 0) : number_format($k, 1)) . 'K';
    }
    return '₱' . $sign . number_format($abs, 2);
}

// ── Stock ─────────────────────────────────────────────────────────────────────

function stock_status(int $qty, int $min): string
{
    if ($qty === 0)   return 'out_of_stock';
    if ($qty <= $min) return 'low_stock';
    return 'in_stock';
}

function stock_label(string $status): string
{
    return match ($status) {
        'out_of_stock' => 'Out of Stock',
        'low_stock'    => 'Low Stock',
        default        => 'In Stock',
    };
}

function stock_badge_class(string $status): string
{
    return match ($status) {
        'out_of_stock' => 'badge-danger',
        'low_stock'    => 'badge-warning',
        default        => 'badge-success',
    };
}

function stock_dot_class(string $status): string
{
    return match ($status) {
        'out_of_stock' => 'dot-out',
        'low_stock'    => 'dot-low',
        default        => 'dot-in',
    };
}
