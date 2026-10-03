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

// Start the query: average performance numbers per site
$sql = "SELECT s.id, s.site_code, s.site_name,
               ROUND(AVG(np.availability), 2) AS avg_availability,
               ROUND(AVG(np.signal_strength), 2) AS avg_signal,
               ROUND(AVG(np.call_success_rate), 2) AS avg_success_rate,
               ROUND(AVG(np.drop_rate), 2) AS avg_drop_rate,
               COUNT(np.id) AS sample_count
        FROM sites s
        LEFT JOIN network_performance np ON np.site_id = s.id";
$conditions = [];
$params = [];

if ($date_from !== '') {
    $conditions[] = "np.recorded_date >= :date_from";
    $params[':date_from'] = $date_from;
}
if ($date_to !== '') {
    $conditions[] = "np.recorded_date <= :date_to";
    $params[':date_to'] = $date_to;
}
if (count($conditions) > 0) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
}
$sql .= " GROUP BY s.id, s.site_code, s.site_name ORDER BY avg_availability ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------- CSV export ----------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="network_availability_report.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Site Code', 'Site Name', 'Avg Availability %', 'Avg Signal', 'Avg Call Success %', 'Avg Drop Rate %', 'Samples']);

    foreach ($rows as $row) {
        fputcsv($out, [
            $row['site_code'],
            $row['site_name'],
            $row['avg_availability'],
            $row['avg_signal'],
            $row['avg_success_rate'],
            $row['avg_drop_rate'],
            $row['sample_count']
        ]);
    }

    fclose($out);
    exit;
}

$pageTitle = 'Network Availability Report';
require_once '../includes/staff_header.php';
?>

<div class="alert alert-info">Averages are calculated from recorded network performance samples (Phase 10 simulated data). Sites with no samples in the selected range show blank values.</div>

<!-- Filters -->
<form method="GET" class="row g-2 mb-3">
    <div class="col-md-3">
        <label class="form-label">From</label>
        <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label">To</label>
        <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
    </div>
    <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-outline-secondary w-100" type="submit">Filter</button>
    </div>
    <div class="col-md-2 d-flex align-items-end">
        <?php $export_query = http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>
        <a class="btn btn-success w-100" href="?<?php echo $export_query; ?>">Export CSV</a>
    </div>
</form>

<table class="table table-bordered table-hover bg-white">
    <thead class="table-dark">
        <tr>
            <th>Site</th>
            <th>Avg Availability</th>
            <th>Avg Signal</th>
            <th>Avg Call Success</th>
            <th>Avg Drop Rate</th>
            <th>Samples</th>
        </tr>
    </thead>
    <tbody>
        <?php if (count($rows) == 0): ?>
            <tr><td colspan="6" class="text-center">No data found.</td></tr>
        <?php endif; ?>

        <?php foreach ($rows as $row): ?>
            <?php
            // Decide the availability badge color
            $availability_badge = '';
            if ($row['avg_availability'] !== null) {
                if ($row['avg_availability'] >= 95) {
                    $availability_badge = 'success';
                } elseif ($row['avg_availability'] >= 85) {
                    $availability_badge = 'warning';
                } else {
                    $availability_badge = 'danger';
                }
            }

            $avg_signal = $row['avg_signal'];
            if ($avg_signal === null) {
                $avg_signal = '-';
            }

            $avg_success_rate = $row['avg_success_rate'];
            if ($avg_success_rate !== null) {
                $avg_success_rate = $avg_success_rate . '%';
            } else {
                $avg_success_rate = '-';
            }

            $avg_drop_rate = $row['avg_drop_rate'];
            if ($avg_drop_rate !== null) {
                $avg_drop_rate = $avg_drop_rate . '%';
            } else {
                $avg_drop_rate = '-';
            }
            ?>
            <tr>
                <td><?php echo htmlspecialchars($row['site_code'] . ' - ' . $row['site_name']); ?></td>
                <td>
                    <?php if ($row['avg_availability'] !== null): ?>
                        <span class="badge bg-<?php echo $availability_badge; ?>"><?php echo $row['avg_availability']; ?>%</span>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td><?php echo $avg_signal; ?></td>
                <td><?php echo $avg_success_rate; ?></td>
                <td><?php echo $avg_drop_rate; ?></td>
                <td><?php echo $row['sample_count']; ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once '../includes/staff_footer.php'; ?>