<?php
/**
 * Admin Topbar
 */
$userName = $userName ?? ($_SESSION['user_name'] ?? 'Administrator');
$userRole = $userRole ?? ($_SESSION['user_role'] ?? 'SUPERADMIN');
?>
<header class="topbar">
    <div class="topbar-left">
        <button type="button" class="menu-toggle" id="sidebarOpen" aria-label="Open menu">
            <span></span><span></span><span></span>
        </button>
        <div class="topbar-title"><?= e($pageTitle ?? 'Dashboard') ?></div>
    </div>
    <div class="topbar-right">
        <div class="user-menu">
            <div class="user-avatar"><?= strtoupper(substr($userName, 0, 1)) ?></div>
            <div class="user-info">
                <span class="user-name"><?= e($userName) ?></span>
                <span class="user-role"><?= e(ucfirst(strtolower($userRole))) ?></span>
            </div>
        </div>
        <a href="logout.php" class="btn btn-outline btn-sm">Logout</a>
    </div>
</header>
