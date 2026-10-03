<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
$pdo = getDatabaseConnection();

// Only a logged-in Administrator can use this page
if (!isLoggedIn() || $_SESSION['role_name'] !== 'Administrator') {
    die("Access denied.");
}

// Only handle a form submission that includes an equipment id
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['equipment_id'])) {

    // Check the security token
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        die("Your session expired. Please try again.");
    }

    $id = (int) $_POST['equipment_id'];

    // Get the equipment details first, so we can mention them in the audit log
    $stmt = $pdo->prepare("SELECT equipment_name, equipment_code FROM equipment WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $equipment = $stmt->fetch(PDO::FETCH_ASSOC);

    // Delete the equipment
    $stmt = $pdo->prepare("DELETE FROM equipment WHERE id = :id");
    $stmt->execute([':id' => $id]);

    // Save a record in the audit log
    if ($equipment) {
        $description = "Deleted equipment: " . $equipment['equipment_name'] . " (" . $equipment['equipment_code'] . ").";
        logAudit($pdo, $_SESSION['user_id'], 'Equipment Deleted', 'equipment', $id, $description);
    }
}

// Go back to the equipment list
header("Location: index.php");
exit;