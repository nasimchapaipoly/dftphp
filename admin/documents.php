<?php
/**
 * Class Routine Management System (NasimSoft)
 * Document Center — Save, Share, and manage generated routine documents.
 * Copyright (c) 2026 NasimSoft.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/exporter.php';

$db = Database::getInstance();
$uploadDir = __DIR__ . '/../assets/uploads/documents/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

function docGenerateShareToken(): string {
    return bin2hex(random_bytes(16));
}

function docResolveEntityLabel(PDO $db, string $viewType, int $id): string {
    switch ($viewType) {
        case 'group':
            $stmt = $db->prepare("SELECT short_code FROM `groups` WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetchColumn() ?: "Group #{$id}";
        case 'teacher':
            $stmt = $db->prepare("SELECT name FROM teachers WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetchColumn() ?: "Teacher #{$id}";
        case 'room':
            $stmt = $db->prepare("SELECT room_code FROM rooms WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetchColumn() ?: "Room #{$id}";
        default:
            return 'Complete Institute Routine';
    }
}

/**
 * Build the document, render it (PDF via DOMPDF if available, otherwise a
 * styled HTML fallback), save it to disk, and upsert the generated_documents
 * row. Returns the new/updated row id.
 */
function docGenerateAndSave(PDO $db, string $viewType, int $id, string $academicYear, int $shiftId, string $status, ?int $existingDocId = null): array {
    require_once __DIR__ . '/../includes/routine-print-template.php';

    $exporter = new RoutineExporter($db);
    $doc = $exporter->buildDocument($viewType, $id, $academicYear, $shiftId);
    $doc['for_pdf'] = true;
    if ($status === 'draft') {
        $doc['watermark'] = 'DRAFT';
    }

    $bodyHtml = renderRoutinePrintDocument($doc);
    $pdfBytes = renderHtmlToPdfBytes($bodyHtml, 'landscape');

    $entityLabel = docResolveEntityLabel($db, $viewType, $id);
    $shareToken = $existingDocId ? null : docGenerateShareToken();
    $uploadDir = __DIR__ . '/../assets/uploads/documents/';

    if ($pdfBytes !== null) {
        $filename = 'doc_' . ($existingDocId ?: uniqid()) . '.pdf';
        file_put_contents($uploadDir . $filename, $pdfBytes);
        $filePath = 'assets/uploads/documents/' . $filename;
    } else {
        // DOMPDF not installed yet — save a styled standalone HTML file instead,
        // so Save/Share still works; it'll become a real PDF once composer
        // install has been run, via the "Regenerate" action.
        $filename = 'doc_' . ($existingDocId ?: uniqid()) . '.html';
        $standalone = '<!DOCTYPE html><html><head><meta charset="UTF-8"><link rel="stylesheet" href="' . (defined('ASSETS_URL') ? ASSETS_URL : '') . '/css/style.css"></head><body style="padding:30px;">' . $bodyHtml . '</body></html>';
        file_put_contents($uploadDir . $filename, $standalone);
        $filePath = 'assets/uploads/documents/' . $filename;
    }

    if ($existingDocId) {
        $stmt = $db->prepare("UPDATE generated_documents SET entity_label = ?, academic_year = ?, shift_id = ?, status = ?, file_path = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$entityLabel, $academicYear, $shiftId ?: null, $status, $filePath, $existingDocId]);
        return ['id' => $existingDocId, 'is_new' => false];
    }

    $stmt = $db->prepare("INSERT INTO generated_documents (doc_type, entity_id, entity_label, shift_id, academic_year, status, file_path, share_token, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$viewType, $viewType === 'bulk' ? null : $id, $entityLabel, $shiftId ?: null, $academicYear, $status, $filePath, $shareToken, $_SESSION['user_id'] ?? null]);
    return ['id' => (int)$db->lastInsertId(), 'is_new' => true];
}

// ---------------------------------------------------------------------
// Actions
// ---------------------------------------------------------------------

// Quick Save (from the export.php preview page's "Save to Document Center" link)
if (isset($_GET['quick_save']) && verifyCsrfToken($_GET['csrf_token'] ?? '')) {
    $viewType = in_array($_GET['view_type'] ?? '', ['group', 'teacher', 'room', 'bulk'], true) ? $_GET['view_type'] : 'group';
    $entityId = (int)($_GET['id'] ?? 0);
    $shiftId = (int)($_GET['shift_id'] ?? 0);
    $academicYear = trim($_GET['academic_year'] ?? getSetting('academic_year', '2026'));

    $result = docGenerateAndSave($db, $viewType, $entityId, $academicYear, $shiftId, 'final');
    logAudit('Generate Document', 'Documents', $result['id'], "Saved {$viewType} document");
    setFlash('success', 'Document saved to the Document Center.');
    header('Location: documents.php?highlight=' . $result['id']);
    exit;
}

// Generate new (from the modal form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'generate') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Security token expired. Please try again.');
    } else {
        $viewType = in_array($_POST['doc_type'] ?? '', ['group', 'teacher', 'room', 'bulk'], true) ? $_POST['doc_type'] : 'group';
        $entityId = (int)($_POST['entity_id'] ?? 0);
        $shiftId = (int)($_POST['shift_id'] ?? 0);
        $academicYear = trim($_POST['academic_year'] ?? getSetting('academic_year', '2026'));
        $status = ($_POST['status'] ?? 'final') === 'draft' ? 'draft' : 'final';

        if ($viewType !== 'bulk' && $entityId <= 0) {
            setFlash('error', 'Please select what this document is for.');
        } else {
            $result = docGenerateAndSave($db, $viewType, $entityId, $academicYear, $shiftId, $status);
            logAudit('Generate Document', 'Documents', $result['id'], "Generated {$viewType} document ({$status})");
            setFlash('success', 'Document generated successfully.');
        }
    }
    header('Location: documents.php');
    exit;
}

