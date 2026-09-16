<?php
/**
 * Class Routine Management System (NasimSoft)
 * Auto Routine Generation API Endpoint
 * Copyright (c) 2026 NasimSoft.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/generator.php';

Auth::requireAdmin();

$action = $_GET['action'] ?? 'generate_group';
$generator = new RoutineGenerator();

switch ($action) {
    case 'generate_group':
        $groupId = (int)($_POST['group_id'] ?? ($_GET['group_id'] ?? 0));
        $overwrite = isset($_POST['overwrite']) ? (bool)$_POST['overwrite'] : true;
        $academicYear = trim($_POST['academic_year'] ?? getSetting('academic_year', '2026'));

        if ($groupId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Valid Group ID required.'], 400);
        }

        $result = $generator->generateForGroup($groupId, $academicYear, $overwrite);
        jsonResponse($result);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action.'], 400);
}