(() => {
    const el = id => document.getElementById(id);
    const editor = document.querySelector('textarea[name="body"]');
    document.querySelectorAll('[data-format]').forEach(button => {
        button.onclick = () => {
            const tag = button.dataset.format;
            const start = editor.selectionStart;
            const end = editor.selectionEnd;
            editor.setRangeText('<' + tag + '>' + editor.value.slice(start,end) + '</' + tag + '>',start,end,'end');
            editor.focus();
        };
    });
    const hidden = el('selected-ids');
    let initialIds;
    try {
        initialIds = JSON.parse(hidden.value);
    } catch {
        initialIds = [];
    }
    const selected = new Set(Array.isArray(initialIds) ? initialIds.map(Number).filter(Number.isSafeInteger) : []);
    const endpoint = el('candidate-endpoint');
    const form = hidden.form;
    let countTimer;
    let revision = 0;
    function refreshCount() {
        const current = ++revision;
        clearTimeout(countTimer);
        el('audience-total').textContent = 'Calculando destinatarios únicos…';
        countTimer = setTimeout(async () => {
            try {
                const response = await fetch(endpoint.dataset.countUrl, {
                    method: 'POST',
                    headers: {'Content-Type':'application/json', Accept:'application/json', 'X-CSRF-TOKEN':form.elements._token.value},
                    body: JSON.stringify({
                        all: form.elements.all.checked,
                        sectors: [...form.querySelectorAll('[name="sectors[]"]:checked')].map(input => input.value),
                        affiliates: [...selected]
                    })
                });
                if (!response.ok) throw new Error();
                const {count} = await response.json();
                if (current === revision) el('audience-total').textContent = count + (count === 1 ? ' destinatario único' : ' destinatarios únicos');
            } catch {
                if (current === revision) el('audience-total').textContent = 'Total no disponible. Revisa la audiencia al guardar el borrador.';
            }
        }, 250);
    }
    form.querySelectorAll('[name="all"], [name="sectors[]"]').forEach(input => input.addEventListener('change',refreshCount));
    const results = el('candidate-results');
    let after = 0;
    const sync = () => {
        hidden.value = JSON.stringify([...selected]);
        el('selected-count').textContent = selected.size + ' seleccionados individualmente';
        refreshCount();
    };
    async function search(bulk = false, reset = true) {
        el('search-status').textContent = 'Buscando afiliados…';
        const buttons = ['find','bulk','more','clear-selection'].map(el);
        buttons.forEach(button => button.disabled = true);
        if (reset) { after = 0; results.replaceChildren(); }
        const url = new URL(el('candidate-endpoint').dataset.url);
        for (const key of ['q','sector','status']) url.searchParams.set(key, el('candidate-' + key).value);
        try {
            do {
                url.searchParams.set('after', after);
                const response = await fetch(url, {headers: {Accept: 'application/json'}});
                if (!response.ok) throw new Error();
                const rows = await response.json();
                for (const row of rows) {
                    after = row.id;
                    if (bulk && Number(row.eligible)) selected.add(row.id);
                    const label = document.createElement('label');
                    label.className = 'flex items-center gap-2 rounded-lg border p-3 text-sm';
                    const box = document.createElement('input');
                    box.type = 'checkbox';
                    box.checked = selected.has(row.id);
                    box.disabled = !Number(row.eligible);
                    box.onchange = () => { if (box.checked) selected.add(row.id); else selected.delete(row.id); sync(); };
                    const description = document.createElement('span');
                    description.textContent = [row.full_name,row.ci,row.registration_number,row.status].filter(Boolean).join(' · ');
                    label.append(box, description);
                    results.append(label);
                }
                el('more').hidden = rows.length < 100;
            } while (bulk && !el('more').hidden);
            el('search-status').textContent = results.childElementCount ? '' : 'Sin resultados para estos filtros.';
        } catch {
            el('search-status').textContent = 'No se pudo completar la búsqueda. Vuelve a intentarlo.';
        } finally {
            if (bulk) sync();
            buttons.forEach(button => button.disabled = false);
        }
    }
    for (const key of ['q','sector','status']) {
        const filter = el('candidate-' + key);
        filter.addEventListener('input', () => { el('more').hidden = true; });
        filter.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                event.preventDefault();
                el('find').click();
            }
        });
    }
    el('find').onclick = () => search();
    el('bulk').onclick = () => search(true);
    el('more').onclick = () => search(false,false);
    el('clear-selection').onclick = () => { selected.clear(); results.querySelectorAll('input').forEach(b => b.checked = false); sync(); };
    sync();
})();
