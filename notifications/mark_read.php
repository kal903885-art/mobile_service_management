<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
$pdo = getDatabaseConnection();

// Only a logged-in user can mark a notification as read
if (!isLoggedIn()) {
    http_response_code(401);
    exit;
}

// Get the notification id from the request
$id = (int) ($_POST['id'] ?? 0);

// Mark it as read, but only if it belongs to this user
if ($id > 0) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $_SESSION['user_id']]);
}

// Tell the page that called this that it worked
echo json_encode(['success' => true]);