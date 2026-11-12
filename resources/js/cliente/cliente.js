document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('[data-cliente-toggle]');
    const overlay = document.querySelector('[data-cliente-overlay]');
    const links = document.querySelectorAll('.cliente-nav-link');

    const setMenuOpen = (isOpen) => {
        document.body.classList.toggle('cliente-menu-open', isOpen);
        toggle?.setAttribute('aria-expanded', String(isOpen));
    };

    toggle?.addEventListener('click', () => {
        setMenuOpen(!document.body.classList.contains('cliente-menu-open'));
    });

    overlay?.addEventListener('click', () => setMenuOpen(false));

    links.forEach((link) => {
        link.addEventListener('click', () => setMenuOpen(false));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') setMenuOpen(false);
    });
});
