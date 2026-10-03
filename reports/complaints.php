<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
$pdo = getDatabaseConnection();

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$allowed_roles = ['Administrator', 'Customer Service Agent', 'Report Viewer'];
if (!in_array($_SESSION['role_name'], $allowed_roles)) {
    die("Access denied.");
}

// Get the filters from the URL
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = trim($_GET['search'] ?? '');

// Start the query
$sql = "SELECT cc.*, sa.area_name
        FROM customer_complaints cc
        LEFT JOIN service_areas sa ON cc.service_area_id = sa.id
        WHERE 1=1";
$params = [];

if ($status !== '') {
    $sql .= " AND cc.status = :status";
    $params[':status'] = $status;
}
if ($date_from !== '') {
    $sql .= " AND cc.created_at >= :date_from";
    $params[':date_from'] = $date_from . ' 00:00:00';
}
if ($date_to !== '') {
    $sql .= " AND cc.created_at <= :date_to";
    $params[':date_to'] = $date_to . ' 23:59:59';
}
if ($search !== '') {
    $sql .= " AND (cc.complaint_number LIKE :search1 OR cc.description LIKE :search2)";
    $like = "%$search%";
    $params[':search1'] = $like;
    $params[':search2'] = $like;
}
$sql .= " ORDER BY cc.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------- CSV export ----------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="complaint_report.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Complaint #', 'Area', 'Category', 'Affected Service', 'Priority', 'Status', 'Created At']);

    foreach ($rows as $row) {
        fputcsv($out, [
            $row['complaint_number'],
            $row['area_name'],
            $row['problem_category'],
            $row['affected_service'],
            $row['priority'],
            $row['status'],
            $row['created_at']
        ]);
    }

    fclose($out);
    exit;
}

$status_options = ['Submitted', 'Received', 'Investigating', 'Resolved', 'Closed'];

$pageTitle = 'Customer Complaint Report';
require_once '../includes/staff_header.php';
?>

<!-- Filters -->
<form method="GET" class="row g-2 mb-3">
    <div class="col-md-3">
        <input type="text" name="search" class="form-control" placeholder="Search complaint # or description"
               value="<?php echo htmlspecialchars($search); ?>">
    </div>
    <div class="col-md-2">
        <select name="status" class="form-select">
            <option value="">All Statuses</option>
            <?php foreach ($status_options as $option): ?>
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
    <div class="col-md-1">
        <?php $export_query = http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>
        <a class="btn btn-success w-100" href="?<?php echo $export_query; ?>">CSV</a>
    </div>
</form>

<table class="table table-bordered table-hover bg-white">
    <thead class="table-dark">
        <tr>
            <th>Complaint #</th>
            <th>Area</th>
            <th>Category</th>
            <th>Service</th>
            <th>Priority</th>
            <th>Status</th>
            <th>Created</th>
        </tr>
    </thead>
    <tbody>
        <?php if (count($rows) == 0): ?>
            <tr><td colspan="7" class="text-center">No complaints found.</td></tr>
        <?php endif; ?>

        <?php foreach ($rows as $row): ?>
            <?php
            $area_name = $row['area_name'];
            if (!$area_name) {
                $area_name = '-';
            }

            $category = $row['problem_category'];
            if (!$category) {
                $category = '-';
            }

            $service = $row['affected_service'];
            if (!$service) {
                $service = '-';
            }
            ?>
            <tr>
                <td><?php echo htmlspecialchars($row['complaint_number']); ?></td>
                <td><?php echo htmlspecialchars($area_name); ?></td>
                <td><?php echo htmlspecialchars($category); ?></td>
                <td><?php echo htmlspecialchars($service); ?></td>
                <td><?php echo htmlspecialchars($row['priority']); ?></td>
                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['status']); ?></span></td>
                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once '../includes/staff_footer.php'; ?>