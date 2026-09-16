<?php
/**
 * Class Routine Management System (NasimSoft)
 * Semester & Group Subject Assignment (Curriculum Builder)
 * Copyright (c) 2026 NasimSoft.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$db = Database::getInstance();

$selectedGroupId = (int)($_GET['group_id'] ?? 0);
$deleteAssignmentId = (int)($_GET['delete_assign'] ?? 0);

// Delete single assignment
if ($deleteAssignmentId > 0 && verifyCsrfToken($_GET['csrf_token'] ?? '')) {
    $stmt = $db->prepare("DELETE FROM group_subjects WHERE id = ?");
    $stmt->execute([$deleteAssignmentId]);
    logAudit('Remove Group Subject', 'Curriculum', $deleteAssignmentId, 'Removed subject from group curriculum');
    setFlash('success', 'Subject removed from group curriculum.');
    header("Location: group-subjects.php?group_id={$selectedGroupId}");
    exit;
}

// Handle Batch Assignment / Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Security token expired. Please try again.');
    } else {
        $groupId = (int)($_POST['group_id'] ?? 0);
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        $teacherId = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
        $roomId = !empty($_POST['preferred_room_id']) ? (int)$_POST['preferred_room_id'] : null;
        $isLab = isset($_POST['is_lab_required']) ? 1 : 0;

        if ($groupId <= 0 || $subjectId <= 0) {
            setFlash('error', 'Please select both Group and Subject.');
        } else {
            $stmt = $db->prepare("
                INSERT INTO group_subjects (group_id, subject_id, teacher_id, preferred_room_id, is_lab_required, active)
                VALUES (?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE 
                    teacher_id = VALUES(teacher_id),
                    preferred_room_id = VALUES(preferred_room_id),
                    is_lab_required = VALUES(is_lab_required),
                    active = 1
            ");
            $stmt->execute([$groupId, $subjectId, $teacherId, $roomId, $isLab]);
            logAudit('Assign Group Subject', 'Curriculum', $groupId, "Assigned subject ID {$subjectId} to group ID {$groupId}");
            setFlash('success', 'Subject successfully allocated to group curriculum.');
            header("Location: group-subjects.php?group_id={$groupId}");
            exit;
        }
    }
}

// Fetch all active groups for dropdown selector
$allGroups = $db->query("
    SELECT g.id, g.short_code, g.group_name, t.name as tech_name, s.name as sem_name, sh.name as shift_name
    FROM `groups` g
    LEFT JOIN technologies t ON g.technology_id = t.id
    LEFT JOIN semesters s ON g.semester_id = s.id
    LEFT JOIN shifts sh ON g.shift_id = sh.id
    WHERE g.active = 1
    ORDER BY g.short_code ASC
")->fetchAll();

if ($selectedGroupId === 0 && !empty($allGroups)) {
    $selectedGroupId = (int)$allGroups[0]['id'];
}

// Fetch details of selected group
$currentGroup = null;
if ($selectedGroupId > 0) {
    $grpStmt = $db->prepare("
        SELECT g.*, t.name as tech_name, t.short_name as tech_code, 
               s.name as sem_name, s.numeric_level, 
               sh.name as shift_name, i.name as institute_name
        FROM `groups` g
        LEFT JOIN technologies t ON g.technology_id = t.id
        LEFT JOIN semesters s ON g.semester_id = s.id
        LEFT JOIN shifts sh ON g.shift_id = sh.id
        LEFT JOIN departments d ON t.department_id = d.id
        LEFT JOIN institutes i ON d.institute_id = i.id
        WHERE g.id = ?
    ");
    $grpStmt->execute([$selectedGroupId]);
    $currentGroup = $grpStmt->fetch();
}

// Fetch assigned subjects for this group
$assignedSubjects = [];
$totalCumulativeLoad = 0;
if ($selectedGroupId > 0) {
    $subStmt = $db->prepare("
        SELECT gs.id as assignment_id, gs.is_lab_required, gs.teacher_id, gs.preferred_room_id,
               s.id as subject_id, s.name as subject_name, s.subject_code, s.subject_type,
               s.theory_load, s.practical_load, s.total_load, s.credit,
               t.name as teacher_name, t.short_code as teacher_code,
               r.name as room_name, r.room_code
        FROM group_subjects gs
        JOIN subjects s ON gs.subject_id = s.id
        LEFT JOIN teachers t ON gs.teacher_id = t.id
        LEFT JOIN rooms r ON gs.preferred_room_id = r.id
        WHERE gs.group_id = ? AND gs.active = 1
        ORDER BY s.subject_code ASC
    ");
    $subStmt->execute([$selectedGroupId]);
    $assignedSubjects = $subStmt->fetchAll();

    foreach ($assignedSubjects as $as) {
        $totalCumulativeLoad += (int)$as['total_load'];
    }
}

// Available Master Catalogs
$allSubjects = $db->query("SELECT id, name, subject_code, subject_type, total_load, credit FROM subjects WHERE active = 1 ORDER BY subject_code ASC")->fetchAll();
$allTeachers = $db->query("SELECT id, name, short_code, designation FROM teachers WHERE active = 1 ORDER BY name ASC")->fetchAll();
$allRooms = $db->query("SELECT id, name, room_code, room_type, capacity FROM rooms WHERE active = 1 ORDER BY room_code ASC")->fetchAll();

$pageTitle = 'Semester/Group Subject Setup';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="color: var(--navy-blue); font-size: 20px; font-weight: 700;">Semester-Wise Subject Setup</h2>
        <p style="color: var(--text-muted); font-size: 13px;">Pre-assign subjects, designated instructors, preferred rooms, and total loads for automated routine generation.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="routine-generate.php?group_id=<?= $selectedGroupId ?>" class="btn btn-accent">&#9881; Auto Generate Routine for this Group</a>
    </div>
</div>

<!-- Group Selector Bar -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding: 16px 20px;">
        <form method="GET" action="" style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
            <label style="font-weight: 700; color: var(--navy-blue); white-space: nowrap;">Select Academic Group:</label>
            <select name="group_id" class="form-select" style="max-width: 380px;" onchange="this.form.submit()">
                <?php foreach ($allGroups as $g): ?>
                    <option value="<?= $g['id'] ?>" <?= ($selectedGroupId === (int)$g['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($g['short_code']) ?> &bull; <?= htmlspecialchars($g['tech_name']) ?> (<?= htmlspecialchars($g['sem_name']) ?> - <?= htmlspecialchars($g['shift_name']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <noscript><button type="submit" class="btn btn-outline">Load</button></noscript>
            <?php if ($currentGroup): ?>
                <div style="margin-left: auto; display: flex; gap: 12px; align-items: center;">
                    <span class="badge badge-accent" style="font-size: 13px;">Group Short Code: <?= htmlspecialchars($currentGroup['short_code']) ?></span>
                    <span class="badge badge-success" style="font-size: 13px;">Total Assigned Load: <?= $totalCumulativeLoad ?></span>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="split-panel">
    <!-- Curriculum Table Card -->
    <div class="card">
        <div class="card-header">
            <h3>&#128214; Assigned Curriculum Subjects (<?= count($assignedSubjects) ?>)</h3>
            <span class="badge badge-info">Total Load: <?= $totalCumulativeLoad ?> Periods</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Subject Name</th>
                            <th>Type</th>
                            <th>Theory</th>
                            <th>Lab</th>
                            <th>Total Load</th>
                            <th>Assigned Faculty</th>
                            <th>Preferred Room</th>
                            <th style="width: 50px; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($assignedSubjects)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                    No subjects assigned to this group yet. Use the form on the right to add curriculum subjects.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($assignedSubjects as $as): ?>
                                <tr>
                                    <td><strong style="color: var(--navy-blue);"><?= htmlspecialchars($as['subject_code']) ?></strong></td>
                                    <td><strong><?= htmlspecialchars($as['subject_name']) ?></strong></td>
                                    <td>
                                        <span class="badge <?= $as['is_lab_required'] ? 'badge-accent' : 'badge-primary' ?>">
                                            <?= $as['is_lab_required'] ? 'Lab' : htmlspecialchars($as['subject_type']) ?>
                                        </span>
                                    </td>
                                    <td><?= $as['theory_load'] ?></td>
                                    <td><?= $as['practical_load'] ?></td>
                                    <td><strong style="color: var(--dark-orange); font-size: 14px;"><?= $as['total_load'] ?></strong></td>
                                    <td>
                                        <?php if ($as['teacher_id']): ?>
                                            <?= htmlspecialchars($as['teacher_name']) ?> (<strong><?= htmlspecialchars($as['teacher_code']) ?></strong>)
                                        <?php else: ?>
                                            <span style="color: #94A3B8; font-style: italic;">Auto-allocated</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($as['preferred_room_id']): ?>
                                            <span class="badge badge-primary"><?= htmlspecialchars($as['room_code']) ?></span>
                                        <?php else: ?>
                                            <span style="color: #94A3B8; font-style: italic;">Auto-allocated</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <button class="btn btn-danger btn-sm" onclick="App.confirm('Remove this subject from group curriculum?', () => { window.location.href='group-subjects.php?group_id=<?= $selectedGroupId ?>&delete_assign=<?= $as['assignment_id'] ?>&csrf_token=<?= generateCsrfToken() ?>'; })">&#128465;</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer" style="display: flex; justify-content: space-between; align-items: center;">
            <small style="color: var(--text-muted);">
                Once saved, the generator automatically loads these exact subjects, teachers, and loads during routine generation.
            </small>
            <strong>Total Semester Load: <?= $totalCumulativeLoad ?></strong>
        </div>
    </div>

    <!-- Add Subject Form Card -->
    <div class="card">
        <div class="card-header">
            <h3>&#10133; Allocate Subject to Group</h3>
        </div>
        <form method="POST" action="">
            <div class="card-body">
                <?= csrfField() ?>
                <input type="hidden" name="group_id" value="<?= $selectedGroupId ?>">

                <div class="form-group">
                    <label class="form-label">Subject from Catalog *</label>
                    <select name="subject_id" class="form-select" required>
                        <option value="">-- Choose Subject --</option>
                        <?php foreach ($allSubjects as $sub): ?>
                            <option value="<?= $sub['id'] ?>">
                                <?= htmlspecialchars($sub['subject_code']) ?> - <?= htmlspecialchars($sub['name']) ?> (Load: <?= $sub['total_load'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Designated Faculty / Teacher</label>
                    <select name="teacher_id" class="form-select">
                        <option value="">-- Auto Allocate Teacher --</option>
                        <?php foreach ($allTeachers as $tch): ?>
                            <option value="<?= $tch['id'] ?>">
                                <?= htmlspecialchars($tch['name']) ?> (<?= htmlspecialchars($tch['short_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: var(--text-muted);">Short code appears in routine cell.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Preferred Classroom or Lab</label>
                    <select name="preferred_room_id" class="form-select">
                        <option value="">-- Auto Allocate Room --</option>
                        <?php foreach ($allRooms as $rm): ?>
                            <option value="<?= $rm['id'] ?>">
                                <?= htmlspecialchars($rm['room_code']) ?> - <?= htmlspecialchars($rm['name']) ?> (<?= $rm['room_type'] ?>, Cap: <?= $rm['capacity'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="background: #FFF7ED; border: 1px solid var(--orange-light); padding: 12px; border-radius: 6px;">
                    <label style="display: flex; align-items: center; gap: 8px; font-weight: 700; color: var(--dark-orange); cursor: pointer;">
                        <input type="checkbox" name="is_lab_required" value="1">
                        Requires Lab / 2 Consecutive Periods
                    </label>
                    <small style="color: var(--text-dark); display: block; margin-top: 4px;">
                        When checked, routine generator schedules this class as two consecutive periods (e.g. 5th + 6th period).
                    </small>
                </div>

                <button type="submit" class="btn btn-accent" style="width: 100%; margin-top: 10px;">
                    Save to Group Curriculum
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>