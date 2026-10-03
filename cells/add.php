<?php
// Only these roles can add cells
require_once __DIR__ . '/../includes/auth.php';
requireRole(['Administrator', 'Network Monitoring Operator']);

require_once __DIR__ . '/../config/database.php';
$pdo = getDatabaseConnection();

// Get the site id (from the link or from the form)
if (isset($_GET['site_id'])) {
    $site_id = (int) $_GET['site_id'];
} elseif (isset($_POST['site_id'])) {
    $site_id = (int) $_POST['site_id'];
} else {
    $site_id = 0;
}

// Find the site in the database
$stmt = $pdo->prepare("SELECT * FROM sites WHERE id = ?");
$stmt->execute([$site_id]);
$site = $stmt->fetch();

if (!$site) {
    die("Site not found.");
}

$errors = [];

// Lists for the dropdowns
$technology_list = ['2G', '3G', '4G', '5G'];
$status_list = ['Online', 'Down', 'Degraded', 'Maintenance'];

// ---------- When the form is submitted ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Get the values from the form
    $cell_name = trim($_POST['cell_name'] ?? '');
    $cell_code = trim($_POST['cell_code'] ?? '');
    $technology = trim($_POST['technology'] ?? '');
    $frequency_band = trim($_POST['frequency_band'] ?? '');
    $sector = (int) ($_POST['sector'] ?? 0);
    $status = trim($_POST['status'] ?? 'Online');

    // Check the required fields
    if ($cell_name === '' || $cell_code === '') {
        $errors[] = "Cell name and code are required.";
    }

    // If there are no errors, save the cell
    if (count($errors) == 0) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO cells (site_id, cell_name, cell_code, technology, frequency_band, sector, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$site_id, $cell_name, $cell_code, $technology, $frequency_band, $sector, $status]);

            // Go back to the site page
            header('Location: /mobile-network-service-management/sites/view.php?id=' . $site_id);
            exit;

        } catch (PDOException $e) {
            $errors[] = "Cell code must be unique — that code may already exist.";
        }
    }
}

$pageTitle = 'Add Cell';
require_once __DIR__ . '/../includes/staff_header.php';
?>

<!-- Show errors -->
<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endforeach; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <p class="text-muted">Site: <strong><?php echo htmlspecialchars($site['site_name']); ?></strong></p>

        <form method="POST">
            <input type="hidden" name="site_id" value="<?php echo $site_id; ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Cell Name *</label>
                    <input type="text" name="cell_name" class="form-control" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Cell Code *</label>
                    <input type="text" name="cell_code" class="form-control" placeholder="CELL-WOL-001-A" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Technology</label>
                    <select name="technology" class="form-select">
                        <?php foreach ($technology_list as $technology): ?>
                            <option value="<?php echo $technology; ?>"><?php echo $technology; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Frequency Band</label>
                    <input type="text" name="frequency_band" class="form-control" placeholder="e.g. Band 3">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Sector</label>
                    <input type="number" name="sector" class="form-control" min="1" max="6" value="1">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php foreach ($status_list as $status_option): ?>
                            <option value="<?php echo $status_option; ?>"><?php echo $status_option; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <button class="btn btn-primary mt-4"><i class="bi bi-check-circle"></i> Save Cell</button>
            <a href="/mobile-network-service-management/sites/view.php?id=<?php echo $site_id; ?>" class="btn btn-outline-secondary mt-4">Cancel</a>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>