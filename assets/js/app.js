(function () {
    'use strict';

    const MOBILE_QUERY = '(max-width: 767px)';

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn, { once: true });
        } else {
            fn();
        }
    }

    function isMobile() {
        return window.matchMedia(MOBILE_QUERY).matches;
    }

    function refreshIcons() {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    }

    function setMobileMenuState(open) {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const toggle = document.getElementById('mobileMenuToggle');

        if (!sidebar) return;

        const shouldOpen = isMobile() && open;

        sidebar.classList.toggle('active', shouldOpen);

        if (overlay) {
            overlay.classList.toggle('active', shouldOpen);
            overlay.setAttribute('aria-hidden', shouldOpen ? 'false' : 'true');
        }

        if (toggle) {
            toggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
            toggle.setAttribute('aria-label', shouldOpen ? 'Tutup menu' : 'Buka menu');
        }

        document.body.classList.toggle('sidebar-open', shouldOpen);
    }

    function toggleMobileMenu() {
        if (!isMobile()) return;

        const sidebar = document.getElementById('sidebar');
        if (!sidebar) return;

        setMobileMenuState(!sidebar.classList.contains('active'));
    }

    ready(function () {
        const sidebar = document.getElementById('sidebar');
        const menuToggle = document.getElementById('mobileMenuToggle');
        const overlay = document.getElementById('sidebarOverlay');

        if (menuToggle) {
            menuToggle.addEventListener('click', toggleMobileMenu);
        }

        if (overlay) {
            overlay.addEventListener('click', function () {
                setMobileMenuState(false);
            });
        }

        if (sidebar) {
            sidebar.querySelectorAll('a').forEach(function (link) {
                link.addEventListener('click', function () {
                    if (isMobile()) {
                        setMobileMenuState(false);
                    }
                });
            });
        }

        window.addEventListener('resize', function () {
            if (!isMobile()) {
                setMobileMenuState(false);
            }

            refreshIcons();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && isMobile()) {
                setMobileMenuState(false);
            }
        });

        refreshIcons();
    });

    // Backward compatibility for any legacy page that still calls toggleSidebar().
    window.toggleSidebar = toggleMobileMenu;
})();
