<?php
// Only administrators can open this page
require_once __DIR__ . '/../includes/auth.php';
requireRole(['Administrator']);

// Connect to the database
require_once __DIR__ . '/../config/database.php';
$pdo = getDatabaseConnection();

$errors = [];
$message = '';

// ---------- Handle the forms ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    try {
        // Add a region
        if ($action === 'add_region') {
            $name = trim($_POST['region_name'] ?? '');

            if ($name !== '') {
                $stmt = $pdo->prepare("INSERT INTO regions (region_name) VALUES (?)");
                $stmt->execute([$name]);
                $message = "Region added.";
            }
        }

        // Add a zone
        if ($action === 'add_zone') {
            $region_id = (int) ($_POST['region_id'] ?? 0);
            $name = trim($_POST['zone_name'] ?? '');

            if ($region_id != 0 && $name !== '') {
                $stmt = $pdo->prepare("INSERT INTO zones (region_id, zone_name) VALUES (?, ?)");
                $stmt->execute([$region_id, $name]);
                $message = "Zone added.";
            }
        }

        // Add a woreda
        if ($action === 'add_woreda') {
            $zone_id = (int) ($_POST['zone_id'] ?? 0);
            $name = trim($_POST['woreda_name'] ?? '');

            if ($zone_id != 0 && $name !== '') {
                $stmt = $pdo->prepare("INSERT INTO woredas (zone_id, woreda_name) VALUES (?, ?)");
                $stmt->execute([$zone_id, $name]);
                $message = "Woreda added.";
            }
        }

        // Add a kebele
        if ($action === 'add_kebele') {
            $woreda_id = (int) ($_POST['woreda_id'] ?? 0);
            $name = trim($_POST['kebele_name'] ?? '');

            if ($woreda_id != 0 && $name !== '') {
                $stmt = $pdo->prepare("INSERT INTO kebeles (woreda_id, kebele_name) VALUES (?, ?)");
                $stmt->execute([$woreda_id, $name]);
                $message = "Kebele added.";
            }
        }

        // Add a service area
        if ($action === 'add_service_area') {
            $name = trim($_POST['area_name'] ?? '');
            $region_id = (int) ($_POST['region_id'] ?? 0);
            $zone_id = (int) ($_POST['zone_id'] ?? 0);
            $woreda_id = (int) ($_POST['woreda_id'] ?? 0);
            $kebele_id = (int) ($_POST['kebele_id'] ?? 0);

            // Kebele is optional, so use NULL when nothing was picked
            if ($kebele_id == 0) {
                $kebele_id = null;
            }

            if ($name !== '' && $region_id != 0 && $zone_id != 0 && $woreda_id != 0) {
                $stmt = $pdo->prepare(
                    "INSERT INTO service_areas (area_name, region_id, zone_id, woreda_id, kebele_id)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->execute([$name, $region_id, $zone_id, $woreda_id, $kebele_id]);
                $message = "Service area added.";
            }
        }

    } catch (PDOException $e) {
        $errors[] = "Database error: could not save. It may already exist.";
    }
}

// ---------- Get the data to show on the page ----------
$regions = $pdo->query("SELECT * FROM regions ORDER BY region_name")->fetchAll();

$zones = $pdo->query(
    "SELECT z.*, r.region_name
     FROM zones z
     JOIN regions r ON z.region_id = r.id
     ORDER BY z.zone_name"
)->fetchAll();

$woredas = $pdo->query(
    "SELECT w.*, z.zone_name
     FROM woredas w
     JOIN zones z ON w.zone_id = z.id
     ORDER BY w.woreda_name"
)->fetchAll();

$kebeles = $pdo->query(
    "SELECT k.*, w.woreda_name
     FROM kebeles k
     JOIN woredas w ON k.woreda_id = w.id
     ORDER BY k.kebele_name"
)->fetchAll();

$service_areas = $pdo->query(
    "SELECT sa.*, r.region_name, z.zone_name, w.woreda_name
     FROM service_areas sa
     JOIN regions r ON sa.region_id = r.id
     JOIN zones z ON sa.zone_id = z.id
     JOIN woredas w ON sa.woreda_id = w.id
     ORDER BY sa.area_name"
)->fetchAll();

$pageTitle = 'Manage Areas';
require_once __DIR__ . '/../includes/staff_header.php';
?>

<!-- Success message -->
<?php if ($message != ''): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<!-- Error messages -->
<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endforeach; ?>

