<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['Administrator', 'Network Monitoring Operator']);

require_once __DIR__ . '/../config/database.php';
$pdo = getDatabaseConnection();

$message = '';

// ---------- When the form is submitted ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $site_id = (int) ($_POST['site_id'] ?? 0);

    // Get the cells that belong to this site
    $stmt = $pdo->prepare("SELECT id, technology FROM cells WHERE site_id = ?");
    $stmt->execute([$site_id]);
    $cells = $stmt->fetchAll();

    // If the site has no cells, still generate one reading with cell_id = NULL
    if (count($cells) > 0) {
        $targets = $cells;
    } else {
        $targets = [['id' => null, 'technology' => '4G']];
    }

    // Generate one random reading for each cell (or for the site itself)
    foreach ($targets as $cell) {

        // Random numbers to simulate real network readings
        $availability = round(mt_rand(9000, 10000) / 100, 2);   // 90.00–100.00
        $load = round(mt_rand(3000, 9500) / 100, 2);             // 30.00–95.00
        $signal = mt_rand(-110, -70);                             // dBm
        $latency = round(mt_rand(1000, 15000) / 100, 2);          // 10.00–150.00 ms
        $throughput = round(mt_rand(500, 15000) / 100, 2);        // Mbps
        $users_connected = mt_rand(10, 500);
        $call_success_rate = round(mt_rand(9000, 10000) / 100, 2);
        $drop_rate = round(mt_rand(0, 500) / 100, 2);
        $packet_loss = round(mt_rand(0, 300) / 100, 2);
        $download = round(mt_rand(500, 10000) / 100, 2);
        $upload = round(mt_rand(200, 5000) / 100, 2);
        $signal_quality = round(mt_rand(6000, 10000) / 100, 2);

        // Use the cell's technology, or 4G if it has none
        $technology = $cell['technology'];
        if (!$technology) {
            $technology = '4G';
        }

        $stmt = $pdo->prepare(
            "INSERT INTO network_performance
             (site_id, cell_id, recorded_date, recorded_time, technology, traffic, load_percent, availability,
              signal_strength, signal_quality, users_connected, throughput, download_speed, upload_speed,
              latency, packet_loss, call_success_rate, drop_rate)
             VALUES (?, ?, CURDATE(), CURTIME(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $site_id, $cell['id'], $technology, $throughput, $load, $availability,
            $signal, $signal_quality, $users_connected, $throughput, $download, $upload,
            $latency, $packet_loss, $call_success_rate, $drop_rate
        ]);
    }

    $message = "Generated " . count($targets) . " sample reading(s).";
}

// Get the sites for the dropdown
$sites = $pdo->query("SELECT id, site_name FROM sites ORDER BY site_name")->fetchAll();

$pageTitle = 'Generate Sample Reading';
require_once __DIR__ . '/../includes/staff_header.php';
?>

<!-- Success message -->
<?php if ($message != ''): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle"></i> This generates <strong>random demonstration data</strong> for testing/learning purposes only — it does not reflect any real network measurement.
</div>

<div class="card shadow-sm" style="max-width: 500px;">
    <div class="card-body">
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Site</label>
                <select name="site_id" class="form-select" required>
                    <option value="">-- Select Site --</option>
                    <?php foreach ($sites as $site): ?>
                        <option value="<?php echo $site['id']; ?>"><?php echo htmlspecialchars($site['site_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primary w-100"><i class="bi bi-shuffle"></i> Generate Reading</button>
        </form>
        <a href="index.php" class="btn btn-link mt-2 d-block text-center">Back to Performance List</a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>