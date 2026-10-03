<?php
// Included at the top of every staff-facing page, after requireLogin()/requireRole() checks.
// The staff pages use the SAME look as the customer pages (assets/css/theme.css).

// Build the page title
$page_title_text = 'MNSM Staff';
if (isset($pageTitle)) {
    $page_title_text = htmlspecialchars($pageTitle) . ' - ' . $page_title_text;
}

// Count how many notifications this user hasn't read yet
$unread_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$unread_stmt->execute([$_SESSION['user_id']]);
$unread_count = $unread_stmt->fetchColumn();

// Get the 5 most recent notifications
$recent_stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$recent_stmt->execute([$_SESSION['user_id']]);
$recent_notifications = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if this user has a profile photo saved
$photo_stmt = $pdo->prepare("SELECT profile_photo FROM users WHERE id = ?");
$photo_stmt->execute([$_SESSION['user_id']]);
$profile_photo = $photo_stmt->fetchColumn();

$has_header_photo = false;
$header_photo_path = __DIR__ . '/../assets/uploads/profiles/' . $profile_photo;
if ($profile_photo && file_exists($header_photo_path)) {
    $has_header_photo = true;
}

// First letter of the name, used when there is no photo
$initial = strtoupper(substr($_SESSION['full_name'], 0, 1));

// Title shown at the left of the top bar
$header_title = 'Dashboard';
if (isset($pageTitle)) {
    $header_title = htmlspecialchars($pageTitle);
}

// Work out which sidebar link is the "current" page, so we can highlight it
$current_page = basename($_SERVER['PHP_SELF']);
$current_folder = basename(dirname($_SERVER['PHP_SELF']));
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
            document.getElementById('staffSidebar').classList.toggle('open');
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
    <nav class="customer-sidebar" id="staffSidebar">
        <div class="brand">
            <div class="brand-mark"><i class="bi bi-broadcast-pin"></i></div>
            <div class="brand-text">
                MNSM
                <small>Staff Console</small>
            </div>
        </div>

        <div class="nav-links">
            <a class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>"
               href="/mobile-network-service-management/staff/dashboard.php">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>

            <div class="nav-section-label">Customer Service</div>
            <a class="nav-link <?php echo $current_folder === 'complaints' ? 'active' : ''; ?>"
               href="/mobile-network-service-management/complaints/index.php">
                <i class="bi bi-chat-left-text"></i> Complaints
            </a>

            <div class="nav-section-label">Network</div>
            <a class="nav-link <?php echo $current_folder === 'sites' ? 'active' : ''; ?>"
               href="/mobile-network-service-management/sites/index.php">
                <i class="bi bi-broadcast"></i> Sites
            </a>
            <a class="nav-link <?php echo $current_folder === 'map' ? 'active' : ''; ?>"
               href="/mobile-network-service-management/map/index.php">
                <i class="bi bi-geo-alt"></i> Network Map
            </a>

            <div class="nav-section-label">Alarms</div>
            <a class="nav-link <?php echo $current_folder === 'alarms' ? 'active' : ''; ?>"
               href="/mobile-network-service-management/alarms/index.php">
                <i class="bi bi-bell"></i> Active Alarms
            </a>

            <div class="nav-section-label">Incidents</div>
            <a class="nav-link <?php echo $current_folder === 'incidents' ? 'active' : ''; ?>"
               href="/mobile-network-service-management/incidents/index.php">
                <i class="bi bi-exclamation-diamond"></i> Incidents
            </a>

            <div class="nav-section-label">Reports</div>
            <a class="nav-link <?php echo $current_folder === 'reports' ? 'active' : ''; ?>"
               href="/mobile-network-service-management/reports/complaints.php">
                <i class="bi bi-file-earmark-bar-graph"></i> Reports
            </a>

            <?php if ($_SESSION['role_name'] === 'Administrator'): ?>
                <div class="nav-section-label">Administration</div>
                <a class="nav-link <?php echo $current_folder === 'admin' ? 'active' : ''; ?>"
                   href="/mobile-network-service-management/admin/users.php">
                    <i class="bi bi-people"></i> Users
                </a>
            <?php endif; ?>

            <div class="nav-section-label">Account</div>
            <a class="nav-link <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>"
               href="/mobile-network-service-management/staff/profile.php">
                <i class="bi bi-person-circle"></i> My Profile
            </a>
        </div>

        <div class="sidebar-footer">
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

            <!-- Page title and your role -->
            <div class="topbar-greet">
                <b><?php echo $header_title; ?></b>
                <span><?php echo htmlspecialchars($_SESSION['role_name']); ?> &middot; <?php echo date('l, j F Y'); ?></span>
            </div>

            <div class="top-right">

                <!-- Light / dark mode button -->
                <button class="icon-btn" type="button" onclick="toggleTheme()" title="Light / dark mode">
                    <i class="bi bi-moon-stars-fill theme-moon"></i>
                    <i class="bi bi-sun-fill theme-sun"></i>
                </button>

                <!-- Notifications bell -->
                <div class="dropdown">
                    <button class="notif-bell-btn" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-bell-fill"></i>
                        <?php if ($unread_count > 0): ?>
                            <span class="badge rounded-pill bg-danger"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-2" style="width: 320px;">
                        <?php if (count($recent_notifications) == 0): ?>
                            <div class="text-muted small p-2">No notifications yet.</div>
                        <?php else: ?>
                            <?php foreach ($recent_notifications as $notification): ?>
                                <?php
                                $item_class = '';
                                if (!$notification['is_read']) {
                                    $item_class = 'fw-bold';
                                }
                                ?>
                                <div class="dropdown-item-text small border-bottom py-2 <?php echo $item_class; ?>">
                                    <?php echo htmlspecialchars($notification['title']); ?><br>
                                    <span class="text-muted"><?php echo htmlspecialchars($notification['message']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <a href="/mobile-network-service-management/notifications/index.php" class="dropdown-item text-center text-primary small mt-1">View All</a>
                    </div>
                </div>

                <!-- Your photo (or first letter), links to your profile -->
                <a href="/mobile-network-service-management/staff/profile.php" title="My Profile">
                    <?php if ($has_header_photo): ?>
                        <img class="avatar" src="/mobile-network-service-management/assets/uploads/profiles/<?php echo htmlspecialchars($profile_photo); ?>" alt="">
                    <?php else: ?>
                        <div class="avatar"><?php echo htmlspecialchars($initial); ?></div>
                    <?php endif; ?>
                </a>
            </div>
        </div>

        <!-- Page content starts here (your staff_footer.php closes these divs) -->
        <div class="container-fluid py-4 px-4">