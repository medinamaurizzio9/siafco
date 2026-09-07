<x-investment-crm.advisor.layout title="Kanban" :advisor="$advisor">
    <div class="crm-page" data-crm-kanban>
        <section class="crm-kanban-hero"><p class="crm-eyebrow">CRM de inversiones</p><h1>Kanban</h1><p>Gestiona tus prospectos de forma simple y visual.</p></section>
        <div class="crm-kanban-tools">
            <label class="crm-search" for="crm-prospect-search"><span aria-hidden="true">⌕</span><input id="crm-prospect-search" type="search" placeholder="Buscar prospectos (nombre, código, celular...)" data-kanban-search></label>
            <button class="crm-filter-button" type="button" data-filter-toggle aria-expanded="false" aria-controls="crm-kanban-filters"><span aria-hidden="true">☷</span><span>Filtros</span></button>
        </div>
        <div class="crm-kanban-filters" id="crm-kanban-filters" hidden><label class="crm-label" for="crm-contact-filter">Método de contacto</label><select class="crm-form-input" id="crm-contact-filter" data-contact-filter><option value="">Todos</option><option value="whatsapp">WhatsApp</option><option value="call">Llamada</option></select></div>
        <div class="crm-stage-tabs" role="tablist" aria-label="Etapas del Kanban">
            @foreach(\App\Support\InvestmentProspectStatus::labels() as $status => $label)<button class="crm-stage-tab crm-stage-tab--{{ $status }}" type="button" role="tab" data-stage-tab="{{ $status }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" aria-controls="crm-stage-{{ $status }}"><span>{{ $label }}</span><strong data-tab-count>{{ $groups->get($status, collect())->count() }}</strong></button>@endforeach
        </div>
        <div class="crm-kanban">
            @foreach(\App\Support\InvestmentProspectStatus::labels() as $status => $label)
                <section class="crm-kanban-column" id="crm-stage-{{ $status }}" role="tabpanel" data-kanban-column data-status="{{ $status }}" @if(!$loop->first) data-mobile-hidden @endif>
                    <header class="crm-column-header"><div><h2>{{ $label }}</h2><p>{{ match($status) { 'captured' => 'Prospectos recién registrados', 'in_transition' => 'Prospectos en seguimiento', 'closed' => 'Gestiones cerradas', 'lost' => 'Oportunidades no concretadas' } }}</p></div><span class="crm-status crm-status--{{ $status }}" data-column-count>{{ $groups->get($status, collect())->count() }}</span></header>
                    <div class="crm-column-cards" data-column-cards>
                        @forelse($groups->get($status, collect()) as $prospect)
                            @php($allowed = app(\App\Services\InvestmentProspectWorkflowService::class)->allowedTargetsForAdvisor($prospect))
                            <article class="crm-card crm-kanban-card" draggable="true" data-kanban-card data-prospect-id="{{ $prospect->prospect_number }}" data-current-status="{{ $prospect->status }}" data-contact="{{ $prospect->preferred_contact_method }}" data-search-text="{{ str($prospect->prospect_number.' '.$prospect->full_name.' '.$prospect->phone)->lower() }}" data-allowed='@json($allowed)' data-endpoint="{{ route('investment-crm.advisor.prospects.status', $prospect) }}">
                                <div class="crm-prospect-heading"><div><strong>{{ $prospect->prospect_number }}</strong><h3>{{ $prospect->full_name }}</h3></div><span class="crm-card-menu" aria-hidden="true">⋮</span></div>
                                <dl class="crm-prospect-facts"><div><dt>Contacto</dt><dd>{{ $prospect->phone }} · {{ $prospect->preferred_contact_method === 'whatsapp' ? 'WhatsApp' : 'Llamada' }}</dd></div><div><dt>Registro</dt><dd>{{ \App\Support\InvestmentCrmDate::format($prospect->captured_at) }}</dd></div><div><dt>Interés</dt><dd>{{ $prospect->requested_shares }} {{ $prospect->requested_shares === 1 ? 'acción' : 'acciones' }}</dd></div></dl>
                                @if($prospect->next_follow_up_at)<p class="crm-follow-up">Seguimiento: {{ \App\Support\InvestmentCrmDate::format($prospect->next_follow_up_at) }}</p>@endif
                                <div class="crm-prospect-actions"><a class="crm-button crm-button--secondary" href="{{ route('investment-crm.advisor.prospects.show', $prospect) }}">Ver</a><a class="crm-button crm-button--secondary" href="{{ route('investment-crm.advisor.prospects.show', $prospect) }}#registrar-interaccion">Registrar interacción</a><form method="post" action="{{ route('investment-crm.advisor.prospects.status', $prospect) }}" data-stage-form>@csrf<label class="sr-only" for="stage-{{ $prospect->id }}">Cambiar etapa</label><select class="crm-form-input" id="stage-{{ $prospect->id }}" name="status" required><option value="">Cambiar etapa</option>@foreach($allowed as $target)<option value="{{ $target }}">{{ \App\Support\InvestmentProspectStatus::label($target) }}</option>@endforeach</select></form></div>
                            </article>
                        @empty<p class="crm-empty-state" data-empty-state>No tienes prospectos en esta etapa.</p>@endforelse
                    </div>
                </section>
            @endforeach
        </div>
        <div class="crm-kanban-toast" data-kanban-toast role="status" aria-live="polite" hidden></div>
    </div>
</x-investment-crm.advisor.layout>
