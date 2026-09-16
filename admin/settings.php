<?php
/**
 * Class Routine Management System (NasimSoft)
 * Site & Print Settings — customises the Public Portal and the printable
 * routine document without touching any code.
 * Copyright (c) 2026 NasimSoft.
 */

declare(strict_types=1);

// Load config + Auth *before* any output so a failed role check can still redirect.
require_once __DIR__ . '/../config/config.php';
Auth::requireAdmin();
require_once __DIR__ . '/../includes/bootstrap.php';

$db = Database::getInstance();

/**
 * All customisable keys with sane defaults. Anything not present in
 * website_settings simply falls back to these values everywhere they
 * are read (admin, public portal, printable routine, PDF export).
 */
$defaults = [
    // Branding (affects admin console + public portal + print header)
    'institute_name'       => 'Chapainawabganj Polytechnic Institute',
    'site_short_name'      => 'CNPI',
    'site_tagline'         => 'Online Class Routine Management System',
    'site_logo_url'        => '',
    'govt_logo_url'        => '',
    'theme_primary_color'  => '#123FA6',
    'theme_accent_color'   => '#1FA855',
    'copyright_text'       => '© {year} {institute_name}. Developed by NasimSoft.',
    'academic_year'        => '2026',
    'show_public_homepage' => '1',

    // Public home page
    'home_hero_style'          => 'gradient',
    'home_hero_title'          => 'Student & Public Routine Portal',
    'home_hero_subtitle'       => 'Access real-time schedules, faculty assignments, and laboratory room allocations.',
    'home_announcement'        => '',
    'home_show_login_button'   => '1',

    // Printable routine document (public print + admin PDF/export layout)
    'print_title'              => '',
    'print_subtitle'           => 'CLASS TIMETABLE',
    'print_font'               => 'sans',
    'print_show_logo'          => '0',
    'print_show_govt_logo'     => '0',
    'print_show_generated_on'  => '0',
    'print_footer_note'        => '',
    'print_show_signatures'    => '1',
];

$fields = [
    'text'     => ['institute_name', 'site_short_name', 'site_tagline', 'academic_year', 'copyright_text', 'home_hero_title', 'home_announcement', 'print_title', 'print_subtitle', 'print_footer_note'],
    'textarea' => ['home_hero_subtitle'],
    'color'    => ['theme_primary_color', 'theme_accent_color'],
    'checkbox' => ['show_public_homepage', 'home_show_login_button', 'print_show_logo', 'print_show_govt_logo', 'print_show_generated_on', 'print_show_signatures'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'settings') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Security token expired. Please try again.');
        header('Location: settings.php?tab=' . urlencode($_POST['active_tab'] ?? 'branding'));
        exit;
    }

    foreach ($defaults as $key => $default) {
        if (in_array($key, $fields['checkbox'], true)) {
            updateSetting($key, isset($_POST[$key]) ? '1' : '0');
            continue;
        }
        if ($key === 'site_logo_url' || $key === 'home_hero_style' || $key === 'print_font') {
            continue; // handled separately below
        }
        if (array_key_exists($key, $_POST)) {
            $value = trim((string)$_POST[$key]);
            if (in_array($key, $fields['color'], true) && !preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
                $value = $default;
            }
            updateSetting($key, $value);
        }
    }

    // Select-type fields with fixed option lists
    $heroStyle = in_array($_POST['home_hero_style'] ?? '', ['gradient', 'solid', 'minimal'], true) ? $_POST['home_hero_style'] : 'gradient';
    updateSetting('home_hero_style', $heroStyle);

    $printFont = in_array($_POST['print_font'] ?? '', ['serif', 'sans'], true) ? $_POST['print_font'] : 'sans';
    updateSetting('print_font', $printFont);

    // Logo uploads (Institute Logo + Government/Ministry Logo, handled independently)
    $logoFields = ['site_logo' => 'site_logo_url', 'govt_logo' => 'govt_logo_url'];
    foreach ($logoFields as $inputName => $settingKey) {
        if (!empty($_FILES[$inputName]['name']) && $_FILES[$inputName]['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES[$inputName]['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'svg'], true)) {
                $uploadDir = __DIR__ . '/../assets/uploads/branding/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $newFileName = $inputName . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES[$inputName]['tmp_name'], $uploadDir . $newFileName)) {
                    updateSetting($settingKey, 'assets/uploads/branding/' . $newFileName);
                }
            }
        } elseif (!empty($_POST['remove_' . $inputName])) {
            updateSetting($settingKey, '');
        }
    }

    logAudit('Update Settings', 'Settings', 0, 'Updated site/print/home page settings');
    setFlash('success', 'Settings saved successfully. Changes are live on the Public Portal immediately.');
    header('Location: settings.php?tab=' . urlencode($_POST['active_tab'] ?? 'branding'));
    exit;
}

