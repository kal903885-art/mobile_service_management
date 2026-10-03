<?php
// User must be logged in and be a customer
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
if (!isCustomer()) {
    header('Location: ../staff/dashboard.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
$pdo = getDatabaseConnection();

$customer_id = $_SESSION['user_id'];
$message = '';
$errors = [];

// List of contact methods for the dropdown
$contact_methods = ['Email', 'SMS', 'Phone'];

// Where uploaded photos are stored on disk, and the URL path used to show them
$upload_dir = __DIR__ . '/../assets/uploads/profiles/';
$upload_url = '/mobile-network-service-management/assets/uploads/profiles/';

// Make sure the upload folder exists
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// ---------- When the form is submitted ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Get the values from the form
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $preferred_contact = trim($_POST['preferred_contact_method'] ?? 'Email');

    // Name is required
    if ($full_name === '') {
        $errors[] = "Full name is required.";
    }

    // ---------- Handle the photo upload, if one was chosen ----------
    $new_photo_filename = null;

    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {

        $file = $_FILES['profile_photo'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "There was a problem uploading your photo. Please try again.";
        } else {

            // Only allow actual image files, and keep them to a sane size
            $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
            $max_size_bytes = 3 * 1024 * 1024; // 3 MB

            $file_info = getimagesize($file['tmp_name']);
            $mime_type = $file_info['mime'] ?? '';

            if (!$file_info || !in_array($mime_type, $allowed_types, true)) {
                $errors[] = "Please upload a JPG, PNG, or WEBP image.";
            } elseif ($file['size'] > $max_size_bytes) {
                $errors[] = "Photo must be smaller than 3 MB.";
            } else {
                // Build a unique filename so photos never overwrite each other
                $extension_map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                $extension = $extension_map[$mime_type];
                $new_photo_filename = 'user' . $customer_id . '_' . time() . '.' . $extension;

                $destination = $upload_dir . $new_photo_filename;

                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    $errors[] = "Could not save the uploaded photo. Please try again.";
                    $new_photo_filename = null;
                }
            }
        }
    }

    // If there are no errors, save the profile
    if (count($errors) == 0) {

        if ($new_photo_filename) {
            // Update the photo along with the other details
            $stmt = $pdo->prepare(
                "UPDATE users SET full_name = ?, phone = ?, preferred_contact_method = ?, profile_photo = ? WHERE id = ?"
            );
            $stmt->execute([$full_name, $phone, $preferred_contact, $new_photo_filename, $customer_id]);
        } else {
            // No new photo, just update the other details
            $stmt = $pdo->prepare(
                "UPDATE users SET full_name = ?, phone = ?, preferred_contact_method = ? WHERE id = ?"
            );
            $stmt->execute([$full_name, $phone, $preferred_contact, $customer_id]);
        }

        // Update the name saved in the session too
        $_SESSION['full_name'] = $full_name;

        $message = 'Profile updated successfully.';
    }
}

// Get the customer's current details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

// Work out what photo to show: the saved one, or nothing (we'll show initials instead)
$has_photo = false;
if (!empty($customer['profile_photo']) && file_exists($upload_dir . $customer['profile_photo'])) {
    $has_photo = true;
}
$initial = strtoupper(substr($customer['full_name'], 0, 1));

$pageTitle = 'My Profile';
require_once __DIR__ . '/../includes/customer_header.php';
?>

