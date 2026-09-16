<?php
/**
 * Class Routine Management System (NasimSoft)
 * Teacher-Wise Bulk Routine Editor & Batch Deletion
 * Copyright (c) 2026 NasimSoft. All rights reserved.
 */

declare(strict_types=1);

$pageTitle = 'Teacher-Wise Bulk Routine Editor';
require_once __DIR__ . '/../includes/header.php';

$db = Database::getInstance();

// Load teachers and rooms for bulk reassignment
$teachers = $db->query("
    SELECT t.id, t.name, t.short_code, t.designation, d.name as dept_name
    FROM teachers t
    JOIN departments d ON t.department_id = d.id
    WHERE t.active = 1
    ORDER BY t.name ASC
")->fetchAll();

$rooms = $db->query("
    SELECT id, name, room_code, room_type, capacity
    FROM rooms
    WHERE active = 1
    ORDER BY room_code ASC
")->fetchAll();

$selectedTeacherId = (int)($_GET['teacher_id'] ?? ($teachers[0]['id'] ?? 0));
$academicYear = trim($_GET['academic_year'] ?? '2026');
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
    <div>
        <h2 style="color: var(--navy-blue); font-size: 22px; font-weight: 800;">Teacher-Wise Bulk Editor & Batch Delete</h2>
        <p style="color: var(--text-muted); font-size: 13px;">Manage, substitute, reassign rooms, or delete routine slots in bulk with live conflict prevention.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="routine-master.php" class="btn btn-outline">&#128197; Master Timetable</a>
        <a href="bulk-generate.php" class="btn btn-accent">&#9889; Auto Generator</a>
    </div>
</div>

<!-- Teacher Selector Card -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding: 16px 20px;">
        <form method="GET" action="" style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
            <label style="font-weight: 700; color: var(--navy-blue); white-space: nowrap;">Source Faculty Member:</label>
            <select name="teacher_id" id="sourceTeacherSelect" class="form-select" style="max-width: 440px;" onchange="this.form.submit()">
                <?php foreach ($teachers as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $selectedTeacherId === (int)$t['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['name']) ?> (<?= htmlspecialchars($t['short_code']) ?>) &bull; <?= htmlspecialchars($t['dept_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div style="display: flex; gap: 8px; align-items: center;">
                <label style="font-weight: 600; font-size: 13px;">Session:</label>
                <input type="text" name="academic_year" id="academicYearInput" class="form-control" style="width: 90px;" value="<?= htmlspecialchars($academicYear) ?>" onchange="this.form.submit()">
            </div>

            <div style="margin-left: auto; display: flex; gap: 10px; align-items: center;">
                <span id="badgeSlotCount" class="badge badge-primary" style="font-size: 13px;">0 Slots</span>
                <span id="badgeLoadCount" class="badge badge-accent" style="font-size: 13px;">0 Weekly Load</span>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Action Toolbar (Floating Bar when slots are selected) -->
<div class="card" id="bulkActionToolbar" style="margin-bottom: 20px; background: #0F172A; color: #FFF; border: none; box-shadow: 0 4px 14px rgba(0,0,0,0.15);">
    <div class="card-body" style="padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <span style="font-weight: 800; font-size: 15px; color: #38BDF8;">
                <span id="selectedCount">0</span> Slots Selected
            </span>
            <button type="button" class="btn btn-outline-light btn-sm" onclick="toggleSelectAll(true)">Select All</button>
            <button type="button" class="btn btn-outline-light btn-sm" onclick="toggleSelectAll(false)">Deselect All</button>
        </div>

        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <!-- Bulk Reassign Teacher -->
            <button type="button" class="btn btn-accent btn-sm" onclick="openReassignModal()">
                &#128104;&#8205;&#127979; Reassign Faculty
            </button>

            <!-- Bulk Reassign Room -->
            <button type="button" class="btn btn-primary btn-sm" onclick="openRoomModal()">
                &#127963; Swap Room
            </button>

            <!-- Bulk Delete -->
            <button type="button" class="btn btn-danger btn-sm" onclick="executeBulkDelete()">
                &#128465; Delete Selected
            </button>
        </div>
    </div>
</div>

<!-- Live Slots Preview Table -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 id="tableTitleHeader">&#128197; Live Scheduled Slots for Selected Faculty</h3>
        <span style="font-size: 12px; color: var(--text-muted);">Check individual slots to apply bulk actions</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" id="slotsTable">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;">
                            <input type="checkbox" id="masterCheckbox" onchange="toggleMasterCheckbox(this)">
                        </th>
                        <th>Day</th>
                        <th>Period(s)</th>
                        <th>Class Type</th>
                        <th>Group / Section</th>
                        <th>Subject</th>
                        <th>Allocated Room</th>
                        <th>Load</th>
                    </tr>
                </thead>
                <tbody id="slotsTableBody">
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 30px; color: var(--text-muted);">
                            Loading schedule data...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Reassign Faculty with Live Conflict Check -->
<div id="reassignModal" class="modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 100%; max-width: 540px; margin: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
        <div class="card-header" style="background: var(--navy-blue); color: #FFF;">
            <h3 style="color: #FFF;">&#128104;&#8205;&#127979; Bulk Substitute / Reassign Faculty</h3>
        </div>
        <div class="card-body">
            <p style="font-size: 13.5px; margin-bottom: 14px; color: var(--text-dark);">
                You are about to transfer <strong id="reassignCountText">0</strong> slot(s) to a replacement faculty member.
            </p>

            <div class="form-group">
                <label class="form-label" style="font-weight: 700;">Select Replacement Faculty:</label>
                <select id="targetTeacherSelect" class="form-select" onchange="runPreflightConflictCheck()">
                    <option value="">-- Select Teacher --</option>
                    <?php foreach ($teachers as $t): ?>
                        <?php if ((int)$t['id'] !== $selectedTeacherId): ?>
                            <option value="<?= $t['id'] ?>">
                                <?= htmlspecialchars($t['name']) ?> (<?= htmlspecialchars($t['short_code']) ?>) &bull; <?= htmlspecialchars($t['dept_name']) ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Live Pre-flight Conflict Result Box -->
            <div id="conflictAlertBox" style="display: none; padding: 12px; border-radius: 6px; margin-top: 14px; font-size: 13px;"></div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn btn-outline" onclick="closeReassignModal()">Cancel</button>
                <button type="button" id="btnConfirmReassign" class="btn btn-accent" onclick="confirmReassignTeacher()" disabled>
                    Confirm & Apply Transfer
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Bulk Room Swap -->
<div id="roomModal" class="modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 100%; max-width: 500px; margin: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
        <div class="card-header" style="background: var(--navy-blue); color: #FFF;">
            <h3 style="color: #FFF;">&#127963; Bulk Room / Lab Swap</h3>
        </div>
        <div class="card-body">
            <p style="font-size: 13.5px; margin-bottom: 14px; color: var(--text-dark);">
                Move <strong id="roomSwapCountText">0</strong> slot(s) to a different classroom or lab facility.
            </p>

            <div class="form-group">
                <label class="form-label" style="font-weight: 700;">Target Room / Lab Facility:</label>
                <select id="targetRoomSelect" class="form-select">
                    <option value="">-- Select Room --</option>
                    <?php foreach ($rooms as $r): ?>
                        <option value="<?= $r['id'] ?>">
                            <?= htmlspecialchars($r['room_code']) ?> (<?= htmlspecialchars($r['room_type']) ?>, Cap: <?= $r['capacity'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn btn-outline" onclick="closeRoomModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="confirmSwapRoom()">
                    Apply Room Swap
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentSlots = [];
const currentTeacherId = <?= $selectedTeacherId ?>;
const academicYear = "<?= htmlspecialchars($academicYear) ?>";

document.addEventListener('DOMContentLoaded', () => {
    loadTeacherSlots();
});

async function loadTeacherSlots() {
    const tbody = document.getElementById('slotsTableBody');
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:30px;">Loading schedule...</td></tr>';

    try {
        const res = await fetch(`../api/routine-bulk-edit.php?action=get_teacher_slots&teacher_id=${currentTeacherId}&academic_year=${academicYear}`);
        const data = await res.json();

        if (data.success) {
            currentSlots = data.slots;
            document.getElementById('badgeSlotCount').textContent = `${data.total_slots} Slots`;
            document.getElementById('badgeLoadCount').textContent = `${data.total_load} Weekly Load (${data.lab_count} Labs)`;
            renderSlotsTable(currentSlots);
        } else {
            tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; color:var(--danger); padding:30px;">${data.message}</td></tr>`;
        }
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; color:var(--danger); padding:30px;">Failed to load data: ${err.message}</td></tr>`;
    }
}

function renderSlotsTable(slots) {
    const tbody = document.getElementById('slotsTableBody');
    tbody.innerHTML = '';

    if (slots.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:30px; color:var(--text-muted);">No routine slots scheduled for this faculty member.</td></tr>';
        updateToolbar();
        return;
    }

    slots.forEach(s => {
        const tr = document.createElement('tr');
        const isLab = s.is_lab == 1;

        tr.innerHTML = `
            <td style="text-align: center;">
                <input type="checkbox" class="slot-checkbox" value="${s.id}" onchange="updateToolbar()">
            </td>
            <td><strong>${s.day}</strong></td>
            <td>
                <span class="badge badge-info" style="font-size: 11px;">P${s.period_start}${s.period_end > s.period_start ? ' - P'+s.period_end : ''}</span>
            </td>
            <td>
                <span class="badge ${isLab ? 'badge-accent' : 'badge-primary'}">
                    ${isLab ? 'LAB (2P)' : 'THEORY'}
                </span>
            </td>
            <td>
                <strong>${s.group_code}</strong><br>
                <small style="color:var(--text-muted);">${s.tech_name} (${s.sem_name}, ${s.shift_name})</small>
            </td>
            <td>
                <strong style="color:var(--navy-blue);">${s.subject_code}</strong><br>
                <span style="font-size:12px;">${s.subject_name}</span>
            </td>
            <td>
                <span class="badge" style="background:#F1F5F9; color:var(--text-dark); font-size:11.5px;">
                    ${s.room_code} (${s.room_type})
                </span>
            </td>
            <td><strong style="color:var(--dark-orange); font-size:13px;">${s.load_count}</strong></td>
        `;
        tbody.appendChild(tr);
    });

    updateToolbar();
}

function getSelectedSlotIds() {
    const checkboxes = document.querySelectorAll('.slot-checkbox:checked');
    return Array.from(checkboxes).map(cb => parseInt(cb.value));
}

function updateToolbar() {
    const count = getSelectedSlotIds().length;
    document.getElementById('selectedCount').textContent = count;
    const master = document.getElementById('masterCheckbox');

    const totalCheckboxes = document.querySelectorAll('.slot-checkbox').length;
    if (totalCheckboxes > 0 && count === totalCheckboxes) {
        master.checked = true;
        master.indeterminate = false;
    } else if (count > 0) {
        master.checked = false;
        master.indeterminate = true;
    } else {
        master.checked = false;
        master.indeterminate = false;
    }
}

function toggleMasterCheckbox(master) {
    const checkboxes = document.querySelectorAll('.slot-checkbox');
    checkboxes.forEach(cb => cb.checked = master.checked);
    updateToolbar();
}

function toggleSelectAll(select) {
    const checkboxes = document.querySelectorAll('.slot-checkbox');
    checkboxes.forEach(cb => cb.checked = select);
    updateToolbar();
}

// ---------------- Modal: Bulk Reassign Faculty ----------------
function openReassignModal() {
    const selected = getSelectedSlotIds();
    if (selected.length === 0) {
        alert('Please select at least one routine slot to reassign.');
        return;
    }
    document.getElementById('reassignCountText').textContent = selected.length;
    document.getElementById('targetTeacherSelect').value = '';
    document.getElementById('conflictAlertBox').style.display = 'none';
    document.getElementById('btnConfirmReassign').disabled = true;

    const modal = document.getElementById('reassignModal');
    modal.style.display = 'flex';
}

function closeReassignModal() {
    document.getElementById('reassignModal').style.display = 'none';
}

async function runPreflightConflictCheck() {
    const targetTeacherId = parseInt(document.getElementById('targetTeacherSelect').value);
    const box = document.getElementById('conflictAlertBox');
    const btn = document.getElementById('btnConfirmReassign');

    if (!targetTeacherId) {
        box.style.display = 'none';
        btn.disabled = true;
        return;
    }

    const slotIds = getSelectedSlotIds();
    box.style.display = 'block';
    box.style.background = '#EFF6FF';
    box.style.color = '#1E40AF';
    box.style.border = '1px solid #BFDBFE';
    box.innerHTML = '&#8987; Simulating schedule collision pre-flight check...';

    try {
        const res = await fetch('../api/routine-bulk-edit.php?action=preview_reassign', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                slot_ids: slotIds,
                target_teacher_id: targetTeacherId,
                academic_year: academicYear
            })
        });
        const result = await res.json();

        if (result.success) {
            if (result.has_conflicts) {
                box.style.background = '#FEF2F2';
                box.style.color = '#B91C1C';
                box.style.border = '1px solid #FECACA';

                let html = `<strong>&#9888; Conflict Detected (${result.conflict_count} slot collisions):</strong><ul style="margin-top:6px; padding-left:20px;">`;
                result.conflicts.forEach(c => {
                    html += `<li><strong>${c.day} (${c.period})</strong>: ${c.reason}</li>`;
                });
                html += '</ul><small>Cannot transfer to this teacher without creating schedule collisions.</small>';
                box.innerHTML = html;
                btn.disabled = true;
            } else {
                box.style.background = '#F0FDF4';
                box.style.color = '#166534';
                box.style.border = '1px solid #BBF7D0';
                box.innerHTML = '<strong>&#10004; 100% Conflict-Free:</strong> Selected faculty is fully available for all chosen periods.';
                btn.disabled = false;
            }
        } else {
            box.innerHTML = `Error: ${result.message}`;
            btn.disabled = true;
        }
    } catch (e) {
        box.innerHTML = `Verification error: ${e.message}`;
        btn.disabled = true;
    }
}

async function confirmReassignTeacher() {
    const slotIds = getSelectedSlotIds();
    const targetTeacherId = parseInt(document.getElementById('targetTeacherSelect').value);

    if (!confirm(`Confirm transferring ${slotIds.length} slot(s) to the selected faculty member?`)) {
        return;
    }

    try {
        const res = await fetch('../api/routine-bulk-edit.php?action=bulk_reassign_teacher', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ slot_ids: slotIds, target_teacher_id: targetTeacherId })
        });
        const result = await res.json();

        if (result.success) {
            alert(`Success: Reassigned ${result.updated_count} slot(s).`);
            closeReassignModal();
            loadTeacherSlots();
        } else {
            alert(`Error: ${result.message}`);
        }
    } catch (e) {
        alert(`Error: ${e.message}`);
    }
}

