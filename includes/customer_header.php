<?php
// Included at the top of every customer-facing page, after requireLogin() checks.
// Expects $_SESSION to already be populated (auth.php must be included first)
// and $pdo to exist.

// ---------- Notifications ----------

// Count how many notifications this user hasn't read yet
$unread_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$unread_stmt->execute([$_SESSION['user_id']]);
$unread_count = $unread_stmt->fetchColumn();

// Get the 5 most recent notifications
$recent_stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$recent_stmt->execute([$_SESSION['user_id']]);
$recent_notifications = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------- Profile photo ----------

// $photo_url stays empty if the user has no photo
$photo_url = '';

try {
    $photo_stmt = $pdo->prepare("SELECT profile_photo FROM users WHERE id = ?");
    $photo_stmt->execute([$_SESSION['user_id']]);
    $photo_file = $photo_stmt->fetchColumn();

    if ($photo_file) {
        $photo_url = '/mobile-network-service-management/assets/uploads/profiles/' . rawurlencode($photo_file);
    }
} catch (PDOException $e) {
    // The profile_photo column does not exist yet, so just show no photo
    $photo_url = '';
}

// First letter of the name, used when there is no photo
$initial = strtoupper(substr($_SESSION['full_name'], 0, 1));

// ---------- Greeting in the top bar ----------

$hour = (int) date('G');   // hour of the day, 0 to 23

if ($hour < 12) {
    $greeting = 'Good morning';
} elseif ($hour < 18) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}

// Only the first name, for example "Kalkidan"
$name_parts = explode(' ', trim($_SESSION['full_name']));
$first_name = $name_parts[0];

// ---------- Page title ----------

$page_title_text = 'Mobile Network Service Management';
if (isset($pageTitle)) {
    $page_title_text = htmlspecialchars($pageTitle) . ' - ' . $page_title_text;
}

// Work out which sidebar link is the "current" page, so we can highlight it
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title_text; ?></title>

    <script>
        // Use the saved light/dark choice before the page shows (avoids a flash)
        var savedTheme = localStorage.getItem('mnsm-theme');
        if (savedTheme === 'dark') {
            document.documentElement.setAttribute('data-bs-theme', 'dark');
        }

        // Called by the moon/sun button in the top bar
        function toggleTheme() {
            var html = document.documentElement;
            if (html.getAttribute('data-bs-theme') === 'dark') {
                html.setAttribute('data-bs-theme', 'light');
                localStorage.setItem('mnsm-theme', 'light');
            } else {
                html.setAttribute('data-bs-theme', 'dark');
                localStorage.setItem('mnsm-theme', 'dark');
            }
        }

        // Called by the menu button on phones: open or close the sidebar
        function toggleSidebar() {
            document.getElementById('customerSidebar').classList.toggle('open');
            document.getElementById('sidebarBackdrop').classList.toggle('show');
        }
    </script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="/mobile-network-service-management/assets/css/style.css" rel="stylesheet">
    <link href="/mobile-network-service-management/assets/css/theme.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/theme.css'); ?>" rel="stylesheet">
</head>
<body>

<!-- Dark layer behind the sidebar on phones -->
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar()"></div>

