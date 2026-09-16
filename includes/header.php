<?php
/**
 * Global Admin Header & HTML Shell
 * Class Routine Management System - NasimSoft
 *
 * Requires includes/bootstrap.php (session/config/auth — no output) and then
 * prints the page shell (doctype, sidebar, topbar, flash message, opens
 * <main>). Pages with a POST/GET action handler that redirects MUST require
 * bootstrap.php directly, run their action handling FIRST, and only require
 * this file afterwards — see includes/bootstrap.php for why.
 */

require_once __DIR__ . '/bootstrap.php';

$pageTitle = $pageTitle ?? 'Routine Console';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars($brandName) ?> NasimSoft</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($assetsUrl) ?>/css/style.css">
    <style>
        :root {
            --navy: <?= htmlspecialchars($brandPrimary) ?>;
            --navy-dark: <?= htmlspecialchars($brandPrimary) ?>;
            --orange: <?= htmlspecialchars($brandAccent) ?>;
        }
    </style>
</head>
<body class="admin-body">

    <?php require __DIR__ . '/sidebar.php'; ?>

    <div class="main-content">
        <?php require __DIR__ . '/topbar.php'; ?>

        <main class="content-body">
            <?php if (!empty($_SESSION['flash'])): ?>
                <div class="alert alert-<?= $_SESSION['flash']['type'] === 'success' ? 'success' : ($_SESSION['flash']['type'] === 'warning' ? 'warning' : 'error') ?> alert-dismissible">
                    <?= htmlspecialchars($_SESSION['flash']['message']) ?>
                </div>
                <?php unset($_SESSION['flash']); ?>
            <?php endif; ?>
