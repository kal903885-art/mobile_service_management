<?php
// Only administrators can open this page
require_once __DIR__ . '/../includes/auth.php';
requireRole(['Administrator']);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
$pdo = getDatabaseConnection();

$errors = [];

// ---------- When the form is submitted ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Check the security token
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = "Your session expired. Please try again.";
    }

    // Get the values from the form
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $role_id = (int) ($_POST['role_id'] ?? 0);

    // Check that the required fields are filled
    if ($full_name === '') {
        $errors[] = "Full name is required.";
    }
    if ($username === '') {
        $errors[] = "Username is required.";
    }
    if ($email === '') {
        $errors[] = "Email is required.";
    }
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }
    if ($role_id == 0) {
        $errors[] = "Please select a role.";
    }

    // If there are no errors, save the user
    if (count($errors) == 0) {

        // Never save the plain password, save the hash
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO users (full_name, username, email, phone, password_hash, role_id, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, 1)"
            );
            $stmt->execute([$full_name, $username, $email, $phone, $password_hash, $role_id]);

            // Save a record in the audit log
            $new_id = $pdo->lastInsertId();
            $description = "Created user: " . $full_name . " (" . $username . ").";
            logAudit($pdo, $_SESSION['user_id'], 'User Added', 'users', $new_id, $description);

            // Go back to the users list
            header('Location: users.php');
            exit;

        } catch (PDOException $e) {
            $errors[] = "Username or email already exists.";
        }
    }
}

// Get the roles for the dropdown
$roles = $pdo->query("SELECT id, role_name FROM roles ORDER BY role_name")->fetchAll();

$pageTitle = 'Add User';
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

            <div class="mb-3">
                <label class="form-label">Full Name *</label>
                <input type="text" name="full_name" class="form-control" required
                       value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Username *</label>
                <input type="text" name="username" class="form-control" required
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Email *</label>
                <input type="email" name="email" class="form-control" required
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control"
                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Password *</label>
                <input type="password" name="password" class="form-control" required minlength="8">
                <small class="text-muted">Minimum 8 characters.</small>
            </div>

            <div class="mb-3">
                <label class="form-label">Role *</label>
                <select name="role_id" class="form-select" required>
                    <option value="">-- Select Role --</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['role_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button class="btn btn-primary"><i class="bi bi-check-circle"></i> Create User</button>
            <a href="users.php" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>