<?php
/**
 * Class Routine Management System (NasimSoft)
 * Bulk Routine Generator Console
 * Copyright (c) 2026 NasimSoft. All rights reserved.
 */

declare(strict_types=1);

$pageTitle = 'Bulk Routine Generator';
require_once __DIR__ . '/../includes/header.php';

$db = Database::getInstance();

$departments = $db->query("SELECT id, name FROM departments WHERE active = 1 ORDER BY name ASC")->fetchAll();
$technologies = $db->query("SELECT id, name, short_name FROM technologies WHERE active = 1 ORDER BY name ASC")->fetchAll();
$semesters = $db->query("SELECT id, name FROM semesters WHERE active = 1 ORDER BY numeric_level ASC")->fetchAll();
$shifts = $db->query("SELECT id, name FROM shifts WHERE active = 1 ORDER BY name ASC")->fetchAll();

$totalActiveGroups = (int)$db->query("SELECT COUNT(*) FROM `groups` WHERE active = 1")->fetchColumn();
$totalPublishedSlots = (int)$db->query("SELECT COUNT(*) FROM routines WHERE status = 'PUBLISHED'")->fetchColumn();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
    <div>
        <h2 style="color: var(--navy-blue); font-size: 22px; font-weight: 800;">Bulk Routine Generator</h2>
        <p style="color: var(--text-muted); font-size: 13.5px;">Schedule conflict-free routines institute-wide with zero room or teacher collisions.</p>
    </div>
    <div style="display: flex; gap: 12px;">
        <a href="routine-generate.php" class="btn btn-outline">&#9881; Single Group Generator</a>
        <a href="routine-master.php" class="btn btn-primary">&#128197; Master Timetable</a>
    </div>
</div>

<!-- Global System Statistics Overview -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-label">Total Active Groups</div>
        <div class="stat-value" style="color: var(--navy-blue);"><?= $totalActiveGroups ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Current Scheduled Slots</div>
        <div class="stat-value" style="color: var(--dark-orange);"><?= $totalPublishedSlots ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Scheduling Algorithm</div>
        <div class="stat-value" style="font-size: 18px; color: var(--success); padding-top: 6px;">MRV Backtracking CSP</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Consecutive Lab Enforcement</div>
        <div class="stat-value" style="font-size: 18px; color: var(--accent); padding-top: 6px;">100% Guaranteed</div>
    </div>
</div>

