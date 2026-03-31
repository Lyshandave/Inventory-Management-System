<?php
/**
 * logout.php
 * Ends the current shift (if cashier), destroys session, redirects to login.
 */

require_once '../config.php';

session_init();

// Close any open shift before logging out
$shiftId = current_shift_id();
if ($shiftId > 0) {
    end_shift($shiftId);
}

session_unset();
session_destroy();

redirect_to('../index.php');
