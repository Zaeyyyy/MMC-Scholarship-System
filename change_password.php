<?php
require_once 'includes/auth_check.php';
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . ($_POST['redirect_to'] ?? '/'));
    exit;
}

$redirect = $_POST['redirect_to'] ?? '/';
$current = $_POST['current_password'] ?? '';
$new = $_POST['new_password'] ?? '';
$confirm = $_POST['new_password_confirm'] ?? '';

if ($current === '' || $new === '' || $confirm === '') {
    $_SESSION['flash_error'] = 'All password fields are required.';
    header('Location: ' . $redirect);
    exit;
}

if ($new !== $confirm) {
    $_SESSION['flash_error'] = 'New passwords do not match.';
    header('Location: ' . $redirect);
    exit;
}

// fetch current password hash
$stmt = $conn->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$res || !password_verify($current, $res['password_hash'])) {
    $_SESSION['flash_error'] = 'Current password is incorrect.';
    header('Location: ' . $redirect);
    exit;
}

$hash = password_hash($new, PASSWORD_DEFAULT);
$u = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
$u->bind_param('si', $hash, $_SESSION['user_id']);
$ok = $u->execute();
$u->close();

if ($ok) {
    $_SESSION['flash_success'] = 'Password changed successfully.';
} else {
    $_SESSION['flash_error'] = 'Failed to change password.';
}
header('Location: ' . $redirect);
exit;
