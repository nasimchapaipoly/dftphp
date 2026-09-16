<?php
/**
 * Class Routine Management System (NasimSoft)
 * Production Admin Dashboard
 * Copyright (c) 2026 NasimSoft.
 */

declare(strict_types=1);

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';

$db = Database::getInstance();

// Retrieve counts for dashboard cards
$stats = [
    'departments' => (int)$db->query("SELECT COUNT(*) FROM departments WHERE active = 1")->fetchColumn(),
    'technologies' => (int)$db->query("SELECT COUNT(*) FROM technologies WHERE active = 1")->fetchColumn(),
    'semesters' => (int)$db->query("SELECT COUNT(*) FROM semesters WHERE active = 1")->fetchColumn(),
    'groups' => (int)$db->query("SELECT COUNT(*) FROM `groups` WHERE active = 1")->fetchColumn(),
    'subjects' => (int)$db->query("SELECT COUNT(*) FROM subjects WHERE active = 1")->fetchColumn(),
    'teachers' => (int)$db->query("SELECT COUNT(*) FROM teachers WHERE active = 1")->fetchColumn(),
    'rooms' => (int)$db->query("SELECT COUNT(*) FROM rooms WHERE active = 1")->fetchColumn(),
    'labs' => (int)$db->query("SELECT COUNT(*) FROM labs WHERE active = 1")->fetchColumn(),
    'routines' => (int)$db->query("SELECT COUNT(*) FROM routines")->fetchColumn(),
    'published' => (int)$db->query("SELECT COUNT(*) FROM routines WHERE status = 'PUBLISHED'")->fetchColumn(),
    'drafts' => (int)$db->query("SELECT COUNT(*) FROM routines WHERE status = 'DRAFT'")->fetchColumn(),
];