<div class="row g-3">

    <!-- Regions -->
    <div class="col-md-3">
        <div class="card shadow-sm mb-3">
            <div class="card-header">Regions</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($regions as $region): ?>
                    <li class="list-group-item"><?php echo htmlspecialchars($region['region_name']); ?></li>
                <?php endforeach; ?>
            </ul>
            <div class="card-body">
                <form method="POST" class="d-flex gap-2">
                    <input type="hidden" name="action" value="add_region">
                    <input type="text" name="region_name" class="form-control form-control-sm" placeholder="New region" required>
                    <button class="btn btn-sm btn-primary">Add</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Zones -->
    <div class="col-md-3">
        <div class="card shadow-sm mb-3">
            <div class="card-header">Zones</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($zones as $zone): ?>
                    <li class="list-group-item">
                        <?php echo htmlspecialchars($zone['zone_name'] . ' (' . $zone['region_name'] . ')'); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_zone">
                    <select name="region_id" class="form-select form-select-sm mb-2" required>
                        <option value="">-- Region --</option>
                        <?php foreach ($regions as $region): ?>
                            <option value="<?php echo $region['id']; ?>"><?php echo htmlspecialchars($region['region_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="zone_name" class="form-control form-control-sm mb-2" placeholder="New zone" required>
                    <button class="btn btn-sm btn-primary w-100">Add</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Woredas -->
    <div class="col-md-3">
        <div class="card shadow-sm mb-3">
            <div class="card-header">Woredas</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($woredas as $woreda): ?>
                    <li class="list-group-item">
                        <?php echo htmlspecialchars($woreda['woreda_name'] . ' (' . $woreda['zone_name'] . ')'); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_woreda">
                    <select name="zone_id" class="form-select form-select-sm mb-2" required>
                        <option value="">-- Zone --</option>
                        <?php foreach ($zones as $zone): ?>
                            <option value="<?php echo $zone['id']; ?>"><?php echo htmlspecialchars($zone['zone_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="woreda_name" class="form-control form-control-sm mb-2" placeholder="New woreda" required>
                    <button class="btn btn-sm btn-primary w-100">Add</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Kebeles -->
    <div class="col-md-3">
        <div class="card shadow-sm mb-3">
            <div class="card-header">Kebeles</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($kebeles as $kebele): ?>
                    <li class="list-group-item">
                        <?php echo htmlspecialchars($kebele['kebele_name'] . ' (' . $kebele['woreda_name'] . ')'); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_kebele">
                    <select name="woreda_id" class="form-select form-select-sm mb-2" required>
                        <option value="">-- Woreda --</option>
                        <?php foreach ($woredas as $woreda): ?>
                            <option value="<?php echo $woreda['id']; ?>"><?php echo htmlspecialchars($woreda['woreda_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="kebele_name" class="form-control form-control-sm mb-2" placeholder="New kebele" required>
                    <button class="btn btn-sm btn-primary w-100">Add</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Service Areas -->
<div class="card shadow-sm">
    <div class="card-header">Service Areas</div>
    <div class="card-body">

        <table class="table table-sm mb-4">
            <thead>
                <tr>
                    <th>Area Name</th>
                    <th>Region</th>
                    <th>Zone</th>
                    <th>Woreda</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($service_areas as $area): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($area['area_name']); ?></td>
                        <td><?php echo htmlspecialchars($area['region_name']); ?></td>
                        <td><?php echo htmlspecialchars($area['zone_name']); ?></td>
                        <td><?php echo htmlspecialchars($area['woreda_name']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Form to add a service area -->
        <form method="POST" class="row g-2">
            <input type="hidden" name="action" value="add_service_area">

            <div class="col-md-3">
                <input type="text" name="area_name" class="form-control" placeholder="Area name" required>
            </div>

            <div class="col-md-2">
                <select name="region_id" class="form-select" required>
                    <option value="">Region</option>
                    <?php foreach ($regions as $region): ?>
                        <option value="<?php echo $region['id']; ?>"><?php echo htmlspecialchars($region['region_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <select name="zone_id" class="form-select" required>
                    <option value="">Zone</option>
                    <?php foreach ($zones as $zone): ?>
                        <option value="<?php echo $zone['id']; ?>"><?php echo htmlspecialchars($zone['zone_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <select name="woreda_id" class="form-select" required>
                    <option value="">Woreda</option>
                    <?php foreach ($woredas as $woreda): ?>
                        <option value="<?php echo $woreda['id']; ?>"><?php echo htmlspecialchars($woreda['woreda_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <select name="kebele_id" class="form-select">
                    <option value="">Kebele (optional)</option>
                    <?php foreach ($kebeles as $kebele): ?>
                        <option value="<?php echo $kebele['id']; ?>"><?php echo htmlspecialchars($kebele['kebele_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-1">
                <button class="btn btn-primary w-100">Add</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>