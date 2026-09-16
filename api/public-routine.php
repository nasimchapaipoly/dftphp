<?php
/**
 * Class Routine Management System (NasimSoft)
 * Public Routine JSON API Endpoint (No Login Required)
 * Copyright (c) 2026 NasimSoft. All rights reserved.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$db = Database::getInstance();

$action = $_GET['action'] ?? 'get_routine';

switch ($action) {
    case 'get_routine':
        $groupId = (int)($_GET['group_id'] ?? 0);
        $academicYear = trim($_GET['academic_year'] ?? '2026');

        if ($groupId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Group ID required.']);
            exit;
        }

        $stmt = $db->prepare("
            SELECT r.id, r.day, r.period_start, r.period_end, r.class_type, r.load_count, r.is_lab,
                   s.name as subject_name, s.subject_code,
                   t.name as teacher_name, t.short_code as teacher_code,
                   rm.room_code, rm.name as room_name, rm.room_type,
                   p1.start_time as class_start_time,
                   p2.end_time as class_end_time
            FROM routines r
            JOIN subjects s ON r.subject_id = s.id
            JOIN teachers t ON r.teacher_id = t.id
            JOIN rooms rm ON r.room_id = rm.id
            LEFT JOIN periods p1 ON r.period_start = p1.period_number
            LEFT JOIN periods p2 ON r.period_end = p2.period_number
            WHERE r.group_id = ? AND r.academic_year = ? AND r.status = 'PUBLISHED'
            ORDER BY FIELD(r.day, 'Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), r.period_start ASC
        ");
        $stmt->execute([$groupId, $academicYear]);
        $slots = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'group_id' => $groupId,
            'academic_year' => $academicYear,
            'total_slots' => count($slots),
            'slots' => $slots
        ]);
        exit;

    case 'search':
        $query = trim($_GET['q'] ?? '');
        if (strlen($query) < 2) {
            echo json_encode(['success' => true, 'results' => []]);
            exit;
        }

        $term = "%{$query}%";
        $stmt = $db->prepare("
            SELECT r.*, s.name as subject_name, s.subject_code,
                   g.short_code as group_code,
                   t.name as teacher_name, t.short_code as teacher_code,
                   rm.room_code, rm.name as room_name
            FROM routines r
            JOIN subjects s ON r.subject_id = s.id
            JOIN `groups` g ON r.group_id = g.id
            JOIN teachers t ON r.teacher_id = t.id
            JOIN rooms rm ON r.room_id = rm.id
            WHERE (s.name LIKE ? OR s.subject_code LIKE ? OR t.name LIKE ? OR t.short_code LIKE ? OR rm.room_code LIKE ?)
              AND r.status = 'PUBLISHED'
            LIMIT 25
        ");
        $stmt->execute([$term, $term, $term, $term, $term]);
        $results = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'query' => $query,
            'results' => $results
        ]);
        exit;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        exit;
}