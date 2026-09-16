<?php
/**
 * Admin Footer + Layout End
 */
?>
        </main>
        <footer class="admin-footer">
            <?= e(defined('APP_COPYRIGHT') ? APP_COPYRIGHT : '© ' . date('Y') . ' Class Routine Management System, Developed by NasimSoft.') ?>
        </footer>
    </div><!-- /.main-content -->

    <script src="<?= defined('ASSETS_URL') ? ASSETS_URL . '/js/app.js' : '../assets/js/app.js' ?>"></script>
</body>
</html>
