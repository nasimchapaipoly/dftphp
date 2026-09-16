<?php
/**
 * Class Routine Management System (NasimSoft)
 * Department Management
 * Copyright (c) 2026 NasimSoft.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$db = Database::getInstance();
$editId = (int)($_GET['edit'] ?? 0);
$deleteId = (int)($_GET['delete'] ?? 0);

if ($deleteId > 0 && verifyCsrfToken($_GET['csrf_token'] ?? '')) {
    $stmt = $db->prepare("DELETE FROM departments WHERE id = ?");
    $stmt->execute([$deleteId]);
    logAudit('Delete Department', 'Departments', $deleteId, 'Deleted department');
    setFlash('success', 'Department deleted successfully.');
    header('Location: departments.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Security token expired. Please try again.');
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $instituteId = (int)($_POST['institute_id'] ?? 1);
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;

        if (empty($name) || empty($code)) {
            setFlash('error', 'Department Name and Department Code are required.');
        } else {
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE departments SET institute_id = ?, name = ?, code = ?, active = ? WHERE id = ?");
                $stmt->execute([$instituteId, $name, $code, $active, $id]);
                logAudit('Update Department', 'Departments', $id, "Updated department {$name}");
                setFlash('success', 'Department updated successfully.');
            } else {
                $stmt = $db->prepare("INSERT INTO departments (institute_id, name, code, active) VALUES (?, ?, ?, ?)");
                $stmt->execute([$instituteId, $name, $code, $active]);
                $newId = (int)$db->lastInsertId();
                logAudit('Create Department', 'Departments', $newId, "Created department {$name}");
                setFlash('success', 'Department created successfully.');
            }
            header('Location: departments.php');
            exit;
        }
    }
}

$editDept = null;
if ($editId > 0) {
    $stmt = $db->prepare("SELECT * FROM departments WHERE id = ?");
    $stmt->execute([$editId]);
    $editDept = $stmt->fetch();
}

$institutes = $db->query("SELECT id, name FROM institutes ORDER BY name ASC")->fetchAll();
$departments = $db->query("
    SELECT d.*, i.name as institute_name, 
           (SELECT COUNT(*) FROM technologies WHERE department_id = d.id) as tech_count,
           (SELECT COUNT(*) FROM teachers WHERE department_id = d.id) as teacher_count
    FROM departments d
    LEFT JOIN institutes i ON d.institute_id = i.id
    ORDER BY d.id DESC
")->fetchAll();

$pageTitle = 'Departments';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="color: var(--navy-blue); font-size: 20px; font-weight: 700;">Department Management</h2>
        <p style="color: var(--text-muted); font-size: 13px;">Manage academic faculties and departmental divisions.</p>
    </div>
    <button class="btn btn-accent" onclick="App.openModal('deptModal')">&#10133; Add Department</button>
</div>

<div class="card">
    <div class="card-header">
        <h3>&#127979; Department List</h3>
        <input type="text" id="deptSearch" class="form-control" placeholder="Search department..." style="max-width: 250px; padding: 6px 12px; font-size: 13px;">
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" id="deptTable">
                <thead>
                    <tr>
                        <th style="width: 50px;">SL</th>
                        <th>Department Name</th>
                        <th>Code</th>
                        <th>Institute</th>
                        <th>Technologies</th>
                        <th>Teachers</th>
                        <th>Status</th>
                        <th style="width: 130px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($departments)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 25px; color: var(--text-muted);">No departments found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($departments as $idx => $d): ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td><strong><?= htmlspecialchars($d['name']) ?></strong></td>
                                <td><span class="badge badge-accent"><?= htmlspecialchars($d['code']) ?></span></td>
                                <td><?= htmlspecialchars($d['institute_name'] ?? 'N/A') ?></td>
                                <td><span class="badge badge-info"><?= $d['tech_count'] ?> Tech</span></td>
                                <td><span class="badge badge-primary"><?= $d['teacher_count'] ?> Faculty</span></td>
                                <td>
                                    <span class="badge <?= $d['active'] ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $d['active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <a href="departments.php?edit=<?= $d['id'] ?>" class="btn btn-outline btn-sm">&#9998; Edit</a>
                                    <button class="btn btn-danger btn-sm" onclick="App.confirm('Are you sure you want to delete this department?', () => { window.location.href='departments.php?delete=<?= $d['id'] ?>&csrf_token=<?= generateCsrfToken() ?>'; })">&#128465;</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Department Modal -->
<div class="modal-overlay <?= $editDept ? 'active' : '' ?>" id="deptModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h4><?= $editDept ? 'Edit Department' : 'Add New Department' ?></h4>
            <button class="modal-close" onclick="<?= $editDept ? "window.location.href='departments.php'" : "App.closeModal('deptModal')" ?>">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $editDept['id'] ?? 0 ?>">
                <div class="form-group">
                    <label class="form-label">Affiliated Institute *</label>
                    <select name="institute_id" class="form-select" required>
                        <?php foreach ($institutes as $inst): ?>
                            <option value="<?= $inst['id'] ?>" <?= (($editDept['institute_id'] ?? 1) == $inst['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($inst['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Department Name *</label>
                    <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($editDept['name'] ?? '') ?>" placeholder="e.g. Computer Science & Technology">
                </div>
                <div class="form-group">
                    <label class="form-label">Department Code *</label>
                    <input type="text" name="code" class="form-control" required value="<?= htmlspecialchars($editDept['code'] ?? '') ?>" placeholder="e.g. CST, ET, CT, FT">
                </div>
                <div class="form-group" style="margin-top: 15px;">
                    <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; cursor: pointer;">
                        <input type="checkbox" name="active" value="1" <?= (!isset($editDept['active']) || $editDept['active']) ? 'checked' : '' ?>>
                        Department is Active
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="<?= $editDept ? "window.location.href='departments.php'" : "App.closeModal('deptModal')" ?>">Cancel</button>
                <button type="submit" class="btn btn-accent"><?= $editDept ? 'Update Department' : 'Save Department' ?></button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    App.setupTableSearch('deptSearch', 'deptTable');
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>