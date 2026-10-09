<?php
require_once 'includes/admin_auth.php';
require_once 'config/db.php';

$pageTitle = 'Academic Year — Scholarship Management System';
$activePage = 'years';

// ---------- Handle delete ----------
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $check = $conn->prepare('SELECT COUNT(*) AS n FROM scholarship_programs WHERE academic_year_id = ?');
    $check->bind_param('i', $id);
    $check->execute();
    $inUse = $check->get_result()->fetch_assoc()['n'];

    if ($inUse > 0) {
        $_SESSION['flash_error'] = 'This academic year has scholarship programs attached to it. Remove those first.';
    } else {
        $del = $conn->prepare('DELETE FROM academic_years WHERE id = ?');
        $del->bind_param('i', $id);
        $del->execute();
        $_SESSION['flash_success'] = 'Academic year deleted.';
    }
    header('Location: academic_year.php');
    exit;
}

// ---------- Handle add / edit submit ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $label = trim($_POST['label'] ?? '');
    $term = trim($_POST['term'] ?? '');
    $startDate = $_POST['start_date'] ?? null;
    $endDate = $_POST['end_date'] ?? null;
    $status = $_POST['status'] ?? 'Upcoming';

    if ($label === '' || $term === '') {
        $_SESSION['flash_error'] = 'School year and term are required.';
    } else {
        if ($id > 0) {
            $stmt = $conn->prepare(
                'UPDATE academic_years SET label=?, term=?, start_date=?, end_date=?, status=? WHERE id=?'
            );
            $stmt->bind_param('sssssi', $label, $term, $startDate, $endDate, $status, $id);
            $stmt->execute();
            $_SESSION['flash_success'] = 'Academic year updated.';
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO academic_years (label, term, start_date, end_date, status) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('sssss', $label, $term, $startDate, $endDate, $status);
            $stmt->execute();
            $_SESSION['flash_success'] = 'Academic year added.';
        }
    }
    header('Location: academic_year.php');
    exit;
}

// ---------- Load record for editing ----------
$editing = null;
if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    $stmt = $conn->prepare('SELECT * FROM academic_years WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $editing = $stmt->get_result()->fetch_assoc();
}

// ---------- Load all records ----------
$years = $conn->query('SELECT * FROM academic_years ORDER BY start_date DESC, id DESC')->fetch_all(MYSQLI_ASSOC);

require_once 'includes/header.php';

function statusBadge($status) {
    $cls = $status === 'Open' ? 'open' : ($status === 'Closed' ? 'closed' : 'upcoming');
    $text = $status === 'Open' ? 'Open for Application' : $status;
    return '<span class="badge ' . $cls . '">' . htmlspecialchars($text) . '</span>';
}
?>

<div class="panel-head">
  <div>
    <span class="eyebrow">Module 1</span>
    <h2>Academic Year</h2>
  </div>
  <span class="count-chip"><?= count($years) ?> record<?= count($years) !== 1 ? 's' : '' ?></span>
</div>

<div class="card">
  <h3><?= $editing ? 'Edit Academic Year' : 'Add Academic Year' ?></h3>
  <form method="POST" action="academic_year.php">
    <input type="hidden" name="id" value="<?= $editing['id'] ?? '' ?>">
    <div class="form-grid">
      <div class="field">
        <label for="label">School Year</label>
        <input type="text" id="label" name="label" placeholder="e.g. 2026-2027"
               value="<?= htmlspecialchars($editing['label'] ?? '') ?>" required>
      </div>
      <div class="field">
        <label for="term">Term</label>
        <?php $termVal = $editing['term'] ?? 'Whole Year'; ?>
        <select id="term" name="term" required>
          <?php foreach (['Whole Year','1st Semester','2nd Semester','Summer'] as $t): ?>
            <option value="<?= $t ?>" <?= $termVal === $t ? 'selected' : '' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="start_date">Application Start</label>
        <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($editing['start_date'] ?? '') ?>" required>
      </div>
      <div class="field">
        <label for="end_date">Application End</label>
        <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($editing['end_date'] ?? '') ?>" required>
      </div>
      <div class="field">
        <label for="status">Status</label>
        <?php $statusVal = $editing['status'] ?? 'Upcoming'; ?>
        <select id="status" name="status" required>
          <option value="Upcoming" <?= $statusVal === 'Upcoming' ? 'selected' : '' ?>>Upcoming</option>
          <option value="Open" <?= $statusVal === 'Open' ? 'selected' : '' ?>>Open for Application</option>
          <option value="Closed" <?= $statusVal === 'Closed' ? 'selected' : '' ?>>Closed</option>
        </select>
      </div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary"><?= $editing ? 'Save Changes' : 'Add Academic Year' ?></button>
      <?php if ($editing): ?><a href="academic_year.php" class="btn btn-cancel">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>

<?php if (empty($years)): ?>
  <div class="empty-state"><strong>No academic years yet</strong>Add one above to define when a scholarship becomes available.</div>
<?php else: ?>
  <table>
    <thead><tr><th>School Year</th><th>Term</th><th>Application Window</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($years as $y): ?>
        <tr>
          <td><strong><?= htmlspecialchars($y['label']) ?></strong></td>
          <td><?= htmlspecialchars($y['term']) ?></td>
          <td><?= htmlspecialchars($y['start_date'] ?: '—') ?> → <?= htmlspecialchars($y['end_date'] ?: '—') ?></td>
          <td><?= statusBadge($y['status']) ?></td>
          <td class="row-actions">
            <a class="icon-btn" href="academic_year.php?edit=<?= $y['id'] ?>">Edit</a>
            <a class="icon-btn danger" href="academic_year.php?delete=<?= $y['id'] ?>"
               onclick="return confirm('Delete this academic year?');">Delete</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
