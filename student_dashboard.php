<?php
require_once 'includes/student_auth.php';
require_once 'config/db.php';

$pageTitle = 'Student Dashboard — Scholarship Management System';
$activePage = 'dashboard';

$pageMessage = '';
$pageError = '';

$years = $conn->query('SELECT * FROM academic_years ORDER BY start_date DESC, id DESC')->fetch_all(MYSQLI_ASSOC);
$programs = $conn->query(
    'SELECT sp.*, ay.id AS academic_year_id, ay.label AS year_label, ay.term AS year_term
     FROM scholarship_programs sp
     JOIN academic_years ay ON ay.id = sp.academic_year_id
     ORDER BY sp.id DESC'
)->fetch_all(MYSQLI_ASSOC);
$requirements = $conn->query(
    'SELECT r.*, sp.name AS program_name
     FROM requirements r
     JOIN scholarship_programs sp ON sp.id = r.scholarship_program_id
    ORDER BY r.id ASC'
)->fetch_all(MYSQLI_ASSOC);

$selectedAcademicYearId = (int) ($_POST['academic_year_id'] ?? 0);
$selectedProgramId = (int) ($_POST['scholarship_program_id'] ?? 0);
$selectedRequirements = [];

if ($selectedProgramId > 0) {
    $stmt = $conn->prepare(
        'SELECT r.*
         FROM requirements r
         WHERE r.scholarship_program_id = ?
         ORDER BY r.id ASC'
    );
    $stmt->bind_param('i', $selectedProgramId);
    $stmt->execute();
    $selectedRequirements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply'])) {
    if ($selectedAcademicYearId <= 0 || $selectedProgramId <= 0) {
        $pageError = 'Please choose an academic year and a scholarship program.';
    } else {
        $check = $conn->prepare('SELECT COUNT(*) AS n FROM scholarship_programs WHERE id = ? AND academic_year_id = ?');
        $check->bind_param('ii', $selectedProgramId, $selectedAcademicYearId);
        $check->execute();
        $valid = (int) $check->get_result()->fetch_assoc()['n'];
        $check->close();

        if ($valid === 0) {
            $pageError = 'The selected program does not belong to the selected academic year.';
        } else {
            $conn->query(
              'CREATE TABLE IF NOT EXISTS applications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                scholarship_program_id INT NOT NULL,
                applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status ENUM(\'For Review\',\'Pending\',\'Accepted\',\'Rejected\') NOT NULL DEFAULT \'For Review\',
                statement TEXT NULL,
                  school_id VARCHAR(100) NULL,
                  course VARCHAR(150) NULL,
                  student_year VARCHAR(50) NULL,
                  full_name VARCHAR(150) NULL,
                  birth_date DATE NULL,
                  address TEXT NULL,
                  contact_number VARCHAR(50) NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (scholarship_program_id) REFERENCES scholarship_programs(id) ON DELETE CASCADE
              ) ENGINE=InnoDB'
            );
            // ensure new columns exist for existing installations
            $colRes = $conn->query("SHOW COLUMNS FROM applications LIKE 'statement'");
            if ($colRes && $colRes->num_rows === 0) {
              $conn->query("ALTER TABLE applications ADD COLUMN statement TEXT NULL");
            }
            $colRes = $conn->query("SHOW COLUMNS FROM applications LIKE 'school_id'");
            if ($colRes && $colRes->num_rows === 0) {
              $conn->query("ALTER TABLE applications ADD COLUMN school_id VARCHAR(100) NULL");
            }
            $colRes = $conn->query("SHOW COLUMNS FROM applications LIKE 'course'");
            if ($colRes && $colRes->num_rows === 0) {
              $conn->query("ALTER TABLE applications ADD COLUMN course VARCHAR(150) NULL");
            }
            $colRes = $conn->query("SHOW COLUMNS FROM applications LIKE 'student_year'");
            if ($colRes && $colRes->num_rows === 0) {
              $conn->query("ALTER TABLE applications ADD COLUMN student_year VARCHAR(50) NULL");
            }
            $colRes = $conn->query("SHOW COLUMNS FROM applications LIKE 'full_name'");
            if ($colRes && $colRes->num_rows === 0) {
              $conn->query("ALTER TABLE applications ADD COLUMN full_name VARCHAR(150) NULL");
            }
            $colRes = $conn->query("SHOW COLUMNS FROM applications LIKE 'birth_date'");
            if ($colRes && $colRes->num_rows === 0) {
              $conn->query("ALTER TABLE applications ADD COLUMN birth_date DATE NULL");
            }
            $colRes = $conn->query("SHOW COLUMNS FROM applications LIKE 'address'");
            if ($colRes && $colRes->num_rows === 0) {
              $conn->query("ALTER TABLE applications ADD COLUMN address TEXT NULL");
            }
            $colRes = $conn->query("SHOW COLUMNS FROM applications LIKE 'contact_number'");
            if ($colRes && $colRes->num_rows === 0) {
              $conn->query("ALTER TABLE applications ADD COLUMN contact_number VARCHAR(50) NULL");
            }

            $checkApply = $conn->prepare('SELECT COUNT(*) AS n FROM applications WHERE user_id = ? AND scholarship_program_id = ?');
            $checkApply->bind_param('ii', $_SESSION['user_id'], $selectedProgramId);
            $checkApply->execute();
            $alreadyApplied = (int) $checkApply->get_result()->fetch_assoc()['n'];
            $checkApply->close();

            if ($alreadyApplied > 0) {
                $pageError = 'You have already applied to this scholarship program.';
            } else {
                $insert = $conn->prepare('INSERT INTO applications (user_id, scholarship_program_id, status, school_id, course, student_year, full_name, birth_date, address, contact_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $initialStatus = 'For Review';
                $schoolIdVal = trim($_POST['school_id'] ?? '');
                $courseVal = trim($_POST['course'] ?? '');
                $studentYearVal = trim($_POST['student_year'] ?? '');
                $fullNameVal = trim($_POST['full_name'] ?? '');
                $birthDateVal = trim($_POST['birth_date'] ?? '');
                $addressVal = trim($_POST['address'] ?? '');
                $contactNumberVal = trim($_POST['contact_number'] ?? '');
                $insert->bind_param('iissssssss', $_SESSION['user_id'], $selectedProgramId, $initialStatus, $schoolIdVal, $courseVal, $studentYearVal, $fullNameVal, $birthDateVal, $addressVal, $contactNumberVal);
                $insert->execute();
                $appId = $conn->insert_id;
                $insert->close();

                // create documents table if missing
                $conn->query(
                  'CREATE TABLE IF NOT EXISTS application_documents (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    application_id INT NOT NULL,
                    requirement_id INT NULL,
                    filename VARCHAR(255) NOT NULL,
                    original_name VARCHAR(255) NULL,
                    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
                  ) ENGINE=InnoDB'
                );

                // handle uploaded requirement files
                if (!empty($_FILES['requirements'])) {
                    $uploadDir = __DIR__ . '/uploads/applications/' . $appId;
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

                    foreach ($_FILES['requirements']['error'] as $reqId => $err) {
                        if ($err !== UPLOAD_ERR_OK) continue;
                        $tmp = $_FILES['requirements']['tmp_name'][$reqId];
                        $orig = basename($_FILES['requirements']['name'][$reqId]);
                        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                        $allowed = ['pdf','jpg','jpeg','png'];
                        if (!in_array($ext, $allowed)) continue;
                        if ($_FILES['requirements']['size'][$reqId] > 5 * 1024 * 1024) continue;
                        $safeName = preg_replace('/[^A-Za-z0-9_.-]/', '_', time() . '_' . $orig);
                        $dest = $uploadDir . '/' . $safeName;
                        if (move_uploaded_file($tmp, $dest)) {
                            $insDoc = $conn->prepare('INSERT INTO application_documents (application_id, requirement_id, filename, original_name) VALUES (?, ?, ?, ?)');
                            $insDoc->bind_param('iiss', $appId, $reqId, $safeName, $orig);
                            $insDoc->execute();
                            $insDoc->close();
                        }
                    }
                }

                // also accept bulk uploads from the single file picker (requirements_bulk[])
                if (!empty($_FILES['requirements_bulk'])) {
                  $uploadDir = __DIR__ . '/uploads/applications/' . $appId;
                  if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

                  $names = $_FILES['requirements_bulk']['name'];
                  $tmps = $_FILES['requirements_bulk']['tmp_name'];
                  $errs = $_FILES['requirements_bulk']['error'];
                  $sizes = $_FILES['requirements_bulk']['size'];
                  $count = count($names ?: []);
                  $allowed = ['pdf','jpg','jpeg','png'];
                  for ($i = 0; $i < $count && $i < 3; $i++) {
                    if ($errs[$i] !== UPLOAD_ERR_OK) continue;
                    $orig = basename($names[$i]);
                    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowed)) continue;
                    if ($sizes[$i] > 5 * 1024 * 1024) continue;
                    $safeName = preg_replace('/[^A-Za-z0-9_.-]/', '_', time() . '_' . $orig);
                    $dest = $uploadDir . '/' . $safeName;
                    if (move_uploaded_file($tmps[$i], $dest)) {
                      $insDoc = $conn->prepare('INSERT INTO application_documents (application_id, requirement_id, filename, original_name) VALUES (?, NULL, ?, ?)');
                      $insDoc->bind_param('iss', $appId, $safeName, $orig);
                      $insDoc->execute();
                      $insDoc->close();
                    }
                  }
                }

                $_SESSION['flash_success'] = 'Your application has been submitted successfully.';
                header('Location: student_applications.php');
                exit;
            }
        }
    }
}

