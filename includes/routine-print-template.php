<?php
/**
 * Class Routine Management System (NasimSoft)
 * Shared Printable Routine Document Renderer
 * Copyright (c) 2026 NasimSoft. All rights reserved.
 *
 * Reproduces the institute's official routine document layout — the exact
 * format already used for physical printouts (two-line title, meta bar,
 * two-row period header, merged multi-period slot cells, a Subject/Teacher
 * list table below, and a three-signature footer). Used by both the public
 * portal print view and the admin PDF/export layout so there is exactly one
 * place that defines this document.
 */

declare(strict_types=1);

if (!function_exists('renderRoutinePrintDocument')) {

/**
 * @param array $doc {
 *   string $mode            'group' | 'teacher'
 *   string $title_line1     e.g. "Seventh Semester Class Routine 2026"
 *   string $title_line2     e.g. "Computer Science & Technology (First Shift)"
 *   string $meta_left       e.g. "Group/Short Code: CST 7/1"
 *   string $meta_right      e.g. "Total Load: 26"
 *   array  $periods         rows from the `periods` table, ordered
 *   array  $days            working day names, ordered
 *   array  $matrix          [day][period_number] => slot row (subject_code, line2, room_code)
 *   array  $subject_rows    rows for the list table beneath the grid
 *   string $class_teacher_line  optional, shown above the signatures
 *   string $note_line       optional note (e.g. advisory note for teacher view)
 *   array  $signatures      rows from the `signatures` table
 * }
 */
function renderRoutinePrintDocument(array $doc): string {
    $mode = $doc['mode'] ?? 'group';
    $periods = $doc['periods'] ?? [];
    $days = $doc['days'] ?? [];
    $matrix = $doc['matrix'] ?? [];
    $subjectRows = $doc['subject_rows'] ?? [];
    $signatures = $doc['signatures'] ?? [];
    $forPdf = $doc['for_pdf'] ?? false; // DOMPDF renders tables reliably but has weak flexbox support
    $watermark = $doc['watermark'] ?? ''; // e.g. "DRAFT"

    ob_start();
    ?>
    <div class="routine-doc">
        <?php if (!empty($watermark)): ?>
            <div class="routine-doc-watermark"><?= htmlspecialchars($watermark) ?></div>
        <?php endif; ?>
        <div class="routine-doc-header">
            <?php if (!empty($doc['logo_url']) || !empty($doc['govt_logo_url'])): ?>
                <div class="routine-doc-logo-row">
                    <?php if (!empty($doc['govt_logo_url'])): ?>
                        <img src="<?= htmlspecialchars($doc['govt_logo_url']) ?>" alt="" class="routine-doc-logo">
                    <?php endif; ?>
                    <?php if (!empty($doc['logo_url'])): ?>
                        <img src="<?= htmlspecialchars($doc['logo_url']) ?>" alt="" class="routine-doc-logo">
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <h1><?= htmlspecialchars($doc['institute_name'] ?? '') ?></h1>
            <?php if (!empty($doc['title_line1'])): ?><h2><?= htmlspecialchars($doc['title_line1']) ?></h2><?php endif; ?>
            <?php if (!empty($doc['title_line2'])): ?><h3><?= htmlspecialchars($doc['title_line2']) ?></h3><?php endif; ?>
            <?php if (!empty($doc['generated_on'])): ?><p class="generated-on">Generated on <?= htmlspecialchars($doc['generated_on']) ?></p><?php endif; ?>
        </div>

        <div class="routine-doc-meta">
            <span><?= htmlspecialchars($doc['meta_left'] ?? '') ?></span>
            <span><?= htmlspecialchars($doc['meta_right'] ?? '') ?></span>
        </div>

        <table class="routine-doc-grid">
            <thead>
                <tr>
                    <th class="corner-cell">Period</th>
                    <?php foreach ($periods as $p): ?>
                        <th><?= htmlspecialchars(preg_replace('/\s*Period$/i', '', $p['period_name'])) ?></th>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <th class="corner-cell">Day/Time</th>
                    <?php foreach ($periods as $p): ?>
                        <th class="time-cell">
                            <?= date('g:iA', strtotime($p['start_time'])) ?>-<?= date('g:iA', strtotime($p['end_time'])) ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($days as $day): ?>
                    <tr>
                        <td class="day-cell"><?= htmlspecialchars($day) ?></td>
                        <?php
                        $skip = 0;
                        foreach ($periods as $p):
                            $pNum = (int)$p['period_number'];
                            if ($skip > 0) { $skip--; continue; }

                            if (isset($matrix[$day][$pNum])) {
                                $slot = $matrix[$day][$pNum];
                                $span = max(1, ((int)$slot['period_end'] - (int)$slot['period_start']) + 1);
                                if ($span > 1) { $skip = $span - 1; }
                                ?>
                                <td colspan="<?= $span ?>" class="slot-cell">
                                    <div class="slot-code"><?= htmlspecialchars($slot['subject_code'] ?? '') ?></div>
                                    <div class="slot-line2"><?= htmlspecialchars($slot['line2'] ?? '') ?></div>
                                    <div class="slot-room"><?= htmlspecialchars($slot['room_code'] ?? '') ?></div>
                                </td>
                                <?php
                            } else {
                                echo '<td class="empty-cell"></td>';
                            }
                        endforeach;
                        ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (!empty($subjectRows)): ?>
            <h4 class="list-heading"><?= $mode === 'teacher' ? 'Subject List' : 'Teacher &amp; Subject List' ?></h4>
            <table class="routine-doc-list">
                <thead>
                    <?php if ($mode === 'teacher'): ?>
                        <tr><th>Subject Name</th><th>Code</th><th>Group Name</th><th>Theory Load</th><th>Practical Load</th><th>Credit</th></tr>
                    <?php else: ?>
                        <tr><th>SL</th><th>Subject Name</th><th>Teacher's Name</th><th>Designation</th><th>Phone no</th></tr>
                    <?php endif; ?>
                </thead>
                <tbody>
                    <?php foreach ($subjectRows as $i => $row): ?>
                        <tr>
                            <?php if ($mode === 'teacher'): ?>
                                <td><?= htmlspecialchars($row['subject_name']) ?></td>
                                <td><?= htmlspecialchars($row['subject_code']) ?></td>
                                <td><?= htmlspecialchars($row['group_name']) ?></td>
                                <td><?= htmlspecialchars((string)$row['theory_load']) ?></td>
                                <td><?= htmlspecialchars((string)$row['practical_load']) ?></td>
                                <td><?= htmlspecialchars((string)$row['credit']) ?></td>
                            <?php else: ?>
                                <td><?= $i + 1 ?></td>
                                <td><?= htmlspecialchars($row['subject_name']) ?> (<?= htmlspecialchars($row['subject_code']) ?>)</td>
                                <td><?= htmlspecialchars($row['teacher_name']) ?>(<?= htmlspecialchars($row['teacher_code']) ?>)</td>
                                <td><?= htmlspecialchars($row['designation'] ?? '') ?></td>
                                <td><?= htmlspecialchars($row['phone'] ?? '') ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (!empty($doc['class_teacher_line'])): ?>
            <p class="class-teacher-line"><?= htmlspecialchars($doc['class_teacher_line']) ?></p>
        <?php endif; ?>

        <?php if (!empty($doc['class_teacher_heading'])): ?>
            <p class="class-teacher-heading"><?= htmlspecialchars($doc['class_teacher_heading']) ?></p>
        <?php endif; ?>

        <?php if (!empty($doc['note_line'])): ?>
            <p class="note-line"><?= htmlspecialchars($doc['note_line']) ?></p>
        <?php endif; ?>

        <?php if (!empty($signatures)): ?>
            <?php if ($forPdf): ?>
                <table class="signature-strip-pdf">
                    <tr>
                        <?php foreach ($signatures as $sig): ?>
                            <td class="signature-slot-pdf">
                                <?php if (!empty($sig['signature_image'])): ?>
                                    <img src="<?= htmlspecialchars($sig['signature_image']) ?>" alt="" class="signature-scan">
                                <?php endif; ?>
                                <div class="signature-rule"></div>
                                <div class="signature-title"><?= nl2br(htmlspecialchars($sig['title'])) ?></div>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                </table>
            <?php else: ?>
                <div class="signature-strip">
                    <?php foreach ($signatures as $sig): ?>
                        <div class="signature-slot">
                            <?php if (!empty($sig['signature_image'])): ?>
                                <img src="<?= htmlspecialchars($sig['signature_image']) ?>" alt="" class="signature-scan">
                            <?php endif; ?>
                            <div class="signature-rule"></div>
                            <div class="signature-title"><?= nl2br(htmlspecialchars($sig['title'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

}
