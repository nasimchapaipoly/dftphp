<?php
/**
 * Class Routine Management System (NasimSoft)
 * Routine Validation & Conflict Diagnostic API
 * Copyright (c) 2026 NasimSoft.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/validator.php';

$action = $_GET['action'] ?? '';
$validator = new ConflictValidator();

switch ($action) {
    case 'audit_group':
        $groupId = (int)($_GET['group_id'] ?? 0);
        if ($groupId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Group ID required.'], 400);
        }
        $academicYear = trim($_GET['academic_year'] ?? getSetting('academic_year', '2026'));
        $result = $validator->validateGroupRoutine($groupId, $academicYear);
        jsonResponse(['success' => true, 'audit' => $result]);
        break;

    case 'suggest_alternatives':
        $teacherId = (int)($_GET['teacher_id'] ?? 0);
        $roomId = (int)($_GET['room_id'] ?? 0);
        $groupId = (int)($_GET['group_id'] ?? 0);
        $duration = max(1, (int)($_GET['duration'] ?? 1));
        $academicYear = trim($_GET['academic_year'] ?? getSetting('academic_year', '2026'));
        $shiftId = (int)($_GET['shift_id'] ?? 0) ?: null;

        $suggestions = $validator->findAvailableAlternatives($teacherId, $roomId, $groupId, $duration, $academicYear, $shiftId);
        jsonResponse(['success' => true, 'suggestions' => $suggestions]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action.'], 400);
}