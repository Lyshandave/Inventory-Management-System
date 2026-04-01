<?php
/**
 * index.php
 * Handles user authentication. On success, starts a shift for cashiers.
 */

require_once 'config.php';

session_init();

// Already logged in — go to dashboard
if (!empty($_SESSION['user_id'])) {
    redirect_to('admin/admin.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $conn = db_connect();
        $stmt = $conn->prepare(
            'SELECT id, username, password, full_name, role, is_active
             FROM users WHERE username = ? LIMIT 1'
        );
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();

        if (!$user || !$user['is_active']) {
            $error = 'Invalid username or password.';
        } elseif (!password_verify($password, $user['password'])) {
            $error = 'Invalid username or password.';
        } else {
            session_regenerate_id(true);

            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];

            // Start a shift for cashiers on login
            if ($user['role'] === 'cashier') {
                // Auto-close any orphaned open shift (e.g. browser closed without logout)
                $conn2  = db_connect();
                $stmtO  = $conn2->prepare(
                    'SELECT id FROM shifts WHERE user_id = ? AND ended_at IS NULL ORDER BY id DESC LIMIT 1'
                );
                $stmtO->bind_param('i', $user['id']);
                $stmtO->execute();
                $openShift = $stmtO->get_result()->fetch_assoc();
                $stmtO->close();
                $conn2->close();

                if ($openShift) {
                    // Close the orphaned shift silently
                    end_shift((int)$openShift['id']);
                }

                $shiftId = start_shift((int)$user['id']);
                $_SESSION['shift_id'] = $shiftId;
            }

            redirect_to('admin/admin.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1d4ed8">
    <title>Login — Shoes Inventory</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="login-page">

<div class="login-wrapper">
    <div class="login-card">

        <div class="login-header">
            <div class="login-icon" aria-hidden="true">
                <i class="fas fa-shoe-prints"></i>
            </div>
            <h1>Shoes Inventory</h1>
            <p>Sign in to your account</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-error" role="alert">
            <i class="fas fa-times-circle" aria-hidden="true"></i>
            <?= h($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" novalidate data-form="login">
            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <div class="input-with-icon">
                    <i class="fas fa-user" aria-hidden="true"></i>
                    <input type="text" id="username" name="username" class="form-control"
                           value="<?= h($_POST['username'] ?? '') ?>"
                           placeholder="Enter username"
                           required autocomplete="username" autofocus>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <div class="input-with-icon">
                    <i class="fas fa-lock" aria-hidden="true"></i>
                    <input type="password" id="password" name="password" class="form-control"
                           placeholder="Enter password"
                           required autocomplete="current-password">
                    <button type="button" class="toggle-password" data-action="toggle-password"
                            data-target="password" aria-label="Show password">
                        <i class="fas fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-sign-in-alt" aria-hidden="true"></i> Sign In
            </button>
        </form>
    </div>
</div>

<script src="assets/js/main.js"></script>
</body>
</html>
