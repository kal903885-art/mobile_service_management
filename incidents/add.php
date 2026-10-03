<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['Administrator', 'Network Monitoring Operator', 'Customer Service Agent']);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
$pdo = getDatabaseConnection();

$errors = [];
$prefill = null;

// Get the complaint id (from the link or from the form), if this incident is being created from a complaint
if (isset($_GET['complaint_id'])) {
    $complaint_id = (int) $_GET['complaint_id'];
} elseif (isset($_POST['complaint_id'])) {
    $complaint_id = (int) $_POST['complaint_id'];
} else {
    $complaint_id = 0;
}

// If there is a complaint id, load its details to pre-fill the form
if ($complaint_id) {
    $stmt = $pdo->prepare(
        "SELECT cc.*, sa.area_name, sa.id AS service_area_id
         FROM customer_complaints cc
         JOIN service_areas sa ON cc.service_area_id = sa.id
         WHERE cc.id = ?"
    );
    $stmt->execute([$complaint_id]);
    $prefill = $stmt->fetch();
}

// Lists for the dropdowns
$technology_list = ['2G', '3G', '4G', '5G'];
$priority_list = ['Critical', 'High', 'Medium', 'Low'];

// ---------- When the form is submitted ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Get the values from the form
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $service_area_id = (int) ($_POST['service_area_id'] ?? 0);
    $site_id = (int) ($_POST['site_id'] ?? 0);
    $technology = trim($_POST['technology'] ?? '');
    $priority = trim($_POST['priority'] ?? 'Medium');
    $related_alarm_id = (int) ($_POST['related_alarm_id'] ?? 0);

    // These are optional, so use NULL if nothing was chosen
    if ($service_area_id == 0) {
        $service_area_id = null;
    }
    if ($site_id == 0) {
        $site_id = null;
    }
    if ($related_alarm_id == 0) {
        $related_alarm_id = null;
    }

    // Check the required fields
    if ($title === '') {
        $errors[] = "Title is required.";
    }
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = "Your session expired. Please try again.";
    }

    // If there are no errors, create the incident
    if (count($errors) == 0) {

        // Start a transaction: either all the saves work, or none of them
        $pdo->beginTransaction();

        $incident_number = generateIncidentNumber($pdo);

        // 1. Save the incident
        $stmt = $pdo->prepare(
            "INSERT INTO incidents (incident_number, title, description, service_area_id, site_id, technology,
                                     related_complaint_id, related_alarm_id, priority, status, started_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Open', NOW())"
        );

        // If there was no complaint, save NULL instead of 0
        $related_complaint_id = $complaint_id;
        if ($related_complaint_id == 0) {
            $related_complaint_id = null;
        }

        $stmt->execute([
            $incident_number, $title, $description, $service_area_id, $site_id, $technology,
            $related_complaint_id, $related_alarm_id, $priority
        ]);
        $incident_id = $pdo->lastInsertId();

        // 2. Save the first entry in the incident's log
        $stmt = $pdo->prepare(
            "INSERT INTO incident_updates (incident_id, updated_by, status, message)
             VALUES (?, ?, 'Open', 'Incident created.')"
        );
        $stmt->execute([$incident_id, $_SESSION['user_id']]);

        // 3. Save a record in the audit log
        logAudit($pdo, $_SESSION['user_id'], 'Incident Created', 'incidents', $incident_id, "Created incident $incident_number: $title.");

        // 4. If this incident came from a complaint, escalate other recent complaints in the same area
        if ($complaint_id && $service_area_id) {
            $stmt = $pdo->prepare(
                "SELECT id FROM customer_complaints
                 WHERE service_area_id = ? AND created_at >= (NOW() - INTERVAL 24 HOUR) AND status NOT IN ('Resolved','Closed','Rejected')"
            );
            $stmt->execute([$service_area_id]);
            $related_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($related_ids as $related_complaint_id_item) {
                $stmt = $pdo->prepare("UPDATE customer_complaints SET status = 'Escalated' WHERE id = ?");
                $stmt->execute([$related_complaint_id_item]);

                $stmt = $pdo->prepare(
                    "INSERT INTO complaint_updates (complaint_id, updated_by, status, message)
                     VALUES (?, ?, 'Escalated', ?)"
                );
                $stmt->execute([$related_complaint_id_item, $_SESSION['user_id'], "Linked to network incident $incident_number."]);
            }
        }

        // 5. Notify network engineers
        notifyRole($pdo, 'Network Engineer', 'New Incident', "Incident $incident_number created: $title.");

        // Save everything
        $pdo->commit();

        header('Location: view.php?id=' . $incident_id);
        exit;
    }
}

