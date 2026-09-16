/**
 * Class Routine Management System (NasimSoft)
 * Front-end helpers shared by the Admin Console and Public Portal
 * Copyright (c) 2026 NasimSoft.
 */

(function () {
    'use strict';

    /* -------------------------------------------------------------
       Mobile sidebar toggle (admin console)
    ------------------------------------------------------------- */
    function initSidebarToggle() {
        var sidebar = document.getElementById('sidebar');
        var openBtn = document.getElementById('sidebarOpen');
        var closeBtn = document.getElementById('sidebarClose');
        var overlay = document.getElementById('sidebarOverlay');
        if (!sidebar) return;

        function openSidebar() {
            sidebar.classList.add('open');
            if (overlay) overlay.classList.add('show');
            if (openBtn) openBtn.classList.add('is-active');
        }
        function closeSidebar() {
            sidebar.classList.remove('open');
            if (overlay) overlay.classList.remove('show');
            if (openBtn) openBtn.classList.remove('is-active');
        }
        function toggleSidebar() {
            if (sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        }
        if (openBtn) openBtn.addEventListener('click', toggleSidebar);
        if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
        if (overlay) overlay.addEventListener('click', closeSidebar);
    }

    /* -------------------------------------------------------------
       Auto-dismiss flash alerts
    ------------------------------------------------------------- */
    function initAutoDismiss() {
        document.querySelectorAll('.alert-dismissible, .alert').forEach(function (el) {
            if (el.dataset.noAutoDismiss) return;
            setTimeout(function () {
                el.style.opacity = '0';
                el.style.transition = 'opacity 0.3s';
                setTimeout(function () { el.remove(); }, 300);
            }, 6000);
        });
    }

    /* -------------------------------------------------------------
       Smooth page transition when navigating via the sidebar
    ------------------------------------------------------------- */
    function initPageTransitions() {
        var content = document.querySelector('.content-body');
        if (!content) return;

        document.querySelectorAll('.sidebar .nav-item').forEach(function (link) {
            link.addEventListener('click', function (e) {
                var href = link.getAttribute('href');
                if (!href || href.startsWith('#') || link.target === '_blank') return;
                if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

                e.preventDefault();
                content.style.transition = 'opacity 0.15s ease, transform 0.15s ease';
                content.style.opacity = '0';
                content.style.transform = 'translateY(6px)';
                setTimeout(function () { window.location.href = href; }, 130);
            });
        });
    }

    /* -------------------------------------------------------------
       Public portal top-right menu (Faculty & Admin Login, etc.)
    ------------------------------------------------------------- */
    function initPublicMenu() {
        var toggle = document.getElementById('publicMenuToggle');
        var dropdown = document.getElementById('publicMenuDropdown');
        if (!toggle || !dropdown) return;

        function close() {
            dropdown.classList.remove('open');
            toggle.classList.remove('is-active');
        }
        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            var isOpen = dropdown.classList.toggle('open');
            toggle.classList.toggle('is-active', isOpen);
        });
        document.addEventListener('click', function (e) {
            if (!dropdown.contains(e.target) && e.target !== toggle) close();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') close();
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initSidebarToggle();
        initAutoDismiss();
        initPageTransitions();
        initPublicMenu();
    });

    /* -------------------------------------------------------------
       Simple AJAX helper using Fetch
    ------------------------------------------------------------- */
    async function apiRequest(url, options) {
        options = options || {};
        var defaults = {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        };

        if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
            defaults.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.body);
        }

        var config = Object.assign({}, defaults, options);
        if (options.headers) {
            config.headers = Object.assign({}, defaults.headers, options.headers);
        }

        try {
            var response = await fetch(url, config);
            return await response.json();
        } catch (err) {
            console.error('API Error:', err);
            return { success: false, message: 'Network error. Please try again.' };
        }
    }

    /* -------------------------------------------------------------
       Toast notifications
    ------------------------------------------------------------- */
    function showToast(message, type) {
        type = type || 'success';
        var existing = document.querySelector('.crms-toast');
        if (existing) existing.remove();

        var toast = document.createElement('div');
        toast.className = 'crms-toast alert alert-' + type;
        toast.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;min-width:280px;max-width:360px;box-shadow:0 8px 24px rgba(0,0,0,0.18);';
        toast.innerHTML = message + '<button class="alert-close" aria-label="Dismiss">&times;</button>';
        document.body.appendChild(toast);

        toast.querySelector('.alert-close').addEventListener('click', function () { toast.remove(); });

        setTimeout(function () {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s';
            setTimeout(function () { toast.remove(); }, 300);
        }, 4500);
    }

    /* -------------------------------------------------------------
       Modal helpers (.modal-overlay / .modal-backdrop + .modal-dialog)
    ------------------------------------------------------------- */
    function openModal(id) {
        var el = document.getElementById(id);
        if (el) el.classList.add('active');
    }
    function closeModal(id) {
        var el = document.getElementById(id);
        if (el) el.classList.remove('active');
    }
    // Click outside dialog closes the modal; Escape closes the topmost one.
    document.addEventListener('click', function (e) {
        var overlay = e.target.closest('.modal-overlay.active, .modal-backdrop.active');
        if (overlay && e.target === overlay) {
            overlay.classList.remove('active');
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.active, .modal-backdrop.active').forEach(function (m) {
                m.classList.remove('active');
            });
        }
    });

    /* -------------------------------------------------------------
       Confirm dialog helper — supports a plain browser confirm() as
       well as an optional callback-style usage: App.confirm(msg, fn)
    ------------------------------------------------------------- */
    function confirmAction(message, onConfirm) {
        var ok = window.confirm(message || 'Are you sure?');
        if (ok && typeof onConfirm === 'function') {
            onConfirm();
        }
        return ok;
    }

    /* -------------------------------------------------------------
       Live client-side table search
       Usage: App.setupTableSearch('searchInputId', 'tableId')
    ------------------------------------------------------------- */
    function setupTableSearch(inputId, tableId) {
        var input = document.getElementById(inputId);
        var table = document.getElementById(tableId);
        if (!input || !table) return;

        input.addEventListener('input', function () {
            var q = input.value.trim().toLowerCase();
            var rows = table.querySelectorAll('tbody tr');
            rows.forEach(function (row) {
                var text = row.textContent.toLowerCase();
                row.style.display = text.indexOf(q) !== -1 ? '' : 'none';
            });
        });
    }

    /* -------------------------------------------------------------
       Public namespace
    ------------------------------------------------------------- */
    window.App = {
        openModal: openModal,
        closeModal: closeModal,
        confirm: confirmAction,
        setupTableSearch: setupTableSearch,
        toast: showToast,
        showToast: showToast, // alias — admin/routine-create.php calls App.showToast()
        api: apiRequest,
        ajax: apiRequest // alias — admin/routine-create.php calls App.ajax()
    };

    // Backwards-compatible bare function names used by a couple of pages
    window.confirmAction = confirmAction;
    window.showToast = showToast;
    window.apiRequest = apiRequest;
})();
