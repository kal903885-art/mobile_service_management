<?php
// User must be logged in and be a customer
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
if (!isCustomer()) {
    header('Location: ../staff/dashboard.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
$pdo = getDatabaseConnection();

$complaint = null;
$not_found = false;

// Get the complaint number (from the link or from a form)
if (isset($_GET['complaint_number'])) {
    $search_number = trim($_GET['complaint_number']);
} elseif (isset($_POST['complaint_number'])) {
    $search_number = trim($_POST['complaint_number']);
} else {
    $search_number = '';
}

// If a number was given, look for it (only among this customer's complaints)
if ($search_number !== '') {
    $stmt = $pdo->prepare(
        "SELECT cc.*, sa.area_name
         FROM customer_complaints cc
         JOIN service_areas sa ON cc.service_area_id = sa.id
         WHERE cc.complaint_number = ? AND cc.customer_id = ?"
    );
    $stmt->execute([$search_number, $_SESSION['user_id']]);
    $complaint = $stmt->fetch();

    if (!$complaint) {
        $not_found = true;
    }
}

// The steps shown in the status timeline
$timeline = ['Submitted', 'Received', 'Under Verification', 'Assigned', 'Under Investigation', 'Resolved', 'Closed'];

// Find which step the complaint is at now
$current_index = false;
if ($complaint) {
    $current_index = array_search($complaint['status'], $timeline);
}

$pageTitle = 'Track Complaint';
require_once __DIR__ . '/../includes/customer_header.php';
?>

<h1 class="page-title">Track Complaint</h1>
<p class="page-sub">Enter your complaint number to see where it is right now.</p>

<!-- ============ Search form ============ -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-9">
                <input type="text" name="complaint_number" class="form-control"
                       placeholder="Enter complaint number, e.g. CMP-2026-000001"
                       value="<?php echo htmlspecialchars($search_number); ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Track</button>
            </div>
        </form>
    </div>
</div>

<!-- Message if nothing was found -->
<?php if ($not_found): ?>
    <div class="alert alert-warning">No complaint found with that number under your account.</div>
<?php endif; ?>

<!-- ============ Complaint details ============ -->
<?php if ($complaint): ?>

    <!-- Status steps -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Status Timeline</span>
            <span class="badge bg-<?php echo statusBadgeColor($complaint['status']); ?>"><?php echo htmlspecialchars($complaint['status']); ?></span>
        </div>
        <div class="card-body">
            <div class="timeline-steps">
                <?php foreach ($timeline as $i => $stage): ?>
                    <?php
                    // A step is "done" if the complaint has reached it
                    $done = false;
                    if ($current_index !== false && $i <= $current_index) {
                        $done = true;
                    }

                    // The step the complaint is at right now
                    $is_current = false;
                    if ($current_index !== false && $i == $current_index) {
                        $is_current = true;
                    }

                    // Build the CSS class for this step
                    $step_class = 'step';
                    if ($done) {
                        $step_class = $step_class . ' done';
                    }
                    if ($is_current) {
                        $step_class = $step_class . ' current';
                    }

                    // Check mark if done, step number if not
                    if ($done) {
                        $dot_content = '<i class="bi bi-check-lg"></i>';
                    } else {
                        $dot_content = $i + 1;
                    }
                    ?>
                    <div class="<?php echo $step_class; ?>">
                        <div class="dot"><?php echo $dot_content; ?></div>
                        <p><?php echo $stage; ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Extra message for rejected complaints -->
            <?php if ($complaint['status'] === 'Rejected'): ?>
                <div class="alert alert-danger mt-3 mb-0">This complaint was rejected. Please contact customer service for details.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Details -->
    <div class="card">
        <div class="card-header">Complaint Details</div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="info-label">Complaint Number</div>
                    <p class="info-value"><?php echo htmlspecialchars($complaint['complaint_number']); ?></p>
                </div>
                <div class="col-md-6">
                    <div class="info-label">Date Submitted</div>
                    <p class="info-value"><?php echo date('M j, Y g:i A', strtotime($complaint['created_at'])); ?></p>
                </div>
                <div class="col-md-6">
                    <div class="info-label">Problem Type</div>
                    <p class="info-value"><?php echo htmlspecialchars($complaint['problem_category']); ?></p>
                </div>
                <div class="col-md-6">
                    <div class="info-label">Affected Area</div>
                    <p class="info-value"><?php echo htmlspecialchars($complaint['area_name']); ?></p>
                </div>
                <div class="col-md-6">
                    <div class="info-label">Service</div>
                    <p class="info-value"><?php echo htmlspecialchars($complaint['affected_service']); ?></p>
                </div>
                <div class="col-md-6">
                    <div class="info-label">Priority</div>
                    <span class="badge bg-<?php echo priorityBadgeColor($complaint['priority']); ?>">
                        <?php echo htmlspecialchars($complaint['priority']); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>

    <?php if (!$not_found): ?>
        <div class="card">
            <div class="empty-state">
                <i class="bi bi-search"></i>
                <p class="mt-3 mb-0">Type a complaint number above to see its progress.</p>
            </div>
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>