<?php
// Only administrators can open this page
require_once __DIR__ . '/../includes/auth.php';
requireRole(['Administrator']);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
$pdo = getDatabaseConnection();

// Get the filter values from the URL
$role_filter = $_GET['role'] ?? '';
$search = trim($_GET['search'] ?? '');

// Start the query (WHERE 1=1 makes it easy to add more conditions)
$sql = "SELECT u.*, r.role_name
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE 1=1";
$params = [];

// Filter by role
if ($role_filter !== '') {
    $sql .= " AND r.role_name = :role";
    $params[':role'] = $role_filter;
}

// Search by name, username or email
if ($search !== '') {
    $sql .= " AND (u.full_name LIKE :search1 OR u.username LIKE :search2 OR u.email LIKE :search3)";
    $params[':search1'] = "%" . $search . "%";
    $params[':search2'] = "%" . $search . "%";
    $params[':search3'] = "%" . $search . "%";
}

$sql .= " ORDER BY u.full_name";

// Run the query
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get the role names for the filter dropdown
$roles = $pdo->query("SELECT role_name FROM roles ORDER BY role_name")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'User Management';
require_once __DIR__ . '/../includes/staff_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">All Users</h5>
    <a href="user_add.php" class="btn btn-primary"><i class="bi bi-person-plus"></i> Add User</a>
</div>

<!-- Search and filter form -->
<form method="GET" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="text" name="search" class="form-control" placeholder="Search name, username, email..."
               value="<?php echo htmlspecialchars($search); ?>">
    </div>

    <div class="col-md-3">
        <select name="role" class="form-select">
            <option value="">All Roles</option>
            <?php foreach ($roles as $role): ?>
                <?php
                // Keep the chosen role selected after filtering
                $selected = '';
                if ($role_filter === $role) {
                    $selected = 'selected';
                }
                ?>
                <option value="<?php echo htmlspecialchars($role); ?>" <?php echo $selected; ?>>
                    <?php echo htmlspecialchars($role); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-2">
        <button class="btn btn-outline-secondary w-100" type="submit">Filter</button>
    </div>
</form>

<!-- Users table -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>

                <!-- Message when there are no users -->
                <?php if (count($users) == 0): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted p-3">No users found.</td>
                    </tr>
                <?php endif; ?>

                <!-- One row for each user -->
                <?php foreach ($users as $user): ?>
                    <?php
                    // Decide the words and colors for the button
                    if ($user['is_active']) {
                        $button_text = 'Deactivate';
                        $button_color = 'danger';
                    } else {
                        $button_text = 'Activate';
                        $button_color = 'success';
                    }
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($user['role_name']); ?></span></td>
                        <td>
                            <?php if ($user['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="user_edit.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>

                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <!-- Activate / Deactivate button (not shown for your own account) -->
                                <form action="toggle_user.php" method="POST" class="d-inline"
                                      onsubmit="return confirm('<?php echo $button_text; ?> this user?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button class="btn btn-sm btn-outline-<?php echo $button_color; ?>">
                                        <?php echo $button_text; ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted small">(You)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>