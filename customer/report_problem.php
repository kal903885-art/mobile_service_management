<?php
// User must be logged in and be a customer
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
if (!isCustomer()) {
    header('Location: ../staff/dashboard.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
$pdo = getDatabaseConnection();

$errors = [];
$new_complaint_number = null;

// Get the service areas for the dropdown
$service_areas = $pdo->query(
    "SELECT sa.id, sa.area_name, r.region_name, z.zone_name, w.woreda_name
     FROM service_areas sa
     JOIN regions r ON sa.region_id = r.id
     JOIN zones z ON sa.zone_id = z.id
     JOIN woredas w ON sa.woreda_id = w.id
     ORDER BY sa.area_name"
)->fetchAll();

// Lists for the dropdowns
$problem_categories = [
    'No Network', 'Weak Signal', 'Mobile Data Not Working', 'Slow Internet',
    'Cannot Make Calls', 'Cannot Receive Calls', 'SMS Not Working',
    '4G Not Available', '5G Not Available', 'Frequent Disconnection',
    'Network Congestion', 'Other'
];
$affected_services = ['Voice', 'SMS', 'Mobile Data', '2G', '3G', '4G', '5G'];
$technology_list = ['2G', '3G', '4G', '5G'];

// ---------- When the form is submitted ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Get the values from the form
    $service_area_id = (int) ($_POST['service_area_id'] ?? 0);
    $problem_category = trim($_POST['problem_category'] ?? '');
    $affected_service = trim($_POST['affected_service'] ?? '');
    $technology = trim($_POST['technology'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $started_date = $_POST['problem_started_date'] ?? '';
    $started_time = $_POST['problem_started_time'] ?? '';
    $contact_phone = trim($_POST['contact_phone'] ?? '');

    // Date and time are optional, so use NULL if they are empty
    if ($started_date == '') {
        $started_date = null;
    }
    if ($started_time == '') {
        $started_time = null;
    }

    // Check the required fields
    if ($service_area_id <= 0) {
        $errors[] = "Please select the affected area.";
    }
    if ($problem_category === '') {
        $errors[] = "Please select a problem category.";
    }
    if ($affected_service === '') {
        $errors[] = "Please select the affected service.";
    }
    if ($description === '') {
        $errors[] = "Please describe the problem.";
    }
    if ($contact_phone === '') {
        $errors[] = "Contact phone is required.";
    }

    // If there are no errors, save the complaint
    if (count($errors) == 0) {
        try {
            // Start a transaction: either all the saves work, or none of them
            $pdo->beginTransaction();

            $complaint_number = generateComplaintNumber($pdo);

            // Simple priority rule: "No Network" is High, everything else is Medium
            if ($problem_category === 'No Network') {
                $priority = 'High';
            } else {
                $priority = 'Medium';
            }

            // 1. Save the complaint
            $stmt = $pdo->prepare(
                "INSERT INTO customer_complaints
                 (complaint_number, customer_id, service_area_id, problem_category, affected_service,
                  technology, description, problem_started_date, problem_started_time, contact_phone,
                  priority, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Submitted')"
            );
            $stmt->execute([
                $complaint_number, $_SESSION['user_id'], $service_area_id, $problem_category, $affected_service,
                $technology, $description, $started_date, $started_time,
                $contact_phone, $priority
            ]);
            $complaint_id = $pdo->lastInsertId();

            // 2. Save the first status in the complaint history
            $stmt = $pdo->prepare(
                "INSERT INTO complaint_updates (complaint_id, updated_by, status, message)
                 VALUES (?, ?, 'Submitted', 'Complaint submitted by customer.')"
            );
            $stmt->execute([$complaint_id, $_SESSION['user_id']]);

            // 3. Notify the customer and the customer service agents
            notifyUser($pdo, $_SESSION['user_id'], 'Complaint Received',
                "Your complaint $complaint_number has been received and is being reviewed.");
            notifyRole($pdo, 'Customer Service Agent', 'New Complaint',
                "New complaint $complaint_number submitted ($problem_category).");

            // Save everything
            $pdo->commit();
            $new_complaint_number = $complaint_number;

        } catch (Exception $e) {
            // Something failed, undo all the saves
            $pdo->rollBack();
            $errors[] = "Something went wrong while submitting your complaint. Please try again.";
        }
    }
}

$pageTitle = 'Report a Problem';
require_once __DIR__ . '/../includes/customer_header.php';
?>

<?php if ($new_complaint_number): ?>

    <!-- ============ Success message after submitting ============ -->
    <div class="card" style="max-width: 640px; margin: 2rem auto;">
        <div class="card-body text-center py-5">
            <div class="success-circle"><i class="bi bi-check-lg"></i></div>
            <h3 class="mt-4">Complaint Submitted</h3>
            <p class="text-muted">Thank you for reporting the problem. Keep this number to track your complaint:</p>

            <div class="my-3">
                <span class="complaint-number"><?php echo htmlspecialchars($new_complaint_number); ?></span>
            </div>
            <span class="badge bg-secondary">Status: Submitted</span>

            <div class="mt-4">
                <a href="track_complaint.php?complaint_number=<?php echo urlencode($new_complaint_number); ?>" class="btn btn-primary me-2">
                    <i class="bi bi-search"></i> Track Complaint
                </a>
                <a href="dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
            </div>
        </div>
    </div>

<?php else: ?>

    <h1 class="page-title">Report a Network Problem</h1>
    <p class="page-sub">Tell us what is wrong and where. We will assign a technician as soon as possible.</p>

    <!-- Show errors -->
    <?php if (count($errors) > 0): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card" style="max-width: 900px;">
        <div class="card-body p-4">
            <form method="POST">

                <!-- Part 1: Where -->
                <div class="section-title"><i class="bi bi-geo-alt-fill"></i> Where is the problem?</div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Affected Area *</label>
                        <select name="service_area_id" class="form-select" required>
                            <option value="">-- Select Area --</option>
                            <?php foreach ($service_areas as $area): ?>
                                <?php
                                // Build the text shown in the dropdown
                                $area_text = $area['area_name'] . ' (' . $area['woreda_name'] . ', ' . $area['zone_name'] . ', ' . $area['region_name'] . ')';
                                ?>
                                <option value="<?php echo $area['id']; ?>"><?php echo htmlspecialchars($area_text); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="section-line"></div>

                <!-- Part 2: What -->
                <div class="section-title"><i class="bi bi-reception-1"></i> What is wrong?</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Problem Category *</label>
                        <select name="problem_category" class="form-select" required>
                            <option value="">-- Select Category --</option>
                            <?php foreach ($problem_categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Affected Service *</label>
                        <select name="affected_service" class="form-select" required>
                            <option value="">-- Select --</option>
                            <?php foreach ($affected_services as $service): ?>
                                <option value="<?php echo $service; ?>"><?php echo $service; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Technology</label>
                        <select name="technology" class="form-select">
                            <option value="">-- Optional --</option>
                            <?php foreach ($technology_list as $tech): ?>
                                <option value="<?php echo $tech; ?>"><?php echo $tech; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Describe the Problem *</label>
                        <textarea name="description" class="form-control" rows="4" required
                                  placeholder="e.g. I cannot make calls since this morning..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="section-line"></div>

                <!-- Part 3: When and contact -->
                <div class="section-title"><i class="bi bi-clock-fill"></i> When did it start, and how can we reach you?</div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Problem Started Date</label>
                        <input type="date" name="problem_started_date" class="form-control">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Problem Started Time</label>
                        <input type="time" name="problem_started_time" class="form-control">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Contact Phone *</label>
                        <input type="text" name="contact_phone" class="form-control" required
                               value="<?php echo htmlspecialchars($_POST['contact_phone'] ?? ''); ?>">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary mt-4 w-100 py-3">
                    <i class="bi bi-send-fill"></i> Submit Complaint
                </button>
            </form>
        </div>
    </div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>