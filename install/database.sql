-- ====================================================================
-- Class Routine Management System (NasimSoft)
-- Complete Database Schema & Seed Data for MySQL 8+ / MariaDB 10.4+
-- Institution: Chapainawabganj Polytechnic Institute (CNPI)
-- Copyright (c) 2026 NasimSoft. All rights reserved.
-- ====================================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `website_settings`;
DROP TABLE IF EXISTS `routines`;
DROP TABLE IF EXISTS `signatures`;
DROP TABLE IF EXISTS `routine_headers`;
DROP TABLE IF EXISTS `group_subjects`;
DROP TABLE IF EXISTS `subjects`;
DROP TABLE IF EXISTS `class_monitors`;
DROP TABLE IF EXISTS `labs`;
DROP TABLE IF EXISTS `rooms`;
DROP TABLE IF EXISTS `teachers`;
DROP TABLE IF EXISTS `groups`;
DROP TABLE IF EXISTS `shifts`;
DROP TABLE IF EXISTS `semesters`;
DROP TABLE IF EXISTS `technologies`;
DROP TABLE IF EXISTS `departments`;
DROP TABLE IF EXISTS `institutes`;
DROP TABLE IF EXISTS `working_days`;
DROP TABLE IF EXISTS `periods`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------------------
-- 1. USERS TABLE (Compatible with password, password_hash, name, and full_name)
-- --------------------------------------------------------------------
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `institute_id` INT NULL,
    `username` VARCHAR(60) NOT NULL UNIQUE,
    `email` VARCHAR(120) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `password_hash` VARCHAR(255) NULL,
    `full_name` VARCHAR(120) NOT NULL,
    `name` VARCHAR(120) NULL,
    `phone` VARCHAR(30) NULL,
    `role` ENUM('SUPERADMIN', 'ADMIN', 'TEACHER', 'MONITOR', 'STUDENT') NOT NULL DEFAULT 'ADMIN',
    `status` ENUM('ACTIVE', 'INACTIVE', 'SUSPENDED') NOT NULL DEFAULT 'ACTIVE',
    `remember_token` VARCHAR(100) NULL,
    `last_login` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user_role` (`role`),
    INDEX `idx_user_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 2. INSTITUTES TABLE
-- --------------------------------------------------------------------
CREATE TABLE `institutes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `short_name` VARCHAR(50) NOT NULL,
    `code` VARCHAR(50) NULL,
    `address` TEXT NULL,
    `email` VARCHAR(120) NULL,
    `phone` VARCHAR(50) NULL,
    `logo_path` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 3. DEPARTMENTS TABLE
-- --------------------------------------------------------------------
CREATE TABLE `departments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `institute_id` INT NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `code` VARCHAR(30) NOT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`institute_id`) REFERENCES `institutes`(`id`) ON DELETE CASCADE,
    INDEX `idx_dept_institute` (`institute_id`),
    INDEX `idx_dept_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 4. TECHNOLOGIES TABLE
-- --------------------------------------------------------------------
CREATE TABLE `technologies` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `department_id` INT NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `code` VARCHAR(30) NOT NULL,
    `short_name` VARCHAR(30) NOT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE,
    INDEX `idx_tech_department` (`department_id`),
    INDEX `idx_tech_short_name` (`short_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 5. SEMESTERS TABLE
-- --------------------------------------------------------------------
CREATE TABLE `semesters` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL,
    `code` VARCHAR(20) NOT NULL,
    `numeric_level` INT NOT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_semester_level` (`numeric_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 6. SHIFTS TABLE
-- --------------------------------------------------------------------
CREATE TABLE `shifts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL,
    `code` VARCHAR(20) NOT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 7. GROUPS TABLE
-- --------------------------------------------------------------------
CREATE TABLE `groups` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `technology_id` INT NOT NULL,
    `semester_id` INT NOT NULL,
    `shift_id` INT NOT NULL,
    `group_name` VARCHAR(50) NOT NULL,
    `section` VARCHAR(20) DEFAULT '',
    `short_code` VARCHAR(50) NOT NULL,
    `student_count` INT DEFAULT 0,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`technology_id`) REFERENCES `technologies`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`shift_id`) REFERENCES `shifts`(`id`) ON DELETE CASCADE,
    INDEX `idx_grp_composite` (`technology_id`, `semester_id`, `shift_id`),
    INDEX `idx_grp_short_code` (`short_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 8. TEACHERS TABLE
