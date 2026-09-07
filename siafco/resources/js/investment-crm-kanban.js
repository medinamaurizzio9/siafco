const board = document.querySelector('[data-crm-kanban]');

if (board) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const toast = board.querySelector('[data-kanban-toast]');
    let dragged = null;
    let busy = false;
    let activeStage = board.querySelector('[data-stage-tab][aria-selected="true"]')?.dataset.stageTab || 'captured';

    const allowed = (card) => {
        try { return JSON.parse(card.dataset.allowed || '[]'); } catch { return []; }
    };

    const showToast = (message, error = false) => {
        toast.textContent = message;
        toast.hidden = false;
        toast.classList.toggle('is-error', error);
        window.clearTimeout(showToast.timer);
        showToast.timer = window.setTimeout(() => { toast.hidden = true; }, 4500);
    };

    const refreshColumn = (column) => {
        const cards = column.querySelectorAll('[data-kanban-card]');
        column.querySelector('[data-column-count]').textContent = String(cards.length);
        const tabCount = board.querySelector(`[data-stage-tab="${column.dataset.status}"] [data-tab-count]`);
        if (tabCount) tabCount.textContent = String(cards.length);
        let empty = column.querySelector('[data-empty-state]');
        if (!cards.length && !empty) {
            empty = document.createElement('p');
            empty.className = 'crm-empty-state crm-muted text-sm';
            empty.dataset.emptyState = '';
            empty.textContent = 'Sin prospectos.';
            column.querySelector('[data-column-cards]').append(empty);
        } else if (cards.length) empty?.remove();
    };

    const showStage = (status) => {
        activeStage = status;
        board.querySelectorAll('[data-stage-tab]').forEach((tab) => tab.setAttribute('aria-selected', String(tab.dataset.stageTab === status)));
        board.querySelectorAll('[data-kanban-column]').forEach((column) => column.toggleAttribute('data-mobile-hidden', column.dataset.status !== status));
    };

    const applyFilters = () => {
        const term = (board.querySelector('[data-kanban-search]')?.value || '').trim().toLocaleLowerCase('es');
        const contact = board.querySelector('[data-contact-filter]')?.value || '';
        board.querySelectorAll('[data-kanban-card]').forEach((card) => {
            card.hidden = Boolean((term && !card.dataset.searchText.includes(term)) || (contact && card.dataset.contact !== contact));
        });
    };

    board.querySelectorAll('[data-stage-tab]').forEach((tab) => tab.addEventListener('click', () => showStage(tab.dataset.stageTab)));
    board.querySelector('[data-kanban-search]')?.addEventListener('input', applyFilters);
    board.querySelector('[data-contact-filter]')?.addEventListener('change', applyFilters);
    board.querySelector('[data-filter-toggle]')?.addEventListener('click', (event) => {
        const panel = board.querySelector('#crm-kanban-filters');
        panel.hidden = !panel.hidden;
        event.currentTarget.setAttribute('aria-expanded', String(!panel.hidden));
    });

    const askLostReason = () => new Promise((resolve) => {
        const dialog = document.createElement('dialog');
        dialog.className = 'crm-lost-dialog';
        dialog.innerHTML = '<form method="dialog" class="crm-card"><h2 class="crm-section-title">Marcar como perdido</h2><label class="crm-label mt-4" for="crm-lost-reason">Motivo</label><textarea id="crm-lost-reason" class="crm-form-input" rows="4" maxlength="1000" required></textarea><p class="mt-1 text-sm text-red-600" data-reason-error hidden>Debes indicar un motivo.</p><div class="mt-4 flex justify-end gap-2"><button class="crm-button crm-button--secondary" value="cancel">Cancelar</button><button class="crm-button crm-button--primary" value="confirm">Confirmar</button></div></form>';
        document.body.append(dialog);
        dialog.addEventListener('close', () => {
            const value = dialog.querySelector('textarea').value.trim();
            const confirmed = dialog.returnValue === 'confirm';
            dialog.remove();
            resolve(confirmed && value ? value : null);
        });
        dialog.querySelector('[value="confirm"]').addEventListener('click', (event) => {
            if (!dialog.querySelector('textarea').value.trim()) {
                event.preventDefault();
                dialog.querySelector('[data-reason-error]').hidden = false;
            }
        });
        dialog.showModal();
        dialog.querySelector('textarea').focus();
    });

    const move = async (card, status) => {
        if (busy || !allowed(card).includes(status)) {
            if (!allowed(card).includes(status)) showToast('La transición seleccionada no está permitida.', true);
            return;
        }
        let reason = null;
        if (status === 'lost') {
            reason = await askLostReason();
            if (!reason) return;
        }

        const origin = card.closest('[data-kanban-column]');
        const destination = board.querySelector(`[data-kanban-column][data-status="${status}"]`);
        if (!destination) return;
        busy = true;
        card.classList.add('is-saving');
        destination.querySelector('[data-column-cards]').append(card);
        refreshColumn(origin); refreshColumn(destination);

        try {
            const response = await fetch(card.dataset.endpoint, {
                method: 'PATCH',
                headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf},
                credentials: 'same-origin',
                body: JSON.stringify({status, reason}),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.ok) throw new Error(data.message || Object.values(data.errors || {})[0]?.[0] || 'No fue posible cambiar la etapa.');
            card.dataset.currentStatus = data.status;
            card.dataset.allowed = JSON.stringify(data.allowed_targets || []);
            card.querySelector('select[name="status"]').value = '';
            showStage(status);
            showToast(`Prospecto movido a ${data.label}.`);
        } catch (error) {
            origin.querySelector('[data-column-cards]').append(card);
            refreshColumn(destination); refreshColumn(origin);
            showToast(error.message, true);
        } finally {
            card.classList.remove('is-saving');
            busy = false;
        }
    };

    board.querySelectorAll('[data-kanban-card]').forEach((card) => {
        card.addEventListener('dragstart', (event) => {
            if (busy || window.matchMedia('(max-width: 1023px)').matches) return event.preventDefault();
            dragged = card;
            card.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            board.querySelectorAll('[data-kanban-column]').forEach((column) => column.classList.toggle('is-valid-target', allowed(card).includes(column.dataset.status)));
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('is-dragging');
            board.querySelectorAll('[data-kanban-column]').forEach((column) => column.classList.remove('is-valid-target', 'is-drag-over'));
            dragged = null;
        });
        card.querySelector('[data-stage-form]')?.addEventListener('submit', (event) => {
            event.preventDefault();
            const status = new FormData(event.currentTarget).get('status');
            if (status) move(card, status);
        });
        card.querySelector('select[name="status"]')?.addEventListener('change', (event) => {
            if (event.currentTarget.value) move(card, event.currentTarget.value);
        });
    });

    board.querySelectorAll('[data-kanban-column]').forEach((column) => {
        column.addEventListener('dragover', (event) => {
            if (dragged && allowed(dragged).includes(column.dataset.status)) { event.preventDefault(); column.classList.add('is-drag-over'); }
        });
        column.addEventListener('dragleave', () => column.classList.remove('is-drag-over'));
        column.addEventListener('drop', (event) => {
            event.preventDefault();
            column.classList.remove('is-drag-over');
            if (dragged) move(dragged, column.dataset.status);
        });
    });

    showStage(activeStage);
}
