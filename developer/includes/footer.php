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

    const copyButton = document.getElementById('copySpecialAccessLink');
    const linkInput = document.getElementById('specialAccessLink');
    copyButton?.addEventListener('click', async () => {
        if (!linkInput) return;
        try {
            await navigator.clipboard.writeText(linkInput.value);
            const label = copyButton.querySelector('span');
            if (label) label.textContent = 'Tersalin';
            copyButton.classList.remove('bg-blue-600', 'hover:bg-blue-700');
            copyButton.classList.add('bg-emerald-600');
            setTimeout(() => {
                if (label) label.textContent = 'Salin Link';
                copyButton.classList.remove('bg-emerald-600');
                copyButton.classList.add('bg-blue-600', 'hover:bg-blue-700');
            }, 3000);
        } catch (error) {
            linkInput.select();
            document.execCommand('copy');
        }
    });

    if (window.lucide) lucide.createIcons();
})();
</script>
</body>
</html>
