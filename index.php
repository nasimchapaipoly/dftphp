<?php
echo '
<div id="preloader">
    <div class="loader">
        <i class="fa-solid fa-seedling"></i>
    </div>
</div>

<div class="menu-overlay" onclick="toggleMenu()"></div>

<header id="header">
    <div class="top-bar">
        <i class="fa-regular fa-clock"></i> <span id="current-datetime"></span>
    </div>

    <div class="nav-container">
        <a href="index.php" class="logo-area">
            <i class="fa-solid fa-seedling"></i>
            <div class="logo-text">
                <h1>
                    <span class="en">Food Technology</span>
                    <span class="bn">ফুড টেকনোলজি</span>
                </h1>
                <p>
                    <span class="en">Chapainawabganj Polytechnic</span>
                    <span class="bn">চাঁপাইনবাবগঞ্জ পলিটেকনিক</span>
                </p>
            </div>
        </a>

        <nav id="navbar">
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="about.php">About</a></li>
                <li><a href="teachers.php">Teachers</a></li>
                <li><a href="staff.php">Staff</a></li>
                <li><a href="semester.php">Syllabus</a></li>
                <li><a href="lab.php">Labs</a></li>
            </ul>
        </nav>

        <div class="header-actions">
            <button class="lang-toggle" onclick="toggleLanguage()">EN / BN</button>
            <button class="menu-toggle" onclick="toggleMenu()">
                <i class="fa-solid fa-bars"></i>
            </button>
        </div>
    </div>
</header>
';
?>
