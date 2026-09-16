<?php
/**
 * Admin Login Page - Bulletproof Edition
 * Class Routine Management System - NasimSoft
 */

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();

// If already logged in, redirect to dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Locate config/config.php (also loads database.php, functions.php, auth.php)
$configFile = file_exists(__DIR__ . '/../config/config.php')
    ? __DIR__ . '/../config/config.php'
    : __DIR__ . '/config/config.php';

if (!file_exists($configFile)) {
    die('<div style="text-align:center;padding:50px;font-family:Times New Roman,Times,serif;">
            <h2>Configuration Missing</h2>
            <p>Please run the installer first: <a href="../install/install.php">Run Installer</a></p>
         </div>');
}

require_once $configFile;

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginInput = trim($_POST['email'] ?? ($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (empty($loginInput) || empty($password)) {
        $error = 'Please enter your username/email and password.';
    } else {
        try {
            // Get database connection
            if (function_exists('getDB')) {
                $pdo = getDB();
            } else {
                $pdo = new PDO(
                    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                    DB_USER,
                    DB_PASS,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
                );
            }

            // Look up user by username or email
            $stmt = $pdo->prepare("SELECT * FROM users WHERE (email = ? OR username = ?) LIMIT 1");
            $stmt->execute([$loginInput, $loginInput]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = 'User account not found. Please check your username or email.';
            } else {
                // Check status
                if (!empty($user['status']) && $user['status'] !== 'ACTIVE') {
                    $error = 'Your account is currently inactive or suspended.';
                } else {
                    // Try password or password_hash
                    $storedHash = $user['password'] ?? ($user['password_hash'] ?? '');

                    if (!empty($storedHash) && password_verify($password, $storedHash)) {
                        // Successful login: Set session
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'] ?? 'admin';
                        $_SESSION['user_name'] = $user['full_name'] ?? ($user['name'] ?? $user['username']);
                        $_SESSION['user_role'] = $user['role'] ?? 'SUPERADMIN';
                        $_SESSION['user_email'] = $user['email'] ?? '';

                        // Update last login (wrapped in try/catch so failure doesn't block login)
                        try {
                            $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                        } catch (Exception $e) {}

                        // Update audit log if table exists
                        try {
                            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                            $pdo->prepare("INSERT INTO audit_logs (user_id, action, module, details, ip_address, created_at) VALUES (?, 'Login', 'Auth', 'User logged in successfully', ?, NOW())")
                                ->execute([$user['id'], $ip]);
                        } catch (Exception $e) {}

                        // Redirect to Dashboard
                        header('Location: dashboard.php');
                        exit;
                    } else {
                        $error = 'Incorrect password. Please try again.';
                    }
                }
            }
        } catch (Throwable $e) {
            $error = 'Login error: ' . $e->getMessage();
        }
    }
}
// Branding (customisable from Admin → Site & Print Settings)
$siteName    = function_exists('getSetting') ? getSetting('institute_name', 'Chapainawabganj Polytechnic Institute') : 'Chapainawabganj Polytechnic Institute';
$siteShort   = function_exists('getSetting') ? getSetting('site_short_name', 'CR') : 'CR';
$siteLogo    = function_exists('getSetting') ? getSetting('site_logo_url', '') : '';
$primary     = function_exists('getSetting') ? getSetting('theme_primary_color', '#123FA6') : '#123FA6';
$accent      = function_exists('getSetting') ? getSetting('theme_accent_color', '#1FA855') : '#1FA855';
$assetsUrl   = defined('ASSETS_URL') ? ASSETS_URL : '../assets';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($assetsUrl) ?>/css/style.css">
    <style>
        :root { --navy: <?= htmlspecialchars($primary) ?>; --navy-dark: <?= htmlspecialchars($primary) ?>; --orange: <?= htmlspecialchars($accent) ?>; }
    </style>
</head>
<body class="login-body-page">
    <div class="login-card">
        <div class="login-header">
            <?php if (!empty($siteLogo)): ?>
                <img src="../<?= htmlspecialchars($siteLogo) ?>" alt="" style="width:54px;height:54px;object-fit:cover;border-radius:14px;margin:0 auto 14px;">
            <?php else: ?>
                <div class="brand-logo"><?= htmlspecialchars(mb_substr($siteShort, 0, 2)) ?></div>
            <?php endif; ?>
            <h1><?= htmlspecialchars($siteName) ?></h1>
            <p>Class Routine Management System &bull; NasimSoft</p>
        </div>
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Username or Email</label>
                    <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($_POST['username'] ?? ($_POST['email'] ?? '')) ?>" required autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn btn-accent" style="width:100%; justify-content:center; padding:12px;">Sign In to Dashboard</button>
            </form>

            <div class="footer-note">
                &copy; <?= date('Y') ?> <?= htmlspecialchars($siteName) ?> &bull; NasimSoft
            </div>
        </div>
    </div>
</body>
</html>