<?php
/**
 * Class Routine Management System (NasimSoft)
 * Intelligent Auto Routine Generator
 * Copyright (c) 2026 NasimSoft.
 */

declare(strict_types=1);

$pageTitle = 'Auto Routine Generator';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/generator.php';

$db = Database::getInstance();
$generator = new RoutineGenerator($db);

$selectedGroupId = (int)($_GET['group_id'] ?? 0);
$allGroups = $db->query("
    SELECT g.id, g.short_code, g.group_name, t.name as tech_name, s.name as sem_name, sh.name as shift_name
    FROM `groups` g
    JOIN technologies t ON g.technology_id = t.id
    JOIN semesters s ON g.semester_id = s.id
    JOIN shifts sh ON g.shift_id = sh.id
    WHERE g.active = 1
    ORDER BY g.short_code ASC
")->fetchAll();

if ($selectedGroupId === 0 && !empty($allGroups)) {
    $selectedGroupId = (int)$allGroups[0]['id'];
}

$generationResult = null;

// Trigger Generator Execution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $targetGroupId = (int)($_POST['group_id'] ?? 0);
    $overwrite = isset($_POST['overwrite_existing']) ? (bool)$_POST['overwrite_existing'] : true;
    $academicYear = trim($_POST['academic_year'] ?? getSetting('academic_year', '2026'));

    if ($targetGroupId > 0) {
        $generationResult = $generator->generateForGroup($targetGroupId, $academicYear, $overwrite);
        if ($generationResult['success']) {
            if ($generationResult['unallocated_count'] === 0) {
                setFlash('success', "Routine generated successfully! All {$generationResult['allocated_count']} slots ({$generationResult['total_load_generated']} loads) scheduled conflict-free.");
            } else {
                setFlash('warning', "Generated {$generationResult['allocated_count']} slots, but {$generationResult['unallocated_count']} classes could not be placed due to external conflicts.");
            }
        } else {
            setFlash('error', $generationResult['message']);
        }
    }
}

