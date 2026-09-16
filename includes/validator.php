<?php
/**
 * Class Routine Management System (NasimSoft)
 * Comprehensive Conflict Detection & Multi-Constraint Validation Engine
 * Copyright (c) 2026 NasimSoft.
 */

declare(strict_types=1);

class ConflictValidator {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Check if teacher is already booked during [pStart, pEnd] on day
     */
    public function checkTeacherConflict(int $teacherId, string $day, int $pStart, int $pEnd, ?int $excludeId = null, ?string $academicYear = null, ?int $shiftId = null): ?array {
        $sql = "
            SELECT r.id, r.period_start, r.period_end, r.day,
                   t.name as teacher_name, t.short_code,
                   g.short_code as group_code, g.group_name,
                   s.name as subject_name, s.subject_code,
                   rm.room_code
            FROM routines r
            JOIN teachers t ON r.teacher_id = t.id
            JOIN `groups` g ON r.group_id = g.id
            JOIN subjects s ON r.subject_id = s.id
            JOIN rooms rm ON r.room_id = rm.id
            WHERE r.teacher_id = ? AND r.day = ?
              AND ((r.period_start <= ? AND r.period_end >= ?) OR (r.period_start <= ? AND r.period_end >= ?) OR (? <= r.period_start AND ? >= r.period_end))
        ";
        $params = [$teacherId, $day, $pStart, $pStart, $pEnd, $pEnd, $pStart, $pEnd];
        if ($academicYear !== null) {
            $sql .= " AND r.academic_year = ?";
            $params[] = $academicYear;
        }
        if ($shiftId !== null) {
            $sql .= " AND r.shift_id = ?";
            $params[] = $shiftId;
        }
        if ($excludeId && $excludeId > 0) {
            $sql .= " AND r.id != ?";
            $params[] = $excludeId;
        }
        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $res = $stmt->fetch();

        if ($res) {
            return [
                'type' => 'TEACHER_CONFLICT',
                'message' => "Teacher conflict: Faculty '{$res['short_code']}' ({$res['teacher_name']}) is already teaching {$res['subject_code']} for {$res['group_code']} on {$day} (Period {$res['period_start']}-{$res['period_end']}) in {$res['room_code']}.",
                'data' => $res
            ];
        }
        return null;
    }

    /**
     * Check if room is already occupied during [pStart, pEnd] on day
     */
    public function checkRoomConflict(int $roomId, string $day, int $pStart, int $pEnd, ?int $excludeId = null, ?string $academicYear = null, ?int $shiftId = null): ?array {
        $sql = "
            SELECT r.id, r.period_start, r.period_end, r.day,
                   rm.name as room_name, rm.room_code, rm.room_type,
                   g.short_code as group_code,
                   s.name as subject_name, s.subject_code,
                   t.short_code as teacher_code
            FROM routines r
            JOIN rooms rm ON r.room_id = rm.id
            JOIN `groups` g ON r.group_id = g.id
            JOIN subjects s ON r.subject_id = s.id
            JOIN teachers t ON r.teacher_id = t.id
            WHERE r.room_id = ? AND r.day = ?
              AND ((r.period_start <= ? AND r.period_end >= ?) OR (r.period_start <= ? AND r.period_end >= ?) OR (? <= r.period_start AND ? >= r.period_end))
        ";
        $params = [$roomId, $day, $pStart, $pStart, $pEnd, $pEnd, $pStart, $pEnd];
        if ($academicYear !== null) {
            $sql .= " AND r.academic_year = ?";
            $params[] = $academicYear;
        }
        if ($shiftId !== null) {
            $sql .= " AND r.shift_id = ?";
            $params[] = $shiftId;
        }
        if ($excludeId && $excludeId > 0) {
            $sql .= " AND r.id != ?";
            $params[] = $excludeId;
        }
        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $res = $stmt->fetch();

        if ($res) {
            return [
                'type' => 'ROOM_CONFLICT',
                'message' => "Room conflict: Room '{$res['room_code']}' is already occupied by {$res['group_code']} for {$res['subject_code']} on {$day} (Period {$res['period_start']}-{$res['period_end']}).",
                'data' => $res
            ];
        }
        return null;
    }

