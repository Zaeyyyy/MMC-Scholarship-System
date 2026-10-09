<?php
require_once 'includes/admin_auth.php';
require_once 'config/db.php';

$pageTitle = 'Applications — Scholarship Management System';
$activePage = 'applications';

// Create table if it doesn't exist (student_dashboard may create it, but ensure here)
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

// Handle final review decisions via quick actions (admin only)
if (isset($_GET['set_status']) && isset($_GET['id']) && in_array($_GET['set_status'], ['Accepted','Rejected'], true)) {
  $id = (int) $_GET['id'];
  $new = $_GET['set_status'];
  $u = $conn->prepare('UPDATE applications SET status = ? WHERE id = ?');
  $u->bind_param('si', $new, $id);
  $u->execute();
  $u->close();
  $_SESSION['flash_success'] = 'Application status updated.';
  header('Location: applications.php');
  exit;
}

// Export CSV
if (isset($_GET['export']) && $_GET['export'] == '1') {
  $rows = $conn->query(
    'SELECT a.id, a.applied_at, a.status, u.full_name, u.school_id, u.email, sp.name AS program_name
     FROM applications a
     JOIN users u ON u.id = a.user_id
     JOIN scholarship_programs sp ON sp.id = a.scholarship_program_id
     ORDER BY a.applied_at DESC'
  )->fetch_all(MYSQLI_ASSOC);

  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="applications.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['ID','Applicant','School ID','Email','Program','Status','Applied At']);
  foreach ($rows as $r) {
    $rStatus = ($r['status'] ?? '') !== '' ? $r['status'] : 'For Review';
    fputcsv($out, [$r['id'], $r['full_name'], $r['school_id'], $r['email'], $r['program_name'], $rStatus, $r['applied_at']]);
  }
  fclose($out);
  exit;
}

// Handle delete action
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $del = $conn->prepare('DELETE FROM applications WHERE id = ?');
    $del->bind_param('i', $id);
    $del->execute();
    $_SESSION['flash_success'] = 'Application removed.';
    header('Location: applications.php');
    exit;
}

// Load applications
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$total = (int) $conn->query('SELECT COUNT(*) AS n FROM applications')->fetch_assoc()['n'];
$apps = $conn->query(
  "SELECT a.id, a.applied_at, a.status, u.id AS user_id, u.full_name, u.school_id, u.email, sp.id AS program_id, sp.name AS program_name
   FROM applications a
   JOIN users u ON u.id = a.user_id
   JOIN scholarship_programs sp ON sp.id = a.scholarship_program_id
   ORDER BY a.applied_at DESC
   LIMIT {$perPage} OFFSET {$offset}"
)->fetch_all(MYSQLI_ASSOC);

require_once 'includes/header.php';
?>

<div class="panel-head">
  <div>
    <span class="eyebrow">Module 4</span>
    <h2>Applications</h2>
  </div>
  <span class="count-chip"><?= count($apps) ?> application<?= count($apps) !== 1 ? 's' : '' ?></span>
</div>

<?php if (empty($apps)): ?>
  <div class="empty-state"><strong>No applications yet</strong> Students will appear here after applying.</div>
<?php else: ?>
  <table>
    <thead>
      <tr>
        <th>Applicant</th>
        <th>School ID</th>
        <th>Email</th>
        <th>Program</th>
        <th>Status</th>
        <th>Applied At</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($apps as $a): ?>
        <?php $displayStatus = ($a['status'] ?? '') !== '' ? $a['status'] : 'For Review'; ?>
        <tr>
          <td>
            <strong><?= htmlspecialchars($a['full_name']) ?></strong>
            <div style="color:var(--muted); font-size:0.85rem;">User ID: <?= (int) $a['user_id'] ?></div>
          </td>
          <td><?= htmlspecialchars($a['school_id']) ?></td>
          <td><?= htmlspecialchars($a['email']) ?></td>
          <td><?= htmlspecialchars($a['program_name']) ?></td>
          <td><?= htmlspecialchars($displayStatus) ?></td>
          <td><?= htmlspecialchars($a['applied_at']) ?></td>
          <td class="row-actions">
              <a class="icon-btn" href="applications_view.php?id=<?= $a['id'] ?>">View</a>
              <a class="icon-btn danger" href="applications.php?delete=<?= $a['id'] ?>" onclick="return confirm('Remove this application?');">Remove</a>
              <div style="margin-top:6px">
                <a class="icon-btn" href="applications.php?set_status=Accepted&id=<?= $a['id'] ?>">Accept</a>
                <a class="icon-btn" href="applications.php?set_status=Rejected&id=<?= $a['id'] ?>">Reject</a>
              </div>
            </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
