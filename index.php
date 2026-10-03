<?php
// Phase 1: basic test page to confirm PHP + Bootstrap + DB connection work
require_once __DIR__ . '/config/database.php';

$db_status = "Not connected";
$db_status_class = "danger";

// Try to connect to the database and see if it works
try {
    $pdo = getDatabaseConnection();
    $db_status = "Connected successfully!";
    $db_status_class = "success";
} catch (PDOException $e) {
    $db_status = "Connection failed: " . $e->getMessage();
    $db_status_class = "danger";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mobile Network Service Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container py-5">
        <div class="text-center mb-4">
            <i class="bi bi-broadcast-pin" style="font-size: 3rem; color: #0d6efd;"></i>
            <h1 class="mt-3">Mobile Network Service Management</h1>
            <p class="text-muted">Customer Complaint Reporting System — Phase 1 Setup Check</p>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">System Check</h5>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                PHP Version
                                <span class="badge bg-primary"><?php echo phpversion(); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Bootstrap
                                <span class="badge bg-success">Loaded</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Database Connection
                                <span class="badge bg-<?php echo $db_status_class; ?>"><?php echo $db_status; ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>