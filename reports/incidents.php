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
$status = $_GET['status'] ?? '';
$priority = $_GET['priority'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Start the query
$sql = "SELECT i.*, s.site_name, u.full_name AS engineer_name
        FROM incidents i
        LEFT JOIN sites s ON i.site_id = s.id
        LEFT JOIN users u ON i.assigned_engineer_id = u.id
        WHERE 1=1";
$params = [];

if ($status !== '') {
    $sql .= " AND i.status = :status";
    $params[':status'] = $status;
}
if ($priority !== '') {
    $sql .= " AND i.priority = :priority";
    $params[':priority'] = $priority;
}
if ($date_from !== '') {
    $sql .= " AND i.created_at >= :date_from";
    $params[':date_from'] = $date_from . ' 00:00:00';
}
if ($date_to !== '') {
    $sql .= " AND i.created_at <= :date_to";
    $params[':date_to'] = $date_to . ' 23:59:59';
}
$sql .= " ORDER BY i.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------- CSV export ----------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="incident_report.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Incident #', 'Title', 'Site', 'Priority', 'Status', 'Engineer', 'Created', 'Resolved']);

    foreach ($rows as $row) {
        fputcsv($out, [
            $row['incident_number'],
            $row['title'],
            $row['site_name'],
            $row['priority'],
            $row['status'],
            $row['engineer_name'],
            $row['created_at'],
            $row['resolved_at']
        ]);
    }

    fclose($out);
    exit;
}

// Lists for the filter dropdowns
$status_list = ['Open', 'Assigned', 'Investigating', 'Resolved', 'Closed'];
$priority_list = ['Low', 'Medium', 'High', 'Critical'];

$pageTitle = 'Incident Report';
require_once '../includes/staff_header.php';
?>

<!-- Filters -->
<form method="GET" class="row g-2 mb-3">
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
        <select name="priority" class="form-select">
            <option value="">All Priorities</option>
            <?php foreach ($priority_list as $option): ?>
                <?php
                $selected = '';
                if ($priority === $option) {
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
            <th>#</th>
            <th>Title</th>
            <th>Site</th>
            <th>Priority</th>
            <th>Status</th>
            <th>Engineer</th>
            <th>Created</th>
            <th>Resolved</th>
        </tr>
    </thead>
    <tbody>
        <?php if (count($rows) == 0): ?>
            <tr><td colspan="8" class="text-center">No incidents found.</td></tr>
        <?php endif; ?>

        <?php foreach ($rows as $row): ?>
            <?php
            $title = $row['title'];
            if (!$title) {
                $title = '-';
            }

            $site_name = $row['site_name'];
            if (!$site_name) {
                $site_name = '-';
            }

            $engineer_name = $row['engineer_name'];
            if (!$engineer_name) {
                $engineer_name = 'Unassigned';
            }

            $resolved_at = $row['resolved_at'];
            if (!$resolved_at) {
                $resolved_at = '-';
            }
            ?>
            <tr>
                <td><?php echo htmlspecialchars($row['incident_number']); ?></td>
                <td><?php echo htmlspecialchars($title); ?></td>
                <td><?php echo htmlspecialchars($site_name); ?></td>
                <td><?php echo htmlspecialchars($row['priority']); ?></td>
                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['status']); ?></span></td>
                <td><?php echo htmlspecialchars($engineer_name); ?></td>
                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                <td><?php echo htmlspecialchars($resolved_at); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once '../includes/staff_footer.php'; ?>