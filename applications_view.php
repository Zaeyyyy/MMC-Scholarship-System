<?php
require_once 'includes/admin_auth.php';
require_once 'config/db.php';

$pageTitle = 'Application Detail — Scholarship Management System';
$activePage = 'applications';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: applications.php');
    exit;
}

// Load application
$stmt = $conn->prepare(
    'SELECT a.*, u.full_name, u.school_id, u.email, sp.name AS program_name
     FROM applications a
     JOIN users u ON u.id = a.user_id
     JOIN scholarship_programs sp ON sp.id = a.scholarship_program_id
     WHERE a.id = ?'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$app) {
    $_SESSION['flash_error'] = 'Application not found.';
    header('Location: applications.php');
    exit;
}
// Keep undecided applications in the review queue, including records from the old Pending workflow.
if ($app['status'] === 'Pending') {
  $u = $conn->prepare('UPDATE applications SET status = ? WHERE id = ?');
  $forReview = 'For Review';
  $u->bind_param('si', $forReview, $id);
  $u->execute();
  $u->close();
  $app['status'] = $forReview;
}

// load uploaded documents for this application
$stmt = $conn->prepare('SELECT * FROM application_documents WHERE application_id = ? ORDER BY uploaded_at DESC');
$stmt->bind_param('i', $id);
$stmt->execute();
$documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle status change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status']) && in_array($_POST['status'], ['Accepted','Rejected'], true)) {
    $status = $_POST['status'];
    $u = $conn->prepare('UPDATE applications SET status = ? WHERE id = ?');
    $u->bind_param('si', $status, $id);
    $u->execute();
    $u->close();
    $_SESSION['flash_success'] = 'Status updated.';
    header('Location: applications_view.php?id=' . $id);
    exit;
}

require_once 'includes/header.php';
?>

<div class="panel-head">
  <div>
    <span class="eyebrow">Applications</span>
    <h2><?= htmlspecialchars($app['program_name']) ?></h2>
    <p class="application-review-subtitle">Reviewing application from <?= htmlspecialchars($app['full_name']) ?></p>
  </div>
  <span class="count-chip"><?= htmlspecialchars($app['status']) ?></span>
</div>