-- --------------------------------------------------------------------
CREATE TABLE `teachers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `short_code` VARCHAR(30) NOT NULL UNIQUE,
    `designation` VARCHAR(100) NOT NULL,
    `department_id` INT NOT NULL,
    `phone` VARCHAR(50) NULL,
    `email` VARCHAR(120) NULL,
    `teacher_type` ENUM('Instructor', 'Jr. Instructor', 'Part-Time Teacher', 'Guest Teacher') NOT NULL DEFAULT 'Instructor',
    `max_daily_load` INT DEFAULT 6,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE,
    INDEX `idx_teacher_dept` (`department_id`),
    INDEX `idx_teacher_short` (`short_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 9. ROOMS TABLE
-- --------------------------------------------------------------------
CREATE TABLE `rooms` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `room_code` VARCHAR(50) NOT NULL UNIQUE,
    `room_type` ENUM('NORMAL', 'LAB') NOT NULL DEFAULT 'NORMAL',
    `capacity` INT NOT NULL DEFAULT 60,
    `department_id` INT NULL,
    `floor` VARCHAR(50) NULL,
    `building` VARCHAR(100) NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
    INDEX `idx_room_type` (`room_type`),
    INDEX `idx_room_code` (`room_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 10. LABS TABLE
-- --------------------------------------------------------------------
CREATE TABLE `labs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `room_id` INT NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `lab_code` VARCHAR(50) NOT NULL UNIQUE,
    `lab_type` VARCHAR(100) NULL,
    `capacity` INT NOT NULL DEFAULT 40,
    `department_id` INT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`room_id`) REFERENCES `rooms`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
    INDEX `idx_lab_room` (`room_id`),
    INDEX `idx_lab_code` (`lab_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 11. CLASS MONITORS TABLE
-- --------------------------------------------------------------------
CREATE TABLE `class_monitors` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `group_id` INT NOT NULL,
    `monitor_name` VARCHAR(120) NOT NULL,
    `student_roll` VARCHAR(50) NOT NULL,
    `phone` VARCHAR(50) NULL,
    `email` VARCHAR(120) NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE,
    INDEX `idx_monitor_group` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 12. SUBJECTS TABLE
-- --------------------------------------------------------------------
CREATE TABLE `subjects` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(200) NOT NULL,
    `subject_code` VARCHAR(50) NOT NULL UNIQUE,
    `subject_type` ENUM('Theory', 'Lab', 'Practical', 'Project') NOT NULL DEFAULT 'Theory',
    `theory_load` INT NOT NULL DEFAULT 0,
    `practical_load` INT NOT NULL DEFAULT 0,
    `total_load` INT NOT NULL DEFAULT 0,
    `credit` DECIMAL(3,1) NOT NULL DEFAULT 3.0,
    `department_id` INT NOT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE,
    INDEX `idx_subject_code` (`subject_code`),
    INDEX `idx_subject_dept` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 13. GROUP_SUBJECTS TABLE (Curriculum & Teacher Allocation)
-- --------------------------------------------------------------------
CREATE TABLE `group_subjects` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `group_id` INT NOT NULL,
    `subject_id` INT NOT NULL,
    `teacher_id` INT NULL,
    `preferred_room_id` INT NULL,
    `is_lab_required` TINYINT(1) NOT NULL DEFAULT 0,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`preferred_room_id`) REFERENCES `rooms`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `uk_group_subject` (`group_id`, `subject_id`),
    INDEX `idx_gs_group` (`group_id`),
    INDEX `idx_gs_subject` (`subject_id`),
    INDEX `idx_gs_teacher` (`teacher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 14. PERIODS TABLE
-- --------------------------------------------------------------------
CREATE TABLE `periods` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `period_number` INT NOT NULL,
    `period_name` VARCHAR(50) NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `shift_id` INT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`shift_id`) REFERENCES `shifts`(`id`) ON DELETE CASCADE,
    INDEX `idx_period_shift` (`shift_id`),
    INDEX `idx_period_num` (`period_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 15. WORKING_DAYS TABLE
-- --------------------------------------------------------------------
CREATE TABLE `working_days` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `day_name` VARCHAR(20) NOT NULL UNIQUE,
    `day_code` VARCHAR(10) NOT NULL UNIQUE,
    `day_order` INT NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_day_order` (`day_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 16. ROUTINE_HEADERS TABLE
-- --------------------------------------------------------------------
CREATE TABLE `routine_headers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `institute_id` INT NOT NULL,
    `routine_title` VARCHAR(200) NOT NULL,
    `academic_year` VARCHAR(20) NOT NULL DEFAULT '2026',
    `department_id` INT NULL,
    `technology_id` INT NULL,
    `semester_id` INT NULL,
    `shift_id` INT NULL,
    `group_id` INT NULL,
    `total_load` INT NOT NULL DEFAULT 0,
    `logo_path` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`institute_id`) REFERENCES `institutes`(`id`) ON DELETE CASCADE,
    INDEX `idx_rh_group` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 17. SIGNATURES TABLE
-- --------------------------------------------------------------------
CREATE TABLE `signatures` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(100) NOT NULL,
    `person_name` VARCHAR(150) NULL,
    `designation` VARCHAR(150) NULL,
    `signature_image` VARCHAR(255) NULL,
    `display_order` INT NOT NULL DEFAULT 1,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_sig_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 17b. GENERATED DOCUMENTS TABLE (Document Center — saved/shared PDFs)
-- --------------------------------------------------------------------
CREATE TABLE `generated_documents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `doc_type` ENUM('group','teacher','room','bulk') NOT NULL,
    `entity_id` INT NULL,
    `entity_label` VARCHAR(150) NOT NULL,
    `shift_id` INT NULL,
    `academic_year` VARCHAR(20) NOT NULL,
    `status` ENUM('draft','final') NOT NULL DEFAULT 'final',
    `file_path` VARCHAR(255) NULL,
    `share_token` VARCHAR(40) NOT NULL UNIQUE,
    `download_count` INT NOT NULL DEFAULT 0,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_doc_type` (`doc_type`),
    INDEX `idx_share_token` (`share_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 18. ROUTINES TABLE
-- --------------------------------------------------------------------
CREATE TABLE `routines` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `academic_year` VARCHAR(20) NOT NULL DEFAULT '2026',
    `institute_id` INT NOT NULL,
    `department_id` INT NOT NULL,
    `technology_id` INT NOT NULL,
    `semester_id` INT NOT NULL,
    `shift_id` INT NOT NULL,
    `group_id` INT NOT NULL,
    `subject_id` INT NOT NULL,
    `teacher_id` INT NOT NULL,
    `room_id` INT NOT NULL,
    `day` VARCHAR(20) NOT NULL,
    `period_start` INT NOT NULL,
    `period_end` INT NOT NULL,
    `class_type` ENUM('Theory', 'Lab', 'Practical', 'Project') NOT NULL DEFAULT 'Theory',
    `load_count` INT NOT NULL DEFAULT 1,
    `is_lab` TINYINT(1) NOT NULL DEFAULT 0,
    `status` ENUM('DRAFT', 'VALIDATED', 'PUBLISHED', 'HIDDEN') NOT NULL DEFAULT 'PUBLISHED',
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`institute_id`) REFERENCES `institutes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`technology_id`) REFERENCES `technologies`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`shift_id`) REFERENCES `shifts`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`room_id`) REFERENCES `rooms`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_rt_group_day` (`group_id`, `day`, `period_start`, `period_end`),
    INDEX `idx_rt_teacher_day` (`teacher_id`, `day`, `period_start`, `period_end`),
    INDEX `idx_rt_room_day` (`room_id`, `day`, `period_start`, `period_end`),
    INDEX `idx_rt_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 19. WEBSITE_SETTINGS TABLE
