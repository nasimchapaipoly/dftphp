<?php
/**
 * Class Routine Management System (NasimSoft)
 * Visual Interactive Weekly Routine Builder & Cell Editor
 * Copyright (c) 2026 NasimSoft.
 */

declare(strict_types=1);

$pageTitle = 'Interactive Routine Builder';
require_once __DIR__ . '/../includes/header.php';

$db = Database::getInstance();
$selectedGroupId = (int)($_GET['group_id'] ?? 0);
$academicYear = trim($_GET['academic_year'] ?? getSetting('academic_year', '2026'));

// Fetch all active groups
$groups = $db->query("SELECT id, short_code, group_name FROM `groups` WHERE active = 1 ORDER BY short_code ASC")->fetchAll();
if ($selectedGroupId === 0 && !empty($groups)) {
    $selectedGroupId = (int)$groups[0]['id'];
}

// Fetch group metadata
$groupInfo = null;
if ($selectedGroupId > 0) {
    $stmt = $db->prepare("
        SELECT g.*, t.name as tech_name, t.short_name as tech_code,
               s.name as sem_name, s.numeric_level,
               sh.name as shift_name,
               d.name as dept_name, d.id as dept_id,
               t.id as tech_id, s.id as sem_id, sh.id as shift_id,
               i.id as institute_id, i.name as institute_name
        FROM `groups` g
        JOIN technologies t ON g.technology_id = t.id
        JOIN semesters s ON g.semester_id = s.id
        JOIN shifts sh ON g.shift_id = sh.id
        JOIN departments d ON t.department_id = d.id
        JOIN institutes i ON d.institute_id = i.id
        WHERE g.id = ?
    ");
    $stmt->execute([$selectedGroupId]);
    $groupInfo = $stmt->fetch();
}

// Fetch active working days (Sunday - Thursday, etc.)
$workingDays = $db->query("SELECT day_name, day_code FROM working_days WHERE is_active = 1 ORDER BY day_order ASC")->fetchAll();

// Fetch periods (1st to 7th) — filtered to the selected group's own shift,
// since Second Shift periods reuse the same period numbers (1-7) at
// different clock times and must never be mixed into the same grid.
$periodShiftId = (int)($groupInfo['shift_id'] ?? 1);
$periodsStmt = $db->prepare("SELECT period_number, period_name, start_time, end_time FROM periods WHERE active = 1 AND shift_id = ? ORDER BY period_number ASC");
$periodsStmt->execute([$periodShiftId]);
$periods = $periodsStmt->fetchAll();
if (empty($periods)) {
    $periods = [
        ['period_number' => 1, 'period_name' => '1st', 'start_time' => '08:00:00', 'end_time' => '08:45:00'],
        ['period_number' => 2, 'period_name' => '2nd', 'start_time' => '08:45:00', 'end_time' => '09:30:00'],
        ['period_number' => 3, 'period_name' => '3rd', 'start_time' => '09:30:00', 'end_time' => '10:15:00'],
        ['period_number' => 4, 'period_name' => '4th', 'start_time' => '10:15:00', 'end_time' => '11:00:00'],
        ['period_number' => 5, 'period_name' => '5th', 'start_time' => '11:00:00', 'end_time' => '11:45:00'],
        ['period_number' => 6, 'period_name' => '6th', 'start_time' => '11:45:00', 'end_time' => '12:30:00'],
        ['period_number' => 7, 'period_name' => '7th', 'start_time' => '12:30:00', 'end_time' => '13:15:00'],
    ];
}

// Fetch group curriculum subjects
$curriculumSubjects = [];
if ($selectedGroupId > 0) {
    $curStmt = $db->prepare("
        SELECT s.id as subject_id, s.name as subject_name, s.subject_code, s.subject_type,
               s.theory_load, s.practical_load, s.total_load,
               gs.teacher_id, gs.preferred_room_id, gs.is_lab_required,
               t.name as default_teacher_name, t.short_code as default_teacher_code,
               r.name as default_room_name, r.room_code as default_room_code
        FROM group_subjects gs
        JOIN subjects s ON gs.subject_id = s.id
        LEFT JOIN teachers t ON gs.teacher_id = t.id
        LEFT JOIN rooms r ON gs.preferred_room_id = r.id
        WHERE gs.group_id = ? AND gs.active = 1
        ORDER BY s.subject_code ASC
    ");
    $curStmt->execute([$selectedGroupId]);
    $curriculumSubjects = $curStmt->fetchAll();
}

// Fetch existing routine slots for this group
$existingSlots = [];
$totalRoutineLoad = 0;
if ($selectedGroupId > 0) {
    $rStmt = $db->prepare("
        SELECT r.*, s.name as subject_name, s.subject_code,
               t.name as teacher_name, t.short_code as teacher_code,
               rm.name as room_name, rm.room_code
        FROM routines r
        JOIN subjects s ON r.subject_id = s.id
        JOIN teachers t ON r.teacher_id = t.id
        JOIN rooms rm ON r.room_id = rm.id
        WHERE r.group_id = ? AND r.academic_year = ?
    ");
    $rStmt->execute([$selectedGroupId, $academicYear]);
    $allSlots = $rStmt->fetchAll();

    foreach ($allSlots as $slot) {
        $day = $slot['day'];
        $pStart = (int)$slot['period_start'];
        $pEnd = (int)$slot['period_end'];
        $totalRoutineLoad += (int)$slot['load_count'];

        for ($p = $pStart; $p <= $pEnd; $p++) {
            $existingSlots[$day][$p] = [
                'slot' => $slot,
                'is_span' => ($pStart !== $pEnd),
                'span_type' => ($p === $pStart) ? 'lead' : 'tail',
                'span_length' => ($pEnd - $pStart + 1)
            ];
        }
    }
}

$allTeachers = $db->query("SELECT id, name, short_code FROM teachers WHERE active = 1 ORDER BY name ASC")->fetchAll();
$allRooms = $db->query("SELECT id, name, room_code, room_type FROM rooms WHERE active = 1 ORDER BY room_code ASC")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="color: var(--navy-blue); font-size: 20px; font-weight: 700;">Interactive Routine Builder</h2>
        <p style="color: var(--text-muted); font-size: 13px;">Click any cell on the weekly routine matrix to schedule, move, or edit class slots.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <button class="btn btn-outline" onclick="App.printRoutine()">&#128424; Print Routine</button>
        <a href="<?= PUBLIC_URL ?>/index.php?group_id=<?= $selectedGroupId ?>&academic_year=<?= urlencode($academicYear) ?>" target="_blank" class="btn btn-primary">&#128065; Public Preview</a>
        <a href="group-subjects.php?group_id=<?= $selectedGroupId ?>" class="btn btn-outline">&#128214; Edit Curriculum</a>
    </div>
</div>

<!-- Group Selector & Info Card -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
        <form method="GET" action="" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <label style="font-weight: 700; color: var(--navy-blue); white-space: nowrap;">Academic Group:</label>
            <select name="group_id" class="form-select" style="max-width: 350px;" onchange="this.form.submit()">
                <?php foreach ($groups as $g): ?>
                    <option value="<?= $g['id'] ?>" <?= ($selectedGroupId === (int)$g['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($g['short_code']) ?> &bull; <?= htmlspecialchars($g['group_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label style="font-weight: 700; color: var(--navy-blue); white-space: nowrap;">Year:</label>
            <input type="text" name="academic_year" value="<?= htmlspecialchars($academicYear) ?>" class="form-control" style="max-width: 100px;" onchange="this.form.submit()">
            <noscript><button type="submit" class="btn btn-outline">Load</button></noscript>
        </form>

        <?php if ($groupInfo): ?>
            <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <span class="badge badge-accent" style="font-size: 13px;"><?= htmlspecialchars($groupInfo['tech_name']) ?></span>
                <span class="badge badge-primary" style="font-size: 13px;"><?= htmlspecialchars($groupInfo['sem_name']) ?> (<?= htmlspecialchars($groupInfo['shift_name']) ?>)</span>
                <span class="badge badge-success" style="font-size: 13px;">Scheduled Load: <?= $totalRoutineLoad ?></span>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Visual Routine Document Presentation -->
<div class="routine-document">
    <?php if ($groupInfo): ?>
        <div class="routine-header-block">
            <div class="institute-title"><?= htmlspecialchars($groupInfo['institute_name']) ?></div>
            <div class="routine-subtitle"><?= htmlspecialchars($groupInfo['sem_name']) ?> Class Routine 2026</div>
            <div style="font-size: 14px; font-weight: 600; color: var(--text-dark);">
                Department: <?= htmlspecialchars($groupInfo['tech_name']) ?> (<?= htmlspecialchars($groupInfo['shift_name']) ?>)
            </div>
            <div class="routine-meta-bar">
                <span>Group/Short Code: <strong><?= htmlspecialchars($groupInfo['short_code']) ?></strong></span>
                <span>Total Scheduled Load: <strong><?= $totalRoutineLoad ?></strong></span>
                <span>Academic Year: <strong>2026</strong></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Interactive Routine Table -->
    <div class="table-responsive">
        <table class="routine-table">
            <thead>
                <tr>
                    <th rowspan="2" class="day-col">Day / Period</th>
                    <?php foreach ($periods as $p): ?>
                        <th><?= htmlspecialchars($p['period_name']) ?></th>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <?php foreach ($periods as $p): ?>
                        <th style="font-size: 10.5px; font-weight: normal; color: #475569;">
                            <?= substr($p['start_time'], 0, 5) ?> - <?= substr($p['end_time'], 0, 5) ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($workingDays as $d): $dayName = $d['day_name']; ?>
                    <tr>
                        <td class="day-col"><?= htmlspecialchars($dayName) ?></td>
                        <?php 
                        $skipCount = 0;
                        foreach ($periods as $p): 
                            $pNum = (int)$p['period_number'];
                            if ($skipCount > 0) {
                                $skipCount--;
                                continue;
                            }

                            if (isset($existingSlots[$dayName][$pNum])):
                                $slotInfo = $existingSlots[$dayName][$pNum];
                                $slotData = $slotInfo['slot'];
                                $colspan = 1;
                                if ($slotInfo['is_span'] && $slotInfo['span_type'] === 'lead') {
                                    $colspan = $slotInfo['span_length'];
                                    $skipCount = $colspan - 1;
                                }
                        ?>
                            <td colspan="<?= $colspan ?>" class="routine-cell <?= $slotData['is_lab'] ? 'lab-cell' : '' ?>" style="cursor: pointer;" onclick="openSlotEditor('<?= $dayName ?>', <?= $pNum ?>, <?= htmlspecialchars(json_encode($slotData)) ?>)">
                                <span class="cell-code"><?= htmlspecialchars($slotData['subject_code']) ?></span>
                                <span class="cell-teacher"><?= htmlspecialchars($slotData['teacher_code']) ?></span>
                                <span class="cell-room"><?= htmlspecialchars($slotData['room_code']) ?></span>
                                <?php if ($slotData['is_lab']): ?>
                                    <small style="color: var(--dark-orange); font-size: 9px; font-weight: bold;">(Lab 2L)</small>
                                <?php endif; ?>
                            </td>
                        <?php else: ?>
                            <td class="routine-cell empty-cell" style="cursor: pointer; background: #FAFAFA;" onclick="openSlotEditor('<?= $dayName ?>', <?= $pNum ?>)" title="Click to schedule class">
                                <span style="color: #CBD5E1; font-size: 20px;">+</span>
                            </td>
                        <?php endif; endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Reference Teacher & Subject List (Under Routine Table) -->
    <div style="margin-top: 30px;">
        <h4 style="font-size: 14px; font-weight: 700; color: var(--navy-blue); margin-bottom: 8px; border-bottom: 1px solid var(--border-color); padding-bottom: 4px;">
            Teacher & Subject List
        </h4>
        <div class="table-responsive">
        <table class="table" style="font-size: 12px;">
            <thead>
                <tr style="background: #F1F5F9;">
                    <th style="width: 40px;">SL</th>
                    <th>Subject Name</th>
                    <th>Code</th>
                    <th>Teacher Name</th>
                    <th>Short Code</th>
                    <th>Class Type</th>
                    <th>Theory</th>
                    <th>Lab</th>
                    <th>Credit</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($curriculumSubjects)): ?>
                    <tr><td colspan="9" style="text-align: center; color: var(--text-muted);">No curriculum subjects attached yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($curriculumSubjects as $idx => $cs): ?>
                        <tr>
                            <td><?= $idx + 1 ?></td>
                            <td><strong><?= htmlspecialchars($cs['subject_name']) ?></strong></td>
                            <td><strong style="color: var(--navy-blue);"><?= htmlspecialchars($cs['subject_code']) ?></strong></td>
                            <td><?= htmlspecialchars($cs['default_teacher_name'] ?? 'To be assigned') ?></td>
                            <td><span class="badge badge-accent"><?= htmlspecialchars($cs['default_teacher_code'] ?? 'N/A') ?></span></td>
                            <td><?= htmlspecialchars($cs['subject_type']) ?></td>
                            <td><?= $cs['theory_load'] ?></td>
                            <td><?= $cs['practical_load'] ?></td>
                            <td><?= number_format((float)$cs['credit'], 1) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

</div>

<!-- Modal Dialog: Interactive Cell Routine Slot Editor -->
<div class="modal-overlay" id="slotModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h4 id="slotModalTitle">Schedule Class Period</h4>
            <button class="modal-close" onclick="App.closeModal('slotModal')">&times;</button>
        </div>
        <form id="slotForm" onsubmit="saveSlot(event)">
            <div class="modal-body">
                <input type="hidden" id="slotId" name="id" value="0">
                <input type="hidden" name="group_id" value="<?= $selectedGroupId ?>">
                <input type="hidden" name="academic_year" value="<?= htmlspecialchars($academicYear) ?>">

                <!-- Real-time conflict feedback container -->
                <div id="conflictAlert" style="display: none;" class="alert alert-danger"></div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Day *</label>
                        <select id="slotDay" name="day" class="form-select" required onchange="triggerConflictCheck()">
                            <?php foreach ($workingDays as $d): ?>
                                <option value="<?= htmlspecialchars($d['day_name']) ?>"><?= htmlspecialchars($d['day_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Start Period *</label>
                        <select id="slotPeriodStart" name="period_start" class="form-select" required onchange="onPeriodChange()">
                            <?php foreach ($periods as $p): ?>
                                <option value="<?= $p['period_number'] ?>">Period <?= $p['period_number'] ?> (<?= substr($p['start_time'], 0, 5) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Subject *</label>
                    <select id="slotSubject" name="subject_id" class="form-select" required onchange="onSubjectSelect()">
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($curriculumSubjects as $cs): ?>
                            <option value="<?= $cs['subject_id'] ?>" 
                                    data-teacher="<?= $cs['teacher_id'] ?>" 
                                    data-room="<?= $cs['preferred_room_id'] ?>"
                                    data-islab="<?= $cs['is_lab_required'] ?>"
                                    data-type="<?= $cs['subject_type'] ?>">
                                <?= htmlspecialchars($cs['subject_code']) ?> &bull; <?= htmlspecialchars($cs['subject_name']) ?> (<?= $cs['subject_type'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Teacher (Short Code) *</label>
                        <select id="slotTeacher" name="teacher_id" class="form-select" required onchange="triggerConflictCheck()">
                            <option value="">-- Select Teacher --</option>
                            <?php foreach ($allTeachers as $t): ?>
                                <option value="<?= $t['id'] ?>">
                                    <?= htmlspecialchars($t['name']) ?> (<?= htmlspecialchars($t['short_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Classroom / Lab *</label>
                        <select id="slotRoom" name="room_id" class="form-select" required onchange="triggerConflictCheck()">
                            <option value="">-- Select Room --</option>
                            <?php foreach ($allRooms as $r): ?>
                                <option value="<?= $r['id'] ?>">
                                    <?= htmlspecialchars($r['room_code']) ?> (<?= $r['room_type'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">End Period *</label>
                        <select id="slotPeriodEnd" name="period_end" class="form-select" required onchange="triggerConflictCheck()">
                            <?php foreach ($periods as $p): ?>
                                <option value="<?= $p['period_number'] ?>">Period <?= $p['period_number'] ?> (<?= substr($p['end_time'], 0, 5) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Publishing Status</label>
                        <select id="slotStatus" name="status" class="form-select">
                            <option value="PUBLISHED">PUBLISHED (Publicly Visible)</option>
                            <option value="DRAFT">DRAFT (Admin Only)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="background: #FFF7ED; padding: 10px 14px; border-radius: 6px; border: 1px solid var(--orange-light);">
                    <label style="display: flex; align-items: center; gap: 8px; font-weight: 700; color: var(--dark-orange); cursor: pointer;">
                        <input type="checkbox" id="slotIsLab" name="is_lab" value="1" onchange="onLabToggle()">
                        Lab Class (2 Consecutive Periods: e.g. 5th + 6th)
                    </label>
                    <small style="color: var(--text-dark); display: block; margin-top: 4px;">
                        Automatically occupies and verifies availability across both consecutive time periods.
                    </small>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: space-between;">
                <button type="button" id="btnDeleteSlot" class="btn btn-danger" style="display: none;" onclick="deleteCurrentSlot()">Delete Slot</button>
                <div style="display: flex; gap: 10px; margin-left: auto;">
                    <button type="button" class="btn btn-outline" onclick="App.closeModal('slotModal')">Cancel</button>
                    <button type="submit" id="btnSaveSlot" class="btn btn-accent">Save Slot</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function openSlotEditor(day, period, slotData = null) {
    document.getElementById('conflictAlert').style.display = 'none';
    document.getElementById('slotDay').value = day;
    document.getElementById('slotPeriodStart').value = period;
    
    if (slotData) {
        document.getElementById('slotModalTitle').innerText = 'Edit Class Slot';
        document.getElementById('slotId').value = slotData.id;
        document.getElementById('slotSubject').value = slotData.subject_id;
        document.getElementById('slotTeacher').value = slotData.teacher_id;
        document.getElementById('slotRoom').value = slotData.room_id;
        document.getElementById('slotPeriodEnd').value = slotData.period_end;
        document.getElementById('slotIsLab').checked = (parseInt(slotData.is_lab) === 1);
        document.getElementById('slotStatus').value = slotData.status;
        document.getElementById('btnDeleteSlot').style.display = 'inline-block';
    } else {
        document.getElementById('slotModalTitle').innerText = `Schedule Class - ${day}, Period ${period}`;
        document.getElementById('slotId').value = "0";
        document.getElementById('slotSubject').value = "";
        document.getElementById('slotTeacher').value = "";
        document.getElementById('slotRoom').value = "";
        document.getElementById('slotPeriodEnd').value = period;
        document.getElementById('slotIsLab').checked = false;
        document.getElementById('slotStatus').value = "PUBLISHED";
        document.getElementById('btnDeleteSlot').style.display = 'none';
    }

    App.openModal('slotModal');
    triggerConflictCheck();
}

function onSubjectSelect() {
    const sel = document.getElementById('slotSubject');
    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.value) {
        const teacherId = opt.getAttribute('data-teacher');
        const roomId = opt.getAttribute('data-room');
        const isLab = parseInt(opt.getAttribute('data-islab')) === 1;

        if (teacherId) document.getElementById('slotTeacher').value = teacherId;
        if (roomId) document.getElementById('slotRoom').value = roomId;
        document.getElementById('slotIsLab').checked = isLab;
        onLabToggle();
    }
    triggerConflictCheck();
}

function onLabToggle() {
    const isLab = document.getElementById('slotIsLab').checked;
    const startP = parseInt(document.getElementById('slotPeriodStart').value) || 1;
    if (isLab) {
        document.getElementById('slotPeriodEnd').value = Math.min(7, startP + 1);
    } else {
        document.getElementById('slotPeriodEnd').value = startP;
    }
    triggerConflictCheck();
}

function onPeriodChange() {
    if (document.getElementById('slotIsLab').checked) {
        onLabToggle();
    } else {
        document.getElementById('slotPeriodEnd').value = document.getElementById('slotPeriodStart').value;
    }
    triggerConflictCheck();
}

async function triggerConflictCheck() {
    const slotId = document.getElementById('slotId').value;
    const day = document.getElementById('slotDay').value;
    const pStart = document.getElementById('slotPeriodStart').value;
    const pEnd = document.getElementById('slotPeriodEnd').value;
    const teacherId = document.getElementById('slotTeacher').value;
    const roomId = document.getElementById('slotRoom').value;
    const groupId = "<?= $selectedGroupId ?>";

    if (!teacherId || !roomId || !day) return;

    try {
        const res = await App.ajax(`../api/conflict-check.php?exclude_id=${slotId}&day=${day}&period_start=${pStart}&period_end=${pEnd}&teacher_id=${teacherId}&room_id=${roomId}&group_id=${groupId}&academic_year=<?= urlencode($academicYear) ?>`);
        const alertBox = document.getElementById('conflictAlert');
        const saveBtn = document.getElementById('btnSaveSlot');

        if (res.conflict) {
            alertBox.innerHTML = `<strong>Conflict Detected:</strong> ${res.message}`;
            alertBox.style.display = 'block';
            saveBtn.disabled = true;
            saveBtn.classList.add('disabled');
        } else {
            alertBox.style.display = 'none';
            saveBtn.disabled = false;
            saveBtn.classList.remove('disabled');
        }
    } catch (e) {
        console.error("Conflict check error", e);
    }
}

async function saveSlot(e) {
    e.preventDefault();
    const form = document.getElementById('slotForm');
    const formData = new FormData(form);

    try {
        const res = await App.ajax('../api/routine.php?action=save_slot', {
            method: 'POST',
            body: formData
        });

        if (res.success) {
            App.showToast('Class slot successfully saved!', 'success');
            App.closeModal('slotModal');
            setTimeout(() => window.location.reload(), 500);
        } else {
            App.showToast(res.message || 'Failed to save slot', 'error');
        }
    } catch (err) {
        App.showToast('Server error while saving slot', 'error');
    }
}

async function deleteCurrentSlot() {
    const slotId = document.getElementById('slotId').value;
    if (!slotId || slotId === "0") return;

    App.confirm('Are you sure you want to delete this routine slot?', async () => {
        try {
            const res = await App.ajax(`../api/routine.php?action=delete_slot&id=${slotId}`);
            if (res.success) {
                App.showToast('Class slot deleted.', 'info');
                App.closeModal('slotModal');
                setTimeout(() => window.location.reload(), 400);
            } else {
                App.showToast(res.message, 'error');
            }
        } catch (err) {
            App.showToast('Error deleting slot', 'error');
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>