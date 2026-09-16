<?php
/**
 * Class Routine Management System (NasimSoft)
 * Real-time Conflict Checking AJAX API
 * Copyright (c) 2026 NasimSoft.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/validator.php';

$excludeId = (int)($_GET['exclude_id'] ?? 0);
$day = trim($_GET['day'] ?? '');
$pStart = (int)($_GET['period_start'] ?? 1);
$pEnd = (int)($_GET['period_end'] ?? 1);
$teacherId = (int)($_GET['teacher_id'] ?? 0);
$roomId = (int)($_GET['room_id'] ?? 0);
$groupId = (int)($_GET['group_id'] ?? 0);
$academicYear = trim($_GET['academic_year'] ?? '') ?: getSetting('academic_year', '2026');

if (empty($day) || $teacherId <= 0 || $roomId <= 0 || $groupId <= 0) {
    jsonResponse(['conflict' => false]);
}

$db = Database::getInstance();
$validator = new ConflictValidator($db);

// A group's shift never changes between its own slots, so we only need it
// to keep teacher/room checks from crossing into the other shift (since both
// shifts reuse the same period numbers 1-7 at different actual clock times).
$shiftStmt = $db->prepare("SELECT shift_id FROM `groups` WHERE id = ?");
$shiftStmt->execute([$groupId]);
$shiftId = (int)$shiftStmt->fetchColumn() ?: null;

// 1. Group Conflict
if ($gConflict = $validator->checkGroupConflict($groupId, $day, $pStart, $pEnd, $excludeId, $academicYear)) {
    jsonResponse(['conflict' => true, 'type' => 'GROUP', 'message' => $gConflict['message']]);
}

// 2. Teacher Conflict
if ($tConflict = $validator->checkTeacherConflict($teacherId, $day, $pStart, $pEnd, $excludeId, $academicYear, $shiftId)) {
    jsonResponse(['conflict' => true, 'type' => 'TEACHER', 'message' => $tConflict['message']]);
}

// 3. Room Conflict
if ($rConflict = $validator->checkRoomConflict($roomId, $day, $pStart, $pEnd, $excludeId, $academicYear, $shiftId)) {
    jsonResponse(['conflict' => true, 'type' => 'ROOM', 'message' => $rConflict['message']]);
}

jsonResponse(['conflict' => false, 'message' => 'No scheduling conflicts detected.']);