// ---------------- Modal: Bulk Room Swap ----------------
function openRoomModal() {
    const selected = getSelectedSlotIds();
    if (selected.length === 0) {
        alert('Please select at least one routine slot to change rooms.');
        return;
    }
    document.getElementById('roomSwapCountText').textContent = selected.length;
    document.getElementById('targetRoomSelect').value = '';

    const modal = document.getElementById('roomModal');
    modal.style.display = 'flex';
}

function closeRoomModal() {
    document.getElementById('roomModal').style.display = 'none';
}

async function confirmSwapRoom() {
    const slotIds = getSelectedSlotIds();
    const targetRoomId = parseInt(document.getElementById('targetRoomSelect').value);

    if (!targetRoomId) {
        alert('Please select a target classroom or lab.');
        return;
    }

    if (!confirm(`Confirm moving ${slotIds.length} slot(s) to the selected room?`)) {
        return;
    }

    try {
        const res = await fetch('../api/routine-bulk-edit.php?action=bulk_reassign_room', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ slot_ids: slotIds, target_room_id: targetRoomId })
        });
        const result = await res.json();

        if (result.success) {
            alert(`Success: Updated room for ${result.updated_count} slot(s).`);
            closeRoomModal();
            loadTeacherSlots();
        } else {
            alert(`Error: ${result.message}`);
        }
    } catch (e) {
        alert(`Error: ${e.message}`);
    }
}

// ---------------- Bulk Delete ----------------
async function executeBulkDelete() {
    const slotIds = getSelectedSlotIds();
    if (slotIds.length === 0) {
        alert('Please select at least one routine slot to delete.');
        return;
    }

    if (!confirm(`Warning: Are you sure you want to permanently delete these ${slotIds.length} routine slot(s)?`)) {
        return;
    }

    try {
        const res = await fetch('../api/routine-bulk-edit.php?action=bulk_delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ slot_ids: slotIds })
        });
        const result = await res.json();

        if (result.success) {
            alert(`Success: Deleted ${result.deleted_count} routine slot(s).`);
            loadTeacherSlots();
        } else {
            alert(`Error: ${result.message}`);
        }
    } catch (e) {
        alert(`Error: ${e.message}`);
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>