-- --------------------------------------------------------------------
CREATE TABLE `website_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 20. AUDIT_LOGS TABLE
-- --------------------------------------------------------------------
CREATE TABLE `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `action` VARCHAR(100) NOT NULL,
    `module` VARCHAR(100) NOT NULL,
    `record_id` INT NULL,
    `details` TEXT NULL,
    `ip_address` VARCHAR(50) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_audit_user` (`user_id`),
    INDEX `idx_audit_module` (`module`),
    INDEX `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- INITIAL SYSTEM SEED DATA
-- ====================================================================

-- Default Superadmin: superadmin / admin123
INSERT INTO `users` (`id`, `username`, `email`, `password`, `password_hash`, `full_name`, `name`, `role`, `status`) VALUES 
(1, 'superadmin', 'admin@nasimsoft.com', '$2y$10$e846V0zT2V4N8Yt/7s7.yO5eWq7z7Q3rL/Q5K3wU6Qk7xGvO8ZfGy', '$2y$10$e846V0zT2V4N8Yt/7s7.yO5eWq7z7Q3rL/Q5K3wU6Qk7xGvO8ZfGy', 'Super Administrator', 'Super Administrator', 'SUPERADMIN', 'ACTIVE');

-- Institute Info
INSERT INTO `institutes` (`id`, `name`, `short_name`, `code`, `address`, `email`, `phone`) VALUES 
(1, 'Chapainawabganj Polytechnic Institute', 'CNPI', 'CNPI-13137', 'Baroghoria, Chapainawabganj, Rajshahi', 'principal@cnpi.edu.bd', '01700000000');

