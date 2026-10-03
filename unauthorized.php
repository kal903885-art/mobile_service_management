<?php require_once __DIR__ . '/includes/auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-5 text-center">
        <h1 class="text-danger"><i class="bi bi-shield-lock"></i> Access Denied</h1>
        <p>You don't have permission to view this page.</p>
        <a href="login.php" class="btn btn-primary">Back to Login</a>
    </div>
</body>
</html>