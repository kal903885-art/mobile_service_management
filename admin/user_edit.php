<?php
// Only administrators can open this page
require_once __DIR__ . '/../includes/auth.php';
requireRole(['Administrator']);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
$pdo = getDatabaseConnection();

// Get the user id (from the link or from the form)
if (isset($_GET['id'])) {
    $id = (int) $_GET['id'];
} elseif (isset($_POST['id'])) {
    $id = (int) $_POST['id'];
} else {
    $id = 0;
}

// Find the user in the database
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    die("User not found.");
}

$errors = [];

// ---------- When the form is submitted ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Check the security token
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = "Your session expired. Please try again.";
    }

    // Get the values from the form
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role_id = (int) ($_POST['role_id'] ?? 0);
    $new_password = $_POST['new_password'] ?? '';

    // Check the values
    if ($full_name === '') {
        $errors[] = "Full name is required.";
    }
    if ($email === '') {
        $errors[] = "Email is required.";
    }
    if ($role_id == 0) {
        $errors[] = "Please select a role.";
    }
    if ($new_password !== '' && strlen($new_password) < 8) {
        $errors[] = "New password must be at least 8 characters.";
    }

    // If there are no errors, update the user
    if (count($errors) == 0) {

        if ($new_password !== '') {
            // Update everything including the new password
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                "UPDATE users SET full_name = ?, email = ?, phone = ?, role_id = ?, password_hash = ? WHERE id = ?"
            );
            $stmt->execute([$full_name, $email, $phone, $role_id, $password_hash, $id]);

            $description = "Updated user: " . $full_name . ". Password was reset.";
        } else {
            // Update without touching the password
            $stmt = $pdo->prepare(
                "UPDATE users SET full_name = ?, email = ?, phone = ?, role_id = ? WHERE id = ?"
            );
            $stmt->execute([$full_name, $email, $phone, $role_id, $id]);

            $description = "Updated user: " . $full_name . ".";
        }

        // Save a record in the audit log
        logAudit($pdo, $_SESSION['user_id'], 'User Updated', 'users', $id, $description);

        // Go back to the users list
        header('Location: users.php');
        exit;
    }

    // If there were errors, keep what the admin typed so it is not lost
    $user = array_merge($user, $_POST);
}

// Get the roles for the dropdown
$roles = $pdo->query("SELECT id, role_name FROM roles ORDER BY role_name")->fetchAll();

$pageTitle = 'Edit User';
require_once __DIR__ . '/../includes/staff_header.php';
?>

<!-- Show errors -->
<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endforeach; ?>

<div class="card shadow-sm" style="max-width: 600px;">
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
            <input type="hidden" name="id" value="<?php echo $id; ?>">

            <!-- Username cannot be changed -->
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
            </div>

            <div class="mb-3">
                <label class="form-label">Full Name *</label>
                <input type="text" name="full_name" class="form-control" required
                       value="<?php echo htmlspecialchars($user['full_name']); ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Email *</label>
                <input type="email" name="email" class="form-control" required
                       value="<?php echo htmlspecialchars($user['email']); ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control"
                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Role *</label>
                <select name="role_id" class="form-select" required>
                    <?php foreach ($roles as $role): ?>
                        <?php
                        // Select the role the user already has
                        $selected = '';
                        if ($user['role_id'] == $role['id']) {
                            $selected = 'selected';
                        }
                        ?>
                        <option value="<?php echo $role['id']; ?>" <?php echo $selected; ?>>
                            <?php echo htmlspecialchars($role['role_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Reset Password</label>
                <input type="password" name="new_password" class="form-control" minlength="8"
                       placeholder="Leave blank to keep current password">
            </div>

            <button class="btn btn-primary"><i class="bi bi-check-circle"></i> Save Changes</button>
            <a href="users.php" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>