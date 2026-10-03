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

// Get the latest status for each area + service
// (the inner query finds the newest row of each group)
$statuses = $pdo->query(
    "SELECT ss.*, sa.area_name, s.service_name
     FROM service_status ss
     JOIN service_areas sa ON ss.service_area_id = sa.id
     JOIN services s ON ss.service_id = s.id
     WHERE ss.id IN (
         SELECT MAX(id) FROM service_status GROUP BY service_area_id, service_id
     )
     ORDER BY sa.area_name, s.service_name"
)->fetchAll();

$pageTitle = 'Service Status';
require_once __DIR__ . '/../includes/customer_header.php';
?>

<h3 class="mb-4"><i class="bi bi-broadcast"></i> Network Service Status</h3>

<?php if (count($statuses) == 0): ?>

    <div class="alert alert-success">
        <i class="bi bi-check-circle"></i> No known issues reported anywhere right now.
    </div>

<?php else: ?>

    <div class="row g-3">
        <?php foreach ($statuses as $item): ?>
            <?php
            // Choose the color: Normal = green, Degraded = yellow, anything else = red
            $color = 'danger';
            if ($item['status'] === 'Normal') {
                $color = 'success';
            } elseif ($item['status'] === 'Degraded') {
                $color = 'warning';
            }

            // Show the technology (like 4G) only if there is one
            $service_text = htmlspecialchars($item['service_name']);
            if ($item['technology']) {
                $service_text = $service_text . ' (' . htmlspecialchars($item['technology']) . ')';
            }
            ?>
            <div class="col-md-4">
                <div class="card shadow-sm border-<?php echo $color; ?>">
                    <div class="card-body">
                        <h6><?php echo htmlspecialchars($item['area_name']); ?></h6>
                        <p class="mb-1"><?php echo $service_text; ?></p>
                        <span class="badge bg-<?php echo $color; ?>"><?php echo htmlspecialchars($item['status']); ?></span>

                        <?php if ($item['reason']): ?>
                            <p class="small text-muted mt-2 mb-0">Reason: <?php echo htmlspecialchars($item['reason']); ?></p>
                        <?php endif; ?>

                        <p class="small text-muted mb-0">Last updated: <?php echo date('M j, g:i A', strtotime($item['updated_at'])); ?></p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>