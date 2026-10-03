<?php
// Only these roles can see a complaint
require_once __DIR__ . '/../includes/auth.php';
requireRole(['Administrator', 'Customer Service Agent', 'Network Monitoring Operator', 'Report Viewer']);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
$pdo = getDatabaseConnection();

// Get the complaint id from the link
$id = (int) ($_GET['id'] ?? 0);

// Lists used by the update form
$status_list = [
    'Submitted', 'Received', 'Under Verification', 'Assigned',
    'Under Investigation', 'Escalated', 'Resolved', 'Closed', 'Rejected'
];
$priority_list = ['Critical', 'High', 'Medium', 'Low'];

// Find the complaint together with the area and customer details
$stmt = $pdo->prepare(
    "SELECT cc.*, sa.area_name, sa.woreda_id,
            u.full_name AS customer_name, u.phone AS customer_phone, u.email AS customer_email
     FROM customer_complaints cc
     JOIN service_areas sa ON cc.service_area_id = sa.id
     JOIN users u ON cc.customer_id = u.id
     WHERE cc.id = ?"
);
$stmt->execute([$id]);
$complaint = $stmt->fetch();

if (!$complaint) {
    die("Complaint not found.");
}

// Count other complaints from the same area in the last 24 hours
// (a simple rule to spot a possible network incident)
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM customer_complaints
     WHERE service_area_id = ? AND id != ? AND created_at >= (NOW() - INTERVAL 24 HOUR)"
);
$stmt->execute([$complaint['service_area_id'], $id]);
$related_count = $stmt->fetchColumn();

// Get the update history of this complaint
$stmt = $pdo->prepare(
    "SELECT cu.*, u.full_name AS updated_by_name
     FROM complaint_updates cu
     LEFT JOIN users u ON cu.updated_by = u.id
     WHERE cu.complaint_id = ?
     ORDER BY cu.created_at ASC"
);
$stmt->execute([$id]);
$updates = $stmt->fetchAll();

// Show a dash if the complaint has no technology
$technology = $complaint['technology'];
if (!$technology) {
    $technology = '—';
}

$pageTitle = 'Complaint ' . $complaint['complaint_number'];
require_once __DIR__ . '/../includes/staff_header.php';
?>

<a href="index.php" class="btn btn-sm btn-outline-secondary mb-3"><i class="bi bi-arrow-left"></i> Back to Complaints</a>

<!-- Warning about other complaints from the same area -->
<?php if ($related_count >= 5): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-octagon-fill"></i>
        <strong>POSSIBLE AREA NETWORK INCIDENT:</strong> <?php echo $related_count; ?> other complaints from
        <?php echo htmlspecialchars($complaint['area_name']); ?> in the last 24 hours. Consider investigating the network site/cell in this area.
        <a href="/mobile-network-service-management/incidents/add.php?complaint_id=<?php echo $id; ?>" class="btn btn-sm btn-dark ms-2">Create Incident</a>
    </div>
<?php elseif ($related_count > 0): ?>
    <div class="alert alert-warning">
        <i class="bi bi-info-circle"></i> <?php echo $related_count; ?> other complaint(s) from the same area in the last 24 hours.
    </div>
<?php endif; ?>

<div class="row g-3">

    <!-- Left side: details and history -->
    <div class="col-md-8">

        <!-- Complaint details -->
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between">
                <span><?php echo htmlspecialchars($complaint['complaint_number']); ?></span>
                <span class="badge bg-<?php echo statusBadgeColor($complaint['status']); ?>"><?php echo $complaint['status']; ?></span>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Customer</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($complaint['customer_name']); ?></dd>

                    <dt class="col-sm-4">Phone</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($complaint['customer_phone']); ?></dd>

                    <dt class="col-sm-4">Email</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($complaint['customer_email']); ?></dd>

                    <dt class="col-sm-4">Area</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($complaint['area_name']); ?></dd>

                    <dt class="col-sm-4">Problem Category</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($complaint['problem_category']); ?></dd>

                    <dt class="col-sm-4">Affected Service</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($complaint['affected_service']); ?></dd>

                    <dt class="col-sm-4">Technology</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($technology); ?></dd>

                    <dt class="col-sm-4">Description</dt>
                    <dd class="col-sm-8"><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></dd>

                    <dt class="col-sm-4">Priority</dt>
                    <dd class="col-sm-8">
                        <span class="badge bg-<?php echo priorityBadgeColor($complaint['priority']); ?>">
                            <?php echo $complaint['priority']; ?>
                        </span>
                    </dd>
                </dl>
            </div>
        </div>

        <!-- Update history -->
        <div class="card shadow-sm mt-3">
            <div class="card-header">Update History</div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <?php foreach ($updates as $update): ?>
                        <?php
                        // If nobody is linked to the update, it was made by the system
                        $updated_by = $update['updated_by_name'];
                        if (!$updated_by) {
                            $updated_by = 'System';
                        }
                        ?>
                        <li class="list-group-item">
                            <span class="badge bg-<?php echo statusBadgeColor($update['status']); ?>">
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
            </div>
        </div>
    </div>

    <!-- Right side: update form -->
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header">Update Status</div>
            <div class="card-body">
                <form method="POST" action="update.php">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="complaint_id" value="<?php echo $id; ?>">

                    <div class="mb-3">
                        <label class="form-label">New Status</label>
                        <select name="status" class="form-select">
                            <?php foreach ($status_list as $status): ?>
                                <?php
                                // Select the current status
                                $selected = '';
                                if ($complaint['status'] === $status) {
                                    $selected = 'selected';
                                }
                                ?>
                                <option value="<?php echo $status; ?>" <?php echo $selected; ?>><?php echo $status; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select">
                            <?php foreach ($priority_list as $priority): ?>
                                <?php
                                // Select the current priority
                                $selected = '';
                                if ($complaint['priority'] === $priority) {
                                    $selected = 'selected';
                                }
                                ?>
                                <option value="<?php echo $priority; ?>" <?php echo $selected; ?>><?php echo $priority; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Note to add</label>
                        <textarea name="message" class="form-control" rows="2" placeholder="e.g. Verified with customer, assigning to network team..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Save Update</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>