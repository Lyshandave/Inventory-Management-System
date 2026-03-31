<?php
/**
 * _navbar.php
 * Shared navigation bar. Role-based links.
 *
 * Admin sees:  Dashboard | Add Product | Sales History | Users | Cashier Report
 * Cashier sees: Dashboard only + End Shift + Lock Screen buttons
 */

$currentPage = basename($_SERVER['PHP_SELF']);
$user        = current_user();
$isAdmin     = is_admin();
?>
<nav class="navbar" role="navigation" aria-label="Main navigation">
    <div class="navbar-container">

        <button class="navbar-toggle" aria-label="Toggle navigation menu"
                aria-expanded="false" aria-controls="navbar-nav">
            <span class="hamburger-bar"></span>
            <span class="hamburger-bar"></span>
            <span class="hamburger-bar"></span>
        </button>

        <ul class="navbar-nav" id="navbar-nav" role="list">
            <li>
                <a href="admin.php"
                   class="nav-link<?= $currentPage === 'admin.php' ? ' active' : '' ?>">
                    Dashboard
                </a>
            </li>
            <?php if ($isAdmin): ?>
            <li>
                <a href="add_product.php"
                   class="nav-link<?= $currentPage === 'add_product.php' ? ' active' : '' ?>">
                    Add Product
                </a>
            </li>
            <li>
                <a href="sales_history.php"
                   class="nav-link<?= $currentPage === 'sales_history.php' ? ' active' : '' ?>">
                    Sales History
                </a>
            </li>
            <li>
                <a href="cashier_report.php"
                   class="nav-link<?= $currentPage === 'cashier_report.php' ? ' active' : '' ?>">
                    Cashier Report
                </a>
            </li>
            <li>
                <a href="manage_users.php"
                   class="nav-link<?= $currentPage === 'manage_users.php' ? ' active' : '' ?>">
                    Users
                </a>
            </li>
            <?php endif; ?>
        </ul>

        <div class="navbar-user">

            <?php if (!$isAdmin): ?>
            <!-- Cashier-only actions -->
            <button class="btn btn-navbar-action btn-icon" data-action="open-end-shift"
                    title="End current shift and start a new one" aria-label="End Shift">
                <i class="fas fa-flag-checkered" aria-hidden="true"></i>
            </button>
            <a href="lock.php" class="btn btn-navbar-action btn-icon" title="Lock screen (shift stays active)" aria-label="Lock">
                <i class="fas fa-lock" aria-hidden="true"></i>
            </a>
            <?php endif; ?>

            <span class="navbar-user-name"><?= h($user['full_name']) ?></span>
            <span class="role-badge role-<?= h($user['role']) ?>">
                <?= $isAdmin ? 'Admin' : 'Cashier' ?>
            </span>
            <?php if ($isAdmin): ?>
            <a href="logout.php" class="btn btn-logout btn-icon" title="Sign out" aria-label="Sign out">
                <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
            </a>
            <?php else: ?>
            <button class="btn btn-logout btn-icon" data-action="cashier-logout" title="Sign out" aria-label="Sign out">
                <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
            </button>
            <?php endif; ?>

        </div>
    </div>
</nav>

<!-- Toast notification -->
<div id="toast" class="toast" role="status" aria-live="polite" aria-atomic="true">
    <div class="toast-icon" aria-hidden="true"></div>
    <div class="toast-body">
        <p class="toast-title" id="toastTitle"></p>
        <p class="toast-text"  id="toastText"></p>
    </div>
    <button class="toast-close" data-action="hide-toast" aria-label="Dismiss">
        <i class="fas fa-times" aria-hidden="true"></i>
    </button>
    <div class="toast-bar" aria-hidden="true"></div>
</div>


