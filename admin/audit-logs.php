<?php
/**
 * Class Routine Management System (NasimSoft)
 * Audit Trail & User Activity Log Viewer
 * Copyright (c) 2026 NasimSoft. All rights reserved.
 */

declare(strict_types=1);

$pageTitle = 'Audit Trail & Activity Logs';
require_once __DIR__ . '/../includes/header.php';

Auth::requireSuperAdmin(); // Restrict to Super Administrator
$db = Database::getInstance();

// Search & Filter Parameters
$actionFilter = trim($_GET['action_filter'] ?? '');
$searchQuery = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$whereClauses = ["1=1"];
$params = [];

if ($actionFilter !== '') {
    $whereClauses[] = "a.action = ?";
    $params[] = $actionFilter;
}

if ($searchQuery !== '') {
    $whereClauses[] = "(a.details LIKE ? OR a.entity LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR a.ip_address LIKE ?)";
    $term = "%{$searchQuery}%";
    array_push($params, $term, $term, $term, $term, $term);
}

$whereSql = implode(" AND ", $whereClauses);

// Total Records Count
$countStmt = $db->prepare("
    SELECT COUNT(*) 
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE {$whereSql}
");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

// Fetch Paginated Logs
$logStmt = $db->prepare("
    SELECT a.*, u.name as user_name, u.email as user_email, u.role as user_role
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE {$whereSql}
    ORDER BY a.created_at DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$logStmt->execute($params);
$logs = $logStmt->fetchAll();

// Distinct Action List for Dropdown
$distinctActions = $db->query("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
    <div>
        <h2 style="color: var(--navy-blue); font-size: 22px; font-weight: 800;">System Audit Trail</h2>
        <p style="color: var(--text-muted); font-size: 13px;">Full forensic timeline of system operations, routine modifications, and generator runs.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <span class="badge badge-info" style="font-size: 13px;">Total Events: <?= number_format($totalRows) ?></span>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding: 16px 20px;">
        <form method="GET" action="" style="display: flex; gap: 14px; flex-wrap: wrap; align-items: center;">
            <div style="flex: 1; min-width: 250px;">
                <input type="text" name="q" class="form-control" placeholder="Search by details, user, entity, or IP..." value="<?= htmlspecialchars($searchQuery) ?>">
            </div>
            <div style="width: 220px;">
                <select name="action_filter" class="form-select">
                    <option value="">-- All Actions --</option>
                    <?php foreach ($distinctActions as $act): ?>
                        <option value="<?= htmlspecialchars($act) ?>" <?= $actionFilter === $act ? 'selected' : '' ?>>
                            <?= htmlspecialchars($act) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">&#128269; Filter</button>
            <?php if ($actionFilter !== '' || $searchQuery !== ''): ?>
                <a href="audit-logs.php" class="btn btn-outline">Reset</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Logs Table -->
<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Entity</th>
                        <th>Details</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                No audit records match your search criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $l): ?>
                            <tr>
                                <td style="white-space: nowrap; font-size: 12px; color: var(--text-muted);">
                                    <?= date('d-M-Y h:i:s A', strtotime($l['created_at'])) ?>
                                </td>
                                <td>
                                    <?php if ($l['user_name']): ?>
                                        <strong><?= htmlspecialchars($l['user_name']) ?></strong><br>
                                        <small style="color: var(--text-muted);"><?= htmlspecialchars($l['user_role']) ?></small>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-style: italic;">System / Guest</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= strpos($l['action'], 'Delete') !== false ? 'badge-danger' : 'badge-primary' ?>">
                                        <?= htmlspecialchars($l['action']) ?>
                                    </span>
                                </td>
                                <td>
                                    <code><?= htmlspecialchars($l['entity'] . ($l['entity_id'] ? " #{$l['entity_id']}" : '')) ?></code>
                                </td>
                                <td style="font-size: 12.5px; max-width: 380px;">
                                    <?= htmlspecialchars($l['details'] ?? '-') ?>
                                </td>
                                <td>
                                    <span style="font-family: monospace; font-size: 11.5px;"><?= htmlspecialchars($l['ip_address'] ?? '127.0.0.1') ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Bar -->
        <?php if ($totalPages > 1): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; border-top: 1px solid var(--border-color);">
                <span style="font-size: 13px; color: var(--text-muted);">
                    Showing Page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong>
                </span>
                <div style="display: flex; gap: 6px;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&action_filter=<?= urlencode($actionFilter) ?>&q=<?= urlencode($searchQuery) ?>" class="btn btn-outline btn-sm">&larr; Previous</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>&action_filter=<?= urlencode($actionFilter) ?>&q=<?= urlencode($searchQuery) ?>" class="btn btn-outline btn-sm">Next &rarr;</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>