-- Working Days (Sunday-Thursday Active in Bangladesh)
INSERT INTO `working_days` (`day_name`, `day_code`, `day_order`, `is_active`) VALUES
('Sunday', 'SUN', 1, 1),
('Monday', 'MON', 2, 1),
('Tuesday', 'TUE', 3, 1),
('Wednesday', 'WED', 4, 1),
('Thursday', 'THU', 5, 1),
('Friday', 'FRI', 6, 0),
('Saturday', 'SAT', 7, 0);

-- Shifts
INSERT INTO `shifts` (`id`, `name`, `code`, `active`) VALUES
(1, 'First Shift', '1st', 1),
(2, 'Second Shift', '2nd', 1);

-- Default Periods (1st Shift)
INSERT INTO `periods` (`period_number`, `period_name`, `start_time`, `end_time`, `shift_id`, `active`) VALUES
(1, '1st Period', '08:00:00', '08:45:00', 1, 1),
(2, '2nd Period', '08:45:00', '09:30:00', 1, 1),
(3, '3rd Period', '09:30:00', '10:15:00', 1, 1),
(4, '4th Period', '10:15:00', '11:00:00', 1, 1),
(5, '5th Period', '11:00:00', '11:45:00', 1, 1),
(6, '6th Period', '11:45:00', '12:30:00', 1, 1),
(7, '7th Period', '12:30:00', '13:15:00', 1, 1);

-- Default Periods (2nd Shift)
INSERT INTO `periods` (`period_number`, `period_name`, `start_time`, `end_time`, `shift_id`, `active`) VALUES
(1, '1st Period', '13:15:00', '14:00:00', 2, 1),
(2, '2nd Period', '14:00:00', '14:45:00', 2, 1),
(3, '3rd Period', '14:45:00', '15:30:00', 2, 1),
(4, '4th Period', '15:30:00', '16:15:00', 2, 1),
(5, '5th Period', '16:15:00', '17:00:00', 2, 1),
(6, '6th Period', '17:00:00', '17:45:00', 2, 1),
(7, '7th Period', '17:45:00', '18:30:00', 2, 1);

-- Semesters
INSERT INTO `semesters` (`id`, `name`, `code`, `numeric_level`, `active`) VALUES
(1, 'First Semester', '1st', 1, 1),
(2, 'Second Semester', '2nd', 2, 1),
(3, 'Third Semester', '3rd', 3, 1),
(4, 'Fourth Semester', '4th', 4, 1),
(5, 'Fifth Semester', '5th', 5, 1),
(6, 'Sixth Semester', '6th', 6, 1),
(7, 'Seventh Semester', '7th', 7, 1),
(8, 'Eighth Semester', '8th', 8, 1);

-- Departments
INSERT INTO `departments` (`id`, `institute_id`, `name`, `code`, `active`) VALUES
(1, 1, 'Computer Science & Technology', 'CST', 1),
(2, 1, 'Electrical Technology', 'ET', 1),
(3, 1, 'Electronics Technology', 'ENT', 1),
(4, 1, 'Civil Technology', 'CT', 1),
(5, 1, 'Food Technology', 'FT', 1),
(6, 1, 'Mechanical Technology', 'MT', 1),
(7, 1, 'Refrigeration & Air Conditioning', 'RAC', 1);

