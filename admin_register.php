<?php
session_start();
require_once 'config/db.php';

$error = '';

$adminExists = false;
$stmt = $conn->prepare('SELECT COUNT(*) AS n FROM users WHERE role = ?');
$role = 'admin';
$stmt->bind_param('s', $role);
$stmt->execute();
$adminExists = (int) $stmt->get_result()->fetch_assoc()['n'] > 0;
$stmt->close();

if ($adminExists && (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin')) {
    header('Location: admin_login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminName = trim($_POST['admin_name'] ?? '');
  $schoolId = trim($_POST['school_id'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

  if ($adminName === '' || $schoolId === '' || $email === '' || $password === '') {
    $error = 'All fields are required.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = 'An account with that email already exists.';
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'admin';
            $insert = $conn->prepare('INSERT INTO users (full_name, email, school_id, password_hash, role) VALUES (?, ?, ?, ?, ?)');
            $insert->bind_param('sssss', $adminName, $email, $schoolId, $passwordHash, $role);
            $insert->execute();

            $_SESSION['user_id'] = $insert->insert_id;
            $_SESSION['full_name'] = $adminName;
            $_SESSION['email'] = $email;
            $_SESSION['school_id'] = $schoolId;
            $_SESSION['role'] = $role;
            header('Location: academic_year.php');
            exit;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Admin Account — Scholarship Management System</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="seal"><img src="images/midwest-logo.png" alt="Scholarship logo"></div>
    <h1>Create Admin Account</h1>
    <p class="sub">Register an admin account to manage scholarships and requirements.</p>
    <form method="POST" action="admin_register.php">
      <div class="field">
        <label for="admin_name">Admin Name</label>
        <input type="text" id="admin_name" name="admin_name" value="<?= htmlspecialchars($_POST['admin_name'] ?? '') ?>" required>
      </div>
      <div class="field">
        <label for="school_id">School ID</label>
        <input type="text" id="school_id" name="school_id" value="<?= htmlspecialchars($_POST['school_id'] ?? '') ?>" required>
      </div>
      <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" minlength="6" required>
      </div>
      <div class="field">
        <label for="confirm_password">Confirm Password</label>
        <input type="password" id="confirm_password" name="confirm_password" minlength="6" required>
      </div>
      <button type="submit" class="btn btn-primary">Create Admin</button>
      <?php if ($error): ?><p class="flash flash-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    </form>
    <p class="auth-switch">Already have an account? <a href="admin_login.php">Sign in</a></p>
  </div>
</div>
</body>
</html>
