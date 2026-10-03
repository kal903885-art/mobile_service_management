<?php
// Only these roles can update complaints
require_once __DIR__ . '/../includes/auth.php';
requireRole(['Administrator', 'Customer Service Agent', 'Network Monitoring Operator']);

require_once __DIR__ . '/../config/database.php';
$pdo = getDatabaseConnection();

// This page only accepts a form submission, otherwise go back to the list
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Check the security token
if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    die("Your session expired. Please go back and try again.");
}

// Get the values from the form
$complaint_id = (int) ($_POST['complaint_id'] ?? 0);
$status = trim($_POST['status'] ?? '');
$priority = trim($_POST['priority'] ?? '');
$message = trim($_POST['message'] ?? '');

// Only these values are allowed
$valid_statuses = [
    'Submitted', 'Received', 'Under Verification', 'Assigned',
    'Under Investigation', 'Escalated', 'Resolved', 'Closed', 'Rejected'
];
$valid_priorities = ['Critical', 'High', 'Medium', 'Low'];

// Check that the id, status and priority are all valid
if ($complaint_id > 0 && in_array($status, $valid_statuses, true) && in_array($priority, $valid_priorities, true)) {

    // Find the customer who owns this complaint
    $stmt = $pdo->prepare("SELECT customer_id FROM customer_complaints WHERE id = ?");
    $stmt->execute([$complaint_id]);
    $customer_id = $stmt->fetchColumn();

    if ($customer_id) {

        // If no note was written, use a default note
        if ($message === '') {
            $message = "Status changed to $status.";
        }

        // Start a transaction: either all the saves work, or none of them
        $pdo->beginTransaction();

        // 1. Update the complaint
        $stmt = $pdo->prepare("UPDATE customer_complaints SET status = ?, priority = ? WHERE id = ?");
        $stmt->execute([$status, $priority, $complaint_id]);

        // 2. Save the update in the history
        $stmt = $pdo->prepare(
            "INSERT INTO complaint_updates (complaint_id, updated_by, status, message) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$complaint_id, $_SESSION['user_id'], $status, $message]);

        // 3. Notify the customer
        $notify_message = "Your complaint status is now: $status.";
        $stmt = $pdo->prepare(
            "INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)"
        );
        $stmt->execute([$customer_id, 'Complaint Update', $notify_message]);

        // 4. Save a record in the audit log
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $description = "Status changed to $status, priority $priority";

        $stmt = $pdo->prepare(
            "INSERT INTO audit_logs (user_id, action, module, record_id, ip_address, description)
             VALUES (?, 'Complaint Update', 'complaints', ?, ?, ?)"
        );
        $stmt->execute([$_SESSION['user_id'], $complaint_id, $ip_address, $description]);

        // Save everything
        $pdo->commit();
    }
}

// Go back to the complaint page
header('Location: view.php?id=' . $complaint_id);
exit;