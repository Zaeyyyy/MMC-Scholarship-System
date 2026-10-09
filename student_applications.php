<?php
require_once 'includes/student_auth.php';
require_once 'config/db.php';

$pageTitle = 'My Applications — Scholarship Management System';
$activePage = 'my_applications';

// Ensure applications table exists
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

$stmt = $conn->prepare(
    'SELECT a.id, a.applied_at, a.status, sp.name AS program_name
     FROM applications a
     JOIN scholarship_programs sp ON sp.id = a.scholarship_program_id
     WHERE a.user_id = ?
     ORDER BY a.applied_at DESC'
);
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

require_once 'includes/header.php';
?>

<div class="panel-head">
  <div>
    <span class="eyebrow">Student</span>
    <h2>My Applications</h2>
  </div>
  <span class="count-chip"><?= count($apps) ?> application<?= count($apps) !== 1 ? 's' : '' ?></span>
</div>

<div class="card">
  <?php if (empty($apps)): ?>
    <p class="form-note">You have not applied to any programs yet.</p>
  <?php else: ?>
    <table>
      <thead><tr><th>Program</th><th>Applied At</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($apps as $a): ?>
          <?php $displayStatus = ($a['status'] ?? '') !== '' ? $a['status'] : 'For Review'; ?>
          <tr>
            <td><?= htmlspecialchars($a['program_name']) ?></td>
            <td><?= htmlspecialchars($a['applied_at']) ?></td>
            <td><?= htmlspecialchars($displayStatus) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
