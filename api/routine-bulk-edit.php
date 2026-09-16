<?php
/**
 * Class Routine Management System (NasimSoft)
 * Bulk Routine Edit & Batch Deletion API
 * Copyright (c) 2026 NasimSoft. All rights reserved.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/validator.php';

Auth::requireAdmin();

header('Content-Type: application/json; charset=utf-8');

$db = Database::getInstance();
$action = $_GET['action'] ?? '';

switch ($action) {
    /**
     * Fetch all routine slots for a specific teacher with full relational metadata
     */
    case 'get_teacher_slots':
        $teacherId = (int)($_GET['teacher_id'] ?? 0);
        $academicYear = trim($_GET['academic_year'] ?? '2026');

        if ($teacherId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Valid Teacher ID required.']);
            exit;
        }

        $stmt = $db->prepare("
            SELECT r.id, r.day, r.period_start, r.period_end, r.class_type, r.load_count, r.is_lab,
                   s.name as subject_name, s.subject_code,
                   g.short_code as group_code, g.group_name,
                   t.name as tech_name, sem.name as sem_name, sh.name as shift_name,
                   rm.room_code, rm.name as room_name, rm.room_type, rm.id as room_id
            FROM routines r
            JOIN subjects s ON r.subject_id = s.id
            JOIN `groups` g ON r.group_id = g.id
            JOIN technologies t ON g.technology_id = t.id
            JOIN semesters sem ON g.semester_id = sem.id
            JOIN shifts sh ON g.shift_id = sh.id
            JOIN rooms rm ON r.room_id = rm.id
            WHERE r.teacher_id = ? AND r.academic_year = ?
            ORDER BY FIELD(r.day, 'Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), r.period_start ASC
        ");
        $stmt->execute([$teacherId, $academicYear]);
        $slots = $stmt->fetchAll();

        // Calculate summary load
        $totalLoad = array_sum(array_column($slots, 'load_count'));
        $labCount = count(array_filter($slots, fn($s) => $s['is_lab'] == 1));

        echo json_encode([
            'success' => true,
            'total_slots' => count($slots),
            'total_load' => $totalLoad,
            'lab_count' => $labCount,
            'slots' => $slots
        ]);
        exit;

    /**
     * Pre-flight Live Conflict Preview for Bulk Teacher Reassignment
     */
    case 'preview_reassign':
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $slotIds = array_map('intval', $data['slot_ids'] ?? []);
        $targetTeacherId = (int)($data['target_teacher_id'] ?? 0);
        $academicYear = trim($data['academic_year'] ?? '2026');

        if (empty($slotIds) || $targetTeacherId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Please select at least one slot and a target teacher.']);
            exit;
        }

        // Fetch slots to be transferred
        $inClause = implode(',', array_fill(0, count($slotIds), '?'));
        $stmt = $db->prepare("SELECT * FROM routines WHERE id IN ({$inClause})");
        $stmt->execute($slotIds);
        $slotsToMove = $stmt->fetchAll();

        // Check if target teacher is already booked during any of these slots
        $conflicts = [];
        $validator = new ConflictValidator($db);

        foreach ($slotsToMove as $slot) {
            $check = $validator->checkTeacherConflict(
                $targetTeacherId,
                $slot['day'],
                (int)$slot['period_start'],
                (int)$slot['period_end'],
                (int)$slot['id'], // exclude the slot itself
                (string)$slot['academic_year'],
                (int)$slot['shift_id']
            );

            if ($check) {
                $conflicts[] = [
                    'slot_id' => $slot['id'],
                    'day' => $slot['day'],
                    'period' => "P{$slot['period_start']}-P{$slot['period_end']}",
                    'reason' => $check['message']
                ];
            }
        }

        echo json_encode([
            'success' => true,
            'has_conflicts' => !empty($conflicts),
            'conflict_count' => count($conflicts),
            'conflicts' => $conflicts
        ]);
        exit;

    /**
     * Execute Bulk Reassign Faculty
     */
    case 'bulk_reassign_teacher':
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $slotIds = array_map('intval', $data['slot_ids'] ?? []);
        $targetTeacherId = (int)($data['target_teacher_id'] ?? 0);

        if (empty($slotIds) || $targetTeacherId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Missing slot IDs or replacement faculty ID.']);
            exit;
        }

        // Server-side safety net — re-validated here even though the UI already
        // ran a preview step, so this endpoint is safe to call on its own too.
        $inClause = implode(',', array_fill(0, count($slotIds), '?'));
        $slotStmt = $db->prepare("SELECT * FROM routines WHERE id IN ({$inClause})");
        $slotStmt->execute($slotIds);
        $slotsToMove = $slotStmt->fetchAll();

        $validator = new ConflictValidator($db);
        $conflicts = [];
        foreach ($slotsToMove as $slot) {
            $check = $validator->checkTeacherConflict(
                $targetTeacherId,
                $slot['day'],
                (int)$slot['period_start'],
                (int)$slot['period_end'],
                (int)$slot['id'],
                (string)$slot['academic_year'],
                (int)$slot['shift_id']
            );
            if ($check) {
                $conflicts[] = "{$slot['day']} P{$slot['period_start']}-{$slot['period_end']}: {$check['message']}";
            }
        }

        if (!empty($conflicts)) {
            echo json_encode([
                'success' => false,
                'message' => 'The target teacher is already booked for ' . count($conflicts) . ' of the selected slot(s): ' . implode(' | ', array_slice($conflicts, 0, 3)),
            ]);
            exit;
        }

        $db->beginTransaction();
        try {
            $params = array_merge([$targetTeacherId], $slotIds);

            $updateStmt = $db->prepare("UPDATE routines SET teacher_id = ?, updated_at = NOW() WHERE id IN ({$inClause})");
            $updateStmt->execute($params);
            $affected = $updateStmt->rowCount();

            logAudit('Bulk Reassign Teacher', 'Routine', $targetTeacherId, "Reassigned {$affected} slots to Teacher #{$targetTeacherId}");

            $db->commit();
            echo json_encode(['success' => true, 'updated_count' => $affected]);
        } catch (Throwable $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;

    /**
     * Execute Bulk Reassign Room
     */
    case 'bulk_reassign_room':
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $slotIds = array_map('intval', $data['slot_ids'] ?? []);
        $targetRoomId = (int)($data['target_room_id'] ?? 0);

        if (empty($slotIds) || $targetRoomId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Missing slot IDs or target room ID.']);
            exit;
        }

        // Pre-flight conflict check — without this, moving slots could silently
        // double-book the target room against something already scheduled there.
        $inClause = implode(',', array_fill(0, count($slotIds), '?'));
        $slotStmt = $db->prepare("SELECT * FROM routines WHERE id IN ({$inClause})");
        $slotStmt->execute($slotIds);
        $slotsToMove = $slotStmt->fetchAll();

        $validator = new ConflictValidator($db);
        $conflicts = [];
        foreach ($slotsToMove as $slot) {
            $check = $validator->checkRoomConflict(
                $targetRoomId,
                $slot['day'],
                (int)$slot['period_start'],
                (int)$slot['period_end'],
                (int)$slot['id'],
                (string)$slot['academic_year'],
                (int)$slot['shift_id']
            );
            if ($check) {
                $conflicts[] = "{$slot['day']} P{$slot['period_start']}-{$slot['period_end']}: {$check['message']}";
            }
        }

        if (!empty($conflicts)) {
            echo json_encode([
                'success' => false,
                'message' => 'The target room is already booked for ' . count($conflicts) . ' of the selected slot(s): ' . implode(' | ', array_slice($conflicts, 0, 3)),
            ]);
            exit;
        }

        $db->beginTransaction();
        try {
            $params = array_merge([$targetRoomId], $slotIds);

            $updateStmt = $db->prepare("UPDATE routines SET room_id = ?, updated_at = NOW() WHERE id IN ({$inClause})");
            $updateStmt->execute($params);
            $affected = $updateStmt->rowCount();

            logAudit('Bulk Reassign Room', 'Routine', $targetRoomId, "Reassigned {$affected} slots to Room #{$targetRoomId}");

            $db->commit();
            echo json_encode(['success' => true, 'updated_count' => $affected]);
        } catch (Throwable $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;

    /**
     * Execute Bulk Deletion of Selected Routine Slots
     */
    case 'bulk_delete':
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $slotIds = array_map('intval', $data['slot_ids'] ?? []);

        if (empty($slotIds)) {
            echo json_encode(['success' => false, 'message' => 'No routine slots selected for deletion.']);
            exit;
        }

        $db->beginTransaction();
        try {
            $inClause = implode(',', array_fill(0, count($slotIds), '?'));
            $delStmt = $db->prepare("DELETE FROM routines WHERE id IN ({$inClause})");
            $delStmt->execute($slotIds);
            $deletedCount = $delStmt->rowCount();

            logAudit('Bulk Delete Routine Slots', 'Routine', 0, "Purged {$deletedCount} routine slots via bulk editor");

            $db->commit();
            echo json_encode(['success' => true, 'deleted_count' => $deletedCount]);
        } catch (Throwable $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to delete slots: ' . $e->getMessage()]);
        }
        exit;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action requested.']);
        exit;
}