<?php
/**
 * Class Routine Management System (NasimSoft)
 * Shift Management
 * Copyright (c) 2026 NasimSoft.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$db = Database::getInstance();
$editId = (int)($_GET['edit'] ?? 0);
$deleteId = (int)($_GET['delete'] ?? 0);

if ($deleteId > 0 && verifyCsrfToken($_GET['csrf_token'] ?? '')) {
    $stmt = $db->prepare("DELETE FROM shifts WHERE id = ?");
    $stmt->execute([$deleteId]);
    logAudit('Delete Shift', 'Shifts', $deleteId, 'Deleted shift');
    setFlash('success', 'Shift deleted successfully.');
    header('Location: shifts.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Security token expired. Please try again.');
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;

        if (empty($name) || empty($code)) {
            setFlash('error', 'Shift Name and Code are required.');
        } else {
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE shifts SET name = ?, code = ?, active = ? WHERE id = ?");
                $stmt->execute([$name, $code, $active, $id]);
                logAudit('Update Shift', 'Shifts', $id, "Updated shift {$name}");
                setFlash('success', 'Shift updated successfully.');
            } else {
                $stmt = $db->prepare("INSERT INTO shifts (name, code, active) VALUES (?, ?, ?)");
                $stmt->execute([$name, $code, $active]);
                $newId = (int)$db->lastInsertId();
                logAudit('Create Shift', 'Shifts', $newId, "Created shift {$name}");
                setFlash('success', 'Shift created successfully.');
            }
            header('Location: shifts.php');
            exit;
        }
    }
}

$editShift = null;
if ($editId > 0) {
    $stmt = $db->prepare("SELECT * FROM shifts WHERE id = ?");
    $stmt->execute([$editId]);
    $editShift = $stmt->fetch();
}

$shifts = $db->query("
    SELECT sh.*, (SELECT COUNT(*) FROM `groups` WHERE shift_id = sh.id) as group_count,
           (SELECT COUNT(*) FROM periods WHERE shift_id = sh.id) as period_count
    FROM shifts sh 
    ORDER BY sh.id ASC
")->fetchAll();
$pageTitle = 'Shifts';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="color: var(--navy-blue); font-size: 20px; font-weight: 700;">Academic Shift Management</h2>
        <p style="color: var(--text-muted); font-size: 13px;">Manage First Shift, Second Shift, Morning, and Day schedules.</p>
    </div>
    <button class="btn btn-accent" onclick="App.openModal('shiftModal')">&#10133; Add Shift</button>
</div>

<div class="card">
    <div class="card-header">
        <h3>&#9200; Academic Shifts</h3>
        <input type="text" id="shiftSearch" class="form-control" placeholder="Search shift..." style="max-width: 250px; padding: 6px 12px; font-size: 13px;">
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" id="shiftTable">
                <thead>
                    <tr>
                        <th style="width: 50px;">SL</th>
                        <th>Shift Name</th>
                        <th>Code (Short)</th>
                        <th>Configured Periods</th>
                        <th>Assigned Groups</th>
                        <th>Status</th>
                        <th style="width: 130px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($shifts)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 25px; color: var(--text-muted);">No shifts found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($shifts as $idx => $sh): ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td><strong><?= htmlspecialchars($sh['name']) ?></strong></td>
                                <td><span class="badge badge-accent"><?= htmlspecialchars($sh['code']) ?></span></td>
                                <td><a href="periods.php?shift_id=<?= $sh['id'] ?>" class="badge badge-primary" style="text-decoration: none;"><?= $sh['period_count'] ?> Periods &rarr;</a></td>
                                <td><span class="badge badge-info"><?= $sh['group_count'] ?> Groups</span></td>
                                <td>
                                    <span class="badge <?= $sh['active'] ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $sh['active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <a href="shifts.php?edit=<?= $sh['id'] ?>" class="btn btn-outline btn-sm">&#9998; Edit</a>
                                    <button class="btn btn-danger btn-sm" onclick="App.confirm('Are you sure you want to delete this shift?', () => { window.location.href='shifts.php?delete=<?= $sh['id'] ?>&csrf_token=<?= generateCsrfToken() ?>'; })">&#128465;</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay <?= $editShift ? 'active' : '' ?>" id="shiftModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h4><?= $editShift ? 'Edit Shift' : 'Add New Shift' ?></h4>
            <button class="modal-close" onclick="<?= $editShift ? "window.location.href='shifts.php'" : "App.closeModal('shiftModal')" ?>">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $editShift['id'] ?? 0 ?>">
                <div class="form-group">
                    <label class="form-label">Shift Name *</label>
                    <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($editShift['name'] ?? '') ?>" placeholder="e.g. First Shift">
                </div>
                <div class="form-group">
                    <label class="form-label">Shift Code *</label>
                    <input type="text" name="code" class="form-control" required value="<?= htmlspecialchars($editShift['code'] ?? '') ?>" placeholder="e.g. 1st or 1">
                    <small style="color: var(--text-muted);">Short code component (e.g. 1 in CST 7/1).</small>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; cursor: pointer;">
                        <input type="checkbox" name="active" value="1" <?= (!isset($editShift['active']) || $editShift['active']) ? 'checked' : '' ?>>
                        Shift is Active
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="<?= $editShift ? "window.location.href='shifts.php'" : "App.closeModal('shiftModal')" ?>">Cancel</button>
                <button type="submit" class="btn btn-accent"><?= $editShift ? 'Update Shift' : 'Save Shift' ?></button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    App.setupTableSearch('shiftSearch', 'shiftTable');
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>