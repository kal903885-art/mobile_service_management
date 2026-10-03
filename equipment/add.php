<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
$pdo = getDatabaseConnection();

// Only a logged-in Administrator can use this page
if (!isLoggedIn() || $_SESSION['role_name'] !== 'Administrator') {
    die("Access denied.");
}

$errors = [];

// Get the sites for the dropdown
$sites = $pdo->query("SELECT id, site_name FROM sites ORDER BY site_name")->fetchAll(PDO::FETCH_ASSOC);

// Lists for the dropdowns
$equipment_types = ['BTS', 'NodeB', 'eNodeB', 'gNodeB', 'BBU', 'RRU', 'Router', 'Switch', 'Rectifier', 'Battery'];
$status_options = ['Active', 'Inactive', 'Faulty', 'Under Maintenance'];

// ---------- When the form is submitted ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Check the security token
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = "Your session expired. Please try again.";
    }

    // Get the values from the form
    $code = trim($_POST['equipment_code']);
    $name = trim($_POST['equipment_name']);
    $type = $_POST['equipment_type'];
    $vendor = trim($_POST['vendor']);
    $model = trim($_POST['model']);
    $serial = trim($_POST['serial_number']);
    $site_id = (int) $_POST['site_id'];
    $status = $_POST['status'];
    $install_date = $_POST['installation_date'];

    // Installation date is optional, so use NULL if it is empty
    if ($install_date == '') {
        $install_date = null;
    }

    // Check the required fields
    if ($code === '') {
        $errors[] = "Equipment code is required.";
    }
    if ($name === '') {
        $errors[] = "Equipment name is required.";
    }
    if ($site_id <= 0) {
        $errors[] = "Please select a site.";
    }

    // If there are no errors, save the equipment
    if (count($errors) == 0) {
        $stmt = $pdo->prepare(
            "INSERT INTO equipment
                (equipment_code, equipment_name, equipment_type, vendor, model, serial_number, site_id, status, installation_date)
             VALUES (:code, :name, :type, :vendor, :model, :serial, :site_id, :status, :installation_date)"
        );

        try {
            $stmt->execute([
                ':code' => $code,
                ':name' => $name,
                ':type' => $type,
                ':vendor' => $vendor,
                ':model' => $model,
                ':serial' => $serial,
                ':site_id' => $site_id,
                ':status' => $status,
                ':installation_date' => $install_date
            ]);

            // Save a record in the audit log
            $new_id = $pdo->lastInsertId();
            logAudit($pdo, $_SESSION['user_id'], 'Equipment Added', 'equipment', $new_id, "Added equipment: $name ($code).");

            // Go back to the equipment list
            header("Location: index.php");
            exit;

        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $errors[] = "Equipment code already exists.";
            } else {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Equipment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4" style="max-width:600px;">
    <h3>Add Equipment</h3>

    <!-- Show errors -->
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">

        <div class="mb-3">
            <label class="form-label">Equipment Code</label>
            <input type="text" name="equipment_code" class="form-control" required
                   value="<?php echo htmlspecialchars($_POST['equipment_code'] ?? ''); ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Equipment Name</label>
            <input type="text" name="equipment_name" class="form-control" required
                   value="<?php echo htmlspecialchars($_POST['equipment_name'] ?? ''); ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Equipment Type</label>
            <select name="equipment_type" class="form-select" required>
                <?php foreach ($equipment_types as $type): ?>
                    <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Vendor</label>
            <input type="text" name="vendor" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label">Model</label>
            <input type="text" name="model" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label">Serial Number</label>
            <input type="text" name="serial_number" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label">Site</label>
            <select name="site_id" class="form-select" required>
                <option value="">-- Select Site --</option>
                <?php foreach ($sites as $site): ?>
                    <option value="<?php echo $site['id']; ?>"><?php echo htmlspecialchars($site['site_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <?php foreach ($status_options as $status): ?>
                    <option value="<?php echo $status; ?>"><?php echo $status; ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Installation Date</label>
            <input type="date" name="installation_date" class="form-control">
        </div>

        <button class="btn btn-primary" type="submit">Save Equipment</button>
        <a href="index.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>
</body>
</html>