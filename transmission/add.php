<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['Administrator', 'Network Monitoring Operator']);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
$pdo = getDatabaseConnection();

$errors = [];

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
    $site_code = trim($_POST['site_code'] ?? '');
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

    // Check the required fields
    if ($site_code === '') {
        $errors[] = "Site code is required.";
    }
    if ($site_name === '') {
        $errors[] = "Site name is required.";
    }
    if (!$service_area_id) {
        $errors[] = "Please select a service area.";
    }

    // If there are no errors, save the site
    if (count($errors) == 0) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO sites (site_code, site_name, service_area_id, latitude, longitude, site_type, technology, status, installation_date, description)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $site_code, $site_name, $service_area_id, $latitude, $longitude,
                $site_type, $technology, $status, $installation_date, $description
            ]);

            // Save a record in the audit log
            $new_id = $pdo->lastInsertId();
            logAudit($pdo, $_SESSION['user_id'], 'Site Added', 'sites', $new_id, "Added site: $site_name ($site_code).");

            // Go back to the sites list
            header('Location: index.php');
            exit;

        } catch (PDOException $e) {
            $errors[] = "Site code must be unique — that code may already exist.";
        }
    }
}

// Get the service areas for the dropdown
$service_areas = $pdo->query("SELECT id, area_name FROM service_areas ORDER BY area_name")->fetchAll();

$pageTitle = 'Add Site';
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

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Site Code *</label>
                    <input type="text" name="site_code" class="form-control" placeholder="SITE-WOL-001" required
                           value="<?php echo htmlspecialchars($_POST['site_code'] ?? ''); ?>">
                </div>

                <div class="col-md-8">
                    <label class="form-label">Site Name *</label>
                    <input type="text" name="site_name" class="form-control" required
                           value="<?php echo htmlspecialchars($_POST['site_name'] ?? ''); ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Service Area *</label>
                    <select name="service_area_id" class="form-select" required>
                        <option value="">-- Select --</option>
                        <?php foreach ($service_areas as $area): ?>
                            <option value="<?php echo $area['id']; ?>"><?php echo htmlspecialchars($area['area_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Latitude</label>
                    <input type="text" name="latitude" class="form-control" placeholder="11.8333">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Longitude</label>
                    <input type="text" name="longitude" class="form-control" placeholder="39.6000">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Site Type</label>
                    <select name="site_type" class="form-select">
                        <?php foreach ($site_type_list as $type): ?>
                            <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Technology</label>
                    <select name="technology" class="form-select">
                        <?php foreach ($technology_list as $tech): ?>
                            <option value="<?php echo $tech; ?>"><?php echo $tech; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php foreach ($status_list as $status_option): ?>
                            <option value="<?php echo $status_option; ?>"><?php echo $status_option; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Installation Date</label>
                    <input type="date" name="installation_date" class="form-control">
                </div>

                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>
            </div>

            <button class="btn btn-primary mt-4"><i class="bi bi-check-circle"></i> Save Site</button>
            <a href="index.php" class="btn btn-outline-secondary mt-4">Cancel</a>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>