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

// Get the status filter from the URL
$status_filter = $_GET['status'] ?? '';

// List of statuses for the dropdown
$status_list = [
    'Submitted', 'Received', 'Under Verification', 'Assigned',
    'Under Investigation', 'Escalated', 'Resolved', 'Closed', 'Rejected'
];

// Get only the complaints of the logged-in customer
$sql = "SELECT cc.*, sa.area_name
        FROM customer_complaints cc
        JOIN service_areas sa ON cc.service_area_id = sa.id
        WHERE cc.customer_id = ?";
$params = [$_SESSION['user_id']];

// Filter by status if one was chosen
if ($status_filter !== '') {
    $sql .= " AND cc.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY cc.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$complaints = $stmt->fetchAll();

$pageTitle = 'My Complaints';
require_once __DIR__ . '/../includes/customer_header.php';
?>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
    <div>
        <h1 class="page-title">My Complaints</h1>
        <p class="page-sub mb-0">All the problems you have reported, newest first.</p>
    </div>
    <a href="report_problem.php" class="btn btn-primary"><i class="bi bi-plus-circle-fill"></i> New Complaint</a>
</div>

<!-- ============ Status filter ============ -->
<div class="card mb-3">
    <div class="card-body d-flex align-items-center gap-3 flex-wrap">
        <form method="GET" class="flex-grow-1" style="max-width: 320px;">
            <select name="status" class="form-select" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <?php foreach ($status_list as $status): ?>
                    <?php
                    // Keep the chosen status selected
                    $selected = '';
                    if ($status_filter === $status) {
                        $selected = 'selected';
                    }
                    ?>
                    <option value="<?php echo $status; ?>" <?php echo $selected; ?>><?php echo $status; ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <span class="text-muted"><?php echo count($complaints); ?> complaint(s) found</span>
    </div>
</div>

<!-- ============ Complaints table ============ -->
<div class="card">
    <div class="card-body p-0">

        <?php if (count($complaints) == 0): ?>
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <p class="mt-3 mb-0">No complaints found.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Complaint #</th>
                            <th>Date</th>
                            <th>Problem</th>
                            <th>Area</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($complaints as $complaint): ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($complaint['complaint_number']); ?></td>
                                <td class="text-muted"><?php echo date('M j, Y', strtotime($complaint['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($complaint['problem_category']); ?></td>
                                <td><?php echo htmlspecialchars($complaint['area_name']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo statusBadgeColor($complaint['status']); ?>">
                                        <?php echo htmlspecialchars($complaint['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo priorityBadgeColor($complaint['priority']); ?>">
                                        <?php echo htmlspecialchars($complaint['priority']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="track_complaint.php?complaint_number=<?php echo urlencode($complaint['complaint_number']); ?>"
                                       class="btn btn-sm btn-outline-primary">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>