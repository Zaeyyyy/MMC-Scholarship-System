<?php
require_once 'includes/admin_auth.php';
require_once 'config/db.php';

$pageTitle = 'Requirements — Scholarship Management System';
$activePage = 'requirements';

// ---------- Handle delete ----------
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $del = $conn->prepare('DELETE FROM requirements WHERE id = ?');
    $del->bind_param('i', $id);
    $del->execute();
    $_SESSION['flash_success'] = 'Requirement deleted.';
    header('Location: requirements.php');
    exit;
}

// ---------- Handle add / edit submit ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $programId = (int) ($_POST['scholarship_program_id'] ?? 0);
    $documentName = trim($_POST['document_name'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $mandatory = isset($_POST['mandatory']) ? 1 : 0;

    if ($programId <= 0 || $documentName === '') {
        $_SESSION['flash_error'] = 'Scholarship program and document name are required.';
    } else {
        if ($id > 0) {
            $stmt = $conn->prepare(
                'UPDATE requirements SET scholarship_program_id=?, document_name=?, notes=?, mandatory=? WHERE id=?'
            );
            $stmt->bind_param('issii', $programId, $documentName, $notes, $mandatory, $id);
            $stmt->execute();
            $_SESSION['flash_success'] = 'Requirement updated.';
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO requirements (scholarship_program_id, document_name, notes, mandatory) VALUES (?, ?, ?, ?)'
            );
            $stmt->bind_param('issi', $programId, $documentName, $notes, $mandatory);
            $stmt->execute();
            $_SESSION['flash_success'] = 'Requirement added.';
        }
    }
    header('Location: requirements.php');
    exit;
}

// ---------- Load record for editing ----------
$editing = null;
if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    $stmt = $conn->prepare('SELECT * FROM requirements WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $editing = $stmt->get_result()->fetch_assoc();
}

// ---------- Load dropdown options and table data ----------
$programs = $conn->query('SELECT * FROM scholarship_programs ORDER BY id DESC')->fetch_all(MYSQLI_ASSOC);

$requirements = $conn->query(
    'SELECT r.*, sp.name AS program_name
     FROM requirements r
     JOIN scholarship_programs sp ON sp.id = r.scholarship_program_id
     ORDER BY r.id DESC'
)->fetch_all(MYSQLI_ASSOC);

require_once 'includes/header.php';
?>

<div class="panel-head">
  <div>
    <span class="eyebrow">Module 3</span>
    <h2>Requirements</h2>
  </div>
  <span class="count-chip"><?= count($requirements) ?> record<?= count($requirements) !== 1 ? 's' : '' ?></span>
</div>

<div class="card">
  <h3><?= $editing ? 'Edit Requirement' : 'Add Requirement' ?></h3>

  <?php if (empty($programs)): ?>
    <p class="form-note">You need at least one scholarship program before adding requirements.
      <a href="scholarship_program.php">Add one here</a>.</p>
  <?php else: ?>
    <form method="POST" action="requirements.php">
      <input type="hidden" name="id" value="<?= $editing['id'] ?? '' ?>">
      <div class="form-grid">
        <div class="field">
          <label for="scholarship_program_id">Scholarship Program</label>
          <?php $programVal = $editing['scholarship_program_id'] ?? null; ?>
          <select id="scholarship_program_id" name="scholarship_program_id" required>
            <?php foreach ($programs as $p): ?>
              <option value="<?= $p['id'] ?>" <?= $programVal == $p['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($p['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="document_name">Document Name</label>
          <input type="text" id="document_name" name="document_name" placeholder="e.g. Certificate of Registration"
                 value="<?= htmlspecialchars($editing['document_name'] ?? '') ?>" required>
        </div>
        <div class="field full">
          <label for="notes">Notes</label>
          <input type="text" id="notes" name="notes" placeholder="e.g. Must be an original copy, issued within 30 days"
                 value="<?= htmlspecialchars($editing['notes'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Requirement Type</label>
          <?php $mandatoryVal = $editing ? (bool) $editing['mandatory'] : true; ?>
          <div class="checkbox-row">
            <input type="checkbox" id="mandatory" name="mandatory" <?= $mandatoryVal ? 'checked' : '' ?>>
            <label for="mandatory">Mandatory document</label>
          </div>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $editing ? 'Save Changes' : 'Add Requirement' ?></button>
        <?php if ($editing): ?><a href="requirements.php" class="btn btn-cancel">Cancel</a><?php endif; ?>
      </div>
    </form>
  <?php endif; ?>
</div>

<?php if (empty($requirements)): ?>
  <div class="empty-state"><strong>No requirements yet</strong>List the documents applicants must submit for each scholarship program.</div>
<?php else: ?>
  <table>
    <thead><tr><th>Scholarship Program</th><th>Document</th><th>Notes</th><th>Type</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($requirements as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['program_name']) ?></td>
          <td><strong><?= htmlspecialchars($r['document_name']) ?></strong></td>
          <td><?= $r['notes'] ? htmlspecialchars($r['notes']) : '<span style="color:var(--muted)">—</span>' ?></td>
          <td><?= $r['mandatory'] ? '<span class="badge mandatory">Mandatory</span>' : '<span class="badge optional">Optional</span>' ?></td>
          <td class="row-actions">
            <a class="icon-btn" href="requirements.php?edit=<?= $r['id'] ?>">Edit</a>
            <a class="icon-btn danger" href="requirements.php?delete=<?= $r['id'] ?>"
               onclick="return confirm('Delete this requirement?');">Delete</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
