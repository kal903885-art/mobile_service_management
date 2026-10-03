<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['Administrator', 'Network Monitoring Operator', 'Network Engineer', 'Report Viewer']);

require_once __DIR__ . '/../config/database.php';
$pdo = getDatabaseConnection();

$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT s.*, sa.area_name FROM sites s JOIN service_areas sa ON s.service_area_id = sa.id WHERE s.id = ?");
$stmt->execute([$id]);
$site = $stmt->fetch();
if (!$site) die("Site not found.");

$stmt = $pdo->prepare("SELECT * FROM cells WHERE site_id = ? ORDER BY cell_name");
$stmt->execute([$id]);
$cells = $stmt->fetchAll();

// Number of complaints from this site's area (helps investigate)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM customer_complaints WHERE service_area_id = ?");
$stmt->execute([$site['service_area_id']]);
$complaintCount = $stmt->fetchColumn();

function siteStatusColor2($status) {
    return match ($status) {
        'Online' => 'success', 'Down' => 'danger', 'Degraded' => 'warning',
        'Maintenance', 'Planned' => 'secondary', default => 'secondary'
    };
}

$pageTitle = $site['site_name'];
require_once __DIR__ . '/../includes/staff_header.php';
?>

<a href="index.php" class="btn btn-sm btn-outline-secondary mb-3"><i class="bi bi-arrow-left"></i> Back to Sites</a>

<div class="row g-3">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between">
                <span><?php echo htmlspecialchars($site['site_name']); ?> (<?php echo htmlspecialchars($site['site_code']); ?>)</span>
                <span class="badge bg-<?php echo siteStatusColor2($site['status']); ?>"><?php echo htmlspecialchars($site['status']); ?></span>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Area</dt><dd class="col-sm-8"><?php echo htmlspecialchars($site['area_name']); ?></dd>
                    <dt class="col-sm-4">Type</dt><dd class="col-sm-8"><?php echo htmlspecialchars($site['site_type']); ?></dd>
                    <dt class="col-sm-4">Technology</dt><dd class="col-sm-8"><?php echo htmlspecialchars($site['technology']); ?></dd>
                    <dt class="col-sm-4">Coordinates</dt><dd class="col-sm-8"><?php echo htmlspecialchars($site['latitude'] . ', ' . $site['longitude']); ?></dd>
                    <dt class="col-sm-4">Installed</dt><dd class="col-sm-8"><?php echo htmlspecialchars($site['installation_date'] ?: '—'); ?></dd>
                    <dt class="col-sm-4">Description</dt><dd class="col-sm-8"><?php echo nl2br(htmlspecialchars($site['description'])); ?></dd>
                </dl>
                <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Cells</span>
                <a href="/mobile-network-service-management/cells/add.php?site_id=<?php echo $id; ?>" class="btn btn-sm btn-primary">Add Cell</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($cells)): ?>
                    <p class="text-muted p-3 mb-0">No cells added yet for this site.</p>
                <?php else: ?>
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Cell Code</th><th>Name</th><th>Tech</th><th>Sector</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($cells as $c): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($c['cell_code']); ?></td>
                                    <td><?php echo htmlspecialchars($c['cell_name']); ?></td>
                                    <td><?php echo htmlspecialchars($c['technology']); ?></td>
                                    <td><?php echo htmlspecialchars($c['sector']); ?></td>
                                    <td><span class="badge bg-<?php echo siteStatusColor2($c['status']); ?>"><?php echo htmlspecialchars($c['status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-chat-left-text text-primary" style="font-size: 1.75rem;"></i>
                <h3 class="mt-2"><?php echo $complaintCount; ?></h3>
                <p class="text-muted mb-0">Complaints from this area</p>
                <a href="/mobile-network-service-management/complaints/index.php?search=" class="btn btn-sm btn-outline-primary mt-2">View Complaints</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>