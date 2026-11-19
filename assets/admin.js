(() => {
    const button = document.getElementById('mobileMenu');
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    if (!button || !sidebar || !backdrop) return;

    const close = () => {
        sidebar.classList.remove('open');
        backdrop.classList.remove('visible');
        document.body.classList.remove('menu-open');
        button.setAttribute('aria-expanded', 'false');
    };
    button.addEventListener('click', () => {
        const open = sidebar.classList.toggle('open');
        backdrop.classList.toggle('visible', open);
        document.body.classList.toggle('menu-open', open);
        button.setAttribute('aria-expanded', String(open));
    });
    backdrop.addEventListener('click', close);
    window.addEventListener('resize', () => {
        if (window.innerWidth > 820) close();
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') close();
    });
})();
