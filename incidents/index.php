<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['Administrator', 'Network Monitoring Operator', 'Network Engineer', 'Transmission Engineer', 'Report Viewer']);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
$pdo = getDatabaseConnection();

// Get the filters from the URL
$status_filter = $_GET['status'] ?? '';
$mine_only = isset($_GET['mine']);

// List of statuses for the dropdown
$status_list = ['Open', 'Assigned', 'Investigating', 'In Progress', 'Resolved', 'Closed'];

// Start the query (WHERE 1=1 makes it easy to add more conditions)
$sql = "SELECT i.*, s.site_name, sa.area_name, u.full_name AS engineer_name
        FROM incidents i
        LEFT JOIN sites s ON i.site_id = s.id
        LEFT JOIN service_areas sa ON i.service_area_id = sa.id
        LEFT JOIN users u ON i.assigned_engineer_id = u.id
        WHERE 1=1";
$params = [];

// Filter by status
if ($status_filter !== '') {
    $sql .= " AND i.status = ?";
    $params[] = $status_filter;
}

// Filter to only the current user's assignments
if ($mine_only) {
    $sql .= " AND i.assigned_engineer_id = ?";
    $params[] = $_SESSION['user_id'];
}

$sql .= " ORDER BY i.created_at DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$incidents = $stmt->fetchAll();

$pageTitle = 'Incidents';
require_once __DIR__ . '/../includes/staff_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">

    <!-- Filter form -->
    <form method="GET" class="d-flex gap-2 align-items-center">
        <select name="status" class="form-select" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <?php foreach ($status_list as $status): ?>
                <?php
                $selected = '';
                if ($status_filter === $status) {
                    $selected = 'selected';
                }
                ?>
                <option value="<?php echo $status; ?>" <?php echo $selected; ?>><?php echo $status; ?></option>
            <?php endforeach; ?>
        </select>

        <div class="form-check ms-2">
            <?php
            $mine_checked = '';
            if ($mine_only) {
                $mine_checked = 'checked';
            }
            ?>
            <input class="form-check-input" type="checkbox" name="mine" value="1" id="mineCheck"
                   onchange="this.form.submit()" <?php echo $mine_checked; ?>>
            <label class="form-check-label" for="mineCheck">My Assignments Only</label>
        </div>
    </form>

    <a href="add.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Create Incident</a>
</div>

<!-- Incidents table -->
<div class="card shadow-sm">
    <div class="card-body p-0">

        <?php if (count($incidents) == 0): ?>
            <p class="text-muted p-3 mb-0">No incidents found.</p>
        <?php else: ?>
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Incident #</th>
                        <th>Title</th>
                        <th>Site/Area</th>
                        <th>Priority</th>
                        <th>Assigned</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($incidents as $incident): ?>
                        <?php
                        // Show the site name, or the area name, or a dash
                        $location = $incident['site_name'];
                        if (!$location) {
                            $location = $incident['area_name'];
                        }
                        if (!$location) {
                            $location = '—';
                        }

                        // Show the engineer's name, or "Unassigned"
                        $engineer_name = $incident['engineer_name'];
                        if (!$engineer_name) {
                            $engineer_name = 'Unassigned';
                        }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($incident['incident_number']); ?></td>
                            <td><?php echo htmlspecialchars($incident['title']); ?></td>
                            <td><?php echo htmlspecialchars($location); ?></td>
                            <td>
                                <span class="badge bg-<?php echo priorityBadgeColor($incident['priority']); ?>">
                                    <?php echo $incident['priority']; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($engineer_name); ?></td>
                            <td>
                                <span class="badge bg-<?php echo incidentStatusColor($incident['status']); ?>">
                                    <?php echo $incident['status']; ?>
                                </span>
                            </td>
                            <td>
                                <a href="view.php?id=<?php echo $incident['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>