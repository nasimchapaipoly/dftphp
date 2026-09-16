<?php
/**
 * Class Routine Management System (NasimSoft)
 * Institute Management
 * Copyright (c) 2026 NasimSoft.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$db = Database::getInstance();
$editId = (int)($_GET['edit'] ?? 0);
$deleteId = (int)($_GET['delete'] ?? 0);

// Delete Action
if ($deleteId > 0 && verifyCsrfToken($_GET['csrf_token'] ?? '')) {
    $stmt = $db->prepare("DELETE FROM institutes WHERE id = ?");
    $stmt->execute([$deleteId]);
    logAudit('Delete Institute', 'Institutes', $deleteId, 'Deleted institute');
    setFlash('success', 'Institute deleted successfully.');
    header('Location: institutes.php');
    exit;
}

// Add or Update Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Security token expired. Please try again.');
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $shortName = trim($_POST['short_name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($name) || empty($shortName)) {
            setFlash('error', 'Institute Name and Short Name are required.');
        } else {
            $logoPath = null;
            if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'svg'], true)) {
                    $newFileName = 'logo_' . time() . '.' . $ext;
                    $uploadDir = __DIR__ . '/../assets/uploads/logos/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . $newFileName)) {
                        $logoPath = 'assets/uploads/logos/' . $newFileName;
                    }
                }
            }

            if ($id > 0) {
                if ($logoPath) {
                    $stmt = $db->prepare("UPDATE institutes SET name = ?, short_name = ?, code = ?, address = ?, email = ?, phone = ?, logo_path = ? WHERE id = ?");
                    $stmt->execute([$name, $shortName, $code, $address, $email, $phone, $logoPath, $id]);
                } else {
                    $stmt = $db->prepare("UPDATE institutes SET name = ?, short_name = ?, code = ?, address = ?, email = ?, phone = ? WHERE id = ?");
                    $stmt->execute([$name, $shortName, $code, $address, $email, $phone, $id]);
                }
                logAudit('Update Institute', 'Institutes', $id, "Updated institute {$name}");
                setFlash('success', 'Institute updated successfully.');
            } else {
                $stmt = $db->prepare("INSERT INTO institutes (name, short_name, code, address, email, phone, logo_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $shortName, $code, $address, $email, $phone, $logoPath]);
                $newId = (int)$db->lastInsertId();
                logAudit('Create Institute', 'Institutes', $newId, "Created institute {$name}");
                setFlash('success', 'Institute created successfully.');
            }
            header('Location: institutes.php');
            exit;
        }
    }
}

$editInstitute = null;
if ($editId > 0) {
    $stmt = $db->prepare("SELECT * FROM institutes WHERE id = ?");
    $stmt->execute([$editId]);
    $editInstitute = $stmt->fetch();
}

$institutes = $db->query("SELECT * FROM institutes ORDER BY id DESC")->fetchAll();
$pageTitle = 'Institute Management';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="color: var(--navy-blue); font-size: 20px; font-weight: 700;">Academic Institutes</h2>
        <p style="color: var(--text-muted); font-size: 13px;">Manage educational institutes, colleges, and polytechnics.</p>
    </div>
    <button class="btn btn-accent" onclick="App.openModal('instituteModal')">&#10133; Add Institute</button>
</div>

<div class="card">
    <div class="card-header">
        <h3>&#127979; Registered Institutes</h3>
        <input type="text" id="instituteSearch" class="form-control" placeholder="Search institute..." style="max-width: 250px; padding: 6px 12px; font-size: 13px;">
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" id="instituteTable">
                <thead>
                    <tr>
                        <th style="width: 50px;">SL</th>
                        <th>Institute Name</th>
                        <th>Short Name</th>
                        <th>Code</th>
                        <th>Email & Phone</th>
                        <th>Address</th>
                        <th style="width: 130px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($institutes)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 25px; color: var(--text-muted);">No institutes added yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($institutes as $idx => $inst): ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td>
                                    <div style="font-weight: 700; color: var(--navy-blue);"><?= htmlspecialchars($inst['name']) ?></div>
                                    <?php if (!empty($inst['logo_path'])): ?>
                                        <span class="badge badge-info" style="font-size: 10px;">Logo Attached</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge badge-accent"><?= htmlspecialchars($inst['short_name']) ?></span></td>
                                <td><?= htmlspecialchars($inst['code'] ?? 'N/A') ?></td>
                                <td>
                                    <div><?= htmlspecialchars($inst['email'] ?? '') ?></div>
                                    <small style="color: var(--text-muted);"><?= htmlspecialchars($inst['phone'] ?? '') ?></small>
                                </td>
                                <td><small><?= htmlspecialchars($inst['address'] ?? 'N/A') ?></small></td>
                                <td style="text-align: right;">
                                    <a href="institutes.php?edit=<?= $inst['id'] ?>" class="btn btn-outline btn-sm">&#9998; Edit</a>
                                    <button class="btn btn-danger btn-sm" onclick="App.confirm('Are you sure you want to delete this institute? Associated departments and routines will be deleted.', () => { window.location.href='institutes.php?delete=<?= $inst['id'] ?>&csrf_token=<?= generateCsrfToken() ?>'; })">&#128465;</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal for Create / Edit -->
<div class="modal-overlay <?= $editInstitute ? 'active' : '' ?>" id="instituteModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h4><?= $editInstitute ? 'Edit Institute' : 'Add New Institute' ?></h4>
            <button class="modal-close" onclick="<?= $editInstitute ? "window.location.href='institutes.php'" : "App.closeModal('instituteModal')" ?>">&times;</button>
        </div>
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="modal-body">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $editInstitute['id'] ?? 0 ?>">
                <div class="form-group">
                    <label class="form-label">Institute Full Name *</label>
                    <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($editInstitute['name'] ?? '') ?>" placeholder="e.g. Chapainawabganj Polytechnic Institute">
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Short Name *</label>
                        <input type="text" name="short_name" class="form-control" required value="<?= htmlspecialchars($editInstitute['short_name'] ?? '') ?>" placeholder="e.g. CNPI">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Institute Code</label>
                        <input type="text" name="code" class="form-control" value="<?= htmlspecialchars($editInstitute['code'] ?? '') ?>" placeholder="e.g. 13137">
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($editInstitute['email'] ?? '') ?>" placeholder="e.g. principal@cnpi.edu.bd">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($editInstitute['phone'] ?? '') ?>" placeholder="e.g. 01700000000">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Location Address</label>
                    <textarea name="address" class="form-control" rows="2" placeholder="Baroghoria, Chapainawabganj"><?= htmlspecialchars($editInstitute['address'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Official Institute Logo</label>
                    <input type="file" name="logo" class="form-control" accept="image/*">
                    <?php if (!empty($editInstitute['logo_path'])): ?>
                        <small style="color: var(--text-muted); display: block; margin-top: 4px;">Current: <?= htmlspecialchars($editInstitute['logo_path']) ?></small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="<?= $editInstitute ? "window.location.href='institutes.php'" : "App.closeModal('instituteModal')" ?>">Cancel</button>
                <button type="submit" class="btn btn-accent"><?= $editInstitute ? 'Update Institute' : 'Save Institute' ?></button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    App.setupTableSearch('instituteSearch', 'instituteTable');
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>