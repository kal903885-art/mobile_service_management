<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
$pdo = getDatabaseConnection();

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Mark all of this user's notifications as read when they visit this page
$stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
$stmt->execute([$user_id]);

// Get the notifications to show
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Notifications';

// Customers and staff each have their own header/footer
if (isCustomer()) {
    require_once '../includes/customer_header.php';
} else {
    require_once '../includes/staff_header.php';
}
?>

<h3 class="mb-4"><i class="bi bi-bell"></i> Notifications</h3>

<?php if (count($notifications) == 0): ?>

    <div class="alert alert-secondary">You have no notifications yet.</div>

<?php else: ?>

    <div class="list-group">
        <?php foreach ($notifications as $notification): ?>
            <div class="list-group-item">
                <div class="d-flex justify-content-between">
                    <strong><?php echo htmlspecialchars($notification['title']); ?></strong>
                    <small class="text-muted"><?php echo date('M j, g:i A', strtotime($notification['created_at'])); ?></small>
                </div>
                <div class="text-muted"><?php echo htmlspecialchars($notification['message']); ?></div>
            </div>
        <?php endforeach; ?>
    </div>

<?php endif; ?>

<?php
// Customers and staff each have their own footer
if (isCustomer()) {
    require_once '../includes/footer.php';
} else {
    require_once '../includes/staff_footer.php';
}
?>