-- Technologies
INSERT INTO `technologies` (`id`, `department_id`, `name`, `code`, `short_name`, `active`) VALUES
(1, 1, 'Computer Science & Technology', '85', 'CST', 1),
(2, 2, 'Electrical Technology', '67', 'ET', 1),
(3, 5, 'Food Technology', '69', 'FT', 1);

-- Academic Groups
INSERT INTO `groups` (`id`, `technology_id`, `semester_id`, `shift_id`, `group_name`, `section`, `short_code`, `student_count`, `active`) VALUES
(1, 1, 7, 1, 'Group 1', 'A', 'CST 7/1', 50, 1),
(2, 2, 5, 1, 'Group 1', 'A', 'ET 5/1 (A)', 48, 1),
(3, 3, 4, 1, 'Group 1', 'A', 'FT 4/1', 45, 1);

-- Teachers (Including Food Technology & Computer Faculty)
INSERT INTO `teachers` (`id`, `name`, `short_code`, `designation`, `department_id`, `phone`, `email`, `teacher_type`, `max_daily_load`, `active`) VALUES
(1, 'MD OMAR FARUK', 'OF', 'Jr. Instructor (Tech/Computer)', 1, '01750787890', 'omarfaruk@cnpi.edu.bd', 'Jr. Instructor', 6, 1),
(2, 'MD. JOSIM UDDIN BAKIULLAH', 'JUB', 'Instructor (Tech/Computer)', 1, '01712000000', 'jub@cnpi.edu.bd', 'Instructor', 6, 1),
(3, 'MD. RASHEDUL ALAM', 'RA', 'Jr. Instructor (Tech/Computer)', 1, '01713000000', 'ra@cnpi.edu.bd', 'Jr. Instructor', 6, 1),
(4, 'MD. IMRAN HOSSAIN', 'IH', 'Jr. Instructor (Tech/Computer)', 1, '01714000000', 'ih@cnpi.edu.bd', 'Jr. Instructor', 6, 1),
(5, 'NASIM AL MASUD', 'NAM', 'Jr. Instructor (Tech/Food)', 5, '01715000000', 'nasim@cnpi.edu.bd', 'Jr. Instructor', 6, 1);

-- Rooms & Labs
INSERT INTO `rooms` (`id`, `name`, `room_code`, `room_type`, `capacity`, `department_id`, `floor`, `building`, `active`) VALUES
(1, 'Room S-315', 'S-315', 'NORMAL', 60, 1, '3rd Floor', 'South Building', 1),
(2, 'Room S-316', 'S-316', 'NORMAL', 60, 1, '3rd Floor', 'South Building', 1),
(3, 'Room S-418', 'S-418', 'NORMAL', 60, 1, '4th Floor', 'South Building', 1),
(4, 'Room N-210', 'N-210', 'NORMAL', 60, 2, '2nd Floor', 'North Building', 1),
(5, 'Room N-211', 'N-211', 'NORMAL', 60, 2, '2nd Floor', 'North Building', 1),
(6, 'Network Lab', 'NET-LAB', 'LAB', 45, 1, '3rd Floor', 'South Building', 1),
(7, 'Software & Apps Lab', 'SOFT-LAB', 'LAB', 45, 1, '3rd Floor', 'South Building', 1),
(8, 'Food Processing Lab', 'FOOD-LAB', 'LAB', 40, 5, 'Ground Floor', 'Main Building', 1),
(9, 'Electrical Lab', 'ELEC-LAB', 'LAB', 45, 2, '1st Floor', 'North Building', 1);

INSERT INTO `labs` (`id`, `room_id`, `name`, `lab_code`, `lab_type`, `capacity`, `department_id`, `active`) VALUES
(1, 6, 'Network Lab', 'LAB-NET', 'Hardware & Networking', 45, 1, 1),
(2, 7, 'Software & Apps Lab', 'LAB-SOFT', 'Programming & Software Development', 45, 1, 1),
(3, 8, 'Food Processing Lab', 'LAB-FOOD', 'Food Technology Testing & Processing', 40, 5, 1),
(4, 9, 'Electrical Machine Lab', 'LAB-ELEC', 'Power & Electrical Circuits', 45, 2, 1);

