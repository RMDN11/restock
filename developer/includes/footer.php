<script>
(function () {
    const sidebar = document.getElementById('developerSidebar');
    const toggle = document.getElementById('developerMenuToggle');
    const overlay = document.getElementById('developerSidebarOverlay');
    function setOpen(open) {
        if (!sidebar || !toggle || !overlay) return;
        sidebar.classList.toggle('-translate-x-full', !open);
        overlay.classList.toggle('hidden', !open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    toggle?.addEventListener('click', () => setOpen(sidebar?.classList.contains('-translate-x-full')));
    overlay?.addEventListener('click', () => setOpen(false));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') setOpen(false);
    });
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 1024) setOpen(false);
    });
    if (window.lucide) lucide.createIcons();
})();
</script>
</body>
</html>
