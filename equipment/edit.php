<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
$pdo = getDatabaseConnection();

// Only a logged-in Administrator can use this page
if (!isLoggedIn() || $_SESSION['role_name'] !== 'Administrator') {
    die("Access denied.");
}

// Get the equipment id from the link
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: index.php");
    exit;
}

// Find the equipment in the database
$stmt = $pdo->prepare("SELECT * FROM equipment WHERE id = :id");
$stmt->execute([':id' => $id]);
$equipment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$equipment) {
    die("Equipment not found.");
}

// Get the sites for the dropdown
$sites = $pdo->query("SELECT id, site_name FROM sites ORDER BY site_name")->fetchAll(PDO::FETCH_ASSOC);

$errors = [];

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

    // If there are no errors, update the equipment
    if (count($errors) == 0) {
        $stmt = $pdo->prepare(
            "UPDATE equipment SET
                equipment_code = :code,
                equipment_name = :name,
                equipment_type = :type,
                vendor = :vendor,
                model = :model,
                serial_number = :serial,
                site_id = :site_id,
                status = :status,
                installation_date = :installation_date
             WHERE id = :id"
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
                ':installation_date' => $install_date,
                ':id' => $id
            ]);

            // Save a record in the audit log
            logAudit($pdo, $_SESSION['user_id'], 'Equipment Updated', 'equipment', $id, "Updated equipment: $name ($code).");

            // Go back to the equipment list
            header("Location: index.php");
            exit;

        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }

    // If there were errors, keep what the admin typed so it is not lost
    $equipment = array_merge($equipment, $_POST);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Equipment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4" style="max-width:600px;">
    <h3>Edit Equipment</h3>

    <!-- Show errors -->
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">

        <div class="mb-3">
            <label class="form-label">Equipment Code</label>
            <input type="text" name="equipment_code" class="form-control" required
                   value="<?php echo htmlspecialchars($equipment['equipment_code']); ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Equipment Name</label>
            <input type="text" name="equipment_name" class="form-control" required
                   value="<?php echo htmlspecialchars($equipment['equipment_name']); ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Equipment Type</label>
            <select name="equipment_type" class="form-select" required>
                <?php foreach ($equipment_types as $type): ?>
                    <?php
                    // Select the type this equipment already has
                    $selected = '';
                    if ($equipment['equipment_type'] === $type) {
                        $selected = 'selected';
                    }
                    ?>
                    <option value="<?php echo $type; ?>" <?php echo $selected; ?>><?php echo $type; ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Vendor</label>
            <input type="text" name="vendor" class="form-control" value="<?php echo htmlspecialchars($equipment['vendor'] ?? ''); ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Model</label>
            <input type="text" name="model" class="form-control" value="<?php echo htmlspecialchars($equipment['model'] ?? ''); ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Serial Number</label>
            <input type="text" name="serial_number" class="form-control" value="<?php echo htmlspecialchars($equipment['serial_number'] ?? ''); ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Site</label>
            <select name="site_id" class="form-select" required>
                <?php foreach ($sites as $site): ?>
                    <?php
                    // Select the site this equipment already has
                    $selected = '';
                    if ($equipment['site_id'] == $site['id']) {
                        $selected = 'selected';
                    }
                    ?>
                    <option value="<?php echo $site['id']; ?>" <?php echo $selected; ?>>
                        <?php echo htmlspecialchars($site['site_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <?php foreach ($status_options as $status): ?>
                    <?php
                    // Select the status this equipment already has
                    $selected = '';
                    if ($equipment['status'] === $status) {
                        $selected = 'selected';
                    }
                    ?>
                    <option value="<?php echo $status; ?>" <?php echo $selected; ?>><?php echo $status; ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Installation Date</label>
            <input type="date" name="installation_date" class="form-control"
                   value="<?php echo htmlspecialchars($equipment['installation_date'] ?? ''); ?>">
        </div>

        <button class="btn btn-primary" type="submit">Update Equipment</button>
        <a href="index.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>
</body>
</html>