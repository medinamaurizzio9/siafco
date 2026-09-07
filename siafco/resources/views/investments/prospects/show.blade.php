<x-layouts.app title="Prospecto {{ $prospect->prospect_number }}">
    <section class="section-card">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-sm font-bold uppercase text-slate-500">{{ $prospect->prospect_number }}</p><h2 class="mt-1 text-2xl font-black text-[#0b1f3a]">{{ $prospect->full_name }}</h2></div><span class="badge">{{ \App\Support\InvestmentProspectStatus::label($prospect->status) }}</span></div>
        <dl class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div><dt class="font-bold text-slate-500">Celular</dt><dd>{{ $prospect->phone }}</dd></div>
            <div><dt class="font-bold text-slate-500">Acciones de interés</dt><dd>{{ $prospect->requested_shares }}</dd></div>
            <div><dt class="font-bold text-slate-500">Contacto preferido</dt><dd>{{ $prospect->preferred_contact_method === 'whatsapp' ? 'WhatsApp' : 'Llamada' }}</dd></div>
            <div><dt class="font-bold text-slate-500">Asesor original</dt><dd>{{ $prospect->originalAdvisor->advisor_number }} - {{ $prospect->originalAdvisor->full_name }}</dd></div>
            <div><dt class="font-bold text-slate-500">Asesor actual</dt><dd>{{ $prospect->currentAdvisor->advisor_number }} - {{ $prospect->currentAdvisor->full_name }}</dd></div>
            <div><dt class="font-bold text-slate-500">Origen</dt><dd>{{ match($prospect->source) { 'advisor_manual' => 'Registro manual del asesor', 'advisor_qr' => 'QR del asesor', default => 'Registro CRM' } }}</dd></div>
            <div><dt class="font-bold text-slate-500">Fecha y hora de captación</dt><dd>{{ \App\Support\InvestmentProspectPresenter::capturedAt($prospect->captured_at, true) }}</dd></div>
            <div><dt class="font-bold text-slate-500">Consentimiento de contacto</dt><dd>{{ $prospect->contact_consent_at ? \App\Support\InvestmentProspectPresenter::capturedAt($prospect->contact_consent_at, true) : 'No registrado' }}</dd></div>
        </dl>
        <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
            <h3 class="font-black text-[#0b1f3a]">Calidad de atención</h3>
            @if($prospect->service_rating)
                <p class="mt-2 text-lg font-bold">{{ \App\Support\InvestmentProspectServiceRating::emoji($prospect->service_rating) }} {{ \App\Support\InvestmentProspectServiceRating::label($prospect->service_rating) }}</p>
                <p class="mt-1 text-sm text-slate-600">Fecha: {{ \App\Support\InvestmentCrmDate::format($prospect->service_rating_at) }}</p>
                <p class="text-sm text-slate-600">Origen: {{ $prospect->service_rating_source === 'public_form' ? 'Formulario del prospecto' : 'Origen registrado' }}</p>
            @else
                <p class="mt-2 text-slate-500">Sin calificación</p>
            @endif
        </div>
        <div class="mt-6"><a class="btn-secondary" href="{{ route('investments.prospects.index') }}">Volver al listado</a></div>
    </section>
    @can('changeStatus',$prospect)<section class="section-card mt-5"><h3 class="font-black">Cambiar estado</h3><form class="mt-3 grid gap-3 md:grid-cols-3" method="post" action="{{ route('investments.prospects.status',$prospect) }}">@csrf @method('patch')<select class="form-input" name="status">@foreach(app(\App\Services\InvestmentProspectWorkflowService::class)->allowedTargets($prospect,auth()->user()) as $target)<option value="{{ $target }}">{{ \App\Support\InvestmentProspectStatus::label($target) }}</option>@endforeach</select><input class="form-input" name="reason" placeholder="Motivo (obligatorio si es perdido)"><button class="btn-primary">Actualizar estado</button></form></section>@endcan
    @can('createInteraction',$prospect)<section class="section-card mt-5"><h3 class="font-black">Registrar interacción</h3><form class="mt-3 grid gap-3 md:grid-cols-2" method="post" action="{{ route('investments.prospects.interactions.store',$prospect) }}">@csrf<select class="form-input" name="type">@foreach($types as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select><select class="form-input" name="channel">@foreach($channels as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select><input class="form-input" type="datetime-local" name="occurred_at" value="{{ \App\Support\InvestmentCrmDate::inputValue(now()) }}"><input class="form-input" type="datetime-local" name="next_follow_up_at"><textarea class="form-input md:col-span-2" name="notes" placeholder="Nota comercial" rows="3"></textarea><button class="btn-primary">Guardar interacción</button></form></section>@endcan
    <section class="section-card mt-5"><h3 class="font-black">Interacciones</h3>@forelse($prospect->interactions as $i)<div class="mt-3 border-t pt-3"><strong>{{ \App\Support\InvestmentProspectInteractionType::typeLabel($i->type) }}</strong> · {{ \App\Support\InvestmentCrmDate::format($i->occurred_at) }}<p>{{ $i->notes }}</p></div>@empty<p class="mt-3 text-slate-500">Sin interacciones.</p>@endforelse</section>
    <section class="section-card mt-5"><h3 class="font-black">Historial de estados</h3>@foreach($prospect->statusHistory as $h)<p class="mt-2">{{ \App\Support\InvestmentProspectStatus::label($h->to_status) }} · {{ \App\Support\InvestmentCrmDate::format($h->changed_at) }} @if($h->reason) — {{ $h->reason }}@endif</p>@endforeach</section>
    @can('reassign',$prospect)<section class="section-card mt-5"><h3 class="font-black">Reasignar asesor</h3><form class="mt-3 grid gap-3 md:grid-cols-3" method="post" action="{{ route('investments.prospects.reassign',$prospect) }}">@csrf @method('patch')<select class="form-input" name="advisor_id">@foreach($advisors as $a)<option value="{{ $a->id }}">{{ $a->advisor_number }} - {{ $a->full_name }}</option>@endforeach</select><input class="form-input" name="reason" placeholder="Motivo"><button class="btn-primary">Reasignar</button></form><h4 class="mt-5 font-bold">Historial de asignaciones</h4>@foreach($prospect->assignments as $a)<p class="mt-2">{{ $a->fromAdvisor?->advisor_number ?? 'Captación' }} → {{ $a->toAdvisor->advisor_number }} · {{ \App\Support\InvestmentCrmDate::format($a->assigned_at) }}</p>@endforeach</section>@endcan
</x-layouts.app>
