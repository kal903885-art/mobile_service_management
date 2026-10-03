<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
$pdo = getDatabaseConnection();

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}
if (!isStaff()) {
    die("Access denied.");
}

// ---------- Get all sites, with their area name ----------
$sites = $pdo->query(
    "SELECT s.id, s.site_code, s.site_name, s.service_area_id, s.latitude, s.longitude,
            s.site_type, s.technology, s.status,
            a.area_name
     FROM sites s
     LEFT JOIN service_areas a ON s.service_area_id = a.id"
)->fetchAll(PDO::FETCH_ASSOC);

// ---------- Get active alarms, grouped by site ----------
$alarm_rows = $pdo->query(
    "SELECT site_id, alarm_title, severity, status, occurred_at
     FROM alarms
     WHERE status != 'Cleared'
     ORDER BY occurred_at DESC"
)->fetchAll(PDO::FETCH_ASSOC);

// Put the alarms into a list per site id
$alarms_by_site = [];
foreach ($alarm_rows as $row) {
    $site_id = $row['site_id'];
    if (!isset($alarms_by_site[$site_id])) {
        $alarms_by_site[$site_id] = [];
    }
    $alarms_by_site[$site_id][] = $row;
}

// ---------- Get open complaint counts, grouped by service area ----------
$complaint_rows = $pdo->query(
    "SELECT service_area_id, COUNT(*) AS total
     FROM customer_complaints
     WHERE status NOT IN ('Resolved','Closed')
     GROUP BY service_area_id"
)->fetchAll(PDO::FETCH_ASSOC);

// Put the counts into a list per area id
$complaints_by_area = [];
foreach ($complaint_rows as $row) {
    $complaints_by_area[$row['service_area_id']] = (int) $row['total'];
}

// ---------- Build the data the map (JavaScript) will use ----------
$map_data = [];
$skipped_sites = [];

foreach ($sites as $site) {

    // Skip sites that have no coordinates, we can't place them on the map
    if ($site['latitude'] === null || $site['longitude'] === null) {
        $skipped_sites[] = $site['site_name'];
        continue;
    }

    // Get this site's alarms (or an empty list if it has none)
    $site_alarms = [];
    if (isset($alarms_by_site[$site['id']])) {
        $site_alarms = $alarms_by_site[$site['id']];
    }

    // Get the area name, or "Unassigned" if there isn't one
    $area_name = $site['area_name'];
    if (!$area_name) {
        $area_name = 'Unassigned';
    }

    // Get the complaint count for this site's area (or 0 if none)
    $complaint_count = 0;
    if (isset($complaints_by_area[$site['service_area_id']])) {
        $complaint_count = $complaints_by_area[$site['service_area_id']];
    }

    $map_data[] = [
        'id' => $site['id'],
        'code' => $site['site_code'],
        'name' => $site['site_name'],
        'lat' => (float) $site['latitude'],
        'lng' => (float) $site['longitude'],
        'type' => $site['site_type'],
        'technology' => $site['technology'],
        'status' => $site['status'],
        'area_name' => $area_name,
        'alarm_count' => count($site_alarms),
        'alarms' => array_slice($site_alarms, 0, 5),
        'complaint_count' => $complaint_count
    ];
}

$pageTitle = 'Network Map';
require_once '../includes/staff_header.php';
?>

<div class="alert alert-info">
    <strong>Note:</strong> Complaint counts shown per site are based on complaints reported in that site's
    <strong>service area</strong>, since complaints are not pinned to an exact site location in this system.
</div>

<!-- Warning for sites with no coordinates -->
<?php if (count($skipped_sites) > 0): ?>
    <div class="alert alert-warning">
        The following sites are not shown on the map because they have no coordinates set:
        <?php echo htmlspecialchars(implode(', ', $skipped_sites)); ?>.
        Edit those sites to add latitude/longitude.
    </div>
<?php endif; ?>

<!-- Color legend -->
<div class="card shadow-sm mb-3">
    <div class="card-body d-flex gap-4 flex-wrap align-items-center">
        <strong>Legend:</strong>
        <span><span class="badge" style="background:#28a745;">&nbsp;&nbsp;</span> Online</span>
        <span><span class="badge" style="background:#fd7e14;">&nbsp;&nbsp;</span> Degraded</span>
        <span><span class="badge" style="background:#dc3545;">&nbsp;&nbsp;</span> Down</span>
        <span><span class="badge" style="background:#6c757d;">&nbsp;&nbsp;</span> Maintenance</span>
        <span><span class="badge" style="background:#0d6efd;">&nbsp;&nbsp;</span> Planned</span>
    </div>
</div>

<div id="networkMap" style="height: 600px; width: 100%; border-radius: 8px;"></div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
// The site data built by PHP above
var siteData = <?php echo json_encode($map_data); ?>;

// Which color to use for each status
var statusColors = {
    'Online': '#28a745',
    'Degraded': '#fd7e14',
    'Down': '#dc3545',
    'Maintenance': '#6c757d',
    'Planned': '#0d6efd'
};

// Create the map, centered on Ethiopia by default
var map = L.map('networkMap').setView([9.145, 40.4897], 6);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 19
}).addTo(map);

var markers = [];

// Add one marker per site
for (var i = 0; i < siteData.length; i++) {
    var site = siteData[i];

    var color = statusColors[site.status];
    if (!color) {
        color = '#6c757d';
    }

    var marker = L.circleMarker([site.lat, site.lng], {
        radius: 10,
        fillColor: color,
        color: '#333',
        weight: 1,
        fillOpacity: 0.9
    }).addTo(map);

    // Build the list of alarms shown in the popup
    var alarmListHtml = '<em>No active alarms</em>';
    if (site.alarms.length > 0) {
        alarmListHtml = '<ul class="mb-0 ps-3">';
        for (var j = 0; j < site.alarms.length; j++) {
            alarmListHtml += '<li>[' + site.alarms[j].severity + '] ' + site.alarms[j].alarm_title + '</li>';
        }
        alarmListHtml += '</ul>';
    }

    // Build the popup content
    var popupHtml =
        '<div style="min-width:220px;">' +
        '<h6 class="mb-1">' + site.name + '</h6>' +
        '<div class="text-muted small mb-2">' + site.code + ' &middot; ' + site.area_name + '</div>' +
        '<div><strong>Type:</strong> ' + (site.type || '-') + '</div>' +
        '<div><strong>Technology:</strong> ' + (site.technology || '-') + '</div>' +
        '<div><strong>Status:</strong> <span style="color:' + color + '; font-weight:bold;">' + site.status + '</span></div>' +
        '<div class="mt-2"><strong>Active Alarms (' + site.alarm_count + '):</strong></div>' +
        alarmListHtml +
        '<div class="mt-2"><strong>Open Complaints in Area:</strong> ' + site.complaint_count + '</div>' +
        '<div class="mt-2">' +
        '<a href="../sites/view.php?id=' + site.id + '" class="btn btn-sm btn-primary">View Site</a>' +
        '</div>' +
        '</div>';

    marker.bindPopup(popupHtml);
    markers.push(marker);
}

// Zoom the map to fit all the markers
if (markers.length > 0) {
    var group = new L.featureGroup(markers);
    map.fitBounds(group.getBounds().pad(0.2));
}
</script>

<?php require_once '../includes/staff_footer.php'; ?>