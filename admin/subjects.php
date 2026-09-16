<?php
/**
 * Class Routine Management System (NasimSoft)
 * Master Subject Catalog & Load Definition
 * Copyright (c) 2026 NasimSoft.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/csv-tools.php';

$db = Database::getInstance();
$editId = (int)($_GET['edit'] ?? 0);
$deleteId = (int)($_GET['delete'] ?? 0);

// CSV Template Download
if (($_GET['template'] ?? '') === 'csv') {
    csvDownload('subjects-template.csv',
        ['subject_code', 'name', 'subject_type', 'department_code', 'theory_load', 'practical_load', 'credit', 'active'],
        [['28571', 'Digital Marketing Technique', 'Theory', 'CST', '2', '0', '2.0', '1']]
    );
}

// CSV Export
if (($_GET['export'] ?? '') === 'csv') {
    $rows = $db->query("SELECT s.subject_code, s.name, s.subject_type, d.code as department_code, s.theory_load, s.practical_load, s.credit, s.active FROM subjects s LEFT JOIN departments d ON s.department_id = d.id ORDER BY s.subject_code ASC")->fetchAll(PDO::FETCH_NUM);
    csvDownload('subjects-export-' . date('Y-m-d') . '.csv',
        ['subject_code', 'name', 'subject_type', 'department_code', 'theory_load', 'practical_load', 'credit', 'active'],
        $rows
    );
}

// CSV Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'csv_import') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Security token expired. Please try again.');
    } else {
        try {
            $rows = csvParseUpload($_FILES['csv_file'] ?? []);
            $deptMap = [];
            foreach ($db->query("SELECT id, code FROM departments") as $d) {
                $deptMap[strtoupper($d['code'])] = (int)$d['id'];
            }

            $created = 0; $updated = 0; $errors = [];
            foreach ($rows as $i => $row) {
                $line = $i + 2; // account for header row
                $code = trim($row['subject_code'] ?? '');
                $name = trim($row['name'] ?? '');
                $deptCode = strtoupper(trim($row['department_code'] ?? ''));

                if ($code === '' || $name === '') {
                    $errors[] = "Line {$line}: subject_code and name are required";
                    continue;
                }
                if (!isset($deptMap[$deptCode])) {
                    $errors[] = "Line {$line}: unknown department_code '{$deptCode}'";
                    continue;
                }

                $type = in_array($row['subject_type'] ?? '', ['Theory', 'Lab', 'Practical', 'Project'], true) ? $row['subject_type'] : 'Theory';
                $theoryLoad = max(0, (int)($row['theory_load'] ?? 0));
                $practicalLoad = max(0, (int)($row['practical_load'] ?? 0));
                $credit = max(0.5, (float)($row['credit'] ?? 3.0));
                $active = isset($row['active']) && $row['active'] !== '' ? (int)(bool)(int)$row['active'] : 1;

                $existing = $db->prepare("SELECT id FROM subjects WHERE subject_code = ?");
                $existing->execute([$code]);
                $existingId = $existing->fetchColumn();

                if ($existingId) {
                    $stmt = $db->prepare("UPDATE subjects SET name=?, subject_type=?, theory_load=?, practical_load=?, total_load=?, credit=?, department_id=?, active=? WHERE id=?");
                    $stmt->execute([$name, $type, $theoryLoad, $practicalLoad, $theoryLoad + $practicalLoad, $credit, $deptMap[$deptCode], $active, $existingId]);
                    $updated++;
                } else {
                    $stmt = $db->prepare("INSERT INTO subjects (name, subject_code, subject_type, theory_load, practical_load, total_load, credit, department_id, active) VALUES (?,?,?,?,?,?,?,?,?)");
                    $stmt->execute([$name, $code, $type, $theoryLoad, $practicalLoad, $theoryLoad + $practicalLoad, $credit, $deptMap[$deptCode], $active]);
                    $created++;
                }
            }
            logAudit('Bulk Import Subjects', 'Subjects', 0, "Created {$created}, updated {$updated}, " . count($errors) . ' errors');
            csvImportSummaryFlash($created, $updated, $errors);
        } catch (Exception $e) {
            setFlash('error', $e->getMessage());
        }
        header('Location: subjects.php');
        exit;
    }
}

// Delete Action
if ($deleteId > 0 && verifyCsrfToken($_GET['csrf_token'] ?? '')) {
    $stmt = $db->prepare("DELETE FROM subjects WHERE id = ?");
    $stmt->execute([$deleteId]);
    logAudit('Delete Subject', 'Subjects', $deleteId, 'Deleted master subject');
    setFlash('success', 'Subject deleted successfully.');
    header('Location: subjects.php');
    exit;
}

// Add or Update Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Security session expired. Please try again.');
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $subjectCode = trim($_POST['subject_code'] ?? '');
        $subjectType = in_array($_POST['subject_type'] ?? '', ['Theory', 'Lab', 'Practical', 'Project'], true) ? $_POST['subject_type'] : 'Theory';
        $theoryLoad = max(0, (int)($_POST['theory_load'] ?? 0));
        $practicalLoad = max(0, (int)($_POST['practical_load'] ?? 0));
        $totalLoad = $theoryLoad + $practicalLoad;
        $credit = max(0.5, (float)($_POST['credit'] ?? 3.0));
        $departmentId = (int)($_POST['department_id'] ?? 0);
        $active = isset($_POST['active']) ? 1 : 0;

        if (empty($name) || empty($subjectCode) || $departmentId <= 0) {
            setFlash('error', 'Subject Name, Subject Code, and Department are required.');
        } else {
            // Check for duplicate subject code
            $dupCheck = $db->prepare("SELECT id FROM subjects WHERE subject_code = ? AND id != ?");
            $dupCheck->execute([$subjectCode, $id]);
            if ($dupCheck->fetch()) {
                setFlash('error', "Subject code '{$subjectCode}' already exists in catalog.");
            } else {
                if ($id > 0) {
                    $stmt = $db->prepare("UPDATE subjects SET name = ?, subject_code = ?, subject_type = ?, theory_load = ?, practical_load = ?, total_load = ?, credit = ?, department_id = ?, active = ? WHERE id = ?");
                    $stmt->execute([$name, $subjectCode, $subjectType, $theoryLoad, $practicalLoad, $totalLoad, $credit, $departmentId, $active, $id]);
                    logAudit('Update Subject', 'Subjects', $id, "Updated subject {$name} ({$subjectCode})");
                    setFlash('success', 'Subject updated successfully.');
                } else {
                    $stmt = $db->prepare("INSERT INTO subjects (name, subject_code, subject_type, theory_load, practical_load, total_load, credit, department_id, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $subjectCode, $subjectType, $theoryLoad, $practicalLoad, $totalLoad, $credit, $departmentId, $active]);
                    $newId = (int)$db->lastInsertId();
                    logAudit('Create Subject', 'Subjects', $newId, "Created subject {$name} ({$subjectCode})");
                    setFlash('success', 'Subject added to catalog successfully.');
                }
                header('Location: subjects.php');
                exit;
            }
        }
    }
}

$editSubject = null;
if ($editId > 0) {
    $stmt = $db->prepare("SELECT * FROM subjects WHERE id = ?");
    $stmt->execute([$editId]);
    $editSubject = $stmt->fetch();
}

$departments = $db->query("SELECT id, name, code FROM departments WHERE active = 1 ORDER BY name ASC")->fetchAll();
$subjects = $db->query("
    SELECT s.*, d.name as department_name, d.code as department_code,
           (SELECT COUNT(*) FROM group_subjects WHERE subject_id = s.id) as assigned_groups,
           (SELECT COUNT(*) FROM routines WHERE subject_id = s.id) as routine_slots
    FROM subjects s
    LEFT JOIN departments d ON s.department_id = d.id
    ORDER BY s.id DESC
")->fetchAll();
$pageTitle = 'Subject Management';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="color: var(--navy-blue); font-size: 20px; font-weight: 700;">Curriculum Subjects & Load Structure</h2>
        <p style="color: var(--text-muted); font-size: 13px;">Manage academic subjects, codes, theory/practical loads, and credit ratings.</p>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="group-subjects.php" class="btn btn-primary">&#128214; Group Subject Setup</a>
        <button class="btn btn-outline" onclick="App.openModal('csvModal')">&#128228; Import / Export</button>
        <button class="btn btn-accent" onclick="App.openModal('subjectModal')">&#10133; Add Subject</button>
    </div>
</div>

<!-- CSV Import/Export Modal -->
<div class="modal-overlay" id="csvModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h4>&#128228; Bulk Import / Export Subjects</h4>
            <button class="modal-close" onclick="App.closeModal('csvModal')">&times;</button>
        </div>
        <div class="modal-body">
            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 14px;">
                Update many subjects at once with a CSV file. Matching is done by <strong>subject_code</strong> —
                existing codes are updated, new codes are created.
            </p>
            <div style="display:flex; gap:10px; margin-bottom:18px;">
                <a href="subjects.php?export=csv" class="btn btn-outline btn-sm">&#11015; Export Current Data</a>
                <a href="subjects.php?template=csv" class="btn btn-outline btn-sm">&#128196; Download Template</a>
            </div>
            <form method="POST" action="subjects.php" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="form" value="csv_import">
                <div class="form-group">
                    <label class="form-label">Choose CSV File *</label>
                    <input type="file" name="csv_file" accept=".csv" class="form-control" required>
                    <span class="form-hint">Columns: subject_code, name, subject_type, department_code, theory_load, practical_load, credit, active</span>
                </div>
                <button type="submit" class="btn btn-accent" style="width:100%;">&#128228; Upload &amp; Import</button>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>&#128218; Subject Catalog</h3>
        <input type="text" id="subjectSearch" class="form-control" placeholder="Search subject name or code..." style="max-width: 250px; padding: 6px 12px; font-size: 13px;">
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" id="subjectTable">
                <thead>
                    <tr>
                        <th style="width: 50px;">SL</th>
                        <th>Subject Code</th>
                        <th>Subject Name</th>
                        <th>Type</th>
                        <th>Department</th>
                        <th>Theory Load</th>
                        <th>Practical Load</th>
                        <th>Total Load</th>
                        <th>Credit</th>
                        <th>Groups</th>
                        <th>Status</th>
                        <th style="width: 130px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subjects)): ?>
                        <tr>
                            <td colspan="12" style="text-align: center; padding: 25px; color: var(--text-muted);">No subjects found in catalog.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($subjects as $idx => $s): ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td><strong style="color: var(--navy-blue); font-size: 13.5px;"><?= htmlspecialchars($s['subject_code']) ?></strong></td>
                                <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                                <td>
                                    <span class="badge <?= $s['subject_type'] === 'Lab' || $s['subject_type'] === 'Practical' ? 'badge-accent' : 'badge-primary' ?>">
                                        <?= htmlspecialchars($s['subject_type']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($s['department_code'] ?? '') ?></td>
                                <td><?= $s['theory_load'] ?> (1p = 1L)</td>
                                <td><?= $s['practical_load'] ?> (2p = 2L)</td>
                                <td><strong style="color: var(--dark-orange); font-size: 14px;"><?= $s['total_load'] ?></strong></td>
                                <td><span class="badge badge-info"><?= number_format((float)$s['credit'], 1) ?></span></td>
                                <td><span class="badge badge-primary"><?= $s['assigned_groups'] ?> grps</span></td>
                                <td>
                                    <span class="badge <?= $s['active'] ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $s['active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <a href="subjects.php?edit=<?= $s['id'] ?>" class="btn btn-outline btn-sm">&#9998; Edit</a>
                                    <button class="btn btn-danger btn-sm" onclick="App.confirm('Are you sure you want to delete this subject?', () => { window.location.href='subjects.php?delete=<?= $s['id'] ?>&csrf_token=<?= generateCsrfToken() ?>'; })">&#128465;</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Subject Modal -->
<div class="modal-overlay <?= $editSubject ? 'active' : '' ?>" id="subjectModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h4><?= $editSubject ? 'Edit Subject' : 'Add Subject to Catalog' ?></h4>
            <button class="modal-close" onclick="<?= $editSubject ? "window.location.href='subjects.php'" : "App.closeModal('subjectModal')" ?>">&times;</button>
        </div>
        <form method="POST" action="" id="subjectForm">
            <div class="modal-body">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $editSubject['id'] ?? 0 ?>">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Subject Code *</label>
                        <input type="text" name="subject_code" class="form-control" required value="<?= htmlspecialchars($editSubject['subject_code'] ?? '') ?>" placeholder="e.g. 28571, 28572">
                        <small style="color: var(--text-muted);">Exact numeric code printed on routine.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Subject Classification *</label>
                        <select name="subject_type" id="selSubType" class="form-select" required onchange="updateLoadEstimates()">
                            <option value="Theory" <?= (($editSubject['subject_type'] ?? 'Theory') === 'Theory') ? 'selected' : '' ?>>Theory</option>
                            <option value="Lab" <?= (($editSubject['subject_type'] ?? '') === 'Lab') ? 'selected' : '' ?>>Lab / Practical</option>
                            <option value="Practical" <?= (($editSubject['subject_type'] ?? '') === 'Practical') ? 'selected' : '' ?>>Practical</option>
                            <option value="Project" <?= (($editSubject['subject_type'] ?? '') === 'Project') ? 'selected' : '' ?>>Project Work</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Full Subject Name *</label>
                    <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($editSubject['name'] ?? '') ?>" placeholder="e.g. Network Administration & Services">
                </div>
                <div class="form-group">
                    <label class="form-label">Department *</label>
                    <select name="department_id" class="form-select" required>
                        <option value="">-- Select Department --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>" <?= (($editSubject['department_id'] ?? 0) == $dept['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept['name']) ?> (<?= htmlspecialchars($dept['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid-3">
                    <div class="form-group">
                        <label class="form-label">Theory Load (1 period = 1 load)</label>
                        <input type="number" name="theory_load" id="inpTheoryLoad" class="form-control" min="0" max="10" value="<?= htmlspecialchars((string)($editSubject['theory_load'] ?? 2)) ?>" oninput="calculateTotalLoad()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Practical Load (Lab)</label>
                        <input type="number" name="practical_load" id="inpPracticalLoad" class="form-control" min="0" max="10" value="<?= htmlspecialchars((string)($editSubject['practical_load'] ?? 0)) ?>" oninput="calculateTotalLoad()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Total Load (Calculated)</label>
                        <input type="number" name="total_load" id="inpTotalLoad" class="form-control" readonly style="background: #F1F5F9; font-weight: 700; color: var(--dark-orange);" value="<?= htmlspecialchars((string)($editSubject['total_load'] ?? 2)) ?>">
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Credit Value</label>
                        <input type="number" name="credit" class="form-control" step="0.5" min="0.5" max="10" value="<?= htmlspecialchars((string)($editSubject['credit'] ?? 3.0)) ?>">
                    </div>
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; cursor: pointer; margin-top: 28px;">
                            <input type="checkbox" name="active" value="1" <?= (!isset($editSubject['active']) || $editSubject['active']) ? 'checked' : '' ?>>
                            Subject is Active
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="<?= $editSubject ? "window.location.href='subjects.php'" : "App.closeModal('subjectModal')" ?>">Cancel</button>
                <button type="submit" class="btn btn-accent"><?= $editSubject ? 'Update Subject' : 'Save Subject' ?></button>
            </div>
        </form>
    </div>
</div>

<script>
function calculateTotalLoad() {
    const t = parseInt(document.getElementById('inpTheoryLoad').value) || 0;
    const p = parseInt(document.getElementById('inpPracticalLoad').value) || 0;
    document.getElementById('inpTotalLoad').value = t + p;
}

function updateLoadEstimates() {
    const type = document.getElementById('selSubType').value;
    const tInput = document.getElementById('inpTheoryLoad');
    const pInput = document.getElementById('inpPracticalLoad');
    if (type === 'Lab' || type === 'Practical') {
        if (parseInt(pInput.value) === 0) pInput.value = 2;
    } else if (type === 'Theory') {
        pInput.value = 0;
    }
    calculateTotalLoad();
}

document.addEventListener('DOMContentLoaded', () => {
    App.setupTableSearch('subjectSearch', 'subjectTable');
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>