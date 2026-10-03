<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['Administrator', 'Network Monitoring Operator', 'Network Engineer', 'Transmission Engineer', 'Report Viewer']);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
$pdo = getDatabaseConnection();

// Get the incident id from the link
$id = (int) ($_GET['id'] ?? 0);

// Lists used by the update form
$status_list = ['Open', 'Assigned', 'Investigating', 'In Progress'];

// Statuses where the incident is already finished
$finished_statuses = ['Resolved', 'Closed'];

// Find the incident together with its site, area, engineer and related complaint
$stmt = $pdo->prepare(
    "SELECT i.*, s.site_name, sa.area_name, u.full_name AS engineer_name, cc.complaint_number
     FROM incidents i
     LEFT JOIN sites s ON i.site_id = s.id
     LEFT JOIN service_areas sa ON i.service_area_id = sa.id
     LEFT JOIN users u ON i.assigned_engineer_id = u.id
     LEFT JOIN customer_complaints cc ON i.related_complaint_id = cc.id
     WHERE i.id = ?"
);
$stmt->execute([$id]);
$incident = $stmt->fetch();

if (!$incident) {
    die("Incident not found.");
}

// Get the incident's log
$stmt = $pdo->prepare(
    "SELECT iu.*, u.full_name AS updated_by_name
     FROM incident_updates iu
     LEFT JOIN users u ON iu.updated_by = u.id
     WHERE iu.incident_id = ?
     ORDER BY iu.created_at ASC"
);
$stmt->execute([$id]);
$updates = $stmt->fetchAll();

// Get the engineers who could be assigned to this incident
$engineers = $pdo->query(
    "SELECT u.id, u.full_name, r.role_name FROM users u
     JOIN roles r ON u.role_id = r.id
     WHERE r.role_name IN ('Network Engineer', 'Transmission Engineer') AND u.is_active = 1"
)->fetchAll();

// Is the incident already finished?
$is_finished = in_array($incident['status'], $finished_statuses);

$pageTitle = $incident['incident_number'];
require_once __DIR__ . '/../includes/staff_header.php';
?>

<a href="index.php" class="btn btn-sm btn-outline-secondary mb-3"><i class="bi bi-arrow-left"></i> Back to Incidents</a>

