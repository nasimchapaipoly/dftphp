-- =========================================================================
-- Class Routine Management System (NasimSoft)
-- Performance Optimization & Composite Index Migration
-- Chapainawabganj Polytechnic Institute (CNPI)
-- =========================================================================

-- 1. Accelerate Conflict Checks (Teacher, Room, and Group collision checks)
ALTER TABLE `routines` 
    ADD INDEX `idx_teacher_schedule` (`teacher_id`, `day`, `period_start`, `period_end`),
    ADD INDEX `idx_room_schedule` (`room_id`, `day`, `period_start`, `period_end`),
    ADD INDEX `idx_group_schedule` (`group_id`, `day`, `period_start`, `period_end`),
    ADD INDEX `idx_lookup_academic` (`academic_year`, `status`, `group_id`);

-- 2. Accelerate Group Curriculum Resolution
ALTER TABLE `group_subjects`
    ADD INDEX `idx_group_active_curriculum` (`group_id`, `active`),
    ADD INDEX `idx_group_teacher_lookup` (`teacher_id`, `subject_id`);

-- 3. Accelerate Hierarchy Navigation
ALTER TABLE `groups`
    ADD INDEX `idx_group_hierarchy` (`technology_id`, `semester_id`, `shift_id`, `active`);

ALTER TABLE `technologies`
    ADD INDEX `idx_tech_department` (`department_id`, `active`);

ALTER TABLE `teachers`
    ADD INDEX `idx_teacher_dept` (`department_id`, `active`);

-- 4. Audit Log Retrieval & Search Optimization
ALTER TABLE `audit_logs`
    ADD INDEX `idx_audit_search` (`action`, `created_at`),
    ADD INDEX `idx_audit_user` (`user_id`, `created_at`);