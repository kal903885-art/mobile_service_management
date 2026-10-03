<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
$pdo = getDatabaseConnection();

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$allowed_roles = ['Administrator', 'Network Monitoring Operator', 'Network Engineer', 'Transmission Engineer', 'Report Viewer'];
if (!in_array($_SESSION['role_name'], $allowed_roles)) {
    die("Access denied.");
}

// Get the filters from the URL
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// An "outage" is an incident that has a linked site and has been started (started_at set)
$sql = "SELECT i.incident_number, i.title, s.site_name, i.priority, i.status,
               i.started_at, i.resolved_at,
               CASE WHEN i.resolved_at IS NOT NULL AND i.started_at IS NOT NULL
                    THEN TIMESTAMPDIFF(MINUTE, i.started_at, i.resolved_at)
                    ELSE NULL END AS downtime_minutes
        FROM incidents i
        JOIN sites s ON i.site_id = s.id
        WHERE i.started_at IS NOT NULL";
$params = [];

if ($date_from !== '') {
    $sql .= " AND i.started_at >= :date_from";
    $params[':date_from'] = $date_from . ' 00:00:00';
}
if ($date_to !== '') {
    $sql .= " AND i.started_at <= :date_to";
    $params[':date_to'] = $date_to . ' 23:59:59';
}
$sql .= " ORDER BY i.started_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------- CSV export ----------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="service_outage_report.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Incident #', 'Title', 'Site', 'Priority', 'Status', 'Started', 'Resolved', 'Downtime (minutes)']);

    foreach ($rows as $row) {
        fputcsv($out, [
            $row['incident_number'],
            $row['title'],
            $row['site_name'],
            $row['priority'],
            $row['status'],
            $row['started_at'],
            $row['resolved_at'],
            $row['downtime_minutes']
        ]);
    }

    fclose($out);
    exit;
}

$pageTitle = 'Service Outage Report';
require_once '../includes/staff_header.php';
?>

<div class="alert alert-info">An outage is defined here as an incident that has been actively started (has a "started at" timestamp) at a specific site. Downtime is calculated as the time between start and resolution.</div>

<!-- Filters -->
<form method="GET" class="row g-2 mb-3">
    <div class="col-md-3">
        <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
    </div>
    <div class="col-md-3">
        <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
    </div>
    <div class="col-md-3">
        <button class="btn btn-outline-secondary w-100" type="submit">Filter</button>
    </div>
    <div class="col-md-3">
        <?php $export_query = http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>
        <a class="btn btn-success w-100" href="?<?php echo $export_query; ?>">Export CSV</a>
    </div>
</form>

<table class="table table-bordered table-hover bg-white">
    <thead class="table-dark">
        <tr>
            <th>#</th>
            <th>Title</th>
            <th>Site</th>
            <th>Priority</th>
            <th>Status</th>
            <th>Started</th>
            <th>Resolved</th>
            <th>Downtime</th>
        </tr>
    </thead>
    <tbody>
        <?php if (count($rows) == 0): ?>
            <tr><td colspan="8" class="text-center">No outages found.</td></tr>
        <?php endif; ?>

        <?php foreach ($rows as $row): ?>
            <?php
            $title = $row['title'];
            if (!$title) {
                $title = '-';
            }

            $resolved_text = $row['resolved_at'];
            if (!$resolved_text) {
                $resolved_text = 'Ongoing';
            }

            // Work out the downtime, in hours and minutes
            $downtime_minutes = $row['downtime_minutes'];
            ?>
            <tr>
                <td><?php echo htmlspecialchars($row['incident_number']); ?></td>
                <td><?php echo htmlspecialchars($title); ?></td>
                <td><?php echo htmlspecialchars($row['site_name']); ?></td>
                <td><?php echo htmlspecialchars($row['priority']); ?></td>
                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['status']); ?></span></td>
                <td><?php echo htmlspecialchars($row['started_at']); ?></td>
                <td><?php echo htmlspecialchars($resolved_text); ?></td>
                <td>
                    <?php if ($downtime_minutes !== null): ?>
                        <?php
                        $hours = floor($downtime_minutes / 60);
                        $minutes = $downtime_minutes % 60;
                        ?>
                        <?php echo $hours; ?>h <?php echo $minutes; ?>m
                    <?php else: ?>
                        <span class="text-danger">Ongoing</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once '../includes/staff_footer.php'; ?>