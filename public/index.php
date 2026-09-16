<?php
/**
 * Class Routine Management System (NasimSoft)
 * Public Routine Portal (No Login Required)
 * Copyright (c) 2026 NasimSoft. All rights reserved.
 *
 * Branding, hero text, colors and the printable routine header/footer are
 * all customisable from Admin → Site & Print Settings (admin/settings.php),
 * backed by the `website_settings` key-value table.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$db = Database::getInstance();

// ---------------------------------------------------------------------
// Settings (safe defaults if a key has never been saved)
// ---------------------------------------------------------------------
$siteName        = getSetting('institute_name', 'Chapainawabganj Polytechnic Institute');
$siteShortName   = getSetting('site_short_name', 'CNPI');
$siteTagline     = getSetting('site_tagline', 'Online Class Routine Management System');
$siteLogo        = getSetting('site_logo_url', '');
$govtLogo        = getSetting('govt_logo_url', '');
$primaryColor    = getSetting('theme_primary_color', '#123FA6');
$accentColor     = getSetting('theme_accent_color', '#1FA855');
$copyrightText   = getSetting('copyright_text', '© {year} {institute_name}. Developed by NasimSoft.');
$defaultYear     = getSetting('academic_year', '2026');
$portalEnabled   = getSetting('show_public_homepage', '1') === '1';

$heroStyle       = getSetting('home_hero_style', 'gradient');
$heroTitle       = getSetting('home_hero_title', 'Student & Public Routine Portal');
$heroSubtitle    = getSetting('home_hero_subtitle', 'Access real-time schedules, faculty assignments, and laboratory room allocations.');
$announcement    = getSetting('home_announcement', '');
$showLoginBtn    = getSetting('home_show_login_button', '1') === '1';

$printTitle          = getSetting('print_title', '') ?: $siteName;
$printSubtitle       = getSetting('print_subtitle', 'CLASS TIMETABLE');
$printFont           = getSetting('print_font', 'sans');
$printShowLogo       = getSetting('print_show_logo', '0') === '1';
$printShowGovtLogo   = getSetting('print_show_govt_logo', '0') === '1';
$printShowGeneratedOn = getSetting('print_show_generated_on', '0') === '1';
$printFooterNote     = getSetting('print_footer_note', '');
$printShowSignatures = getSetting('print_show_signatures', '1') === '1';

$copyrightHtml = str_replace(
    ['{year}', '{institute_name}'],
    [date('Y'), htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8')],
    htmlspecialchars($copyrightText, ENT_QUOTES, 'UTF-8')
);

// ---------------------------------------------------------------------
// Portal disabled by admin? Show a friendly notice instead of data.
// ---------------------------------------------------------------------
if (!$portalEnabled) {
    http_response_code(503);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($siteName) ?> - Routine Portal</title>
        <link rel="stylesheet" href="<?= defined('ASSETS_URL') ? ASSETS_URL : '../assets' ?>/css/style.css">
        <style>:root{--navy:<?= htmlspecialchars($primaryColor) ?>;--orange:<?= htmlspecialchars($accentColor) ?>;}
        body{display:flex;align-items:center;justify-content:center;min-height:100vh;text-align:center;padding:20px;}
        .offline-card{max-width:420px;}</style>
    </head>
    <body>
        <div class="offline-card">
            <h1 style="font-size:22px;color:var(--navy);margin-bottom:10px;">Portal Temporarily Unavailable</h1>
            <p style="color:var(--text-muted);font-size:14px;">The public routine portal is currently offline for maintenance. Please check back soon.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Load working days & periods
$daysStmt = $db->query("SELECT day_name FROM working_days WHERE is_active = 1 ORDER BY day_order ASC");
$workingDays = $daysStmt->fetchAll(PDO::FETCH_COLUMN) ?: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'];

// Load filter options
$technologies = $db->query("SELECT id, name, short_name FROM technologies WHERE active = 1 ORDER BY name ASC")->fetchAll();
$semesters = $db->query("SELECT id, name, numeric_level FROM semesters WHERE active = 1 ORDER BY numeric_level ASC")->fetchAll();
$shifts = $db->query("SELECT id, name FROM shifts WHERE active = 1 ORDER BY name ASC")->fetchAll();

// Quick stats strip (purely decorative/informational — makes the portal feel alive)
$quickStats = [
    'groups' => (int)$db->query("SELECT COUNT(*) FROM `groups` WHERE active = 1")->fetchColumn(),
    'teachers' => (int)$db->query("SELECT COUNT(*) FROM teachers WHERE active = 1")->fetchColumn(),
    'technologies' => (int)$db->query("SELECT COUNT(*) FROM technologies WHERE active = 1")->fetchColumn(),
    'published' => (int)$db->query("SELECT COUNT(*) FROM routines WHERE status = 'PUBLISHED'")->fetchColumn(),
];

// Current day detection (Local Bangladesh Time)
$currentDayName = date('l'); // e.g., 'Sunday'
$isWeekend = in_array($currentDayName, ['Friday', 'Saturday'], true);

// Pre-selected parameters
$selectedTechId = (int)($_GET['technology_id'] ?? ($technologies[0]['id'] ?? 0));
$selectedSemId = (int)($_GET['semester_id'] ?? ($semesters[0]['id'] ?? 0));
$selectedShiftId = (int)($_GET['shift_id'] ?? ($shifts[0]['id'] ?? 0));
$selectedGroupId = (int)($_GET['group_id'] ?? 0);
$academicYear = trim($_GET['academic_year'] ?? $defaultYear);

// Periods follow the selected shift — each shift has its own 7-period timetable
$periodsStmt = $db->prepare("SELECT * FROM periods WHERE active = 1 AND shift_id = ? ORDER BY period_number ASC");
$periodsStmt->execute([$selectedShiftId ?: ($shifts[0]['id'] ?? 1)]);
$periods = $periodsStmt->fetchAll();

// Fetch groups matching selected filters
$groupsQuery = "SELECT id, short_code, group_name FROM `groups` WHERE active = 1";
$groupParams = [];
if ($selectedTechId > 0) {
    $groupsQuery .= " AND technology_id = ?";
    $groupParams[] = $selectedTechId;
}
if ($selectedSemId > 0) {
    $groupsQuery .= " AND semester_id = ?";
    $groupParams[] = $selectedSemId;
}
if ($selectedShiftId > 0) {
    $groupsQuery .= " AND shift_id = ?";
    $groupParams[] = $selectedShiftId;
}
$groupsQuery .= " ORDER BY short_code ASC";
$grpStmt = $db->prepare($groupsQuery);
$grpStmt->execute($groupParams);
$matchingGroups = $grpStmt->fetchAll();

if ($selectedGroupId === 0 && !empty($matchingGroups)) {
    $selectedGroupId = (int)$matchingGroups[0]['id'];
}

// Fetch Group Meta
$currentGroup = null;
if ($selectedGroupId > 0) {
    $cgStmt = $db->prepare("
        SELECT g.*, t.name as tech_name, t.short_name as tech_code,
               s.name as sem_name, sh.name as shift_name,
               d.name as dept_name, i.name as institute_name
        FROM `groups` g
        JOIN technologies t ON g.technology_id = t.id
        JOIN departments d ON t.department_id = d.id
        JOIN institutes i ON d.institute_id = i.id
        JOIN semesters s ON g.semester_id = s.id
        JOIN shifts sh ON g.shift_id = sh.id
        WHERE g.id = ?
    ");
    $cgStmt->execute([$selectedGroupId]);
    $currentGroup = $cgStmt->fetch();
}

// Fetch Published Routine Slots for this group
$routineSlots = [];
$matrix = [];
if ($selectedGroupId > 0) {
    $rStmt = $db->prepare("
        SELECT r.*, s.name as subject_name, s.subject_code, s.subject_type,
               t.name as teacher_name, t.short_code as teacher_code, t.designation, t.phone,
               rm.room_code, rm.name as room_name, rm.room_type
        FROM routines r
        JOIN subjects s ON r.subject_id = s.id
        JOIN teachers t ON r.teacher_id = t.id
        JOIN rooms rm ON r.room_id = rm.id
        WHERE r.group_id = ? AND r.academic_year = ? AND r.status = 'PUBLISHED'
        ORDER BY FIELD(r.day, 'Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), r.period_start ASC
    ");
    $rStmt->execute([$selectedGroupId, $academicYear]);
    $routineSlots = $rStmt->fetchAll();

    foreach ($routineSlots as $slot) {
        $slot['line2'] = $slot['teacher_code']; // group/student print view shows the teacher's short code
        $matrix[$slot['day']][(int)$slot['period_start']] = $slot;
    }
}

// Signature blocks for the printable footer
$signatures = [];
if ($printShowSignatures) {
    try {
        $signatures = $db->query("SELECT * FROM signatures WHERE is_active = 1 ORDER BY display_order ASC")->fetchAll();
    } catch (Exception $e) {}
}

// Class teacher line (if set on the group) — "Class Teacher: Name, Designation, Department"
$classTeacherLine = '';
if ($currentGroup && !empty($currentGroup['class_teacher_id'])) {
    $ctStmt = $db->prepare("
        SELECT t.name, t.designation, d.name AS dept_name
        FROM teachers t JOIN departments d ON t.department_id = d.id
        WHERE t.id = ?
    ");
    $ctStmt->execute([$currentGroup['class_teacher_id']]);
    if ($ct = $ctStmt->fetch()) {
        $classTeacherLine = "Class Teacher: {$ct['name']}, {$ct['designation']}, {$ct['dept_name']}";
    }
}

// Subject & Teacher list for the printed document (distinct subject+teacher pairs)
$printSubjectRows = [];
if (!empty($routineSlots)) {
    $seen = [];
    foreach ($routineSlots as $slot) {
        $key = $slot['subject_id'] . '_' . $slot['teacher_id'];
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $printSubjectRows[] = [
            'subject_name' => $slot['subject_name'],
            'subject_code' => $slot['subject_code'],
            'teacher_name' => $slot['teacher_name'],
            'teacher_code' => $slot['teacher_code'],
            'designation'  => $slot['designation'] ?? '',
            'phone'        => $slot['phone'] ?? '',
        ];
    }
    usort($printSubjectRows, fn($a, $b) => strcmp($a['subject_code'], $b['subject_code']));
}
$printTotalLoad = array_sum(array_column($routineSlots, 'load_count'));

require_once __DIR__ . '/../includes/routine-print-template.php';

$assetsUrl = defined('ASSETS_URL') ? ASSETS_URL : '../assets';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $currentGroup ? htmlspecialchars($currentGroup['short_code']) . ' Routine' : 'Class Routine Portal' ?> - <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($assetsUrl) ?>/css/style.css">
    <style>
        :root {
            --navy: <?= htmlspecialchars($primaryColor) ?>;
            --navy-dark: <?= htmlspecialchars($primaryColor) ?>;
            --orange: <?= htmlspecialchars($accentColor) ?>;
            --font-print-active: <?= $printFont === 'serif' ? "'Times New Roman', Georgia, serif" : "Arial, Helvetica, sans-serif" ?>;
        }
    </style>
</head>
<body class="public-body">

    <!-- Top Navigation -->
    <header class="public-nav no-print">
        <div class="nav-container">
            <a href="index.php" class="brand-badge">
                <?php if (!empty($siteLogo)): ?>
                    <img src="../<?= htmlspecialchars($siteLogo) ?>" alt="<?= htmlspecialchars($siteShortName) ?>" class="brand-logo-img">
                <?php else: ?>
                    <div class="brand-icon">&#128197;</div>
                <?php endif; ?>
                <div>
                    <div class="brand-title"><?= htmlspecialchars($siteName) ?></div>
                    <div class="brand-tagline"><?= htmlspecialchars($siteTagline) ?> &bull; NasimSoft</div>
                </div>
            </a>
            <div class="public-menu">
                <button type="button" class="public-menu-toggle" id="publicMenuToggle" aria-label="Menu">
                    <span></span><span></span><span></span>
                </button>
                <div class="public-menu-dropdown" id="publicMenuDropdown">
                    <?php if ($showLoginBtn): ?>
                        <a href="<?= ADMIN_URL ?>/login.php" class="public-menu-item">&#128272; Faculty &amp; Admin Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <main class="main-container">

        <?php if (!empty($announcement)): ?>
            <div class="announcement-bar no-print">&#128226; <?= htmlspecialchars($announcement) ?></div>
        <?php endif; ?>

        <!-- Hero Header -->
        <div class="hero-card style-<?= htmlspecialchars($heroStyle) ?> no-print">
            <div class="hero-decor hero-decor-1"></div>
            <div class="hero-decor hero-decor-2"></div>
            <div class="hero-content">
                <h1><?= htmlspecialchars($heroTitle) ?></h1>
                <p><?= htmlspecialchars($heroSubtitle) ?></p>
                <div class="hero-stats">
                    <div class="hero-stat"><strong><?= $quickStats['groups'] ?></strong><span>Groups</span></div>
                    <div class="hero-stat"><strong><?= $quickStats['teachers'] ?></strong><span>Faculty</span></div>
                    <div class="hero-stat"><strong><?= $quickStats['technologies'] ?></strong><span>Technologies</span></div>
                    <div class="hero-stat"><strong><?= $quickStats['published'] ?></strong><span>Live Classes</span></div>
                </div>
            </div>
            <div style="display: flex; gap: 10px; position: relative; z-index: 1;">
                <button type="button" class="btn btn-accent" onclick="window.print()">&#128424; Print Routine</button>
            </div>
        </div>

        <!-- Academic Filter Panel -->
        <div class="filter-card no-print">
            <form method="GET" action="index.php" id="routineFilterForm">
                <div class="filter-grid">
                    <div class="form-group">
                        <label class="form-label">Technology</label>
                        <select name="technology_id" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($technologies as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= $selectedTechId === (int)$t['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t['name']) ?> (<?= htmlspecialchars($t['short_name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Semester</label>
                        <select name="semester_id" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($semesters as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $selectedSemId === (int)$s['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Shift</label>
                        <select name="shift_id" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($shifts as $sh): ?>
                                <option value="<?= $sh['id'] ?>" <?= $selectedShiftId === (int)$sh['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sh['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Group / Section</label>
                        <select name="group_id" class="form-select" onchange="this.form.submit()">
                            <?php if (empty($matchingGroups)): ?>
                                <option value="0">No Groups Found</option>
                            <?php else: ?>
                                <?php foreach ($matchingGroups as $mg): ?>
                                    <option value="<?= $mg['id'] ?>" <?= $selectedGroupId === (int)$mg['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($mg['short_code']) ?> &bull; <?= htmlspecialchars($mg['group_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Academic Year</label>
                        <input type="text" name="academic_year" class="form-control" value="<?= htmlspecialchars($academicYear) ?>" onchange="this.form.submit()">
                    </div>
                </div>
            </form>
        </div>

        <!-- View Controls & Metadata Header -->
        <div class="view-switch-bar no-print">
            <div>
                <?php if ($currentGroup): ?>
                    <h2 style="font-size: 19px; font-weight: 800; color: var(--navy);">
                        <?= htmlspecialchars($currentGroup['short_code']) ?> Routine
                    </h2>
                    <p style="font-size: 13px; color: var(--text-muted);">
                        <?= htmlspecialchars($currentGroup['tech_name']) ?> &bull; <?= htmlspecialchars($currentGroup['sem_name']) ?> &bull; <?= htmlspecialchars($currentGroup['shift_name']) ?>
                    </p>
                <?php else: ?>
                    <h2 style="font-size: 18px; color: var(--text-muted);">Please select a class group above to view routine.</h2>
                <?php endif; ?>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <span class="badge badge-info">Total Classes: <?= count($routineSlots) ?></span>
                <?php if ($isWeekend): ?>
                    <span class="badge badge-danger">Weekend Today</span>
                <?php else: ?>
                    <span class="badge badge-warning">Today: <?= $currentDayName ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Print-Only Official Routine Document (matches the institute's official printout format exactly) -->
        <div class="print-only">
            <?= renderRoutinePrintDocument([
                'mode'          => 'group',
                'institute_name'=> $printTitle,
                'logo_url'      => ($printShowLogo && !empty($siteLogo)) ? '../' . $siteLogo : '',
                'govt_logo_url' => ($printShowGovtLogo && !empty($govtLogo)) ? '../' . $govtLogo : '',
                'generated_on'  => $printShowGeneratedOn ? date('d F Y, h:i A') : '',
                'title_line1'   => $currentGroup ? ($currentGroup['sem_name'] . ' Class Routine ' . $academicYear) : $printSubtitle,
                'title_line2'   => $currentGroup ? ($currentGroup['tech_name'] . ' (' . $currentGroup['shift_name'] . ')') : '',
                'meta_left'     => 'Group/Short Code: ' . ($currentGroup['short_code'] ?? ''),
                'meta_right'    => 'Total Load: ' . $printTotalLoad,
                'periods'       => $periods,
                'days'          => $workingDays,
                'matrix'        => $matrix,
                'subject_rows'  => $printSubjectRows,
                'class_teacher_line' => $classTeacherLine,
                'note_line'     => $printFooterNote,
                'signatures'    => $signatures,
            ]) ?>
        </div>

        <!-- DESKTOP TIMETABLE GRID -->
        <div class="routine-table-container">
            <table class="routine-table">
                <thead>
                    <tr>
                        <th style="width: 100px;">DAY</th>
                        <?php foreach ($periods as $p): ?>
                            <th>
                                <?= htmlspecialchars($p['period_name']) ?>
                                <small><?= date('h:i A', strtotime($p['start_time'])) ?> - <?= date('h:i A', strtotime($p['end_time'])) ?></small>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($workingDays as $day):
                        $isTodayRow = (strcasecmp($day, $currentDayName) === 0);
                    ?>
                        <tr>
                            <td class="day-header <?= $isTodayRow ? 'day-today' : '' ?>">
                                <?= strtoupper($day) ?>
                                <?php if ($isTodayRow): ?>
                                    <span style="display: block; font-size: 9.5px; font-weight: 700; color: var(--orange);">&#9679; TODAY</span>
                                <?php endif; ?>
                            </td>
                            <?php
                            $skipPeriods = 0;
                            foreach ($periods as $p):
                                $pNum = (int)$p['period_number'];

                                if ($skipPeriods > 0) {
                                    $skipPeriods--;
                                    continue;
                                }

                                if (isset($matrix[$day][$pNum])) {
                                    $slot = $matrix[$day][$pNum];
                                    $span = max(1, ((int)$slot['period_end'] - (int)$slot['period_start']) + 1);
                                    if ($span > 1) {
                                        $skipPeriods = $span - 1;
                                    }
                                    $pillClass = $slot['is_lab'] ? 'pill-lab' : 'pill-theory';
                                    ?>
                                    <td colspan="<?= $span ?>">
                                        <div class="slot-pill <?= $pillClass ?>">
                                            <div>
                                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                                    <span class="subject-code"><?= htmlspecialchars($slot['subject_code']) ?></span>
                                                    <span class="badge <?= $slot['is_lab'] ? 'badge-lab' : 'badge-theory' ?>">
                                                        <?= $slot['is_lab'] ? 'LAB' : 'THEORY' ?>
                                                    </span>
                                                </div>
                                                <div style="font-weight: 600; color: var(--text-dark); line-height: 1.25; margin-bottom: 6px;">
                                                    <?= htmlspecialchars($slot['subject_name']) ?>
                                                </div>
                                            </div>
                                            <div style="display: flex; justify-content: space-between; font-size: 11px; margin-top: auto;">
                                                <span style="font-weight: 700; color: var(--navy);">&#128100; <?= htmlspecialchars($slot['teacher_code']) ?></span>
                                                <span style="font-weight: 700; color: var(--orange);">&#127963; <?= htmlspecialchars($slot['room_code']) ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <?php
                                } else {
                                    echo '<td style="text-align: center; color: #CBD5E1; vertical-align: middle;">-</td>';
                                }
                            endforeach;
                            ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- SMARTPHONE RESPONSIVE VIEW (CARD DECK) -->
        <div class="mobile-cards-view no-print">
            <?php foreach ($workingDays as $day):
                $isToday = (strcasecmp($day, $currentDayName) === 0);
                $daySlots = array_filter($routineSlots, fn($s) => strcasecmp($s['day'], $day) === 0);
            ?>
                <div class="day-deck-card">
                    <div class="day-deck-header <?= $isToday ? 'is-today' : '' ?>">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span><?= strtoupper($day) ?></span>
                            <?php if ($isToday): ?>
                                <span class="badge" style="background: var(--orange); color: #FFF;">TODAY</span>
                            <?php endif; ?>
                        </div>
                        <span style="font-size: 12px; font-weight: 600;"><?= count($daySlots) ?> classes</span>
                    </div>

                    <div>
                        <?php if (empty($daySlots)): ?>
                            <div style="padding: 16px; text-align: center; color: var(--text-muted); font-size: 13px;">
                                No classes scheduled for <?= $day ?>.
                            </div>
                        <?php else: ?>
                            <?php foreach ($daySlots as $ds): ?>
                                <div class="deck-slot-item">
                                    <div style="flex: 1;">
                                        <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 4px;">
                                            <strong style="color: var(--navy); font-size: 13.5px;"><?= htmlspecialchars($ds['subject_code']) ?></strong>
                                            <span class="badge <?= $ds['is_lab'] ? 'badge-lab' : 'badge-theory' ?>">
                                                <?= $ds['is_lab'] ? 'Lab' : 'Theory' ?>
                                            </span>
                                        </div>
                                        <div style="font-weight: 600; font-size: 13px; color: var(--text-dark);">
                                            <?= htmlspecialchars($ds['subject_name']) ?>
                                        </div>
                                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                                            Faculty: <strong><?= htmlspecialchars($ds['teacher_name']) ?> (<?= htmlspecialchars($ds['teacher_code']) ?>)</strong>
                                        </div>
                                    </div>
                                    <div style="text-align: right; white-space: nowrap;">
                                        <div style="background: #F1F5F9; border-radius: 6px; padding: 4px 8px; font-weight: 700; color: var(--navy); font-size: 12px;">
                                            P<?= $ds['period_start'] ?><?= $ds['period_end'] > $ds['period_start'] ? ' - P'.$ds['period_end'] : '' ?>
                                        </div>
                                        <div style="margin-top: 4px; font-size: 11.5px; font-weight: 700; color: var(--orange);">
                                            Room <?= htmlspecialchars($ds['room_code']) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </main>

    <!-- Public Footer -->
    <footer class="public-footer no-print">
        <p><?= $copyrightHtml ?></p>
    </footer>

</body>
</html>