// Get the sites and service areas for the dropdowns
$sites = $pdo->query("SELECT id, site_name FROM sites ORDER BY site_name")->fetchAll();
$service_areas = $pdo->query("SELECT id, area_name FROM service_areas ORDER BY area_name")->fetchAll();

$pageTitle = 'Create Incident';
require_once __DIR__ . '/../includes/staff_header.php';
?>

<!-- Show errors -->
<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endforeach; ?>

<!-- Message when creating from a complaint -->
<?php if ($prefill): ?>
    <div class="alert alert-info">
        Creating incident from complaint <strong><?php echo htmlspecialchars($prefill['complaint_number']); ?></strong>
        (<?php echo htmlspecialchars($prefill['problem_category']); ?> in <?php echo htmlspecialchars($prefill['area_name']); ?>)
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
            <input type="hidden" name="complaint_id" value="<?php echo $complaint_id; ?>">

            <div class="row g-3">

                <div class="col-12">
                    <label class="form-label">Title *</label>
                    <?php
                    // Pre-fill the title if we came from a complaint
                    $title_value = '';
                    if ($prefill) {
                        $title_value = $prefill['problem_category'] . ' - ' . $prefill['area_name'];
                    }
                    ?>
                    <input type="text" name="title" class="form-control" required
                           value="<?php echo htmlspecialchars($title_value); ?>">
                </div>

                <div class="col-12">
                    <label class="form-label">Description</label>
                    <?php
                    $description_value = '';
                    if ($prefill) {
                        $description_value = $prefill['description'];
                    }
                    ?>
                    <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($description_value); ?></textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Affected Area</label>
                    <select name="service_area_id" class="form-select">
                        <option value="">-- None --</option>
                        <?php foreach ($service_areas as $area): ?>
                            <?php
                            $selected = '';
                            if ($prefill && $prefill['service_area_id'] == $area['id']) {
                                $selected = 'selected';
                            }
                            ?>
                            <option value="<?php echo $area['id']; ?>" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($area['area_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Site</label>
                    <select name="site_id" class="form-select">
                        <option value="">-- None / Unknown Yet --</option>
                        <?php foreach ($sites as $site): ?>
                            <option value="<?php echo $site['id']; ?>"><?php echo htmlspecialchars($site['site_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Technology</label>
                    <select name="technology" class="form-select">
                        <option value="">—</option>
                        <?php foreach ($technology_list as $tech): ?>
                            <?php
                            $selected = '';
                            if ($prefill && $prefill['technology'] === $tech) {
                                $selected = 'selected';
                            }
                            ?>
                            <option value="<?php echo $tech; ?>" <?php echo $selected; ?>><?php echo $tech; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-select">
                        <?php foreach ($priority_list as $priority): ?>
                            <?php
                            $selected = '';
                            if ($prefill && $prefill['priority'] === $priority) {
                                $selected = 'selected';
                            }
                            ?>
                            <option value="<?php echo $priority; ?>" <?php echo $selected; ?>><?php echo $priority; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <button class="btn btn-danger mt-4"><i class="bi bi-exclamation-diamond"></i> Create Incident</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>