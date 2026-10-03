<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole(['Administrator', 'Network Monitoring Operator']);

require_once __DIR__ . '/../config/database.php';
$pdo = getDatabaseConnection();

$errors = [];

// List of alarm titles to choose from
$alarm_titles = [
    'Site Power Failure',
    'Transmission Link Down',
    'Cell Unavailable',
    'High Traffic',
    'Low Signal',
    'BBU Failure',
    'RRU Failure',
    'Battery Low',
    'High Temperature',
    'Microwave Link Failure'
];

// List of severity levels
$severity_list = ['Critical', 'Major', 'Minor', 'Warning'];

// ---------- When the form is submitted ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Get the values from the form
    $site_id = (int) ($_POST['site_id'] ?? 0);
    $cell_id = (int) ($_POST['cell_id'] ?? 0);
    $title = trim($_POST['alarm_title'] ?? '');
    $severity = trim($_POST['severity'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // Cell is optional, so use NULL if nothing was picked
    if ($cell_id == 0) {
        $cell_id = null;
    }

    // Check the values
    if ($site_id == 0) {
        $errors[] = "Please select a site.";
    }
    if ($title === '') {
        $errors[] = "Please select an alarm title.";
    }
    if ($severity === '') {
        $errors[] = "Please select a severity.";
    }

    // Check the security token
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = "Your session expired. Please try again.";
    }

    // If there are no errors, save the alarm
    if (count($errors) == 0) {

        // Make a random alarm code like ALM-A1B2C3
        $random_part = strtoupper(substr(md5(uniqid()), 0, 6));
        $alarm_code = 'ALM-' . $random_part;

        $stmt = $pdo->prepare(
            "INSERT INTO alarms (site_id, cell_id, alarm_code, alarm_title, description, severity, status)
             VALUES (?, ?, ?, ?, ?, ?, 'Active')"
        );
        $stmt->execute([$site_id, $cell_id, $alarm_code, $title, $description, $severity]);

        // Save a record in the audit log
        $new_id = $pdo->lastInsertId();
        logAudit($pdo, $_SESSION['user_id'], 'Alarm Raised', 'alarms', $new_id, "Raised $severity alarm: $title.");

        // For critical alarms, send a notification
        if ($severity === 'Critical') {

            // Get the site name for the message
            $stmt = $pdo->prepare("SELECT site_name FROM sites WHERE id = ?");
            $stmt->execute([$site_id]);
            $site_name = $stmt->fetchColumn();

            $notify_message = "$title at $site_name ($alarm_code).";

            notifyRole($pdo, 'Network Monitoring Operator', 'Critical Alarm', $notify_message);
            notifyRole($pdo, 'Network Engineer', 'Critical Alarm', $notify_message);
        }

        // Go back to the alarms list
        header('Location: index.php');
        exit;
    }
}

// Get the sites for the dropdown
$sites = $pdo->query("SELECT id, site_name FROM sites ORDER BY site_name")->fetchAll();

$pageTitle = 'Raise Sample Alarm';
require_once __DIR__ . '/../includes/staff_header.php';
?>

<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle"></i>
    In a real system, alarms are raised automatically by network equipment. This form is provided for demonstration/testing since we don't have live equipment feeding this system.
</div>

<!-- Show errors -->
<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endforeach; ?>

<div class="card shadow-sm" style="max-width: 600px;">
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">

            <div class="mb-3">
                <label class="form-label">Site *</label>
                <select name="site_id" class="form-select" required>
                    <option value="">-- Select Site --</option>
                    <?php foreach ($sites as $site): ?>
                        <option value="<?php echo $site['id']; ?>"><?php echo htmlspecialchars($site['site_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Alarm Title *</label>
                <select name="alarm_title" class="form-select" required>
                    <option value="">-- Select --</option>
                    <?php foreach ($alarm_titles as $alarm_title): ?>
                        <option value="<?php echo htmlspecialchars($alarm_title); ?>"><?php echo htmlspecialchars($alarm_title); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Severity *</label>
                <select name="severity" class="form-select" required>
                    <?php foreach ($severity_list as $level): ?>
                        <option value="<?php echo $level; ?>"><?php echo $level; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2"></textarea>
            </div>

            <button class="btn btn-primary w-100"><i class="bi bi-bell"></i> Raise Alarm</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>