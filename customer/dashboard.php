<?php
// User must be logged in and be a customer
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
if (!isCustomer()) {
    header('Location: ../staff/dashboard.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
$pdo = getDatabaseConnection();

$customer_id = $_SESSION['user_id'];

// ---------- Complaint counts ----------
$stmt = $pdo->prepare(
    "SELECT status, COUNT(*) AS cnt
     FROM customer_complaints
     WHERE customer_id = ?
     GROUP BY status"
);
$stmt->execute([$customer_id]);
$status_counts = $stmt->fetchAll();

// Start all the counters at zero
$open = 0;
$in_progress = 0;
$resolved = 0;
$total = 0;

// Which statuses belong to which group
$open_statuses = ['Submitted', 'Received', 'Under Verification', 'Assigned'];
$progress_statuses = ['Under Investigation', 'Escalated'];
$resolved_statuses = ['Resolved', 'Closed'];

// Add up the counts for each group
foreach ($status_counts as $row) {
    $total = $total + $row['cnt'];

    if (in_array($row['status'], $open_statuses)) {
        $open = $open + $row['cnt'];
    } elseif (in_array($row['status'], $progress_statuses)) {
        $in_progress = $in_progress + $row['cnt'];
    } elseif (in_array($row['status'], $resolved_statuses)) {
        $resolved = $resolved + $row['cnt'];
    }
}

// What percent of the complaints are resolved? (used by the banner bar)
$resolved_percent = 0;
if ($total > 0) {
    $resolved_percent = round(($resolved / $total) * 100);
}

// ---------- Recent complaints (last 5) ----------
$stmt = $pdo->prepare(
    "SELECT complaint_number, problem_category, status, priority, created_at
     FROM customer_complaints
     WHERE customer_id = ?
     ORDER BY created_at DESC
     LIMIT 5"
);
$stmt->execute([$customer_id]);
$recent_complaints = $stmt->fetchAll();

// ---------- Service status in the customer's area ----------
// First find the customer's woreda
$stmt = $pdo->prepare("SELECT woreda_id, kebele_id FROM users WHERE id = ?");
$stmt->execute([$customer_id]);
$customer_location = $stmt->fetch();

$area_status = null;

// If the customer has a woreda, get the latest service status there
if ($customer_location && $customer_location['woreda_id']) {
    $stmt = $pdo->prepare(
        "SELECT ss.status, ss.reason, s.service_name, ss.updated_at
         FROM service_status ss
         JOIN service_areas sa ON ss.service_area_id = sa.id
         JOIN services s ON ss.service_id = s.id
         WHERE sa.woreda_id = ?
         ORDER BY ss.updated_at DESC
         LIMIT 1"
    );
    $stmt->execute([$customer_location['woreda_id']]);
    $area_status = $stmt->fetch();
}

// Choose the badge color for the area status
$status_color = 'danger';
if ($area_status) {
    if ($area_status['status'] === 'Normal') {
        $status_color = 'success';
    } elseif ($area_status['status'] === 'Degraded') {
        $status_color = 'warning';
    }
}

// Pick an icon to match the status color
$status_icon = 'bi-check-circle-fill';
if ($status_color === 'warning') {
    $status_icon = 'bi-exclamation-triangle-fill';
} elseif ($status_color === 'danger') {
    $status_icon = 'bi-x-circle-fill';
}

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/customer_header.php';
?>

<!-- ============ Welcome banner ============ -->
<div class="hero-card mb-4">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h2>

            <?php if ($total == 0): ?>
                <p class="mb-3">You have no complaints yet. If your network has a problem, tell us and we will fix it.</p>
            <?php else: ?>
                <p class="mb-2"><?php echo $resolved; ?> of your <?php echo $total; ?> complaints are resolved.</p>
                <div class="hero-progress mb-3">
                    <div style="width: <?php echo $resolved_percent; ?>%;"></div>
                </div>
            <?php endif; ?>

            <a href="report_problem.php" class="btn btn-light">
                <i class="bi bi-exclamation-triangle-fill"></i> Report a Network Problem
            </a>
        </div>
        <div class="col-lg-4 text-center d-none d-lg-block">
            <i class="bi bi-broadcast-pin" style="font-size: 6rem; opacity: 0.35;"></i>
        </div>
    </div>
</div>

<!-- ============ Number tiles ============ -->
<div class="row g-3 mb-4">

    <div class="col-6 col-lg-3">
        <div class="stat-card h-100">
            <div class="stat-icon icon-orange"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-num"><?php echo $open; ?></div>
            <div class="stat-label">Open</div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="stat-card h-100">
            <div class="stat-icon"><i class="bi bi-gear-fill"></i></div>
            <div class="stat-num"><?php echo $in_progress; ?></div>
            <div class="stat-label">In Progress</div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="stat-card h-100">
            <div class="stat-icon icon-green"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-num"><?php echo $resolved; ?></div>
            <div class="stat-label">Resolved</div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="stat-card h-100">
            <div class="stat-icon icon-purple"><i class="bi bi-collection-fill"></i></div>
            <div class="stat-num"><?php echo $total; ?></div>
            <div class="stat-label">Total Complaints</div>
        </div>
    </div>

</div>

<div class="row g-3">

    <!-- ============ Recent complaints ============ -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history"></i> Recent Complaints</span>
                <a href="complaints.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (count($recent_complaints) == 0): ?>
                    <p class="text-muted p-4 mb-0">You haven't submitted any complaints yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Complaint #</th>
                                    <th>Problem</th>
                                    <th>Status</th>
                                    <th>Priority</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_complaints as $complaint): ?>
                                    <?php
                                    // Pick a badge color for this complaint's status
                                    $badge_color = 'secondary';
                                    if (in_array($complaint['status'], $open_statuses)) {
                                        $badge_color = 'warning';
                                    } elseif (in_array($complaint['status'], $progress_statuses)) {
                                        $badge_color = 'info';
                                    } elseif (in_array($complaint['status'], $resolved_statuses)) {
                                        $badge_color = 'success';
                                    }

                                    // Pick a badge color for the priority
                                    $priority_color = 'secondary';
                                    if ($complaint['priority'] === 'High' || $complaint['priority'] === 'Critical') {
                                        $priority_color = 'danger';
                                    } elseif ($complaint['priority'] === 'Medium') {
                                        $priority_color = 'warning';
                                    }
                                    ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo htmlspecialchars($complaint['complaint_number']); ?></td>
                                        <td><?php echo htmlspecialchars($complaint['problem_category']); ?></td>
                                        <td><span class="badge bg-<?php echo $badge_color; ?>"><?php echo htmlspecialchars($complaint['status']); ?></span></td>
                                        <td><span class="badge bg-<?php echo $priority_color; ?>"><?php echo htmlspecialchars($complaint['priority']); ?></span></td>
                                        <td class="text-muted"><?php echo date('d M Y', strtotime($complaint['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ============ Service status ============ -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-broadcast"></i> Service Status in Your Area</div>
            <div class="card-body">

                <?php if ($area_status): ?>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="status-icon <?php echo $status_color; ?>">
                            <i class="bi <?php echo $status_icon; ?>"></i>
                        </div>
                        <div>
                            <strong><?php echo htmlspecialchars($area_status['service_name']); ?></strong><br>
                            <span class="badge bg-<?php echo $status_color; ?>"><?php echo htmlspecialchars($area_status['status']); ?></span>
                        </div>
                    </div>

                    <?php if ($area_status['reason']): ?>
                        <p class="text-muted small mb-2">Reason: <?php echo htmlspecialchars($area_status['reason']); ?></p>
                    <?php endif; ?>
                    <p class="text-muted small mb-0">Updated <?php echo date('d M Y, H:i', strtotime($area_status['updated_at'])); ?></p>

                <?php else: ?>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="status-icon success"><i class="bi bi-check-circle-fill"></i></div>
                        <div>
                            <strong>All services</strong><br>
                            <span class="badge bg-success">Normal</span>
                        </div>
                    </div>
                    <p class="text-muted small mb-0">No known issues reported in your area.</p>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>