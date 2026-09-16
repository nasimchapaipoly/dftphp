<?php
/**
 * Class Routine Management System (NasimSoft)
 * Period Management (per Shift) — Add / Edit / Delete class periods
 * Copyright (c) 2026 NasimSoft.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$db = Database::getInstance();
$editId = (int)($_GET['edit'] ?? 0);
$deleteId = (int)($_GET['delete'] ?? 0);

// Delete Action
if ($deleteId > 0 && verifyCsrfToken($_GET['csrf_token'] ?? '')) {
    $inUse = (int)$db->query("SELECT COUNT(*) FROM routines")->fetchColumn();
    $stmt = $db->prepare("DELETE FROM periods WHERE id = ?");
    $stmt->execute([$deleteId]);
    logAudit('Delete Period', 'Periods', $deleteId, 'Deleted class period');
    setFlash('success', 'Period deleted successfully.');
    header('Location: periods.php?shift_id=' . (int)($_GET['shift_id'] ?? 0));
    exit;
}

// Add or Update Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Security token expired. Please try again.');
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $shiftId = (int)($_POST['shift_id'] ?? 0);
        $periodNumber = (int)($_POST['period_number'] ?? 0);
        $periodName = trim($_POST['period_name'] ?? '');
        $startTime = trim($_POST['start_time'] ?? '');
        $endTime = trim($_POST['end_time'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;

        if ($shiftId <= 0 || $periodNumber <= 0 || empty($periodName) || empty($startTime) || empty($endTime)) {
            setFlash('error', 'Shift, Period Number, Period Name, Start Time, and End Time are all required.');
        } elseif ($startTime >= $endTime) {
            setFlash('error', 'End Time must be after Start Time.');
        } else {
            $dupCheck = $db->prepare("SELECT id FROM periods WHERE shift_id = ? AND period_number = ? AND id != ?");
            $dupCheck->execute([$shiftId, $periodNumber, $id]);
            if ($dupCheck->fetch()) {
                setFlash('error', "Period number {$periodNumber} already exists for this shift.");
            } else {
                if ($id > 0) {
                    $stmt = $db->prepare("UPDATE periods SET shift_id = ?, period_number = ?, period_name = ?, start_time = ?, end_time = ?, active = ? WHERE id = ?");
                    $stmt->execute([$shiftId, $periodNumber, $periodName, $startTime, $endTime, $active, $id]);
                    logAudit('Update Period', 'Periods', $id, "Updated period {$periodName}");
                    setFlash('success', 'Period updated successfully.');
                } else {
                    $stmt = $db->prepare("INSERT INTO periods (shift_id, period_number, period_name, start_time, end_time, active) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$shiftId, $periodNumber, $periodName, $startTime, $endTime, $active]);
                    $newId = (int)$db->lastInsertId();
                    logAudit('Create Period', 'Periods', $newId, "Created period {$periodName}");
                    setFlash('success', 'Period created successfully.');
                }
                header('Location: periods.php?shift_id=' . $shiftId);
                exit;
            }
        }
    }
}

$editPeriod = null;
if ($editId > 0) {
    $stmt = $db->prepare("SELECT * FROM periods WHERE id = ?");
    $stmt->execute([$editId]);
    $editPeriod = $stmt->fetch();
}

$shifts = $db->query("SELECT id, name, code FROM shifts WHERE active = 1 ORDER BY id ASC")->fetchAll();
$selectedShiftId = (int)($_GET['shift_id'] ?? ($editPeriod['shift_id'] ?? ($shifts[0]['id'] ?? 0)));

$periods = [];
if ($selectedShiftId > 0) {
    $pStmt = $db->prepare("SELECT * FROM periods WHERE shift_id = ? ORDER BY period_number ASC");
    $pStmt->execute([$selectedShiftId]);
    $periods = $pStmt->fetchAll();
}

// Suggest the next available period number for the "Add" button default
$nextPeriodNumber = 1;
foreach ($periods as $p) {
    if ((int)$p['period_number'] >= $nextPeriodNumber) {
        $nextPeriodNumber = (int)$p['period_number'] + 1;
    }
}

$pageTitle = 'Period Management';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
    <div>
        <h2 style="color: var(--navy); font-size: 20px; font-weight: 700;">Period Management</h2>
        <p style="color: var(--text-muted); font-size: 13px;">Define class period timings separately for each shift — add, edit, or remove periods.</p>
    </div>
    <button class="btn btn-accent" onclick="document.getElementById('periodNumberInput').value=<?= $nextPeriodNumber ?>; App.openModal('periodModal')">&#10133; Add Period</button>
</div>

<!-- Shift Tabs -->
<div class="tabs" style="background: var(--surface); border-radius: var(--radius) var(--radius) 0 0; border: 1px solid var(--border-color); border-bottom: none;">
    <?php foreach ($shifts as $sh): ?>
        <a href="periods.php?shift_id=<?= $sh['id'] ?>" class="tab-btn <?= $selectedShiftId === (int)$sh['id'] ? 'active' : '' ?>" style="text-decoration: none; display: inline-block;">
            <?= htmlspecialchars($sh['name']) ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="card" style="border-top-left-radius: 0; border-top-right-radius: 0; margin-top: 0;">
    <div class="card-header">
        <h3>&#9200; <?= htmlspecialchars($shifts[array_search($selectedShiftId, array_column($shifts, 'id'))]['name'] ?? 'Shift') ?> — Periods</h3>
        <span class="badge badge-info"><?= count($periods) ?> periods configured</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>Period Name</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th style="width: 130px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($periods)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 25px; color: var(--text-muted);">
                                No periods configured for this shift yet. Click "Add Period" to create the first one.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($periods as $p):
                            $mins = (strtotime($p['end_time']) - strtotime($p['start_time'])) / 60;
                        ?>
                            <tr>
                                <td><strong style="color: var(--navy);"><?= $p['period_number'] ?></strong></td>
                                <td><?= htmlspecialchars($p['period_name']) ?></td>
                                <td><?= date('g:i A', strtotime($p['start_time'])) ?></td>
                                <td><?= date('g:i A', strtotime($p['end_time'])) ?></td>
                                <td><span class="badge badge-primary"><?= (int)$mins ?> min</span></td>
                                <td>
                                    <span class="badge <?= $p['active'] ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $p['active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <a href="periods.php?shift_id=<?= $selectedShiftId ?>&edit=<?= $p['id'] ?>" class="btn btn-outline btn-sm">&#9998; Edit</a>
                                    <button class="btn btn-danger btn-sm" onclick="App.confirm('Delete this period? Any routine slots already scheduled in it will be orphaned.', () => { window.location.href='periods.php?shift_id=<?= $selectedShiftId ?>&delete=<?= $p['id'] ?>&csrf_token=<?= generateCsrfToken() ?>'; })">&#128465;</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Period Modal -->
<div class="modal-overlay <?= $editPeriod ? 'active' : '' ?>" id="periodModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h4><?= $editPeriod ? 'Edit Period' : 'Add New Period' ?></h4>
            <button class="modal-close" onclick="<?= $editPeriod ? "window.location.href='periods.php?shift_id={$selectedShiftId}'" : "App.closeModal('periodModal')" ?>">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $editPeriod['id'] ?? 0 ?>">
                <div class="form-group">
                    <label class="form-label">Shift *</label>
                    <select name="shift_id" class="form-select" required>
                        <?php foreach ($shifts as $sh): ?>
                            <option value="<?= $sh['id'] ?>" <?= (($editPeriod['shift_id'] ?? $selectedShiftId) == $sh['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sh['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Period Number *</label>
                        <input type="number" id="periodNumberInput" name="period_number" class="form-control" min="1" max="20" required value="<?= htmlspecialchars((string)($editPeriod['period_number'] ?? $nextPeriodNumber)) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Period Name *</label>
                        <input type="text" name="period_name" class="form-control" required value="<?= htmlspecialchars($editPeriod['period_name'] ?? '') ?>" placeholder="e.g. 1st Period">
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Start Time *</label>
                        <input type="time" name="start_time" class="form-control" required value="<?= htmlspecialchars(substr($editPeriod['start_time'] ?? '', 0, 5)) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Time *</label>
                        <input type="time" name="end_time" class="form-control" required value="<?= htmlspecialchars(substr($editPeriod['end_time'] ?? '', 0, 5)) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; cursor: pointer;">
                        <input type="checkbox" name="active" value="1" <?= (!isset($editPeriod['active']) || $editPeriod['active']) ? 'checked' : '' ?>>
                        Period is Active
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="<?= $editPeriod ? "window.location.href='periods.php?shift_id={$selectedShiftId}'" : "App.closeModal('periodModal')" ?>">Cancel</button>
                <button type="submit" class="btn btn-accent"><?= $editPeriod ? 'Update Period' : 'Save Period' ?></button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
