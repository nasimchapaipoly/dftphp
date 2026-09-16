<?php
/**
 * Class Routine Management System (NasimSoft)
 * Routine View & Export API Endpoint
 * Copyright (c) 2026 NasimSoft. All rights reserved.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

$db = Database::getInstance();

$viewType = $_GET['view_type'] ?? 'group';
$id = (int)($_GET['id'] ?? 0);
$academicYear = trim($_GET['academic_year'] ?? '2026');

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing entity ID parameter.']);
    exit;
}

switch ($viewType) {
    case 'group':
        $stmt = $db->prepare("
            SELECT r.*, s.name as subject_name, s.subject_code,
                   t.name as teacher_name, t.short_code as teacher_code,
                   rm.room_code, rm.name as room_name, rm.room_type
            FROM routines r
            JOIN subjects s ON r.subject_id = s.id
            JOIN teachers t ON r.teacher_id = t.id
            JOIN rooms rm ON r.room_id = rm.id
            WHERE r.group_id = ? AND r.academic_year = ?
            ORDER BY FIELD(r.day, 'Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), r.period_start ASC
        ");
        $stmt->execute([$id, $academicYear]);
        $data = $stmt->fetchAll();
        break;

    case 'teacher':
        $stmt = $db->prepare("
            SELECT r.*, s.name as subject_name, s.subject_code,
                   g.short_code as group_code, g.group_name,
                   rm.room_code, rm.name as room_name, rm.room_type
            FROM routines r
            JOIN subjects s ON r.subject_id = s.id
            JOIN `groups` g ON r.group_id = g.id
            JOIN rooms rm ON r.room_id = rm.id
            WHERE r.teacher_id = ? AND r.academic_year = ?
            ORDER BY FIELD(r.day, 'Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), r.period_start ASC
        ");
        $stmt->execute([$id, $academicYear]);
        $data = $stmt->fetchAll();
        break;

    case 'room':
        $stmt = $db->prepare("
            SELECT r.*, s.name as subject_name, s.subject_code,
                   g.short_code as group_code,
                   t.name as teacher_name, t.short_code as teacher_code
            FROM routines r
            JOIN subjects s ON r.subject_id = s.id
            JOIN `groups` g ON r.group_id = g.id
            JOIN teachers t ON r.teacher_id = t.id
            WHERE r.room_id = ? AND r.academic_year = ?
            ORDER BY FIELD(r.day, 'Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), r.period_start ASC
        ");
        $stmt->execute([$id, $academicYear]);
        $data = $stmt->fetchAll();
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid view_type.']);
        exit;
}

echo json_encode([
    'success' => true,
    'view_type' => $viewType,
    'total_slots' => count($data),
    'slots' => $data
]);