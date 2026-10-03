<?php
// Only administrators can use this page
require_once __DIR__ . '/../includes/auth.php';
requireRole(['Administrator']);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
$pdo = getDatabaseConnection();

// Check the security token
if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    die("Your session expired. Please try again.");
}

// Get the id of the user we want to change
$user_id = (int) ($_POST['user_id'] ?? 0);

// An admin cannot deactivate their own account
if ($user_id != 0 && $user_id != $_SESSION['user_id']) {

    // Find the user
    $stmt = $pdo->prepare("SELECT full_name, is_active FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if ($user) {
        // Flip the status: active becomes inactive, inactive becomes active
        if ($user['is_active'] == 1) {
            $new_status = 0;
            $action = 'User Deactivated';
        } else {
            $new_status = 1;
            $action = 'User Activated';
        }

        // Save the new status
        $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        $stmt->execute([$new_status, $user_id]);

        // Save a record in the audit log
        $description = $user['full_name'] . " account " . $action . ".";
        logAudit($pdo, $_SESSION['user_id'], $action, 'users', $user_id, $description);
    }
}

// Go back to the users list
header('Location: users.php');
exit;