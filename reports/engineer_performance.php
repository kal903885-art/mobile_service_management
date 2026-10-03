<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
$pdo = getDatabaseConnection();

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$allowed_roles = ['Administrator', 'Network Monitoring Operator', 'Report Viewer'];
if (!in_array($_SESSION['role_name'], $allowed_roles)) {
    die("Access denied.");
}

// Get the filters from the URL
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Start the query: for each engineer, count assigned incidents, resolved incidents,
// and the average time it took to resolve them
$sql = "SELECT u.id, u.full_name,
               COUNT(i.id) AS total_assigned,
               SUM(CASE WHEN i.status IN ('Resolved','Closed') THEN 1 ELSE 0 END) AS total_resolved,
               ROUND(AVG(CASE WHEN i.resolved_at IS NOT NULL AND i.started_at IS NOT NULL
                    THEN TIMESTAMPDIFF(HOUR, i.started_at, i.resolved_at) END), 1) AS avg_resolution_hours
        FROM users u
        JOIN incidents i ON i.assigned_engineer_id = u.id
        WHERE 1=1";
$params = [];

if ($date_from !== '') {
    $sql .= " AND i.created_at >= :date_from";
    $params[':date_from'] = $date_from . ' 00:00:00';
}
if ($date_to !== '') {
    $sql .= " AND i.created_at <= :date_to";
    $params[':date_to'] = $date_to . ' 23:59:59';
}
$sql .= " GROUP BY u.id, u.full_name ORDER BY total_resolved DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------- CSV export ----------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="engineer_performance_report.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Engineer', 'Total Assigned', 'Total Resolved', 'Avg Resolution Time (hrs)']);

    foreach ($rows as $row) {
        fputcsv($out, [$row['full_name'], $row['total_assigned'], $row['total_resolved'], $row['avg_resolution_hours']]);
    }

    fclose($out);
    exit;
}

$pageTitle = 'Engineer Performance Report';
require_once '../includes/staff_header.php';
?>

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
            <th>Engineer</th>
            <th>Total Assigned</th>
            <th>Total Resolved</th>
            <th>Avg Resolution Time (hrs)</th>
        </tr>
    </thead>
    <tbody>
        <?php if (count($rows) == 0): ?>
            <tr><td colspan="4" class="text-center">No incident data found.</td></tr>
        <?php endif; ?>

        <?php foreach ($rows as $row): ?>
            <?php
            $avg_hours = $row['avg_resolution_hours'];
            if ($avg_hours === null) {
                $avg_hours = '-';
            }
            ?>
            <tr>
                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                <td><?php echo $row['total_assigned']; ?></td>
                <td><?php echo $row['total_resolved']; ?></td>
                <td><?php echo $avg_hours; ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once '../includes/staff_footer.php'; ?>