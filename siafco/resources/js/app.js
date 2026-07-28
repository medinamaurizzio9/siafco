import './bootstrap';
import './public-affiliation-form';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = button.closest('label')?.querySelector('[data-password-input]');
            if (!input) return;

            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            button.setAttribute('aria-pressed', showing ? 'false' : 'true');
            button.setAttribute('aria-label', `${showing ? 'Mostrar' : 'Ocultar'} ${input.name === 'password_confirmation' ? 'confirmación de contraseña' : 'contraseña'}`);
            button.querySelector('[data-eye-open]')?.classList.toggle('hidden', !showing);
            button.querySelector('[data-eye-closed]')?.classList.toggle('hidden', showing);
        });
    });

    document.querySelectorAll('[data-uppercase]').forEach((input) => {
        input.addEventListener('input', () => {
            const start = input.selectionStart;
            const end = input.selectionEnd;
            input.value = input.value.toLocaleUpperCase('es-BO');
            if (start !== null && end !== null) input.setSelectionRange(start, end);
        });
    });

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
