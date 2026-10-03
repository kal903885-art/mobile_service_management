<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['Administrator', 'Network Monitoring Operator', 'Network Engineer', 'Report Viewer']);

require_once __DIR__ . '/../config/database.php';
$pdo = getDatabaseConnection();

// Get the filters from the URL
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';

// List of statuses for the dropdown
$status_list = ['Online', 'Down', 'Degraded', 'Maintenance', 'Planned'];

// Start the query
$sql = "SELECT s.*, sa.area_name FROM sites s JOIN service_areas sa ON s.service_area_id = sa.id WHERE 1=1";
$params = [];

// Search by site code or name
if ($search !== '') {
    $sql .= " AND (s.site_code LIKE ? OR s.site_name LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
}

// Filter by status
if ($status_filter !== '') {
    $sql .= " AND s.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY s.site_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sites = $stmt->fetchAll();

$pageTitle = 'Network Sites';
require_once __DIR__ . '/../includes/staff_header.php';

// Give each status a badge color
function siteStatusColor($status)
{
    if ($status === 'Online') {
        return 'success';
    }
    if ($status === 'Down') {
        return 'danger';
    }
    if ($status === 'Degraded') {
        return 'warning';
    }
    if ($status === 'Maintenance' || $status === 'Planned') {
        return 'secondary';
    }
    return 'secondary';
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"></h5>
    <a href="add.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Add Site</a>
</div>

<!-- Search and filter form -->
<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control" placeholder="Search site code or name..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-4">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <?php foreach ($status_list as $status): ?>
                        <?php
                        $selected = '';
                        if ($status_filter === $status) {
                            $selected = 'selected';
                        }
                        ?>
                        <option value="<?php echo $status; ?>" <?php echo $selected; ?>><?php echo $status; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-outline-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Sites table -->
<div class="card shadow-sm">
    <div class="card-body p-0">

        <?php if (count($sites) == 0): ?>
            <p class="text-muted p-3 mb-0">No sites yet. Click "Add Site" to create your first one.</p>
        <?php else: ?>
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Area</th>
                        <th>Type</th>
                        <th>Technology</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sites as $site): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($site['site_code']); ?></td>
                            <td><?php echo htmlspecialchars($site['site_name']); ?></td>
                            <td><?php echo htmlspecialchars($site['area_name']); ?></td>
                            <td><?php echo htmlspecialchars($site['site_type']); ?></td>
                            <td><?php echo htmlspecialchars($site['technology']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo siteStatusColor($site['status']); ?>">
                                    <?php echo htmlspecialchars($site['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="view.php?id=<?php echo $site['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                <a href="edit.php?id=<?php echo $site['id']; ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>