// Check current conflict count in routines table
$teacherConflicts = (int)$db->query("
    SELECT COUNT(*) FROM (
        SELECT teacher_id, day, period_start, COUNT(*) as c
        FROM routines
        GROUP BY teacher_id, day, period_start
        HAVING c > 1
    ) t
")->fetchColumn();

$roomConflicts = (int)$db->query("
    SELECT COUNT(*) FROM (
        SELECT room_id, day, period_start, COUNT(*) as c
        FROM routines
        GROUP BY room_id, day, period_start
        HAVING c > 1
    ) r
")->fetchColumn();

// Fetch recent routine entries
$recentRoutines = $db->query("
    SELECT r.*, s.name as subject_name, s.subject_code, t.name as teacher_name, t.short_code as teacher_code, rm.room_code, g.short_code as group_code
    FROM routines r
    LEFT JOIN subjects s ON r.subject_id = s.id
    LEFT JOIN teachers t ON r.teacher_id = t.id
    LEFT JOIN rooms rm ON r.room_id = rm.id
    LEFT JOIN `groups` g ON r.group_id = g.id
    ORDER BY r.id DESC
    LIMIT 6
")->fetchAll();
?>

<!-- Conflict Warning Banner -->
<?php if ($teacherConflicts > 0 || $roomConflicts > 0): ?>
    <div class="alert alert-danger" style="margin-bottom: 24px;">
        <div>
            <strong>Attention: Schedule Conflicts Detected!</strong><br>
            Teacher Overlaps: <?= $teacherConflicts ?> &bull; Room Overlaps: <?= $roomConflicts ?>.
        </div>
        <a href="<?= ADMIN_URL ?>/routine-bulk-edit.php" class="btn btn-danger btn-sm">Resolve Now</a>
    </div>
<?php endif; ?>

<!-- Quick Actions Toolbar -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <h3>&#9889; Quick Action Center</h3>
        <span class="badge badge-accent">Production Ready</span>
    </div>
    <div class="card-body" style="display: flex; flex-wrap: wrap; gap: 12px;">
        <a href="<?= ADMIN_URL ?>/routine-create.php" class="btn btn-accent">&#10133; Create Routine</a>
        <a href="<?= ADMIN_URL ?>/routine-generate.php" class="btn btn-primary">&#9881; Auto Generate</a>
        <a href="<?= ADMIN_URL ?>/bulk-generate.php" class="btn btn-primary">&#9889; Generate All (Bulk)</a>
        <a href="<?= ADMIN_URL ?>/routine-bulk-edit.php" class="btn btn-outline">&#9998; Bulk Edit / Delete</a>
        <a href="<?= ADMIN_URL ?>/routine-master.php?view_type=teacher" target="_blank" class="btn btn-outline">&#128104;&#8205;&#127979; Teacher Wise</a>
        <a href="<?= ADMIN_URL ?>/routine-master.php?view_type=room" target="_blank" class="btn btn-outline">&#127963; Room Wise</a>
        <a href="<?= PUBLIC_URL ?>/index.php" target="_blank" class="btn btn-outline">&#127760; Public Portal</a>
    </div>
</div>

<!-- Primary Statistics Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">&#127979;</div>
        <div class="stat-details">
            <div class="stat-value"><?= $stats['departments'] ?></div>
            <div class="stat-label">Departments</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">&#128295;</div>
        <div class="stat-details">
            <div class="stat-value"><?= $stats['technologies'] ?></div>
            <div class="stat-label">Technologies</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">&#128101;</div>
        <div class="stat-details">
            <div class="stat-value"><?= $stats['groups'] ?></div>
            <div class="stat-label">Active Groups</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">&#128218;</div>
        <div class="stat-details">
            <div class="stat-value"><?= $stats['subjects'] ?></div>
            <div class="stat-label">Subjects</div>
        </div>
    </div>
    <div class="stat-card accent">
        <div class="stat-icon">&#128104;&#8205;&#127979;</div>
        <div class="stat-details">
            <div class="stat-value"><?= $stats['teachers'] ?></div>
            <div class="stat-label">Faculty / Teachers</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">&#127963;</div>
        <div class="stat-details">
            <div class="stat-value"><?= $stats['rooms'] ?></div>
            <div class="stat-label">Classrooms</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">&#128187;</div>
        <div class="stat-details">
            <div class="stat-value"><?= $stats['labs'] ?></div>
            <div class="stat-label">Labs / Workshops</div>
        </div>
    </div>
    <div class="stat-card success">
        <div class="stat-icon">&#128197;</div>
        <div class="stat-details">
            <div class="stat-value"><?= $stats['routines'] ?></div>
            <div class="stat-label">Total Routine Slots</div>
        </div>
    </div>
</div>

<!-- Recent Routine Entries -->
<div class="card">
    <div class="card-header">
        <h3>&#128197; Recent Schedule Allocations</h3>
        <a href="<?= ADMIN_URL ?>/routines.php" class="btn btn-outline btn-sm">View All Routines &rarr;</a>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Group</th>
                        <th>Day & Period</th>
                        <th>Subject</th>
                        <th>Teacher</th>
                        <th>Room</th>
                        <th>Type</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentRoutines)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                No routine entries scheduled yet. Click <strong>Auto Generate</strong> or <strong>Create Routine</strong> to start.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentRoutines as $item): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($item['group_code'] ?? 'Group') ?></strong></td>
                                <td>
                                    <?= htmlspecialchars($item['day']) ?>,
                                    <?= $item['period_start'] === $item['period_end'] ? $item['period_start'] . 'th Period' : $item['period_start'] . 'th - ' . $item['period_end'] . 'th' ?>
                                </td>
                                <td><?= htmlspecialchars($item['subject_name']) ?> (<?= htmlspecialchars($item['subject_code']) ?>)</td>
                                <td><?= htmlspecialchars($item['teacher_name']) ?> (<strong><?= htmlspecialchars($item['teacher_code']) ?></strong>)</td>
                                <td><span class="badge badge-primary"><?= htmlspecialchars($item['room_code']) ?></span></td>
                                <td>
                                    <span class="badge <?= $item['is_lab'] ? 'badge-accent' : 'badge-info' ?>">
                                        <?= $item['is_lab'] ? 'Lab (2 Loads)' : 'Theory (1 Load)' ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $item['status'] === 'PUBLISHED' ? 'badge-success' : 'badge-warning' ?>">
                                        <?= htmlspecialchars($item['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>