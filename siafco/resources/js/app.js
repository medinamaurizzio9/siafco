import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('[data-sidebar]');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');
    const accordion = document.querySelector('[data-sidebar-accordion]');

    const showSidebar = () => {
        sidebar?.classList.remove('hidden');
        backdrop?.classList.remove('hidden');
    };

    const hideSidebar = () => {
        if (window.matchMedia('(min-width: 1024px)').matches) {
            return;
        }

        sidebar?.classList.add('hidden');
        backdrop?.classList.add('hidden');
    };

    document.querySelectorAll('[data-sidebar-open]').forEach((button) => button.addEventListener('click', showSidebar));
    document.querySelectorAll('[data-sidebar-close], [data-sidebar-backdrop]').forEach((button) => button.addEventListener('click', hideSidebar));
    document.querySelectorAll('[data-sidebar-link]').forEach((link) => link.addEventListener('click', hideSidebar));

    if (!accordion) {
        return;
    }

    const modules = Array.from(accordion.querySelectorAll('[data-accordion-module]'));
    const currentModule = accordion.dataset.currentModule;

    const openModule = (targetModule, persist = true) => {
        modules.forEach((module) => {
            const isOpen = module.dataset.accordionModule === targetModule;
            module.querySelector('[data-accordion-toggle]')?.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            module.querySelector('.nav-module-panel')?.classList.toggle('hidden', !isOpen);
        });

        if (persist) {
            localStorage.setItem('siafco.sidebar.module', targetModule);
        }
    };

    modules.forEach((module) => {
        module.querySelector('[data-accordion-toggle]')?.addEventListener('click', () => {
            const isOpen = module.querySelector('[data-accordion-toggle]')?.getAttribute('aria-expanded') === 'true';
            openModule(isOpen ? '' : module.dataset.accordionModule);
        });
    });

    const remembered = localStorage.getItem('siafco.sidebar.module');
    openModule(currentModule || remembered || modules[0]?.dataset.accordionModule || '', false);
});
