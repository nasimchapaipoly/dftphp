-- ============================================================================
-- Class Routine Management System (NasimSoft)
-- Migration: Public Home Page + Print Document customisation settings
--
-- Safe to run on an already-installed database — it only INSERTs keys that
-- do not exist yet (INSERT IGNORE against the UNIQUE setting_key column).
-- Run once via phpMyAdmin / mysql CLI after upgrading the application files.
-- ============================================================================

INSERT IGNORE INTO `website_settings` (`setting_key`, `setting_value`) VALUES
('site_short_name',          'CNPI'),
('site_tagline',             'Online Class Routine Management System'),
('site_logo_url',            ''),
('govt_logo_url',            ''),

('home_hero_style',          'gradient'),
('home_hero_title',          'Student & Public Routine Portal'),
('home_hero_subtitle',       'Access real-time schedules, faculty assignments, and laboratory room allocations.'),
('home_announcement',        ''),
('home_show_login_button',   '1'),

('print_title',              ''),
('print_subtitle',           'CLASS TIMETABLE'),
('print_font',               'sans'),
('print_show_logo',          '0'),
('print_show_govt_logo',     '0'),
('print_show_generated_on',  '0'),
('print_footer_note',        ''),
('print_show_signatures',    '1');

-- ----------------------------------------------------------------------------
-- Class Teacher reference on `groups` (printed on the routine document).
-- MySQL has no "ADD COLUMN IF NOT EXISTS" before 8.0.29, so this uses a
-- prepared-statement guard that is safe to run repeatedly.
-- ----------------------------------------------------------------------------
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'groups' AND COLUMN_NAME = 'class_teacher_id'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `groups` ADD COLUMN `class_teacher_id` INT NULL AFTER `student_count`, ADD CONSTRAINT `fk_groups_class_teacher` FOREIGN KEY (`class_teacher_id`) REFERENCES `teachers`(`id`) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------------------------
-- Re-brand to match the NasimSoft logo (blue → green). This OVERWRITES any
-- previously saved primary/accent colors — if you have already customised
-- these from Admin → Site & Print Settings, skip these two lines or update
-- the color pickers there afterwards instead.
-- ----------------------------------------------------------------------------
UPDATE `website_settings` SET `setting_value` = '#123FA6' WHERE `setting_key` = 'theme_primary_color';
UPDATE `website_settings` SET `setting_value` = '#1FA855' WHERE `setting_key` = 'theme_accent_color';

-- ----------------------------------------------------------------------------
-- Second Shift periods (7 periods, right after First Shift ends at 1:15 PM).
-- Only inserted if shift_id=2 has no periods yet, so this is safe to re-run.
-- ----------------------------------------------------------------------------
INSERT INTO `periods` (`period_number`, `period_name`, `start_time`, `end_time`, `shift_id`, `active`)
SELECT * FROM (
    SELECT 1, '1st Period', '13:15:00', '14:00:00', 2, 1 UNION ALL
    SELECT 2, '2nd Period', '14:00:00', '14:45:00', 2, 1 UNION ALL
    SELECT 3, '3rd Period', '14:45:00', '15:30:00', 2, 1 UNION ALL
    SELECT 4, '4th Period', '15:30:00', '16:15:00', 2, 1 UNION ALL
    SELECT 5, '5th Period', '16:15:00', '17:00:00', 2, 1 UNION ALL
    SELECT 6, '6th Period', '17:00:00', '17:45:00', 2, 1 UNION ALL
    SELECT 7, '7th Period', '17:45:00', '18:30:00', 2, 1
) AS seed_data
WHERE NOT EXISTS (SELECT 1 FROM `periods` WHERE `shift_id` = 2);

-- ----------------------------------------------------------------------------
-- Document Center (Save / Share / Draft-Final PDF documents). Safe to re-run —
-- only created if it doesn't already exist.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `generated_documents` (
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
