<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['Administrator', 'Network Monitoring Operator']);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
$pdo = getDatabaseConnection();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$errors = [];

$stmt = $pdo->prepare("SELECT * FROM sites WHERE id = ?");
$stmt->execute([$id]);
$site = $stmt->fetch();
if (!$site) die("Site not found.");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = "Your session expired. Please try again.";
    }

    $siteName = trim($_POST['site_name'] ?? '');
    $serviceAreaId = (int) ($_POST['service_area_id'] ?? 0);
    $latitude = trim($_POST['latitude'] ?? '');
    $longitude = trim($_POST['longitude'] ?? '');
    $siteType = trim($_POST['site_type'] ?? '');
    $technology = trim($_POST['technology'] ?? '');
    $status = trim($_POST['status'] ?? 'Online');
    $installationDate = $_POST['installation_date'] ?? '';
    $description = trim($_POST['description'] ?? '');

    if ($siteName === '') $errors[] = "Site name is required.";

    if (empty($errors)) {
        $statusChanged = ($status !== $site['status']);

        $stmt = $pdo->prepare(
            "UPDATE sites SET site_name=?, service_area_id=?, latitude=?, longitude=?, site_type=?, technology=?, status=?, installation_date=?, description=?
             WHERE id=?"
        );
        $stmt->execute([
            $siteName, $serviceAreaId, $latitude ?: null, $longitude ?: null, $siteType, $technology, $status,
            $installationDate ?: null, $description, $id
        ]);

        $desc = "Updated site: $siteName ({$site['site_code']}).";
        if ($statusChanged) {
            $desc .= " Status changed from {$site['status']} to $status.";
        }
        logAudit($pdo, $_SESSION['user_id'], 'Site Updated', 'sites', $id, $desc);

        header('Location: view.php?id=' . $id);
        exit;
    }
}

$serviceAreas = $pdo->query("SELECT id, area_name FROM service_areas ORDER BY area_name")->fetchAll();

$pageTitle = 'Edit Site';
require_once __DIR__ . '/../includes/staff_header.php';
?>

<?php foreach ($errors as $e): ?><div class="alert alert-danger"><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Site Code</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($site['site_code']); ?>" disabled>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Site Name *</label>
                    <input type="text" name="site_name" class="form-control" required value="<?php echo htmlspecialchars($site['site_name']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Service Area</label>
                    <select name="service_area_id" class="form-select">
                        <?php foreach ($serviceAreas as $a): ?>
                            <option value="<?php echo $a['id']; ?>" <?php echo $site['service_area_id'] == $a['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($a['area_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Latitude</label>
                    <input type="text" name="latitude" class="form-control" value="<?php echo htmlspecialchars($site['latitude']); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Longitude</label>
                    <input type="text" name="longitude" class="form-control" value="<?php echo htmlspecialchars($site['longitude']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Site Type</label>
                    <select name="site_type" class="form-select">
                        <?php foreach (['Macro','Micro','Rooftop','Indoor','Tower'] as $t): ?>
                            <option value="<?php echo $t; ?>" <?php echo $site['site_type'] === $t ? 'selected' : ''; ?>><?php echo $t; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Technology</label>
                    <select name="technology" class="form-select">
                        <?php foreach (['2G','3G','4G','5G','Multi-Technology'] as $t): ?>
                            <option value="<?php echo $t; ?>" <?php echo $site['technology'] === $t ? 'selected' : ''; ?>><?php echo $t; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php foreach (['Online','Down','Degraded','Maintenance','Planned'] as $s): ?>
                            <option value="<?php echo $s; ?>" <?php echo $site['status'] === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Installation Date</label>
                    <input type="date" name="installation_date" class="form-control" value="<?php echo htmlspecialchars($site['installation_date']); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($site['description']); ?></textarea>
                </div>
            </div>
            <button class="btn btn-primary mt-4"><i class="bi bi-check-circle"></i> Save Changes</button>
            <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary mt-4">Cancel</a>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>