<div class="d-flex">

    <!-- ===================== SIDEBAR ===================== -->
    <nav class="customer-sidebar" id="customerSidebar">
        <div class="brand">
            <div class="brand-mark"><i class="bi bi-broadcast-pin"></i></div>
            <div class="brand-text">
                MNSM
                <small>Service Management</small>
            </div>
        </div>

        <div class="nav-links">
            <div class="nav-section-label">Menu</div>

            <a class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>"
               href="/mobile-network-service-management/customer/dashboard.php">
                <i class="bi bi-house-door-fill"></i> Home
            </a>

            <a class="nav-link <?php echo $current_page === 'report_problem.php' ? 'active' : ''; ?>"
               href="/mobile-network-service-management/customer/report_problem.php">
                <i class="bi bi-exclamation-triangle-fill"></i> Report Problem
            </a>

            <a class="nav-link <?php echo $current_page === 'track_complaint.php' ? 'active' : ''; ?>"
               href="/mobile-network-service-management/customer/track_complaint.php">
                <i class="bi bi-search"></i> Track Complaint
            </a>

            <a class="nav-link <?php echo $current_page === 'complaints.php' ? 'active' : ''; ?>"
               href="/mobile-network-service-management/customer/complaints.php">
                <i class="bi bi-list-check"></i> My Complaints
            </a>

            <a class="nav-link <?php echo $current_page === 'service_status.php' ? 'active' : ''; ?>"
               href="/mobile-network-service-management/customer/service_status.php">
                <i class="bi bi-reception-4"></i> Service Status
            </a>

            <div class="nav-section-label">Account</div>

            <a class="nav-link <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>"
               href="/mobile-network-service-management/customer/profile.php">
                <i class="bi bi-person-circle"></i> My Profile
            </a>
        </div>

        <div class="sidebar-footer">
            <div class="user-chip">
                <?php if ($photo_url != ''): ?>
                    <img class="avatar" src="<?php echo $photo_url; ?>" alt="">
                <?php else: ?>
                    <div class="avatar"><?php echo htmlspecialchars($initial); ?></div>
                <?php endif; ?>
                <div>
                    <div class="name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                    <div class="role">Customer</div>
                </div>
            </div>
            <a class="logout-link" href="/mobile-network-service-management/logout.php">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </nav>

    <!-- ===================== MAIN CONTENT ===================== -->
    <div class="main-content">

        <!-- Top bar -->
        <div class="content-topbar">

            <!-- Menu button (only visible on phones) -->
            <button class="icon-btn menu-btn" type="button" onclick="toggleSidebar()">
                <i class="bi bi-list fs-5"></i>
            </button>

            <!-- Greeting -->
            <div class="topbar-greet">
                <b><?php echo $greeting . ', ' . htmlspecialchars($first_name); ?></b>
                <span><?php echo date('l, j F Y'); ?></span>
            </div>

            <div class="top-right">

                <!-- Light / dark mode button -->
                <button class="icon-btn" type="button" onclick="toggleTheme()" title="Light / dark mode">
                    <i class="bi bi-moon-stars-fill theme-moon"></i>
                    <i class="bi bi-sun-fill theme-sun"></i>
                </button>

                <!-- Notification bell -->
                <div class="dropdown">
                    <button class="notif-bell-btn" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-bell-fill"></i>
                        <?php if ($unread_count > 0): ?>
                            <span class="badge rounded-pill bg-danger"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end p-2" style="width: 300px;">
                        <?php if (count($recent_notifications) == 0): ?>
                            <li class="dropdown-item-text small text-muted">No notifications yet.</li>
                        <?php else: ?>
                            <?php foreach ($recent_notifications as $notification): ?>
                                <?php
                                $item_class = '';
                                if (!$notification['is_read']) {
                                    $item_class = 'fw-bold';
                                }
                                ?>
                                <li class="dropdown-item-text small border-bottom py-2 <?php echo $item_class; ?>">
                                    <?php echo htmlspecialchars($notification['title']); ?><br>
                                    <span class="text-muted"><?php echo htmlspecialchars($notification['message']); ?></span>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <li><a class="dropdown-item text-center text-primary small" href="/mobile-network-service-management/notifications/index.php">View All</a></li>
                    </ul>
                </div>

                <!-- Your photo (or first letter) -->
                <?php if ($photo_url != ''): ?>
                    <img class="avatar" src="<?php echo $photo_url; ?>" alt="">
                <?php else: ?>
                    <div class="avatar"><?php echo htmlspecialchars($initial); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Page content starts here (your footer.php closes these divs) -->
        <div class="container-fluid py-4 px-4">