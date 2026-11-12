document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('[data-cliente-toggle]');
    const overlay = document.querySelector('[data-cliente-overlay]');

    if (!toggle || !overlay) return;

    toggle.addEventListener('click', () => {
        document.body.classList.toggle('cliente-menu-open');
    });

    overlay.addEventListener('click', () => {
        document.body.classList.remove('cliente-menu-open');
    });
});