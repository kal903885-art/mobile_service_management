<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['Administrator', 'Network Monitoring Operator', 'Network Engineer', 'Transmission Engineer']);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
$pdo = getDatabaseConnection();

// Check the security token
if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    die("Your session expired. Please go back and try again.");
}

// Get the values from the form
$incident_id = (int) ($_POST['incident_id'] ?? 0);
$status = trim($_POST['status'] ?? '');
$message = trim($_POST['message'] ?? '');

// Only these statuses can be set from this form
$valid_statuses = ['Open', 'Assigned', 'Investigating', 'In Progress'];

// Check that everything is valid before saving
if ($incident_id && in_array($status, $valid_statuses, true) && $message !== '') {

    // Update the incident's status
    $stmt = $pdo->prepare("UPDATE incidents SET status = ? WHERE id = ?");
    $stmt->execute([$status, $incident_id]);

    // Save the update in the incident's log
    $stmt = $pdo->prepare(
        "INSERT INTO incident_updates (incident_id, updated_by, status, message) VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$incident_id, $_SESSION['user_id'], $status, $message]);

    // Save a record in the audit log
    logAudit($pdo, $_SESSION['user_id'], 'Incident Update', 'incidents', $incident_id, "Status changed to $status. Note: $message");
}

// Go back to the incident page
header('Location: view.php?id=' . $incident_id);
exit;