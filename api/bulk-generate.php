<?php
/**
 * Class Routine Management System (NasimSoft)
 * Bulk Routine Generation AJAX API Endpoint
 * Copyright (c) 2026 NasimSoft. All rights reserved.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/generator.php';

Auth::requireAdmin();

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$db = Database::getInstance();

switch ($action) {
    /**
     * Build the list of target groups based on filter criteria
     */
    case 'get_queue':
        $deptId  = (int)($_GET['department_id'] ?? 0);
        $techId  = (int)($_GET['technology_id'] ?? 0);
        $semId   = (int)($_GET['semester_id'] ?? 0);
        $shiftId = (int)($_GET['shift_id'] ?? 0);

        $conditions = ["g.active = 1"];
        $params = [];

        if ($deptId > 0) {
            $conditions[] = "t.department_id = ?";
            $params[] = $deptId;
        }
        if ($techId > 0) {
            $conditions[] = "g.technology_id = ?";
            $params[] = $techId;
        }
        if ($semId > 0) {
            $conditions[] = "g.semester_id = ?";
            $params[] = $semId;
        }
        if ($shiftId > 0) {
            $conditions[] = "g.shift_id = ?";
            $params[] = $shiftId;
        }

        $whereSql = implode(" AND ", $conditions);

        $sql = "
            SELECT g.id, g.short_code, g.group_name,
                   t.name as tech_name, t.short_name as tech_code,
                   s.name as sem_name, s.numeric_level,
                   sh.name as shift_name,
                   d.name as dept_name,
                   (SELECT COUNT(*) FROM group_subjects gs WHERE gs.group_id = g.id AND gs.active = 1) as subject_count,
                   (SELECT COALESCE(SUM(sub.total_load), 0) 
                    FROM group_subjects gs 
                    JOIN subjects sub ON gs.subject_id = sub.id 
                    WHERE gs.group_id = g.id AND gs.active = 1) as total_curriculum_load,
                   (SELECT COUNT(*) FROM routines r WHERE r.group_id = g.id) as existing_slots
            FROM `groups` g
            JOIN technologies t ON g.technology_id = t.id
            JOIN departments d ON t.department_id = d.id
            JOIN semesters s ON g.semester_id = s.id
            JOIN shifts sh ON g.shift_id = sh.id
            WHERE {$whereSql}
            ORDER BY s.numeric_level ASC, t.short_name ASC, sh.name ASC, g.short_code ASC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $queue = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'total_groups' => count($queue),
            'queue' => $queue
        ]);
        exit;

    /**
     * Clean slate: clear all existing routine slots for target groups upfront
     */
    case 'clear_scope':
        $data = json_decode(file_get_contents('php://input'), true);
        $groupIds = $data['group_ids'] ?? [];

        if (empty($groupIds) || !is_array($groupIds)) {
            echo json_encode(['success' => false, 'message' => 'No target groups specified for reset.']);
            exit;
        }

        $sanitizedIds = array_map('intval', $groupIds);
        $placeholders = implode(',', array_fill(0, count($sanitizedIds), '?'));

        $delStmt = $db->prepare("DELETE FROM routines WHERE group_id IN ({$placeholders})");
        $delStmt->execute($sanitizedIds);
        $deletedCount = $delStmt->rowCount();

        logAudit('Bulk Clear Routines', 'Generator', 0, "Cleared {$deletedCount} routine records for " . count($sanitizedIds) . " groups.");

        echo json_encode([
            'success' => true,
            'deleted_slots' => $deletedCount,
            'groups_affected' => count($sanitizedIds)
        ]);
        exit;

    /**
     * Process an individual group sequentially in the bulk queue
     */
    case 'process_group':
        $groupId = (int)($_POST['group_id'] ?? 0);
        $academicYear = trim($_POST['academic_year'] ?? getSetting('academic_year', '2026'));
        // Do not delete internally if clear_scope already wiped the batch
        $overwrite = isset($_POST['overwrite']) ? filter_var($_POST['overwrite'], FILTER_VALIDATE_BOOLEAN) : false;

        if ($groupId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid group identifier.']);
            exit;
        }

        $startTime = microtime(true);
        $generator = new RoutineGenerator($db);
        $result = $generator->generateForGroup($groupId, $academicYear, $overwrite);
        $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);

        $result['execution_time_ms'] = $executionTimeMs;
        echo json_encode($result);
        exit;

    default:
        echo json_encode(['success' => false, 'message' => 'Unsupported API operation.']);
        exit;
}