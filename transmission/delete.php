<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
$pdo = getDatabaseConnection();

// Only a logged-in Administrator can use this page
if (!isLoggedIn() || $_SESSION['role_name'] !== 'Administrator') {
    die("Access denied.");
}

// Only handle a form submission that includes a link id
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['link_id'])) {

    // Check the security token
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        die("Your session expired. Please try again.");
    }

    $id = (int) $_POST['link_id'];

    // Get the link details first, so we can mention them in the audit log
    $stmt = $pdo->prepare("SELECT link_name, link_code FROM transmission_links WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $link = $stmt->fetch(PDO::FETCH_ASSOC);

    // Delete the link
    $stmt = $pdo->prepare("DELETE FROM transmission_links WHERE id = :id");
    $stmt->execute([':id' => $id]);

    // Save a record in the audit log
    if ($link) {
        $description = "Deleted link: " . $link['link_name'] . " (" . $link['link_code'] . ").";
        logAudit($pdo, $_SESSION['user_id'], 'Transmission Link Deleted', 'transmission_links', $id, $description);
    }
}

// Go back to the links list
header("Location: index.php");
exit;