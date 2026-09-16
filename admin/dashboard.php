<?php
/**
 * Administrative Control Center Dashboard
 * Class Routine Management System (NasimSoft)
 * Copyright (c) 2026 NasimSoft. All rights reserved.
 */

declare(strict_types=1);

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';

$pdo = Database::getInstance();

// Fetch Live Statistics
$statCounts = [
    'departments'  => (int)$pdo->query("SELECT COUNT(*) FROM departments WHERE active = 1")->fetchColumn(),
    'technologies' => (int)$pdo->query("SELECT COUNT(*) FROM technologies WHERE active = 1")->fetchColumn(),
    'teachers'     => (int)$pdo->query("SELECT COUNT(*) FROM teachers WHERE active = 1")->fetchColumn(),
    'groups'       => (int)$pdo->query("SELECT COUNT(*) FROM `groups` WHERE active = 1")->fetchColumn(),
    'rooms'        => (int)$pdo->query("SELECT COUNT(*) FROM rooms WHERE active = 1")->fetchColumn(),
    'routines'     => (int)$pdo->query("SELECT COUNT(*) FROM routines WHERE status = 'PUBLISHED'")->fetchColumn(),
];

// Fetch Recent Audit Logs
$recentLogs = [];
try {
    $recentLogs = $pdo->query("
        SELECT a.*, u.username, u.full_name
        FROM audit_logs a
        LEFT JOIN users u ON a.user_id = u.id
        ORDER BY a.created_at DESC
        LIMIT 6
    ")->fetchAll();
} catch (Exception $e) {}

$academicYear = getSetting('academic_year', '2026');
?>

<!-- Welcome Card -->
<div class="welcome-card">
    <div>
        <h1 style="font-size: 22px; font-weight: 800; margin-bottom: 6px;">
            Welcome back, <?= e($userName) ?>!
        </h1>
        <p style="font-size: 13.5px; opacity: 0.9;">
            <?= e($brandFull) ?> Routine System is active for Session <strong><?= e($academicYear) ?></strong>.
        </p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="routine-master.php" class="btn btn-outline-light">&#128197; Open Timetable</a>
        <a href="routine-generate.php" class="btn btn-accent">&#9889; Generate Routine</a>
    </div>
</div>

<!-- Key Metrics Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">&#128101;</div>
        <div class="stat-details">
            <div class="stat-value"><?= $statCounts['groups'] ?></div>
            <div class="stat-label">Active Groups</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">&#128104;&#8205;&#127979;</div>
        <div class="stat-details">
            <div class="stat-value"><?= $statCounts['teachers'] ?></div>
            <div class="stat-label">Faculty Members</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">&#127979;</div>
        <div class="stat-details">
            <div class="stat-value"><?= $statCounts['departments'] ?></div>
            <div class="stat-label">Departments</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">&#128295;</div>
        <div class="stat-details">
            <div class="stat-value"><?= $statCounts['technologies'] ?></div>
            <div class="stat-label">Technologies</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">&#127963;</div>
        <div class="stat-details">
            <div class="stat-value"><?= $statCounts['rooms'] ?></div>
            <div class="stat-label">Rooms &amp; Labs</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--success-bg); color: var(--success);">&#9989;</div>
        <div class="stat-details">
            <div class="stat-value" style="color: var(--success);"><?= $statCounts['routines'] ?></div>
            <div class="stat-label">Scheduled Slots</div>
        </div>
    </div>
</div>

<!-- Quick Action Shortcuts -->
<h3 style="font-size: 16px; font-weight: 800; color: var(--navy); margin-bottom: 14px;">Routine Operations Hub</h3>
<div class="quick-actions" style="margin-bottom: 26px;">
    <a href="routine-master.php" class="action-card">
        <div class="action-icon">&#128197;</div>
        <div style="font-weight: 700; font-size: 14px;">Master Timetable</div>
        <div style="font-size: 12px; color: var(--text-muted);">View group, faculty, or room matrices</div>
    </a>

    <a href="routine-generate.php" class="action-card">
        <div class="action-icon">&#9889;</div>
        <div style="font-weight: 700; font-size: 14px;">Auto Generator</div>
        <div style="font-size: 12px; color: var(--text-muted);">CSP solver for conflict-free routines</div>
    </a>

    <a href="bulk-generate.php" class="action-card">
        <div class="action-icon">&#128640;</div>
        <div style="font-weight: 700; font-size: 14px;">Bulk Generator</div>
        <div style="font-size: 12px; color: var(--text-muted);">Schedule the entire institute at once</div>
    </a>

    <a href="routine-bulk-edit.php" class="action-card">
        <div class="action-icon">&#9998;</div>
        <div style="font-weight: 700; font-size: 14px;">Bulk Edit &amp; Delete</div>
        <div style="font-size: 12px; color: var(--text-muted);">Faculty substitution &amp; batch cleaning</div>
    </a>

    <a href="settings.php" class="action-card">
        <div class="action-icon">&#9881;</div>
        <div style="font-weight: 700; font-size: 14px;">Site &amp; Print Settings</div>
        <div style="font-size: 12px; color: var(--text-muted);">Customise the public portal &amp; print design</div>
    </a>
</div>

<!-- Recent Activity Table -->
<div class="card">
    <div class="card-header">
        <h3>&#128196; Recent System Events</h3>
        <a href="audit-logs.php" style="font-size: 12.5px; color: var(--orange); text-decoration: none; font-weight: 700;">View All Activity &rarr;</a>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Module</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentLogs)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 25px;">
                            No audit entries recorded yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentLogs as $log): ?>
                        <tr>
                            <td style="font-size: 12px; color: var(--text-muted);"><?= date('d-M h:i A', strtotime($log['created_at'])) ?></td>
                            <td><strong><?= e($log['full_name'] ?? ($log['username'] ?? 'System')) ?></strong></td>
                            <td><span style="font-weight: 700; color: var(--navy);"><?= e($log['action']) ?></span></td>
                            <td><code><?= e($log['module']) ?></code></td>
                            <td style="color: var(--text-muted);"><?= e($log['details'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
