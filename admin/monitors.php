<?php
/**
 * Class Routine Management System (NasimSoft)
 * Class Monitor Management
 * Copyright (c) 2026 NasimSoft.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$db = Database::getInstance();
$editId = (int)($_GET['edit'] ?? 0);
$deleteId = (int)($_GET['delete'] ?? 0);

if ($deleteId > 0 && verifyCsrfToken($_GET['csrf_token'] ?? '')) {
    $stmt = $db->prepare("DELETE FROM class_monitors WHERE id = ?");
    $stmt->execute([$deleteId]);
    logAudit('Delete Monitor', 'Monitors', $deleteId, 'Deleted class monitor');
    setFlash('success', 'Class monitor removed successfully.');
    header('Location: monitors.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Security token expired. Please try again.');
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $groupId = (int)($_POST['group_id'] ?? 0);
        $name = trim($_POST['monitor_name'] ?? '');
        $roll = trim($_POST['student_roll'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;

        if (empty($name) || empty($roll) || $groupId <= 0) {
            setFlash('error', 'Academic Group, Student Name, and Board Roll are required.');
        } else {
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE class_monitors SET group_id = ?, monitor_name = ?, student_roll = ?, phone = ?, email = ?, active = ? WHERE id = ?");
                $stmt->execute([$groupId, $name, $roll, $phone, $email, $active, $id]);
                logAudit('Update Monitor', 'Monitors', $id, "Updated monitor {$name}");
                setFlash('success', 'Class monitor updated successfully.');
            } else {
                $stmt = $db->prepare("INSERT INTO class_monitors (group_id, monitor_name, student_roll, phone, email, active) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$groupId, $name, $roll, $phone, $email, $active]);
                $newId = (int)$db->lastInsertId();
                logAudit('Create Monitor', 'Monitors', $newId, "Created monitor {$name}");
                setFlash('success', 'Class monitor assigned successfully.');
            }
            header('Location: monitors.php');
            exit;
        }
    }
}

$editMonitor = null;
if ($editId > 0) {
    $stmt = $db->prepare("SELECT * FROM class_monitors WHERE id = ?");
    $stmt->execute([$editId]);
    $editMonitor = $stmt->fetch();
}

$groups = $db->query("SELECT id, short_code, group_name FROM `groups` WHERE active = 1 ORDER BY short_code ASC")->fetchAll();
$monitors = $db->query("
    SELECT cm.*, g.short_code as group_code, g.group_name
    FROM class_monitors cm
    LEFT JOIN `groups` g ON cm.group_id = g.id
    ORDER BY cm.id DESC
")->fetchAll();
$pageTitle = 'Class Monitors';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="color: var(--navy-blue); font-size: 20px; font-weight: 700;">Class Monitor Management</h2>
        <p style="color: var(--text-muted); font-size: 13px;">Assign student class monitors and group representatives to track and view assigned routines.</p>
    </div>
    <button class="btn btn-accent" onclick="App.openModal('monModal')">&#10133; Assign Monitor</button>
</div>

<div class="card">
    <div class="card-header">
        <h3>&#128100; Registered Class Monitors</h3>
        <input type="text" id="monSearch" class="form-control" placeholder="Search monitor or roll..." style="max-width: 250px; padding: 6px 12px; font-size: 13px;">
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" id="monTable">
                <thead>
                    <tr>
                        <th style="width: 50px;">SL</th>
                        <th>Monitor Name</th>
                        <th>Board Roll / ID</th>
                        <th>Academic Group</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th style="width: 130px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($monitors)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 25px; color: var(--text-muted);">No class monitors assigned yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($monitors as $idx => $m): ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td><strong style="color: var(--navy-blue);"><?= htmlspecialchars($m['monitor_name']) ?></strong></td>
                                <td><span class="badge badge-primary"><?= htmlspecialchars($m['student_roll']) ?></span></td>
                                <td><span class="badge badge-accent"><?= htmlspecialchars($m['group_code'] ?? 'Group') ?></span></td>
                                <td><?= htmlspecialchars($m['phone'] ?: 'N/A') ?></td>
                                <td><small><?= htmlspecialchars($m['email'] ?: 'N/A') ?></small></td>
                                <td>
                                    <span class="badge <?= $m['active'] ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $m['active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <a href="monitors.php?edit=<?= $m['id'] ?>" class="btn btn-outline btn-sm">&#9998; Edit</a>
                                    <button class="btn btn-danger btn-sm" onclick="App.confirm('Are you sure you want to remove this monitor?', () => { window.location.href='monitors.php?delete=<?= $m['id'] ?>&csrf_token=<?= generateCsrfToken() ?>'; })">&#128465;</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Monitor Modal -->
<div class="modal-overlay <?= $editMonitor ? 'active' : '' ?>" id="monModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h4><?= $editMonitor ? 'Edit Class Monitor' : 'Assign Class Monitor' ?></h4>
            <button class="modal-close" onclick="<?= $editMonitor ? "window.location.href='monitors.php'" : "App.closeModal('monModal')" ?>">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $editMonitor['id'] ?? 0 ?>">
                <div class="form-group">
                    <label class="form-label">Academic Group *</label>
                    <select name="group_id" class="form-select" required>
                        <option value="">-- Select Group --</option>
                        <?php foreach ($groups as $g): ?>
                            <option value="<?= $g['id'] ?>" <?= (($editMonitor['group_id'] ?? 0) == $g['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($g['short_code']) ?> (<?= htmlspecialchars($g['group_name']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Monitor Full Name *</label>
                        <input type="text" name="monitor_name" class="form-control" required value="<?= htmlspecialchars($editMonitor['monitor_name'] ?? '') ?>" placeholder="e.g. Tanvir Ahmed">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Student Board Roll / ID *</label>
                        <input type="text" name="student_roll" class="form-control" required value="<?= htmlspecialchars($editMonitor['student_roll'] ?? '') ?>" placeholder="e.g. 512345">
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($editMonitor['phone'] ?? '') ?>" placeholder="01712345678">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($editMonitor['email'] ?? '') ?>" placeholder="student@gmail.com">
                    </div>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; cursor: pointer;">
                        <input type="checkbox" name="active" value="1" <?= (!isset($editMonitor['active']) || $editMonitor['active']) ? 'checked' : '' ?>>
                        Monitor Status is Active
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="<?= $editMonitor ? "window.location.href='monitors.php'" : "App.closeModal('monModal')" ?>">Cancel</button>
                <button type="submit" class="btn btn-accent"><?= $editMonitor ? 'Update Monitor' : 'Save Monitor' ?></button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    App.setupTableSearch('monSearch', 'monTable');
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>