<!-- Admin view modal (read-only, shows applicant form + uploaded files) -->
<div id="viewApplicationModal" class="dialog-backdrop" style="display:flex;">
  <section id="viewApplicationPanel" class="dialog-panel admin-application-dialog" role="dialog" aria-modal="true" aria-labelledby="applicationProgramTitle">
    <div class="dialog-heading">
      <div>
        <span class="eyebrow">Scholarship application</span>
        <h2 id="applicationProgramTitle"><?= htmlspecialchars($app['program_name']) ?></h2>
        <p>Submitted <?= htmlspecialchars($app['applied_at']) ?> <span class="application-status-inline">Current status: <strong><?= htmlspecialchars($app['status']) ?></strong></span></p>
      </div>
      <a href="applications.php" class="dialog-close dialog-close-link" aria-label="Back to applications">&times;</a>
    </div>

    <div class="application-form-content admin-application-content">
      <section class="application-section">
        <h3>Applicant information</h3>
        <div class="form-grid">
          <div class="field"><label>Full Name</label><input type="text" value="<?= htmlspecialchars($app['full_name']) ?>" disabled /></div>
          <div class="field"><label>Email</label><input type="email" value="<?= htmlspecialchars($app['email']) ?>" disabled /></div>
          <div class="field"><label>Birth Date</label><input type="date" value="<?= htmlspecialchars($app['birth_date']) ?>" disabled /></div>
          <div class="field"><label>Contact Number</label><input type="text" value="<?= htmlspecialchars($app['contact_number']) ?>" disabled /></div>
          <div class="field full"><label>Address</label><textarea rows="2" disabled><?= htmlspecialchars($app['address']) ?></textarea></div>
          <div class="field"><label>School ID</label><input type="text" value="<?= htmlspecialchars($app['school_id']) ?>" disabled /></div>
          <div class="field"><label>Course / Program</label><input type="text" value="<?= htmlspecialchars($app['course']) ?>" disabled /></div>
          <div class="field"><label>Year Level</label><input type="text" value="<?= htmlspecialchars($app['student_year']) ?>" disabled /></div>
        </div>
      </section>

      <section class="application-section">
      <h3>Attached documents</h3>
      <div class="admin-document-viewer">
        <div id="admin_documents_list" class="admin-document-list">
        <?php if (empty($documents)): ?>
          <div class="form-note">No documents uploaded for this application.</div>
        <?php else: ?>
          <?php foreach ($documents as $index => $doc): ?>
            <?php
              $documentName = $doc['original_name'] ?: $doc['filename'];
              $extension = strtolower(pathinfo($documentName, PATHINFO_EXTENSION));
              $documentUrl = 'uploads/applications/' . (int) $app['id'] . '/' . rawurlencode($doc['filename']);
            ?>
            <button type="button" class="admin-document-button <?= $index === 0 ? 'active' : '' ?>" data-url="<?= htmlspecialchars($documentUrl, ENT_QUOTES) ?>" data-name="<?= htmlspecialchars($documentName, ENT_QUOTES) ?>" data-type="<?= htmlspecialchars($extension, ENT_QUOTES) ?>">
              <span class="document-type-tag"><?= htmlspecialchars(strtoupper($extension ?: 'FILE')) ?></span>
              <span class="admin-document-name"><?= htmlspecialchars($documentName) ?></span>
              <small><?= htmlspecialchars($doc['uploaded_at']) ?></small>
            </button>
          <?php endforeach; ?>
        <?php endif; ?>
        </div>
        <?php if (!empty($documents)): ?>
          <?php
            $firstDocument = $documents[0];
            $firstName = $firstDocument['original_name'] ?: $firstDocument['filename'];
            $firstExtension = strtolower(pathinfo($firstName, PATHINFO_EXTENSION));
            $firstUrl = 'uploads/applications/' . (int) $app['id'] . '/' . rawurlencode($firstDocument['filename']);
          ?>
          <div class="admin-document-preview" id="adminDocumentPreview">
            <div class="preview-heading"><strong id="adminPreviewName"><?= htmlspecialchars($firstName) ?></strong><a id="adminPreviewDownload" href="<?= htmlspecialchars($firstUrl, ENT_QUOTES) ?>" download>Download</a></div>
            <div class="preview-canvas" id="adminPreviewCanvas">
              <?php if (in_array($firstExtension, ['jpg', 'jpeg', 'png'], true)): ?>
                <img src="<?= htmlspecialchars($firstUrl, ENT_QUOTES) ?>" alt="<?= htmlspecialchars($firstName) ?>">
              <?php elseif ($firstExtension === 'pdf'): ?>
                <iframe src="<?= htmlspecialchars($firstUrl, ENT_QUOTES) ?>" title="<?= htmlspecialchars($firstName) ?>"></iframe>
              <?php else: ?>
                <p>Preview is not available for this file type.</p>
              <?php endif; ?>
            </div>
          </div>
          <script>
            document.querySelectorAll('.admin-document-button').forEach(function (button) {
              button.addEventListener('click', function () {
                document.querySelectorAll('.admin-document-button').forEach(function (item) { item.classList.remove('active'); });
                button.classList.add('active');
                const canvas = document.getElementById('adminPreviewCanvas');
                const name = button.dataset.name;
                const type = button.dataset.type;
                const url = button.dataset.url;
                document.getElementById('adminPreviewName').textContent = name;
                document.getElementById('adminPreviewDownload').href = url;
                canvas.innerHTML = '';
                if (['jpg', 'jpeg', 'png'].includes(type)) {
                  const image = document.createElement('img');
                  image.src = url;
                  image.alt = name;
                  canvas.appendChild(image);
                } else if (type === 'pdf') {
                  const frame = document.createElement('iframe');
                  frame.src = url;
                  frame.title = name;
                  canvas.appendChild(frame);
                } else {
                  const message = document.createElement('p');
                  message.textContent = 'Preview is not available for this file type.';
                  canvas.appendChild(message);
                }
              });
            });
          </script>
        <?php endif; ?>
      </div>
      </section>

    </div>
    <div class="dialog-actions admin-review-actions">
      <form method="POST" action="applications_view.php?id=<?= (int) $app['id'] ?>" class="status-update-form">
        <label for="status">Change status</label>
        <select id="status" name="status">
          <?php if (!in_array($app['status'], ['Accepted', 'Rejected'], true)): ?>
            <option value="" selected disabled>For Review</option>
          <?php endif; ?>
          <?php foreach (['Accepted','Rejected'] as $s): ?>
            <option value="<?= $s ?>" <?= $app['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">Update Status</button>
      </form>
      <a href="applications.php" class="btn btn-cancel">Back to Applications</a>
    </div>
  </section>
</div>

<?php require_once 'includes/footer.php'; ?>

