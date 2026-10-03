<?php
// Only these roles can see the complaints list
require_once __DIR__ . '/../includes/auth.php';
requireRole(['Administrator', 'Customer Service Agent', 'Network Monitoring Operator', 'Report Viewer']);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
$pdo = getDatabaseConnection();

// Get the search and filter values from the URL
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';

// Lists used by the filter dropdowns
$status_list = [
    'Submitted', 'Received', 'Under Verification', 'Assigned',
    'Under Investigation', 'Escalated', 'Resolved', 'Closed', 'Rejected'
];
$priority_list = ['Critical', 'High', 'Medium', 'Low'];

// Start the query (WHERE 1=1 makes it easy to add more conditions)
$sql = "SELECT cc.*, sa.area_name, u.full_name AS customer_name, u.phone AS customer_phone
        FROM customer_complaints cc
        JOIN service_areas sa ON cc.service_area_id = sa.id
        JOIN users u ON cc.customer_id = u.id
        WHERE 1=1";
$params = [];

// Search by complaint number, customer name or phone
if ($search !== '') {
    $sql .= " AND (cc.complaint_number LIKE ? OR u.full_name LIKE ? OR u.phone LIKE ?)";
    $like = "%" . $search . "%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

// Filter by status
if ($status_filter !== '') {
    $sql .= " AND cc.status = ?";
    $params[] = $status_filter;
}

// Filter by priority
if ($priority_filter !== '') {
    $sql .= " AND cc.priority = ?";
    $params[] = $priority_filter;
}

// Newest first, only the last 100
$sql .= " ORDER BY cc.created_at DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$complaints = $stmt->fetchAll();

$pageTitle = 'Complaints';
require_once __DIR__ . '/../includes/staff_header.php';
?>

<!-- Search and filter form -->
<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Search complaint #, customer, phone..."
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <div class="col-md-3">
                <select name="status" class="form-select">
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
            </div>

            <div class="col-md-3">
                <select name="priority" class="form-select">
                    <option value="">All Priorities</option>
                    <?php foreach ($priority_list as $priority): ?>
                        <?php
                        // Keep the chosen priority selected
                        $selected = '';
                        if ($priority_filter === $priority) {
                            $selected = 'selected';
                        }
                        ?>
                        <option value="<?php echo $priority; ?>" <?php echo $selected; ?>><?php echo $priority; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Complaints table -->
<div class="card shadow-sm">
    <div class="card-body p-0">

        <?php if (count($complaints) == 0): ?>
            <p class="text-muted p-3 mb-0">No complaints match your filters.</p>
        <?php else: ?>
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Complaint #</th>
                        <th>Customer</th>
                        <th>Area</th>
                        <th>Problem</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($complaints as $complaint): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($complaint['complaint_number']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($complaint['customer_name']); ?><br>
                                <small class="text-muted"><?php echo htmlspecialchars($complaint['customer_phone']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($complaint['area_name']); ?></td>
                            <td><?php echo htmlspecialchars($complaint['problem_category']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo priorityBadgeColor($complaint['priority']); ?>">
                                    <?php echo $complaint['priority']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo statusBadgeColor($complaint['status']); ?>">
                                    <?php echo $complaint['status']; ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, g:i A', strtotime($complaint['created_at'])); ?></td>
                            <td>
                                <a href="view.php?id=<?php echo $complaint['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>