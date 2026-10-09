<?php
require_once 'includes/auth_check.php';
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . ($_POST['redirect_to'] ?? '/'));
    exit;
}

$redirect = $_POST['redirect_to'] ?? '/';
$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$school_id = trim($_POST['school_id'] ?? '');

// profile picture upload (optional)
$profilePhotoFilename = null;
if (!empty($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
    $pf = $_FILES['profile_picture'];
    if ($pf['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg','jpeg','png'];
        $ext = strtolower(pathinfo($pf['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            $_SESSION['flash_error'] = 'Invalid profile picture type.';
            header('Location: ' . $redirect);
            exit;
        }
        if ($pf['size'] > 2 * 1024 * 1024) {
            $_SESSION['flash_error'] = 'Profile picture must be 2MB or smaller.';
            header('Location: ' . $redirect);
            exit;
        }
        $uploadDir = __DIR__ . '/uploads/profiles/' . (int)$_SESSION['user_id'];
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $safeName = preg_replace('/[^A-Za-z0-9_.-]/', '_', time() . '_' . basename($pf['name']));
        $dest = $uploadDir . '/' . $safeName;
        if (!move_uploaded_file($pf['tmp_name'], $dest)) {
            $_SESSION['flash_error'] = 'Failed to save profile picture.';
            header('Location: ' . $redirect);
            exit;
        }
        $profilePhotoFilename = $safeName;
    }
}

if ($full_name === '' || $email === '') {
    $_SESSION['flash_error'] = 'Full name and email are required.';
    header('Location: ' . $redirect);
    exit;
}

// ensure email is not used by another account
$stmt = $conn->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
$stmt->bind_param('si', $email, $_SESSION['user_id']);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($res) {
    $_SESSION['flash_error'] = 'Email is already in use by another account.';
    header('Location: ' . $redirect);
    exit;
}

$params = [];
$sql = 'UPDATE users SET full_name = ?, email = ?, school_id = ?';
array_push($params, $full_name, $email, $school_id);

// ensure profile_photo column exists
$colRes = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_photo'");
if ($colRes && $colRes->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL");
}

if ($profilePhotoFilename !== null) {
    $sql .= ', profile_photo = ?';
    array_push($params, $profilePhotoFilename);
}

$sql .= ' WHERE id = ?';
array_push($params, $_SESSION['user_id']);

$types = str_repeat('s', count($params)-1) . 'i';
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$ok = $stmt->execute();
$stmt->close();

if ($ok) {
    // refresh session values
    $_SESSION['full_name'] = $full_name;
    $_SESSION['email'] = $email;
    $_SESSION['school_id'] = $school_id;
    if ($profilePhotoFilename !== null) $_SESSION['profile_photo'] = $profilePhotoFilename;
    $_SESSION['flash_success'] = 'Profile updated successfully.';
} else {
    $_SESSION['flash_error'] = 'Failed to update profile.';
}

header('Location: ' . $redirect);
exit;
