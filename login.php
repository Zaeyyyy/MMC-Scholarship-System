<?php
session_start();
require_once 'config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schoolId = trim($_POST['school_id'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($schoolId === '' || $password === '') {
        $error = 'School ID and password are required.';
    } else {
        $role = 'student';
          $stmt = $conn->prepare('SELECT id, full_name, email, password_hash, role, school_id FROM users WHERE school_id = ? AND role = ?');
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
            $_SESSION['role'] = $user['role'];
            $_SESSION['school_id'] = $user['school_id'];
            header('Location: student_dashboard.php');
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
<title>Sign In — Scholarship Management System</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="seal"><img src="images/midwest-logo.png" alt="Scholarship logo"></div>
    <h1>Student</h1>
    <p class="sub">Sign in with your school ID to view scholarships and requirements.</p>
    <form method="POST" action="login.php">
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
    <p class="auth-switch">Don't have an account? <a href="register.php">Create one</a></p>
    <p class="auth-switch">Admin? <a href="admin_login.php">Sign in here</a></p>
  </div>
</div>
</body>
</html>
