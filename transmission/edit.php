<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['Administrator', 'Network Monitoring Operator']);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
$pdo = getDatabaseConnection();

// Get the site id (from the link or from the form)
if (isset($_GET['id'])) {
    $id = (int) $_GET['id'];
} elseif (isset($_POST['id'])) {
    $id = (int) $_POST['id'];
} else {
    $id = 0;
}

$errors = [];

// Find the site in the database
$stmt = $pdo->prepare("SELECT * FROM sites WHERE id = ?");
$stmt->execute([$id]);
$site = $stmt->fetch();

if (!$site) {
    die("Site not found.");
}

// Lists for the dropdowns
$site_type_list = ['Macro', 'Micro', 'Rooftop', 'Indoor', 'Tower'];
$technology_list = ['2G', '3G', '4G', '5G', 'Multi-Technology'];
$status_list = ['Online', 'Down', 'Degraded', 'Maintenance', 'Planned'];

// ---------- When the form is submitted ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Check the security token
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = "Your session expired. Please try again.";
    }

    // Get the values from the form
    $site_name = trim($_POST['site_name'] ?? '');
    $service_area_id = (int) ($_POST['service_area_id'] ?? 0);
    $latitude = trim($_POST['latitude'] ?? '');
    $longitude = trim($_POST['longitude'] ?? '');
    $site_type = trim($_POST['site_type'] ?? '');
    $technology = trim($_POST['technology'] ?? '');
    $status = trim($_POST['status'] ?? 'Online');
    $installation_date = $_POST['installation_date'] ?? '';
    $description = trim($_POST['description'] ?? '');

    // These are optional, so use NULL if they are empty
    if ($latitude == '') {
        $latitude = null;
    }
    if ($longitude == '') {
        $longitude = null;
    }
    if ($installation_date == '') {
        $installation_date = null;
    }

    // Check the required field
    if ($site_name === '') {
        $errors[] = "Site name is required.";
    }

    // If there are no errors, update the site
    if (count($errors) == 0) {

        // Note if the status is changing, so we can mention it in the audit log
        $status_changed = ($status !== $site['status']);

        $stmt = $pdo->prepare(
            "UPDATE sites SET site_name=?, service_area_id=?, latitude=?, longitude=?, site_type=?, technology=?, status=?, installation_date=?, description=?
             WHERE id=?"
        );
        $stmt->execute([
            $site_name, $service_area_id, $latitude, $longitude, $site_type, $technology, $status,
            $installation_date, $description, $id
        ]);

        // Save a record in the audit log
        $description_text = "Updated site: $site_name (" . $site['site_code'] . ").";
        if ($status_changed) {
            $description_text = $description_text . " Status changed from " . $site['status'] . " to $status.";
        }
        logAudit($pdo, $_SESSION['user_id'], 'Site Updated', 'sites', $id, $description_text);

        // Go back to the site page
        header('Location: view.php?id=' . $id);
        exit;
    }
}

// Get the service areas for the dropdown
$service_areas = $pdo->query("SELECT id, area_name FROM service_areas ORDER BY area_name")->fetchAll();

$pageTitle = 'Edit Site';
require_once __DIR__ . '/../includes/staff_header.php';
?>

<!-- Show errors -->
<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endforeach; ?>

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
                        <?php foreach ($service_areas as $area): ?>
                            <?php
                            $selected = '';
                            if ($site['service_area_id'] == $area['id']) {
                                $selected = 'selected';
                            }
                            ?>
                            <option value="<?php echo $area['id']; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($area['area_name']); ?></option>
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
                        <?php foreach ($site_type_list as $type): ?>
                            <?php
                            $selected = '';
                            if ($site['site_type'] === $type) {
                                $selected = 'selected';
                            }
                            ?>
                            <option value="<?php echo $type; ?>" <?php echo $selected; ?>><?php echo $type; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Technology</label>
                    <select name="technology" class="form-select">
                        <?php foreach ($technology_list as $tech): ?>
                            <?php
                            $selected = '';
                            if ($site['technology'] === $tech) {
                                $selected = 'selected';
                            }
                            ?>
                            <option value="<?php echo $tech; ?>" <?php echo $selected; ?>><?php echo $tech; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php foreach ($status_list as $status_option): ?>
                            <?php
                            $selected = '';
                            if ($site['status'] === $status_option) {
                                $selected = 'selected';
                            }
                            ?>
                            <option value="<?php echo $status_option; ?>" <?php echo $selected; ?>><?php echo $status_option; ?></option>
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