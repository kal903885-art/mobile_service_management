<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
if (!isCustomer()) {
    header('Location: /mobile-network-service-management/staff/dashboard.php');
    exit;
}

$pdo = getDatabaseConnection();
$uid = (int)$_SESSION['user_id'];

// >>> CHECK THESE TWO: the column names in your users table <<<
$COL_PHONE   = 'phone';
$COL_CONTACT = 'preferred_contact_method';
$CONTACT_OPTIONS = ['Email', 'Phone', 'SMS'];   // values your database accepts

const MAX_PHOTO_BYTES = 2 * 1024 * 1024;   // 2 MB
$PHOTO_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$uploadDir = __DIR__ . '/../assets/uploads/profiles/';
if (!is_dir($uploadDir)) { mkdir($uploadDir, 0775, true); }

function loadProfile(PDO $pdo, int $uid, string $colPhone, string $colContact): array {
    $s = $pdo->prepare("SELECT full_name, username, email, $colPhone AS phone, $colContact AS contact, profile_photo FROM users WHERE id = ?");
    $s->execute([$uid]);
    return $s->fetch() ?: [];
}

$user   = loadProfile($pdo, $uid, $COL_PHONE, $COL_CONTACT);
$errors = [];
$success = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    }

    $fullName = trim($_POST['full_name'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $contact  = $_POST['contact'] ?? '';
    $allowed  = array_unique(array_merge($CONTACT_OPTIONS, [$user['contact'] ?? '']));

    if ($fullName === '' || mb_strlen($fullName) > 100)       $errors[] = 'Enter your full name.';
    if (!preg_match('/^[0-9+\s-]{7,15}$/', $phone))           $errors[] = 'Enter a valid phone number (digits, +, spaces or dashes).';
    if (!in_array($contact, $allowed, true))                  $errors[] = 'Choose a contact method.';

    $oldPhoto = $user['profile_photo'] ?? null;
    $newPhoto = $oldPhoto;

    $f = $_FILES['photo'] ?? null;
    if ($f && $f['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'The photo could not be uploaded. Try a smaller image.';
        } elseif ($f['size'] > MAX_PHOTO_BYTES) {
            $errors[] = 'Photo must be 2 MB or smaller.';
        } else {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
            if (!isset($PHOTO_TYPES[$mime]) || !@getimagesize($f['tmp_name'])) {
                $errors[] = 'Use a JPG, PNG or WebP image.';
            } elseif (!$errors) {
                $name = 'u' . $uid . '_' . bin2hex(random_bytes(6)) . '.' . $PHOTO_TYPES[$mime];
                if (move_uploaded_file($f['tmp_name'], $uploadDir . $name)) {
                    $newPhoto = $name;
                } else {
                    $errors[] = 'Could not save the photo. Check write permissions on the uploads/profiles folder.';
                }
            }
        }
    } elseif (!empty($_POST['remove_photo'])) {
        $newPhoto = null;
    }

    if (!$errors) {
        $s = $pdo->prepare("UPDATE users SET full_name = ?, $COL_PHONE = ?, $COL_CONTACT = ?, profile_photo = ? WHERE id = ?");
        $s->execute([$fullName, $phone, $contact, $newPhoto, $uid]);

        if ($newPhoto !== $oldPhoto && $oldPhoto && is_file($uploadDir . basename($oldPhoto))) {
            @unlink($uploadDir . basename($oldPhoto));
        }
        $_SESSION['full_name'] = $fullName;
        logAudit($pdo, $uid, 'Profile Updated', 'profile', $uid, 'Customer updated their profile.');

        $_SESSION['flash'] = 'Profile saved.';
        header('Location: profile.php');   // stops the form re-submitting on refresh
        exit;
    }
    // keep what the person typed
    $user = array_merge($user, ['full_name' => $fullName, 'phone' => $phone, 'contact' => $contact]);
}

$photoUrl = !empty($user['profile_photo'])
    ? '/mobile-network-service-management/assets/uploads/profiles/' . rawurlencode($user['profile_photo']) : '';
$initial  = strtoupper(mb_substr($user['full_name'] ?? 'C', 0, 1));
$options  = array_unique(array_merge($CONTACT_OPTIONS, [$user['contact'] ?? '']));

$pageTitle = 'My Profile';
require_once __DIR__ . '/../includes/customer_header.php';
?>

<h1 class="page-title">My Profile</h1>
<p class="page-sub">Keep your details current so we can reach you about your complaints.</p>

<?php if ($success): ?>
    <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?php echo htmlspecialchars($e); ?></div>
<?php endforeach; ?>

<form method="POST" enctype="multipart/form-data" class="card" style="max-width: 760px;" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">

    <div class="profile-banner"></div>
    <div class="profile-head">
        <div class="photo-wrap">
            <img id="photoPreview" src="<?php echo $photoUrl; ?>" alt="Profile photo" style="<?php echo $photoUrl ? '' : 'display:none'; ?>">
            <div id="photoInitial" class="avatar" style="<?php echo $photoUrl ? 'display:none' : ''; ?>"><?php echo htmlspecialchars($initial); ?></div>
            <label class="photo-btn" for="photo" title="Change photo"><i class="bi bi-camera-fill"></i></label>
            <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" hidden>
        </div>
        <div class="pb-1">
            <h2 class="h4 mb-1"><?php echo htmlspecialchars($user['full_name'] ?? ''); ?></h2>
            <span class="chip">Customer</span>
            <div class="hint mt-2">JPG, PNG or WebP, up to 2 MB.
                <?php if ($photoUrl): ?>
                    <label class="ms-2"><input type="checkbox" name="remove_photo" value="1" id="removePhoto"> Remove photo</label>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="p-4 pt-2">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="full_name">Full name</label>
                <input class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="username">Username</label>
                <input class="form-control" id="username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" readonly>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="email">Email</label>
                <input class="form-control" id="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="phone">Phone number</label>
                <input class="form-control" id="phone" name="phone" inputmode="tel" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
            </div>
            <div class="col-12">
                <label class="form-label" for="contact">Preferred contact method</label>
                <select class="form-select" id="contact" name="contact">
                    <?php foreach ($options as $m): if ($m === '') continue; ?>
                        <option <?php echo ($user['contact'] ?? '') === $m ? 'selected' : ''; ?>><?php echo htmlspecialchars($m); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="mt-4 text-end">
            <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle me-2"></i>Save changes</button>
        </div>
    </div>
</form>

<script>
    // Instant preview when a photo is chosen
    document.getElementById('photo').addEventListener('change', function () {
        var file = this.files[0];
        if (!file) return;
        if (file.size > 2 * 1024 * 1024) { alert('Photo must be 2 MB or smaller.'); this.value = ''; return; }
        var img = document.getElementById('photoPreview');
        img.src = URL.createObjectURL(file);
        img.style.display = '';
        document.getElementById('photoInitial').style.display = 'none';
        var rm = document.getElementById('removePhoto'); if (rm) rm.checked = false;
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>