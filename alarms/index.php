<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole(['Administrator', 'Network Monitoring Operator', 'Network Engineer', 'Report Viewer']);

require_once __DIR__ . '/../config/database.php';
$pdo = getDatabaseConnection();

// Get the filters from the URL (default status is Active)
$status_filter = $_GET['status'] ?? 'Active';
$severity_filter = $_GET['severity'] ?? '';

// Start the query (WHERE 1=1 makes it easy to add more conditions)
$sql = "SELECT a.*, s.site_name, c.cell_name
        FROM alarms a
        JOIN sites s ON a.site_id = s.id
        LEFT JOIN cells c ON a.cell_id = c.id
        WHERE 1=1";
$params = [];

// Filter by status
if ($status_filter !== '') {
    $sql .= " AND a.status = ?";
    $params[] = $status_filter;
}

// Filter by severity
if ($severity_filter !== '') {
    $sql .= " AND a.severity = ?";
    $params[] = $severity_filter;
}

// Newest alarms first, only the last 100
$sql .= " ORDER BY a.occurred_at DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$alarms = $stmt->fetchAll();

// Lists used by the filter dropdowns
$status_list = [
    ''             => 'All Statuses',
    'Active'       => 'Active',
    'Acknowledged' => 'Acknowledged',
    'Cleared'      => 'Cleared'
];
$severity_list = ['Critical', 'Major', 'Minor', 'Warning'];

// Give each severity a badge color
function severityColor($severity)
{
    if ($severity === 'Critical') {
        return 'danger';
    }
    if ($severity === 'Major') {
        return 'warning';
    }
    if ($severity === 'Minor') {
        return 'info';
    }
    return 'secondary';
}

// Give each status a badge color
function alarmStatusColor($status)
{
    if ($status === 'Active') {
        return 'danger';
    }
    if ($status === 'Acknowledged') {
        return 'warning';
    }
    if ($status === 'Cleared') {
        return 'success';
    }
    return 'secondary';
}

$pageTitle = 'Alarms';
require_once __DIR__ . '/../includes/staff_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">

    <!-- Filter form -->
    <form method="GET" class="d-flex gap-2">
        <select name="status" class="form-select" onchange="this.form.submit()">
            <?php foreach ($status_list as $value => $label): ?>
                <?php
                // Keep the chosen status selected
                $selected = '';
                if ($status_filter === $value) {
                    $selected = 'selected';
                }
                ?>
                <option value="<?php echo $value; ?>" <?php echo $selected; ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
        </select>

        <select name="severity" class="form-select" onchange="this.form.submit()">
            <option value="">All Severities</option>
            <?php foreach ($severity_list as $level): ?>
                <?php
                // Keep the chosen severity selected
                $selected = '';
                if ($severity_filter === $level) {
                    $selected = 'selected';
                }
                ?>
                <option value="<?php echo $level; ?>" <?php echo $selected; ?>><?php echo $level; ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <a href="add.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Raise Sample Alarm</a>
</div>

<!-- Alarms table -->
<div class="card shadow-sm">
    <div class="card-body p-0">

        <?php if (count($alarms) == 0): ?>
            <p class="text-muted p-3 mb-0">No alarms found for these filters.</p>
        <?php else: ?>
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Site</th>
                        <th>Cell</th>
                        <th>Severity</th>
                        <th>Status</th>
                        <th>Occurred</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alarms as $alarm): ?>
                        <?php
                        // Show a dash if the alarm has no cell
                        $cell_name = $alarm['cell_name'];
                        if (!$cell_name) {
                            $cell_name = '—';
                        }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($alarm['alarm_title']); ?></td>
                            <td><?php echo htmlspecialchars($alarm['site_name']); ?></td>
                            <td><?php echo htmlspecialchars($cell_name); ?></td>
                            <td>
                                <span class="badge bg-<?php echo severityColor($alarm['severity']); ?>">
                                    <?php echo $alarm['severity']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo alarmStatusColor($alarm['status']); ?>">
                                    <?php echo $alarm['status']; ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, g:i A', strtotime($alarm['occurred_at'])); ?></td>
                            <td>
                                <?php if ($alarm['status'] === 'Active'): ?>

                                    <!-- Active alarm: show Acknowledge button -->
                                    <form method="POST" action="acknowledge.php" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                        <input type="hidden" name="id" value="<?php echo $alarm['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-warning">Acknowledge</button>
                                    </form>

                                <?php elseif ($alarm['status'] === 'Acknowledged'): ?>

                                    <!-- Acknowledged alarm: show Clear button -->
                                    <form method="POST" action="clear.php" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                        <input type="hidden" name="id" value="<?php echo $alarm['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success">Clear</button>
                                    </form>

                                <?php else: ?>

                                    <!-- Cleared alarm: show the date it was cleared -->
                                    <span class="text-muted small">
                                        Cleared
                                        <?php
                                        if ($alarm['cleared_at']) {
                                            echo date('M j', strtotime($alarm['cleared_at']));
                                        }
                                        ?>
                                    </span>

                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>