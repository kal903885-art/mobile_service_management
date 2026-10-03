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
$severity = $_GET['severity'] ?? '';
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Start the query
$sql = "SELECT al.*, s.site_name
        FROM alarms al
        LEFT JOIN sites s ON al.site_id = s.id
        WHERE 1=1";
$params = [];

if ($severity !== '') {
    $sql .= " AND al.severity = :severity";
    $params[':severity'] = $severity;
}
if ($status !== '') {
    $sql .= " AND al.status = :status";
    $params[':status'] = $status;
}
if ($date_from !== '') {
    $sql .= " AND al.occurred_at >= :date_from";
    $params[':date_from'] = $date_from . ' 00:00:00';
}
if ($date_to !== '') {
    $sql .= " AND al.occurred_at <= :date_to";
    $params[':date_to'] = $date_to . ' 23:59:59';
}
$sql .= " ORDER BY al.occurred_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------- CSV export ----------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="alarm_report.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Alarm Code', 'Title', 'Site', 'Severity', 'Status', 'Occurred At', 'Cleared At']);

    foreach ($rows as $row) {
        fputcsv($out, [
            $row['alarm_code'],
            $row['alarm_title'],
            $row['site_name'],
            $row['severity'],
            $row['status'],
            $row['occurred_at'],
            $row['cleared_at']
        ]);
    }

    fclose($out);
    exit;
}

// Lists for the filter dropdowns
$severity_list = ['Critical', 'Major', 'Minor', 'Warning'];
$status_list = ['Active', 'Acknowledged', 'Cleared'];

// Give each severity a badge color
function severityBadgeColor($severity)
{
    if ($severity === 'Critical') {
        return 'danger';
    }
    if ($severity === 'Major') {
        return 'warning';
    }
    if ($severity === 'Minor') {
        return 'info';
    }
    if ($severity === 'Warning') {
        return 'secondary';
    }
    return 'secondary';
}

// Give each status a badge color
function alarmStatusBadgeColor($status)
{
    if ($status === 'Active') {
        return 'danger';
    }
    if ($status === 'Acknowledged') {
        return 'warning';
    }
    if ($status === 'Cleared') {
        return 'success';
    }
    return 'secondary';
}

$pageTitle = 'Alarm Report';
require_once '../includes/staff_header.php';
?>

<!-- Filters -->
<form method="GET" class="row g-2 mb-3">
    <div class="col-md-2">
        <select name="severity" class="form-select">
            <option value="">All Severities</option>
            <?php foreach ($severity_list as $option): ?>
                <?php
                $selected = '';
                if ($severity === $option) {
                    $selected = 'selected';
                }
                ?>
                <option value="<?php echo $option; ?>" <?php echo $selected; ?>><?php echo $option; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select name="status" class="form-select">
            <option value="">All Statuses</option>
            <?php foreach ($status_list as $option): ?>
                <?php
                $selected = '';
                if ($status === $option) {
                    $selected = 'selected';
                }
                ?>
                <option value="<?php echo $option; ?>" <?php echo $selected; ?>><?php echo $option; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
    </div>
    <div class="col-md-2">
        <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
    </div>
    <div class="col-md-2">
        <button class="btn btn-outline-secondary w-100" type="submit">Filter</button>
    </div>
    <div class="col-md-2">
        <?php $export_query = http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>
        <a class="btn btn-success w-100" href="?<?php echo $export_query; ?>">Export CSV</a>
    </div>
</form>

<table class="table table-bordered table-hover bg-white">
    <thead class="table-dark">
        <tr>
            <th>Code</th>
            <th>Title</th>
            <th>Site</th>
            <th>Severity</th>
            <th>Status</th>
            <th>Occurred</th>
            <th>Cleared</th>
        </tr>
    </thead>
    <tbody>
        <?php if (count($rows) == 0): ?>
            <tr><td colspan="7" class="text-center">No alarms found.</td></tr>
        <?php endif; ?>

        <?php foreach ($rows as $row): ?>
            <?php
            $code = $row['alarm_code'];
            if (!$code) {
                $code = '-';
            }

            $title = $row['alarm_title'];
            if (!$title) {
                $title = '-';
            }

            $site_name = $row['site_name'];
            if (!$site_name) {
                $site_name = '-';
            }

            $cleared_at = $row['cleared_at'];
            if (!$cleared_at) {
                $cleared_at = '-';
            }
            ?>
            <tr>
                <td><?php echo htmlspecialchars($code); ?></td>
                <td><?php echo htmlspecialchars($title); ?></td>
                <td><?php echo htmlspecialchars($site_name); ?></td>
                <td>
                    <span class="badge bg-<?php echo severityBadgeColor($row['severity']); ?>">
                        <?php echo htmlspecialchars($row['severity']); ?>
                    </span>
                </td>
                <td>
                    <span class="badge bg-<?php echo alarmStatusBadgeColor($row['status']); ?>">
                        <?php echo htmlspecialchars($row['status']); ?>
                    </span>
                </td>
                <td><?php echo htmlspecialchars($row['occurred_at']); ?></td>
                <td><?php echo htmlspecialchars($cleared_at); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once '../includes/staff_footer.php'; ?>