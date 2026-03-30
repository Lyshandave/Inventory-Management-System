<?php
/**
 * manage_users.php
 * Admin-only page to create, edit, and deactivate user accounts.
 */

require_once '../config.php';
require_login('admin');

$conn   = db_connect();
$errors = [];
$success = '';
$editUser = null;

// ── Handle form submissions ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = clean($_POST['action'] ?? '');

    // ── Create new user ───────────────────────────────────────────────────────
    if ($action === 'create') {
        $username  = clean($_POST['username']  ?? '');
        $fullName  = clean($_POST['full_name'] ?? '');
        $role      = in_array($_POST['role'] ?? '', ['admin', 'cashier'], true) ? $_POST['role'] : 'cashier';
        $password  = $_POST['password']  ?? '';
        $password2 = $_POST['password2'] ?? '';

        if ($username  === '') $errors[] = 'Username is required.';
        if ($fullName  === '') $errors[] = 'Full name is required.';
        if ($password  === '') {
            $errors[] = 'Password is required.';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }
        if ($password !== '' && $password !== $password2) $errors[] = 'Passwords do not match.';

        if (empty($errors)) {
            // Check username uniqueness
            $stmt = $conn->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->bind_param('s', $username);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $errors[] = "Username \"{$username}\" is already taken.";
            }
            $stmt->close();
        }

        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $conn->prepare(
                'INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)'
            );
            $stmt->bind_param('ssss', $username, $hash, $fullName, $role);
            if ($stmt->execute()) {
                $success = "User \"{$username}\" created successfully.";
            } else {
                $errors[] = 'Database error: ' . $stmt->error;
            }
            $stmt->close();
        }
    }

    // ── Update existing user ──────────────────────────────────────────────────
    elseif ($action === 'update') {
        $userId   = valid_id($_POST['user_id'] ?? 0);
        $fullName = clean($_POST['full_name']  ?? '');
        $role     = in_array($_POST['role'] ?? '', ['admin', 'cashier'], true) ? $_POST['role'] : 'cashier';
        $password = $_POST['password'] ?? '';

        if (!$userId)        $errors[] = 'Invalid user.';
        if ($fullName === '') $errors[] = 'Full name is required.';
        if ($password !== '' && strlen($password) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        }

        // Cannot remove the last admin
        if ($role === 'cashier') {
            $stmtAdm = $conn->prepare("SELECT COUNT(*) c FROM users WHERE role = 'admin' AND is_active = 1 AND id != ?");
            $stmtAdm->bind_param('i', $userId);
            $stmtAdm->execute();
            $adminCount = (int)$stmtAdm->get_result()->fetch_assoc()['c'];
            $stmtAdm->close();
            if ($adminCount === 0) $errors[] = 'Cannot downgrade — at least one active admin is required.';
        }

        if (empty($errors)) {
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $conn->prepare('UPDATE users SET full_name=?, role=?, password=? WHERE id=?');
                $stmt->bind_param('sssi', $fullName, $role, $hash, $userId);
            } else {
                $stmt = $conn->prepare('UPDATE users SET full_name=?, role=? WHERE id=?');
                $stmt->bind_param('ssi', $fullName, $role, $userId);
            }
            if ($stmt->execute()) {
                $success = 'User updated successfully.';
            } else {
                $errors[] = 'Database error: ' . $stmt->error;
            }
            $stmt->close();
        }
    }

    // ── Toggle active status ──────────────────────────────────────────────────
    elseif ($action === 'toggle_active') {
        $userId = valid_id($_POST['user_id'] ?? 0);

        // Cannot deactivate yourself
        if ($userId === (int)current_user()['id']) {
            $errors[] = 'You cannot deactivate your own account.';
        } else {
            // Cannot deactivate last admin
            $stmt = $conn->prepare('SELECT role, is_active FROM users WHERE id = ?');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $target = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($target && $target['role'] === 'admin' && (int)$target['is_active'] === 1) {
                $stmtAdm2 = $conn->prepare("SELECT COUNT(*) c FROM users WHERE role = 'admin' AND is_active = 1 AND id != ?");
                $stmtAdm2->bind_param('i', $userId);
                $stmtAdm2->execute();
                $adminCount = (int)$stmtAdm2->get_result()->fetch_assoc()['c'];
                $stmtAdm2->close();
                if ($adminCount === 0) {
                    $errors[] = 'Cannot deactivate the last active admin.';
                }
            }

            if (empty($errors) && $target) {
                $newState = (int)$target['is_active'] === 1 ? 0 : 1;
                $stmt = $conn->prepare('UPDATE users SET is_active = ? WHERE id = ?');
                $stmt->bind_param('ii', $newState, $userId);
                $stmt->execute();
                $stmt->close();
                $success = 'User status updated.';
            }
        }
    }
}