<!-- Scope Filter & Execution Controls Card -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <h3>&#127919; Target Scope Configuration</h3>
        <span class="badge badge-info">Select target segment or leave empty for Entire Institute</span>
    </div>
    <div class="card-body">
        <form id="bulkFilterForm">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Department</label>
                    <select name="department_id" id="department_id" class="form-select">
                        <option value="0">-- All Departments --</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Technology</label>
                    <select name="technology_id" id="technology_id" class="form-select">
                        <option value="0">-- All Technologies --</option>
                        <?php foreach ($technologies as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?> (<?= htmlspecialchars($t['short_name']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Semester</label>
                    <select name="semester_id" id="semester_id" class="form-select">
                        <option value="0">-- All Semesters --</option>
                        <?php foreach ($semesters as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Shift</label>
                    <select name="shift_id" id="shift_id" class="form-select">
                        <option value="0">-- All Shifts --</option>
                        <?php foreach ($shifts as $sh): ?>
                            <option value="<?= $sh['id'] ?>"><?= htmlspecialchars($sh['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Academic Year</label>
                    <input type="text" name="academic_year" id="academic_year" class="form-control" value="2026" required>
                </div>
            </div>

            <!-- Execution Options -->
            <div style="margin-top: 16px; padding: 14px; background: #F8FAFC; border-radius: 6px; border: 1px solid var(--border-color); display: flex; flex-wrap: wrap; gap: 24px; align-items: center;">
                <label style="display: flex; align-items: center; gap: 8px; font-weight: 700; color: var(--navy-blue); cursor: pointer; margin: 0;">
                    <input type="checkbox" id="clean_slate" name="clean_slate" value="1" checked>
                    Clean Slate (Wipe existing routines for selected scope before running)
                </label>
                <label style="display: flex; align-items: center; gap: 8px; font-weight: 700; color: var(--text-dark); cursor: pointer; margin: 0;">
                    <input type="checkbox" id="continue_on_conflict" name="continue_on_conflict" value="1" checked>
                    Continue queue on individual group conflict (Don't abort)
                </label>
            </div>

            <!-- Actions Bar -->
            <div style="margin-top: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div id="queueStatusBadge" style="font-size: 13.5px; font-weight: 600; color: var(--text-muted);">
                    Click "Preview Queue" or "Start Generation" to begin.
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" id="btnPreviewQueue" class="btn btn-outline" onclick="fetchQueue(false)">
                        &#128065; Preview Target Groups
                    </button>
                    <button type="button" id="btnStartGeneration" class="btn btn-accent" style="padding: 10px 24px; font-size: 14.5px;" onclick="startBulkGeneration()">
                        &#9889; START BULK GENERATION
                    </button>
                    <button type="button" id="btnStopGeneration" class="btn btn-danger" style="display: none;" onclick="stopBulkGeneration()">
                        &#9632; ABORT GENERATION
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Live Execution Dashboard (Shown when running or previewed) -->
<div id="executionDashboard" style="display: none;">
    <!-- Live Progress Bar Card -->
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-body" style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <div>
                    <span id="progressPercentText" style="font-size: 22px; font-weight: 800; color: var(--navy-blue);">0%</span>
                    <span id="progressFractionText" style="font-size: 14px; font-weight: 600; color: var(--text-muted); margin-left: 8px;">(0 / 0 Groups)</span>
                </div>
                <div id="liveTimerText" style="font-family: monospace; font-size: 15px; font-weight: 700; color: var(--dark-orange);">
                    Elapsed: 00:00.0
                </div>
            </div>

            <div style="width: 100%; height: 16px; background: #E2E8F0; border-radius: 999px; overflow: hidden; position: relative;">
                <div id="progressBarFill" style="width: 0%; height: 100%; background: linear-gradient(90deg, var(--navy-blue), var(--dark-orange)); border-radius: 999px; transition: width 0.3s ease;"></div>
            </div>

            <!-- Dynamic Live Metric Badges -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-top: 18px;">
                <div style="background: #F8FAFC; border: 1px solid var(--border-color); border-radius: 6px; padding: 12px; text-align: center;">
                    <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Total Target Groups</div>
                    <div id="statTotalGroups" style="font-size: 20px; font-weight: 800; color: var(--navy-blue);">0</div>
                </div>
                <div style="background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 6px; padding: 12px; text-align: center;">
                    <div style="font-size: 11px; font-weight: 700; color: #166534; text-transform: uppercase;">Slots Allocated</div>
                    <div id="statAllocatedSlots" style="font-size: 20px; font-weight: 800; color: #15803D;">0</div>
                </div>
                <div style="background: #FFF7ED; border: 1px solid var(--orange-light); border-radius: 6px; padding: 12px; text-align: center;">
                    <div style="font-size: 11px; font-weight: 700; color: var(--dark-orange); text-transform: uppercase;">Total Load Placed</div>
                    <div id="statTotalLoad" style="font-size: 20px; font-weight: 800; color: var(--dark-orange);">0</div>
                </div>
                <div style="background: #FEF2F2; border: 1px solid #FECACA; border-radius: 6px; padding: 12px; text-align: center;">
                    <div style="font-size: 11px; font-weight: 700; color: var(--danger); text-transform: uppercase;">Conflicts / Skipped</div>
                    <div id="statConflicts" style="font-size: 20px; font-weight: 800; color: var(--danger);">0</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Live Execution Console Log -->
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-header" style="background: #0F172A; color: #F8FAFC;">
            <h3 style="color: #F8FAFC; font-family: monospace; font-size: 14px;">&#128187; Real-Time Generation Terminal</h3>
            <button type="button" class="btn btn-outline btn-sm" style="color: #F8FAFC; border-color: #334155;" onclick="clearTerminal()">Clear</button>
        </div>
        <div class="card-body" style="background: #090D16; padding: 16px; border-radius: 0 0 8px 8px;">
            <div id="terminalLog" style="height: 260px; overflow-y: auto; font-family: 'Courier New', Courier, monospace; font-size: 12.5px; line-height: 1.5; color: #E2E8F0; white-space: pre-wrap;"></div>
        </div>
    </div>
</div>

<!-- Results Breakdown Modal / Section -->
<div id="resultsSection" class="card" style="display: none; margin-bottom: 30px;">
    <div class="card-header" style="background: #F0FDF4; border-bottom: 1px solid #BBF7D0;">
        <h3 style="color: #166534;">&#10004; Bulk Generation Execution Summary</h3>
        <div style="display: flex; gap: 8px;">
            <a href="routine-master.php" class="btn btn-primary btn-sm">&#128197; Open Master Timetable</a>
            <button type="button" class="btn btn-outline btn-sm" onclick="window.print()">&#128424; Print Report</button>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" id="summaryTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Group</th>
                        <th>Technology & Shift</th>
                        <th>Semester</th>
                        <th>Curriculum Items</th>
                        <th>Allocated Slots</th>
                        <th>Weekly Load</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="summaryTableBody"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
let bulkQueue = [];
let currentIndex = 0;
let isRunning = false;
let abortRequested = false;
let startTime = null;
let timerInterval = null;

let totalSlotsCount = 0;
let totalLoadCount = 0;
let totalConflictsCount = 0;
let executionResults = [];

function appendLog(message, type = 'info') {
    const terminal = document.getElementById('terminalLog');
    const time = new Date().toTimeString().split(' ')[0];
    let color = '#38BDF8'; // cyan

    if (type === 'success') color = '#4ADE80'; // green
    if (type === 'warning') color = '#FBBF24'; // amber
    if (type === 'error') color = '#F87171'; // red
    if (type === 'meta') color = '#94A3B8'; // gray

    const line = document.createElement('div');
    line.style.color = color;
    line.innerHTML = `<span style="color: #64748B;">[${time}]</span> ${message}`;
    terminal.appendChild(line);
    terminal.scrollTop = terminal.scrollHeight;
}

function clearTerminal() {
    document.getElementById('terminalLog').innerHTML = '';
}

function updateTimer() {
    if (!startTime) return;
    const elapsed = Date.now() - startTime;
    const mins = Math.floor(elapsed / 60000);
    const secs = Math.floor((elapsed % 60000) / 1000);
    const ms = Math.floor((elapsed % 1000) / 100);
    document.getElementById('liveTimerText').textContent = 
        `Elapsed: ${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}.${ms}`;
}

async function fetchQueue(silent = true) {
    const form = document.getElementById('bulkFilterForm');
    const params = new URLSearchParams(new FormData(form)).toString();

    document.getElementById('queueStatusBadge').innerHTML = '<span style="color: var(--navy-blue);">&#8987; Fetching target groups...</span>';

    try {
        const response = await fetch(`../api/bulk-generate.php?action=get_queue&${params}`);
        const data = await response.json();

        if (data.success) {
            bulkQueue = data.queue;
            document.getElementById('statTotalGroups').textContent = data.total_groups;
            document.getElementById('queueStatusBadge').innerHTML = 
                `<span style="color: var(--success); font-weight: 700;">&#10004; Ready: ${data.total_groups} target groups identified in selected scope.</span>`;

            if (!silent) {
                document.getElementById('executionDashboard').style.display = 'block';
                clearTerminal();
                appendLog(`Target queue populated: ${data.total_groups} groups ready for generation.`, 'info');
                data.queue.forEach((g, idx) => {
                    appendLog(`  #${idx + 1}: ${g.short_code} | ${g.tech_name} (${g.sem_name}, ${g.shift_name}) - ${g.subject_count} subjects, Load: ${g.total_curriculum_load}`, 'meta');
                });
            }
            return data.queue;
        } else {
            alert('Failed to retrieve target queue: ' + data.message);
            return [];
        }
    } catch (e) {
        alert('Error fetching target queue: ' + e.message);
        return [];
    }
}

async function startBulkGeneration() {
    if (isRunning) return;

    const queue = await fetchQueue(true);
    if (!queue || queue.length === 0) {
        alert('No active groups found in the selected scope to generate.');
        return;
    }

    if (!confirm(`Are you sure you want to generate routines for ${queue.length} group(s)?`)) {
        return;
    }

    // Initialize UI State
    isRunning = true;
    abortRequested = false;
    currentIndex = 0;
    totalSlotsCount = 0;
    totalLoadCount = 0;
    totalConflictsCount = 0;
    executionResults = [];

    document.getElementById('btnStartGeneration').style.display = 'none';
    document.getElementById('btnPreviewQueue').disabled = true;
    document.getElementById('btnStopGeneration').style.display = 'inline-block';
    document.getElementById('executionDashboard').style.display = 'block';
    document.getElementById('resultsSection').style.display = 'none';
    document.getElementById('summaryTableBody').innerHTML = '';

    clearTerminal();
    appendLog(`Initializing Bulk Routine Generation engine...`, 'info');
    appendLog(`Scope: ${queue.length} academic group(s) | Algorithm: Backtracking CSP`, 'meta');

    startTime = Date.now();
    timerInterval = setInterval(updateTimer, 100);

    // Step 1: Clean Slate scope clearing if checked
    const isCleanSlate = document.getElementById('clean_slate').checked;
    if (isCleanSlate) {
        appendLog(`[Clean Slate Active] Clearing existing routine slots for target groups...`, 'warning');
        const groupIds = queue.map(g => g.id);
        try {
            const clearRes = await fetch('../api/bulk-generate.php?action=clear_scope', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ group_ids: groupIds })
            });
            const clearData = await clearRes.json();
            if (clearData.success) {
                appendLog(`Successfully flushed ${clearData.deleted_slots} existing records. Starting from pristine state.`, 'success');
            }
        } catch (err) {
            appendLog(`Warning: Failed to flush existing records: ${err.message}`, 'error');
        }
    }

    // Step 2: Sequential Queue Runner
    processNextGroup();
}

async function processNextGroup() {
    if (abortRequested) {
        finishGeneration('Aborted by user request.');
        return;
    }

    if (currentIndex >= bulkQueue.length) {
        finishGeneration('Completed institute-wide generation.');
        return;
    }

    const group = bulkQueue[currentIndex];
    const groupNum = currentIndex + 1;
    const total = bulkQueue.length;
    const academicYear = document.getElementById('academic_year').value;

    appendLog(`[${groupNum}/${total}] Scheduling ${group.short_code} (${group.tech_name}, ${group.sem_name})...`, 'info');

    const formData = new FormData();
    formData.append('group_id', group.id);
    formData.append('academic_year', academicYear);
    // Overwrite is false since we already cleared scope cleanly
    formData.append('overwrite', 'false');

    try {
        const response = await fetch('../api/bulk-generate.php?action=process_group', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            const allocated = result.allocated_count || 0;
            const unallocated = result.unallocated_count || 0;
            const loadGen = result.total_load_generated || 0;

            totalSlotsCount += allocated;
            totalLoadCount += loadGen;
            totalConflictsCount += unallocated;

            executionResults.push({
                group: group,
                result: result,
                status: unallocated === 0 ? 'SUCCESS' : 'PARTIAL'
            });

            if (unallocated === 0) {
                appendLog(`  &#10004; Finished ${group.short_code}: Placed ${allocated} slots (${loadGen} loads) in ${result.execution_time_ms}ms`, 'success');
            } else {
                appendLog(`  &#9888; Partial ${group.short_code}: Placed ${allocated} slots, but ${unallocated} subject(s) conflicted.`, 'warning');
                result.unallocated_tasks.forEach(ut => {
                    appendLog(`     - [${ut.subject_code}] ${ut.subject_name}: ${ut.reason}`, 'error');
                });
            }
        } else {
            totalConflictsCount++;
            executionResults.push({
                group: group,
                result: result,
                status: 'FAILED'
            });
            appendLog(`  &#10008; Failed for ${group.short_code}: ${result.message}`, 'error');

            const continueOnConflict = document.getElementById('continue_on_conflict').checked;
            if (!continueOnConflict) {
                finishGeneration(`Stopped due to failure on group ${group.short_code}.`);
                return;
            }
        }
    } catch (err) {
        totalConflictsCount++;
        appendLog(`  &#10008; Network error on ${group.short_code}: ${err.message}`, 'error');
    }

    currentIndex++;
    updateProgressUI();

    // Small delay to let DOM render and avoid locking UI thread
    setTimeout(processNextGroup, 60);
}

function updateProgressUI() {
    const total = bulkQueue.length;
    const percent = Math.round((currentIndex / total) * 100);

    document.getElementById('progressBarFill').style.width = `${percent}%`;
    document.getElementById('progressPercentText').textContent = `${percent}%`;
    document.getElementById('progressFractionText').textContent = `(${currentIndex} / ${total} Groups)`;

    document.getElementById('statAllocatedSlots').textContent = totalSlotsCount;
    document.getElementById('statTotalLoad').textContent = totalLoadCount;
    document.getElementById('statConflicts').textContent = totalConflictsCount;
}

function stopBulkGeneration() {
    if (confirm('Are you sure you want to stop the generation queue? Already placed slots will remain saved.')) {
        abortRequested = true;
        appendLog('Stop signal received. Aborting after current operation...', 'warning');
    }
}

function finishGeneration(finalMsg) {
    isRunning = false;
    clearInterval(timerInterval);

    document.getElementById('btnStartGeneration').style.display = 'inline-block';
    document.getElementById('btnPreviewQueue').disabled = false;
    document.getElementById('btnStopGeneration').style.display = 'none';

    appendLog('========================================================', 'meta');
    appendLog(`[COMPLETE] ${finalMsg}`, 'success');
    appendLog(`Summary: ${totalSlotsCount} slots placed, ${totalLoadCount} total loads across ${currentIndex} groups.`, 'success');

    // Build Results Breakdown Table
    const tbody = document.getElementById('summaryTableBody');
    tbody.innerHTML = '';

    executionResults.forEach((res, i) => {
        const g = res.group;
        const r = res.result;
        const tr = document.createElement('tr');

        let statusBadge = `<span class="badge badge-success">Conflict-Free</span>`;
        if (res.status === 'PARTIAL') {
            statusBadge = `<span class="badge badge-accent">${r.unallocated_count} Unplaced</span>`;
        } else if (res.status === 'FAILED') {
            statusBadge = `<span class="badge badge-danger">Failed</span>`;
        }

        tr.innerHTML = `
            <td>${i + 1}</td>
            <td><strong>${g.short_code}</strong></td>
            <td>${g.tech_name} &bull; <small>${g.shift_name}</small></td>
            <td>${g.sem_name}</td>
            <td>${g.subject_count} subjects</td>
            <td><strong style="color: var(--navy-blue);">${r.allocated_count || 0}</strong></td>
            <td><strong style="color: var(--dark-orange);">${r.total_load_generated || 0}</strong></td>
            <td>${statusBadge}</td>
            <td>
                <a href="routine-create.php?group_id=${g.id}" class="btn btn-outline btn-sm" target="_blank">&#128197; Grid</a>
            </td>
        `;
        tbody.appendChild(tr);
    });

    document.getElementById('resultsSection').style.display = 'block';
    document.getElementById('resultsSection').scrollIntoView({ behavior: 'smooth' });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>