// Signature block management (used on the printable routine footer)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'signature_add') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $title = trim($_POST['title'] ?? '');
        $person = trim($_POST['person_name'] ?? '');
        $designation = trim($_POST['designation'] ?? '');

        $signatureImagePath = null;
        if (!empty($_FILES['signature_image']['name']) && $_FILES['signature_image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['signature_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
                $uploadDir = __DIR__ . '/../assets/uploads/signatures/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $newFileName = 'sig_' . time() . '_' . mt_rand(100, 999) . '.' . $ext;
                if (move_uploaded_file($_FILES['signature_image']['tmp_name'], $uploadDir . $newFileName)) {
                    $signatureImagePath = 'assets/uploads/signatures/' . $newFileName;
                }
            } else {
                setFlash('error', 'Signature image must be a PNG, JPG, or WEBP file.');
            }
        }

        if ($title !== '') {
            $stmt = $db->prepare("INSERT INTO signatures (title, person_name, designation, signature_image, display_order, is_active) VALUES (?, ?, ?, ?, (SELECT n FROM (SELECT COALESCE(MAX(display_order),0)+1 AS n FROM signatures) t), 1)");
            $stmt->execute([$title, $person ?: null, $designation ?: null, $signatureImagePath]);
            setFlash('success', 'Signature block added.');
        } else {
            setFlash('error', 'Signature title (e.g. "Prepared By") is required.');
        }
    }
    header('Location: settings.php?tab=print');
    exit;
}

if (isset($_GET['delete_signature']) && verifyCsrfToken($_GET['csrf_token'] ?? '')) {
    $sigId = (int)$_GET['delete_signature'];
    $db->prepare("DELETE FROM signatures WHERE id = ?")->execute([$sigId]);
    setFlash('success', 'Signature block removed.');
    header('Location: settings.php?tab=print');
    exit;
}

$pageTitle = 'Site & Print Settings';
require_once __DIR__ . '/../includes/header.php';

// Read current values (fresh, bypassing the static cache from earlier requires)
$current = [];
foreach ($defaults as $key => $default) {
    $current[$key] = getSetting($key, $default);
}

$signatures = [];
try {
    $signatures = $db->query("SELECT * FROM signatures ORDER BY display_order ASC")->fetchAll();
} catch (Exception $e) {}

$activeTab = in_array($_GET['tab'] ?? '', ['branding', 'home', 'print'], true) ? $_GET['tab'] : 'branding';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
    <div>
        <h2 style="color: var(--navy); font-size: 20px; font-weight: 700;">Site &amp; Print Settings</h2>
        <p style="color: var(--text-muted); font-size: 13px;">Customise the Public Portal and the printable routine document — no code changes needed.</p>
    </div>
    <a href="../public/index.php" target="_blank" class="btn btn-outline">&#128065; Preview Public Portal</a>
</div>