-- Subjects for CST 7th Semester
INSERT INTO `subjects` (`id`, `name`, `subject_code`, `subject_type`, `theory_load`, `practical_load`, `total_load`, `credit`, `department_id`, `active`) VALUES
(1, 'Digital Marketing Technique', '28571', 'Theory', 2, 0, 2, 2.0, 1, 1),
(2, 'Network Administration & Services', '28572', 'Lab', 2, 2, 4, 3.0, 1, 1),
(3, 'Cyber Security & Ethics', '28573', 'Theory', 2, 0, 2, 2.0, 1, 1),
(4, 'Apps Development Project', '28574', 'Lab', 1, 2, 3, 2.0, 1, 1),
(5, 'Multimedia & Animation', '28575', 'Lab', 2, 2, 4, 3.0, 1, 1),
(6, 'Project Work-II', '28576', 'Project', 0, 4, 4, 2.0, 1, 1),
(7, 'Innovation & Entrepreneurship', '25853', 'Theory', 2, 0, 2, 2.0, 1, 1);

-- Group Subjects (Curriculum for CST 7/1)
INSERT INTO `group_subjects` (`group_id`, `subject_id`, `teacher_id`, `preferred_room_id`, `is_lab_required`, `active`) VALUES
(1, 1, 2, 2, 0, 1),
(1, 2, 1, 6, 1, 1),
(1, 3, 3, 3, 0, 1),
(1, 4, 4, 7, 1, 1),
(1, 5, 1, 7, 1, 1),
(1, 6, 2, 7, 1, 1),
(1, 7, 3, 1, 0, 1);

-- Default Signatures
INSERT INTO `signatures` (`id`, `title`, `person_name`, `designation`, `display_order`, `is_active`) VALUES
(1, 'Chairman Routine Committee', 'Md. Josim Uddin Bakiullah', 'Chairman, Routine Committee', 1, 1),
(2, 'Vice-Principal', 'Engr. Md. Abdur Razzak', 'Vice-Principal', 2, 1),
(3, 'Principal', 'Engr. A. K. M. Mokhlesur Rahman', 'Principal, CNPI', 3, 1);

-- Website Settings
INSERT INTO `website_settings` (`setting_key`, `setting_value`) VALUES
('site_name', 'Class Routine Management System'),
('site_brand', 'NasimSoft'),
('copyright_text', '© Class Routine Management System 2026, Developed by NasimSoft.'),
('academic_year', '2026'),
('show_public_homepage', '1'),
('public_routine_status', '1'),
('public_department_view', '1'),
('public_semester_view', '1'),
('public_group_view', '1'),
('public_teacher_view', '1'),
('public_room_view', '1'),
('public_lab_view', '1'),
('public_monitor_view', '1'),
('institute_name', 'Chapainawabganj Polytechnic Institute'),
('institute_address', 'Baroghoria, Chapainawabganj, Rajshahi'),
('institute_email', 'principal@cnpi.edu.bd'),
('institute_phone', '01700-000000'),
('theme_primary_color', '#123FA6'),
('theme_accent_color', '#1FA855'),
('site_short_name', 'CNPI'),
('site_tagline', 'Online Class Routine Management System'),
('site_logo_url', ''),
('govt_logo_url', ''),
('home_hero_style', 'gradient'),
('home_hero_title', 'Student & Public Routine Portal'),
('home_hero_subtitle', 'Access real-time schedules, faculty assignments, and laboratory room allocations.'),
('home_announcement', ''),
('home_show_login_button', '1'),
('print_title', ''),
('print_subtitle', 'CLASS TIMETABLE'),
('print_font', 'sans'),
('print_show_logo', '0'),
('print_show_govt_logo', '0'),
('print_show_generated_on', '0'),
('print_footer_note', ''),
('print_show_signatures', '1');

-- ============================================================================
-- Class Teacher reference on `groups` (shown on the printed routine document,
-- e.g. "Class Teacher: Md. Rezuanul Arefin, Instructor, CST"). Added after
-- the `teachers` table so the foreign key can be created inline.
-- ============================================================================
ALTER TABLE `groups`
    ADD COLUMN `class_teacher_id` INT NULL AFTER `student_count`,
    ADD CONSTRAINT `fk_groups_class_teacher` FOREIGN KEY (`class_teacher_id`) REFERENCES `teachers`(`id`) ON DELETE SET NULL;