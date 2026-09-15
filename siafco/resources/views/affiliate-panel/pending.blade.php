<span class="sr-only">affiliate-panel-content {{ $affiliate?->registration_number }}</span>
@php($application = $affiliate->publicRequest)
<div class="affiliate-screen">
    <section class="affiliate-card">
        <div class="flex flex-col gap-3 border-b border-slate-200 pb-5 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-black uppercase text-[#b8942f]">{{ $application?->request_code ?: 'Solicitud en proceso' }}</p>
                <h2 class="text-2xl font-black text-[#0b1f3a]">{{ $affiliate->full_name }}</h2>
            </div>
            <x-affiliation-status :status="$application?->status ?: $affiliate->status" size="sm" />
        </div>
        <dl class="mt-5 grid gap-4 sm:grid-cols-2">
            <div><dt>Estado</dt><dd>{{ \App\Support\AffiliationStatusPresenter::label($application?->status ?: $affiliate->status) }}</dd></div>
            <div><dt>Plan seleccionado</dt><dd>{{ $affiliate->plan->name }}</dd></div>
            <div><dt>Monto</dt><dd>BOB {{ number_format($application?->amount_due ?? $latestPayment?->amount, 2) }}</dd></div>
            <div><dt>Acceso</dt><dd>Bloqueado hasta la activación</dd></div>
        </dl>
        @if($application)
            <a class="btn-primary mt-6 min-h-12 w-full sm:w-auto" href="{{ route('public-affiliation.status',$application) }}">Consultar estado</a>
        @endif
    </section>
</div>
