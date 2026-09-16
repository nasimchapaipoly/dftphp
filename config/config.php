<?php
/**
 * Class Routine Management System (NasimSoft)
 * Application Configuration
 * Copyright (c) 2026 NasimSoft.
 */

declare(strict_types=1);

if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}

// Database Credentials are defined in config/database.php (loaded below)
// to avoid overriding the real production values with placeholders here.

// Application Information
define('APP_NAME', 'Class Routine Management System');
define('APP_BRAND', 'NasimSoft');
define('APP_YEAR', '2026');
define('APP_VERSION', '1.0.0');
define('APP_COPYRIGHT', '© Class Routine Management System 2026, Developed by NasimSoft.');

// Timezone and Locale
date_default_timezone_set('Asia/Dhaka');

// Session Settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

// Base URL Auto-Detection
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$appBase = rtrim($scriptDir, '/');

// Adjust if inside /admin, /public, /api, /install
$appBase = preg_replace('/\/(admin|public|api|install)$/', '', $appBase);
define('BASE_URL', $protocol . $host . $appBase);
define('ADMIN_URL', BASE_URL . '/admin');
define('PUBLIC_URL', BASE_URL . '/public');
define('ASSETS_URL', BASE_URL . '/assets');

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';