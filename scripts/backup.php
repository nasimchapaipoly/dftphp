<?php
/**
 * Class Routine Management System (NasimSoft)
 * Automated Native Database Backup Generator
 * Chapainawabganj Polytechnic Institute (CNPI)
 * Copyright (c) 2026 NasimSoft. All rights reserved.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

// CLI execution or SuperAdmin session verification
if (php_sapi_name() !== 'cli') {
    Auth::requireSuperAdmin();
}

$db = Database::getInstance();
$backupDir = __DIR__ . '/../backups';

if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0750, true);
    // Write .htaccess to prevent HTTP direct downloads
    @file_put_contents($backupDir . '/.htaccess', "Deny from all\n");
}

$timestamp = date('Y-m-d_H-i-s');
$backupFileName = "cnpi_routine_backup_{$timestamp}.sql";
$backupFilePath = $backupDir . '/' . $backupFileName;

$tables = [];
$tblQuery = $db->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
while ($row = $tblQuery->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}

$handle = fopen($backupFilePath, 'w');
if (!$handle) {
    die("Error: Unable to initialize backup file at {$backupFilePath}\n");
}

fwrite($handle, "-- ========================================================\n");
fwrite($handle, "-- NasimSoft Routine Management System Backup\n");
fwrite($handle, "-- Institution: Chapainawabganj Polytechnic Institute\n");
fwrite($handle, "-- Generation Date: " . date('Y-m-d H:i:s') . "\n");
fwrite($handle, "-- ========================================================\n\n");
fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

foreach ($tables as $table) {
    // Structure
    $createStmt = $db->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
    fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
    fwrite($handle, $createStmt[1] . ";\n\n");

    // Records
    $rows = $db->query("SELECT * FROM `{$table}`");
    $columnCount = $rows->columnCount();

    while ($row = $rows->fetch(PDO::FETCH_NUM)) {
        fwrite($handle, "INSERT INTO `{$table}` VALUES(");
        for ($j = 0; $j < $columnCount; $j++) {
            if (isset($row[$j])) {
                fwrite($handle, $db->quote($row[$j]));
            } else {
                fwrite($handle, 'NULL');
            }
            if ($j < ($columnCount - 1)) {
                fwrite($handle, ',');
            }
        }
        fwrite($handle, ");\n");
    }
    fwrite($handle, "\n");
}

fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
fclose($handle);

// Audit logging
if (function_exists('logAudit')) {
    logAudit('Database Backup', 'System', 0, "Created automated backup file {$backupFileName} (" . round(filesize($backupFilePath) / 1024, 2) . " KB)");
}

$msg = "Backup successfully generated: {$backupFilePath} (" . round(filesize($backupFilePath) / 1024, 2) . " KB)\n";

if (php_sapi_name() === 'cli') {
    echo $msg;
} else {
    echo "<div style='font-family: Times New Roman, Times, serif; padding: 20px; background: #F0FDF4; border: 1px solid #BBF7D0; color: #166534; border-radius: 8px;'>
            <strong>&#10004; Success:</strong> {$msg}
            <br><br><a href='../admin/dashboard.php' style='color: #1E3A8A; font-weight: bold;'>&larr; Return to Dashboard</a>
          </div>";
}