// Regenerate (refresh an existing document from current live data)
if (isset($_GET['regenerate']) && verifyCsrfToken($_GET['csrf_token'] ?? '')) {
    $docId = (int)$_GET['regenerate'];
    $stmt = $db->prepare("SELECT * FROM generated_documents WHERE id = ?");
    $stmt->execute([$docId]);
    $row = $stmt->fetch();

    if ($row) {
        docGenerateAndSave($db, $row['doc_type'], (int)$row['entity_id'], $row['academic_year'], (int)($row['shift_id'] ?? 0), $row['status'], $docId);
        logAudit('Regenerate Document', 'Documents', $docId, 'Refreshed from live data');
        setFlash('success', 'Document refreshed from current live data.');
    } else {
        setFlash('error', 'Document not found.');
    }
    header('Location: documents.php');
    exit;
}

// Toggle Draft <-> Final
if (isset($_GET['toggle_status']) && verifyCsrfToken($_GET['csrf_token'] ?? '')) {
    $docId = (int)$_GET['toggle_status'];
    $stmt = $db->prepare("SELECT * FROM generated_documents WHERE id = ?");
    $stmt->execute([$docId]);
    $row = $stmt->fetch();

    if ($row) {
        $newStatus = $row['status'] === 'draft' ? 'final' : 'draft';
        docGenerateAndSave($db, $row['doc_type'], (int)$row['entity_id'], $row['academic_year'], (int)($row['shift_id'] ?? 0), $newStatus, $docId);
        logAudit('Update Document Status', 'Documents', $docId, "Changed to {$newStatus}");
        setFlash('success', 'Document marked as ' . ucfirst($newStatus) . '.');
    }
    header('Location: documents.php');
    exit;
}

// Delete
if (isset($_GET['delete']) && verifyCsrfToken($_GET['csrf_token'] ?? '')) {
    $docId = (int)$_GET['delete'];
    $stmt = $db->prepare("SELECT file_path FROM generated_documents WHERE id = ?");
    $stmt->execute([$docId]);
    $filePath = $stmt->fetchColumn();

    if ($filePath && file_exists(__DIR__ . '/../' . $filePath)) {
        unlink(__DIR__ . '/../' . $filePath);
    }
    $db->prepare("DELETE FROM generated_documents WHERE id = ?")->execute([$docId]);
    logAudit('Delete Document', 'Documents', $docId, 'Removed from Document Center');
    setFlash('success', 'Document deleted.');
    header('Location: documents.php');
    exit;
}

$pageTitle = 'Document Center';
require_once __DIR__ . '/../includes/header.php';

// ---------------------------------------------------------------------
// Data for display
// ---------------------------------------------------------------------
$documents = $db->query("SELECT * FROM generated_documents ORDER BY updated_at DESC")->fetchAll();
$stats = [
    'total' => count($documents),
    'draft' => count(array_filter($documents, fn($d) => $d['status'] === 'draft')),
    'final' => count(array_filter($documents, fn($d) => $d['status'] === 'final')),
    'downloads' => array_sum(array_column($documents, 'download_count')),
];

