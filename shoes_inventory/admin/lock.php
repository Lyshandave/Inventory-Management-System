<?php
/**
 * lock.php
 * Locks the screen without ending the shift or logging out.
 * Cashier must re-enter their password to unlock.
 * The shift and session remain active throughout.
 */

require_once '../config.php';
require_login();

// Lock screen is for cashiers only — admins have no shift to lock
if (is_admin()) {
    redirect_to('admin.php');
}

$user  = current_user();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';

    $conn = db_connect();
    $stmt = $conn->prepare('SELECT password FROM users WHERE id = ? AND is_active = 1');
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();

    if ($row && password_verify($password, $row['password'])) {
        // Unlock — go back to dashboard
        redirect_to('admin.php');
    } else {
        $error = 'Incorrect password. Try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1d4ed8">
    <title>Screen Locked — Shoes Inventory</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="login-page">

<div class="login-wrapper">
    <div class="login-card">

        <div class="login-header">
            <div class="login-icon lock-icon" aria-hidden="true">
                <i class="fas fa-lock"></i>
            </div>
            <h1>Screen Locked</h1>
            <p>Locked by <strong><?= h($user['full_name']) ?></strong></p>
            <p class="lock-note">Your shift is still active. Enter your password to continue.</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-error" role="alert">
            <i class="fas fa-times-circle" aria-hidden="true"></i>
            <?= h($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" novalidate data-form="lock">
            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <div class="input-with-icon">
                    <i class="fas fa-lock" aria-hidden="true"></i>
                    <input type="password" id="password" name="password" class="form-control"
                           placeholder="Enter your password"
                           required autocomplete="current-password" autofocus>
                    <button type="button" class="toggle-password" data-action="toggle-password"
                            data-target="password" aria-label="Show password">
                        <i class="fas fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-unlock" aria-hidden="true"></i> Unlock
            </button>
        </form>

        <div class="lock-logout-link">
            Not <?= h($user['full_name']) ?>?
            <a href="logout.php">Sign out instead</a>
        </div>

    </div>
</div>

<script src="../assets/js/main.js"></script>
</body>
</html>
