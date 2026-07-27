<x-layouts.app title="Estado de solicitud">
    <section class="section-card mx-auto max-w-2xl">
        <p class="text-sm font-bold text-slate-500">Paso 3 de 3</p>
        <h1 class="text-2xl font-black text-[#0b1f3a]">Seguimiento de afiliación</h1>
        <dl class="mt-6 grid gap-4 sm:grid-cols-2">
            <div><dt class="text-sm font-bold text-slate-500">Código</dt><dd>{{ $application->request_code }}</dd></div>
            <div><dt class="text-sm font-bold text-slate-500">Nombre</dt><dd>{{ $application->person->full_name }}</dd></div>
            <div><dt class="text-sm font-bold text-slate-500">Sector</dt><dd>{{ $application->sector->name }}</dd></div>
            <div><dt class="text-sm font-bold text-slate-500">Plan</dt><dd>{{ $application->plan->name }}</dd></div>
            <div><dt class="text-sm font-bold text-slate-500">Monto</dt><dd>BOB {{ number_format($application->amount_due, 2) }}</dd></div>
            <div><dt class="text-sm font-bold text-slate-500">Estado</dt><dd><span class="badge">{{ str_replace('_', ' ', $application->status) }}</span></dd></div>
            <div><dt class="text-sm font-bold text-slate-500">Registro</dt><dd>{{ $application->submitted_at->format('d/m/Y H:i') }}</dd></div>
            <div><dt class="text-sm font-bold text-slate-500">Pago enviado</dt><dd>{{ $application->payment_submitted_at?->format('d/m/Y H:i') ?: 'Aún no enviado' }}</dd></div>
        </dl>
        @if($application->rejection_reason)<div class="mt-6 border border-red-200 bg-red-50 p-4 text-red-900"><strong>Observación:</strong> {{ $application->rejection_reason }}</div>@endif
        @if(in_array($application->status, ['pending_payment','rejected']))<a class="btn-primary mt-6" href="{{ route('public-affiliation.payment', $application) }}">Registrar o corregir pago</a>@endif
    </section>
</x-layouts.app>
