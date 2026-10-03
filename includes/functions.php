<?php
// Shared helper functions.

// Generate the next unique complaint number, e.g. CMP-2026-000001
// Uses the current year and looks at the highest existing number for that year.
function generateComplaintNumber($pdo)
{
    $year = date('Y');
    $prefix = "CMP-$year-";

    $stmt = $pdo->prepare(
        "SELECT complaint_number FROM customer_complaints
         WHERE complaint_number LIKE ?
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();

    if ($last) {
        $last_number = (int) substr($last, strlen($prefix));
        $next_number = $last_number + 1;
    } else {
        $next_number = 1;
    }

    return $prefix . str_pad($next_number, 6, '0', STR_PAD_LEFT);
}

// Generate the next unique incident number, e.g. INC-2026-000001
function generateIncidentNumber($pdo)
{
    $year = date('Y');
    $prefix = "INC-$year-";

    $stmt = $pdo->prepare(
        "SELECT incident_number FROM incidents WHERE incident_number LIKE ? ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();

    if ($last) {
        $next_number = (int) substr($last, strlen($prefix)) + 1;
    } else {
        $next_number = 1;
    }

    return $prefix . str_pad($next_number, 6, '0', STR_PAD_LEFT);
}

// Human-friendly badge color for an incident status
function incidentStatusColor($status)
{
    if ($status === 'Open') {
        return 'secondary';
    }
    if ($status === 'Assigned') {
        return 'info';
    }
    if ($status === 'Investigating' || $status === 'In Progress') {
        return 'warning';
    }
    if ($status === 'Resolved') {
        return 'success';
    }
    if ($status === 'Closed') {
        return 'dark';
    }
    return 'secondary';
}

// Human-friendly badge color for a complaint status
function statusBadgeColor($status)
{
    if ($status === 'Submitted' || $status === 'Received') {
        return 'secondary';
    }
    if ($status === 'Under Verification' || $status === 'Assigned') {
        return 'info';
    }
    if ($status === 'Under Investigation' || $status === 'Escalated') {
        return 'warning';
    }
    if ($status === 'Resolved' || $status === 'Closed') {
        return 'success';
    }
    if ($status === 'Rejected') {
        return 'danger';
    }
    return 'secondary';
}

// Human-friendly badge color for priority
function priorityBadgeColor($priority)
{
    if ($priority === 'Critical') {
        return 'danger';
    }
    if ($priority === 'High') {
        return 'warning';
    }
    if ($priority === 'Medium') {
        return 'info';
    }
    if ($priority === 'Low') {
        return 'secondary';
    }
    return 'secondary';
}

// Insert a notification for a single user.
function notifyUser($pdo, $user_id, $title, $message)
{
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $title, $message]);
}

// Insert the same notification for every user holding a given role.
function notifyRole($pdo, $role_name, $title, $message)
{
    $stmt = $pdo->prepare(
        "SELECT u.id FROM users u
         JOIN roles r ON u.role_id = r.id
         WHERE r.role_name = ?"
    );
    $stmt->execute([$role_name]);
    $user_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $insert = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
    foreach ($user_ids as $user_id) {
        $insert->execute([$user_id, $title, $message]);
    }
}

// Record an action in the audit_logs table. Use this for any sensitive
// create/update/delete action across the system.
function logAudit($pdo, $user_id, $action, $module, $record_id, $description)
{
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

    $stmt = $pdo->prepare(
        "INSERT INTO audit_logs (user_id, action, module, record_id, ip_address, description)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$user_id, $action, $module, $record_id, $ip_address, $description]);
}