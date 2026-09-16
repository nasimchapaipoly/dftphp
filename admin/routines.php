<?php
/**
 * Class Routine Management System (NasimSoft)
 * All Routines Directory & Filter Explorer
 * Copyright (c) 2026 NasimSoft.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$db = Database::getInstance();

$filterDept = (int)($_GET['department_id'] ?? 0);
$filterSem = (int)($_GET['semester_id'] ?? 0);
$filterShift = (int)($_GET['shift_id'] ?? 0);
$filterGroup = (int)($_GET['group_id'] ?? 0);
$filterStatus = trim($_GET['status'] ?? '');

$deleteId = (int)($_GET['delete'] ?? 0);
if ($deleteId > 0 && verifyCsrfToken($_GET['csrf_token'] ?? '')) {
    $stmt = $db->prepare("DELETE FROM routines WHERE id = ?");
    $stmt->execute([$deleteId]);
    logAudit('Delete Routine Slot', 'Routines', $deleteId, 'Deleted routine period');
    setFlash('success', 'Routine slot deleted successfully.');
    header('Location: routines.php');
    exit;
}

// Publish / Unpublish Toggle
$toggleStatusId = (int)($_GET['toggle_status'] ?? 0);
if ($toggleStatusId > 0 && verifyCsrfToken($_GET['csrf_token'] ?? '')) {
    $current = $db->prepare("SELECT status FROM routines WHERE id = ?");
    $current->execute([$toggleStatusId]);
    $currStatus = $current->fetchColumn();
    $newStatus = ($currStatus === 'PUBLISHED') ? 'DRAFT' : 'PUBLISHED';

    $upd = $db->prepare("UPDATE routines SET status = ? WHERE id = ?");
    $upd->execute([$newStatus, $toggleStatusId]);
    logAudit('Toggle Routine Status', 'Routines', $toggleStatusId, "Changed status to {$newStatus}");
    setFlash('success', "Routine status updated to {$newStatus}.");
    header('Location: routines.php');
    exit;
}

// Build Filter Query
$where = ["1=1"];
$params = [];

if ($filterDept > 0) {
    $where[] = "r.department_id = ?";
    $params[] = $filterDept;
}
if ($filterSem > 0) {
    $where[] = "r.semester_id = ?";
    $params[] = $filterSem;
}
if ($filterShift > 0) {
    $where[] = "r.shift_id = ?";
    $params[] = $filterShift;
}
if ($filterGroup > 0) {
    $where[] = "r.group_id = ?";
    $params[] = $filterGroup;
}
if (!empty($filterStatus)) {
    $where[] = "r.status = ?";
    $params[] = $filterStatus;
}

$whereClause = implode(" AND ", $where);

// Fetch routine records
$routines = $db->prepare("
    SELECT r.*, 
           g.short_code as group_code, g.group_name,
           s.name as subject_name, s.subject_code,
           t.name as teacher_name, t.short_code as teacher_code,
           rm.name as room_name, rm.room_code,
           d.name as department_name, d.code as department_code,
           sem.name as semester_name,
           sh.name as shift_name
    FROM routines r
    JOIN `groups` g ON r.group_id = g.id
    JOIN subjects s ON r.subject_id = s.id
    JOIN teachers t ON r.teacher_id = t.id
    JOIN rooms rm ON r.room_id = rm.id
    LEFT JOIN departments d ON r.department_id = d.id
    LEFT JOIN semesters sem ON r.semester_id = sem.id
    LEFT JOIN shifts sh ON r.shift_id = sh.id
    WHERE {$whereClause}
    ORDER BY FIELD(r.day, 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), r.period_start ASC
");
$routines->execute($params);
$routineList = $routines->fetchAll();

// Fetch filter options
$departments = $db->query("SELECT id, name, code FROM departments WHERE active = 1 ORDER BY name ASC")->fetchAll();
$semesters = $db->query("SELECT id, name, numeric_level FROM semesters WHERE active = 1 ORDER BY numeric_level ASC")->fetchAll();
$shifts = $db->query("SELECT id, name FROM shifts WHERE active = 1 ORDER BY id ASC")->fetchAll();
$groups = $db->query("SELECT id, short_code FROM `groups` WHERE active = 1 ORDER BY short_code ASC")->fetchAll();
$pageTitle = 'All Class Routines';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="color: var(--navy-blue); font-size: 20px; font-weight: 700;">Institutional Routine Directory</h2>
        <p style="color: var(--text-muted); font-size: 13px;">Manage, review, filter, and modify scheduled academic routine slots.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="routine-create.php" class="btn btn-accent">&#10133; Interactive Routine Builder</a>
        <a href="routine-generate.php" class="btn btn-primary">&#9881; Auto Generator</a>
    </div>
</div>

<!-- Filter Bar Card -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding: 16px 20px;">
        <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) 120px; gap: 12px; align-items: flex-end;">
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 12px;">Department</label>
                <select name="department_id" class="form-select">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= ($filterDept === (int)$d['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d['code']) ?> - <?= htmlspecialchars($d['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 12px;">Semester</label>
                <select name="semester_id" class="form-select">
                    <option value="">All Semesters</option>
                    <?php foreach ($semesters as $sm): ?>
                        <option value="<?= $sm['id'] ?>" <?= ($filterSem === (int)$sm['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sm['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 12px;">Shift</label>
                <select name="shift_id" class="form-select">
                    <option value="">All Shifts</option>
                    <?php foreach ($shifts as $sh): ?>
                        <option value="<?= $sh['id'] ?>" <?= ($filterShift === (int)$sh['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sh['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 12px;">Group</label>
                <select name="group_id" class="form-select">
                    <option value="">All Groups</option>
                    <?php foreach ($groups as $g): ?>
                        <option value="<?= $g['id'] ?>" <?= ($filterGroup === (int)$g['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($g['short_code']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 12px;">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="PUBLISHED" <?= ($filterStatus === 'PUBLISHED') ? 'selected' : '' ?>>PUBLISHED</option>
                    <option value="DRAFT" <?= ($filterStatus === 'DRAFT') ? 'selected' : '' ?>>DRAFT</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="height: 38px;">Apply Filters</button>
        </form>
    </div>
</div>

<!-- Routines List Card -->
<div class="card">
    <div class="card-header">
        <h3>&#128197; Scheduled Routine Slots (<?= count($routineList) ?>)</h3>
        <input type="text" id="routineSearch" class="form-control" placeholder="Search teacher, subject, room..." style="max-width: 250px; padding: 6px 12px; font-size: 13px;">
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" id="routineTable">
                <thead>
                    <tr>
                        <th>Group</th>
                        <th>Day</th>
                        <th>Period(s)</th>
                        <th>Subject (Code)</th>
                        <th>Teacher (Code)</th>
                        <th>Room</th>
                        <th>Class Type</th>
                        <th>Load</th>
                        <th>Status</th>
                        <th style="width: 140px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($routineList)): ?>
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                No routine entries match your criteria. Click <strong>Interactive Routine Builder</strong> or <strong>Auto Generator</strong> to create schedules.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($routineList as $rt): ?>
                            <tr>
                                <td><strong style="color: var(--dark-orange); font-size: 13.5px;"><?= htmlspecialchars($rt['group_code']) ?></strong></td>
                                <td><strong><?= htmlspecialchars($rt['day']) ?></strong></td>
                                <td>
                                    <?php if ($rt['period_start'] === $rt['period_end']): ?>
                                        <span class="badge badge-info"><?= $rt['period_start'] ?>th Period</span>
                                    <?php else: ?>
                                        <span class="badge badge-accent"><?= $rt['period_start'] ?>th + <?= $rt['period_end'] ?>th Period</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?= htmlspecialchars($rt['subject_name']) ?></div>
                                    <small style="color: var(--navy-blue); font-weight: 700;"><?= htmlspecialchars($rt['subject_code']) ?></small>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($rt['teacher_name']) ?></div>
                                    <span class="badge badge-accent" style="font-size: 11px;"><?= htmlspecialchars($rt['teacher_code']) ?></span>
                                </td>
                                <td><span class="badge badge-primary"><?= htmlspecialchars($rt['room_code']) ?></span></td>
                                <td>
                                    <span class="badge <?= $rt['is_lab'] ? 'badge-accent' : 'badge-info' ?>">
                                        <?= $rt['is_lab'] ? 'Lab' : htmlspecialchars($rt['class_type']) ?>
                                    </span>
                                </td>
                                <td><strong><?= $rt['load_count'] ?></strong></td>
                                <td>
                                    <a href="routines.php?toggle_status=<?= $rt['id'] ?>&csrf_token=<?= generateCsrfToken() ?>" title="Click to toggle publish status">
                                        <span class="badge <?= $rt['status'] === 'PUBLISHED' ? 'badge-success' : 'badge-warning' ?>" style="cursor: pointer;">
                                            <?= htmlspecialchars($rt['status']) ?> &#8646;
                                        </span>
                                    </a>
                                </td>
                                <td style="text-align: right;">
                                    <a href="routine-create.php?group_id=<?= $rt['group_id'] ?>" class="btn btn-outline btn-sm" title="Open Interactive Builder">&#9998;</a>
                                    <button class="btn btn-danger btn-sm" onclick="App.confirm('Are you sure you want to delete this routine slot?', () => { window.location.href='routines.php?delete=<?= $rt['id'] ?>&csrf_token=<?= generateCsrfToken() ?>'; })">&#128465;</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    App.setupTableSearch('routineSearch', 'routineTable');
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>