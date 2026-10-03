<?php
// Only these roles can acknowledge alarms
require_once __DIR__ . '/../includes/auth.php';
requireRole(['Administrator', 'Network Monitoring Operator', 'Network Engineer']);

require_once __DIR__ . '/../config/database.php';
$pdo = getDatabaseConnection();

// Only accept a POST request with a valid security token
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    die("Invalid request.");
}

// Get the alarm id
$id = (int) ($_POST['id'] ?? 0);

// Change the alarm from Active to Acknowledged
$stmt = $pdo->prepare(
    "UPDATE alarms
     SET status = 'Acknowledged', acknowledged_at = NOW(), acknowledged_by = ?
     WHERE id = ? AND status = 'Active'"
);
$stmt->execute([$_SESSION['user_id'], $id]);

// Save a record in the audit log
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

$stmt = $pdo->prepare(
    "INSERT INTO audit_logs (user_id, action, module, record_id, ip_address, description)
     VALUES (?, 'Alarm Acknowledged', 'alarms', ?, ?, 'Alarm acknowledged')"
);
$stmt->execute([$_SESSION['user_id'], $id, $ip_address]);

// Go back to the alarms list
header('Location: index.php');
exit;