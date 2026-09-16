<?php
/**
 * Class Routine Management System (NasimSoft)
 * Comprehensive Conflict Detection & Routine Audit Center
 * Copyright (c) 2026 NasimSoft.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/validator.php';

$db = Database::getInstance();
$validator = new ConflictValidator($db);

$selectedGroupId = (int)($_GET['group_id'] ?? 0);
$allGroups = $db->query("SELECT id, short_code, group_name FROM `groups` WHERE active = 1 ORDER BY short_code ASC")->fetchAll();

if ($selectedGroupId === 0 && !empty($allGroups)) {
    $selectedGroupId = (int)$allGroups[0]['id'];
}

// Publish action if validated
if (isset($_POST['publish_group']) && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $pubGroupId = (int)$_POST['publish_group'];
    $audit = $validator->validateGroupRoutine($pubGroupId);
    if ($audit['valid']) {
        $stmt = $db->prepare("UPDATE routines SET status = 'PUBLISHED' WHERE group_id = ?");
        $stmt->execute([$pubGroupId]);
        logAudit('Publish Routine', 'Validator', $pubGroupId, "Validated and published routine for group ID {$pubGroupId}");
        setFlash('success', "Routine for {$audit['group']['short_code']} has been verified and PUBLISHED.");
    } else {
        setFlash('error', "Cannot publish routine with outstanding conflicts.");
    }
    header("Location: conflict-validator.php?group_id={$pubGroupId}");
    exit;
}

// Run audit for selected group
$audit = $validator->validateGroupRoutine($selectedGroupId);

// Global conflict counts across all routines in institute
$globalTeacherConflicts = $db->query("
    SELECT r1.day, r1.period_start, r1.period_end, t.name as teacher_name, t.short_code as teacher_code,
           g1.short_code as group1, g2.short_code as group2, rm1.room_code as room1, rm2.room_code as room2
    FROM routines r1
    JOIN `groups` g1 ON r1.group_id = g1.id
    JOIN routines r2 ON r1.teacher_id = r2.teacher_id 
                    AND r1.day = r2.day 
                    AND r1.id < r2.id
                    AND ((r1.period_start <= r2.period_start AND r1.period_end >= r2.period_start) OR (r1.period_start <= r2.period_end AND r1.period_end >= r2.period_end))
    JOIN `groups` g2 ON r2.group_id = g2.id AND g2.shift_id = g1.shift_id
    JOIN teachers t ON r1.teacher_id = t.id
    JOIN rooms rm1 ON r1.room_id = rm1.id
    JOIN rooms rm2 ON r2.room_id = rm2.id
")->fetchAll();

$globalRoomConflicts = $db->query("
    SELECT r1.day, r1.period_start, r1.period_end, rm.room_code,
           g1.short_code as group1, g2.short_code as group2, t1.short_code as teacher1, t2.short_code as teacher2
    FROM routines r1
    JOIN `groups` g1 ON r1.group_id = g1.id
    JOIN routines r2 ON r1.room_id = r2.room_id 
                    AND r1.day = r2.day 
                    AND r1.id < r2.id
                    AND ((r1.period_start <= r2.period_start AND r1.period_end >= r2.period_start) OR (r1.period_start <= r2.period_end AND r1.period_end >= r2.period_end))
    JOIN `groups` g2 ON r2.group_id = g2.id AND g2.shift_id = g1.shift_id
    JOIN rooms rm ON r1.room_id = rm.id
    JOIN teachers t1 ON r1.teacher_id = t1.id
    JOIN teachers t2 ON r2.teacher_id = t2.id
")->fetchAll();

$pageTitle = 'Conflict & Integrity Validator';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="color: var(--navy-blue); font-size: 20px; font-weight: 700;">Conflict Detection & Audit Engine</h2>
        <p style="color: var(--text-muted); font-size: 13px;">Automated verification of teacher overlaps, room occupancy, consecutive lab rules, and load quotas.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="routine-create.php?group_id=<?= $selectedGroupId ?>" class="btn btn-outline">&#9998; Open Routine Grid</a>
        <a href="routine-bulk-edit.php" class="btn btn-primary">&#9998; Bulk Conflict Resolver</a>
    </div>
</div>

<!-- Global Conflict Banner -->
<?php if (!empty($globalTeacherConflicts) || !empty($globalRoomConflicts)): ?>
    <div class="alert alert-danger" style="margin-bottom: 24px;">
        <div>
            <strong>Campus-Wide Scheduling Overlaps Detected!</strong><br>
            Teacher Clashes: <?= count($globalTeacherConflicts) ?> &bull; Classroom Clashes: <?= count($globalRoomConflicts) ?>. Review the breakdown below to resolve conflicts.
        </div>
    </div>
<?php endif; ?>

<!-- Group Selector Bar -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
        <form method="GET" action="" style="display: flex; align-items: center; gap: 12px;">
            <label style="font-weight: 700; color: var(--navy-blue);">Audit Routine for Group:</label>
            <select name="group_id" class="form-select" style="max-width: 380px;" onchange="this.form.submit()">
                <?php foreach ($allGroups as $g): ?>
                    <option value="<?= $g['id'] ?>" <?= ($selectedGroupId === (int)$g['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($g['short_code']) ?> &bull; <?= htmlspecialchars($g['group_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <noscript><button type="submit" class="btn btn-outline">Audit</button></noscript>
        </form>

        <div style="display: flex; gap: 10px; align-items: center;">
            <?php if ($audit['valid']): ?>
                <span class="badge badge-success" style="font-size: 13px; padding: 6px 12px;">&#10004; VALIDATED & CONFLICT-FREE</span>
                <form method="POST" action="" style="display: inline;">
                    <?= csrfField() ?>
                    <input type="hidden" name="publish_group" value="<?= $selectedGroupId ?>">
                    <button type="submit" class="btn btn-accent btn-sm">Publish Routine</button>
                </form>
            <?php else: ?>
                <span class="badge badge-danger" style="font-size: 13px; padding: 6px 12px;">&#10006; CONFLICTS DETECTED</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="grid-2">
    <!-- Validation Checklist Card -->
    <div class="card">
        <div class="card-header">
            <h3>&#9989; Routine Integrity Status for <?= htmlspecialchars($audit['group']['short_code'] ?? '') ?></h3>
            <span class="badge <?= $audit['valid'] ? 'badge-success' : 'badge-danger' ?>">
                <?= $audit['valid'] ? 'Ready for Publishing' : 'Action Required' ?>
            </span>
        </div>
        <div class="card-body">
            <!-- Load Quota Summary -->
            <div class="grid-2" style="margin-bottom: 20px;">
                <div style="background: #F8FAFC; padding: 14px; border-radius: 6px; border: 1px solid var(--border-color); text-align: center;">
                    <div style="font-size: 12px; color: var(--text-muted); font-weight: 700;">REQUIRED CURRICULUM LOAD</div>
                    <div style="font-size: 24px; font-weight: 800; color: var(--navy-blue);"><?= $audit['total_required_load'] ?></div>
                </div>
                <div style="background: #FFF7ED; padding: 14px; border-radius: 6px; border: 1px solid var(--orange-light); text-align: center;">
                    <div style="font-size: 12px; color: var(--dark-orange); font-weight: 700;">SCHEDULED ROUTINE LOAD</div>
                    <div style="font-size: 24px; font-weight: 800; color: var(--dark-orange);"><?= $audit['total_scheduled_load'] ?></div>
                </div>
            </div>

            <!-- Passed Rules -->
            <?php if (!empty($audit['passed_checks'])): ?>
                <h5 style="font-size: 13px; font-weight: 700; color: var(--navy-blue); margin-bottom: 8px;">Passed Criteria</h5>
                <ul style="list-style: none; padding-left: 0; margin-bottom: 18px;">
                    <?php foreach ($audit['passed_checks'] as $pc): ?>
                        <li style="padding: 6px 0; color: #15803D; font-size: 13px; display: flex; align-items: center; gap: 8px;">
                            <span style="font-weight: bold;">&#10004;</span> <?= htmlspecialchars($pc) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <!-- Critical Errors -->
            <?php if (!empty($audit['errors'])): ?>
                <h5 style="font-size: 13px; font-weight: 700; color: var(--danger); margin-bottom: 8px;">Critical Conflicts & Missing Rules</h5>
                <ul style="list-style: none; padding-left: 0; margin-bottom: 18px;">
                    <?php foreach ($audit['errors'] as $err): ?>
                        <li style="padding: 8px 12px; background: #FEF2F2; color: #991B1B; border-radius: 6px; margin-bottom: 8px; font-size: 12.5px; border-left: 3px solid var(--danger);">
                            <strong>Error:</strong> <?= htmlspecialchars($err) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <!-- Warnings -->
            <?php if (!empty($audit['warnings'])): ?>
                <h5 style="font-size: 13px; font-weight: 700; color: #B45309; margin-bottom: 8px;">Advisory Warnings</h5>
                <ul style="list-style: none; padding-left: 0;">
                    <?php foreach ($audit['warnings'] as $warn): ?>
                        <li style="padding: 8px 12px; background: #FFFBEB; color: #92400E; border-radius: 6px; margin-bottom: 8px; font-size: 12.5px; border-left: 3px solid var(--warning);">
                            <strong>Notice:</strong> <?= htmlspecialchars($warn) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Subject Load Completion Breakdown -->
    <div class="card">
        <div class="card-header">
            <h3>&#128218; Curriculum Subject Load Breakdown</h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Subject</th>
                            <th>Required</th>
                            <th>Scheduled</th>
                            <th>Fulfillment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($audit['subject_breakdown'] as $sub): ?>
                            <?php $isDone = $sub['scheduled_load'] >= $sub['required_load']; ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($sub['code']) ?></strong></td>
                                <td><?= htmlspecialchars($sub['name']) ?></td>
                                <td><?= $sub['required_load'] ?></td>
                                <td><strong><?= $sub['scheduled_load'] ?></strong></td>
                                <td>
                                    <?php if ($sub['scheduled_load'] === 0): ?>
                                        <span class="badge badge-danger">Unassigned</span>
                                    <?php elseif ($isDone): ?>
                                        <span class="badge badge-success">&#10004; Complete</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Partial (<?= $sub['scheduled_load'] ?>/<?= $sub['required_load'] ?>)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Global Overlap Details Table -->
<?php if (!empty($globalTeacherConflicts) || !empty($globalRoomConflicts)): ?>
    <div class="card" style="margin-top: 24px;">
        <div class="card-header">
            <h3>&#9888; Detailed Campus-Wide Collision Log</h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Resource</th>
                            <th>Day & Period</th>
                            <th>Conflict Between Groups</th>
                            <th>Locations</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($globalTeacherConflicts as $tc): ?>
                            <tr>
                                <td><span class="badge badge-danger">Teacher Clash</span></td>
                                <td><strong><?= htmlspecialchars($tc['teacher_name']) ?> (<?= htmlspecialchars($tc['teacher_code']) ?>)</strong></td>
                                <td><?= htmlspecialchars($tc['day']) ?> &bull; Period <?= $tc['period_start'] ?>-<?= $tc['period_end'] ?></td>
                                <td><span class="badge badge-accent"><?= htmlspecialchars($tc['group1']) ?></span> &harr; <span class="badge badge-accent"><?= htmlspecialchars($tc['group2']) ?></span></td>
                                <td><?= htmlspecialchars($tc['room1']) ?> / <?= htmlspecialchars($tc['room2']) ?></td>
                                <td style="text-align: right;"><a href="routine-bulk-edit.php" class="btn btn-outline btn-sm">Resolve</a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php foreach ($globalRoomConflicts as $rc): ?>
                            <tr>
                                <td><span class="badge badge-danger">Room Clash</span></td>
                                <td><strong>Room <?= htmlspecialchars($rc['room_code']) ?></strong></td>
                                <td><?= htmlspecialchars($rc['day']) ?> &bull; Period <?= $rc['period_start'] ?>-<?= $rc['period_end'] ?></td>
                                <td><span class="badge badge-primary"><?= htmlspecialchars($rc['group1']) ?></span> &harr; <span class="badge badge-primary"><?= htmlspecialchars($rc['group2']) ?></span></td>
                                <td>Teachers: <?= htmlspecialchars($rc['teacher1']) ?>, <?= htmlspecialchars($rc['teacher2']) ?></td>
                                <td style="text-align: right;"><a href="routine-bulk-edit.php" class="btn btn-outline btn-sm">Resolve</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>