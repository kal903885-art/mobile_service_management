<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['Administrator', 'Network Monitoring Operator']);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
$pdo = getDatabaseConnection();

// Check the security token
if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    die("Your session expired. Please go back and try again.");
}

// Get the values from the form
$incident_id = (int) ($_POST['incident_id'] ?? 0);
$engineer_id = (int) ($_POST['engineer_id'] ?? 0);

if ($incident_id && $engineer_id) {

    // Assign the incident to the engineer
    $stmt = $pdo->prepare("UPDATE incidents SET assigned_engineer_id = ?, status = 'Assigned' WHERE id = ?");
    $stmt->execute([$engineer_id, $incident_id]);

    // Get the engineer's name for the log and notification
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$engineer_id]);
    $engineer_name = $stmt->fetchColumn();

    // Save the update in the incident's log
    $stmt = $pdo->prepare(
        "INSERT INTO incident_updates (incident_id, updated_by, status, message) VALUES (?, ?, 'Assigned', ?)"
    );
    $stmt->execute([$incident_id, $_SESSION['user_id'], "Assigned to $engineer_name."]);

    // Notify the engineer
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, 'Incident Assigned', ?)");
    $stmt->execute([$engineer_id, "You have been assigned incident #$incident_id."]);

    // Save a record in the audit log
    logAudit($pdo, $_SESSION['user_id'], 'Incident Assigned', 'incidents', $incident_id, "Assigned incident #$incident_id to $engineer_name.");
}

// Go back to the incident page
header('Location: view.php?id=' . $incident_id);
exit;