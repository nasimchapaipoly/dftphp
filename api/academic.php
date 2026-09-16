<?php
/**
 * Class Routine Management System (NasimSoft)
 * Academic AJAX Helper Endpoint
 * Copyright (c) 2026 NasimSoft.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$action = $_GET['action'] ?? '';
$db = Database::getInstance();

switch ($action) {
    case 'get_technologies':
        $deptId = (int)($_GET['department_id'] ?? 0);
        $stmt = $db->prepare("SELECT id, name, short_name, code FROM technologies WHERE department_id = ? AND active = 1 ORDER BY name ASC");
        $stmt->execute([$deptId]);
        jsonResponse(['success' => true, 'technologies' => $stmt->fetchAll()]);
        break;

    case 'get_groups':
        $techId = (int)($_GET['technology_id'] ?? 0);
        $semesterId = (int)($_GET['semester_id'] ?? 0);
        $shiftId = (int)($_GET['shift_id'] ?? 0);

        $query = "SELECT id, short_code, group_name, section, student_count FROM `groups` WHERE active = 1";
        $params = [];

        if ($techId > 0) {
            $query .= " AND technology_id = ?";
            $params[] = $techId;
        }
        if ($semesterId > 0) {
            $query .= " AND semester_id = ?";
            $params[] = $semesterId;
        }
        if ($shiftId > 0) {
            $query .= " AND shift_id = ?";
            $params[] = $shiftId;
        }
        $query .= " ORDER BY short_code ASC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        jsonResponse(['success' => true, 'groups' => $stmt->fetchAll()]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action parameter.'], 400);
}