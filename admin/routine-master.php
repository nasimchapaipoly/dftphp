<?php
/**
 * Master Timetable Explorer
 * Class Routine Management System (NasimSoft)
 * Chapainawabganj Polytechnic Institute (CNPI)
 * Copyright (c) 2026 NasimSoft. All rights reserved.
 */

// 1. Enable Full Error Reporting so 500 errors never hide
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

if (!defined('CRMS_SYSTEM')) {
    define('CRMS_SYSTEM', true);
}

session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$configFile = file_exists(__DIR__ . '/../config/config.php')
    ? __DIR__ . '/../config/config.php'
    : __DIR__ . '/config/config.php';

if (!file_exists($configFile)) {
    die("Error: config/config.php file not found.");
}

require_once $configFile;

// Branding (customisable from Admin → Site & Print Settings)
$brandName = getSetting('site_short_name', 'CNPI');
$brandPrimary = getSetting('theme_primary_color', '#123FA6');
$brandAccent = getSetting('theme_accent_color', '#1FA855');
$assetsUrl = defined('ASSETS_URL') ? ASSETS_URL : '../assets';

try {
    $db = getDB();

    // Active working days
    $daysStmt = $db->query("SELECT day_name FROM working_days WHERE is_active = 1 ORDER BY day_order ASC");
    $workingDays = $daysStmt->fetchAll(PDO::FETCH_COLUMN) ?: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'];

    // Filter parameters
    $viewType = $_GET['view_type'] ?? 'group'; // 'group', 'teacher', 'room'
    $selectedGroupId = (int)($_GET['group_id'] ?? 0);
    $selectedTeacherId = (int)($_GET['teacher_id'] ?? 0);
    $selectedRoomId = (int)($_GET['room_id'] ?? 0);
    $selectedShiftId = (int)($_GET['shift_id'] ?? 0);
    $academicYear = trim($_GET['academic_year'] ?? getSetting('academic_year', '2026'));

    $shifts = $db->query("SELECT id, name FROM shifts WHERE active = 1 ORDER BY id ASC")->fetchAll();
    if ($selectedShiftId <= 0) {
        $selectedShiftId = (int)($shifts[0]['id'] ?? 1);
    }

    // Fetch dropdown entities
    $groups = $db->query("
        SELECT g.id, g.short_code, g.group_name, t.name as tech_name, s.name as sem_name, sh.name as shift_name
        FROM `groups` g
        JOIN technologies t ON g.technology_id = t.id
        JOIN semesters s ON g.semester_id = s.id
        JOIN shifts sh ON g.shift_id = sh.id
        WHERE g.active = 1
        ORDER BY g.short_code ASC
    ")->fetchAll();

    $teachers = $db->query("SELECT id, name, short_code, designation FROM teachers WHERE active = 1 ORDER BY name ASC")->fetchAll();
    
    // Fixed query: uses 'name' instead of 'room_number'
    $rooms = $db->query("SELECT id, name, room_code, room_type, capacity FROM rooms WHERE active = 1 ORDER BY room_code ASC")->fetchAll();

    // Defaults
    if ($viewType === 'group' && $selectedGroupId === 0 && !empty($groups)) {
        $selectedGroupId = (int)$groups[0]['id'];
    }
    if ($viewType === 'teacher' && $selectedTeacherId === 0 && !empty($teachers)) {
        $selectedTeacherId = (int)$teachers[0]['id'];
    }
    if ($viewType === 'room' && $selectedRoomId === 0 && !empty($rooms)) {
        $selectedRoomId = (int)$rooms[0]['id'];
    }

    // Prepare Matrix Query
    $slotsMatrix = [];
    $totalLoad = 0;
    $titleHeader = '';
    $subHeader = '';

    if ($viewType === 'group' && $selectedGroupId > 0) {
        $stmt = $db->prepare("
            SELECT g.*, t.name as tech_name, s.name as sem_name, sh.name as shift_name
            FROM `groups` g
            JOIN technologies t ON g.technology_id = t.id
            JOIN semesters s ON g.semester_id = s.id
            JOIN shifts sh ON g.shift_id = sh.id
            WHERE g.id = ?
        ");
        $stmt->execute([$selectedGroupId]);
        $targetGroup = $stmt->fetch();

        // A group's periods always follow its own shift, regardless of the shift_id filter above
        $selectedShiftId = (int)($targetGroup['shift_id'] ?? $selectedShiftId);

        $titleHeader = "CLASS ROUTINE: " . ($targetGroup['short_code'] ?? '');
        $subHeader = ($targetGroup['tech_name'] ?? '') . " | " . ($targetGroup['sem_name'] ?? '') . " (" . ($targetGroup['shift_name'] ?? '') . ") | Academic Year: " . $academicYear;

        $stmt = $db->prepare("
            SELECT r.*, s.name as subject_name, s.subject_code,
                   t.name as teacher_name, t.short_code as teacher_code,
                   rm.room_code
            FROM routines r
            JOIN subjects s ON r.subject_id = s.id
            JOIN teachers t ON r.teacher_id = t.id
            JOIN rooms rm ON r.room_id = rm.id
            WHERE r.group_id = ? AND r.academic_year = ?
        ");
        $stmt->execute([$selectedGroupId, $academicYear]);
        $slots = $stmt->fetchAll();

    } elseif ($viewType === 'teacher' && $selectedTeacherId > 0) {
        $stmt = $db->prepare("SELECT t.*, d.name as dept_name FROM teachers t JOIN departments d ON t.department_id = d.id WHERE t.id = ?");
        $stmt->execute([$selectedTeacherId]);
        $targetTeacher = $stmt->fetch();

        $titleHeader = "FACULTY ROUTINE: " . ($targetTeacher['name'] ?? '') . " (" . ($targetTeacher['short_code'] ?? '') . ")";
        $subHeader = ($targetTeacher['designation'] ?? '') . ", Dept. of " . ($targetTeacher['dept_name'] ?? '') . " | Academic Year: " . $academicYear;

        // Restrict to the selected shift so a teacher who teaches in both shifts
        // doesn't collide two different classes into the same period cell.
        $stmt = $db->prepare("
            SELECT r.*, s.name as subject_name, s.subject_code,
                   g.short_code as group_code, rm.room_code
            FROM routines r
            JOIN subjects s ON r.subject_id = s.id
            JOIN `groups` g ON r.group_id = g.id
            JOIN rooms rm ON r.room_id = rm.id
            WHERE r.teacher_id = ? AND r.academic_year = ? AND g.shift_id = ?
        ");
        $stmt->execute([$selectedTeacherId, $academicYear, $selectedShiftId]);
        $slots = $stmt->fetchAll();

    } elseif ($viewType === 'room' && $selectedRoomId > 0) {
        $stmt = $db->prepare("SELECT * FROM rooms WHERE id = ?");
        $stmt->execute([$selectedRoomId]);
        $targetRoom = $stmt->fetch();

        $titleHeader = "ROOM UTILIZATION: " . ($targetRoom['room_code'] ?? '');
        $subHeader = "Type: " . ($targetRoom['room_type'] ?? '') . " | Capacity: " . ($targetRoom['capacity'] ?? 'N/A') . " Students | Academic Year: " . $academicYear;

        $stmt = $db->prepare("
            SELECT r.*, s.name as subject_name, s.subject_code,
                   g.short_code as group_code, t.short_code as teacher_code
            FROM routines r
            JOIN subjects s ON r.subject_id = s.id
            JOIN `groups` g ON r.group_id = g.id
            JOIN teachers t ON r.teacher_id = t.id
            WHERE r.room_id = ? AND r.academic_year = ? AND g.shift_id = ?
        ");
        $stmt->execute([$selectedRoomId, $academicYear, $selectedShiftId]);
        $slots = $stmt->fetchAll();

    } else {
        $slots = [];
    }

    // Periods follow whichever shift ended up effective for this view
    $periods = $db->prepare("SELECT * FROM periods WHERE active = 1 AND shift_id = ? ORDER BY period_number ASC");
    $periods->execute([$selectedShiftId]);
    $periods = $periods->fetchAll();

    // Build matrix
    foreach ($slots as $sl) {
        $day = $sl['day'];
        $pStart = (int)$sl['period_start'];
        $slotsMatrix[$day][$pStart] = $sl;
        $totalLoad += (int)$sl['load_count'];
    }

} catch (Throwable $e) {
    die("<div style='padding:30px; font-family:Arial, Helvetica, sans-serif; background:#FEF2F2; color:#B91C1C; border:1px solid #FECACA; border-radius:8px; margin:40px auto; max-width:800px;'>
            <h3 style='margin-top:0;'>Routine Master Database Error</h3>
            <p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
            <p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . " (Line " . $e->getLine() . ")</p>
            <a href='dashboard.php' style='color:#1E3A8A; font-weight:bold;'>&larr; Back to Dashboard</a>
         </div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titleHeader ?: 'Master Timetable') ?> - <?= htmlspecialchars($brandName) ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($assetsUrl) ?>/css/style.css">
    <style>
        :root {
            --navy: <?= htmlspecialchars($brandPrimary) ?>;
            --orange: <?= htmlspecialchars($brandAccent) ?>;
            --white: #FFFFFF;
            --light-gray: #F8FAFC;
            --border: #E2E8F0;
            --text-dark: #1E293B;
            --text-muted: #64748B;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: var(--font-body, 'Inter', sans-serif); background-color: var(--light-gray); color: var(--text-dark); min-height: 100vh; display: flex; flex-direction: column; }
        
        .navbar { background: var(--navy); color: #FFF; padding: 14px 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .nav-container { max-width: 1350px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .brand { color: #FFF; text-decoration: none; font-weight: 800; font-size: 16px; display: flex; align-items: center; gap: 10px; min-width: 0; }
        .brand span:last-child { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 700; cursor: pointer; text-decoration: none; border: 1px solid transparent; }
        .btn-outline { color: #FFF; border-color: rgba(255,255,255,0.3); background: rgba(255,255,255,0.05); }
        .btn-orange { background: var(--orange); color: #FFF; }
        
        .main-container { max-width: 1350px; margin: 20px auto; padding: 0 16px; width: 100%; flex: 1; }
        .card { background: #FFF; border: 1px solid var(--border); border-radius: 10px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.02); }
        
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; align-items: end; }
        .form-label { display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; text-transform: uppercase; color: var(--text-muted); }
        .form-select, .form-control { width: 100%; padding: 9px 12px; font-size: 13.5px; border: 1px solid var(--border); border-radius: 6px; outline: none; }
        
        .routine-table { width: 100%; border-collapse: collapse; background: #FFF; }
        .routine-table th, .routine-table td { border: 1px solid var(--border); padding: 8px; vertical-align: top; }
        .routine-table th { background: #F1F5F9; color: var(--navy); font-size: 12px; font-weight: 700; text-align: center; }
        .routine-table th small { display: block; font-size: 10.5px; color: var(--text-muted); font-weight: normal; }
        .day-col { background: #F8FAFC; font-weight: 800; text-align: center; vertical-align: middle !important; width: 90px; color: var(--navy); }
        
        .slot-box { border-radius: 6px; padding: 8px; font-size: 11.5px; display: flex; flex-direction: column; justify-content: space-between; height: 100%; min-height: 85px; }
        .slot-theory { background: #EFF6FF; border-left: 3.5px solid #2563EB; }
        .slot-lab { background: #FFF7ED; border-left: 3.5px solid var(--orange); }
        .sub-code { font-weight: 800; color: var(--navy); font-size: 12px; }
        .sub-title { font-weight: 600; color: var(--text-dark); margin: 3px 0; line-height: 1.25; }
        .sub-meta { display: flex; justify-content: space-between; font-size: 11px; margin-top: 4px; }
        
        @media print {
            .navbar, .card:first-of-type, .no-print { display: none !important; }
            body { background: #FFF; }
        }

        @media (max-width: 720px) {
            .navbar { padding: 12px 16px; }
            .brand span:last-child { display: none; }
            .main-container { padding: 0 10px; margin: 14px auto; }
            .card { padding: 14px; }
            .slot-box { min-height: 72px; font-size: 11px; }
        }
    </style>
</head>
<body>

    <header class="navbar no-print">
        <div class="nav-container">
            <a href="dashboard.php" class="brand">
                <span style="background:var(--orange); padding:4px 8px; border-radius:4px;"><?= htmlspecialchars($brandName) ?></span>
                <span>Master Timetable Explorer</span>
            </a>
            <div style="display: flex; gap: 10px;">
                <a href="dashboard.php" class="btn btn-outline">&larr; Back to Dashboard</a>
                <?php
                $exportId = $viewType === 'teacher' ? $selectedTeacherId : ($viewType === 'room' ? $selectedRoomId : $selectedGroupId);
                if ($exportId > 0):
                ?>
                <a href="export.php?format=pdf&view_type=<?= urlencode($viewType) ?>&id=<?= $exportId ?>&shift_id=<?= $selectedShiftId ?>&academic_year=<?= urlencode($academicYear) ?>" target="_blank" class="btn btn-primary">&#128196; Official PDF</a>
                <?php endif; ?>
                <button type="button" class="btn btn-orange" onclick="window.print()">&#128424; Quick Print</button>
            </div>
        </div>
    </header>

    <main class="main-container">
        <!-- Perspective Filter Bar -->
        <div class="card no-print">
            <form method="GET" action="">
                <div class="filter-grid">
                    <div>
                        <label class="form-label">Perspective</label>
                        <select name="view_type" class="form-select" onchange="this.form.submit()">
                            <option value="group" <?= $viewType === 'group' ? 'selected' : '' ?>>Group Timetable</option>
                            <option value="teacher" <?= $viewType === 'teacher' ? 'selected' : '' ?>>Teacher Timetable</option>
                            <option value="room" <?= $viewType === 'room' ? 'selected' : '' ?>>Room / Lab Timetable</option>
                        </select>
                    </div>

                    <?php if ($viewType === 'group'): ?>
                        <div>
                            <label class="form-label">Select Group</label>
                            <select name="group_id" class="form-select" onchange="this.form.submit()">
                                <?php foreach ($groups as $g): ?>
                                    <option value="<?= $g['id'] ?>" <?= $selectedGroupId === (int)$g['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($g['short_code']) ?> (<?= htmlspecialchars($g['tech_name']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php elseif ($viewType === 'teacher'): ?>
                        <div>
                            <label class="form-label">Select Faculty Member</label>
                            <select name="teacher_id" class="form-select" onchange="this.form.submit()">
                                <?php foreach ($teachers as $t): ?>
                                    <option value="<?= $t['id'] ?>" <?= $selectedTeacherId === (int)$t['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($t['name']) ?> (<?= htmlspecialchars($t['short_code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Shift</label>
                            <select name="shift_id" class="form-select" onchange="this.form.submit()">
                                <?php foreach ($shifts as $sh): ?>
                                    <option value="<?= $sh['id'] ?>" <?= $selectedShiftId === (int)$sh['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sh['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small style="color: var(--text-muted);">A teacher's classes are shown one shift at a time.</small>
                        </div>
                    <?php elseif ($viewType === 'room'): ?>
                        <div>
                            <label class="form-label">Select Classroom / Lab</label>
                            <select name="room_id" class="form-select" onchange="this.form.submit()">
                                <?php foreach ($rooms as $r): ?>
                                    <option value="<?= $r['id'] ?>" <?= $selectedRoomId === (int)$r['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($r['room_code']) ?> (<?= htmlspecialchars($r['room_type']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Shift</label>
                            <select name="shift_id" class="form-select" onchange="this.form.submit()">
                                <?php foreach ($shifts as $sh): ?>
                                    <option value="<?= $sh['id'] ?>" <?= $selectedShiftId === (int)$sh['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sh['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small style="color: var(--text-muted);">A room's usage is shown one shift at a time.</small>
                        </div>
                    <?php endif; ?>

                    <div>
                        <label class="form-label">Academic Year</label>
                        <input type="text" name="academic_year" class="form-control" value="<?= htmlspecialchars($academicYear) ?>" onchange="this.form.submit()">
                    </div>

                    <div>
                        <button type="submit" class="btn btn-orange" style="width: 100%;">Apply Filter</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Routine Title Banner -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div>
                <h2 style="font-size: 20px; font-weight: 800; color: var(--navy);"><?= htmlspecialchars($titleHeader ?: 'Routine Grid') ?></h2>
                <p style="font-size: 13px; color: var(--text-muted);"><?= htmlspecialchars($subHeader) ?></p>
            </div>
            <span style="background: #F1F5F9; border: 1px solid var(--border); padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 13px;">
                Total Weekly Periods: <?= $totalLoad ?>
            </span>
        </div>

        <!-- Timetable Matrix Grid -->
        <div class="card" style="padding: 0; overflow-x: auto;">
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
                    <?php foreach ($workingDays as $day): ?>
                        <tr>
                            <td class="day-col"><?= strtoupper($day) ?></td>
                            <?php 
                            $skipCount = 0;
                            foreach ($periods as $p): 
                                $pNum = (int)$p['period_number'];
                                if ($skipCount > 0) {
                                    $skipCount--;
                                    continue;
                                }

                                if (isset($slotsMatrix[$day][$pNum])) {
                                    $slot = $slotsMatrix[$day][$pNum];
                                    $span = max(1, ((int)$slot['period_end'] - (int)$slot['period_start']) + 1);
                                    if ($span > 1) {
                                        $skipCount = $span - 1;
                                    }
                                    $boxClass = $slot['is_lab'] ? 'slot-lab' : 'slot-theory';
                                    ?>
                                    <td colspan="<?= $span ?>">
                                        <div class="slot-box <?= $boxClass ?>">
                                            <div>
                                                <span class="sub-code"><?= htmlspecialchars($slot['subject_code']) ?></span>
                                                <div class="sub-title"><?= htmlspecialchars($slot['subject_name']) ?></div>
                                            </div>
                                            <div class="sub-meta">
                                                <?php if ($viewType === 'group'): ?>
                                                    <span style="font-weight:700; color:var(--navy);">&#128100; <?= htmlspecialchars($slot['teacher_code']) ?></span>
                                                    <span style="font-weight:700; color:var(--orange);">&#127963; <?= htmlspecialchars($slot['room_code']) ?></span>
                                                <?php elseif ($viewType === 'teacher'): ?>
                                                    <span style="font-weight:700; color:var(--navy);">&#128101; <?= htmlspecialchars($slot['group_code']) ?></span>
                                                    <span style="font-weight:700; color:var(--orange);">&#127963; <?= htmlspecialchars($slot['room_code']) ?></span>
                                                <?php elseif ($viewType === 'room'): ?>
                                                    <span style="font-weight:700; color:var(--navy);">&#128101; <?= htmlspecialchars($slot['group_code']) ?></span>
                                                    <span style="font-weight:700; color:var(--orange);">&#128100; <?= htmlspecialchars($slot['teacher_code']) ?></span>
                                                <?php endif; ?>
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
    </main>

</body>
</html>