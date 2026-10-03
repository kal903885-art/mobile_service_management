<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['Administrator', 'Network Monitoring Operator']);

require_once __DIR__ . '/../config/database.php';
$pdo = getDatabaseConnection();

$message = '';

// Lists for the dropdowns
$technology_list = ['2G', '3G', '4G', '5G'];
$status_list = ['Normal', 'Degraded', 'Unavailable', 'Maintenance', 'Planned Outage'];

// ---------- When the form is submitted ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Get the values from the form
    $service_area_id = (int) ($_POST['service_area_id'] ?? 0);
    $service_id = (int) ($_POST['service_id'] ?? 0);
    $technology = trim($_POST['technology'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $expected_resolution = $_POST['expected_resolution'] ?? '';

    // Expected resolution is optional, so use NULL if it is empty
    if ($expected_resolution == '') {
        $expected_resolution = null;
    }

    // Only save if the required fields are filled
    if ($service_area_id && $service_id && $status) {
        $stmt = $pdo->prepare(
            "INSERT INTO service_status (service_area_id, service_id, technology, status, reason, start_time, expected_resolution, updated_by)
             VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)"
        );
        $stmt->execute([$service_area_id, $service_id, $technology, $status, $reason, $expected_resolution, $_SESSION['user_id']]);

        $message = "Service status updated.";
    }
}

// Get the data needed for the dropdowns and the table
$service_areas = $pdo->query("SELECT id, area_name FROM service_areas ORDER BY area_name")->fetchAll();
$services = $pdo->query("SELECT id, service_name FROM services ORDER BY service_name")->fetchAll();

$current_statuses = $pdo->query(
    "SELECT ss.*, sa.area_name, s.service_name, u.full_name AS updated_by_name
     FROM service_status ss
     JOIN service_areas sa ON ss.service_area_id = sa.id
     JOIN services s ON ss.service_id = s.id
     LEFT JOIN users u ON ss.updated_by = u.id
     ORDER BY ss.updated_at DESC LIMIT 50"
)->fetchAll();

$pageTitle = 'Service Status Management';
require_once __DIR__ . '/../includes/staff_header.php';
?>

<!-- Success message -->
<?php if ($message != ''): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="alert alert-info">
    <i class="bi bi-info-circle"></i> This updates the <strong>simulated</strong> service status shown to customers. It does not affect any real network equipment.
</div>

<div class="row g-3">

    <!-- Form to set a new status -->
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header">Set Service Status</div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Area</label>
                        <select name="service_area_id" class="form-select" required>
                            <?php foreach ($service_areas as $area): ?>
                                <option value="<?php echo $area['id']; ?>"><?php echo htmlspecialchars($area['area_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Service</label>
                        <select name="service_id" class="form-select" required>
                            <?php foreach ($services as $service): ?>
                                <option value="<?php echo $service['id']; ?>"><?php echo htmlspecialchars($service['service_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Technology</label>
                        <select name="technology" class="form-select">
                            <option value="">—</option>
                            <?php foreach ($technology_list as $tech): ?>
                                <option value="<?php echo $tech; ?>"><?php echo $tech; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" required>
                            <?php foreach ($status_list as $status_option): ?>
                                <option value="<?php echo $status_option; ?>"><?php echo $status_option; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <input type="text" name="reason" class="form-control" placeholder="e.g. High network load">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Expected Resolution</label>
                        <input type="datetime-local" name="expected_resolution" class="form-control">
                    </div>

                    <button class="btn btn-primary w-100">Save Status</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Table of recent status changes -->
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header">Recent Status Changes</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Area</th>
                            <th>Service</th>
                            <th>Tech</th>
                            <th>Status</th>
                            <th>Reason</th>
                            <th>Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($current_statuses as $current): ?>
                            <?php
                            // Choose the color: Normal = green, Degraded = yellow, anything else = red
                            $color = 'danger';
                            if ($current['status'] === 'Normal') {
                                $color = 'success';
                            } elseif ($current['status'] === 'Degraded') {
                                $color = 'warning';
                            }

                            // Show a dash if there is no technology
                            $technology_text = $current['technology'];
                            if (!$technology_text) {
                                $technology_text = '—';
                            }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($current['area_name']); ?></td>
                                <td><?php echo htmlspecialchars($current['service_name']); ?></td>
                                <td><?php echo htmlspecialchars($technology_text); ?></td>
                                <td><span class="badge bg-<?php echo $color; ?>"><?php echo htmlspecialchars($current['status']); ?></span></td>
                                <td><?php echo htmlspecialchars($current['reason']); ?></td>
                                <td><?php echo date('M j, g:i A', strtotime($current['updated_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>