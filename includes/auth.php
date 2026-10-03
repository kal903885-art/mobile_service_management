<?php
// Authentication & session helper functions.
// Must be included at the very top of any page that needs login checks,
// BEFORE any HTML output.

// Harden session cookie settings before starting the session.
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Log out automatically after this many seconds of inactivity.
define('SESSION_TIMEOUT_SECONDS', 1800); // 30 minutes

/**
 * Log a user in by storing their info in the session.
 */
function loginUser(array $user): void {
    // Prevent session fixation: issue a brand new session ID on login.
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role_id'] = $user['role_id'];
    $_SESSION['role_name'] = $user['role_name'];
    $_SESSION['logged_in_at'] = time();
    $_SESSION['last_activity'] = time();
}

/**
 * Is anyone logged in right now? Also enforces the inactivity timeout.
 */
function isLoggedIn(): bool {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT_SECONDS) {
        logoutUser();
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Is the logged-in user a Customer?
 */
function isCustomer(): bool {
    return isLoggedIn() && $_SESSION['role_name'] === 'Customer';
}

/**
 * Is the logged-in user staff (any non-customer role)?
 */
function isStaff(): bool {
    return isLoggedIn() && $_SESSION['role_name'] !== 'Customer';
}

/**
 * Force login: redirect to login page if not authenticated.
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /mobile-network-service-management/login.php');
        exit;
    }
}

/**
 * Force a specific role (or one of several). Redirects to unauthorized.php if not matched.
 * Example: requireRole(['Administrator', 'Network Engineer']);
 */
function requireRole(array $allowedRoles): void {
    requireLogin();
    if (!in_array($_SESSION['role_name'], $allowedRoles, true)) {
        header('Location: /mobile-network-service-management/unauthorized.php');
        exit;
    }
}

/**
 * Log the user out and destroy the session.
 */
function logoutUser(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

/**
 * Generate (or reuse) a CSRF token for the current session.
 * Call this when rendering a form: <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
 */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a submitted CSRF token. Call this at the top of every POST handler,
 * before touching the database:
 *   if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) { die('Invalid request.'); }
 */
function verifyCsrfToken(?string $token): bool {
    return isset($_SESSION['csrf_token']) && $token !== null && hash_equals($_SESSION['csrf_token'], $token);
}