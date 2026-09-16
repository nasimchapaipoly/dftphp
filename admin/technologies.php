<?php
/**
 * Class Routine Management System (NasimSoft)
 * Technology Management
 * Copyright (c) 2026 NasimSoft.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$db = Database::getInstance();
$editId = (int)($_GET['edit'] ?? 0);
$deleteId = (int)($_GET['delete'] ?? 0);

if ($deleteId > 0 && verifyCsrfToken($_GET['csrf_token'] ?? '')) {
    $stmt = $db->prepare("DELETE FROM technologies WHERE id = ?");
    $stmt->execute([$deleteId]);
    logAudit('Delete Technology', 'Technologies', $deleteId, 'Deleted technology');
    setFlash('success', 'Technology deleted successfully.');
    header('Location: technologies.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Security token expired. Please try again.');
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $departmentId = (int)($_POST['department_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $shortName = trim($_POST['short_name'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;

        if (empty($name) || empty($shortName) || $departmentId <= 0) {
            setFlash('error', 'Department, Technology Name, and Short Code are required.');
        } else {
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE technologies SET department_id = ?, name = ?, code = ?, short_name = ?, active = ? WHERE id = ?");
                $stmt->execute([$departmentId, $name, $code, $shortName, $active, $id]);
                logAudit('Update Technology', 'Technologies', $id, "Updated technology {$name}");
                setFlash('success', 'Technology updated successfully.');
            } else {
                $stmt = $db->prepare("INSERT INTO technologies (department_id, name, code, short_name, active) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$departmentId, $name, $code, $shortName, $active]);
                $newId = (int)$db->lastInsertId();
                logAudit('Create Technology', 'Technologies', $newId, "Created technology {$name}");
                setFlash('success', 'Technology created successfully.');
            }
            header('Location: technologies.php');
            exit;
        }
    }
}

$editTech = null;
if ($editId > 0) {
    $stmt = $db->prepare("SELECT * FROM technologies WHERE id = ?");
    $stmt->execute([$editId]);
    $editTech = $stmt->fetch();
}

$departments = $db->query("SELECT id, name, code FROM departments WHERE active = 1 ORDER BY name ASC")->fetchAll();
$technologies = $db->query("
    SELECT t.*, d.name as department_name, d.code as department_code,
           (SELECT COUNT(*) FROM `groups` WHERE technology_id = t.id) as group_count
    FROM technologies t
    LEFT JOIN departments d ON t.department_id = d.id
    ORDER BY t.id DESC
")->fetchAll();
$pageTitle = 'Technologies';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="color: var(--navy-blue); font-size: 20px; font-weight: 700;">Technology Management</h2>
        <p style="color: var(--text-muted); font-size: 13px;">Manage individual technology curricula, short prefixes, and discipline codes.</p>
    </div>
    <button class="btn btn-accent" onclick="App.openModal('techModal')">&#10133; Add Technology</button>
</div>

<div class="card">
    <div class="card-header">
        <h3>&#128295; Registered Technologies</h3>
        <input type="text" id="techSearch" class="form-control" placeholder="Search technology..." style="max-width: 250px; padding: 6px 12px; font-size: 13px;">
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" id="techTable">
                <thead>
                    <tr>
                        <th style="width: 50px;">SL</th>
                        <th>Technology Name</th>
                        <th>Short Code Prefix</th>
                        <th>Board Tech Code</th>
                        <th>Department</th>
                        <th>Active Groups</th>
                        <th>Status</th>
                        <th style="width: 130px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($technologies)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 25px; color: var(--text-muted);">No technologies found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($technologies as $idx => $t): ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td><strong><?= htmlspecialchars($t['name']) ?></strong></td>
                                <td><span class="badge badge-accent" style="font-size: 12px;"><?= htmlspecialchars($t['short_name']) ?></span></td>
                                <td><?= htmlspecialchars($t['code'] ?: 'N/A') ?></td>
                                <td><?= htmlspecialchars($t['department_name'] ?? 'N/A') ?></td>
                                <td><span class="badge badge-info"><?= $t['group_count'] ?> Groups</span></td>
                                <td>
                                    <span class="badge <?= $t['active'] ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $t['active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <a href="technologies.php?edit=<?= $t['id'] ?>" class="btn btn-outline btn-sm">&#9998; Edit</a>
                                    <button class="btn btn-danger btn-sm" onclick="App.confirm('Are you sure you want to delete this technology?', () => { window.location.href='technologies.php?delete=<?= $t['id'] ?>&csrf_token=<?= generateCsrfToken() ?>'; })">&#128465;</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Technology Modal -->
<div class="modal-overlay <?= $editTech ? 'active' : '' ?>" id="techModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h4><?= $editTech ? 'Edit Technology' : 'Add New Technology' ?></h4>
            <button class="modal-close" onclick="<?= $editTech ? "window.location.href='technologies.php'" : "App.closeModal('techModal')" ?>">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $editTech['id'] ?? 0 ?>">
                <div class="form-group">
                    <label class="form-label">Parent Department *</label>
                    <select name="department_id" class="form-select" required>
                        <option value="">-- Select Department --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>" <?= (($editTech['department_id'] ?? 0) == $dept['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept['name']) ?> (<?= htmlspecialchars($dept['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Technology Name *</label>
                    <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($editTech['name'] ?? '') ?>" placeholder="e.g. Computer Science & Technology">
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Short Name / Prefix *</label>
                        <input type="text" name="short_name" class="form-control" required value="<?= htmlspecialchars($editTech['short_name'] ?? '') ?>" placeholder="e.g. CST, ET, FT">
                        <small style="color: var(--text-muted);">Used to auto-generate routine group short codes.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Curriculum / Code</label>
                        <input type="text" name="code" class="form-control" value="<?= htmlspecialchars($editTech['code'] ?? '') ?>" placeholder="e.g. 85, 67, 69">
                    </div>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; cursor: pointer;">
                        <input type="checkbox" name="active" value="1" <?= (!isset($editTech['active']) || $editTech['active']) ? 'checked' : '' ?>>
                        Technology is Active
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="<?= $editTech ? "window.location.href='technologies.php'" : "App.closeModal('techModal')" ?>">Cancel</button>
                <button type="submit" class="btn btn-accent"><?= $editTech ? 'Update Technology' : 'Save Technology' ?></button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    App.setupTableSearch('techSearch', 'techTable');
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>