$groups = $db->query("SELECT id, short_code FROM `groups` WHERE active = 1 ORDER BY short_code ASC")->fetchAll();
$teachersList = $db->query("SELECT id, name, short_code FROM teachers WHERE active = 1 ORDER BY name ASC")->fetchAll();
$roomsList = $db->query("SELECT id, room_code FROM rooms WHERE active = 1 ORDER BY room_code ASC")->fetchAll();
$shifts = $db->query("SELECT id, name FROM shifts WHERE active = 1 ORDER BY id ASC")->fetchAll();
$academicYear = getSetting('academic_year', '2026');
$pdfReady = isPdfEngineReady();
$highlightId = (int)($_GET['highlight'] ?? 0);
$publicShareBase = (defined('BASE_URL') ? BASE_URL : '') . '/public/document.php?token=';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
    <div>
        <h2 style="color: var(--navy); font-size: 20px; font-weight: 700;">Document Center</h2>
        <p style="color: var(--text-muted); font-size: 13px;">Save, share, and manage generated routine documents — as a live draft or a final published version.</p>
    </div>
    <button class="btn btn-accent" onclick="App.openModal('generateModal')">&#10133; Generate Document</button>
</div>

<?php if (!$pdfReady): ?>
<div class="alert alert-warning" data-no-auto-dismiss="1">
    <strong>Real PDF generation isn't active yet.</strong> Documents are being saved as styled HTML for now.
    To generate true, downloadable <code>.pdf</code> files, run this once on your server (via SSH) in the project's root folder:
    <code style="display:block; background:#00000010; padding:8px 10px; border-radius:6px; margin-top:8px; font-size:12.5px;">composer require dompdf/dompdf</code>
    Then click "Regenerate" on any saved document below to convert it to a real PDF — no re-upload needed.
</div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">&#128196;</div>
        <div class="stat-details">
            <div class="stat-value"><?= $stats['total'] ?></div>
            <div class="stat-label">Total Documents</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:var(--warning-bg); color:var(--warning);">&#9998;</div>
        <div class="stat-details">
            <div class="stat-value" style="color:var(--warning);"><?= $stats['draft'] ?></div>
            <div class="stat-label">Drafts (Live/Editable)</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:var(--success-bg); color:var(--success);">&#9989;</div>
        <div class="stat-details">
            <div class="stat-value" style="color:var(--success);"><?= $stats['final'] ?></div>
            <div class="stat-label">Final / Published</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">&#11015;</div>
        <div class="stat-details">
            <div class="stat-value"><?= $stats['downloads'] ?></div>
            <div class="stat-label">Total Downloads</div>
        </div>
    </div>
</div>

<!-- Document List -->
<div class="card">
    <div class="card-header">
        <h3>&#128193; Saved Documents</h3>
        <input type="text" id="docSearch" class="form-control" placeholder="Search documents..." style="max-width: 240px;">
    </div>
    <div class="table-responsive">
        <table class="table" id="docTable">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Entity</th>
                    <th>Academic Year</th>
                    <th>Status</th>
                    <th>Downloads</th>
                    <th>Updated</th>
                    <th style="text-align:right; width: 280px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($documents)): ?>
                    <tr><td colspan="7" style="text-align:center; padding: 30px; color: var(--text-muted);">
                        No documents generated yet. Click "Generate Document" above to create your first one.
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($documents as $d):
                        $icon = ['group' => '&#128101;', 'teacher' => '&#128104;&#8205;&#127979;', 'room' => '&#127963;', 'bulk' => '&#127760;'][$d['doc_type']] ?? '&#128196;';
                        $isHighlighted = $highlightId === (int)$d['id'];
                        $fileExt = pathinfo($d['file_path'] ?? '', PATHINFO_EXTENSION);
                    ?>
                        <tr style="<?= $isHighlighted ? 'background: var(--success-bg);' : '' ?>">
                            <td><?= $icon ?> <?= ucfirst($d['doc_type']) ?></td>
                            <td><strong><?= e($d['entity_label']) ?></strong></td>
                            <td><?= e($d['academic_year']) ?></td>
                            <td>
                                <span class="badge <?= $d['status'] === 'draft' ? 'badge-warning' : 'badge-success' ?>">
                                    <?= $d['status'] === 'draft' ? '&#9998; Draft' : '&#9989; Final' ?>
                                </span>
                                <?php if ($fileExt !== 'pdf'): ?>
                                    <span class="badge badge-info" title="Will become a real PDF after composer install">HTML</span>
                                <?php endif; ?>
                            </td>
                            <td><?= (int)$d['download_count'] ?></td>
                            <td style="font-size: 12px; color: var(--text-muted);"><?= date('d M Y, h:i A', strtotime($d['updated_at'])) ?></td>
                            <td style="text-align:right; white-space: nowrap;">
                                <a href="../<?= e($d['file_path']) ?>" target="_blank" class="btn btn-outline btn-sm">&#128065; View</a>
                                <button type="button" class="btn btn-outline btn-sm" onclick="copyShareLink('<?= e($d['share_token']) ?>')">&#128279; Share</button>
                                <a href="documents.php?regenerate=<?= (int)$d['id'] ?>&csrf_token=<?= generateCsrfToken() ?>" class="btn btn-outline btn-sm">&#128260;</a>
                                <a href="documents.php?toggle_status=<?= (int)$d['id'] ?>&csrf_token=<?= generateCsrfToken() ?>" class="btn btn-outline btn-sm"><?= $d['status'] === 'draft' ? 'Publish' : 'Unpublish' ?></a>
                                <button type="button" class="btn btn-danger btn-sm" onclick="App.confirm('Delete this saved document permanently?', () => { window.location.href='documents.php?delete=<?= (int)$d['id'] ?>&csrf_token=<?= generateCsrfToken() ?>'; })">&#128465;</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Generate Document Modal -->