<!-- End Shift Modal (cashier only) -->
<?php if (!$isAdmin): ?>
<!-- ── End Shift Modal ──────────────────────────────────────────────────────── -->
<div class="modal-overlay hidden" id="endShiftModal" role="dialog" aria-modal="true" aria-labelledby="endShiftTitle">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="endShiftTitle">
                <i class="fas fa-flag-checkered" aria-hidden="true"></i> End Shift
            </h3>
            <button class="modal-close" data-action="close-modal" aria-label="Close">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <div class="modal-body">

            <!-- Pre-confirm: live current shift summary -->
            <div id="shiftPreConfirm">
                <p class="text-muted modal-note-mb">
                    Are you sure you want to end your shift?
                    Your current session summary:
                </p>
                <div class="end-shift-stats">
                    <div class="end-shift-stat">
                        <strong id="shiftTxnCount">—</strong>
                        <span>Transactions</span>
                    </div>
                    <div class="end-shift-stat">
                        <strong id="shiftItemCount">—</strong>
                        <span>Items Sold</span>
                    </div>
                    <div class="end-shift-stat">
                        <strong id="shiftRevenue">—</strong>
                        <span>Revenue</span>
                    </div>
                </div>
            </div>

            <!-- Post-confirm: shift ended summary (hidden until success) -->
            <div id="shiftSummaryBox" class="hidden">
                <div class="shift-summary">
                    <p><strong>Shift ended!</strong> Here is your final summary:</p>
                    <div class="end-shift-stats">
                        <div class="end-shift-stat">
                            <strong id="shiftSumTxn">0</strong>
                            <span>Transactions</span>
                        </div>
                        <div class="end-shift-stat">
                            <strong id="shiftSumItems">0</strong>
                            <span>Items Sold</span>
                        </div>
                        <div class="end-shift-stat">
                            <strong id="shiftSumRevenue">₱0.00</strong>
                            <span>Revenue</span>
                        </div>
                    </div>
                    <p class="shift-summary-time">
                        <i class="fas fa-clock" aria-hidden="true"></i>
                        Time out: <strong id="shiftOutTime">—</strong>
                    </p>
                    <p class="text-muted modal-note-sm">
                        A new shift has started. You are still logged in.
                    </p>
                </div>
            </div>

        </div>
        <div class="modal-footer" id="endShiftFooter">
            <div id="endShiftFooterConfirm">
                <button class="btn btn-secondary" data-action="close-modal">Cancel</button>
                <button class="btn btn-warning" id="endShiftBtn" data-action="submit-end-shift">
                    <i class="fas fa-flag-checkered" aria-hidden="true"></i> End Shift
                </button>
            </div>
            <div id="endShiftFooterDone" class="hidden">
                <button class="btn btn-primary" data-action="close-modal">
                    <i class="fas fa-check" aria-hidden="true"></i> Done
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Cashier Logout Confirmation Modal -->
<?php if (!$isAdmin): ?>
<div class="modal-overlay hidden" id="cashierLogoutModal" role="dialog"
     aria-modal="true" aria-labelledby="cashierLogoutTitle">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="cashierLogoutTitle">
                <i class="fas fa-sign-out-alt" aria-hidden="true"></i> Logout
            </h3>
            <button class="modal-close" data-action="close-modal" aria-label="Close">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <div class="modal-body">

            <!-- Default: show options -->
            <div id="logoutChoiceBox">
                <p class="modal-note-mb">
                    Do you want to <strong>end your shift</strong> before logging out,
                    or just log out directly?
                </p>
                <div class="logout-options">
                    <div class="logout-option" id="logoutOptEndShift">
                        <i class="fas fa-flag-checkered" aria-hidden="true"></i>
                        <div>
                            <strong>End Shift &amp; Logout</strong>
                            <small>Closes your shift properly and saves your sales report.</small>
                        </div>
                    </div>
                    <div class="logout-option logout-option-direct" id="logoutOptDirect">
                        <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                        <div>
                            <strong>Just Logout</strong>
                            <small>Your shift will be closed automatically. No summary shown.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- End shift + logout summary (shown after end-shift AJAX) -->
            <div id="logoutShiftSummaryBox" class="hidden">
                <div class="shift-summary">
                    <p><strong>Shift ended!</strong> Final summary:</p>
                    <div class="end-shift-stats">
                        <div class="end-shift-stat">
                            <strong id="logoutSumTxn">0</strong>
                            <span>Transactions</span>
                        </div>
                        <div class="end-shift-stat">
                            <strong id="logoutSumItems">0</strong>
                            <span>Items Sold</span>
                        </div>
                        <div class="end-shift-stat">
                            <strong id="logoutSumRevenue">&#8369;0.00</strong>
                            <span>Revenue</span>
                        </div>
                    </div>
                    <p class="shift-summary-time">
                        <i class="fas fa-clock" aria-hidden="true"></i>
                        Time out: <strong id="logoutOutTime">—</strong>
                    </p>
                </div>
                <p class="text-muted modal-note-top-sm">
                    Redirecting to login...
                </p>
            </div>

        </div>
        <div class="modal-footer" id="cashierLogoutFooter">
            <button class="btn btn-secondary" data-action="close-modal">Cancel</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Auto-lock warning overlay (cashier only) -->
<?php if (!$isAdmin): ?>
<div id="autoLockOverlay" class="auto-lock-overlay hidden" role="alertdialog"
     aria-modal="true" aria-labelledby="autoLockTitle">
    <div class="auto-lock-box">
        <div class="auto-lock-icon" aria-hidden="true">
            <i class="fas fa-lock"></i>
        </div>
        <h3 id="autoLockTitle">Screen Locking Soon</h3>
        <p>No activity detected. Screen will lock in</p>
        <div class="auto-lock-count" id="autoLockCount">30</div>
        <p class="auto-lock-unit">seconds</p>
        <button class="btn btn-primary" data-action="dismiss-lock-warning">
            <i class="fas fa-unlock" aria-hidden="true"></i> I'm still here
        </button>
    </div>
</div>
<?php endif; ?>