<style>
    .profile-card {
        border: none;
        border-radius: 16px;
        box-shadow: 0 8px 24px rgba(20, 50, 120, 0.08);
        overflow: hidden;
    }

    .profile-card .card-header-band {
        background: linear-gradient(135deg, #1558e8 0%, #2f7af0 100%);
        height: 110px;
        position: relative;
    }

    .profile-photo-wrap {
        position: relative;
        width: 120px;
        height: 120px;
        margin: -60px auto 0;
    }

    .profile-photo-circle {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        border: 4px solid #fff;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15);
        object-fit: cover;
        background: #eef2fb;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.6rem;
        font-weight: 700;
        color: #1558e8;
    }

    .profile-photo-upload-btn {
        position: absolute;
        bottom: 2px;
        right: 2px;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #1558e8;
        color: #fff;
        border: 3px solid #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: background 0.15s ease;
    }

    .profile-photo-upload-btn:hover {
        background: #0f45bd;
    }

    .profile-photo-upload-btn input[type="file"] {
        display: none;
    }

    .profile-form-label {
        font-weight: 600;
        font-size: 0.82rem;
        color: #45506b;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .profile-form-control {
        border-radius: 10px;
        padding: 0.65rem 0.9rem;
        border: 1px solid #dde3ef;
    }

    .profile-form-control:focus {
        border-color: #1558e8;
        box-shadow: 0 0 0 3px rgba(21, 88, 232, 0.12);
    }

    .btn-save-profile {
        background: linear-gradient(135deg, #1558e8 0%, #2f7af0 100%);
        border: none;
        border-radius: 10px;
        padding: 0.7rem;
        font-weight: 600;
        box-shadow: 0 6px 16px rgba(21, 88, 232, 0.25);
    }

    .btn-save-profile:hover {
        opacity: 0.95;
        color: #fff;
    }
</style>

<h3 class="mb-4"><i class="bi bi-person-circle"></i> My Profile</h3>

<!-- Success message -->
<?php if ($message != ''): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<!-- Error messages -->
<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endforeach; ?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card profile-card">
            <div class="card-header-band"></div>

            <div class="card-body px-4 pb-4">

                <form method="POST" enctype="multipart/form-data">

                    <!-- Photo, with a small camera button to change it -->
                    <div class="profile-photo-wrap">
                        <?php if ($has_photo): ?>
                            <img class="profile-photo-circle"
                                 src="<?php echo $upload_url . htmlspecialchars($customer['profile_photo']); ?>"
                                 alt="Profile photo" id="photoPreview">
                        <?php else: ?>
                            <div class="profile-photo-circle" id="photoPreview"><?php echo htmlspecialchars($initial); ?></div>
                        <?php endif; ?>

                        <label class="profile-photo-upload-btn" title="Change photo">
                            <i class="bi bi-camera-fill"></i>
                            <input type="file" name="profile_photo" accept="image/png, image/jpeg, image/webp" id="photoInput">
                        </label>
                    </div>
                    <p class="text-center text-muted small mt-2 mb-4">JPG, PNG or WEBP — up to 3 MB</p>

                    <div class="mb-3">
                        <label class="profile-form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control profile-form-control"
                               value="<?php echo htmlspecialchars($customer['full_name']); ?>" required>
                    </div>

                    <!-- Username and email cannot be changed -->
                    <div class="mb-3">
                        <label class="profile-form-label">Username</label>
                        <input type="text" class="form-control profile-form-control bg-light" value="<?php echo htmlspecialchars($customer['username']); ?>" disabled>
                    </div>

                    <div class="mb-3">
                        <label class="profile-form-label">Email</label>
                        <input type="email" class="form-control profile-form-control bg-light" value="<?php echo htmlspecialchars($customer['email']); ?>" disabled>
                    </div>

                    <div class="mb-3">
                        <label class="profile-form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control profile-form-control"
                               value="<?php echo htmlspecialchars($customer['phone']); ?>">
                    </div>

                    <div class="mb-4">
                        <label class="profile-form-label">Preferred Contact Method</label>
                        <select name="preferred_contact_method" class="form-select profile-form-control">
                            <?php foreach ($contact_methods as $method): ?>
                                <?php
                                $selected = '';
                                if ($customer['preferred_contact_method'] === $method) {
                                    $selected = 'selected';
                                }
                                ?>
                                <option value="<?php echo $method; ?>" <?php echo $selected; ?>><?php echo $method; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-save-profile text-white w-100">Save Changes</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Show the chosen photo immediately, before the form is submitted
document.getElementById('photoInput').addEventListener('change', function (event) {
    var file = event.target.files[0];
    if (!file) {
        return;
    }

    var reader = new FileReader();
    reader.onload = function (loadEvent) {
        var preview = document.getElementById('photoPreview');
        var newImage = document.createElement('img');
        newImage.src = loadEvent.target.result;
        newImage.className = 'profile-photo-circle';
        newImage.id = 'photoPreview';
        preview.replaceWith(newImage);
    };
    reader.readAsDataURL(file);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>