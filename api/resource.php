<?php
/**
 * Class Routine Management System (NasimSoft)
 * Resource Verification & Capacity Check API
 * Copyright (c) 2026 NasimSoft.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$action = $_GET['action'] ?? '';
$db = Database::getInstance();

switch ($action) {
    case 'check_capacity':
        $roomId = (int)($_GET['room_id'] ?? 0);
        $groupId = (int)($_GET['group_id'] ?? 0);

        $room = $db->prepare("SELECT name, room_code, capacity, room_type FROM rooms WHERE id = ?");
        $room->execute([$roomId]);
        $roomData = $room->fetch();

        $group = $db->prepare("SELECT short_code, student_count FROM `groups` WHERE id = ?");
        $group->execute([$groupId]);
        $groupData = $group->fetch();

        if (!$roomData || !$groupData) {
            jsonResponse(['success' => false, 'message' => 'Room or group not found.'], 404);
        }

        $isSufficient = $roomData['capacity'] >= $groupData['student_count'];
        $diff = $groupData['student_count'] - $roomData['capacity'];

        jsonResponse([
            'success' => true,
            'sufficient' => $isSufficient,
            'room_capacity' => (int)$roomData['capacity'],
            'student_count' => (int)$groupData['student_count'],
            'difference' => $diff,
            'room_code' => $roomData['room_code'],
            'group_code' => $groupData['short_code'],
            'message' => $isSufficient 
                ? "Capacity suitable ({$groupData['student_count']} students in {$roomData['capacity']}-seat room)." 
                : "Warning: Room capacity insufficient! Group has {$groupData['student_count']} students but {$roomData['room_code']} holds only {$roomData['capacity']}."
        ]);
        break;

    case 'get_teacher_load':
        $teacherId = (int)($_GET['teacher_id'] ?? 0);
        $day = trim($_GET['day'] ?? '');

        $teacher = $db->prepare("SELECT name, short_code, max_daily_load FROM teachers WHERE id = ?");
        $teacher->execute([$teacherId]);
        $teacherData = $teacher->fetch();

        if (!$teacherData) {
            jsonResponse(['success' => false, 'message' => 'Teacher not found.'], 404);
        }

        $query = "SELECT SUM(load_count) as total_load, COUNT(*) as total_classes FROM routines WHERE teacher_id = ?";
        $params = [$teacherId];

        if (!empty($day)) {
            $query .= " AND day = ?";
            $params[] = $day;
        }

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $loadData = $stmt->fetch();

        $currentLoad = (int)($loadData['total_load'] ?? 0);
        $maxLoad = (int)$teacherData['max_daily_load'];

        jsonResponse([
            'success' => true,
            'teacher' => $teacherData['name'],
            'short_code' => $teacherData['short_code'],
            'current_load' => $currentLoad,
            'max_daily_load' => $maxLoad,
            'exceeded' => ($currentLoad >= $maxLoad)
        ]);
        break;

    case 'get_rooms_by_type':
        $type = in_array($_GET['type'] ?? '', ['NORMAL', 'LAB'], true) ? $_GET['type'] : 'NORMAL';
        $stmt = $db->prepare("SELECT id, name, room_code, capacity, building, floor FROM rooms WHERE room_type = ? AND active = 1 ORDER BY room_code ASC");
        $stmt->execute([$type]);
        jsonResponse(['success' => true, 'rooms' => $stmt->fetchAll()]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action.'], 400);
}