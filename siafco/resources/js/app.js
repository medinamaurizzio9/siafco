import './bootstrap';
import './public-affiliation-form';
import { initPhotoCroppers } from './components/photo-cropper';

document.addEventListener('DOMContentLoaded', () => {
    initPhotoCroppers();

    document.querySelectorAll('[data-notification-close]').forEach((button) => {
        button.addEventListener('click', () => button.closest('[data-notification]')?.remove());
    });
    document.querySelectorAll('[data-notification][data-auto-dismiss]').forEach((notification) => {
        window.setTimeout(() => notification.remove(), Number(notification.dataset.autoDismiss));
    });

    const confirmModal = document.querySelector('[data-confirm-modal]');
    if (confirmModal) {
        const title = confirmModal.querySelector('[data-confirm-title]');
        const message = confirmModal.querySelector('[data-confirm-message]');
        const detail = confirmModal.querySelector('[data-confirm-detail]');
        const accept = confirmModal.querySelector('[data-confirm-accept]');
        const cancel = confirmModal.querySelector('[data-confirm-cancel]');
        let pendingForm = null;
        let pendingSubmitter = null;

        const officeDetail = (form) => {
            if (!form.hasAttribute('data-confirm-office-affiliation')) return form.dataset.confirmDetail || '';

            const data = new FormData(form);
            const selectedText = (name) => form.elements.namedItem(name)?.selectedOptions?.[0]?.textContent?.trim() || 'No seleccionado';
            const amount = data.get('received_amount') || '0.00';

            return [
                `Nombre: ${data.get('full_name') || 'No registrado'}`,
                `CI: ${data.get('ci') || 'No registrado'}`,
                `Plan: ${selectedText('affiliation_plan_id')}`,
                `Sector: ${selectedText('sector_id')}`,
                `Monto recibido: BOB ${amount}`,
                `Método: ${selectedText('payment_method')}`,
            ].join('\n');
        };

        const closeConfirmModal = () => {
            confirmModal.close();
            pendingSubmitter?.focus();
            pendingForm = null;
            pendingSubmitter = null;
        };

        const requiresConfirmation = (form, submitter) => {
            if (form.hasAttribute('data-confirm-title')) return true;

            const methodOverride = form.querySelector('input[name="_method"]')?.value?.toUpperCase();
            if (methodOverride === 'DELETE') {
                form.dataset.confirmTitle = 'Confirmar eliminación';
                form.dataset.confirmMessage = 'Esta acción eliminará el registro seleccionado.';
                form.dataset.confirmAccept = 'Eliminar';
                form.dataset.confirmVariant = 'danger';
                return true;
            }

            const actionText = submitter?.textContent?.trim() || '';
            const sensitiveAction = /^(confirmar|aprobar|rechazar|anular|bloquear|activar|restaurar|restablecer|aplicar estado|actualizar estado|registrar pago)/i;
            if (!sensitiveAction.test(actionText)) return false;

            form.dataset.confirmTitle = actionText;
            form.dataset.confirmMessage = 'Revise la información antes de ejecutar esta acción.';
            form.dataset.confirmAccept = actionText;
            form.dataset.confirmVariant = /rechazar|anular|bloquear/i.test(actionText) ? 'danger' : 'warning';
            return true;
        };

        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || form.dataset.confirmed === 'true' || !requiresConfirmation(form, event.submitter)) return;

            event.preventDefault();
            pendingForm = form;
            pendingSubmitter = event.submitter;
            accept.disabled = false;
            title.textContent = form.dataset.confirmTitle || 'Confirmar acción';
            message.textContent = form.dataset.confirmMessage || 'Esta acción modificará información del sistema.';
            accept.textContent = form.dataset.confirmAccept || 'Confirmar';
            confirmModal.dataset.variant = form.dataset.confirmVariant || 'normal';

            const detailText = officeDetail(form);
            detail.textContent = detailText;
            detail.classList.toggle('hidden', !detailText);
            confirmModal.showModal();
            cancel.focus();
        });

        cancel.addEventListener('click', closeConfirmModal);
        confirmModal.addEventListener('cancel', (event) => {
            event.preventDefault();
            closeConfirmModal();
        });
        confirmModal.addEventListener('click', (event) => {
            if (event.target === confirmModal) closeConfirmModal();
        });
        accept.addEventListener('click', () => {
            if (!pendingForm || pendingForm.dataset.submitting === 'true') return;

            const form = pendingForm;
            const submitter = pendingSubmitter;
            form.dataset.confirmed = 'true';
            form.dataset.submitting = 'true';
            accept.disabled = true;
            accept.textContent = 'Procesando...';
            form.querySelectorAll('button[type="submit"], button:not([type])').forEach((button) => {
                button.disabled = true;
                if (button === submitter) button.textContent = 'Procesando...';
            });
            confirmModal.close();
            HTMLFormElement.prototype.submit.call(form);
        });
    }

    if (document.querySelector('[data-dashboard-charts]')) {
        import('./dashboard').then(({ initDashboardCharts }) => initDashboardCharts());
    }
    const passwordResetDialog = document.querySelector('[data-password-reset-dialog]');
    const passwordResetConfirmation = passwordResetDialog?.querySelector('[data-password-reset-confirmation]');
    const passwordResetSubmit = passwordResetDialog?.querySelector('[data-password-reset-submit]');
    document.querySelector('[data-password-reset-open]')?.addEventListener('click', () => passwordResetDialog?.showModal());
    passwordResetDialog?.querySelector('[data-password-reset-close]')?.addEventListener('click', () => passwordResetDialog.close());
    passwordResetConfirmation?.addEventListener('input', () => {
        passwordResetSubmit.disabled = passwordResetConfirmation.value !== 'RESTABLECER';
    });
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

    const sectorSelects = document.querySelectorAll('[data-sector-select]');
    sectorSelects.forEach((sector) => {
        const form = sector.closest('form');
        const plan = form?.querySelector('[data-sector-plan-select]');
        if (!plan) return;

        const syncPlans = (sectorChanged = false) => {
            const sectorId = sector.value;
            let available = 0;
            Array.from(plan.options).forEach((option) => {
                if (!option.value) return;
                const matches = Boolean(sectorId) && option.dataset.sector === sectorId;
                option.hidden = !matches;
                option.disabled = !matches;
                if (matches) available += 1;
            });

            if (sectorChanged || plan.selectedOptions[0]?.disabled) plan.value = '';
            plan.disabled = !sectorId || available === 0;
            plan.options[0].textContent = !sectorId
                ? 'Seleccione primero un sector'
                : available === 0 ? 'No existen planes disponibles para este sector' : 'Seleccione plan';
            plan.dispatchEvent(new Event('change'));
        };

        sector.addEventListener('change', () => syncPlans(true));
        syncPlans(false);
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

    const sidebar = document.querySelector('[data-sidebar]');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');
    const accordion = document.querySelector('[data-sidebar-accordion]');
    const sidebarOpenButtons = Array.from(document.querySelectorAll('[data-sidebar-open]'));
    const sidebarFocusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

    const showSidebar = () => {
        sidebar?.classList.add('is-open');
        backdrop?.classList.remove('hidden');
        document.body.classList.add('sidebar-open');
        sidebarOpenButtons.forEach((button) => button.setAttribute('aria-expanded', 'true'));
        const firstFocusable = sidebar?.querySelector(sidebarFocusableSelector);
        firstFocusable?.focus({ preventScroll: true });
    };

    const hideSidebar = () => {
        if (window.matchMedia('(min-width: 1024px)').matches) {
            return;
        }

        sidebar?.classList.remove('is-open');
        backdrop?.classList.add('hidden');
        document.body.classList.remove('sidebar-open');
        sidebarOpenButtons.forEach((button) => button.setAttribute('aria-expanded', 'false'));
    };

    sidebarOpenButtons.forEach((button) => button.addEventListener('click', showSidebar));
    document.querySelectorAll('[data-sidebar-close], [data-sidebar-backdrop]').forEach((button) => button.addEventListener('click', hideSidebar));
    document.querySelectorAll('[data-sidebar-link]').forEach((link) => link.addEventListener('click', hideSidebar));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            hideSidebar();
        }
    });

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
