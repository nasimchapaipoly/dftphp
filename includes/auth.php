<?php
/**
 * Class Routine Management System (NasimSoft)
 * Authentication and Access Control Handler
 * Copyright (c) 2026 NasimSoft.
 */

declare(strict_types=1);

class Auth {
    /**
     * Attempt login
     */
    public static function login(string $usernameOrEmail, string $password, bool $remember = false): array {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 'ACTIVE' LIMIT 1");
        $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }

        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }

        session_regenerate_id(true);

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_email'] = $user['email'];

        $update = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $update->execute([$user['id']]);

        logAudit('User Login', 'Auth', (int)$user['id'], 'User logged in successfully.');

        return ['success' => true, 'role' => $user['role']];
    }

    /**
     * Check if user is logged in
     */
    public static function check(): bool {
        return !empty($_SESSION['user_id']);
    }

    /**
     * Role checks
     */
    public static function isSuperAdmin(): bool {
        return self::check() && (($_SESSION['user_role'] ?? '') === 'SUPERADMIN');
    }

    public static function isAdmin(): bool {
        return self::check() && in_array($_SESSION['user_role'] ?? '', ['SUPERADMIN', 'ADMIN'], true);
    }

    /**
     * Enforce authentication for admin pages
     */
    public static function requireAdmin(): void {
        if (!self::isAdmin()) {
            setFlash('error', 'Please log in to access the administration panel.');
            header('Location: ' . BASE_URL . '/admin/login.php');
            exit;
        }
    }

    /**
     * Enforce superadmin privileges
     */
    public static function requireSuperAdmin(): void {
        if (!self::isSuperAdmin()) {
            setFlash('error', 'Access restricted to Superadmin only.');
            header('Location: ' . BASE_URL . '/admin/index.php');
            exit;
        }
    }

    /**
     * Logout
     */
    public static function logout(): void {
        if (!empty($_SESSION['user_id'])) {
            logAudit('User Logout', 'Auth', (int)$_SESSION['user_id'], 'User logged out.');
        }

        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
}