<?php
require_once 'includes/admin_auth.php';
require_once 'config/db.php';

$pageTitle = 'Scholarship Program — Scholarship Management System';
$activePage = 'programs';

// ---------- Handle delete ----------
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $check = $conn->prepare('SELECT COUNT(*) AS n FROM requirements WHERE scholarship_program_id = ?');
    $check->bind_param('i', $id);
    $check->execute();
    $inUse = $check->get_result()->fetch_assoc()['n'];

    if ($inUse > 0) {
        $_SESSION['flash_error'] = 'This program has requirements attached to it. Remove those first.';
    } else {
        $del = $conn->prepare('DELETE FROM scholarship_programs WHERE id = ?');
        $del->bind_param('i', $id);
        $del->execute();
        $_SESSION['flash_success'] = 'Scholarship program deleted.';
    }
    header('Location: scholarship_program.php');
    exit;
}

// ---------- Handle add / edit submit ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $academicYearId = (int) ($_POST['academic_year_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $slots = (int) ($_POST['slots'] ?? 0);
    $amount = (float) ($_POST['amount'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    if ($academicYearId <= 0 || $name === '') {
        $_SESSION['flash_error'] = 'Academic year and program name are required.';
    } else {
        if ($id > 0) {
            $stmt = $conn->prepare(
                'UPDATE scholarship_programs SET academic_year_id=?, name=?, slots=?, amount=?, description=? WHERE id=?'
            );
            $stmt->bind_param('isidsi', $academicYearId, $name, $slots, $amount, $description, $id);
            $stmt->execute();
            $_SESSION['flash_success'] = 'Scholarship program updated.';
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO scholarship_programs (academic_year_id, name, slots, amount, description) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('isids', $academicYearId, $name, $slots, $amount, $description);
            $stmt->execute();
            $_SESSION['flash_success'] = 'Scholarship program added.';
        }
    }
    header('Location: scholarship_program.php');
    exit;
}

// ---------- Load record for editing ----------
$editing = null;
if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    $stmt = $conn->prepare('SELECT * FROM scholarship_programs WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $editing = $stmt->get_result()->fetch_assoc();
}

// ---------- Load dropdown options and table data ----------
$academicYears = $conn->query('SELECT * FROM academic_years ORDER BY start_date DESC, id DESC')->fetch_all(MYSQLI_ASSOC);

$programs = $conn->query(
    'SELECT sp.*, ay.label AS year_label, ay.term AS year_term
     FROM scholarship_programs sp
     JOIN academic_years ay ON ay.id = sp.academic_year_id
     ORDER BY sp.id DESC'
)->fetch_all(MYSQLI_ASSOC);

require_once 'includes/header.php';
?>

<div class="panel-head">
  <div>
    <span class="eyebrow">Module 2</span>
    <h2>Scholarship Program</h2>
  </div>
  <span class="count-chip"><?= count($programs) ?> record<?= count($programs) !== 1 ? 's' : '' ?></span>
</div>

<div class="card">
  <h3><?= $editing ? 'Edit Scholarship Program' : 'Add Scholarship Program' ?></h3>

  <?php if (empty($academicYears)): ?>
    <p class="form-note">You need at least one academic year before creating a scholarship program.
      <a href="academic_year.php">Add one here</a>.</p>
  <?php else: ?>
    <form method="POST" action="scholarship_program.php">
      <input type="hidden" name="id" value="<?= $editing['id'] ?? '' ?>">
      <div class="form-grid">
        <div class="field full">
          <label for="name">Program Name</label>
          <input type="text" id="name" name="name" placeholder="e.g. Academic Excellence Grant"
                 value="<?= htmlspecialchars($editing['name'] ?? '') ?>" required>
        </div>
        <div class="field">
          <label for="academic_year_id">Academic Year</label>
          <?php $yearVal = $editing['academic_year_id'] ?? null; ?>
          <select id="academic_year_id" name="academic_year_id" required>
            <?php foreach ($academicYears as $y): ?>
              <option value="<?= $y['id'] ?>" <?= $yearVal == $y['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($y['label']) ?> — <?= htmlspecialchars($y['term']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="slots">Available Slots</label>
          <input type="number" id="slots" name="slots" min="1" placeholder="e.g. 50"
                 value="<?= htmlspecialchars($editing['slots'] ?? '') ?>" required>
        </div>
        <div class="field">
          <label for="amount">Grant Amount (₱)</label>
          <input type="number" id="amount" name="amount" min="0" step="0.01" placeholder="e.g. 10000"
                 value="<?= htmlspecialchars($editing['amount'] ?? '') ?>" required>
        </div>
        <div class="field full">
          <label for="description">Coverage / Description</label>
          <textarea id="description" name="description" placeholder="What this scholarship covers, eligibility notes, etc."><?= htmlspecialchars($editing['description'] ?? '') ?></textarea>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $editing ? 'Save Changes' : 'Add Program' ?></button>
        <?php if ($editing): ?><a href="scholarship_program.php" class="btn btn-cancel">Cancel</a><?php endif; ?>
      </div>
    </form>
  <?php endif; ?>
</div>

<?php if (empty($programs)): ?>
  <div class="empty-state"><strong>No scholarship programs yet</strong>Create one above and attach it to an academic year.</div>
<?php else: ?>
  <table>
    <thead><tr><th>Program</th><th>Academic Year</th><th>Slots</th><th>Grant Amount</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($programs as $p): ?>
        <tr>
          <td>
            <strong><?= htmlspecialchars($p['name']) ?></strong>
            <?php if ($p['description']): ?>
              <div style="color:var(--muted); font-size:0.8rem; margin-top:4px;"><?= htmlspecialchars($p['description']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($p['year_label']) ?> (<?= htmlspecialchars($p['year_term']) ?>)</td>
          <td><?= (int) $p['slots'] ?></td>
          <td>₱<?= number_format($p['amount'], 2) ?></td>
          <td class="row-actions">
            <a class="icon-btn" href="scholarship_program.php?edit=<?= $p['id'] ?>">Edit</a>
            <a class="icon-btn danger" href="scholarship_program.php?delete=<?= $p['id'] ?>"
               onclick="return confirm('Delete this scholarship program?');">Delete</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
