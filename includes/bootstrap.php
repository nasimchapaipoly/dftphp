<?php
/**
 * Global Admin Bootstrap — session, config, auth guard, and shared helpers.
 * Class Routine Management System - NasimSoft
 *
 * IMPORTANT: This file produces NO HTML output. Any admin page that needs to
 * redirect (header('Location: ...')) after handling a POST/GET action MUST
 * require this file first, run its action handling, and only require
 * includes/header.php (which prints the page shell) afterwards. Requiring
 * header.php before an action handler is what causes "success message only
 * shows after a manual refresh" — the HTML header.php already streamed to
 * the browser makes header('Location: ...') fail silently.
 */

// 1. Ensure system constant is defined immediately
if (!defined('CRMS_SYSTEM')) {
    define('CRMS_SYSTEM', true);
}

// 2. Load full application config (starts the session, connects the
//    database, defines BASE_URL/ADMIN_URL/PUBLIC_URL/ASSETS_URL, and
//    pulls in includes/functions.php + includes/auth.php).
$configFile = file_exists(__DIR__ . '/../config/config.php')
    ? __DIR__ . '/../config/config.php'
    : __DIR__ . '/config/config.php';

require_once $configFile;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Auth guard
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!class_exists('Database')) {
    class Database {
        public static function getInstance(): PDO {
            return getDB();
        }
    }
}

// 3b. Load shared helper functions (sanitize, CSRF tokens, flash messages, e(), etc.)
if (!function_exists('generateCsrfToken')) {
    require_once __DIR__ . '/functions.php';
}

// 3c. Load Auth class (needed by every admin page for access control)
if (!class_exists('Auth')) {
    require_once __DIR__ . '/auth.php';
}

$userName = $_SESSION['user_name'] ?? ($_SESSION['full_name'] ?? 'Administrator');
$userRole = $_SESSION['user_role'] ?? 'SUPERADMIN';

// Fallback logAudit() in case functions.php didn't load it for some reason
if (!function_exists('logAudit')) {
function logAudit(string $action, string $module, int $recordId, string $details = ''): void {
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, module, record_id, details, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $action, $module, $recordId, $details, $ip]);
    } catch (Exception $e) {}
}
}

// Small HTML-escaping helper used throughout the admin views
if (!function_exists('e')) {
    function e(?string $value): string {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// Branding settings (customisable from admin/settings.php). These same keys
// also drive the public portal + printable routine.
$brandName    = getSetting('site_short_name', 'CR');
$brandFull    = getSetting('institute_name', 'Chapainawabganj Polytechnic Institute');
$brandPrimary = getSetting('theme_primary_color', '#123FA6');
$brandAccent  = getSetting('theme_accent_color', '#1FA855');
$assetsUrl    = defined('ASSETS_URL') ? ASSETS_URL : '../assets';