<form method="POST" action="settings.php" enctype="multipart/form-data" id="settingsForm">
    <?= csrfField() ?>
    <input type="hidden" name="form" value="settings">
    <input type="hidden" name="active_tab" id="activeTabInput" value="<?= e($activeTab) ?>">

    <div class="card">
        <div class="tabs">
            <button type="button" class="tab-btn <?= $activeTab === 'branding' ? 'active' : '' ?>" data-tab="branding">&#127968; Branding</button>
            <button type="button" class="tab-btn <?= $activeTab === 'home' ? 'active' : '' ?>" data-tab="home">&#127760; Public Home Page</button>
            <button type="button" class="tab-btn <?= $activeTab === 'print' ? 'active' : '' ?>" data-tab="print">&#128424; Print / Routine Document</button>
        </div>

        <div class="card-body">
            <!-- ===================== BRANDING TAB ===================== -->
            <div class="tab-panel <?= $activeTab === 'branding' ? 'active' : '' ?>" data-panel="branding">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Institute / Site Name *</label>
                        <input type="text" name="institute_name" class="form-control" required value="<?= e($current['institute_name']) ?>">
                        <span class="form-hint">Shown in the browser tab, portal header, and print header.</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Short Name / Initials</label>
                        <input type="text" name="site_short_name" class="form-control" maxlength="10" value="<?= e($current['site_short_name']) ?>">
                        <span class="form-hint">Used for the sidebar logo badge, e.g. "CNPI".</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Tagline</label>
                    <input type="text" name="site_tagline" class="form-control" value="<?= e($current['site_tagline']) ?>">
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Primary Color (Navy)</label>
                        <div class="color-swatch-row">
                            <input type="color" class="form-control color-pick" data-pair="theme_primary_color_text" value="<?= e($current['theme_primary_color']) ?>">
                            <input type="text" name="theme_primary_color" id="theme_primary_color_text" class="form-control" value="<?= e($current['theme_primary_color']) ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Accent Color (Orange)</label>
                        <div class="color-swatch-row">
                            <input type="color" class="form-control color-pick" data-pair="theme_accent_color_text" value="<?= e($current['theme_accent_color']) ?>">
                            <input type="text" name="theme_accent_color" id="theme_accent_color_text" class="form-control" value="<?= e($current['theme_accent_color']) ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Institute Logo</label>
                    <input type="file" name="site_logo" class="form-control" accept="image/*">
                    <?php if (!empty($current['site_logo_url'])): ?>
                        <div style="display:flex; align-items:center; gap:12px; margin-top:10px;">
                            <img src="../<?= e($current['site_logo_url']) ?>" alt="Institute Logo" style="width:48px;height:48px;object-fit:cover;border-radius:8px;border:1px solid var(--border-color);">
                            <label class="form-check"><input type="checkbox" name="remove_site_logo" value="1"> Remove current logo</label>
                        </div>
                    <?php else: ?>
                        <span class="form-hint">No logo uploaded yet — an initials badge is shown instead.</span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label">Government / Ministry Logo (Optional)</label>
                    <input type="file" name="govt_logo" class="form-control" accept="image/*">
                    <?php if (!empty($current['govt_logo_url'])): ?>
                        <div style="display:flex; align-items:center; gap:12px; margin-top:10px;">
                            <img src="../<?= e($current['govt_logo_url']) ?>" alt="Government Logo" style="width:48px;height:48px;object-fit:cover;border-radius:8px;border:1px solid var(--border-color);">
                            <label class="form-check"><input type="checkbox" name="remove_govt_logo" value="1"> Remove current logo</label>
                        </div>
                    <?php else: ?>
                        <span class="form-hint">e.g. the national emblem — shown alongside the institute logo on the printed routine.</span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label">Footer Text</label>
                    <input type="text" name="copyright_text" class="form-control" value="<?= e($current['copyright_text']) ?>">
                    <span class="form-hint">Placeholders: <code>{year}</code> and <code>{institute_name}</code>.</span>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Academic Year</label>
                        <input type="text" name="academic_year" class="form-control" value="<?= e($current['academic_year']) ?>" placeholder="e.g. 2026">
                        <span class="form-hint">Pre-filled as the "Academic Year" filter on the Public Portal and Routine Console.</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Public Portal Status</label>
                        <label class="form-check" style="margin-top:10px;">
                            <input type="checkbox" name="show_public_homepage" value="1" <?= $current['show_public_homepage'] === '1' ? 'checked' : '' ?>>
                            Public Portal is enabled (uncheck to temporarily take it offline)
                        </label>
                    </div>
                </div>
            </div>

            <!-- ===================== HOME PAGE TAB ===================== -->
            <div class="tab-panel <?= $activeTab === 'home' ? 'active' : '' ?>" data-panel="home">
                <div class="form-group">
                    <label class="form-label">Hero Banner Style</label>
                    <select name="home_hero_style" class="form-select">
                        <option value="gradient" <?= $current['home_hero_style'] === 'gradient' ? 'selected' : '' ?>>Gradient (Navy → Dark)</option>
                        <option value="solid" <?= $current['home_hero_style'] === 'solid' ? 'selected' : '' ?>>Solid Navy</option>
                        <option value="minimal" <?= $current['home_hero_style'] === 'minimal' ? 'selected' : '' ?>>Minimal (White Card)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Hero Title</label>
                    <input type="text" name="home_hero_title" class="form-control" value="<?= e($current['home_hero_title']) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Hero Subtitle</label>
                    <textarea name="home_hero_subtitle" class="form-control" rows="2"><?= e($current['home_hero_subtitle']) ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Announcement Banner</label>
                    <input type="text" name="home_announcement" class="form-control" placeholder="e.g. Mid-term routine published — check your section." value="<?= e($current['home_announcement']) ?>">
                    <span class="form-hint">Leave blank to hide. Shown at the top of the portal, above the hero banner.</span>
                </div>

                <div class="form-group">
                    <label class="form-check">
                        <input type="checkbox" name="home_show_login_button" value="1" <?= $current['home_show_login_button'] === '1' ? 'checked' : '' ?>>
                        Show "Faculty &amp; Admin Login" button in the public navbar
                    </label>
                </div>
            </div>

            <!-- ===================== PRINT TAB ===================== -->
            <div class="tab-panel <?= $activeTab === 'print' ? 'active' : '' ?>" data-panel="print">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Print Header Title</label>
                        <input type="text" name="print_title" class="form-control" placeholder="Defaults to institute name" value="<?= e($current['print_title']) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Print Header Subtitle</label>
                        <input type="text" name="print_subtitle" class="form-control" value="<?= e($current['print_subtitle']) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Print Font</label>
                    <select name="print_font" class="form-select">
                        <option value="serif" <?= $current['print_font'] === 'serif' ? 'selected' : '' ?>>Classic Serif (Times New Roman) — formal document look</option>
                        <option value="sans" <?= $current['print_font'] === 'sans' ? 'selected' : '' ?>>Modern Sans-serif (Inter)</option>
                    </select>
                </div>

                <div class="grid-3">
                    <div class="form-group">
                        <label class="form-check">
                            <input type="checkbox" name="print_show_logo" value="1" <?= $current['print_show_logo'] === '1' ? 'checked' : '' ?>>
                            Show institute logo on printed routine
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="form-check">
                            <input type="checkbox" name="print_show_govt_logo" value="1" <?= $current['print_show_govt_logo'] === '1' ? 'checked' : '' ?>>
                            Show government/ministry logo on printed routine
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="form-check">
                            <input type="checkbox" name="print_show_generated_on" value="1" <?= $current['print_show_generated_on'] === '1' ? 'checked' : '' ?>>
                            Show "Generated on" timestamp
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Print Footer Note</label>
                    <input type="text" name="print_footer_note" class="form-control" placeholder="e.g. Subject to change without prior notice." value="<?= e($current['print_footer_note']) ?>">
                </div>

                <div class="form-group">
                    <label class="form-check">
                        <input type="checkbox" name="print_show_signatures" value="1" <?= $current['print_show_signatures'] === '1' ? 'checked' : '' ?>>
                        Show signature blocks below the printed routine
                    </label>
                </div>
            </div>
        </div>

        <div class="card-footer" style="display:flex; justify-content:flex-end;">
            <button type="submit" class="btn btn-accent">&#128190; Save Settings</button>
        </div>
    </div>
