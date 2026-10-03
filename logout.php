<?php
require_once __DIR__ . '/includes/auth.php';

// End the session and send the user back to the login page
logoutUser();
header('Location: login.php');
exit;