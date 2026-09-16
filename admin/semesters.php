<?php
/**
 * Class Routine Management System (NasimSoft)
 * Semester Management
 * Copyright (c) 2026 NasimSoft.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$db = Database::getInstance();
$editId = (int)($_GET['edit'] ?? 0);
$deleteId = (int)($_GET['delete'] ?? 0);

if ($deleteId > 0 && verifyCsrfToken($_GET['csrf_token'] ?? '')) {
    $stmt = $db->prepare("DELETE FROM semesters WHERE id = ?");
    $stmt->execute([$deleteId]);
    logAudit('Delete Semester', 'Semesters', $deleteId, 'Deleted semester');
    setFlash('success', 'Semester deleted successfully.');
    header('Location: semesters.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Security token expired. Please try again.');
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $numericLevel = (int)($_POST['numeric_level'] ?? 1);
        $active = isset($_POST['active']) ? 1 : 0;

        if (empty($name) || empty($code)) {
            setFlash('error', 'Semester Name and Code are required.');
        } else {
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE semesters SET name = ?, code = ?, numeric_level = ?, active = ? WHERE id = ?");
                $stmt->execute([$name, $code, $numericLevel, $active, $id]);
                logAudit('Update Semester', 'Semesters', $id, "Updated semester {$name}");
                setFlash('success', 'Semester updated successfully.');
            } else {
                $stmt = $db->prepare("INSERT INTO semesters (name, code, numeric_level, active) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $code, $numericLevel, $active]);
                $newId = (int)$db->lastInsertId();
                logAudit('Create Semester', 'Semesters', $newId, "Created semester {$name}");
                setFlash('success', 'Semester created successfully.');
            }
            header('Location: semesters.php');
            exit;
        }
    }
}

$editSem = null;
if ($editId > 0) {
    $stmt = $db->prepare("SELECT * FROM semesters WHERE id = ?");
    $stmt->execute([$editId]);
    $editSem = $stmt->fetch();
}

$semesters = $db->query("
    SELECT s.*, (SELECT COUNT(*) FROM `groups` WHERE semester_id = s.id) as group_count 
    FROM semesters s 
    ORDER BY s.numeric_level ASC
")->fetchAll();
$pageTitle = 'Semesters';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="color: var(--navy-blue); font-size: 20px; font-weight: 700;">Semester Management</h2>
        <p style="color: var(--text-muted); font-size: 13px;">Manage academic terms, semester levels, and ordinal codes.</p>
    </div>
    <button class="btn btn-accent" onclick="App.openModal('semModal')">&#10133; Add Semester</button>
</div>

<div class="card">
    <div class="card-header">
        <h3>&#128198; Academic Semesters</h3>
        <input type="text" id="semSearch" class="form-control" placeholder="Search semester..." style="max-width: 250px; padding: 6px 12px; font-size: 13px;">
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" id="semTable">
                <thead>
                    <tr>
                        <th style="width: 50px;">Level</th>
                        <th>Semester Name</th>
                        <th>Code</th>
                        <th>Ordinal Value</th>
                        <th>Allocated Groups</th>
                        <th>Status</th>
                        <th style="width: 130px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($semesters)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 25px; color: var(--text-muted);">No semesters registered.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($semesters as $s): ?>
                            <tr>
                                <td><span class="badge badge-primary"><?= $s['numeric_level'] ?></span></td>
                                <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                                <td><span class="badge badge-accent"><?= htmlspecialchars($s['code']) ?></span></td>
                                <td><?= $s['numeric_level'] ?></td>
                                <td><span class="badge badge-info"><?= $s['group_count'] ?> Groups</span></td>
                                <td>
                                    <span class="badge <?= $s['active'] ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $s['active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <a href="semesters.php?edit=<?= $s['id'] ?>" class="btn btn-outline btn-sm">&#9998; Edit</a>
                                    <button class="btn btn-danger btn-sm" onclick="App.confirm('Are you sure you want to delete this semester?', () => { window.location.href='semesters.php?delete=<?= $s['id'] ?>&csrf_token=<?= generateCsrfToken() ?>'; })">&#128465;</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay <?= $editSem ? 'active' : '' ?>" id="semModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h4><?= $editSem ? 'Edit Semester' : 'Add New Semester' ?></h4>
            <button class="modal-close" onclick="<?= $editSem ? "window.location.href='semesters.php'" : "App.closeModal('semModal')" ?>">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $editSem['id'] ?? 0 ?>">
                <div class="form-group">
                    <label class="form-label">Semester Name *</label>
                    <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($editSem['name'] ?? '') ?>" placeholder="e.g. Seventh Semester">
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Semester Code *</label>
                        <input type="text" name="code" class="form-control" required value="<?= htmlspecialchars($editSem['code'] ?? '') ?>" placeholder="e.g. 7th">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Numeric Level (1-12) *</label>
                        <input type="number" name="numeric_level" class="form-control" min="1" max="12" required value="<?= htmlspecialchars((string)($editSem['numeric_level'] ?? 1)) ?>" placeholder="e.g. 7">
                    </div>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; cursor: pointer;">
                        <input type="checkbox" name="active" value="1" <?= (!isset($editSem['active']) || $editSem['active']) ? 'checked' : '' ?>>
                        Semester is Active
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="<?= $editSem ? "window.location.href='semesters.php'" : "App.closeModal('semModal')" ?>">Cancel</button>
                <button type="submit" class="btn btn-accent"><?= $editSem ? 'Update Semester' : 'Save Semester' ?></button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    App.setupTableSearch('semSearch', 'semTable');
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>