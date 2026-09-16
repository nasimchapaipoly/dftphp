<?php
/**
 * Class Routine Management System (NasimSoft)
 * Public Document Share Link — no login required.
 * Copyright (c) 2026 NasimSoft.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$db = Database::getInstance();
$token = trim($_GET['token'] ?? '');

$doc = null;
if ($token !== '') {
    $stmt = $db->prepare("SELECT * FROM generated_documents WHERE share_token = ?");
    $stmt->execute([$token]);
    $doc = $stmt->fetch();
}

if (!$doc || empty($doc['file_path']) || !file_exists(__DIR__ . '/../' . $doc['file_path'])) {
    http_response_code(404);
    $siteName = getSetting('institute_name', 'Class Routine Management System');
    $primaryColor = getSetting('theme_primary_color', '#123FA6');
    $accentColor = getSetting('theme_accent_color', '#1FA855');
    $assetsUrl = defined('ASSETS_URL') ? ASSETS_URL : '../assets';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Document Not Found - <?= htmlspecialchars($siteName) ?></title>
        <link rel="stylesheet" href="<?= htmlspecialchars($assetsUrl) ?>/css/style.css">
        <style>
            :root { --navy: <?= htmlspecialchars($primaryColor) ?>; --orange: <?= htmlspecialchars($accentColor) ?>; }
            body { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--brand-gradient); padding: 20px; }
            .card { background: #FFF; border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); max-width: 440px; width: 100%; padding: 36px; text-align: center; }
        </style>
    </head>
    <body>
        <div class="card">
            <h2 style="color: var(--navy); font-size: 19px; margin-bottom: 10px;">&#128203; Document Not Available</h2>
            <p style="color: var(--text-muted); font-size: 13.5px; line-height: 1.6;">
                This share link has expired, been removed, or never existed. Please ask for a new link.
            </p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Track usage
$db->prepare("UPDATE generated_documents SET download_count = download_count + 1 WHERE id = ?")->execute([$doc['id']]);

$filePath = __DIR__ . '/../' . $doc['file_path'];
$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$downloadName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $doc['entity_label']) . '_' . $doc['academic_year'] . '.' . $ext;

if ($ext === 'pdf') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
} else {
    // HTML fallback (shown when DOMPDF hasn't been installed yet)
    header('Content-Type: text/html; charset=utf-8');
    readfile($filePath);
}
exit;
