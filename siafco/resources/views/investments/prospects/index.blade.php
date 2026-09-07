<x-layouts.app title="Prospectos de inversión">
    <div class="section-card">
        <div class="mb-5 flex flex-wrap items-start justify-between gap-3"><div><h2 class="text-xl font-black text-[#0b1f3a]">{{ $isAdvisorView ? 'Mis prospectos' : 'Prospectos de inversión' }}</h2><p class="text-sm text-slate-500">Interesados captados mediante QR o registro manual.</p></div>@can('create', \App\Models\InvestmentProspect::class)<a class="btn-primary" href="{{ route('investments.prospects.create') }}">Nuevo prospecto</a>@endcan</div>
        <form class="mb-5 grid gap-3 md:grid-cols-2 xl:grid-cols-6" method="get">
            <input class="form-input xl:col-span-2" name="search" value="{{ request('search') }}" placeholder="Código, nombre, celular o asesor">
            <select class="form-input" name="status"><option value="">Todos los estados</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select>
            <select class="form-input" name="advisor_id"><option value="">Todos los asesores</option>@foreach($advisors as $advisor)<option value="{{ $advisor->id }}" @selected((string) request('advisor_id') === (string) $advisor->id)>{{ $advisor->advisor_number }} - {{ $advisor->full_name }}</option>@endforeach</select>
            <div><label class="form-label" for="from">Desde</label><input class="form-input" type="date" id="from" name="from" value="{{ request('from') }}"></div>
            <div><label class="form-label" for="to">Hasta</label><input class="form-input" type="date" id="to" name="to" value="{{ request('to') }}"></div>
            <select class="form-input" name="follow_up"><option value="">Todos los seguimientos</option><option value="pending" @selected(request('follow_up') === 'pending')>Con seguimiento pendiente</option><option value="today" @selected(request('follow_up') === 'today')>Seguimiento hoy</option><option value="overdue" @selected(request('follow_up') === 'overdue')>Seguimiento vencido</option><option value="none" @selected(request('follow_up') === 'none')>Sin seguimiento</option></select>
            <select class="form-input" name="service_rating"><option value="">Todas las calificaciones</option>@foreach($ratings as $value => $label)<option value="{{ $value }}" @selected(request('service_rating') === $value)>{{ $label }}</option>@endforeach<option value="none" @selected(request('service_rating') === 'none')>Sin calificación</option></select>
            <div><label class="form-label" for="interaction_from">Última interacción desde</label><input class="form-input" type="date" id="interaction_from" name="interaction_from" value="{{ request('interaction_from') }}"></div>
            <div><label class="form-label" for="interaction_to">Última interacción hasta</label><input class="form-input" type="date" id="interaction_to" name="interaction_to" value="{{ request('interaction_to') }}"></div>
            <div class="flex gap-3 md:col-span-2 xl:col-span-6"><button class="btn-primary">Filtrar</button><a class="btn-secondary" href="{{ route('investments.prospects.index') }}">Limpiar</a></div>
        </form>
        <div class="overflow-x-auto"><table class="table">
            <thead><tr><th>Código</th><th>Nombre</th><th>Celular</th><th>Acciones de interés</th><th>Contacto</th><th>Asesor actual</th><th>Calificación</th><th>Estado</th><th>Captado</th><th>Acciones</th></tr></thead>
            <tbody>@forelse($prospects as $prospect)<tr>
                <td class="font-black">{{ $prospect->prospect_number }}</td><td>{{ $prospect->full_name }}</td><td>{{ $prospect->phone }}</td><td>{{ $prospect->requested_shares }}</td>
                <td>{{ $prospect->preferred_contact_method === 'whatsapp' ? 'WhatsApp' : 'Llamada' }}</td><td>{{ $prospect->currentAdvisor->advisor_number }} - {{ $prospect->currentAdvisor->full_name }}</td>
                <td>{{ $prospect->service_rating ? \App\Support\InvestmentProspectServiceRating::emoji($prospect->service_rating).' '.\App\Support\InvestmentProspectServiceRating::label($prospect->service_rating) : 'Sin calificación' }}</td>
                <td><span class="badge">{{ \App\Support\InvestmentProspectStatus::label($prospect->status) }}</span></td><td class="whitespace-nowrap">{{ \App\Support\InvestmentProspectPresenter::capturedAt($prospect->captured_at) }}</td>
                <td><a class="font-bold text-[#0b1f3a]" href="{{ route('investments.prospects.show', $prospect) }}">Ver</a></td>
            </tr>@empty<tr><td colspan="10" class="py-8 text-center text-slate-500">No se encontraron prospectos.</td></tr>@endforelse</tbody>
        </table></div>
        <div class="mt-4">{{ $prospects->links() }}</div>
    </div>
</x-layouts.app>
