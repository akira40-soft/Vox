/**
 * theme.js - VOX Theme Management
 * Handles: light/dark toggle, icon sync, cross-tab sync, mobile nav
 */

// ── IIFE: Apply theme immediately to <html> to prevent flash ──────────────────
// (Also inlined in header.php <head> for fastest possible application)
(function () {
    var saved = localStorage.getItem('vox-theme') || 'light';
    document.documentElement.setAttribute('data-theme', saved);
})();

// ── After DOM is ready: wire up UI ───────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {

    var html       = document.documentElement;
    var toggle     = document.getElementById('themeToggle');
    var hamburger  = document.getElementById('navHamburger');
    var mainNav    = document.getElementById('mainNav');

    // --- Theme helpers ---
    function applyTheme(theme) {
        html.setAttribute('data-theme', theme);
        syncIcon(theme === 'dark');
    }

    function syncIcon(isDark) {
        if (!toggle) return;
        var icon = toggle.querySelector('i');
        if (!icon) return;
        icon.className = isDark ? 'fa fa-sun-o' : 'fa fa-moon-o';
        icon.style.color = isDark ? '#fbbf24' : '';
    }

    // --- Init icon state ---
    syncIcon(html.getAttribute('data-theme') === 'dark');

    // --- Toggle button ---
    if (toggle) {
        toggle.addEventListener('click', function () {
            var current = html.getAttribute('data-theme') || 'light';
            var next    = current === 'dark' ? 'light' : 'dark';
            applyTheme(next);
            localStorage.setItem('vox-theme', next);
        });
    }

    // --- Cross-tab sync via storage event ---
    window.addEventListener('storage', function (e) {
        if (e.key === 'vox-theme' && e.newValue) {
            applyTheme(e.newValue);
        }
    });

    // --- Mobile hamburger ---
    if (hamburger && mainNav) {
        hamburger.addEventListener('click', function (e) {
            e.stopPropagation();
            var isOpen = mainNav.classList.toggle('mobile-open');
            hamburger.classList.toggle('open', isOpen);
            hamburger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        // Close on outside click
        document.addEventListener('click', function (e) {
            if (!mainNav.contains(e.target) && e.target !== hamburger) {
                mainNav.classList.remove('mobile-open');
                hamburger.classList.remove('open');
                hamburger.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // --- Dropdown toggles (header inline script removed — handled here) ---
    function setupDropdown(toggleId, menuId) {
        var t = document.getElementById(toggleId);
        var m = document.getElementById(menuId);
        if (!t || !m) return;

        t.addEventListener('click', function (e) {
            e.stopPropagation();
            // Close all other dropdown menus
            document.querySelectorAll('.dropdown-menu.show').forEach(function (el) {
                if (el !== m) el.classList.remove('show');
            });
            m.classList.toggle('show');
        });
    }

    setupDropdown('notifToggle',    'notifDropdown');
    setupDropdown('userMenuToggle', 'userDropdown');

    document.addEventListener('click', function () {
        document.querySelectorAll('.dropdown-menu.show').forEach(function (el) {
            el.classList.remove('show');
        });
    });
});
