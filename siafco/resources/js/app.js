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

    const appearanceEditor = document.querySelector('[data-login-appearance-editor]');
    if (appearanceEditor) {
        const preview = document.querySelector('[data-login-preview]');
        const previewLogo = document.querySelector('[data-login-preview-logo]');
        const previewFallback = document.querySelector('[data-login-preview-logo-fallback]');

        appearanceEditor.querySelectorAll('[data-login-image-input]').forEach((input) => {
            input.addEventListener('change', () => {
                const file = input.files?.[0];
                if (!file) return;

                const url = URL.createObjectURL(file);
                const target = input.dataset.loginImageInput;
                appearanceEditor.querySelector(`[data-login-remove-input="${target}"]`).value = '0';

                if (target === 'background') {
                    preview.style.backgroundImage = `url("${url}")`;
                } else if (previewLogo) {
                    previewLogo.src = url;
                    previewLogo.classList.remove('hidden');
                    previewFallback?.classList.add('hidden');
                }
            });
        });

        appearanceEditor.querySelectorAll('[data-login-remove]').forEach((button) => {
            button.addEventListener('click', () => {
                const target = button.dataset.loginRemove;
                appearanceEditor.querySelector(`[data-login-remove-input="${target}"]`).value = '1';
                const input = appearanceEditor.querySelector(`[data-login-image-input="${target}"]`);
                input.value = '';

                if (target === 'background') {
                    preview.style.backgroundImage = 'none';
                } else {
                    previewLogo?.classList.add('hidden');
                    previewFallback?.classList.remove('hidden');
                }
            });
        });

        appearanceEditor.querySelectorAll('[data-login-copy]').forEach((input) => {
            input.addEventListener('input', () => {
                document.querySelector(`[data-login-preview-${input.dataset.loginCopy}]`).textContent = input.value;
            });
        });

        const opacityInput = appearanceEditor.querySelector('[data-login-opacity]');
        opacityInput?.addEventListener('input', () => {
            document.querySelector('[data-login-opacity-output]').textContent = `${opacityInput.value}%`;
            document.querySelector('[data-login-preview-overlay]').style.opacity = String(Number(opacityInput.value) / 100);
        });
    }

    const deleteModal = document.querySelector('[data-delete-affiliate-modal]');
    if (deleteModal) {
        const deleteForm = deleteModal.querySelector('[data-delete-affiliate-form]');
        const confirmation = deleteModal.querySelector('[data-delete-confirmation]');
        const reason = deleteModal.querySelector('[data-delete-reason]');
        const submit = deleteModal.querySelector('[data-delete-submit]');
        const cancel = deleteModal.querySelector('[data-delete-cancel]');
        let trigger = null;

        const updateDeleteState = () => {
            submit.disabled = confirmation.value !== 'ELIMINAR' || reason.value.trim().length < 5;
        };

        const closeDeleteModal = () => {
            deleteModal.classList.add('hidden');
            deleteModal.classList.remove('flex');
            deleteForm.reset();
            updateDeleteState();
            trigger?.focus();
        };

        document.querySelectorAll('[data-delete-affiliate-trigger]').forEach((button) => {
            button.addEventListener('click', () => {
                trigger = button;
                deleteForm.action = button.dataset.deleteUrl;
                deleteModal.querySelector('[data-delete-name]').textContent = button.dataset.affiliateName;
                deleteModal.querySelector('[data-delete-number]').textContent = button.dataset.affiliateNumber;
                deleteModal.querySelector('[data-delete-ci]').textContent = button.dataset.affiliateCi;
                deleteModal.classList.remove('hidden');
                deleteModal.classList.add('flex');
                confirmation.focus();
            });
        });

        confirmation.addEventListener('input', updateDeleteState);
        reason.addEventListener('input', updateDeleteState);
        cancel.addEventListener('click', closeDeleteModal);
        deleteModal.addEventListener('click', (event) => {
            if (event.target === deleteModal) closeDeleteModal();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !deleteModal.classList.contains('hidden')) closeDeleteModal();
        });
    }

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