<div class="modal-overlay" id="generateModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h4>&#10133; Generate Document</h4>
            <button class="modal-close" onclick="App.closeModal('generateModal')">&times;</button>
        </div>
        <form method="POST" action="documents.php">
            <div class="modal-body">
                <?= csrfField() ?>
                <input type="hidden" name="form" value="generate">

                <div class="form-group">
                    <label class="form-label">Document Type *</label>
                    <select name="doc_type" id="docTypeSelect" class="form-select" onchange="toggleEntityField()" required>
                        <option value="group">Group / Section Routine</option>
                        <option value="teacher">Teacher Routine</option>
                        <option value="room">Room / Lab Utilization</option>
                        <option value="bulk">Complete Institute Routine (Bulk)</option>
                    </select>
                </div>

                <div class="form-group" id="entityFieldWrap">
                    <label class="form-label" id="entityFieldLabel">Group *</label>
                    <select name="entity_id" id="entitySelect" class="form-select">
                        <?php foreach ($groups as $g): ?>
                            <option value="<?= $g['id'] ?>"><?= e($g['short_code']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Shift <span style="font-weight:400; color:var(--text-muted);">(Teacher/Room only)</span></label>
                        <select name="shift_id" class="form-select">
                            <option value="0">Auto-detect</option>
                            <?php foreach ($shifts as $sh): ?>
                                <option value="<?= $sh['id'] ?>"><?= e($sh['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Academic Year</label>
                        <input type="text" name="academic_year" class="form-control" value="<?= e($academicYear) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Status</label>
                    <div style="display:flex; gap:16px; margin-top:6px;">
                        <label class="form-check"><input type="radio" name="status" value="final" checked> &#9989; Final (Published)</label>
                        <label class="form-check"><input type="radio" name="status" value="draft"> &#9998; Draft (Live/Editable, watermarked)</label>
                    </div>
                    <span class="form-hint">Drafts show a "DRAFT" watermark and can be freely regenerated as the routine changes. Mark Final when it's ready to share.</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="App.closeModal('generateModal')">Cancel</button>
                <button type="submit" class="btn btn-accent">&#128190; Generate &amp; Save</button>
            </div>
        </form>
    </div>
</div>

<script>
const groupOptions = <?= json_encode(array_map(fn($g) => ['id' => $g['id'], 'label' => $g['short_code']], $groups)) ?>;
const teacherOptions = <?= json_encode(array_map(fn($t) => ['id' => $t['id'], 'label' => $t['name'] . ' (' . $t['short_code'] . ')'], $teachersList)) ?>;
const roomOptions = <?= json_encode(array_map(fn($r) => ['id' => $r['id'], 'label' => $r['room_code']], $roomsList)) ?>;
const shareBase = <?= json_encode($publicShareBase) ?>;

function toggleEntityField() {
    const type = document.getElementById('docTypeSelect').value;
    const wrap = document.getElementById('entityFieldWrap');
    const label = document.getElementById('entityFieldLabel');
    const select = document.getElementById('entitySelect');

    if (type === 'bulk') {
        wrap.style.display = 'none';
        return;
    }
    wrap.style.display = '';

    const map = { group: ['Group / Section *', groupOptions], teacher: ['Teacher *', teacherOptions], room: ['Room / Lab *', roomOptions] };
    const [labelText, options] = map[type] || map.group;
    label.textContent = labelText;
    select.innerHTML = options.map(o => `<option value="${o.id}">${o.label}</option>`).join('');
}

function copyShareLink(token) {
    const url = shareBase + token;
    navigator.clipboard.writeText(url).then(() => {
        App.toast('Share link copied to clipboard!', 'success');
    }).catch(() => {
        prompt('Copy this share link:', url);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    App.setupTableSearch('docSearch', 'docTable');
    <?php if ($highlightId): ?>
    setTimeout(() => { App.toast('Document saved and ready to share!', 'success'); }, 200);
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
