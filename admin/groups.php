<?php
/**
 * Class Routine Management System (NasimSoft)
 * Academic Group Management with Automated Short Code Generator
 * Copyright (c) 2026 NasimSoft.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$db = Database::getInstance();
$editId = (int)($_GET['edit'] ?? 0);
$deleteId = (int)($_GET['delete'] ?? 0);

// Delete Action
if ($deleteId > 0 && verifyCsrfToken($_GET['csrf_token'] ?? '')) {
    $stmt = $db->prepare("DELETE FROM `groups` WHERE id = ?");
    $stmt->execute([$deleteId]);
    logAudit('Delete Group', 'Groups', $deleteId, 'Deleted academic group');
    setFlash('success', 'Group deleted successfully.');
    header('Location: groups.php');
    exit;
}

// Add or Update Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Security token expired. Please try again.');
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $techId = (int)($_POST['technology_id'] ?? 0);
        $semesterId = (int)($_POST['semester_id'] ?? 0);
        $shiftId = (int)($_POST['shift_id'] ?? 0);
        $groupName = trim($_POST['group_name'] ?? 'Group 1');
        $section = trim($_POST['section'] ?? '');
        $studentCount = (int)($_POST['student_count'] ?? 50);
        $shortCode = trim($_POST['short_code'] ?? '');
        $classTeacherId = (int)($_POST['class_teacher_id'] ?? 0) ?: null;
        $active = isset($_POST['active']) ? 1 : 0;

        if ($techId <= 0 || $semesterId <= 0 || $shiftId <= 0) {
            setFlash('error', 'Please select Technology, Semester, and Shift.');
        } else {
            // Automated Short Code Computation if left empty
            if (empty($shortCode)) {
                $techQuery = $db->prepare("SELECT short_name FROM technologies WHERE id = ?");
                $techQuery->execute([$techId]);
                $tName = $techQuery->fetchColumn() ?: 'TECH';

                $semQuery = $db->prepare("SELECT numeric_level, code FROM semesters WHERE id = ?");
                $semQuery->execute([$semesterId]);
                $sData = $semQuery->fetch();
                $sLevel = $sData['numeric_level'] ?? '1';

                $shiftQuery = $db->prepare("SELECT code FROM shifts WHERE id = ?");
                $shiftQuery->execute([$shiftId]);
                $shiftCode = $shiftQuery->fetchColumn() ?: '1';
                $shiftNum = preg_replace('/[^0-9]/', '', (string)$shiftCode) ?: '1';

                $shortCode = "{$tName} {$sLevel}/{$shiftNum}";
                if (!empty($section)) {
                    $shortCode .= " ({$section})";
                }
            }

            try {
                if ($id > 0) {
                    $stmt = $db->prepare("UPDATE `groups` SET technology_id = ?, semester_id = ?, shift_id = ?, group_name = ?, section = ?, short_code = ?, student_count = ?, class_teacher_id = ?, active = ? WHERE id = ?");
                    $stmt->execute([$techId, $semesterId, $shiftId, $groupName, $section, $shortCode, $studentCount, $classTeacherId, $active, $id]);
                    logAudit('Update Group', 'Groups', $id, "Updated group {$shortCode}");
                    setFlash('success', 'Group updated successfully.');
                } else {
                    $stmt = $db->prepare("INSERT INTO `groups` (technology_id, semester_id, shift_id, group_name, section, short_code, student_count, class_teacher_id, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$techId, $semesterId, $shiftId, $groupName, $section, $shortCode, $studentCount, $classTeacherId, $active]);
                    $newId = (int)$db->lastInsertId();
                    logAudit('Create Group', 'Groups', $newId, "Created group {$shortCode}");
                    setFlash('success', 'Group created successfully.');
                }
            } catch (PDOException $e) {
                // Most likely cause: install/settings-migration.sql hasn't been run yet,
                // so the `class_teacher_id` column doesn't exist on this database.
                if (stripos($e->getMessage(), 'class_teacher_id') !== false) {
                    setFlash('error', 'Database is missing the "class_teacher_id" column. Please run install/settings-migration.sql on your database, then try again.');
                } else {
                    setFlash('error', 'Could not save this group: ' . $e->getMessage());
                }
            }
            header('Location: groups.php');
            exit;
        }
    }
}

$editGroup = null;
if ($editId > 0) {
    $stmt = $db->prepare("SELECT * FROM `groups` WHERE id = ?");
    $stmt->execute([$editId]);
    $editGroup = $stmt->fetch();
}

$technologies = $db->query("SELECT id, name, short_name FROM technologies WHERE active = 1 ORDER BY name ASC")->fetchAll();
$semesters = $db->query("SELECT id, name, code, numeric_level FROM semesters WHERE active = 1 ORDER BY numeric_level ASC")->fetchAll();
$shifts = $db->query("SELECT id, name, code FROM shifts WHERE active = 1 ORDER BY id ASC")->fetchAll();
$teachersList = $db->query("SELECT id, name, short_code FROM teachers WHERE active = 1 ORDER BY name ASC")->fetchAll();

$groups = $db->query("
    SELECT g.*, t.name as tech_name, t.short_name as tech_code, 
           s.name as semester_name, s.numeric_level, 
           sh.name as shift_name, sh.code as shift_code,
           (SELECT COUNT(*) FROM group_subjects WHERE group_id = g.id) as subject_count,
           (SELECT COUNT(*) FROM routines WHERE group_id = g.id) as routine_count
    FROM `groups` g
    LEFT JOIN technologies t ON g.technology_id = t.id
    LEFT JOIN semesters s ON g.semester_id = s.id
    LEFT JOIN shifts sh ON g.shift_id = sh.id
    ORDER BY g.id DESC
")->fetchAll();
$pageTitle = 'Academic Groups & Short Codes';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="color: var(--navy-blue); font-size: 20px; font-weight: 700;">Academic Groups & Short Codes</h2>
        <p style="color: var(--text-muted); font-size: 13px;">Manage Technology + Semester + Shift + Section combinations with real-time short code generation.</p>
    </div>
    <button class="btn btn-accent" onclick="App.openModal('groupModal')">&#10133; Add New Group</button>
</div>

<div class="card">
    <div class="card-header">
        <h3>&#128101; Active Academic Groups</h3>
        <input type="text" id="groupSearch" class="form-control" placeholder="Search group or short code..." style="max-width: 250px; padding: 6px 12px; font-size: 13px;">
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" id="groupTable">
                <thead>
                    <tr>
                        <th style="width: 50px;">SL</th>
                        <th>Short Code</th>
                        <th>Technology</th>
                        <th>Semester</th>
                        <th>Shift</th>
                        <th>Section</th>
                        <th>Students</th>
                        <th>Subjects</th>
                        <th>Routines</th>
                        <th>Status</th>
                        <th style="width: 130px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($groups)): ?>
                        <tr>
                            <td colspan="11" style="text-align: center; padding: 25px; color: var(--text-muted);">No academic groups created yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($groups as $idx => $grp): ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td><strong style="color: var(--dark-orange); font-size: 14px;"><?= htmlspecialchars($grp['short_code']) ?></strong></td>
                                <td><?= htmlspecialchars($grp['tech_name']) ?> (<strong><?= htmlspecialchars($grp['tech_code']) ?></strong>)</td>
                                <td><?= htmlspecialchars($grp['semester_name']) ?></td>
                                <td><?= htmlspecialchars($grp['shift_name']) ?></td>
                                <td><?= htmlspecialchars($grp['section'] ?: 'Default') ?></td>
                                <td><span class="badge badge-primary"><?= $grp['student_count'] ?></span></td>
                                <td><span class="badge badge-info"><?= $grp['subject_count'] ?> Subjects</span></td>
                                <td><span class="badge badge-accent"><?= $grp['routine_count'] ?> Periods</span></td>
                                <td>
                                    <span class="badge <?= $grp['active'] ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $grp['active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <a href="groups.php?edit=<?= $grp['id'] ?>" class="btn btn-outline btn-sm">&#9998; Edit</a>
                                    <button class="btn btn-danger btn-sm" onclick="App.confirm('Are you sure you want to delete this group? All associated routine slots will be removed.', () => { window.location.href='groups.php?delete=<?= $grp['id'] ?>&csrf_token=<?= generateCsrfToken() ?>'; })">&#128465;</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Group Modal -->
<div class="modal-overlay <?= $editGroup ? 'active' : '' ?>" id="groupModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h4><?= $editGroup ? 'Edit Group' : 'Add Academic Group' ?></h4>
            <button class="modal-close" onclick="<?= $editGroup ? "window.location.href='groups.php'" : "App.closeModal('groupModal')" ?>">&times;</button>
        </div>
        <form method="POST" action="" id="groupForm">
            <div class="modal-body">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $editGroup['id'] ?? 0 ?>">
                
                <div class="form-group">
                    <label class="form-label">Technology *</label>
                    <select name="technology_id" id="selTech" class="form-select" required onchange="calculateShortCode()">
                        <option value="">-- Select Technology --</option>
                        <?php foreach ($technologies as $tech): ?>
                            <option value="<?= $tech['id'] ?>" data-code="<?= htmlspecialchars($tech['short_name']) ?>" <?= (($editGroup['technology_id'] ?? 0) == $tech['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tech['name']) ?> (<?= htmlspecialchars($tech['short_name']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Semester *</label>
                        <select name="semester_id" id="selSem" class="form-select" required onchange="calculateShortCode()">
                            <option value="">-- Select Semester --</option>
                            <?php foreach ($semesters as $sem): ?>
                                <option value="<?= $sem['id'] ?>" data-level="<?= $sem['numeric_level'] ?>" <?= (($editGroup['semester_id'] ?? 0) == $sem['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sem['name']) ?> (<?= $sem['numeric_level'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Shift *</label>
                        <select name="shift_id" id="selShift" class="form-select" required onchange="calculateShortCode()">
                            <option value="">-- Select Shift --</option>
                            <?php foreach ($shifts as $sh): ?>
                                <option value="<?= $sh['id'] ?>" data-code="<?= htmlspecialchars($sh['code']) ?>" <?= (($editGroup['shift_id'] ?? 0) == $sh['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sh['name']) ?> (<?= htmlspecialchars($sh['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid-3">
                    <div class="form-group">
                        <label class="form-label">Group Name</label>
                        <input type="text" name="group_name" id="inpGroupName" class="form-control" value="<?= htmlspecialchars($editGroup['group_name'] ?? 'Group 1') ?>" placeholder="e.g. Group 1">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Section (Optional)</label>
                        <input type="text" name="section" id="inpSection" class="form-control" value="<?= htmlspecialchars($editGroup['section'] ?? '') ?>" placeholder="e.g. A or B" onkeyup="calculateShortCode()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Student Capacity</label>
                        <input type="number" name="student_count" class="form-control" min="1" max="300" value="<?= htmlspecialchars((string)($editGroup['student_count'] ?? 50)) ?>">
                    </div>
                </div>

                <div class="form-group" style="background: #F8FAFC; padding: 14px; border-radius: 6px; border: 1px solid var(--border-color);">
                    <label class="form-label" style="display: flex; justify-content: space-between;">
                        <span>Generated Short Code (e.g. CST 7/1, ET 5/1 (A)) *</span>
                        <a href="javascript:void(0)" onclick="calculateShortCode(true)" style="color: var(--dark-orange); text-decoration: none; font-size: 11px;">&#8635; Regenerate</a>
                    </label>
                    <input type="text" name="short_code" id="inpShortCode" class="form-control" required style="font-weight: 700; font-size: 15px; color: var(--navy-blue);" value="<?= htmlspecialchars($editGroup['short_code'] ?? '') ?>" placeholder="e.g. CST 7/1 (A)">
                    <small style="color: var(--text-muted);">This short code appears in the header and every cell of the institutional routine.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Class Teacher (Optional)</label>
                    <select name="class_teacher_id" class="form-select">
                        <option value="0">-- None --</option>
                        <?php foreach ($teachersList as $tch): ?>
                            <option value="<?= $tch['id'] ?>" <?= (($editGroup['class_teacher_id'] ?? 0) == $tch['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tch['name']) ?> (<?= htmlspecialchars($tch['short_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-hint">Printed as "Class Teacher: ..." on this group's routine document.</span>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; cursor: pointer;">
                        <input type="checkbox" name="active" value="1" <?= (!isset($editGroup['active']) || $editGroup['active']) ? 'checked' : '' ?>>
                        Academic Group is Active
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="<?= $editGroup ? "window.location.href='groups.php'" : "App.closeModal('groupModal')" ?>">Cancel</button>
                <button type="submit" class="btn btn-accent"><?= $editGroup ? 'Update Group' : 'Save Group' ?></button>
            </div>
        </form>
    </div>
</div>

<script>
// Dynamic Short Code Calculation Engine
function calculateShortCode(force = false) {
    const codeInput = document.getElementById('inpShortCode');
    if (!force && codeInput.value && !codeInput.dataset.auto) {
        if (!codeInput.dataset.touched) return;
    }

    const techSelect = document.getElementById('selTech');
    const semSelect = document.getElementById('selSem');
    const shiftSelect = document.getElementById('selShift');
    const sectionInput = document.getElementById('inpSection');

    const techOpt = techSelect.options[techSelect.selectedIndex];
    const semOpt = semSelect.options[semSelect.selectedIndex];
    const shiftOpt = shiftSelect.options[shiftSelect.selectedIndex];

    const techCode = techOpt ? (techOpt.getAttribute('data-code') || '') : '';
    const semLevel = semOpt ? (semOpt.getAttribute('data-level') || '') : '';
    const shiftCodeRaw = shiftOpt ? (shiftOpt.getAttribute('data-code') || '') : '';
    const shiftNum = shiftCodeRaw.replace(/\D/g, '') || '1';
    const section = (sectionInput.value || '').trim();

    if (techCode && semLevel) {
        let code = `${techCode} ${semLevel}/${shiftNum}`;
        if (section) {
            code += ` (${section})`;
        }
        codeInput.value = code;
        codeInput.dataset.auto = "1";
    }
}

document.getElementById('inpShortCode').addEventListener('input', function() {
    this.dataset.auto = "";
    this.dataset.touched = "1";
});

document.addEventListener('DOMContentLoaded', () => {
    App.setupTableSearch('groupSearch', 'groupTable');
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>