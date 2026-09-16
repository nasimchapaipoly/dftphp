<?php
/**
 * Class Routine Management System (NasimSoft)
 * Intelligent CSP Backtracking Routine Scheduling Engine
 * Copyright (c) 2026 NasimSoft. All rights reserved.
 */

declare(strict_types=1);

require_once __DIR__ . '/validator.php';

class RoutineGenerator {
    private PDO $db;
    private ConflictValidator $validator;
    private array $workingDays = [];
    private array $periods = [];
    private array $candidateLabPairs = [];
    private int $loadedShiftId = 0;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getInstance();
        $this->validator = new ConflictValidator($this->db);

        // Fetch active days (shared across shifts)
        $daysStmt = $this->db->query("SELECT day_name FROM working_days WHERE is_active = 1 ORDER BY day_order ASC");
        $this->workingDays = $daysStmt->fetchAll(PDO::FETCH_COLUMN) ?: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'];

        // Periods are loaded per-shift inside generateForGroup(), since both
        // shifts reuse the same period numbers (1-7) at different clock times —
        // loading them globally here would duplicate/corrupt the period list.
    }

    /**
     * Load this shift's period numbers and valid consecutive lab pairs.
     * Cheap to call repeatedly — skips reloading if already loaded for this shift.
     */
    private function loadPeriodsForShift(int $shiftId): void {
        if ($this->loadedShiftId === $shiftId && !empty($this->periods)) {
            return;
        }

        $pStmt = $this->db->prepare("SELECT period_number FROM periods WHERE active = 1 AND shift_id = ? ORDER BY period_number ASC");
        $pStmt->execute([$shiftId]);
        $this->periods = $pStmt->fetchAll(PDO::FETCH_COLUMN) ?: [1, 2, 3, 4, 5, 6, 7];

        $this->candidateLabPairs = [];
        for ($i = 0; $i < count($this->periods) - 1; $i++) {
            $p1 = (int)$this->periods[$i];
            $p2 = (int)$this->periods[$i + 1];
            if ($p2 === $p1 + 1) {
                $this->candidateLabPairs[] = [$p1, $p2];
            }
        }

        $this->loadedShiftId = $shiftId;
    }

    /**
     * Generate complete routine for an individual group
     */
    public function generateForGroup(int $groupId, string $academicYear = '2026', bool $overwriteExisting = true): array {
        // 1. Fetch Group Metadata
        $grpStmt = $this->db->prepare("
            SELECT g.*, t.name as tech_name, t.short_name as tech_code,
                   s.name as sem_name, s.numeric_level,
                   sh.name as shift_name,
                   d.name as dept_name, d.id as dept_id,
                   t.id as tech_id, s.id as sem_id, sh.id as shift_id,
                   i.id as institute_id, i.name as institute_name
        FROM `groups` g
        JOIN technologies t ON g.technology_id = t.id
        JOIN semesters s ON g.semester_id = s.id
        JOIN shifts sh ON g.shift_id = sh.id
        JOIN departments d ON t.department_id = d.id
        JOIN institutes i ON d.institute_id = i.id
        WHERE g.id = ?
        ");
        $grpStmt->execute([$groupId]);
        $group = $grpStmt->fetch();

        if (!$group) {
            return ['success' => false, 'message' => 'Academic group not found.'];
        }

        $this->loadPeriodsForShift((int)$group['shift_id']);

        // 2. Fetch Curriculum Requirements
        $curStmt = $this->db->prepare("
            SELECT gs.subject_id, gs.teacher_id, gs.preferred_room_id, gs.is_lab_required,
                   s.name as subject_name, s.subject_code, s.subject_type,
                   s.theory_load, s.practical_load, s.total_load
            FROM group_subjects gs
            JOIN subjects s ON gs.subject_id = s.id
            WHERE gs.group_id = ? AND gs.active = 1
        ");
        $curStmt->execute([$groupId]);
        $curriculum = $curStmt->fetchAll();

        if (empty($curriculum)) {
            return ['success' => false, 'message' => 'No curriculum subjects assigned to this group yet. Please configure Group Curriculum first.'];
        }

        $deptTeachersStmt = $this->db->prepare("SELECT id FROM teachers WHERE department_id = ? AND active = 1");
        $deptTeachersStmt->execute([$group['dept_id']]);
        $defaultTeachers = $deptTeachersStmt->fetchAll(PDO::FETCH_COLUMN);
        if (empty($defaultTeachers)) {
            $defaultTeachers = $this->db->query("SELECT id FROM teachers WHERE active = 1")->fetchAll(PDO::FETCH_COLUMN);
        }
        $normalRooms = $this->db->query("SELECT id FROM rooms WHERE room_type = 'NORMAL' AND active = 1")->fetchAll(PDO::FETCH_COLUMN);
        $labRooms = $this->db->query("SELECT id FROM rooms WHERE room_type = 'LAB' AND active = 1")->fetchAll(PDO::FETCH_COLUMN);

        // Per-teacher max daily load (falls back to 6 only if not configured)
        $teacherMaxLoad = [];
        foreach ($this->db->query("SELECT id, max_daily_load FROM teachers WHERE active = 1") as $t) {
            $teacherMaxLoad[(int)$t['id']] = (int)($t['max_daily_load'] ?: 6);
        }

        // 3. Decompose Curriculum into Scheduling Tasks
        $tasks = [];
        foreach ($curriculum as $c) {
            $teacherId = !empty($c['teacher_id']) ? (int)$c['teacher_id'] : ($defaultTeachers[0] ?? 1);
            $subId = (int)$c['subject_id'];
            $theoryLoad = (int)$c['theory_load'];
            $practicalLoad = (int)$c['practical_load'];

            // Practical / Lab Units: 1 lab class = 2 consecutive periods = 2 loads
            if ($c['is_lab_required'] || $c['subject_type'] === 'Lab' || $c['subject_type'] === 'Practical' || $practicalLoad > 0) {
                $labSessions = max(1, (int)ceil($practicalLoad / 2));
                $preferredLab = !empty($c['preferred_room_id']) ? (int)$c['preferred_room_id'] : ($labRooms[0] ?? ($normalRooms[0] ?? 1));

                for ($i = 0; $i < $labSessions; $i++) {
                    $tasks[] = [
                        'subject_id' => $subId,
                        'subject_name' => $c['subject_name'],
                        'subject_code' => $c['subject_code'],
                        'teacher_id' => $teacherId,
                        'preferred_room_id' => $preferredLab,
                        'is_lab' => true,
                        'duration' => 2,
                        'load' => 2,
                        'priority' => 1 // Labs scheduled first (MRV heuristic)
                    ];
                }
            }

            // Theory Units: 1 theory class = 1 period = 1 load
            if ($theoryLoad > 0) {
                $preferredNormal = !empty($c['preferred_room_id']) ? (int)$c['preferred_room_id'] : ($normalRooms[0] ?? 1);
                for ($i = 0; $i < $theoryLoad; $i++) {
                    $tasks[] = [
                        'subject_id' => $subId,
                        'subject_name' => $c['subject_name'],
                        'subject_code' => $c['subject_code'],
                        'teacher_id' => $teacherId,
                        'preferred_room_id' => $preferredNormal,
                        'is_lab' => false,
                        'duration' => 1,
                        'load' => 1,
                        'priority' => 2
                    ];
                }
            }
        }

        // Sort tasks by priority (Labs first)
        usort($tasks, fn($a, $b) => $a['priority'] <=> $b['priority']);

        // 4. In-Memory Timetable State Tracking
        $externalReservations = $this->loadExternalReservations($groupId, $overwriteExisting, $academicYear, (int)$group['shift_id']);
        $groupGrid = [];
        $teacherDailyLoad = [];
        $groupDailyClasses = [];

        foreach ($this->workingDays as $d) {
            $groupGrid[$d] = [];
            $groupDailyClasses[$d] = 0;
        }

        $allocatedSlots = [];
        $unallocatedTasks = [];

        // 5. Backtracking CSP Placement
        foreach ($tasks as $task) {
            $placed = false;

            // Sort days: least loaded days first to balance distribution
            $sortedDays = $this->workingDays;
            usort($sortedDays, function($d1, $d2) use ($groupDailyClasses) {
                return $groupDailyClasses[$d1] <=> $groupDailyClasses[$d2];
            });

            foreach ($sortedDays as $day) {
                // Avoid placing the same theory subject twice on the same day if possible
                if (!$task['is_lab'] && $this->isSubjectOnDay($groupGrid[$day], $task['subject_id'])) {
                    continue;
                }

                if ($task['is_lab']) {
                    foreach ($this->candidateLabPairs as $pair) {
                        [$pStart, $pEnd] = $pair;
                        if ($this->canPlace($day, $pStart, $pEnd, $task, $groupGrid, $externalReservations, $teacherDailyLoad, $teacherMaxLoad)) {
                            $this->commitPlacement($day, $pStart, $pEnd, $task, $groupGrid, $externalReservations, $teacherDailyLoad, $groupDailyClasses, $allocatedSlots);
                            $placed = true;
                            break 2;
                        }
                    }
                } else {
                    foreach ($this->periods as $p) {
                        $p = (int)$p;
                        if ($this->canPlace($day, $p, $p, $task, $groupGrid, $externalReservations, $teacherDailyLoad, $teacherMaxLoad)) {
                            $this->commitPlacement($day, $p, $p, $task, $groupGrid, $externalReservations, $teacherDailyLoad, $groupDailyClasses, $allocatedSlots);
                            $placed = true;
                            break 2;
                        }
                    }
                }
            }

            if (!$placed) {
                $conflictReason = $this->diagnoseFailure($task, $sortedDays, $externalReservations);
                $unallocatedTasks[] = array_merge($task, ['reason' => $conflictReason]);
            }
        }

        // 6. Persistence to Database
        if ($overwriteExisting) {
            $del = $this->db->prepare("DELETE FROM routines WHERE group_id = ? AND academic_year = ?");
            $del->execute([$groupId, $academicYear]);
        }

        $userId = $_SESSION['user_id'] ?? null;
        $savedCount = 0;

        foreach ($allocatedSlots as $slot) {
            $ins = $this->db->prepare("
                INSERT INTO routines (
                    academic_year, institute_id, department_id, technology_id, semester_id, shift_id,
                    group_id, subject_id, teacher_id, room_id, day, period_start, period_end,
                    class_type, load_count, is_lab, status, created_by
                ) VALUES (
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, 'PUBLISHED', ?
                )
            ");
            $classType = $slot['is_lab'] ? 'Lab' : 'Theory';
            $ins->execute([
                $academicYear, $group['institute_id'], $group['dept_id'], $group['tech_id'], $group['sem_id'], $group['shift_id'],
                $groupId, $slot['subject_id'], $slot['teacher_id'], $slot['room_id'], $slot['day'], $slot['period_start'], $slot['period_end'],
                $classType, $slot['load'], $slot['is_lab'] ? 1 : 0, $userId
            ]);
            $savedCount++;
        }

        logAudit('Auto Generate Routine', 'Generator', $groupId, "Generated {$savedCount} routine slots for {$group['short_code']}");

        return [
            'success' => true,
            'group' => $group,
            'total_tasks' => count($tasks),
            'allocated_count' => count($allocatedSlots),
            'unallocated_count' => count($unallocatedTasks),
            'allocated_slots' => $allocatedSlots,
            'unallocated_tasks' => $unallocatedTasks,
            'total_load_generated' => array_sum(array_column($allocatedSlots, 'load'))
        ];
    }

    private function canPlace(string $day, int $pStart, int $pEnd, array $task, array $groupGrid, array $external, array $teacherLoads, array $teacherMaxLoad): bool {
        // 1. Check Group Availability
        for ($p = $pStart; $p <= $pEnd; $p++) {
            if (isset($groupGrid[$day][$p])) {
                return false;
            }
        }

        // 2. Check Teacher Availability
        $tId = $task['teacher_id'];
        for ($p = $pStart; $p <= $pEnd; $p++) {
            if (isset($external['teachers'][$tId][$day][$p])) {
                return false;
            }
        }

        // 3. Check Room Availability
        $rId = $task['preferred_room_id'];
        for ($p = $pStart; $p <= $pEnd; $p++) {
            if (isset($external['rooms'][$rId][$day][$p])) {
                return false;
            }
        }

        // 4. Soft constraint: Teacher max daily load (this teacher's own
        // configured limit — falls back to 6 only if not set on their profile)
        $currentLoad = $teacherLoads[$tId][$day] ?? 0;
        $maxLoad = $teacherMaxLoad[$tId] ?? 6;
        if (($currentLoad + $task['load']) > $maxLoad) {
            return false;
        }

        return true;
    }

    private function commitPlacement(string $day, int $pStart, int $pEnd, array $task, array &$groupGrid, array &$external, array &$teacherLoads, array &$groupDailyClasses, array &$allocated): void {
        $tId = $task['teacher_id'];
        $rId = $task['preferred_room_id'];

        for ($p = $pStart; $p <= $pEnd; $p++) {
            $groupGrid[$day][$p] = $task;
            $external['teachers'][$tId][$day][$p] = true;
            $external['rooms'][$rId][$day][$p] = true;
        }

        $teacherLoads[$tId][$day] = ($teacherLoads[$tId][$day] ?? 0) + $task['load'];
        $groupDailyClasses[$day] += $task['load'];

        $allocated[] = [
            'subject_id' => $task['subject_id'],
            'subject_name' => $task['subject_name'],
            'subject_code' => $task['subject_code'],
            'teacher_id' => $tId,
            'room_id' => $rId,
            'day' => $day,
            'period_start' => $pStart,
            'period_end' => $pEnd,
            'is_lab' => $task['is_lab'],
            'load' => $task['load']
        ];
    }

    private function isSubjectOnDay(array $daySlots, int $subjectId): bool {
        foreach ($daySlots as $slot) {
            if (($slot['subject_id'] ?? 0) === $subjectId) {
                return true;
            }
        }
        return false;
    }

    private function loadExternalReservations(int $excludeGroupId, bool $overwrite, string $academicYear, int $shiftId): array {
        $reservations = [
            'teachers' => [],
            'rooms' => []
        ];

        // Restricted to this academic year AND this shift — otherwise a
        // teacher/room booked in a different year, or in the other shift
        // (which reuses the same period numbers at different clock times),
        // would incorrectly appear "busy" and block valid placements.
        $sql = "SELECT teacher_id, room_id, day, period_start, period_end FROM routines WHERE academic_year = ? AND shift_id = ?";
        $params = [$academicYear, $shiftId];
        if ($overwrite) {
            $sql .= " AND group_id != ?";
            $params[] = $excludeGroupId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        while ($row = $stmt->fetch()) {
            $t = (int)$row['teacher_id'];
            $r = (int)$row['room_id'];
            $d = $row['day'];
            $pS = (int)$row['period_start'];
            $pE = (int)$row['period_end'];

            for ($p = $pS; $p <= $pE; $p++) {
                $reservations['teachers'][$t][$d][$p] = true;
                $reservations['rooms'][$r][$d][$p] = true;
            }
        }

        return $reservations;
    }

    private function diagnoseFailure(array $task, array $days, array $external): string {
        return "Teacher or Preferred Room unavailable across all working days during required consecutive periods.";
    }
}