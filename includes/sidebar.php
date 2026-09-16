<?php
/**
 * Admin Sidebar
 * Class Routine Management System - NasimSoft
 */
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$brandName = $brandName ?? 'CR';
?>
<aside class="sidebar" id="sidebar">
    <a href="dashboard.php" class="sidebar-brand">
        <div class="brand-logo"><?= htmlspecialchars(mb_substr($brandName, 0, 2)) ?></div>
        <div class="brand-text">
            <strong><?= htmlspecialchars($brandName) ?> Routine</strong>
            <span>NasimSoft v2.0</span>
        </div>
        <button type="button" class="sidebar-toggle mobile-only" id="sidebarClose" aria-label="Close menu">&times;</button>
    </a>

    <nav class="sidebar-nav">
        <div class="nav-section">
            <a href="dashboard.php" class="nav-item <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
                <span class="nav-icon">&#128202;</span><span>Dashboard</span>
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-label">Routine Hub</div>
            <a href="routines.php" class="nav-item <?= $currentPage === 'routines.php' ? 'active' : '' ?>">
                <span class="nav-icon">&#128203;</span><span>All Routines</span>
            </a>
            <a href="routine-master.php" class="nav-item <?= $currentPage === 'routine-master.php' ? 'active' : '' ?>">
                <span class="nav-icon">&#128197;</span><span>Master Timetable</span>
            </a>
            <a href="routine-create.php" class="nav-item <?= $currentPage === 'routine-create.php' ? 'active' : '' ?>">
                <span class="nav-icon">&#10133;</span><span>Create Routine</span>
            </a>
            <a href="routine-generate.php" class="nav-item <?= $currentPage === 'routine-generate.php' ? 'active' : '' ?>">
                <span class="nav-icon">&#9889;</span><span>Auto Generator</span>
            </a>
            <a href="bulk-generate.php" class="nav-item <?= $currentPage === 'bulk-generate.php' ? 'active' : '' ?>">
                <span class="nav-icon">&#128640;</span><span>Bulk Generator</span>
            </a>
            <a href="routine-bulk-edit.php" class="nav-item <?= $currentPage === 'routine-bulk-edit.php' ? 'active' : '' ?>">
                <span class="nav-icon">&#9998;</span><span>Bulk Edit &amp; Delete</span>
            </a>
            <a href="conflict-validator.php" class="nav-item <?= $currentPage === 'conflict-validator.php' ? 'active' : '' ?>">
                <span class="nav-icon">&#9888;</span><span>Conflict Validator</span>
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-label">Structure &amp; Faculty</div>
            <a href="institutes.php" class="nav-item <?= $currentPage === 'institutes.php' ? 'active' : '' ?>">
                <span class="nav-icon">&#127979;</span><span>Institutes</span>
            </a>
            <a href="departments.php" class="nav-item <?= $currentPage === 'departments.php' ? 'active' : '' ?>">
                <span class="nav-icon">&#127978;</span><span>Departments</span>
            </a>
            <a href="technologies.php" class="nav-item <?= $currentPage === 'technologies.php' ? 'active' : '' ?>">
                <span class="nav-icon">&#128295;</span><span>Technologies</span>
            </a>
            <a href="semesters.php" class="nav-item <?= $currentPage === 'semesters.php' ? 'active' : '' ?>">
                <span class="nav-icon">&#128198;</span><span>Semesters</span>
            </a>
            <a href="shifts.php" class="nav-item <?= $currentPage === 'shifts.php' ? 'active' : '' ?>">
                <span class="nav-icon">&#128336;</span><span>Shifts</span>
            </a>
            <a href="periods.php" class="nav-item <?= $currentPage === 'periods.php' ? 'active' : '' ?>">
                <span class="nav-icon">&#8987;</span><span>Periods</span>
            </a>
            <a href="groups.php" class="nav-item <?= $currentPage === 'groups.php' ? 'active' : '' ?>">
                <span class="nav-icon">&#128101;</span><span>Academic Groups</span>
            </a>
            <a href="group-subjects.php" class="nav-item <?= $currentPage === 'group-subjects.php' ? 'active' : '' ?>">
                <span class="nav-icon">&#128279;</span><span>Group Subjects</span>
            </a>
            <a href="teachers.php" class="nav-item <?= $currentPage === 'teachers.php' ? 'active' : '' ?>">
                <span class="nav-icon">&#128104;&#8205;&#127979;</span><span>Teachers</span>
            </a>
            <a href="rooms.php" class="nav-item <?= $currentPage === 'rooms.php' ? 'active' : '' ?>">
                <span class="nav-icon">&#127963;</span><span>Classrooms</span>
            </a>
            <a href="labs.php" class="nav-item <?= $currentPage === 'labs.php' ? 'active' : '' ?>">
                <span class="nav-icon">&#128300;</span><span>Labs</span>
            </a>
            <a href="subjects.php" class="nav-item <?= $currentPage === 'subjects.php' ? 'active' : '' ?>">
                <span class="nav-icon">&#128218;</span><span>Subjects</span>
            </a>
            <a href="monitors.php" class="nav-item <?= $currentPage === 'monitors.php' ? 'active' : '' ?>">
                <span class="nav-icon">&#127891;</span><span>Class Monitors</span>
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-label">System</div>
            <a href="settings.php" class="nav-item <?= $currentPage === 'settings.php' ? 'active' : '' ?>">
                <span class="nav-icon">&#9881;</span><span>Site &amp; Print Settings</span>
            </a>
            <a href="documents.php" class="nav-item <?= $currentPage === 'documents.php' ? 'active' : '' ?>">
                <span class="nav-icon">&#128193;</span><span>Document Center</span>
            </a>
            <a href="export.php?format=pdf&amp;view_type=bulk" class="nav-item">
                <span class="nav-icon">&#128424;</span><span>Print / Export</span>
            </a>
            <a href="audit-logs.php" class="nav-item <?= $currentPage === 'audit-logs.php' ? 'active' : '' ?>">
                <span class="nav-icon">&#128196;</span><span>Audit Trail</span>
            </a>
            <a href="../public/index.php" target="_blank" class="nav-item">
                <span class="nav-icon">&#128065;</span><span>View Public Portal</span>
            </a>
            <a href="logout.php" class="nav-item nav-logout">
                <span class="nav-icon">&#128682;</span><span>Logout</span>
            </a>
        </div>
    </nav>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
