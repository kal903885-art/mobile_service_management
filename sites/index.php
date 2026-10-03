<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['Administrator', 'Network Monitoring Operator', 'Network Engineer', 'Report Viewer']);

require_once __DIR__ . '/../config/database.php';
$pdo = getDatabaseConnection();

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';

$sql = "SELECT s.*, sa.area_name FROM sites s JOIN service_areas sa ON s.service_area_id = sa.id WHERE 1=1";
$params = [];
if ($search !== '') {
    $sql .= " AND (s.site_code LIKE ? OR s.site_name LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
if ($statusFilter !== '') {
    $sql .= " AND s.status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY s.site_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sites = $stmt->fetchAll();

$pageTitle = 'Network Sites';
require_once __DIR__ . '/../includes/staff_header.php';

function siteStatusColor($status) {
    return match ($status) {
        'Online' => 'success', 'Down' => 'danger', 'Degraded' => 'warning',
        'Maintenance', 'Planned' => 'secondary', default => 'secondary'
    };
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"></h5>
    <a href="add.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Add Site</a>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-5"><input type="text" name="search" class="form-control" placeholder="Search site code or name..." value="<?php echo htmlspecialchars($search); ?>"></div>
            <div class="col-md-4">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <?php foreach (['Online','Down','Degraded','Maintenance','Planned'] as $s): ?>
                        <option value="<?php echo $s; ?>" <?php echo $statusFilter === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3"><button class="btn btn-outline-primary w-100">Filter</button></div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($sites)): ?>
            <p class="text-muted p-3 mb-0">No sites yet. Click "Add Site" to create your first one.</p>
        <?php else: ?>
            <table class="table table-hover mb-0">
                <thead><tr><th>Code</th><th>Name</th><th>Area</th><th>Type</th><th>Technology</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($sites as $s): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($s['site_code']); ?></td>
                            <td><?php echo htmlspecialchars($s['site_name']); ?></td>
                            <td><?php echo htmlspecialchars($s['area_name']); ?></td>
                            <td><?php echo htmlspecialchars($s['site_type']); ?></td>
                            <td><?php echo htmlspecialchars($s['technology']); ?></td>
                            <td><span class="badge bg-<?php echo siteStatusColor($s['status']); ?>"><?php echo htmlspecialchars($s['status']); ?></span></td>
                            <td>
                                <a href="view.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                <a href="edit.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>