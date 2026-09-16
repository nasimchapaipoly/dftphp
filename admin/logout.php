<?php
/**
 * Class Routine Management System (NasimSoft)
 * Logout Handler
 * Copyright (c) 2026 NasimSoft.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
Auth::logout();
setFlash('info', 'You have been safely logged out.');
header('Location: ' . ADMIN_URL . '/login.php');
exit;