    /**
     * Check if group already has another class during [pStart, pEnd] on day
     */
    public function checkGroupConflict(int $groupId, string $day, int $pStart, int $pEnd, ?int $excludeId = null, ?string $academicYear = null): ?array {
        $sql = "
            SELECT r.id, r.period_start, r.period_end, r.day,
                   g.short_code as group_code,
                   s.name as subject_name, s.subject_code,
                   t.short_code as teacher_code,
                   rm.room_code
            FROM routines r
            JOIN `groups` g ON r.group_id = g.id
            JOIN subjects s ON r.subject_id = s.id
            JOIN teachers t ON r.teacher_id = t.id
            JOIN rooms rm ON r.room_id = rm.id
            WHERE r.group_id = ? AND r.day = ?
              AND ((r.period_start <= ? AND r.period_end >= ?) OR (r.period_start <= ? AND r.period_end >= ?) OR (? <= r.period_start AND ? >= r.period_end))
        ";
        $params = [$groupId, $day, $pStart, $pStart, $pEnd, $pEnd, $pStart, $pEnd];
        if ($academicYear !== null) {
            $sql .= " AND r.academic_year = ?";
            $params[] = $academicYear;
        }
        if ($excludeId && $excludeId > 0) {
            $sql .= " AND r.id != ?";
            $params[] = $excludeId;
        }
        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $res = $stmt->fetch();

        if ($res) {
            return [
                'type' => 'GROUP_CONFLICT',
                'message' => "Group conflict: Group {$res['group_code']} already has '{$res['subject_name']}' with {$res['teacher_code']} on {$day} (Period {$res['period_start']}-{$res['period_end']}) in {$res['room_code']}.",
                'data' => $res
            ];
        }
        return null;
    }

    /**
     * Strict Lab Rule: Lab must occupy exactly two consecutive periods
     */
    public function checkLabConsecutiveRule(bool $isLab, int $pStart, int $pEnd): ?array {
        if ($isLab) {
            if ($pEnd !== ($pStart + 1)) {
                return [
                    'type' => 'LAB_RULE_VIOLATION',
                    'message' => "Lab Rule Violation: Practical lab classes must occupy exactly two consecutive periods (e.g., 5th + 6th period). Current is Period {$pStart} to {$pEnd}."
                ];
            }
        }
        return null;
    }

    /**
     * Room Type Compatibility: Lab class requires LAB room type
     */
    public function checkRoomTypeRule(int $roomId, bool $isLab): ?array {
        $stmt = $this->db->prepare("SELECT name, room_code, room_type FROM rooms WHERE id = ?");
        $stmt->execute([$roomId]);
        $room = $stmt->fetch();

        if (!$room) {
            return ['type' => 'ROOM_NOT_FOUND', 'message' => 'Specified room does not exist.'];
        }

        if ($isLab && $room['room_type'] !== 'LAB') {
            return [
                'type' => 'ROOM_TYPE_MISMATCH',
                'message' => "Room Warning: Subject requires a Laboratory facility, but room '{$room['room_code']}' is marked as a Normal classroom."
            ];
        }
        return null;
    }

    /**
     * Room Capacity Check vs Student Count
     */
    public function checkRoomCapacity(int $roomId, int $groupId): ?array {
        $rStmt = $this->db->prepare("SELECT room_code, capacity FROM rooms WHERE id = ?");
        $rStmt->execute([$roomId]);
        $room = $rStmt->fetch();

        $gStmt = $this->db->prepare("SELECT short_code, student_count FROM `groups` WHERE id = ?");
        $gStmt->execute([$groupId]);
        $group = $gStmt->fetch();

        if ($room && $group && $group['student_count'] > $room['capacity']) {
            return [
                'type' => 'CAPACITY_INSUFFICIENT',
                'message' => "Room Capacity Warning: Group {$group['short_code']} has {$group['student_count']} students, exceeding {$room['room_code']} capacity of {$room['capacity']} seats."
            ];
        }
        return null;
    }

    /**
     * Teacher Daily Load Guard
     */
    public function checkTeacherDailyLoad(int $teacherId, string $day, int $addedLoad = 1, ?int $excludeId = null, ?string $academicYear = null): ?array {
        $tStmt = $this->db->prepare("SELECT short_code, name, max_daily_load FROM teachers WHERE id = ?");
        $tStmt->execute([$teacherId]);
        $teacher = $tStmt->fetch();

        if (!$teacher) return null;

        $sql = "SELECT SUM(load_count) FROM routines WHERE teacher_id = ? AND day = ?";
        $params = [$teacherId, $day];
        if ($academicYear !== null) {
            $sql .= " AND academic_year = ?";
            $params[] = $academicYear;
        }
        if ($excludeId && $excludeId > 0) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $currentLoad = (int)$stmt->fetchColumn();

        $maxLoad = (int)$teacher['max_daily_load'];
        if (($currentLoad + $addedLoad) > $maxLoad) {
            return [
                'type' => 'TEACHER_MAX_LOAD_EXCEEDED',
                'message' => "Teacher Load Alert: Faculty '{$teacher['short_code']}' daily workload would reach " . ($currentLoad + $addedLoad) . " loads on {$day}, which exceeds the max daily limit of {$maxLoad}."
            ];
        }
        return null;
    }

