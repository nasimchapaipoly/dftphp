<?php
/**
 * Class Routine Management System (NasimSoft)
 * Room Management
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
    csvDownload('rooms-template.csv',
        ['room_code', 'name', 'room_type', 'capacity', 'department_code', 'building', 'floor', 'active'],
        [['S-316', 'Room S-316', 'NORMAL', '60', 'CST', 'South Building', '3rd Floor', '1']]
    );
}

// CSV Export
if (($_GET['export'] ?? '') === 'csv') {
    $rows = $db->query("SELECT r.room_code, r.name, r.room_type, r.capacity, d.code as department_code, r.building, r.floor, r.active FROM rooms r LEFT JOIN departments d ON r.department_id = d.id ORDER BY r.room_code ASC")->fetchAll(PDO::FETCH_NUM);
    csvDownload('rooms-export-' . date('Y-m-d') . '.csv',
        ['room_code', 'name', 'room_type', 'capacity', 'department_code', 'building', 'floor', 'active'],
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

            $created = 0; $updated = 0; $errors = [];
            foreach ($rows as $i => $row) {
                $line = $i + 2;
                $code = strtoupper(trim($row['room_code'] ?? ''));
                $name = trim($row['name'] ?? '');

                if ($code === '' || $name === '') {
                    $errors[] = "Line {$line}: room_code and name are required";
                    continue;
                }

                $deptCode = strtoupper(trim($row['department_code'] ?? ''));
                $deptId = $deptMap[$deptCode] ?? null;
                $roomType = strtoupper(trim($row['room_type'] ?? '')) === 'LAB' ? 'LAB' : 'NORMAL';
                $capacity = max(1, (int)($row['capacity'] ?? 60));
                $building = trim($row['building'] ?? '');
                $floor = trim($row['floor'] ?? '');
                $active = isset($row['active']) && $row['active'] !== '' ? (int)(bool)(int)$row['active'] : 1;

                $existing = $db->prepare("SELECT id FROM rooms WHERE room_code = ?");
                $existing->execute([$code]);
                $existingId = $existing->fetchColumn();

                if ($existingId) {
                    $stmt = $db->prepare("UPDATE rooms SET name=?, room_type=?, capacity=?, department_id=?, building=?, floor=?, active=? WHERE id=?");
                    $stmt->execute([$name, $roomType, $capacity, $deptId, $building, $floor, $active, $existingId]);
                    $updated++;
                } else {
                    $stmt = $db->prepare("INSERT INTO rooms (name, room_code, room_type, capacity, department_id, building, floor, active) VALUES (?,?,?,?,?,?,?,?)");
                    $stmt->execute([$name, $code, $roomType, $capacity, $deptId, $building, $floor, $active]);
                    $created++;
                }
            }
            logAudit('Bulk Import Rooms', 'Rooms', 0, "Created {$created}, updated {$updated}, " . count($errors) . ' errors');
            csvImportSummaryFlash($created, $updated, $errors);
        } catch (Exception $e) {
            setFlash('error', $e->getMessage());
        }
        header('Location: rooms.php');
        exit;
    }
}

if ($deleteId > 0 && verifyCsrfToken($_GET['csrf_token'] ?? '')) {
    $stmt = $db->prepare("DELETE FROM rooms WHERE id = ?");
    $stmt->execute([$deleteId]);
    logAudit('Delete Room', 'Rooms', $deleteId, 'Deleted room');
    setFlash('success', 'Room deleted successfully.');
    header('Location: rooms.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Security token expired. Please try again.');
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $roomCode = strtoupper(trim($_POST['room_code'] ?? ''));
        $roomType = in_array($_POST['room_type'] ?? '', ['NORMAL', 'LAB'], true) ? $_POST['room_type'] : 'NORMAL';
        $capacity = (int)($_POST['capacity'] ?? 60);
        $deptId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $floor = trim($_POST['floor'] ?? '');
        $building = trim($_POST['building'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;

        if (empty($name) || empty($roomCode)) {
            setFlash('error', 'Room Name and Room Code are required.');
        } else {
            $dupCheck = $db->prepare("SELECT id FROM rooms WHERE room_code = ? AND id != ?");
            $dupCheck->execute([$roomCode, $id]);
            if ($dupCheck->fetch()) {
                setFlash('error', "Room code '{$roomCode}' is already assigned.");
            } else {
                if ($id > 0) {
                    $stmt = $db->prepare("UPDATE rooms SET name = ?, room_code = ?, room_type = ?, capacity = ?, department_id = ?, floor = ?, building = ?, active = ? WHERE id = ?");
                    $stmt->execute([$name, $roomCode, $roomType, $capacity, $deptId, $floor, $building, $active, $id]);
                    logAudit('Update Room', 'Rooms', $id, "Updated room {$name} ({$roomCode})");
                    setFlash('success', 'Room updated successfully.');
                } else {
                    $stmt = $db->prepare("INSERT INTO rooms (name, room_code, room_type, capacity, department_id, floor, building, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $roomCode, $roomType, $capacity, $deptId, $floor, $building, $active]);
                    $newId = (int)$db->lastInsertId();
                    logAudit('Create Room', 'Rooms', $newId, "Created room {$name} ({$roomCode})");
                    setFlash('success', 'Room created successfully.');
                }
                header('Location: rooms.php');
                exit;
            }
        }
    }
}

$editRoom = null;
if ($editId > 0) {
    $stmt = $db->prepare("SELECT * FROM rooms WHERE id = ?");
    $stmt->execute([$editId]);
    $editRoom = $stmt->fetch();
}

$departments = $db->query("SELECT id, name, code FROM departments WHERE active = 1 ORDER BY name ASC")->fetchAll();
$rooms = $db->query("
    SELECT r.*, d.name as department_name, d.code as department_code,
           (SELECT COUNT(*) FROM routines WHERE room_id = r.id) as scheduled_classes
    FROM rooms r
    LEFT JOIN departments d ON r.department_id = d.id
    ORDER BY r.id DESC
")->fetchAll();
$pageTitle = 'Classroom & Room Management';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h2 style="color: var(--navy-blue); font-size: 20px; font-weight: 700;">Classroom & Space Management</h2>
        <p style="color: var(--text-muted); font-size: 13px;">Manage lecture rooms, seminar halls, room codes, and seating capacities.</p>
    </div>
    <button class="btn btn-outline" onclick="App.openModal('csvModal')">&#128228; Import / Export</button>
    <button class="btn btn-accent" onclick="App.openModal('roomModal')">&#10133; Add Room</button>
</div>

<!-- CSV Import/Export Modal -->
<div class="modal-overlay" id="csvModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h4>&#128228; Bulk Import / Export Rooms</h4>
            <button class="modal-close" onclick="App.closeModal('csvModal')">&times;</button>
        </div>
        <div class="modal-body">
            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 14px;">
                Update many rooms at once with a CSV file. Matching is done by <strong>room_code</strong> —
                existing codes are updated, new codes are created.
            </p>
            <div style="display:flex; gap:10px; margin-bottom:18px;">
                <a href="rooms.php?export=csv" class="btn btn-outline btn-sm">&#11015; Export Current Data</a>
                <a href="rooms.php?template=csv" class="btn btn-outline btn-sm">&#128196; Download Template</a>
            </div>
            <form method="POST" action="rooms.php" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="form" value="csv_import">
                <div class="form-group">
                    <label class="form-label">Choose CSV File *</label>
                    <input type="file" name="csv_file" accept=".csv" class="form-control" required>
                    <span class="form-hint">Columns: room_code, name, room_type (NORMAL/LAB), capacity, department_code, building, floor, active</span>
                </div>
                <button type="submit" class="btn btn-accent" style="width:100%;">&#128228; Upload &amp; Import</button>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>&#127963; Classroom Inventory</h3>
        <input type="text" id="roomSearch" class="form-control" placeholder="Search room code or name..." style="max-width: 250px; padding: 6px 12px; font-size: 13px;">
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" id="roomTable">
                <thead>
                    <tr>
                        <th style="width: 50px;">SL</th>
                        <th>Room Code</th>
                        <th>Room Name</th>
                        <th>Type</th>
                        <th>Capacity</th>
                        <th>Building & Floor</th>
                        <th>Department</th>
                        <th>Assigned Slots</th>
                        <th>Status</th>
                        <th style="width: 130px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rooms)): ?>
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 25px; color: var(--text-muted);">No classrooms created yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rooms as $idx => $r): ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td><strong style="color: var(--navy-blue); font-size: 13.5px;"><?= htmlspecialchars($r['room_code']) ?></strong></td>
                                <td><?= htmlspecialchars($r['name']) ?></td>
                                <td>
                                    <span class="badge <?= $r['room_type'] === 'LAB' ? 'badge-accent' : 'badge-primary' ?>">
                                        <?= $r['room_type'] === 'LAB' ? 'Lab Room' : 'Normal Room' ?>
                                    </span>
                                </td>
                                <td><strong><?= $r['capacity'] ?></strong> seats</td>
                                <td>
                                    <div><?= htmlspecialchars($r['building'] ?: 'Main Campus') ?></div>
                                    <small style="color: var(--text-muted);"><?= htmlspecialchars($r['floor'] ?: '') ?></small>
                                </td>
                                <td><?= htmlspecialchars($r['department_code'] ?? 'General') ?></td>
                                <td><span class="badge badge-info"><?= $r['scheduled_classes'] ?> slots</span></td>
                                <td>
                                    <span class="badge <?= $r['active'] ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $r['active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <a href="rooms.php?edit=<?= $r['id'] ?>" class="btn btn-outline btn-sm">&#9998; Edit</a>
                                    <button class="btn btn-danger btn-sm" onclick="App.confirm('Are you sure you want to delete this room?', () => { window.location.href='rooms.php?delete=<?= $r['id'] ?>&csrf_token=<?= generateCsrfToken() ?>'; })">&#128465;</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Room Modal -->
<div class="modal-overlay <?= $editRoom ? 'active' : '' ?>" id="roomModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h4><?= $editRoom ? 'Edit Classroom' : 'Add New Room' ?></h4>
            <button class="modal-close" onclick="<?= $editRoom ? "window.location.href='rooms.php'" : "App.closeModal('roomModal')" ?>">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $editRoom['id'] ?? 0 ?>">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Room Code (e.g. S-316, N-211) *</label>
                        <input type="text" name="room_code" class="form-control" required value="<?= htmlspecialchars($editRoom['room_code'] ?? '') ?>" placeholder="e.g. S-316">
                        <small style="color: var(--text-muted);">Exact code printed in routine cells.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Room Description / Name *</label>
                        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($editRoom['name'] ?? '') ?>" placeholder="e.g. Room S-316">
                    </div>
                </div>
                <div class="grid-3">
                    <div class="form-group">
                        <label class="form-label">Room Classification *</label>
                        <select name="room_type" class="form-select" required>
                            <option value="NORMAL" <?= (($editRoom['room_type'] ?? 'NORMAL') === 'NORMAL') ? 'selected' : '' ?>>Normal Classroom</option>
                            <option value="LAB" <?= (($editRoom['room_type'] ?? 'NORMAL') === 'LAB') ? 'selected' : '' ?>>Lab / Practical Room</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Seating Capacity *</label>
                        <input type="number" name="capacity" class="form-control" min="1" max="500" required value="<?= htmlspecialchars((string)($editRoom['capacity'] ?? 60)) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Department (Optional)</label>
                        <select name="department_id" class="form-select">
                            <option value="">-- General / Shared --</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['id'] ?>" <?= (($editRoom['department_id'] ?? 0) == $dept['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['name']) ?> (<?= htmlspecialchars($dept['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Building</label>
                        <input type="text" name="building" class="form-control" value="<?= htmlspecialchars($editRoom['building'] ?? 'South Building') ?>" placeholder="e.g. South Building, North Building">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Floor</label>
                        <input type="text" name="floor" class="form-control" value="<?= htmlspecialchars($editRoom['floor'] ?? '3rd Floor') ?>" placeholder="e.g. 3rd Floor">
                    </div>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; cursor: pointer;">
                        <input type="checkbox" name="active" value="1" <?= (!isset($editRoom['active']) || $editRoom['active']) ? 'checked' : '' ?>>
                        Room is Available for Scheduling
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="<?= $editRoom ? "window.location.href='rooms.php'" : "App.closeModal('roomModal')" ?>">Cancel</button>
                <button type="submit" class="btn btn-accent"><?= $editRoom ? 'Update Room' : 'Save Room' ?></button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    App.setupTableSearch('roomSearch', 'roomTable');
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>