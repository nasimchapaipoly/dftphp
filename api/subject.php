<?php
/**
 * Class Routine Management System (NasimSoft)
 * Subject Catalog & Curriculum API
 * Copyright (c) 2026 NasimSoft.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$action = $_GET['action'] ?? '';
$db = Database::getInstance();

switch ($action) {
    case 'get_by_department':
        $deptId = (int)($_GET['department_id'] ?? 0);
        $stmt = $db->prepare("SELECT id, name, subject_code, subject_type, theory_load, practical_load, total_load, credit FROM subjects WHERE department_id = ? AND active = 1 ORDER BY subject_code ASC");
        $stmt->execute([$deptId]);
        jsonResponse(['success' => true, 'subjects' => $stmt->fetchAll()]);
        break;

    case 'get_group_curriculum':
        $groupId = (int)($_GET['group_id'] ?? 0);
        $stmt = $db->prepare("
            SELECT gs.id as assignment_id, gs.is_lab_required, gs.teacher_id, gs.preferred_room_id,
                   s.id as subject_id, s.name as subject_name, s.subject_code, s.subject_type,
                   s.theory_load, s.practical_load, s.total_load, s.credit,
                   t.name as teacher_name, t.short_code as teacher_code,
                   r.name as room_name, r.room_code
            FROM group_subjects gs
            JOIN subjects s ON gs.subject_id = s.id
            LEFT JOIN teachers t ON gs.teacher_id = t.id
            LEFT JOIN rooms r ON gs.preferred_room_id = r.id
            WHERE gs.group_id = ? AND gs.active = 1
            ORDER BY s.subject_code ASC
        ");
        $stmt->execute([$groupId]);
        $items = $stmt->fetchAll();

        $totalLoad = 0;
        foreach ($items as $item) {
            $totalLoad += (int)$item['total_load'];
        }

        jsonResponse([
            'success' => true,
            'subjects' => $items,
            'total_load' => $totalLoad
        ]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action parameter.'], 400);
}