    /**
     * Validate an individual slot completely
     */
    public function validateSlot(array $slot, ?int $excludeId = null): array {
        $errors = [];
        $warnings = [];

        $isLab = !empty($slot['is_lab']);
        $pStart = (int)($slot['period_start'] ?? 1);
        $pEnd = (int)($slot['period_end'] ?? 1);
        $day = trim($slot['day'] ?? '');
        $teacherId = (int)($slot['teacher_id'] ?? 0);
        $roomId = (int)($slot['room_id'] ?? 0);
        $groupId = (int)($slot['group_id'] ?? 0);
        $academicYear = isset($slot['academic_year']) ? (string)$slot['academic_year'] : null;
        $shiftId = isset($slot['shift_id']) ? (int)$slot['shift_id'] : null;

        // 1. Lab consecutive rule
        if ($labRule = $this->checkLabConsecutiveRule($isLab, $pStart, $pEnd)) {
            $errors[] = $labRule['message'];
        }

        // 2. Group Conflict
        if ($gConf = $this->checkGroupConflict($groupId, $day, $pStart, $pEnd, $excludeId, $academicYear)) {
            $errors[] = $gConf['message'];
        }

        // 3. Teacher Conflict
        if ($tConf = $this->checkTeacherConflict($teacherId, $day, $pStart, $pEnd, $excludeId, $academicYear, $shiftId)) {
            $errors[] = $tConf['message'];
        }

        // 4. Room Conflict
        if ($rConf = $this->checkRoomConflict($roomId, $day, $pStart, $pEnd, $excludeId, $academicYear, $shiftId)) {
            $errors[] = $rConf['message'];
        }

        // 5. Room Type Rule (Warning)
        if ($rType = $this->checkRoomTypeRule($roomId, $isLab)) {
            $warnings[] = $rType['message'];
        }

        // 6. Capacity Rule (Warning)
        if ($cap = $this->checkRoomCapacity($roomId, $groupId)) {
            $warnings[] = $cap['message'];
        }

        // 7. Teacher Daily Load (Warning)
        $addedLoad = $isLab ? 2 : 1;
        if ($tLoad = $this->checkTeacherDailyLoad($teacherId, $day, $addedLoad, $excludeId, $academicYear)) {
            $warnings[] = $tLoad['message'];
        }

        return [
            'valid' => empty($errors),
            'has_warnings' => !empty($warnings),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * Complete Group Routine Audit (Curriculum fulfillment, consecutive labs, conflicts)
     */
    public function validateGroupRoutine(int $groupId, ?string $academicYear = null): array {
        $grpStmt = $this->db->prepare("
            SELECT g.*, t.name as tech_name, s.name as sem_name, sh.name as shift_name
            FROM `groups` g
            JOIN technologies t ON g.technology_id = t.id
            JOIN semesters s ON g.semester_id = s.id
            JOIN shifts sh ON g.shift_id = sh.id
            WHERE g.id = ?
        ");
        $grpStmt->execute([$groupId]);
        $group = $grpStmt->fetch();

        if (!$group) {
            return ['valid' => false, 'errors' => ['Group not found.']];
        }

        // Fetch curriculum subjects required for this group
        $curStmt = $this->db->prepare("
            SELECT s.id as subject_id, s.name as subject_name, s.subject_code, s.subject_type,
                   s.theory_load, s.practical_load, s.total_load,
                   gs.teacher_id, gs.preferred_room_id, gs.is_lab_required
            FROM group_subjects gs
            JOIN subjects s ON gs.subject_id = s.id
            WHERE gs.group_id = ? AND gs.active = 1
        ");
        $curStmt->execute([$groupId]);
        $curriculum = $curStmt->fetchAll();

        // Fetch scheduled routine slots for this group (restricted to the given
        // academic year, if provided, so audits don't mix in past years' data)
        $rSql = "
            SELECT r.*, s.name as subject_name, s.subject_code,
                   t.name as teacher_name, t.short_code as teacher_code,
                   rm.name as room_name, rm.room_code
            FROM routines r
            JOIN subjects s ON r.subject_id = s.id
            JOIN teachers t ON r.teacher_id = t.id
            JOIN rooms rm ON r.room_id = rm.id
            WHERE r.group_id = ?
        ";
        $rParams = [$groupId];
        if ($academicYear !== null) {
            $rSql .= " AND r.academic_year = ?";
            $rParams[] = $academicYear;
        }
        $rSql .= " ORDER BY FIELD(r.day, 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), r.period_start ASC";

        $rStmt = $this->db->prepare($rSql);
        $rStmt->execute($rParams);
        $routineSlots = $rStmt->fetchAll();

        $errors = [];
        $warnings = [];
        $passedChecks = [];

        $scheduledSubjectLoads = [];
        $totalScheduledLoad = 0;
        $totalRequiredLoad = 0;

        foreach ($curriculum as $cur) {
            $totalRequiredLoad += (int)$cur['total_load'];
            $scheduledSubjectLoads[$cur['subject_id']] = [
                'code' => $cur['subject_code'],
                'name' => $cur['subject_name'],
                'required_load' => (int)$cur['total_load'],
                'scheduled_load' => 0,
                'is_lab_required' => (bool)$cur['is_lab_required']
            ];
        }

        // Audit each routine slot
        foreach ($routineSlots as $slot) {
            $subId = (int)$slot['subject_id'];
            $load = (int)$slot['load_count'];
            $totalScheduledLoad += $load;

            if (isset($scheduledSubjectLoads[$subId])) {
                $scheduledSubjectLoads[$subId]['scheduled_load'] += $load;
            }

            // Check if slot has external conflicts
            $validation = $this->validateSlot($slot, (int)$slot['id']);
            if (!$validation['valid']) {
                foreach ($validation['errors'] as $err) {
                    $errors[] = "[Slot #{$slot['id']} - {$slot['day']} P{$slot['period_start']}-{$slot['period_end']}]: {$err}";
                }
            }
            if ($validation['has_warnings']) {
                foreach ($validation['warnings'] as $warn) {
                    $warnings[] = "[Slot #{$slot['id']} - {$slot['day']} P{$slot['period_start']}-{$slot['period_end']}]: {$warn}";
                }
            }
        }

        // Check load fulfillment per subject
        $unassignedSubjects = [];
        foreach ($scheduledSubjectLoads as $subId => $info) {
            if ($info['scheduled_load'] === 0) {
                $unassignedSubjects[] = "{$info['code']} - {$info['name']} (Requires {$info['required_load']} loads)";
            } elseif ($info['scheduled_load'] < $info['required_load']) {
                $warnings[] = "Partial Load: {$info['code']} ({$info['name']}) scheduled for {$info['scheduled_load']}/{$info['required_load']} required loads.";
            }
        }

        if (!empty($unassignedSubjects)) {
            $errors[] = "Unassigned Subjects Found (" . count($unassignedSubjects) . "): " . implode(", ", $unassignedSubjects);
        } else {
            $passedChecks[] = "All required curriculum subjects have scheduled routine slots.";
        }

        if ($totalScheduledLoad >= $totalRequiredLoad && $totalRequiredLoad > 0) {
            $passedChecks[] = "Total load completely fulfilled ({$totalScheduledLoad} / {$totalRequiredLoad} loads).";
        } elseif ($totalScheduledLoad < $totalRequiredLoad) {
            $warnings[] = "Total scheduled load ({$totalScheduledLoad}) is less than required curriculum load ({$totalRequiredLoad}).";
        }

        if (empty($errors)) {
            $passedChecks[] = "Zero teacher, room, and group conflicts detected.";
            $passedChecks[] = "All practical lab classes occupy two consecutive periods.";
        }

        return [
            'valid' => empty($errors),
            'group' => $group,
            'total_required_load' => $totalRequiredLoad,
            'total_scheduled_load' => $totalScheduledLoad,
            'errors' => $errors,
            'warnings' => $warnings,
            'passed_checks' => $passedChecks,
            'subject_breakdown' => $scheduledSubjectLoads
        ];
    }

    /**
     * Find suggested available slot for a teacher and room
     */
    public function findAvailableAlternatives(int $teacherId, int $roomId, int $groupId, int $duration = 1, ?string $academicYear = null, ?int $shiftId = null): array {
        $workingDays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'];
        $periods = [1, 2, 3, 4, 5, 6, 7];
        $alternatives = [];

        foreach ($workingDays as $day) {
            foreach ($periods as $pStart) {
                $pEnd = $pStart + $duration - 1;
                if ($pEnd > 7) continue;

                $tConf = $this->checkTeacherConflict($teacherId, $day, $pStart, $pEnd, null, $academicYear, $shiftId);
                $rConf = $this->checkRoomConflict($roomId, $day, $pStart, $pEnd, null, $academicYear, $shiftId);
                $gConf = $this->checkGroupConflict($groupId, $day, $pStart, $pEnd, null, $academicYear);

                if (!$tConf && !$rConf && !$gConf) {
                    $periodStr = ($pStart === $pEnd) ? "{$pStart}th Period" : "{$pStart}th + {$pEnd}th Period";
                    $alternatives[] = "{$day} {$periodStr}";
                    if (count($alternatives) >= 4) {
                        return $alternatives;
                    }
                }
            }
        }
        return $alternatives;
    }
}