</form>

<!-- Signature blocks manager (only meaningful when the Print tab is active) -->
<div class="card" id="signaturesCard" style="<?= $activeTab === 'print' ? '' : 'display:none;' ?>">
    <div class="card-header">
        <h3>&#9997; Signature Blocks (Print Footer)</h3>
    </div>
    <div class="card-body" style="padding-top: 0;">
        <p style="font-size: 12.5px; color: var(--text-muted); margin-bottom: 14px;">
            These appear as signature lines at the bottom of the printed routine (e.g. "Prepared By", "Approved By: Principal").
        </p>

        <div class="table-responsive" style="margin-bottom: 18px;">
            <table class="table">
                <thead>
                    <tr><th>Signature</th><th>Title</th><th>Name</th><th>Designation</th><th style="text-align:right;">Action</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($signatures)): ?>
                        <tr><td colspan="5" style="text-align:center; color: var(--text-muted); padding: 18px;">No signature blocks added yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($signatures as $sig): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($sig['signature_image'])): ?>
                                        <img src="../<?= e($sig['signature_image']) ?>" alt="Signature" style="height:34px; max-width:100px; object-fit:contain;">
                                    <?php else: ?>
                                        <span style="color: var(--text-faint); font-size: 12px;">No image</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= e($sig['title']) ?></strong></td>
                                <td><?= e($sig['person_name'] ?? '') ?></td>
                                <td><?= e($sig['designation'] ?? '') ?></td>
                                <td style="text-align:right;">
                                    <button type="button" class="btn btn-danger-outline btn-sm" onclick="App.confirm('Remove this signature block?', () => { window.location.href='settings.php?tab=print&delete_signature=<?= (int)$sig['id'] ?>&csrf_token=<?= generateCsrfToken() ?>'; })">&#128465; Remove</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <form method="POST" action="settings.php?tab=print" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="form" value="signature_add">
            <div class="grid-3">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" class="form-control" placeholder="Prepared By" required>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Name</label>
                    <input type="text" name="person_name" class="form-control" placeholder="Optional">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Designation</label>
                    <input type="text" name="designation" class="form-control" placeholder="Optional">
                </div>
            </div>
            <div class="grid-2" style="align-items:end; margin-top:14px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Signature Image (Optional)</label>
                    <input type="file" name="signature_image" class="form-control" accept="image/png,image/jpeg,image/webp">
                    <span class="form-hint">A scanned/transparent-background signature works best.</span>
                </div>
                <button type="submit" class="btn btn-primary" style="height:42px;">&#10133; Add Signature Block</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const tabButtons = document.querySelectorAll('.tab-btn');
    const panels = document.querySelectorAll('.tab-panel');
    const activeInput = document.getElementById('activeTabInput');
    const sigCard = document.getElementById('signaturesCard');

    function activate(tab) {
        tabButtons.forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
        panels.forEach(p => p.classList.toggle('active', p.dataset.panel === tab));
        if (activeInput) activeInput.value = tab;
        if (sigCard) sigCard.style.display = (tab === 'print') ? '' : 'none';
    }

    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => activate(btn.dataset.tab));
    });

    // Sync color picker <-> hex text input
    document.querySelectorAll('.color-pick').forEach(picker => {
        picker.addEventListener('input', () => {
            const pairEl = document.getElementById(picker.dataset.pair);
            if (pairEl) pairEl.value = picker.value;
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
