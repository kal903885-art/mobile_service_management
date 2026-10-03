<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
$pdo = getDatabaseConnection();

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$role = $_SESSION['role_name'];

// The list of available reports, and which roles can see each one
$reports = [
    [
        'title' => 'Customer Complaint Report',
        'desc' => 'All complaints with status, category, and area filters.',
        'url' => 'complaints.php',
        'icon' => 'bi-chat-left-text',
        'roles' => ['Administrator', 'Customer Service Agent', 'Report Viewer']
    ],
    [
        'title' => 'Network Availability Report',
        'desc' => 'Average availability, signal, and call success rate per site.',
        'url' => 'network_availability.php',
        'icon' => 'bi-reception-4',
        'roles' => ['Administrator', 'Network Monitoring Operator', 'Network Engineer', 'Transmission Engineer', 'Report Viewer']
    ],
    [
        'title' => 'Alarm Report',
        'desc' => 'Alarms by severity, status, and site over a date range.',
        'url' => 'alarms.php',
        'icon' => 'bi-bell',
        'roles' => ['Administrator', 'Network Monitoring Operator', 'Network Engineer', 'Transmission Engineer', 'Report Viewer']
    ],
    [
        'title' => 'Incident Report',
        'desc' => 'Incidents with priority, status, and resolution times.',
        'url' => 'incidents.php',
        'icon' => 'bi-exclamation-diamond',
        'roles' => ['Administrator', 'Network Monitoring Operator', 'Network Engineer', 'Transmission Engineer', 'Report Viewer']
    ],
    [
        'title' => 'Engineer Performance Report',
        'desc' => 'Incidents resolved per engineer, with average resolution time.',
        'url' => 'engineer_performance.php',
        'icon' => 'bi-person-badge',
        'roles' => ['Administrator', 'Network Monitoring Operator', 'Report Viewer']
    ],
    [
        'title' => 'Service Outage Report',
        'desc' => 'Incidents affecting service, with downtime duration.',
        'url' => 'outages.php',
        'icon' => 'bi-plug',
        'roles' => ['Administrator', 'Network Monitoring Operator', 'Network Engineer', 'Transmission Engineer', 'Report Viewer']
    ],
];

$pageTitle = 'Reports';
require_once '../includes/staff_header.php';
?>

<div class="row g-3">
    <?php foreach ($reports as $report): ?>
        <?php if (in_array($role, $report['roles'])): ?>
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <i class="bi <?php echo $report['icon']; ?> fs-2 text-primary"></i>
                        <h5 class="mt-2"><?php echo htmlspecialchars($report['title']); ?></h5>
                        <p class="text-muted small"><?php echo htmlspecialchars($report['desc']); ?></p>
                        <a href="<?php echo $report['url']; ?>" class="btn btn-primary btn-sm">Open Report</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<?php require_once '../includes/staff_footer.php'; ?>