<?php
/**
 * Class Routine Management System (NasimSoft)
 * Export Dispatcher Controller
 * Copyright (c) 2026 NasimSoft. All rights reserved.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/exporter.php';

// Allow public exports for student/parent convenience or enforce staff session
$format = strtolower(trim($_GET['format'] ?? 'pdf')); // 'pdf', 'excel', 'csv'
$viewType = strtolower(trim($_GET['view_type'] ?? 'group')); // 'group', 'teacher', 'room', 'bulk'
$id = (int)($_GET['id'] ?? 0);
$shiftId = (int)($_GET['shift_id'] ?? 0);
$academicYear = trim($_GET['academic_year'] ?? getSetting('academic_year', '2026'));

if ($viewType !== 'bulk' && $id <= 0) {
    die("Invalid request: Entity ID required for export.");
}

$exporter = new RoutineExporter();

switch ($format) {
    case 'csv':
        $exporter->exportCsv($viewType, $id, $academicYear);
        break;

    case 'excel':
    case 'xls':
        $exporter->exportExcel($viewType, $id, $academicYear);
        break;

    case 'pdf':
    default:
        $exporter->renderPdfLayout($viewType, $id, $academicYear, $shiftId);
        break;
}