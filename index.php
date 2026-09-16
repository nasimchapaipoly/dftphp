<?php
/**
 * Class Routine Management System (NasimSoft)
 * Root Entry Point
 *
 * The canonical Public Routine Portal lives in /public/index.php. This file
 * previously contained a second, divergent copy of that page which (unlike
 * the real portal) did not filter routines by status = 'PUBLISHED', so it
 * could leak draft/unpublished schedules. It has been replaced with a
 * simple redirect so there is exactly one public homepage to maintain.
 */

declare(strict_types=1);

$target = 'public/index.php';
if (!empty($_SERVER['QUERY_STRING'])) {
    $target .= '?' . $_SERVER['QUERY_STRING'];
}

header('Location: ' . $target);
exit;
