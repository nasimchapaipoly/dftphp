<?php
/**
 * Class Routine Management System (NasimSoft)
 * Custom 404 — Page Not Found
 * Copyright (c) 2026 NasimSoft.
 *
 * Wired up via .htaccess: ErrorDocument 404 /404.php
 */

declare(strict_types=1);

http_response_code(404);

// Best-effort branding load — this page must still render something sensible
// even if the database is unreachable, so every setting lookup is guarded.
$siteName = 'Class Routine Management System';
$siteShort = 'CR';
$siteLogo = '';
$primaryColor = '#123FA6';
$accentColor = '#1FA855';
$assetsUrl = 'assets';
$publicUrl = 'public';

try {
    require_once __DIR__ . '/config/config.php';
    $siteName = getSetting('institute_name', $siteName);
    $siteShort = getSetting('site_short_name', $siteShort);
    $siteLogo = getSetting('site_logo_url', '');
    $primaryColor = getSetting('theme_primary_color', $primaryColor);
    $accentColor = getSetting('theme_accent_color', $accentColor);
    $assetsUrl = defined('ASSETS_URL') ? ASSETS_URL : 'assets';
    $publicUrl = defined('PUBLIC_URL') ? PUBLIC_URL : 'public';
} catch (Throwable $e) {
    // Config/DB unavailable — fall back to the defaults above, page still works.
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found - <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($assetsUrl) ?>/css/style.css">
    <style>
        :root { --navy: <?= htmlspecialchars($primaryColor) ?>; --navy-dark: <?= htmlspecialchars($primaryColor) ?>; --orange: <?= htmlspecialchars($accentColor) ?>; }
        body {
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: var(--brand-gradient); padding: 20px;
        }
        .error-card {
            background: #FFF; border-radius: var(--radius-lg); box-shadow: var(--shadow-lg);
            max-width: 480px; width: 100%; padding: 40px 34px; text-align: center;
            animation: heroIn 0.4s ease both;
        }
        .error-logo { width: 54px; height: 54px; border-radius: 14px; object-fit: cover; margin: 0 auto 18px; }
        .error-code {
            font-family: var(--font-heading); font-size: 62px; font-weight: 800;
            background: var(--brand-gradient); -webkit-background-clip: text; background-clip: text;
            color: transparent; line-height: 1; margin-bottom: 6px;
        }
        .error-title { font-size: 18px; font-weight: 700; color: var(--text-dark); margin-bottom: 10px; }
        .error-text { font-size: 13.5px; color: var(--text-muted); line-height: 1.6; margin-bottom: 26px; }
        .error-actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
    </style>
</head>
<body>
    <div class="error-card">
        <?php if (!empty($siteLogo)): ?>
            <img src="<?= htmlspecialchars($siteLogo) ?>" alt="" class="error-logo">
        <?php else: ?>
            <div class="brand-logo error-logo" style="display:flex;align-items:center;justify-content:center;font-size:20px;"><?= htmlspecialchars(mb_substr($siteShort, 0, 2)) ?></div>
        <?php endif; ?>

        <div class="error-code">404</div>
        <div class="error-title">Page Not Found</div>
        <p class="error-text">
            The page you're looking for doesn't exist, may have been moved, or the link is broken.
            Let's get you back on track.
        </p>

        <div class="error-actions">
            <a href="<?= htmlspecialchars(rtrim($publicUrl, '/')) ?>/index.php" class="btn btn-accent">&#127760; Go to Routine Portal</a>
            <button type="button" class="btn btn-outline" onclick="history.back()">&larr; Go Back</button>
        </div>
    </div>
</body>
</html>
