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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $schoolId = trim($_POST['school_id'] ?? '');
    $password = $_POST['password'] ?? '';

  if ($schoolId === '' || $password === '') {
    $error = 'School ID and password are required.';
    } else {
    $stmt = $conn->prepare('SELECT id, full_name, email, school_id, password_hash FROM users WHERE school_id = ? AND role = ?');
        $role = 'admin';
    $stmt->bind_param('ss', $schoolId, $role);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'Incorrect school ID or password.';
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['school_id'] = $user['school_id'];
            $_SESSION['role'] = 'admin';
            header('Location: academic_year.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Sign In — Scholarship Management System</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="seal"><img src="images/midwest-logo.png" alt="Scholarship logo"></div>
    <h1>Admin</h1>
    <p class="sub">Sign in to manage academic years, scholarship programs, and requirements.</p>
    <form method="POST" action="admin_login.php">
      <div class="field">
        <label for="school_id">School ID</label>
        <input type="text" id="school_id" name="school_id" value="<?= htmlspecialchars($_POST['school_id'] ?? '') ?>" required>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
      </div>
      <button type="submit" class="btn btn-primary">Sign In</button>
      <?php if ($error): ?><p class="flash flash-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    </form>
    <?php if (!$adminExists): ?>
      <p class="auth-switch">Need an admin account? <a href="admin_register.php">Create one</a></p>
    <?php endif; ?>
    <p class="auth-switch">Student? <a href="login.php">Sign in here</a></p>
  </div>
</div>
</body>
</html>
