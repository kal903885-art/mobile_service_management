<?php
// User must be logged in
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

// Customers are not allowed to see the charts
if (isCustomer()) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../config/database.php';
$pdo = getDatabaseConnection();

// This page sends data (JSON) for the charts, not a normal web page
header('Content-Type: application/json');

// 1. Complaints by category
$by_category = $pdo->query(
    "SELECT problem_category, COUNT(*) AS cnt
     FROM customer_complaints
     GROUP BY problem_category
     ORDER BY cnt DESC"
)->fetchAll();

// 2. Complaints by region
$by_region = $pdo->query(
    "SELECT r.region_name, COUNT(*) AS cnt
     FROM customer_complaints cc
     JOIN service_areas sa ON cc.service_area_id = sa.id
     JOIN regions r ON sa.region_id = r.id
     GROUP BY r.region_name
     ORDER BY cnt DESC"
)->fetchAll();

// 3. Complaints per day (last 14 days, today included)
$per_day_rows = $pdo->query(
    "SELECT DATE(created_at) AS day, COUNT(*) AS cnt
     FROM customer_complaints
     WHERE created_at >= (CURDATE() - INTERVAL 13 DAY)
     GROUP BY DATE(created_at)
     ORDER BY day ASC"
)->fetchAll();

// The database only returns days that HAVE complaints.
// So first we remember the counts by date...
$counts_by_day = [];
foreach ($per_day_rows as $row) {
    $counts_by_day[$row['day']] = (int) $row['cnt'];
}

// ...then we build a list of all 14 days, using 0 for the empty days
$per_day = [];
for ($i = 13; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));

    $count = 0;
    if (isset($counts_by_day[$date])) {
        $count = $counts_by_day[$date];
    }

    $per_day[] = [
        'day'   => $date,
        'label' => date('d M', strtotime($date)),   // for example "03 Oct"
        'cnt'   => $count
    ];
}

// 4. Complaints by status
$by_status = $pdo->query(
    "SELECT status, COUNT(*) AS cnt
     FROM customer_complaints
     GROUP BY status"
)->fetchAll();

// Put everything together and send it
// (the keys keep the same names, so the chart JavaScript still works)
$data = [
    'byCategory' => $by_category,
    'byRegion'   => $by_region,
    'perDay'     => $per_day,
    'byStatus'   => $by_status
];

echo json_encode($data);