<div class="row g-3">

    <!-- Left side: details and log -->
    <div class="col-md-8">

        <!-- Incident details -->
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between">
                <span><?php echo htmlspecialchars($incident['incident_number']); ?> — <?php echo htmlspecialchars($incident['title']); ?></span>
                <span class="badge bg-<?php echo incidentStatusColor($incident['status']); ?>"><?php echo $incident['status']; ?></span>
            </div>
            <div class="card-body">
                <?php
                // Fill in dashes for empty values
                $area_text = $incident['area_name'];
                if (!$area_text) {
                    $area_text = '—';
                }

                $site_text = $incident['site_name'];
                if (!$site_text) {
                    $site_text = 'Not yet identified';
                }

                $technology_text = $incident['technology'];
                if (!$technology_text) {
                    $technology_text = '—';
                }

                $complaint_text = '—';
                if ($incident['complaint_number']) {
                    $complaint_text = $incident['complaint_number'];
                }

                $engineer_text = $incident['engineer_name'];
                if (!$engineer_text) {
                    $engineer_text = 'Unassigned';
                }
                ?>
                <dl class="row mb-0">
                    <dt class="col-sm-4">Description</dt>
                    <dd class="col-sm-8"><?php echo nl2br(htmlspecialchars($incident['description'])); ?></dd>

                    <dt class="col-sm-4">Area</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($area_text); ?></dd>

                    <dt class="col-sm-4">Site</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($site_text); ?></dd>

                    <dt class="col-sm-4">Technology</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($technology_text); ?></dd>

                    <dt class="col-sm-4">Priority</dt>
                    <dd class="col-sm-8">
                        <span class="badge bg-<?php echo priorityBadgeColor($incident['priority']); ?>">
                            <?php echo $incident['priority']; ?>
                        </span>
                    </dd>

                    <dt class="col-sm-4">Related Complaint</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($complaint_text); ?></dd>

                    <dt class="col-sm-4">Assigned Engineer</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($engineer_text); ?></dd>
                </dl>
            </div>
        </div>

        <!-- Investigation log -->
        <div class="card shadow-sm mt-3">
            <div class="card-header">Investigation Log</div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <?php foreach ($updates as $update): ?>
                        <?php
                        $updated_by = $update['updated_by_name'];
                        if (!$updated_by) {
                            $updated_by = 'System';
                        }
                        ?>
                        <li class="list-group-item">
                            <span class="badge bg-<?php echo incidentStatusColor($update['status']); ?>">
                                <?php echo htmlspecialchars($update['status']); ?>
                            </span>
                            <?php echo htmlspecialchars($update['message']); ?>
                            <br>
                            <small class="text-muted">
                                <?php echo htmlspecialchars($updated_by); ?> —
                                <?php echo date('M j, Y g:i A', strtotime($update['created_at'])); ?>
                            </small>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <!-- Form to add a new log entry (only while the incident is still open) -->
                <?php if (!$is_finished): ?>
                    <form method="POST" action="update.php" class="mt-3">
                        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                        <input type="hidden" name="incident_id" value="<?php echo $id; ?>">
                        <div class="mb-2">
                            <select name="status" class="form-select mb-2">
                                <?php foreach ($status_list as $status): ?>
                                    <?php
                                    $selected = '';
                                    if ($incident['status'] === $status) {
                                        $selected = 'selected';
                                    }
                                    ?>
                                    <option value="<?php echo $status; ?>" <?php echo $selected; ?>><?php echo $status; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <textarea name="message" class="form-control mb-2" rows="2" placeholder="Add investigation note..." required></textarea>
                            <button class="btn btn-primary">Add Update</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right side: assign / resolve, or the final resolution -->
    <div class="col-md-4">
        <?php if (!$is_finished): ?>

            <!-- Assign engineer -->
            <div class="card shadow-sm mb-3">
                <div class="card-header">Assign Engineer</div>
                <div class="card-body">
                    <form method="POST" action="assign.php">
                        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                        <input type="hidden" name="incident_id" value="<?php echo $id; ?>">
                        <select name="engineer_id" class="form-select mb-2" required>
                            <option value="">-- Select Engineer --</option>
                            <?php foreach ($engineers as $engineer): ?>
                                <?php
                                $selected = '';
                                if ($incident['assigned_engineer_id'] == $engineer['id']) {
                                    $selected = 'selected';
                                }
                                $engineer_label = $engineer['full_name'] . ' (' . $engineer['role_name'] . ')';
                                ?>
                                <option value="<?php echo $engineer['id']; ?>" <?php echo $selected; ?>>
                                    <?php echo htmlspecialchars($engineer_label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-outline-primary w-100">Assign</button>
                    </form>
                </div>
            </div>

            <!-- Resolve incident -->
            <div class="card shadow-sm">
                <div class="card-header">Resolve Incident</div>
                <div class="card-body">
                    <form method="POST" action="resolve.php">
                        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                        <input type="hidden" name="incident_id" value="<?php echo $id; ?>">
                        <textarea name="resolution" class="form-control mb-2" rows="3" placeholder="Resolution details..." required></textarea>
                        <button class="btn btn-success w-100">Mark Resolved</button>
                    </form>
                </div>
            </div>

        <?php else: ?>

            <!-- Already resolved: show the resolution -->
            <div class="card shadow-sm border-success">
                <div class="card-body">
                    <h6 class="text-success"><i class="bi bi-check-circle"></i> Resolution</h6>
                    <p><?php echo nl2br(htmlspecialchars($incident['resolution'])); ?></p>
                    <?php
                    $resolved_text = '—';
                    if ($incident['resolved_at']) {
                        $resolved_text = date('M j, Y g:i A', strtotime($incident['resolved_at']));
                    }
                    ?>
                    <p class="text-muted small mb-0">Resolved: <?php echo $resolved_text; ?></p>
                </div>
            </div>

        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>