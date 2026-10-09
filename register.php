<?php
session_start();
require_once 'config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $fullName = trim($_POST['full_name'] ?? '');
  $schoolId = trim($_POST['school_id'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($fullName === '' || $schoolId === '' || $email === '' || $password === '') {
        $error = 'Full name, School ID, email, and password are all required.';
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
            $role = 'student';
            $insert = $conn->prepare('INSERT INTO users (full_name, email, school_id, password_hash, role) VALUES (?, ?, ?, ?, ?)');
            $insert->bind_param('sssss', $fullName, $email, $schoolId, $passwordHash, $role);
            $insert->execute();

            $_SESSION['user_id'] = $insert->insert_id;
            $_SESSION['full_name'] = $fullName;
            $_SESSION['email'] = $email;
            $_SESSION['school_id'] = $schoolId;
            $_SESSION['role'] = $role;
            header('Location: student_dashboard.php');
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
<title>Create Account — Scholarship Management System</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="seal"><img src="images/logo.png" alt="Scholarship logo"></div>
    <h1>Create your account</h1>
    <p class="sub">Create Your Account To Apply for Scholarships</p>
    <form method="POST" action="register.php">
      <div class="field">
        <label for="full_name">Full Name</label>
        <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
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
      <button type="submit" class="btn btn-primary">Create Account</button>
      <?php if ($error): ?><p class="flash flash-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    </form>
    <p class="auth-switch">Already have an account? <a href="login.php">Sign in</a></p>
  </div>
</div>
</body>
</html>
