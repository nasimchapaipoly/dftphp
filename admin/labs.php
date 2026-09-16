<?php
/**
 * Class Routine Management System (NasimSoft)
 * Specialized Lab Management
 * Copyright (c) 2026 NasimSoft.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/csv-tools.php';

$db = Database::getInstance();
$editId = (int)($_GET['edit'] ?? 0);
$deleteId = (int)($_GET['delete'] ?? 0);

// CSV Template Download
if (($_GET['template'] ?? '') === 'csv') {
    csvDownload('labs-template.csv',
        ['lab_code', 'name', 'room_code', 'lab_type', 'capacity', 'department_code', 'active'],
        [['LAB-NET', 'Network Lab', 'N-211', 'Hardware & Networking', '40', 'CST', '1']]
    );
}

// CSV Export
if (($_GET['export'] ?? '') === 'csv') {
    $rows = $db->query("SELECT l.lab_code, l.name, r.room_code, l.lab_type, l.capacity, d.code as department_code, l.active FROM labs l LEFT JOIN rooms r ON l.room_id = r.id LEFT JOIN departments d ON l.department_id = d.id ORDER BY l.lab_code ASC")->fetchAll(PDO::FETCH_NUM);
    csvDownload('labs-export-' . date('Y-m-d') . '.csv',
        ['lab_code', 'name', 'room_code', 'lab_type', 'capacity', 'department_code', 'active'],
        $rows
    );
}

// CSV Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'csv_import') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Security token expired. Please try again.');
    } else {
        try {
            $rows = csvParseUpload($_FILES['csv_file'] ?? []);
            $deptMap = [];
            foreach ($db->query("SELECT id, code FROM departments") as $d) {
                $deptMap[strtoupper($d['code'])] = (int)$d['id'];
            }
            $roomMap = [];
            foreach ($db->query("SELECT id, room_code FROM rooms") as $r) {
                $roomMap[strtoupper($r['room_code'])] = (int)$r['id'];
            }

            $created = 0; $updated = 0; $errors = [];
            foreach ($rows as $i => $row) {
                $line = $i + 2;
                $code = strtoupper(trim($row['lab_code'] ?? ''));
                $name = trim($row['name'] ?? '');
                $roomCode = strtoupper(trim($row['room_code'] ?? ''));

                if ($code === '' || $name === '') {
                    $errors[] = "Line {$line}: lab_code and name are required";
                    continue;
                }
                if (!isset($roomMap[$roomCode])) {
                    $errors[] = "Line {$line}: unknown room_code '{$roomCode}' — create the room first";
                    continue;
                }

                $deptCode = strtoupper(trim($row['department_code'] ?? ''));
                $deptId = $deptMap[$deptCode] ?? null;
                $labType = trim($row['lab_type'] ?? '');
                $capacity = max(1, (int)($row['capacity'] ?? 40));
                $active = isset($row['active']) && $row['active'] !== '' ? (int)(bool)(int)$row['active'] : 1;

                $existing = $db->prepare("SELECT id FROM labs WHERE lab_code = ?");
                $existing->execute([$code]);
                $existingId = $existing->fetchColumn();

                if ($existingId) {
                    $stmt = $db->prepare("UPDATE labs SET room_id=?, name=?, lab_type=?, capacity=?, department_id=?, active=? WHERE id=?");
                    $stmt->execute([$roomMap[$roomCode], $name, $labType, $capacity, $deptId, $active, $existingId]);
                    $updated++;
                } else {
                    $stmt = $db->prepare("INSERT INTO labs (room_id, name, lab_code, lab_type, capacity, department_id, active) VALUES (?,?,?,?,?,?,?)");
                    $stmt->execute([$roomMap[$roomCode], $name, $code, $labType, $capacity, $deptId, $active]);
                    $created++;
                }
            }
            logAudit('Bulk Import Labs', 'Labs', 0, "Created {$created}, updated {$updated}, " . count($errors) . ' errors');
            csvImportSummaryFlash($created, $updated, $errors);
        } catch (Exception $e) {
            setFlash('error', $e->getMessage());
        }
        header('Location: labs.php');
        exit;
    }
}

if ($deleteId > 0 && verifyCsrfToken($_GET['csrf_token'] ?? '')) {
    $stmt = $db->prepare("DELETE FROM labs WHERE id = ?");
    $stmt->execute([$deleteId]);
    logAudit('Delete Lab', 'Labs', $deleteId, 'Deleted lab');
    setFlash('success', 'Lab deleted successfully.');
    header('Location: labs.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Security token expired. Please try again.');
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $labCode = strtoupper(trim($_POST['lab_code'] ?? ''));
        $roomId = (int)($_POST['room_id'] ?? 0);
        $labType = trim($_POST['lab_type'] ?? '');
        $capacity = (int)($_POST['capacity'] ?? 40);
        $deptId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $active = isset($_POST['active']) ? 1 : 0;

        if (empty($name) || empty($labCode) || $roomId <= 0) {
            setFlash('error', 'Lab Name, Lab Code, and Assigned Room are required.');
        } else {
            $dupCheck = $db->prepare("SELECT id FROM labs WHERE lab_code = ? AND id != ?");
            $dupCheck->execute([$labCode, $id]);
            if ($dupCheck->fetch()) {
                setFlash('error', "Lab code '{$labCode}' already exists.");
            } else {
                if ($id > 0) {
                    $stmt = $db->prepare("UPDATE labs SET room_id = ?, name = ?, lab_code = ?, lab_type = ?, capacity = ?, department_id = ?, active = ? WHERE id = ?");
                    $stmt->execute([$roomId, $name, $labCode, $labType, $capacity, $deptId, $active, $id]);
                    logAudit('Update Lab', 'Labs', $id, "Updated lab {$name} ({$labCode})");
                    setFlash('success', 'Lab details updated successfully.');
                } else {
                    $stmt = $db->prepare("INSERT INTO labs (room_id, name, lab_code, lab_type, capacity, department_id, active) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$roomId, $name, $labCode, $labType, $capacity, $deptId, $active]);
                    $newId = (int)$db->lastInsertId();
                    logAudit('Create Lab', 'Labs', $newId, "Created lab {$name} ({$labCode})");
                    setFlash('success', 'Lab created successfully.');
                }
                header('Location: labs.php');
                exit;
            }
        }
    }
}

$editLab = null;
if ($editId > 0) {
    $stmt = $db->prepare("SELECT * FROM labs WHERE id = ?");
    $stmt->execute([$editId]);
    $editLab = $stmt->fetch();
}

$departments = $db->query("SELECT id, name, code FROM departments WHERE active = 1 ORDER BY name ASC")->fetchAll();
$labRooms = $db->query("SELECT id, name, room_code, capacity FROM rooms WHERE active = 1 ORDER BY name ASC")->fetchAll();

$labs = $db->query("
    SELECT l.*, r.room_code, r.building, r.floor, d.name as department_name, d.code as department_code,
           (SELECT COUNT(*) FROM routines WHERE room_id = l.room_id AND is_lab = 1) as scheduled_labs
    FROM labs l
    LEFT JOIN rooms r ON l.room_id = r.id
    LEFT JOIN departments d ON l.department_id = d.id
    ORDER BY l.id DESC
")->fetchAll();
$pageTitle = 'Specialized Labs';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="color: var(--navy-blue); font-size: 20px; font-weight: 700;">Specialized Laboratories & Workshops</h2>
        <p style="color: var(--text-muted); font-size: 13px;">Manage computer, electronics, food testing, mechanical and civil labs.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <button class="btn btn-outline" onclick="App.openModal('csvModal')">&#128228; Import / Export</button>
        <button class="btn btn-accent" onclick="App.openModal('labModal')">&#10133; Register Lab</button>
    </div>
</div>

<!-- CSV Import/Export Modal -->
<div class="modal-overlay" id="csvModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h4>&#128228; Bulk Import / Export Labs</h4>
            <button class="modal-close" onclick="App.closeModal('csvModal')">&times;</button>
        </div>
        <div class="modal-body">
            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 14px;">
                Update many labs at once with a CSV file. Matching is done by <strong>lab_code</strong> —
                existing codes are updated, new codes are created. The referenced <strong>room_code</strong> must already exist.
            </p>
            <div style="display:flex; gap:10px; margin-bottom:18px;">
                <a href="labs.php?export=csv" class="btn btn-outline btn-sm">&#11015; Export Current Data</a>
                <a href="labs.php?template=csv" class="btn btn-outline btn-sm">&#128196; Download Template</a>
            </div>
            <form method="POST" action="labs.php" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="form" value="csv_import">
                <div class="form-group">
                    <label class="form-label">Choose CSV File *</label>
                    <input type="file" name="csv_file" accept=".csv" class="form-control" required>
                    <span class="form-hint">Columns: lab_code, name, room_code, lab_type, capacity, department_code, active</span>
                </div>
                <button type="submit" class="btn btn-accent" style="width:100%;">&#128228; Upload &amp; Import</button>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>&#128187; Laboratory Facilities</h3>
        <input type="text" id="labSearch" class="form-control" placeholder="Search lab name or code..." style="max-width: 250px; padding: 6px 12px; font-size: 13px;">
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" id="labTable">
                <thead>
                    <tr>
                        <th style="width: 50px;">SL</th>
                        <th>Lab Name</th>
                        <th>Lab Code</th>
                        <th>Assigned Room</th>
                        <th>Specialty / Type</th>
                        <th>Capacity</th>
                        <th>Department</th>
                        <th>Lab Classes</th>
                        <th>Status</th>
                        <th style="width: 130px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($labs)): ?>
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 25px; color: var(--text-muted);">No laboratories registered yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($labs as $idx => $l): ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td><strong style="color: var(--navy-blue);"><?= htmlspecialchars($l['name']) ?></strong></td>
                                <td><span class="badge badge-accent"><?= htmlspecialchars($l['lab_code']) ?></span></td>
                                <td><span class="badge badge-primary"><?= htmlspecialchars($l['room_code'] ?? 'Unassigned') ?></span></td>
                                <td><small><?= htmlspecialchars($l['lab_type'] ?: 'Standard Laboratory') ?></small></td>
                                <td><?= $l['capacity'] ?> workstations</td>
                                <td><?= htmlspecialchars($l['department_code'] ?? 'Central') ?></td>
                                <td><span class="badge badge-info"><?= $l['scheduled_labs'] ?> lab slots</span></td>
                                <td>
                                    <span class="badge <?= $l['active'] ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $l['active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <a href="labs.php?edit=<?= $l['id'] ?>" class="btn btn-outline btn-sm">&#9998; Edit</a>
                                    <button class="btn btn-danger btn-sm" onclick="App.confirm('Are you sure you want to delete this lab?', () => { window.location.href='labs.php?delete=<?= $l['id'] ?>&csrf_token=<?= generateCsrfToken() ?>'; })">&#128465;</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Lab Modal -->
<div class="modal-overlay <?= $editLab ? 'active' : '' ?>" id="labModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h4><?= $editLab ? 'Edit Laboratory' : 'Add New Laboratory' ?></h4>
            <button class="modal-close" onclick="<?= $editLab ? "window.location.href='labs.php'" : "App.closeModal('labModal')" ?>">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $editLab['id'] ?? 0 ?>">
                <div class="form-group">
                    <label class="form-label">Laboratory Name *</label>
                    <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($editLab['name'] ?? '') ?>" placeholder="e.g. Network Lab, Software & Apps Lab">
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Lab Code *</label>
                        <input type="text" name="lab_code" class="form-control" required value="<?= htmlspecialchars($editLab['lab_code'] ?? '') ?>" placeholder="e.g. LAB-NET">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Physical Room *</label>
                        <select name="room_id" class="form-select" required>
                            <option value="">-- Select Classroom / Room --</option>
                            <?php foreach ($labRooms as $rm): ?>
                                <option value="<?= $rm['id'] ?>" <?= (($editLab['room_id'] ?? 0) == $rm['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($rm['name']) ?> (<?= htmlspecialchars($rm['room_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Lab Specialty / Technology Type</label>
                        <input type="text" name="lab_type" class="form-control" value="<?= htmlspecialchars($editLab['lab_type'] ?? '') ?>" placeholder="e.g. Hardware & Networking, Food Testing">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Student Workstation Capacity</label>
                        <input type="number" name="capacity" class="form-control" min="1" max="200" value="<?= htmlspecialchars((string)($editLab['capacity'] ?? 40)) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Department</label>
                    <select name="department_id" class="form-select">
                        <option value="">-- Central / Shared Lab --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>" <?= (($editLab['department_id'] ?? 0) == $dept['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept['name']) ?> (<?= htmlspecialchars($dept['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; cursor: pointer;">
                        <input type="checkbox" name="active" value="1" <?= (!isset($editLab['active']) || $editLab['active']) ? 'checked' : '' ?>>
                        Lab is Available for Practical Schedules
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="<?= $editLab ? "window.location.href='labs.php'" : "App.closeModal('labModal')" ?>">Cancel</button>
                <button type="submit" class="btn btn-accent"><?= $editLab ? 'Update Lab' : 'Save Lab' ?></button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    App.setupTableSearch('labSearch', 'labTable');
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>