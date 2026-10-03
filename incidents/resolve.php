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
$resolution = trim($_POST['resolution'] ?? '');

if ($incident_id && $resolution !== '') {

    // Start a transaction: either all the saves work, or none of them
    $pdo->beginTransaction();

    // Find the area this incident affected
    $stmt = $pdo->prepare("SELECT service_area_id FROM incidents WHERE id = ?");
    $stmt->execute([$incident_id]);
    $service_area_id = $stmt->fetchColumn();

    // 1. Mark the incident as resolved
    $stmt = $pdo->prepare(
        "UPDATE incidents SET status = 'Resolved', resolution = ?, resolved_at = NOW() WHERE id = ?"
    );
    $stmt->execute([$resolution, $incident_id]);

    // 2. Save the update in the incident's log
    $stmt = $pdo->prepare(
        "INSERT INTO incident_updates (incident_id, updated_by, status, message) VALUES (?, ?, 'Resolved', ?)"
    );
    $stmt->execute([$incident_id, $_SESSION['user_id'], "Resolved: $resolution"]);

    // 3. Resolve any complaints that were escalated because of this incident's area
    if ($service_area_id) {
        $stmt = $pdo->prepare(
            "SELECT id, customer_id FROM customer_complaints WHERE service_area_id = ? AND status = 'Escalated'"
        );
        $stmt->execute([$service_area_id]);
        $affected_complaints = $stmt->fetchAll();

        foreach ($affected_complaints as $complaint) {
            $stmt = $pdo->prepare("UPDATE customer_complaints SET status = 'Resolved' WHERE id = ?");
            $stmt->execute([$complaint['id']]);

            $stmt = $pdo->prepare(
                "INSERT INTO complaint_updates (complaint_id, updated_by, status, message) VALUES (?, ?, 'Resolved', ?)"
            );
            $stmt->execute([$complaint['id'], $_SESSION['user_id'], "Resolved as part of network incident resolution: $resolution"]);

            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, 'Complaint Resolved', ?)");
            $stmt->execute([$complaint['customer_id'], "Your complaint has been resolved: $resolution"]);
        }
    }

    // 4. Save a record in the audit log
    logAudit($pdo, $_SESSION['user_id'], 'Incident Resolved', 'incidents', $incident_id, "Resolved incident #$incident_id: $resolution");

    // Save everything
    $pdo->commit();
}

// Go back to the incident page
header('Location: view.php?id=' . $incident_id);
exit;