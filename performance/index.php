<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['Administrator', 'Network Monitoring Operator', 'Network Engineer', 'Report Viewer']);

require_once __DIR__ . '/../config/database.php';
$pdo = getDatabaseConnection();

// Get the site filter from the URL
$site_filter = (int) ($_GET['site_id'] ?? 0);

// Get the sites for the filter dropdown
$sites = $pdo->query("SELECT id, site_name, site_code FROM sites ORDER BY site_name")->fetchAll();

// Start the query (WHERE 1=1 makes it easy to add more conditions)
$sql = "SELECT np.*, s.site_name, s.site_code, c.cell_name
        FROM network_performance np
        JOIN sites s ON np.site_id = s.id
        LEFT JOIN cells c ON np.cell_id = c.id
        WHERE 1=1";
$params = [];

// Filter by site
if ($site_filter) {
    $sql .= " AND np.site_id = ?";
    $params[] = $site_filter;
}

$sql .= " ORDER BY np.recorded_date DESC, np.recorded_time DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

// Decide the badge color for a metric, based on simple good/warning/bad thresholds
function thresholdColor($metric, $value)
{
    if ($metric === 'availability') {
        if ($value >= 99) {
            return 'success';
        }
        if ($value >= 95) {
            return 'warning';
        }
        return 'danger';
    }

    if ($metric === 'load') {
        if ($value < 70) {
            return 'success';
        }
        if ($value <= 85) {
            return 'warning';
        }
        return 'danger';
    }

    if ($metric === 'signal') {
        if ($value >= -85) {
            return 'success';
        }
        if ($value >= -100) {
            return 'warning';
        }
        return 'danger';
    }

    if ($metric === 'latency') {
        if ($value < 50) {
            return 'success';
        }
        if ($value <= 100) {
            return 'warning';
        }
        return 'danger';
    }

    return 'secondary';
}

$pageTitle = 'Network Performance';
require_once __DIR__ . '/../includes/staff_header.php';
?>

<div class="alert alert-info">
    <i class="bi bi-info-circle"></i> These are simulated demonstration readings, not live equipment data.
    Thresholds shown: Availability ≥99% good / ≥95% warning / below critical. Load &lt;70% good / ≤85% warning / above critical.
    Signal ≥-85dBm good / ≥-100dBm warning / below critical. Latency &lt;50ms good / ≤100ms warning / above critical.
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <!-- Site filter -->
    <form method="GET" class="d-flex gap-2">
        <select name="site_id" class="form-select" onchange="this.form.submit()">
            <option value="">All Sites</option>
            <?php foreach ($sites as $site): ?>
                <?php
                $selected = '';
                if ($site_filter === (int) $site['id']) {
                    $selected = 'selected';
                }
                ?>
                <option value="<?php echo $site['id']; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($site['site_name']); ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <a href="generate_sample.php" class="btn btn-primary"><i class="bi bi-shuffle"></i> Generate Sample Reading</a>
</div>

<!-- Performance records table -->
<div class="card shadow-sm">
    <div class="card-body p-0">

        <?php if (count($records) == 0): ?>
            <p class="text-muted p-3 mb-0">No performance records yet. Click "Generate Sample Reading" to create one, or wait for real sites/cells to accumulate data.</p>
        <?php else: ?>
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr>
                        <th>Site</th>
                        <th>Cell</th>
                        <th>Date/Time</th>
                        <th>Tech</th>
                        <th>Availability</th>
                        <th>Load</th>
                        <th>Signal</th>
                        <th>Latency</th>
                        <th>Throughput</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $record): ?>
                        <?php
                        // Show a dash if the reading has no cell
                        $cell_name = $record['cell_name'];
                        if (!$cell_name) {
                            $cell_name = '—';
                        }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($record['site_name']); ?></td>
                            <td><?php echo htmlspecialchars($cell_name); ?></td>
                            <td><?php echo htmlspecialchars($record['recorded_date'] . ' ' . $record['recorded_time']); ?></td>
                            <td><?php echo htmlspecialchars($record['technology']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo thresholdColor('availability', $record['availability']); ?>">
                                    <?php echo $record['availability']; ?>%
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo thresholdColor('load', $record['load_percent']); ?>">
                                    <?php echo $record['load_percent']; ?>%
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo thresholdColor('signal', $record['signal_strength']); ?>">
                                    <?php echo $record['signal_strength']; ?> dBm
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo thresholdColor('latency', $record['latency']); ?>">
                                    <?php echo $record['latency']; ?> ms
                                </span>
                            </td>
                            <td><?php echo $record['throughput']; ?> Mbps</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>