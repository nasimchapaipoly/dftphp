<?php
/**
 * Class Routine Management System (NasimSoft)
 * Routine Export Engine (CSV / Excel / Printable PDF Layout)
 * Copyright (c) 2026 NasimSoft. All rights reserved.
 */

declare(strict_types=1);

require_once __DIR__ . '/pdf-generator.php';

class RoutineExporter
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Fetch routine slots for the requested view.
     * $viewType: 'group' | 'teacher' | 'room' | 'bulk'
     * $shiftId: for 'teacher'/'room' views, restricts to one shift so two
     * different classes sharing the same period NUMBER (but different actual
     * clock times) in different shifts never collide in the printed matrix.
     */
    private function fetchSlots(string $viewType, int $id, string $academicYear, int $shiftId = 0): array
    {
        $sql = "
            SELECT r.*, s.name AS subject_name, s.subject_code, s.theory_load, s.practical_load, s.credit,
                   t.name AS teacher_name, t.short_code AS teacher_code, t.designation, t.phone,
                   rm.room_code, rm.name AS room_name,
                   g.short_code AS group_code, g.group_name, g.shift_id AS group_shift_id
            FROM routines r
            JOIN subjects s ON r.subject_id = s.id
            JOIN teachers t ON r.teacher_id = t.id
            JOIN rooms rm ON r.room_id = rm.id
            JOIN `groups` g ON r.group_id = g.id
            WHERE r.academic_year = ?
        ";
        $params = [$academicYear];

        switch ($viewType) {
            case 'teacher':
                $sql .= " AND r.teacher_id = ?";
                $params[] = $id;
                if ($shiftId > 0) { $sql .= " AND g.shift_id = ?"; $params[] = $shiftId; }
                break;
            case 'room':
                $sql .= " AND r.room_id = ?";
                $params[] = $id;
                if ($shiftId > 0) { $sql .= " AND g.shift_id = ?"; $params[] = $shiftId; }
                break;
            case 'bulk':
                // No extra filter — export everything for the academic year.
                break;
            case 'group':
            default:
                $sql .= " AND r.group_id = ?";
                $params[] = $id;
                break;
        }

        $sql .= " ORDER BY FIELD(r.day, 'Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), r.period_start ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function buildFilename(string $viewType, int $id, string $academicYear, string $ext): string
    {
        $safeYear = preg_replace('/[^A-Za-z0-9_-]/', '', $academicYear) ?: 'all';
        $label = $viewType === 'bulk' ? 'all' : ($viewType . '-' . $id);
        return "routine-{$label}-{$safeYear}.{$ext}";
    }

    /**
     * Export as CSV.
     */
    public function exportCsv(string $viewType, int $id, string $academicYear): void
    {
        $slots = $this->fetchSlots($viewType, $id, $academicYear);
        $filename = $this->buildFilename($viewType, $id, $academicYear, 'csv');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM so Excel renders non-ASCII characters correctly
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, ['Group', 'Day', 'Period Start', 'Period End', 'Subject Code', 'Subject Name', 'Teacher', 'Room', 'Type', 'Status']);

        foreach ($slots as $slot) {
            fputcsv($out, [
                $slot['group_code'] ?? '',
                $slot['day'] ?? '',
                $slot['period_start'] ?? '',
                $slot['period_end'] ?? '',
                $slot['subject_code'] ?? '',
                $slot['subject_name'] ?? '',
                ($slot['teacher_name'] ?? '') . ' (' . ($slot['teacher_code'] ?? '') . ')',
                $slot['room_code'] ?? '',
                !empty($slot['is_lab']) ? 'Lab' : 'Theory',
                $slot['status'] ?? '',
            ]);
        }

        fclose($out);
        exit;
    }

    /**
     * Export as Excel-compatible HTML table (.xls that Excel opens natively).
     */
    public function exportExcel(string $viewType, int $id, string $academicYear): void
    {
        $slots = $this->fetchSlots($viewType, $id, $academicYear);
        $filename = $this->buildFilename($viewType, $id, $academicYear, 'xls');

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        echo "<table border='1'>";
        echo "<tr>";
        foreach (['Group', 'Day', 'Period Start', 'Period End', 'Subject Code', 'Subject Name', 'Teacher', 'Room', 'Type', 'Status'] as $h) {
            echo '<th>' . htmlspecialchars($h) . '</th>';
        }
        echo "</tr>";

        foreach ($slots as $slot) {
            echo "<tr>";
            echo '<td>' . htmlspecialchars((string)($slot['group_code'] ?? '')) . '</td>';
            echo '<td>' . htmlspecialchars((string)($slot['day'] ?? '')) . '</td>';
            echo '<td>' . htmlspecialchars((string)($slot['period_start'] ?? '')) . '</td>';
            echo '<td>' . htmlspecialchars((string)($slot['period_end'] ?? '')) . '</td>';
            echo '<td>' . htmlspecialchars((string)($slot['subject_code'] ?? '')) . '</td>';
            echo '<td>' . htmlspecialchars((string)($slot['subject_name'] ?? '')) . '</td>';
            echo '<td>' . htmlspecialchars(($slot['teacher_name'] ?? '') . ' (' . ($slot['teacher_code'] ?? '') . ')') . '</td>';
            echo '<td>' . htmlspecialchars((string)($slot['room_code'] ?? '')) . '</td>';
            echo '<td>' . (!empty($slot['is_lab']) ? 'Lab' : 'Theory') . '</td>';
            echo '<td>' . htmlspecialchars((string)($slot['status'] ?? '')) . '</td>';
            echo "</tr>";
        }
        echo "</table>";
        exit;
    }

    /**
     * Build the routine document data array for any view type — shared by
     * the browser preview (renderPdfLayout), the real PDF generator, and the
     * Document Center's save/share flow, so there is exactly one place that
     * assembles this data.
     */
    public function buildDocument(string $viewType, int $id, string $academicYear, int $shiftId = 0): array
    {
        // For teacher/room views, auto-detect the shift if not explicitly given
        // (using whichever shift that teacher/room has the most classes in),
        // so period numbers never collide across shifts in the printed grid.
        if (in_array($viewType, ['teacher', 'room'], true) && $shiftId <= 0) {
            $probe = $this->fetchSlots($viewType, $id, $academicYear);
            if (!empty($probe)) {
                $counts = array_count_values(array_column($probe, 'group_shift_id'));
                arsort($counts);
                $shiftId = (int)array_key_first($counts);
            }
        }

        $slots = $this->fetchSlots($viewType, $id, $academicYear, $shiftId);

        // Branding + print design — all customisable from Admin → Site & Print Settings
        $instituteName       = function_exists('getSetting') ? getSetting('institute_name', 'Chapainawabganj Polytechnic Institute') : 'Chapainawabganj Polytechnic Institute';
        $printTitle          = (function_exists('getSetting') ? getSetting('print_title', '') : '') ?: $instituteName;
        $siteLogo            = function_exists('getSetting') ? getSetting('site_logo_url', '') : '';
        $govtLogo            = function_exists('getSetting') ? getSetting('govt_logo_url', '') : '';
        $printFont           = function_exists('getSetting') ? getSetting('print_font', 'sans') : 'sans';
        $printShowLogo       = (function_exists('getSetting') ? getSetting('print_show_logo', '0') : '0') === '1';
        $printShowGovtLogo   = (function_exists('getSetting') ? getSetting('print_show_govt_logo', '0') : '0') === '1';
        $printShowGenerated  = (function_exists('getSetting') ? getSetting('print_show_generated_on', '0') : '0') === '1';
        $printFooterNote     = function_exists('getSetting') ? getSetting('print_footer_note', '') : '';
        $printShowSignatures = (function_exists('getSetting') ? getSetting('print_show_signatures', '1') : '1') === '1';
        $accentColor         = function_exists('getSetting') ? getSetting('theme_accent_color', '#1FA855') : '#1FA855';
        $primaryColor        = function_exists('getSetting') ? getSetting('theme_primary_color', '#123FA6') : '#123FA6';

        $signatures = [];
        if ($printShowSignatures) {
            try {
                $signatures = $this->db->query("SELECT * FROM signatures WHERE is_active = 1 ORDER BY display_order ASC")->fetchAll();
            } catch (Exception $e) {}
        }

        // Working days (shared across all view types)
        $days = $this->db->query("SELECT day_name FROM working_days WHERE is_active = 1 ORDER BY day_order ASC")->fetchAll(PDO::FETCH_COLUMN) ?: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'];

        // Determine which shift's period timings to display. For group/bulk
        // views this comes from the routine rows themselves; for teacher/room
        // it was already resolved above (explicitly or auto-detected).
        $effectiveShiftId = $shiftId > 0 ? $shiftId : (!empty($slots) ? (int)($slots[0]['group_shift_id'] ?? $slots[0]['shift_id'] ?? 1) : 1);
        $periodsStmt = $this->db->prepare("SELECT * FROM periods WHERE active = 1 AND shift_id = ? ORDER BY period_number ASC");
        $periodsStmt->execute([$effectiveShiftId]);
        $periods = $periodsStmt->fetchAll();

        $doc = [
            'institute_name' => $printTitle,
            'logo_url'       => ($printShowLogo && !empty($siteLogo)) ? '../' . $siteLogo : '',
            'govt_logo_url'  => ($printShowGovtLogo && !empty($govtLogo)) ? '../' . $govtLogo : '',
            'generated_on'   => $printShowGenerated ? date('d F Y, h:i A') : '',
            'periods'        => $periods,
            'days'           => $days,
            'note_line'      => $printFooterNote,
            'signatures'     => $signatures,
            '_font'          => $printFont,
            '_primary_color' => $primaryColor,
            '_accent_color'  => $accentColor,
            '_shift_id'      => $effectiveShiftId,
        ];

        if ($viewType === 'teacher') {
            $doc = array_merge($doc, $this->buildTeacherDocument($slots, $id, $academicYear));
        } elseif ($viewType === 'group') {
            $doc = array_merge($doc, $this->buildGroupDocument($slots, $id, $academicYear));
        } elseif ($viewType === 'room') {
            $doc = array_merge($doc, $this->buildRoomDocument($slots, $id, $academicYear));
        } else {
            // 'bulk' — no house-style reference for this yet; render a simple
            // grouped list rather than guessing at a matrix layout.
            $doc = array_merge($doc, $this->buildGenericDocument($slots, $viewType, $academicYear));
        }

        return $doc;
    }

    /**
     * Render the print-ready routine document (Print > Save as PDF), using the
     * exact same institutional layout as the Public Portal print view —
     * see includes/routine-print-template.php.
     */
    public function renderPdfLayout(string $viewType, int $id, string $academicYear, int $shiftId = 0): void
    {
        require_once __DIR__ . '/routine-print-template.php';

        $doc = $this->buildDocument($viewType, $id, $academicYear, $shiftId);
        $printFont = $doc['_font'];
        $primaryColor = $doc['_primary_color'];
        $accentColor = $doc['_accent_color'];

        $fontStack = $printFont === 'serif'
            ? "'Times New Roman', Georgia, 'Noto Serif', serif"
            : "Arial, Helvetica, sans-serif";

        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?= htmlspecialchars($doc['title_line1'] ?? 'Class Routine') ?></title>
            <style>
                :root {
                    --navy: <?= htmlspecialchars($primaryColor) ?>;
                    --orange: <?= htmlspecialchars($accentColor) ?>;
                    --font-print-active: <?= $fontStack ?>;
                }
                * { box-sizing: border-box; }
                body { margin: 30px; }
                .no-print { text-align: center; margin-bottom: 20px; }
                .no-print button {
                    background: var(--orange); color: #FFF; border: none; padding: 10px 22px;
                    border-radius: 6px; font-size: 13.5px; font-weight: 700; cursor: pointer; font-family: Arial, sans-serif;
                }
                @media print { .no-print { display: none; } }
            </style>
            <link rel="stylesheet" href="<?= defined('ASSETS_URL') ? ASSETS_URL : '../assets' ?>/css/style.css">
        </head>
        <body>
            <div class="no-print">
                <button onclick="window.print()">Print / Save as PDF</button>
                <?php if (isPdfEngineReady()): ?>
                    <a href="documents.php?quick_save=1&view_type=<?= urlencode($viewType) ?>&id=<?= $id ?>&shift_id=<?= (int)$doc['_shift_id'] ?>&academic_year=<?= urlencode($academicYear) ?>&csrf_token=<?= generateCsrfToken() ?>" style="display:inline-block; margin-left:10px; background:var(--navy); color:#FFF; border:none; padding:10px 22px; border-radius:6px; font-size:13.5px; font-weight:700; text-decoration:none; font-family:Arial, sans-serif;">&#128190; Save to Document Center</a>
                <?php endif; ?>
            </div>
            <?= renderRoutinePrintDocument($doc) ?>
        </body>
        </html>
        <?php
        exit;
    }

    /** Join a list of strings with commas and "and" before the last item. */
    private function joinWithAnd(array $items): string
    {
        $items = array_values(array_unique(array_filter($items)));
        if (count($items) === 0) return '';
        if (count($items) === 1) return $items[0];
        $last = array_pop($items);
        return implode(', ', $items) . ' and ' . $last;
    }

    /** Build document data for the student/group print view (see reference: group routine). */
    private function buildGroupDocument(array $slots, int $groupId, string $academicYear): array
    {
        $stmt = $this->db->prepare("
            SELECT g.*, t.name AS tech_name, s.name AS sem_name, sh.name AS shift_name
            FROM `groups` g
            JOIN technologies t ON g.technology_id = t.id
            JOIN semesters s ON g.semester_id = s.id
            JOIN shifts sh ON g.shift_id = sh.id
            WHERE g.id = ?
        ");
        $stmt->execute([$groupId]);
        $group = $stmt->fetch();

        $classTeacherLine = '';
        if ($group && !empty($group['class_teacher_id'])) {
            $ctStmt = $this->db->prepare("SELECT t.name, t.designation, d.name AS dept_name FROM teachers t JOIN departments d ON t.department_id = d.id WHERE t.id = ?");
            $ctStmt->execute([$group['class_teacher_id']]);
            if ($ct = $ctStmt->fetch()) {
                $classTeacherLine = "Class Teacher: {$ct['name']}, {$ct['designation']}, {$ct['dept_name']}";
            }
        }

        $matrix = [];
        $subjectRows = [];
        $seen = [];
        foreach ($slots as $slot) {
            $slot['line2'] = $slot['teacher_code'];
            $matrix[$slot['day']][(int)$slot['period_start']] = $slot;

            $key = $slot['subject_id'] . '_' . $slot['teacher_id'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $subjectRows[] = [
                    'subject_name' => $slot['subject_name'],
                    'subject_code' => $slot['subject_code'],
                    'teacher_name' => $slot['teacher_name'],
                    'teacher_code' => $slot['teacher_code'],
                    'designation'  => $slot['designation'] ?? '',
                    'phone'        => $slot['phone'] ?? '',
                ];
            }
        }
        usort($subjectRows, fn($a, $b) => strcmp($a['subject_code'], $b['subject_code']));

        return [
            'mode'        => 'group',
            'title_line1' => $group ? ($group['sem_name'] . ' Class Routine ' . $academicYear) : 'Class Routine ' . $academicYear,
            'title_line2' => $group ? ($group['tech_name'] . ' (' . $group['shift_name'] . ')') : '',
            'meta_left'   => 'Group/Short Code: ' . ($group['short_code'] ?? ''),
            'meta_right'  => 'Total Load: ' . array_sum(array_column($slots, 'load_count')),
            'matrix'      => $matrix,
            'subject_rows'=> $subjectRows,
            'class_teacher_line' => $classTeacherLine,
        ];
    }

    /** Build document data for the teacher print view (see reference: teacher routine). */
    private function buildTeacherDocument(array $slots, int $teacherId, string $academicYear): array
    {
        $tStmt = $this->db->prepare("SELECT * FROM teachers WHERE id = ?");
        $tStmt->execute([$teacherId]);
        $teacher = $tStmt->fetch();

        $matrix = [];
        $subjectRows = [];
        $seen = [];
        $semLevels = [];
        $techNames = [];
        $shiftNames = [];

        foreach ($slots as $slot) {
            $slot['line2'] = $slot['group_code'];
            $matrix[$slot['day']][(int)$slot['period_start']] = $slot;

            $key = $slot['subject_id'] . '_' . $slot['group_id'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $subjectRows[] = [
                    'subject_name'   => $slot['subject_name'],
                    'subject_code'   => $slot['subject_code'],
                    'group_name'     => $slot['group_code'],
                    'theory_load'    => $slot['theory_load'] ?? 0,
                    'practical_load' => $slot['practical_load'] ?? 0,
                    'credit'         => $slot['credit'] ?? '',
                ];
            }
        }

        if (!empty($slots)) {
            $groupIds = array_unique(array_column($slots, 'group_id'));
            $in = implode(',', array_fill(0, count($groupIds), '?'));
            $metaStmt = $this->db->prepare("
                SELECT DISTINCT s.name AS sem_name, s.numeric_level, t.name AS tech_name, sh.name AS shift_name
                FROM `groups` g
                JOIN semesters s ON g.semester_id = s.id
                JOIN technologies t ON g.technology_id = t.id
                JOIN shifts sh ON g.shift_id = sh.id
                WHERE g.id IN ($in)
                ORDER BY s.numeric_level ASC
            ");
            $metaStmt->execute($groupIds);
            foreach ($metaStmt->fetchAll() as $row) {
                $semLevels[] = preg_replace('/\s*Semester$/i', '', $row['sem_name']);
                $techNames[] = $row['tech_name'];
                $shiftNames[] = $row['shift_name'];
            }
        }

        $classTeacherOf = [];
        if ($teacher) {
            $cgStmt = $this->db->prepare("SELECT short_code FROM `groups` WHERE class_teacher_id = ? AND active = 1");
            $cgStmt->execute([$teacher['id']]);
            $classTeacherOf = $cgStmt->fetchAll(PDO::FETCH_COLUMN);
        }

        return [
            'mode'         => 'teacher',
            'title_line1'  => $this->joinWithAnd($semLevels) . ' Semester Class Routine ' . $this->joinWithAnd(array_unique($shiftNames)) . '-' . $academicYear,
            'title_line2'  => $this->joinWithAnd(array_unique($techNames)) . '(' . $this->joinWithAnd(array_unique($shiftNames)) . ')',
            'meta_left'    => "Teacher's Name:: " . ($teacher['name'] ?? '') . ', ' . ($teacher['designation'] ?? ''),
            'meta_right'   => 'Total Load: ' . array_sum(array_column($slots, 'load_count')),
            'matrix'       => $matrix,
            'subject_rows' => $subjectRows,
            'class_teacher_heading' => !empty($classTeacherOf) ? ('Class Teacher of ' . implode(', ', $classTeacherOf)) : '',
            'note_line'    => !empty($classTeacherOf)
                ? 'Requested to advise the students for their learning, career guidance, counseling and any other problems.'
                : '',
        ];
    }

    /**
     * Build document data for the room/lab utilization print view (matches the
     * institute's official "Room Name: X" routine format — grid only, no
     * subject list table, no class-teacher line).
     */
    private function buildRoomDocument(array $slots, int $roomId, string $academicYear): array
    {
        $stmt = $this->db->prepare("SELECT * FROM rooms WHERE id = ?");
        $stmt->execute([$roomId]);
        $room = $stmt->fetch();

        $matrix = [];
        foreach ($slots as $slot) {
            $slot['line2'] = $slot['teacher_code'];
            $matrix[$slot['day']][(int)$slot['period_start']] = $slot;
        }

        $semLevels = [];
        $shiftNames = [];
        if (!empty($slots)) {
            $groupIds = array_unique(array_column($slots, 'group_id'));
            $in = implode(',', array_fill(0, count($groupIds), '?'));
            $metaStmt = $this->db->prepare("
                SELECT DISTINCT s.name AS sem_name, s.numeric_level, sh.name AS shift_name
                FROM `groups` g
                JOIN semesters s ON g.semester_id = s.id
                JOIN shifts sh ON g.shift_id = sh.id
                WHERE g.id IN ($in)
                ORDER BY s.numeric_level ASC
            ");
            $metaStmt->execute($groupIds);
            foreach ($metaStmt->fetchAll() as $row) {
                $semLevels[] = preg_replace('/\s*Semester$/i', '', $row['sem_name']);
                $shiftNames[] = $row['shift_name'];
            }
        }

        return [
            'mode'         => 'room',
            'title_line1'  => $this->joinWithAnd($semLevels) . ' Semester Class Routine ' . $this->joinWithAnd(array_unique($shiftNames)) . '-' . $academicYear,
            'title_line2'  => '',
            'meta_left'    => 'Room Name: ' . ($room['name'] ?? ($room['room_code'] ?? '')),
            'meta_right'   => 'Total Load: ' . array_sum(array_column($slots, 'load_count')),
            'matrix'       => $matrix,
            'subject_rows' => [], // no subject/teacher list table for room view — matches house style
        ];
    }

    /** Fallback simple list layout for bulk exports (no house-style reference given). */
    private function buildGenericDocument(array $slots, string $viewType, string $academicYear): array
    {
        $heading = $viewType === 'room' && !empty($slots)
            ? 'Room Utilization: ' . $slots[0]['room_code']
            : 'Complete Routine - All Groups';

        $subjectRows = [];
        foreach ($slots as $slot) {
            $subjectRows[] = [
                'subject_name' => $slot['day'] . ' — ' . $slot['subject_name'],
                'subject_code' => $slot['subject_code'],
                'teacher_name' => $slot['teacher_name'],
                'teacher_code' => $slot['teacher_code'],
                'designation'  => $slot['group_code'] ?? '',
                'phone'        => $slot['room_code'] ?? '',
            ];
        }

        return [
            'mode'         => 'group',
            'title_line1'  => $heading,
            'title_line2'  => 'Academic Year ' . $academicYear,
            'meta_left'    => 'Total Entries: ' . count($slots),
            'meta_right'   => '',
            'matrix'       => [],
            'subject_rows' => $subjectRows,
        ];
    }
}
