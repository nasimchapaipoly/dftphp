<?php
/**
 * Class Routine Management System (NasimSoft)
 * Routine Operations & Interactive AJAX Endpoint
 * Copyright (c) 2026 NasimSoft.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/validator.php';

$action = $_GET['action'] ?? '';
$db = Database::getInstance();
$validator = new ConflictValidator($db);

switch ($action) {
    case 'save_slot':
        Auth::requireAdmin();
        $id = (int)($_POST['id'] ?? 0);
        $groupId = (int)($_POST['group_id'] ?? 0);
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        $teacherId = (int)($_POST['teacher_id'] ?? 0);
        $roomId = (int)($_POST['room_id'] ?? 0);
        $day = trim($_POST['day'] ?? '');
        $pStart = (int)($_POST['period_start'] ?? 1);
        $pEnd = (int)($_POST['period_end'] ?? 1);
        $isLab = !empty($_POST['is_lab']) ? 1 : 0;
        $status = in_array($_POST['status'] ?? '', ['PUBLISHED', 'DRAFT', 'HIDDEN'], true) ? $_POST['status'] : 'PUBLISHED';
        $academicYear = trim($_POST['academic_year'] ?? '') ?: getSetting('academic_year', '2026');

        if ($groupId <= 0 || $subjectId <= 0 || $teacherId <= 0 || $roomId <= 0 || empty($day)) {
            jsonResponse(['success' => false, 'message' => 'All slot fields are required.'], 400);
        }

        if ($isLab) {
            $pEnd = max($pStart + 1, $pEnd);
            $loadCount = 2;
            $classType = 'Lab';
        } else {
            $pEnd = $pStart;
            $loadCount = 1;
            $classType = 'Theory';
        }

        // Fetch hierarchy IDs
        $grpStmt = $db->prepare("
            SELECT g.technology_id, g.semester_id, g.shift_id, t.department_id, d.institute_id
            FROM `groups` g
            JOIN technologies t ON g.technology_id = t.id
            JOIN departments d ON t.department_id = d.id
            WHERE g.id = ?
        ");
        $grpStmt->execute([$groupId]);
        $grp = $grpStmt->fetch();
        if (!$grp) {
            jsonResponse(['success' => false, 'message' => 'Group not found.'], 404);
        }
        $shiftId = (int)$grp['shift_id'];

        // Conflict checks — restricted to this academic year (and, for
        // teacher/room, this shift) so classes in other years/shifts that
        // happen to reuse the same period number are never mistaken for a
        // real clash. Same shared engine used everywhere else in the app.
        if ($gConf = $validator->checkGroupConflict($groupId, $day, $pStart, $pEnd, $id, $academicYear)) {
            jsonResponse(['success' => false, 'message' => $gConf['message']], 409);
        }
        if ($tConf = $validator->checkTeacherConflict($teacherId, $day, $pStart, $pEnd, $id, $academicYear, $shiftId)) {
            jsonResponse(['success' => false, 'message' => $tConf['message']], 409);
        }
        if ($rConf = $validator->checkRoomConflict($roomId, $day, $pStart, $pEnd, $id, $academicYear, $shiftId)) {
            jsonResponse(['success' => false, 'message' => $rConf['message']], 409);
        }

        $userId = $_SESSION['user_id'] ?? null;

        if ($id > 0) {
            $stmt = $db->prepare("
                UPDATE routines SET
                    institute_id = ?, department_id = ?, technology_id = ?, semester_id = ?, shift_id = ?,
                    group_id = ?, subject_id = ?, teacher_id = ?, room_id = ?, day = ?,
                    period_start = ?, period_end = ?, class_type = ?, load_count = ?, is_lab = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $grp['institute_id'], $grp['department_id'], $grp['technology_id'], $grp['semester_id'], $grp['shift_id'],
                $groupId, $subjectId, $teacherId, $roomId, $day,
                $pStart, $pEnd, $classType, $loadCount, $isLab, $status, $id
            ]);
            logAudit('Update Routine Slot', 'Routines', $id, "Updated slot on {$day} (Period {$pStart}-{$pEnd})");
            jsonResponse(['success' => true, 'message' => 'Routine slot updated successfully.', 'id' => $id]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO routines (
                    academic_year, institute_id, department_id, technology_id, semester_id, shift_id,
                    group_id, subject_id, teacher_id, room_id, day, period_start, period_end,
                    class_type, load_count, is_lab, status, created_by
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?
                )
            ");
            $stmt->execute([
                $academicYear, $grp['institute_id'], $grp['department_id'], $grp['technology_id'], $grp['semester_id'], $grp['shift_id'],
                $groupId, $subjectId, $teacherId, $roomId, $day, $pStart, $pEnd,
                $classType, $loadCount, $isLab, $status, $userId
            ]);
            $newId = (int)$db->lastInsertId();
            logAudit('Create Routine Slot', 'Routines', $newId, "Created slot on {$day} (Period {$pStart}-{$pEnd})");
            jsonResponse(['success' => true, 'message' => 'Routine slot scheduled successfully.', 'id' => $newId]);
        }
        break;

    case 'delete_slot':
        Auth::requireAdmin();
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid slot ID.'], 400);
        }
        $stmt = $db->prepare("DELETE FROM routines WHERE id = ?");
        $stmt->execute([$id]);
        logAudit('Delete Routine Slot', 'Routines', $id, 'Deleted class slot');
        jsonResponse(['success' => true, 'message' => 'Routine slot deleted successfully.']);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action.'], 400);
}