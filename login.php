<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// If already logged in, send the user straight to their dashboard
if (isLoggedIn()) {
    if (isCustomer()) {
        header('Location: customer/dashboard.php');
    } else {
        header('Location: staff/dashboard.php');
    }
    exit;
}

$error = '';

// ---------- When the form is submitted ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = getDatabaseConnection();

    // Check the security token
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = "Your session expired. Please try logging in again.";
    } else {

        $username_or_email = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        // Both fields are required
        if ($username_or_email === '' || $password === '') {
            $error = "Please enter both username/email and password.";
        } else {
            try {
                // Look for a matching, active user by username or email
                $stmt = $pdo->prepare(
                    "SELECT u.*, r.role_name FROM users u
                     JOIN roles r ON u.role_id = r.id
                     WHERE (u.username = ? OR u.email = ?) AND u.is_active = 1"
                );
                $stmt->execute([$username_or_email, $username_or_email]);
                $user = $stmt->fetch();

                // Check the password matches
                if ($user && password_verify($password, $user['password_hash'])) {

                    // Success: log it and start the session
                    logAudit($pdo, $user['id'], 'Login Success', 'auth', null, "User " . $user['username'] . " logged in.");
                    loginUser($user);

                    if ($user['role_name'] === 'Customer') {
                        header('Location: customer/dashboard.php');
                    } else {
                        header('Location: staff/dashboard.php');
                    }
                    exit;

                } else {
                    // Failed: log the attempt and show an error
                    $failed_user_id = null;
                    if ($user) {
                        $failed_user_id = $user['id'];
                    }
                    logAudit($pdo, $failed_user_id, 'Login Failed', 'auth', null, "Failed login attempt for '$username_or_email'.");
                    $error = "Invalid username/email or password.";
                }
            } catch (PDOException $e) {
                $error = "Unable to connect to the database. Please try again later.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Mobile Network Service Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/theme.css?v=<?php echo filemtime(__DIR__ . '/assets/css/theme.css'); ?>" rel="stylesheet">

    <style>
        /* The page is split in two halves: left = picture side, right = form */
        body {
            background: #ffffff;
        }

        .login-page {
            display: grid;
            grid-template-columns: 1.1fr 1fr;   /* left side a little wider */
            min-height: 100vh;
        }

        /* ----- LEFT SIDE ----- */
        .left-side {
            position: relative;
            overflow: hidden;
            padding: 44px 56px;
            color: #ffffff;
            background: #081431;
            display: flex;
            flex-direction: column;
        }

        /* Big blurry colored circles that slowly float */
        .circle {
            position: absolute;
            border-radius: 50%;
            filter: blur(70px);
            animation: float-around 14s ease-in-out infinite alternate;
        }

        .circle-1 { width: 420px; height: 420px; background: #1D5BFF; left: -120px; bottom: -80px; opacity: 0.75; }
        .circle-2 { width: 340px; height: 340px; background: #22D3EE; right: -100px; top: -60px; opacity: 0.45; }
        .circle-3 { width: 260px; height: 260px; background: #7C5CFF; right: 12%; bottom: 8%; opacity: 0.35; }

        @keyframes float-around {
            to { transform: translate(40px, -30px) scale(1.1); }
        }

        /* Put the text above the circles */
        .left-content {
            position: relative;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .logo-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .logo-row b {
            display: block;
            font-size: 1.1rem;
        }

        .logo-row small {
            color: #9FB0D6;
        }

        .big-title {
            font-size: 3rem;
            font-weight: 800;
            line-height: 1.1;
            margin-top: auto;           /* pushes the title to the middle */
            margin-bottom: 1rem;
            max-width: 500px;
        }

        /* Colored second line of the title */
        .big-title span {
            color: #7FD8FF;
        }

        .left-text {
            color: #B9C5E3;
            font-size: 1.05rem;
            max-width: 440px;
            margin-bottom: 1.8rem;
        }

        /* Frosted glass boxes */
        .glass {
            background: rgba(255, 255, 255, 0.10);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 20px;
            backdrop-filter: blur(14px);
        }

        .status-box {
            padding: 1.1rem 1.25rem;
            max-width: 400px;
            margin-bottom: 1rem;
        }

        .status-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.7rem;
        }

        .status-tag {
            font-size: 0.72rem;
            font-weight: 700;
            background: rgba(34, 211, 238, 0.2);
            color: #7BE8FA;
            padding: 0.25rem 0.65rem;
            border-radius: 99px;
        }

        .progress-line {
            height: 7px;
            border-radius: 99px;
            background: rgba(255, 255, 255, 0.15);
        }

        .progress-line div {
            width: 72%;
            height: 100%;
            border-radius: 99px;
            background: linear-gradient(90deg, #1D5BFF, #22D3EE);
        }

        .status-box small {
            color: #9FB0D6;
        }

        /* The three small feature pills */
        .feature-row {
            display: flex;
            gap: 0.6rem;
            flex-wrap: wrap;
        }

        .feature {
            padding: 0.55rem 0.9rem;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 99px;
        }

        .feature i {
            color: #22D3EE;
            margin-right: 0.4rem;
        }

        /* ----- RIGHT SIDE ----- */
        .right-side {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 24px;
            background: #ffffff;
        }

        .login-box {
            width: 100%;
            max-width: 410px;
        }

        .login-box h2 {
            font-size: 2rem;
            margin-bottom: 0.35rem;
        }

        /* Input with an icon inside on the left */
        .input-wrap {
            position: relative;
        }

        .input-wrap .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #6B7A99;
        }

        .input-wrap .form-control {
            height: 52px;
            padding-left: 46px;
            background: #F5F8FE;
            border-color: transparent;
        }

        .input-wrap .form-control:focus {
            background: #ffffff;
            border-color: #1D5BFF;
        }

        /* Show / hide password button */
        .eye-button {
            position: absolute;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: none;
            color: #6B7A99;
            width: 42px;
            height: 42px;
        }

        .login-button {
            height: 54px;
            font-size: 1.02rem;
        }

        .secure-note {
            text-align: center;
            color: #6B7A99;
            font-size: 0.8rem;
            margin-top: 1.4rem;
        }

        /* ----- PHONE: stack the two halves ----- */
        @media (max-width: 900px) {
            .login-page {
                grid-template-columns: 1fr;
            }

            .left-side {
                padding: 28px 24px 34px;
            }

            .big-title {
                font-size: 2rem;
                margin-top: 1.5rem;
            }

            .status-box,
            .feature-row {
                display: none;       /* hide extras on small screens */
            }
        }
    </style>
</head>
<body>
    <div class="login-page">

        <!-- ================= LEFT SIDE ================= -->
        <div class="left-side">
            <div class="circle circle-1"></div>
            <div class="circle circle-2"></div>
            <div class="circle circle-3"></div>

            <div class="left-content">
                <div class="logo-row">
                    <div class="brand-mark"><i class="bi bi-broadcast-pin"></i></div>
                    <div>
                        <b>MNSM</b>
                        <small>Mobile Network Service Management</small>
                    </div>
                </div>

                <div class="big-title">
                    Network problem?<br>
                    <span>We'll get you back online.</span>
                </div>
                <div class="left-text">
                    Report an issue, follow its progress and check service status in one place.
                </div>

                <!-- Example card, just for decoration -->
                <div class="glass status-box">
                    <div class="status-top">
                        <b>Complaint in progress</b>
                        <span class="status-tag">Technician assigned</span>
                    </div>
                    <div class="progress-line"><div></div></div>
                    <small class="d-block mt-2">Estimated fix: within 2 hours</small>
                </div>

                <div class="feature-row">
                    <span class="glass feature"><i class="bi bi-lightning-charge-fill"></i>Report in a minute</span>
                    <span class="glass feature"><i class="bi bi-geo-alt-fill"></i>Live tracking</span>
                    <span class="glass feature"><i class="bi bi-bell-fill"></i>Instant alerts</span>
                </div>
            </div>
        </div>

        <!-- ================= RIGHT SIDE (the form) ================= -->
        <div class="right-side">
            <form class="login-box" method="POST" action="login.php">
                <h2>Welcome back</h2>
                <p class="page-sub">Sign in with your username or email.</p>

                <!-- Show the error message if there is one -->
                <?php if ($error != ''): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <!-- Security token (do not remove) -->
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">

                <label class="form-label">Username or Email</label>
                <div class="input-wrap mb-3">
                    <i class="bi bi-person input-icon"></i>
                    <input type="text" name="username" class="form-control"
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required autofocus>
                </div>

                <label class="form-label">Password</label>
                <div class="input-wrap mb-4">
                    <i class="bi bi-lock input-icon"></i>
                    <input type="password" name="password" id="password" class="form-control" required>
                    <button type="button" class="eye-button" onclick="showHidePassword()">
                        <i class="bi bi-eye" id="eye-icon"></i>
                    </button>
                </div>

                <button type="submit" class="btn btn-primary login-button w-100">Login</button>

                <p class="text-center mt-4 mb-0">
                    Don't have an account? <a href="register.php">Register here</a>
                </p>
                <div class="secure-note"><i class="bi bi-shield-lock-fill"></i> Your session is protected</div>
            </form>
        </div>

    </div>

    <script>
        // Show the password when the eye button is clicked, hide it when clicked again
        function showHidePassword() {
            var passwordBox = document.getElementById('password');
            var eyeIcon = document.getElementById('eye-icon');

            if (passwordBox.type === 'password') {
                passwordBox.type = 'text';
                eyeIcon.className = 'bi bi-eye-slash';
            } else {
                passwordBox.type = 'password';
                eyeIcon.className = 'bi bi-eye';
            }
        }
    </script>
</body>
</html>