// Fetch Group Curriculum details
$curriculumSubjects = [];
$totalCurriculumLoad = 0;
if ($selectedGroupId > 0) {
    $cStmt = $db->prepare("
        SELECT gs.*, s.name as subject_name, s.subject_code, s.subject_type, s.theory_load, s.practical_load, s.total_load,
               t.name as teacher_name, t.short_code as teacher_code,
               r.room_code
        FROM group_subjects gs
        JOIN subjects s ON gs.subject_id = s.id
        LEFT JOIN teachers t ON gs.teacher_id = t.id
        LEFT JOIN rooms r ON gs.preferred_room_id = r.id
        WHERE gs.group_id = ? AND gs.active = 1
        ORDER BY s.subject_code ASC
    ");
    $cStmt->execute([$selectedGroupId]);
    $curriculumSubjects = $cStmt->fetchAll();
    foreach ($curriculumSubjects as $cs) {
        $totalCurriculumLoad += (int)$cs['total_load'];
    }
}

$existingSlotsCount = (int)$db->query("SELECT COUNT(*) FROM routines WHERE group_id = {$selectedGroupId}")->fetchColumn();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="color: var(--navy-blue); font-size: 20px; font-weight: 700;">Intelligent Automatic Routine Generator</h2>
        <p style="color: var(--text-muted); font-size: 13px;">Constraint-Satisfaction CSP solver: schedules 100% conflict-free routines with consecutive lab allocation.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="bulk-generate.php" class="btn btn-accent">&#9889; Bulk Generate Entire Institute</a>
        <a href="routine-create.php?group_id=<?= $selectedGroupId ?>" class="btn btn-outline">&#128197; Open Routine Grid</a>
    </div>
</div>

<!-- Group Selector Bar -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding: 16px 20px;">
        <form method="GET" action="" style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
            <label style="font-weight: 700; color: var(--navy-blue); white-space: nowrap;">Target Academic Group:</label>
            <select name="group_id" class="form-select" style="max-width: 420px;" onchange="this.form.submit()">
                <?php foreach ($allGroups as $g): ?>
                    <option value="<?= $g['id'] ?>" <?= ($selectedGroupId === (int)$g['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($g['short_code']) ?> &bull; <?= htmlspecialchars($g['tech_name']) ?> (<?= htmlspecialchars($g['sem_name']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <noscript><button type="submit" class="btn btn-outline">Select</button></noscript>
            <div style="margin-left: auto; display: flex; gap: 12px; align-items: center;">
                <span class="badge badge-info" style="font-size: 13px;">Curriculum: <?= count($curriculumSubjects) ?> Subjects</span>
                <span class="badge badge-primary" style="font-size: 13px;">Total Load: <?= $totalCurriculumLoad ?></span>
                <span class="badge <?= $existingSlotsCount > 0 ? 'badge-accent' : 'badge-danger' ?>" style="font-size: 13px;">Currently Scheduled: <?= $existingSlotsCount ?></span>
            </div>
        </form>
    </div>
</div>

<div class="split-panel-reverse">
    <!-- Generator Trigger Card -->
    <div class="card">
        <div class="card-header">
            <h3>&#9881; Execution Parameters</h3>
        </div>
        <form method="POST" action="">
            <div class="card-body">
                <?= csrfField() ?>
                <input type="hidden" name="group_id" value="<?= $selectedGroupId ?>">

                <div class="form-group">
                    <label class="form-label">Academic Year</label>
                    <input type="text" name="academic_year" class="form-control" value="2026" required>
                </div>

                <div class="form-group" style="background: #F8FAFC; padding: 12px; border-radius: 6px; border: 1px solid var(--border-color);">
                    <label style="display: flex; align-items: center; gap: 8px; font-weight: 700; color: var(--navy-blue); cursor: pointer;">
                        <input type="checkbox" name="overwrite_existing" value="1" checked>
                        Clean Slate (Overwrite existing slots)
                    </label>
                    <small style="color: var(--text-muted); display: block; margin-top: 4px;">
                        Replaces any existing drafts or slots for this group to generate a fresh, mathematically optimized timetable.
                    </small>
                </div>

                <div style="margin-top: 20px;">
                    <div style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px;">Enforced Solver Rules:</div>
                    <ul style="font-size: 12px; color: var(--text-dark); padding-left: 18px; line-height: 1.6;">
                        <li>Zero Teacher Overlap (Hard Constraint)</li>
                        <li>Zero Classroom/Lab Overlap (Hard Constraint)</li>
                        <li>Zero Group Clashes (Hard Constraint)</li>
                        <li>Labs strictly scheduled in 2 consecutive periods</li>
                        <li>Equal weekly load distribution across days</li>
                    </ul>
                </div>

                <button type="submit" class="btn btn-accent" style="width: 100%; margin-top: 20px; padding: 12px; font-size: 15px;">
                    &#9889; GENERATE ROUTINE NOW
                </button>
            </div>
        </form>
    </div>

    <!-- Curriculum & Results Panel -->
    <div class="card">
        <div class="card-header">
            <h3>&#128218; Subjects to be Scheduled (<?= count($curriculumSubjects) ?>)</h3>
            <span class="badge badge-success">Target Load: <?= $totalCurriculumLoad ?></span>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Subject Name</th>
                            <th>Class Type</th>
                            <th>Theory</th>
                            <th>Lab</th>
                            <th>Total Load</th>
                            <th>Assigned Faculty</th>
                            <th>Preferred Room</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($curriculumSubjects)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 25px; color: var(--text-muted);">
                                    No subjects assigned. Please go to <a href="group-subjects.php?group_id=<?= $selectedGroupId ?>">Group Curriculum Setup</a>.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($curriculumSubjects as $cs): ?>
                                <tr>
                                    <td><strong style="color: var(--navy-blue);"><?= htmlspecialchars($cs['subject_code']) ?></strong></td>
                                    <td><strong><?= htmlspecialchars($cs['subject_name']) ?></strong></td>
                                    <td>
                                        <span class="badge <?= $cs['is_lab_required'] ? 'badge-accent' : 'badge-primary' ?>">
                                            <?= $cs['is_lab_required'] ? 'Lab (2 Consec)' : htmlspecialchars($cs['subject_type']) ?>
                                        </span>
                                    </td>
                                    <td><?= $cs['theory_load'] ?></td>
                                    <td><?= $cs['practical_load'] ?></td>
                                    <td><strong style="color: var(--dark-orange); font-size: 13.5px;"><?= $cs['total_load'] ?></strong></td>
                                    <td><?= htmlspecialchars($cs['teacher_name'] ?? 'Auto') ?> (<strong><?= htmlspecialchars($cs['teacher_code'] ?? 'TBD') ?></strong>)</td>
                                    <td><span class="badge badge-primary"><?= htmlspecialchars($cs['room_code'] ?? 'Auto') ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Generation Output Card if Generated -->
<?php if ($generationResult): ?>
    <div class="card" style="margin-top: 24px;">
        <div class="card-header">
            <h3>&#10004; Routine Generation Results Report</h3>
            <a href="routine-create.php?group_id=<?= $selectedGroupId ?>" class="btn btn-primary btn-sm">Inspect Visual Timetable &rarr;</a>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 20px;">
                <div style="background: #F0FDF4; padding: 14px; border-radius: 6px; border: 1px solid #BBF7D0; text-align: center;">
                    <div style="font-size: 11px; font-weight: 700; color: #166534;">SUCCESSFULLY PLACED</div>
                    <div style="font-size: 26px; font-weight: 800; color: #15803D;"><?= $generationResult['allocated_count'] ?> slots</div>
                </div>
                <div style="background: #FFF7ED; padding: 14px; border-radius: 6px; border: 1px solid var(--orange-light); text-align: center;">
                    <div style="font-size: 11px; font-weight: 700; color: var(--dark-orange);">TOTAL LOAD GENERATED</div>
                    <div style="font-size: 26px; font-weight: 800; color: var(--dark-orange);"><?= $generationResult['total_load_generated'] ?> loads</div>
                </div>
                <div style="background: <?= $generationResult['unallocated_count'] > 0 ? '#FEF2F2' : '#F8FAFC' ?>; padding: 14px; border-radius: 6px; border: 1px solid var(--border-color); text-align: center;">
                    <div style="font-size: 11px; font-weight: 700; color: <?= $generationResult['unallocated_count'] > 0 ? 'var(--danger)' : 'var(--text-muted)' ?>;">CONFLICTS / UNASSIGNED</div>
                    <div style="font-size: 26px; font-weight: 800; color: <?= $generationResult['unallocated_count'] > 0 ? 'var(--danger)' : 'var(--navy-blue)' ?>;"><?= $generationResult['unallocated_count'] ?></div>
                </div>
            </div>

            <?php if (!empty($generationResult['unallocated_tasks'])): ?>
                <div class="alert alert-danger">
                    <strong>Notice:</strong> Some curriculum subjects could not be placed due to external faculty or room unavailability.
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Code</th>
                                <th>Type</th>
                                <th>Reason for Conflict</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($generationResult['unallocated_tasks'] as $ut): ?>
                                <tr>
                                    <td><?= htmlspecialchars($ut['subject_name']) ?></td>
                                    <td><span class="badge badge-accent"><?= htmlspecialchars($ut['subject_code']) ?></span></td>
                                    <td><?= $ut['is_lab'] ? 'Lab (2 Consec)' : 'Theory' ?></td>
                                    <td style="color: var(--danger); font-weight: 600;"><?= htmlspecialchars($ut['reason']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>