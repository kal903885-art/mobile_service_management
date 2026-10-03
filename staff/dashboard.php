<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
if (isCustomer()) {
    header('Location: ../customer/dashboard.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
$pdo = getDatabaseConnection();

// ---------- Totals for the count boxes ----------
$total_complaints = (int) $pdo->query("SELECT COUNT(*) FROM customer_complaints")->fetchColumn();

$open_complaints = (int) $pdo->query(
    "SELECT COUNT(*) FROM customer_complaints WHERE status IN ('Submitted','Received','Under Verification','Assigned','Under Investigation','Escalated')"
)->fetchColumn();

$critical_complaints = (int) $pdo->query(
    "SELECT COUNT(*) FROM customer_complaints WHERE priority = 'Critical' AND status NOT IN ('Resolved','Closed','Rejected')"
)->fetchColumn();

$resolved_today = (int) $pdo->query(
    "SELECT COUNT(*) FROM customer_complaints WHERE status IN ('Resolved','Closed') AND DATE(updated_at) = CURDATE()"
)->fetchColumn();

// These will be real once Phase 9 (sites) and Phase 11 (alarms) exist — safe to query now, will just show 0
$down_sites = (int) $pdo->query("SELECT COUNT(*) FROM sites WHERE status = 'Down'")->fetchColumn();
$active_alarms = (int) $pdo->query("SELECT COUNT(*) FROM alarms WHERE status = 'Active'")->fetchColumn();

// Work out what percentage of complaints have been resolved
$resolution_rate = 0;
if ($total_complaints > 0) {
    $resolved_count = (int) $pdo->query("SELECT COUNT(*) FROM customer_complaints WHERE status IN ('Resolved','Closed')")->fetchColumn();
    $resolution_rate = round(($resolved_count / $total_complaints) * 100, 1);
}

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/staff_header.php';
?>

<!-- ---------- KPI cards (the styles are in assets/css/theme.css, section 17) ---------- -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="kpi-card">
            <div class="kpi-icon bg-blue"><i class="bi bi-chat-left-text-fill"></i></div>
            <div>
                <div class="kpi-value"><?php echo $total_complaints; ?></div>
                <div class="kpi-label">Total Complaints</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="kpi-card">
            <div class="kpi-icon bg-amber"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="kpi-value"><?php echo $open_complaints; ?></div>
                <div class="kpi-label">Open Complaints</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="kpi-card">
            <div class="kpi-icon bg-red"><i class="bi bi-exclamation-octagon-fill"></i></div>
            <div>
                <div class="kpi-value"><?php echo $critical_complaints; ?></div>
                <div class="kpi-label">Critical (Unresolved)</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="kpi-card">
            <div class="kpi-icon bg-green"><i class="bi bi-check-circle-fill"></i></div>
            <div>
                <div class="kpi-value"><?php echo $resolved_today; ?></div>
                <div class="kpi-label">Resolved Today</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4 col-6">
        <div class="kpi-card">
            <div class="kpi-icon bg-slate"><i class="bi bi-broadcast"></i></div>
            <div>
                <div class="kpi-value"><?php echo $down_sites; ?></div>
                <div class="kpi-label">Down Sites</div>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-6">
        <div class="kpi-card">
            <div class="kpi-icon bg-slate"><i class="bi bi-bell-fill"></i></div>
            <div>
                <div class="kpi-value"><?php echo $active_alarms; ?></div>
                <div class="kpi-label">Active Alarms</div>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-12">
        <div class="kpi-card">
            <div class="kpi-icon bg-teal"><i class="bi bi-graph-up-arrow"></i></div>
            <div>
                <div class="kpi-value"><?php echo $resolution_rate; ?>%</div>
                <div class="kpi-label">Complaint Resolution Rate</div>
            </div>
        </div>
    </div>
</div>

<!-- ---------- Charts ---------- -->
<div class="row g-3">
    <div class="col-lg-6">
        <div class="chart-card">
            <div class="chart-card-header"><i class="bi bi-bar-chart-fill"></i> Complaints by Category</div>
            <div class="chart-card-body">
                <div class="chart-box">
                    <canvas id="chartCategory"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="chart-card">
            <div class="chart-card-header"><i class="bi bi-pie-chart-fill"></i> Complaints by Region</div>
            <div class="chart-card-body">
                <div class="chart-box">
                    <canvas id="chartRegion"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="chart-card">
            <div class="chart-card-header"><i class="bi bi-graph-up"></i> Complaints per Day (Last 14 Days)</div>
            <div class="chart-card-body">
                <div class="chart-box chart-box-tall">
                    <canvas id="chartPerDay"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="chart-card">
            <div class="chart-card-header"><i class="bi bi-list-task"></i> Complaints by Status</div>
            <div class="chart-card-body">
                <div class="chart-box chart-box-tall">
                    <canvas id="chartStatus"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
// Pull out just one field from a list of objects, e.g. get every "cnt" value
function pluck(list, field) {
    var values = [];
    for (var i = 0; i < list.length; i++) {
        values.push(list[i][field]);
    }
    return values;
}

// Read a color from theme.css (for example --muted). This is how the
// charts match light mode and dark mode.
function themeColor(name) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
}

// ----- Default look for ALL charts -----
Chart.defaults.font.family = "'Manrope', sans-serif";
Chart.defaults.font.weight = 600;
Chart.defaults.color = themeColor('--muted');          // text color
Chart.defaults.borderColor = themeColor('--line');     // grid line color
Chart.defaults.plugins.tooltip.backgroundColor = '#0B1B3A';
Chart.defaults.plugins.tooltip.padding = 12;
Chart.defaults.plugins.tooltip.cornerRadius = 10;

// The colors we use
var blue = '#1D5BFF';
var palette = ['#1D5BFF', '#22D3EE', '#7C5CFF', '#F59E0B', '#12A36B', '#E5484D'];

// One color per complaint status (so "Resolved" is always green, and so on)
var statusColors = {
    'Submitted': '#22D3EE',
    'Received': '#1D5BFF',
    'Under Verification': '#7C5CFF',
    'Assigned': '#A78BFA',
    'Under Investigation': '#F59E0B',
    'Escalated': '#E5484D',
    'Resolved': '#12A36B',
    'Closed': '#64748B',
    'Rejected': '#F43F5E'
};

// Shared options so every chart fills its fixed-height box instead of growing forever
var baseOptions = {
    responsive: true,
    maintainAspectRatio: false
};

fetch('/mobile-network-service-management/api/dashboard_charts.php')
    .then(function (response) {
        return response.json();
    })
    .then(function (data) {

        // ---------- Complaints by category (horizontal bars) ----------
        new Chart(document.getElementById('chartCategory'), {
            type: 'bar',
            data: {
                labels: pluck(data.byCategory, 'problem_category'),
                datasets: [{
                    label: 'Complaints',
                    data: pluck(data.byCategory, 'cnt'),
                    backgroundColor: blue,
                    borderRadius: 8,
                    barThickness: 18
                }]
            },
            options: Object.assign({}, baseOptions, {
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0 } },   // whole numbers only
                    y: { grid: { display: false } }
                }
            })
        });

        // ---------- Complaints by region (ring) ----------
        new Chart(document.getElementById('chartRegion'), {
            type: 'doughnut',
            data: {
                labels: pluck(data.byRegion, 'region_name'),
                datasets: [{
                    data: pluck(data.byRegion, 'cnt'),
                    backgroundColor: palette,
                    borderWidth: 0
                }]
            },
            options: Object.assign({}, baseOptions, {
                cutout: '68%',
                plugins: { legend: { position: 'bottom', labels: { usePointStyle: true } } }
            })
        });

        // ---------- Complaints per day (line with soft blue fill) ----------
        var perDayCanvas = document.getElementById('chartPerDay');

        // A fade from blue (top) to see-through (bottom) for under the line
        var fill = perDayCanvas.getContext('2d').createLinearGradient(0, 0, 0, 300);
        fill.addColorStop(0, 'rgba(29, 91, 255, 0.30)');
        fill.addColorStop(1, 'rgba(29, 91, 255, 0)');

        new Chart(perDayCanvas, {
            type: 'line',
            data: {
                labels: pluck(data.perDay, 'label'),
                datasets: [{
                    label: 'Complaints',
                    data: pluck(data.perDay, 'cnt'),
                    borderColor: blue,
                    backgroundColor: fill,
                    fill: true,
                    tension: 0.35,
                    borderWidth: 3,
                    pointRadius: 4,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: blue,
                    pointBorderWidth: 2
                }]
            },
            options: Object.assign({}, baseOptions, {
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    // Start at 0, whole numbers only, and always show at least up to 4
                    y: { beginAtZero: true, suggestedMax: 4, ticks: { precision: 0 } }
                }
            })
        });

        // ---------- Complaints by status (ring, one color per status) ----------
        var statusNames = pluck(data.byStatus, 'status');
        var statusBackgrounds = [];
        for (var i = 0; i < statusNames.length; i++) {
            var color = statusColors[statusNames[i]];
            if (!color) {
                color = '#94A3B8';     // grey for any status we did not list
            }
            statusBackgrounds.push(color);
        }

        new Chart(document.getElementById('chartStatus'), {
            type: 'doughnut',
            data: {
                labels: statusNames,
                datasets: [{
                    data: pluck(data.byStatus, 'cnt'),
                    backgroundColor: statusBackgrounds,
                    borderWidth: 0
                }]
            },
            options: Object.assign({}, baseOptions, {
                cutout: '68%',
                plugins: { legend: { position: 'bottom', labels: { usePointStyle: true } } }
            })
        });
    })
    .catch(function (error) {
        console.error('Chart data failed to load:', error);
    });
</script>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>