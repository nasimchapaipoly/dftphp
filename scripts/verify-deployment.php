<?php
/**
 * Class Routine Management System (NasimSoft)
 * Pre-Flight Deployment Verification & Diagnostics
 * Copyright (c) 2026 NasimSoft. All rights reserved.
 */

declare(strict_types=1);

$checks = [];

function recordCheck(string $label, bool $passed, string $details = ''): void {
    global $checks;
    $checks[] = ['label' => $label, 'passed' => $passed, 'details' => $details];
}

// 1. PHP Version
$phpVersion = PHP_VERSION;
recordCheck("PHP Version (>= 8.1.0)", version_compare($phpVersion, '8.1.0', '>='), "Current: {$phpVersion}");

// 2. Critical Extensions
$requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'session'];
foreach ($requiredExtensions as $ext) {
    recordCheck("PHP Extension: {$ext}", extension_loaded($ext), extension_loaded($ext) ? 'Loaded' : 'Missing');
}

// 3. Directory Write Permissions
$writableDirs = [
    'backups' => __DIR__ . '/../backups',
    'logs'    => __DIR__ . '/../logs',
];
foreach ($writableDirs as $name => $path) {
    if (!is_dir($path)) {
        @mkdir($path, 0750, true);
    }
    $isWritable = is_writable($path);
    recordCheck("Writable Directory: {$name}/", $isWritable, $isWritable ? 'Writable' : 'Permission Denied');
}

// 4. Database Connectivity & Essential Tables
try {
    require_once __DIR__ . '/../config/config.php';
    $db = Database::getInstance();
    recordCheck("Database Connection", true, "Connected to MySQL successfully");

    $expectedTables = [
        'users', 'institutes', 'departments', 'technologies', 'semesters',
        'shifts', 'groups', 'teachers', 'rooms', 'subjects', 'group_subjects',
        'periods', 'working_days', 'routines', 'audit_logs'
    ];
    $existingTables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($expectedTables as $t) {
        $exists = in_array($t, $existingTables, true);
        recordCheck("Database Table: {$t}", $exists, $exists ? 'Present' : 'Missing Table');
    }
} catch (Throwable $e) {
    recordCheck("Database Connection", false, $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Production Pre-Flight Verification - NasimSoft</title>
    <style>
        body { font-family: 'Times New Roman', Times, serif; background: #F8FAFC; color: #1E293B; padding: 30px; }
        .box { max-width: 800px; margin: 0 auto; background: #FFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.04); }
        h2 { color: #1E3A8A; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px 14px; border-bottom: 1px solid #E2E8F0; font-size: 13.5px; text-align: left; }
        th { background: #F1F5F9; color: #1E3A8A; font-weight: 700; }
        .pass { color: #15803D; font-weight: bold; }
        .fail { color: #B91C1C; font-weight: bold; }
    </style>
</head>
<body>
    <div class="box">
        <h2>&#128269; Production Pre-Flight Checklist & System Diagnostics</h2>
        <p style="font-size: 13.5px; color: #64748B;">Chapainawabganj Polytechnic Institute (CNPI) &bull; Class Routine Management System</p>
        
        <table>
            <thead>
                <tr>
                    <th>Check Item</th>
                    <th>Status</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($checks as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['label']) ?></td>
                        <td class="<?= $c['passed'] ? 'pass' : 'fail' ?>">
                            <?= $c['passed'] ? '&#10004; PASSED' : '&#10008; FAILED' ?>
                        </td>
                        <td style="color: #64748B; font-size: 12.5px;"><?= htmlspecialchars($c['details']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top: 24px; display: flex; justify-content: space-between; align-items: center;">
            <a href="../index.php" style="color: #1E3A8A; font-weight: bold; text-decoration: none;">&larr; Open Public Portal</a>
            <a href="../login.php" style="background: #1E3A8A; color: #FFF; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: bold;">Access Admin Console &rarr;</a>
        </div>
    </div>
</body>
</html>