require_once 'includes/header.php';
?>

<div class="panel-head">
  <div>
    <span class="eyebrow">Student Dashboard</span>
    <h2>Scholarship Application</h2>
  </div>
  <span class="count-chip"><?= count($programs) ?> program<?= count($programs) !== 1 ? 's' : '' ?></span>
</div>

<div class="card">
  <h3>Apply for a Scholarship</h3>
  <?php if ($pageMessage): ?>
    <div class="flash flash-success"><?= htmlspecialchars($pageMessage) ?></div>
  <?php endif; ?>
  <?php if ($pageError): ?>
    <div class="flash flash-error"><?= htmlspecialchars($pageError) ?></div>
  <?php endif; ?>

  <p class="application-intro">Start a new scholarship application. Your program details and supporting documents will be collected in the application form.</p>
  <div class="form-actions">
    <button type="button" id="openApplyModal" class="btn btn-primary">Start Application</button>
  </div>
</div>

<!-- Apply Modal -->
<div id="applyModal" class="dialog-backdrop" style="display:none;">
  <form method="POST" action="student_dashboard.php" id="applyForm" class="dialog-panel application-dialog" enctype="multipart/form-data" role="dialog" aria-modal="true" aria-labelledby="applyDialogTitle">
    <input type="hidden" name="apply" value="1" id="apply_hidden">
    <input type="hidden" name="school_id" id="school_id_input">
    <input type="hidden" name="course" id="course_input">
    <input type="hidden" name="student_year" id="student_year_input">
    <input type="hidden" name="full_name" id="full_name_input">
    <input type="hidden" name="birth_date" id="birth_date_input">
    <input type="hidden" name="address" id="address_input">
    <input type="hidden" name="contact_number" id="contact_number_input">
    <input type="file" id="requirements_bulk_input" name="requirements_bulk[]" multiple class="visually-hidden-file" accept=".pdf,image/png,image/jpeg" />
  <div id="applyModalPanel" class="application-dialog-content">
    <div class="dialog-heading">
      <div>
        <span class="eyebrow">Scholarship application</span>
        <h2 id="applyDialogTitle">Tell us about yourself</h2>
        <p>Complete your student details and attach the requested documents.</p>
      </div>
      <button type="button" id="cancelApplyTop" class="dialog-close" aria-label="Close application">&times;</button>
    </div>
    <div class="application-form-content">
      <section class="application-section">
        <h3><span>01</span> Scholarship selection</h3>
        <div class="form-grid">
          <div class="field">
            <label for="academic_year_id">Academic Year</label>
            <select id="academic_year_id" name="academic_year_id" required>
              <option value="">Choose academic year</option>
              <?php foreach ($years as $year): ?>
                <option value="<?= $year['id'] ?>" <?= $year['id'] === $selectedAcademicYearId ? 'selected' : '' ?>><?= htmlspecialchars($year['label']) ?> — <?= htmlspecialchars($year['term']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="scholarship_program_id">Scholarship Program</label>
            <select id="scholarship_program_id" name="scholarship_program_id" required>
              <option value="">Choose scholarship program</option>
              <?php foreach ($programs as $program): ?>
                <option value="<?= $program['id'] ?>" data-year="<?= $program['academic_year_id'] ?>" <?= $program['id'] === $selectedProgramId ? 'selected' : '' ?>><?= htmlspecialchars($program['name']) ?> (<?= htmlspecialchars($program['year_label']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </section>
      <section class="application-section">
        <h3><span>02</span> Student information</h3>
        <div class="form-grid">
          <div class="field"><label for="full_name_field">Full Name <span class="required-mark">Required</span></label><input type="text" id="full_name_field" required /></div>
          <div class="field"><label for="birth_date_field">Birth Date <span class="required-mark">Required</span></label><input type="date" id="birth_date_field" required /></div>
          <div class="field"><label for="contact_number_field">Contact Number <span class="required-mark">Required</span></label><input type="tel" id="contact_number_field" required /></div>
          <div class="field"><label for="school_id_field">School ID <span class="required-mark">Required</span></label><input type="text" id="school_id_field" required /></div>
          <div class="field"><label for="course_field">Course / Program <span class="required-mark">Required</span></label><input type="text" id="course_field" required /></div>
          <div class="field"><label for="student_year_field">Year Level <span class="required-mark">Required</span></label>
            <select id="student_year_field" required><option value="">Choose year level</option><option value="1">1st Year</option><option value="2">2nd Year</option><option value="3">3rd Year</option><option value="4">4th Year</option><option value="5">5th Year / Graduate</option></select>
          </div>
          <div class="field full"><label for="address_field">Address <span class="required-mark">Required</span></label><textarea id="address_field" rows="3" required></textarea></div>
        </div>
      </section>
      <section class="application-section">
        <h3><span>03</span> Scholarship requirements</h3>
        <p class="form-note requirements-intro">Requirements shown here are set for the selected scholarship program.</p>
        <div id="program_requirements_list" class="program-requirements-list" aria-live="polite">
          <p class="form-note">Choose a scholarship program to view its requirements.</p>
        </div>
      </section>
      <section class="application-section documents-section">
        <h3><span>04</span> Upload supporting documents</h3>
        <div id="requirements_files" class="requirement-upload-list"></div>
        <div class="extra-upload">
          <div><strong>Additional files</strong><p>Optional supporting documents, up to 3 files.</p></div>
          <label for="requirements_bulk_input" id="submitRequirementsBtn" class="file-picker-button"><span aria-hidden="true">+</span> Choose files</label>
          <p id="requirementsStatus" class="upload-status" aria-live="polite"></p>
          <div id="bulk_files_list" class="selected-files-list"></div>
        </div>
        <p class="form-note">PDF, JPG, or PNG. Maximum 5MB per file.</p>
      </section>
    </div>
    <div class="dialog-actions">
      <button type="button" id="cancelApply" class="btn btn-cancel">Cancel</button>
      <button type="button" id="submitApply" class="btn btn-primary">Submit Application</button>
    </div>
  </div>
  </form>
</div>

<?php require_once 'includes/footer.php'; ?>
<script>
  const yearSelect = document.getElementById('academic_year_id');
  const programSelect = document.getElementById('scholarship_program_id');
  const requirements = <?= json_encode($selectedRequirements, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const allRequirements = <?= json_encode($requirements, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

  function filterPrograms() {
    const selectedYear = yearSelect.value;
    Array.from(programSelect.options).forEach(option => {
      if (!option.value) return;
      option.hidden = option.dataset.year !== selectedYear;
    });
    const visibleOptions = Array.from(programSelect.options).filter(option => !option.hidden);
    if (!visibleOptions.some(option => option.selected)) {
      if (visibleOptions.length) {
        visibleOptions[0].selected = true;
      } else {
        programSelect.value = '';
      }
    }
  }

  function resetUploads() {
    const bulk = document.getElementById('requirements_bulk_input');
    if (bulk) {
      bulkBuffer = new DataTransfer();
      try { bulk.files = bulkBuffer.files; } catch (e) { bulk.value = ''; }
    }
    const container = document.getElementById('requirements_files');
    if (container) container.innerHTML = '';
    const summary = document.getElementById('program_requirements_list');
    if (summary) summary.innerHTML = '';
    const bulkFiles = document.getElementById('bulk_files_list');
    if (bulkFiles) bulkFiles.innerHTML = '';
    const status = document.getElementById('requirementsStatus');
    if (status) status.textContent = '';
    requirementsReady = false;
  }

  yearSelect.addEventListener('change', () => {
    filterPrograms();
    populateRequirementInputs();
  });
  programSelect.addEventListener('change', populateRequirementInputs);
  document.addEventListener('DOMContentLoaded', filterPrograms);
  
  // Modal apply flow
  const openApplyModalBtn = document.getElementById('openApplyModal');
  const applyModal = document.getElementById('applyModal');
  const cancelApplyBtn = document.getElementById('cancelApply');
  const cancelApplyTopBtn = document.getElementById('cancelApplyTop');
  const submitApplyBtn = document.getElementById('submitApply');
  const fullNameField = document.getElementById('full_name_field');
  const birthDateField = document.getElementById('birth_date_field');
  const contactNumberField = document.getElementById('contact_number_field');
  const addressField = document.getElementById('address_field');
  const schoolIdField = document.getElementById('school_id_field');
  const courseField = document.getElementById('course_field');
  const studentYearField = document.getElementById('student_year_field');

  const fullNameInput = document.getElementById('full_name_input');
  const birthDateInput = document.getElementById('birth_date_input');
  const addressInput = document.getElementById('address_input');
  const contactNumberInput = document.getElementById('contact_number_input');
  const schoolIdInput = document.getElementById('school_id_input');
  const courseInput = document.getElementById('course_input');
  const studentYearInput = document.getElementById('student_year_input');
  const applyForm = document.getElementById('applyForm');

  function showModal() {
    applyModal.style.display = 'flex';
    if (yearSelect) yearSelect.focus();
  }

  function hideModal() {
    applyModal.style.display = 'none';
    if (openApplyModalBtn) openApplyModalBtn.focus();
  }

  openApplyModalBtn.addEventListener('click', () => {
    populateRequirementInputs();
    showModal();
  });

  cancelApplyBtn.addEventListener('click', () => {
    resetUploads();
    hideModal();
  });
  cancelApplyTopBtn.addEventListener('click', () => {
    resetUploads();
    hideModal();
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && applyModal.style.display === 'flex') {
      resetUploads();
      hideModal();
    }
  });

  submitApplyBtn.addEventListener('click', () => {
    if (!yearSelect.value || !programSelect.value) {
      alert('Please choose an academic year and scholarship program.');
      (!yearSelect.value ? yearSelect : programSelect).focus();
      return;
    }
    const fullNameVal = (fullNameField && fullNameField.value || '').trim();
    const birthVal = (birthDateField && birthDateField.value || '').trim();
    const contactVal = (contactNumberField && contactNumberField.value || '').trim();
    const addressVal = (addressField && addressField.value || '').trim();
    const schoolVal = (schoolIdField && schoolIdField.value || '').trim();
    const courseVal = (courseField && courseField.value || '').trim();
    const yearVal = (studentYearField && studentYearField.value || '').trim();

    if (!fullNameVal) {
      alert('Please enter your Full Name.');
      fullNameField.focus();
      return;
    }
    if (!birthVal) {
      alert('Please enter your Birth Date.');
      birthDateField.focus();
      return;
    }
    if (!contactVal) {
      alert('Please enter your Contact Number.');
      contactNumberField.focus();
      return;
    }
    if (!addressVal) {
      alert('Please enter your Address.');
      addressField.focus();
      return;
    }
    if (!schoolVal) {
      alert('Please enter your School ID.');
      schoolIdField.focus();
      return;
    }
    if (!courseVal) {
      alert('Please enter your Course/Program.');
      courseField.focus();
      return;
    }
    if (!yearVal) {
      alert('Please select your Year Level.');
      studentYearField.focus();
      return;
    }

    fullNameInput.value = fullNameVal;
    birthDateInput.value = birthVal;
    addressInput.value = addressVal;
    contactNumberInput.value = contactVal;
    schoolIdInput.value = schoolVal;
    courseInput.value = courseVal;
    studentYearInput.value = yearVal;

    // ensure requirement files validated before submitting
    if (!validateRequirementFiles()) return;

    hideModal();
    applyForm.submit();
  });

  // Build file inputs for the selected program's requirements
  function populateRequirementInputs() {
    const container = document.getElementById('requirements_files');
    const summary = document.getElementById('program_requirements_list');
    container.innerHTML = '';
    summary.innerHTML = '';
    const pid = parseInt(programSelect.value || 0, 10);
    if (!pid) {
      summary.textContent = 'Choose a scholarship program to view its requirements.';
      summary.className = 'program-requirements-list empty';
      return;
    }
    const reqs = allRequirements.filter(r => parseInt(r.scholarship_program_id, 10) === pid);
    if (!reqs.length) {
      summary.textContent = 'No specific requirements are listed for this scholarship program.';
      summary.className = 'program-requirements-list empty';
      container.textContent = 'No document uploads are required for this program.';
      return;
    }
    summary.className = 'program-requirements-list';
    reqs.forEach(r => {
      const summaryItem = document.createElement('article');
      summaryItem.className = 'program-requirement-item';
      const summaryHeading = document.createElement('div');
      summaryHeading.className = 'program-requirement-heading';
      const summaryName = document.createElement('strong');
      summaryName.textContent = r.document_name;
      const requirementStatus = document.createElement('span');
      requirementStatus.className = r.mandatory == 1 ? 'requirement-status mandatory' : 'requirement-status optional';
      requirementStatus.textContent = r.mandatory == 1 ? 'Mandatory' : 'Optional';
      summaryHeading.appendChild(summaryName);
      summaryHeading.appendChild(requirementStatus);
      summaryItem.appendChild(summaryHeading);
      if (r.notes) {
        const notes = document.createElement('p');
        notes.textContent = r.notes;
        summaryItem.appendChild(notes);
      }
      summary.appendChild(summaryItem);

      const wrap = document.createElement('div');
      wrap.className = 'requirement-upload-item';
      const label = document.createElement('label');
      label.className = 'requirement-name';
      label.textContent = r.document_name;
      const input = document.createElement('input');
      input.id = `requirement_file_${r.id}`;
      input.type = 'file';
      input.name = `requirements[${r.id}]`;
      input.accept = '.pdf,image/png,image/jpeg';
      input.className = 'visually-hidden-file';
      input.dataset.label = r.document_name;
      if (r.mandatory == 1) input.required = true;
      const picker = document.createElement('label');
      picker.className = 'requirement-picker-button';
      picker.htmlFor = input.id;
      picker.textContent = 'Choose file';
      const selectedName = document.createElement('span');
      selectedName.className = 'requirement-file-name';
      selectedName.textContent = 'No file selected';
      input.addEventListener('change', () => {
        selectedName.textContent = input.files.length ? input.files[0].name : 'No file selected';
        selectedName.classList.toggle('has-file', input.files.length > 0);
      });
      wrap.appendChild(label);
      wrap.appendChild(picker);
      wrap.appendChild(input);
      wrap.appendChild(selectedName);
      container.appendChild(wrap);
    });
  }

  // Requirement validation and UI
  const submitRequirementsBtn = document.getElementById('submitRequirementsBtn');
  const requirementsStatus = document.getElementById('requirementsStatus');
  let requirementsReady = false;
  let bulkBuffer = null;

  function validateRequirementFiles() {
    const container = document.getElementById('requirements_files');
    if (!container) return true;
    const inputs = Array.from(container.querySelectorAll('input[type=file]'));
    for (const inp of inputs) {
      if (inp.required && inp.files.length === 0) {
        alert('Please attach the required document: ' + (inp.dataset.label || 'a document'));
        inp.focus();
        return false;
      }
      if (inp.files.length && inp.files[0].size > 5 * 1024 * 1024) {
        alert('File is too large. Max 5MB each.');
        inp.focus();
        return false;
      }
      const allowed = ['pdf','jpg','jpeg','png'];
      if (inp.files.length) {
        const ext = inp.files[0].name.split('.').pop().toLowerCase();
        if (!allowed.includes(ext)) {
          alert('Invalid file type. Allowed: PDF, JPG, PNG.');
          inp.focus();
          return false;
        }
      }
    }
    requirementsReady = true;
    requirementsStatus.textContent = 'All required documents attached';
    return true;
  }

  // handle bulk input change
  const bulkInput = document.getElementById('requirements_bulk_input');
  if (bulkInput) {
    // buffer to hold appended files across multiple selections
    bulkBuffer = new DataTransfer();
    bulkInput.addEventListener('change', () => {
      const newFiles = Array.from(bulkInput.files || []);
      const allowed = ['pdf','jpg','jpeg','png'];
      if (newFiles.length === 0) return;

      if (bulkBuffer.files.length + newFiles.length > 3) {
        alert('You can attach up to 3 additional files.');
        bulkInput.value = '';
        return;
      }
      for (const f of newFiles) {
        if (f.size > 5 * 1024 * 1024) { alert('One of the selected files is too large (max 5MB).'); bulkInput.value = ''; return; }
        const ext = f.name.split('.').pop().toLowerCase();
        if (!allowed.includes(ext)) { alert('Invalid file type selected. Allowed: PDF, JPG, PNG.'); bulkInput.value = ''; return; }
      }
      for (const f of newFiles) {
        bulkBuffer.items.add(f);
      }

      // assign buffered files back to the input so server receives them
      bulkInput.files = bulkBuffer.files;

      // Show the chosen supporting files without replacing required-document inputs.
      const files = Array.from(bulkBuffer.files || []);
      const container = document.getElementById('bulk_files_list');
      container.innerHTML = '';
      files.forEach(f => {
        const name = document.createElement('span');
        name.className = 'selected-file-chip';
        name.textContent = f.name;
        container.appendChild(name);
      });

      requirementsStatus.textContent = files.length ? files.length + ' file(s) attached' : '';
      requirementsReady = true;
    });
  }
</script>

