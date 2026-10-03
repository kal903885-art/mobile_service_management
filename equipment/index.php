<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$pdo = getDatabaseConnection();

// ---------- Access control ----------
if (!isLoggedIn()) {
    die("DEBUG: Not logged in. \$_SESSION user_id is not set.");
}
if (!isset($_SESSION['role_name'])) {
    die("DEBUG: Logged in, but role_name is missing from session. Session contents: " . print_r($_SESSION, true));
}

$allowed_roles = ['Administrator', 'Network Operator', 'Network Engineer'];
if (!in_array($_SESSION['role_name'], $allowed_roles)) {
    die("Access denied.");
}

$is_admin = false;
if ($_SESSION['role_name'] === 'Administrator') {
    $is_admin = true;
}

// ---------- Filters ----------
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type_filter = isset($_GET['type']) ? trim($_GET['type']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

// Start the query (WHERE 1=1 makes it easy to add more conditions)
$sql = "SELECT e.*, s.site_name
        FROM equipment e
        JOIN sites s ON e.site_id = s.id
        WHERE 1=1";
$params = [];

// Search by code, name or serial number
if ($search !== '') {
    $sql .= " AND (e.equipment_code LIKE :search1
                OR e.equipment_name LIKE :search2
                OR e.serial_number LIKE :search3)";
    $params[':search1'] = "%$search%";
    $params[':search2'] = "%$search%";
    $params[':search3'] = "%$search%";
}

// Filter by type
if ($type_filter !== '') {
    $sql .= " AND e.equipment_type = :type";
    $params[':type'] = $type_filter;
}

// Filter by status
if ($status_filter !== '') {
    $sql .= " AND e.status = :status";
    $params[':status'] = $status_filter;
}

$sql .= " ORDER BY e.created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $equipment_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("DEBUG: Database query failed: " . $e->getMessage());
}

// Lists used by the filter dropdowns
$equipment_types = ['BTS', 'NodeB', 'eNodeB', 'gNodeB', 'BBU', 'RRU', 'Router', 'Switch', 'Rectifier', 'Battery'];
$status_options = ['Active', 'Inactive', 'Faulty', 'Under Maintenance'];

// Give each status a badge color
function equipmentStatusColor($status)
{
    if ($status === 'Active') {
        return 'success';
    }
    if ($status === 'Inactive') {
        return 'secondary';
    }
    if ($status === 'Faulty') {
        return 'danger';
    }
    if ($status === 'Under Maintenance') {
        return 'warning';
    }
    return 'secondary';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Equipment Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Equipment Management</h3>
        <?php if ($is_admin): ?>
            <a href="add.php" class="btn btn-primary">+ Add Equipment</a>
        <?php endif; ?>
    </div>

    <!-- Search and filter form -->
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control"
                   placeholder="Search code, name, serial..."
                   value="<?php echo htmlspecialchars($search); ?>">
        </div>

        <div class="col-md-3">
            <select name="type" class="form-select">
                <option value="">All Types</option>
                <?php foreach ($equipment_types as $type): ?>
                    <?php
                    $selected = '';
                    if ($type_filter === $type) {
                        $selected = 'selected';
                    }
                    ?>
                    <option value="<?php echo $type; ?>" <?php echo $selected; ?>><?php echo $type; ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="">All Statuses</option>
                <?php foreach ($status_options as $status): ?>
                    <?php
                    $selected = '';
                    if ($status_filter === $status) {
                        $selected = 'selected';
                    }
                    ?>
                    <option value="<?php echo $status; ?>" <?php echo $selected; ?>><?php echo $status; ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <button class="btn btn-outline-secondary w-100" type="submit">Filter</button>
        </div>
    </form>

    <!-- Equipment table -->
    <table class="table table-bordered table-hover bg-white">
        <thead class="table-dark">
        <tr>
            <th>Code</th>
            <th>Name</th>
            <th>Type</th>
            <th>Vendor / Model</th>
            <th>Site</th>
            <th>Status</th>
            <th>Installed</th>
            <?php if ($is_admin): ?><th>Actions</th><?php endif; ?>
        </tr>
        </thead>
        <tbody>
            <?php if (count($equipment_list) == 0): ?>
                <tr><td colspan="8" class="text-center">No equipment found.</td></tr>
            <?php endif; ?>

            <?php foreach ($equipment_list as $equipment): ?>
                <?php
                // Show a dash if there is no installation date
                $installed_date = $equipment['installation_date'];
                if (!$installed_date) {
                    $installed_date = '-';
                }
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($equipment['equipment_code']); ?></td>
                    <td><?php echo htmlspecialchars($equipment['equipment_name']); ?></td>
                    <td><?php echo htmlspecialchars($equipment['equipment_type']); ?></td>
                    <td><?php echo htmlspecialchars($equipment['vendor'] . ' / ' . $equipment['model']); ?></td>
                    <td><?php echo htmlspecialchars($equipment['site_name']); ?></td>
                    <td>
                        <span class="badge bg-<?php echo equipmentStatusColor($equipment['status']); ?>">
                            <?php echo $equipment['status']; ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($installed_date); ?></td>

                    <?php if ($is_admin): ?>
                        <td>
                            <a href="edit.php?id=<?php echo $equipment['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                            <form action="delete.php" method="POST" class="d-inline"
                                  onsubmit="return confirm('Delete this equipment?');">
                                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                <input type="hidden" name="equipment_id" value="<?php echo $equipment['id']; ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>