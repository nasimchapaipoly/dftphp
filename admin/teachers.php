<?php
/**
 * Class Routine Management System (NasimSoft)
 * Teacher Management
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
    csvDownload('teachers-template.csv',
        ['short_code', 'name', 'designation', 'department_code', 'teacher_type', 'phone', 'email', 'max_daily_load', 'active'],
        [['OF', 'MD OMAR FARUK', 'Jr. Instructor (Tech/Computer)', 'CST', 'Jr. Instructor', '01750787890', 'omar@cnpi.edu.bd', '6', '1']]
    );
}

// CSV Export
if (($_GET['export'] ?? '') === 'csv') {
    $rows = $db->query("SELECT t.short_code, t.name, t.designation, d.code as department_code, t.teacher_type, t.phone, t.email, t.max_daily_load, t.active FROM teachers t LEFT JOIN departments d ON t.department_id = d.id ORDER BY t.short_code ASC")->fetchAll(PDO::FETCH_NUM);
    csvDownload('teachers-export-' . date('Y-m-d') . '.csv',
        ['short_code', 'name', 'designation', 'department_code', 'teacher_type', 'phone', 'email', 'max_daily_load', 'active'],
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
                $line = $i + 2;
                $code = strtoupper(trim($row['short_code'] ?? ''));
                $name = trim($row['name'] ?? '');
                $deptCode = strtoupper(trim($row['department_code'] ?? ''));

                if ($code === '' || $name === '') {
                    $errors[] = "Line {$line}: short_code and name are required";
                    continue;
                }
                if (!isset($deptMap[$deptCode])) {
                    $errors[] = "Line {$line}: unknown department_code '{$deptCode}'";
                    continue;
                }

                $designation = trim($row['designation'] ?? '') ?: 'Instructor';
                $teacherType = trim($row['teacher_type'] ?? '') ?: 'Instructor';
                $phone = trim($row['phone'] ?? '');
                $email = trim($row['email'] ?? '');
                $maxLoad = max(1, (int)($row['max_daily_load'] ?? 6));
                $active = isset($row['active']) && $row['active'] !== '' ? (int)(bool)(int)$row['active'] : 1;

                $existing = $db->prepare("SELECT id FROM teachers WHERE short_code = ?");
                $existing->execute([$code]);
                $existingId = $existing->fetchColumn();

                if ($existingId) {
                    $stmt = $db->prepare("UPDATE teachers SET name=?, designation=?, department_id=?, phone=?, email=?, teacher_type=?, max_daily_load=?, active=? WHERE id=?");
                    $stmt->execute([$name, $designation, $deptMap[$deptCode], $phone, $email, $teacherType, $maxLoad, $active, $existingId]);
                    $updated++;
                } else {
                    $stmt = $db->prepare("INSERT INTO teachers (name, short_code, designation, department_id, phone, email, teacher_type, max_daily_load, active) VALUES (?,?,?,?,?,?,?,?,?)");
                    $stmt->execute([$name, $code, $designation, $deptMap[$deptCode], $phone, $email, $teacherType, $maxLoad, $active]);
                    $created++;
                }
            }
            logAudit('Bulk Import Teachers', 'Teachers', 0, "Created {$created}, updated {$updated}, " . count($errors) . ' errors');
            csvImportSummaryFlash($created, $updated, $errors);
        } catch (Exception $e) {
            setFlash('error', $e->getMessage());
        }
        header('Location: teachers.php');
        exit;
    }
}

// Delete Action
if ($deleteId > 0 && verifyCsrfToken($_GET['csrf_token'] ?? '')) {
    $stmt = $db->prepare("DELETE FROM teachers WHERE id = ?");
    $stmt->execute([$deleteId]);
    logAudit('Delete Teacher', 'Teachers', $deleteId, 'Deleted teacher');
    setFlash('success', 'Teacher deleted successfully.');
    header('Location: teachers.php');
    exit;
}

// Add or Update Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Security token expired. Please try again.');
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $shortCode = strtoupper(trim($_POST['short_code'] ?? ''));
        $designation = trim($_POST['designation'] ?? '');
        $departmentId = (int)($_POST['department_id'] ?? 0);
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $teacherType = trim($_POST['teacher_type'] ?? 'Instructor');
        $maxDailyLoad = (int)($_POST['max_daily_load'] ?? 6);
        $active = isset($_POST['active']) ? 1 : 0;

        if (empty($name) || empty($shortCode) || $departmentId <= 0) {
            setFlash('error', 'Teacher Name, Short Code, and Department are required.');
        } else {
            // Check for duplicate short code
            $dupCheck = $db->prepare("SELECT id FROM teachers WHERE short_code = ? AND id != ?");
            $dupCheck->execute([$shortCode, $id]);
            if ($dupCheck->fetch()) {
                setFlash('error', "Teacher short code '{$shortCode}' is already in use.");
            } else {
                if ($id > 0) {
                    $stmt = $db->prepare("UPDATE teachers SET name = ?, short_code = ?, designation = ?, department_id = ?, phone = ?, email = ?, teacher_type = ?, max_daily_load = ?, active = ? WHERE id = ?");
                    $stmt->execute([$name, $shortCode, $designation, $departmentId, $phone, $email, $teacherType, $maxDailyLoad, $active, $id]);
                    logAudit('Update Teacher', 'Teachers', $id, "Updated teacher {$name} ({$shortCode})");
                    setFlash('success', 'Teacher updated successfully.');
                } else {
                    $stmt = $db->prepare("INSERT INTO teachers (name, short_code, designation, department_id, phone, email, teacher_type, max_daily_load, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $shortCode, $designation, $departmentId, $phone, $email, $teacherType, $maxDailyLoad, $active]);
                    $newId = (int)$db->lastInsertId();
                    logAudit('Create Teacher', 'Teachers', $newId, "Created teacher {$name} ({$shortCode})");
                    setFlash('success', 'Teacher registered successfully.');
                }
                header('Location: teachers.php');
                exit;
            }
        }
    }
}

$editTeacher = null;
if ($editId > 0) {
    $stmt = $db->prepare("SELECT * FROM teachers WHERE id = ?");
    $stmt->execute([$editId]);
    $editTeacher = $stmt->fetch();
}

$departments = $db->query("SELECT id, name, code FROM departments WHERE active = 1 ORDER BY name ASC")->fetchAll();
$teachers = $db->query("
    SELECT t.*, d.name as department_name, d.code as department_code,
           (SELECT COUNT(*) FROM group_subjects WHERE teacher_id = t.id) as assigned_subjects,
           (SELECT COUNT(*) FROM routines WHERE teacher_id = t.id) as scheduled_classes
    FROM teachers t
    LEFT JOIN departments d ON t.department_id = d.id
    ORDER BY t.id DESC
")->fetchAll();
$pageTitle = 'Teacher Management';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="color: var(--navy-blue); font-size: 20px; font-weight: 700;">Faculty & Teacher Management</h2>
        <p style="color: var(--text-muted); font-size: 13px;">Manage instructors, short codes (for routine cells), maximum loads, and contact info.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <button class="btn btn-outline" onclick="App.openModal('csvModal')">&#128228; Import / Export</button>
        <button class="btn btn-accent" onclick="App.openModal('teacherModal')">&#10133; Register Teacher</button>
    </div>
</div>

<!-- CSV Import/Export Modal -->
<div class="modal-overlay" id="csvModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h4>&#128228; Bulk Import / Export Teachers</h4>
            <button class="modal-close" onclick="App.closeModal('csvModal')">&times;</button>
        </div>
        <div class="modal-body">
            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 14px;">
                Update many teachers at once with a CSV file. Matching is done by <strong>short_code</strong> —
                existing codes are updated, new codes are created.
            </p>
            <div style="display:flex; gap:10px; margin-bottom:18px;">
                <a href="teachers.php?export=csv" class="btn btn-outline btn-sm">&#11015; Export Current Data</a>
                <a href="teachers.php?template=csv" class="btn btn-outline btn-sm">&#128196; Download Template</a>
            </div>
            <form method="POST" action="teachers.php" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="form" value="csv_import">
                <div class="form-group">
                    <label class="form-label">Choose CSV File *</label>
                    <input type="file" name="csv_file" accept=".csv" class="form-control" required>
                    <span class="form-hint">Columns: short_code, name, designation, department_code, teacher_type, phone, email, max_daily_load, active</span>
                </div>
                <button type="submit" class="btn btn-accent" style="width:100%;">&#128228; Upload &amp; Import</button>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>&#128104;&#8205;&#127979; Teaching Faculty</h3>
        <input type="text" id="teacherSearch" class="form-control" placeholder="Search teacher or code..." style="max-width: 250px; padding: 6px 12px; font-size: 13px;">
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" id="teacherTable">
                <thead>
                    <tr>
                        <th style="width: 50px;">SL</th>
                        <th>Teacher Name</th>
                        <th>Short Code</th>
                        <th>Designation & Type</th>
                        <th>Department</th>
                        <th>Contact</th>
                        <th>Max Load</th>
                        <th>Classes</th>
                        <th>Status</th>
                        <th style="width: 130px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($teachers)): ?>
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 25px; color: var(--text-muted);">No teachers registered yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($teachers as $idx => $t): ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td><strong style="color: var(--navy-blue);"><?= htmlspecialchars($t['name']) ?></strong></td>
                                <td><span class="badge badge-accent" style="font-size: 12.5px;"><?= htmlspecialchars($t['short_code']) ?></span></td>
                                <td>
                                    <div><?= htmlspecialchars($t['designation']) ?></div>
                                    <small class="badge badge-info" style="font-size: 10px;"><?= htmlspecialchars($t['teacher_type']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($t['department_code'] ?? '') ?></td>
                                <td>
                                    <div><?= htmlspecialchars($t['phone'] ?: 'N/A') ?></div>
                                    <small style="color: var(--text-muted);"><?= htmlspecialchars($t['email'] ?: '') ?></small>
                                </td>
                                <td><?= $t['max_daily_load'] ?> / day</td>
                                <td><span class="badge badge-primary"><?= $t['scheduled_classes'] ?> slots</span></td>
                                <td>
                                    <span class="badge <?= $t['active'] ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $t['active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <a href="teachers.php?edit=<?= $t['id'] ?>" class="btn btn-outline btn-sm">&#9998; Edit</a>
                                    <button class="btn btn-danger btn-sm" onclick="App.confirm('Are you sure you want to delete this teacher?', () => { window.location.href='teachers.php?delete=<?= $t['id'] ?>&csrf_token=<?= generateCsrfToken() ?>'; })">&#128465;</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Teacher Modal -->
<div class="modal-overlay <?= $editTeacher ? 'active' : '' ?>" id="teacherModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h4><?= $editTeacher ? 'Edit Teacher' : 'Register New Teacher' ?></h4>
            <button class="modal-close" onclick="<?= $editTeacher ? "window.location.href='teachers.php'" : "App.closeModal('teacherModal')" ?>">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $editTeacher['id'] ?? 0 ?>">
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($editTeacher['name'] ?? '') ?>" placeholder="e.g. MD OMAR FARUK">
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Short Code (Routine Tag) *</label>
                        <input type="text" name="short_code" class="form-control" required value="<?= htmlspecialchars($editTeacher['short_code'] ?? '') ?>" placeholder="e.g. OF, JUB, RA">
                        <small style="color: var(--text-muted);">Exact abbreviation printed in routine cells.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Department *</label>
                        <select name="department_id" class="form-select" required>
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['id'] ?>" <?= (($editTeacher['department_id'] ?? 0) == $dept['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['name']) ?> (<?= htmlspecialchars($dept['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Designation *</label>
                        <input type="text" name="designation" class="form-control" required value="<?= htmlspecialchars($editTeacher['designation'] ?? 'Jr. Instructor') ?>" placeholder="e.g. Jr. Instructor (Tech/Computer)">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Teacher Type</label>
                        <select name="teacher_type" class="form-select">
                            <?php foreach (['Instructor', 'Jr. Instructor', 'Part-Time Teacher', 'Guest Teacher'] as $type): ?>
                                <option value="<?= $type ?>" <?= (($editTeacher['teacher_type'] ?? 'Instructor') === $type) ? 'selected' : '' ?>>
                                    <?= $type ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="grid-3">
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($editTeacher['phone'] ?? '') ?>" placeholder="01750787890">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($editTeacher['email'] ?? '') ?>" placeholder="omar@cnpi.edu.bd">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Max Daily Load</label>
                        <input type="number" name="max_daily_load" class="form-control" min="1" max="10" value="<?= htmlspecialchars((string)($editTeacher['max_daily_load'] ?? 6)) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; cursor: pointer;">
                        <input type="checkbox" name="active" value="1" <?= (!isset($editTeacher['active']) || $editTeacher['active']) ? 'checked' : '' ?>>
                        Faculty Member is Active
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="<?= $editTeacher ? "window.location.href='teachers.php'" : "App.closeModal('teacherModal')" ?>">Cancel</button>
                <button type="submit" class="btn btn-accent"><?= $editTeacher ? 'Update Teacher' : 'Save Teacher' ?></button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    App.setupTableSearch('teacherSearch', 'teacherTable');
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>