// ── Load edit form if ?edit=ID ────────────────────────────────────────────────
$editId = valid_id($_GET['edit'] ?? 0);
if ($editId) {
    $stmt = $conn->prepare('SELECT id, username, full_name, role FROM users WHERE id = ?');
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editUser = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// ── Load all users ────────────────────────────────────────────────────────────
// Session is no longer needed — release lock so other requests aren't blocked
$currentUserId = (int)current_user()['id'];
session_write_close();

$users = [];
$result = $conn->query('SELECT id, username, full_name, role, is_active, created_at FROM users ORDER BY created_at ASC');
while ($row = $result->fetch_assoc()) $users[] = $row;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1d4ed8">
    <title>Manage Users — Shoes Inventory</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body<?php if ($success): ?> data-toast-type="success" data-toast-title="Success" data-toast-text="<?= h($success) ?>"<?php endif; ?>>

<?php include '_navbar.php'; ?>

<main class="main-container">

    <div class="page-header">
        <h1><i class="fas fa-users page-header-icon" aria-hidden="true"></i> Manage Users</h1>
        <p>Create and manage staff accounts. Admins have full access; Cashiers can only sell and restock.</p>
    </div>

    <?php if ($errors): ?>
    <div class="alert alert-error" role="alert">
        <i class="fas fa-times-circle" aria-hidden="true"></i>
        <ul class="alert-list">
            <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="users-layout">

        <!-- ── User list ──────────────────────────────────────────────────── -->
        <div class="card">
            <div class="card-header">
                <h2>All Users</h2>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">Full Name</th>
                            <th scope="col">Username</th>
                            <th scope="col">Role</th>
                            <th scope="col">Status</th>
                            <th scope="col">Created</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr class="<?= !(int)$u['is_active'] ? 'row-archived' : '' ?>">
                            <td>
                                <strong><?= h($u['full_name']) ?></strong>
                                <?php if ((int)$u['id'] === $currentUserId): ?>
                                    <span class="badge badge-info">You</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted">@<?= h($u['username']) ?></td>
                            <td>
                                <span class="role-badge role-<?= h($u['role']) ?>">
                                    <?= $u['role'] === 'admin' ? 'Admin' : 'Cashier' ?>
                                </span>
                            </td>
                            <td>
                                <?php if ((int)$u['is_active']): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-gray">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted">
                                <?= date('M d, Y', strtotime($u['created_at'])) ?>
                            </td>
                            <td>
                                <a href="manage_users.php?edit=<?= (int)$u['id'] ?>"
                                   class="btn btn-sm btn-warning btn-icon" title="Edit" aria-label="Edit">
                                    <i class="fas fa-edit" aria-hidden="true"></i>
                                </a>

                                <?php if ((int)$u['id'] !== $currentUserId): ?>
                                <form method="POST" class="form-inline">
                                    <input type="hidden" name="action"  value="toggle_active">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                    <button type="submit"
                                            class="btn btn-sm btn-icon <?= (int)$u['is_active'] ? 'btn-gray' : 'btn-success' ?>"
                                            title="<?= (int)$u['is_active'] ? 'Deactivate' : 'Activate' ?>"
                                            aria-label="<?= (int)$u['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                        <i class="fas <?= (int)$u['is_active'] ? 'fa-ban' : 'fa-check' ?>" aria-hidden="true"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ── Create / Edit form ─────────────────────────────────────────── -->
        <div class="card">
            <div class="card-header">
                <h2><?= $editUser ? 'Edit User' : 'Add New User' ?></h2>
                <?php if ($editUser): ?>
                    <a href="manage_users.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-plus" aria-hidden="true"></i> New User
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="POST" novalidate data-form="user-form">
                    <input type="hidden" name="action"  value="<?= $editUser ? 'update' : 'create' ?>">
                    <?php if ($editUser): ?>
                        <input type="hidden" name="user_id" value="<?= (int)$editUser['id'] ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label class="form-label" for="full_name">
                            Full Name <span class="required" aria-hidden="true">*</span>
                        </label>
                        <input type="text" id="full_name" name="full_name" class="form-control"
                               value="<?= h($editUser['full_name'] ?? '') ?>"
                               placeholder="e.g., Juan dela Cruz" required autocomplete="off">
                    </div>

                    <?php if (!$editUser): ?>
                    <div class="form-group">
                        <label class="form-label" for="username">
                            Username <span class="required" aria-hidden="true">*</span>
                        </label>
                        <input type="text" id="username" name="username" class="form-control"
                               value="<?= h($_POST['username'] ?? '') ?>"
                               placeholder="e.g., juan.delacruz" required autocomplete="off">
                        <small class="form-hint">Unique. Cannot be changed after creation.</small>
                    </div>
                    <?php else: ?>
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <div class="form-control-static">@<?= h($editUser['username']) ?></div>
                        <small class="form-hint">Username cannot be changed.</small>
                    </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label class="form-label" for="role">
                            Role <span class="required" aria-hidden="true">*</span>
                        </label>
                        <select id="role" name="role" class="form-control">
                            <option value="cashier" <?= ($editUser['role'] ?? 'cashier') === 'cashier' ? 'selected' : '' ?>>
                                Cashier — can sell products and add stock
                            </option>
                            <option value="admin" <?= ($editUser['role'] ?? '') === 'admin' ? 'selected' : '' ?>>
                                Admin — full access to all features
                            </option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">
                            <?= $editUser ? 'New Password' : 'Password' ?>
                            <?= !$editUser ? '<span class="required" aria-hidden="true">*</span>' : '' ?>
                        </label>
                        <div class="input-with-icon">
                            <i class="fas fa-lock" aria-hidden="true"></i>
                            <input type="password" id="password" name="password" class="form-control"
                                   placeholder="<?= $editUser ? 'Leave blank to keep current password' : 'At least 6 characters' ?>"
                                   <?= !$editUser ? 'required' : '' ?>
                                   autocomplete="new-password">
                            <button type="button" class="toggle-password" data-action="toggle-password"
                                    data-target="password" aria-label="Show password">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        <small class="form-hint">Minimum 6 characters.</small>
                    </div>

                    <?php if (!$editUser): ?>
                    <div class="form-group">
                        <label class="form-label" for="password2">
                            Confirm Password <span class="required" aria-hidden="true">*</span>
                        </label>
                        <div class="input-with-icon">
                            <i class="fas fa-lock" aria-hidden="true"></i>
                            <input type="password" id="password2" name="password2" class="form-control"
                                   placeholder="Repeat password" required autocomplete="new-password">
                            <button type="button" class="toggle-password" data-action="toggle-password"
                                    data-target="password2" aria-label="Show password">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="form-buttons">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save" aria-hidden="true"></i>
                            <?= $editUser ? ' Update User' : ' Create User' ?>
                        </button>
                        <?php if ($editUser): ?>
                        <a href="manage_users.php" class="btn btn-secondary">
                            <i class="fas fa-times" aria-hidden="true"></i> Cancel
                        </a>
                        <?php endif; ?>
                    </div>

                </form>
            </div>
        </div>

    </div><!-- /.users-layout -->

</main>

<script src="../assets/js/main.js"></script>
</body>
</html>
