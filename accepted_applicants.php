<?php
require_once 'includes/auth_check.php';
require_once 'config/db.php';

$pageTitle = 'Accepted Students — Scholarship Management System';
$activePage = 'accepted_applicants';
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$conn->query(
    'CREATE TABLE IF NOT EXISTS applications (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NOT NULL,
      scholarship_program_id INT NOT NULL,
      applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      status ENUM(\'For Review\',\'Pending\',\'Accepted\',\'Rejected\') NOT NULL DEFAULT \'For Review\',
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
      FOREIGN KEY (scholarship_program_id) REFERENCES scholarship_programs(id) ON DELETE CASCADE
    ) ENGINE=InnoDB'
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
  $submittedToken = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
    $_SESSION['flash_error'] = 'The request could not be verified. Please try again.';
    header('Location: accepted_applicants.php');
    exit;
  }

  if (($_POST['action'] ?? '') === 'add') {
    $studentId = (int) ($_POST['student_id'] ?? 0);
    $programId = (int) ($_POST['program_id'] ?? 0);

    $studentCheck = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'student' LIMIT 1");
    $studentCheck->bind_param('i', $studentId);
    $studentCheck->execute();
    $studentExists = $studentCheck->get_result()->num_rows > 0;
    $studentCheck->close();

    $programCheck = $conn->prepare('SELECT id FROM scholarship_programs WHERE id = ? LIMIT 1');
    $programCheck->bind_param('i', $programId);
    $programCheck->execute();
    $programExists = $programCheck->get_result()->num_rows > 0;
    $programCheck->close();

    if (!$studentExists || !$programExists) {
      $_SESSION['flash_error'] = 'Choose a valid student and scholarship program.';
    } else {
      $existing = $conn->prepare('SELECT id, status FROM applications WHERE user_id = ? AND scholarship_program_id = ? ORDER BY applied_at DESC, id DESC LIMIT 1');
      $existing->bind_param('ii', $studentId, $programId);
      $existing->execute();
      $application = $existing->get_result()->fetch_assoc();
      $existing->close();

      if ($application && $application['status'] === 'Accepted') {
        $_SESSION['flash_error'] = 'This student is already accepted for that program.';
      } elseif ($application) {
        $accepted = 'Accepted';
        $update = $conn->prepare('UPDATE applications SET status = ? WHERE id = ?');
        $update->bind_param('si', $accepted, $application['id']);
        $update->execute();
        $update->close();
        $_SESSION['flash_success'] = 'Student added to the accepted list.';
      } else {
        $accepted = 'Accepted';
        $insert = $conn->prepare('INSERT INTO applications (user_id, scholarship_program_id, status) VALUES (?, ?, ?)');
        $insert->bind_param('iis', $studentId, $programId, $accepted);
        $insert->execute();
        $insert->close();
        $_SESSION['flash_success'] = 'Student added to the accepted list.';
      }
    }
  } elseif (($_POST['action'] ?? '') === 'remove') {
    $applicationId = (int) ($_POST['application_id'] ?? 0);
    $forReview = 'For Review';
    $remove = $conn->prepare("UPDATE applications SET status = ? WHERE id = ? AND status = 'Accepted'");
    $remove->bind_param('si', $forReview, $applicationId);
    $remove->execute();
    $removed = $remove->affected_rows > 0;
    $remove->close();
    $_SESSION[$removed ? 'flash_success' : 'flash_error'] = $removed
      ? 'Student removed from the accepted list. The application record was kept for reference.'
      : 'Accepted application was not found.';
  }

  header('Location: accepted_applicants.php');
  exit;
}

$students = [];
$programs = [];
if ($isAdmin) {
  $students = $conn->query("SELECT id, school_id, full_name FROM users WHERE role = 'student' ORDER BY full_name ASC")->fetch_all(MYSQLI_ASSOC);
  $programs = $conn->query('SELECT id, name FROM scholarship_programs ORDER BY name ASC')->fetch_all(MYSQLI_ASSOC);
}

$result = $conn->query(
  "SELECT a.id AS application_id, u.school_id, u.full_name, sp.name AS program_name
     FROM applications a
     JOIN users u ON u.id = a.user_id
     JOIN scholarship_programs sp ON sp.id = a.scholarship_program_id
     WHERE a.status = 'Accepted'
     ORDER BY sp.name ASC, u.full_name ASC"
);
$acceptedApplicants = $result->fetch_all(MYSQLI_ASSOC);

require_once 'includes/header.php';
?>

<div class="panel-head">
  <div>
    <span class="eyebrow"><?= ($_SESSION['role'] ?? '') === 'admin' ? 'Administration' : 'Student' ?></span>
    <h2>Accepted Students</h2>
  </div>
  <span class="count-chip"><?= count($acceptedApplicants) ?> accepted</span>
</div>

<?php if ($isAdmin): ?>
  <div class="card accepted-management-card">
    <h3>Add accepted student</h3>
    <?php if (empty($students) || empty($programs)): ?>
      <p class="form-note">Add student accounts and scholarship programs before managing accepted students.</p>
    <?php else: ?>
      <form method="POST" action="accepted_applicants.php" class="accepted-add-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="add">
        <div class="field">
          <label for="student_id">Student</label>
          <select id="student_id" name="student_id" required>
            <option value="">Choose student</option>
            <?php foreach ($students as $student): ?>
              <option value="<?= (int) $student['id'] ?>"><?= htmlspecialchars(($student['school_id'] ?: 'No School ID') . ' — ' . $student['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="program_id">Scholarship Program</label>
          <select id="program_id" name="program_id" required>
            <option value="">Choose program</option>
            <?php foreach ($programs as $program): ?>
              <option value="<?= (int) $program['id'] ?>"><?= htmlspecialchars($program['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary">Add to Accepted</button>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if (empty($acceptedApplicants)): ?>
  <div class="empty-state"><strong>No accepted students yet</strong>Students with accepted scholarship applications will appear here.</div>
<?php else: ?>
  <table>
    <thead>
      <tr>
        <th>School ID</th>
        <th>Student Name</th>
        <th>Scholarship Program</th>
        <?php if ($isAdmin): ?><th>Actions</th><?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($acceptedApplicants as $applicant): ?>
        <tr>
          <td><?= htmlspecialchars($applicant['school_id'] ?? '') ?></td>
          <td><?= htmlspecialchars($applicant['full_name']) ?></td>
          <td><?= htmlspecialchars($applicant['program_name']) ?></td>
          <?php if ($isAdmin): ?>
            <td class="accepted-applicant-actions">
              <form method="POST" action="accepted_applicants.php" onsubmit="return confirm('Remove this student from the accepted list? The application record will be kept.');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="application_id" value="<?= (int) $applicant['application_id'] ?>">
                <button type="submit" class="icon-btn danger">Remove</button>
              </form>
            </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
