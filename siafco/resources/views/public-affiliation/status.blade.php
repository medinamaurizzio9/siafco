@php
    use App\Support\AffiliationStatusPresenter;
    $currentStep = AffiliationStatusPresenter::currentStep($application->status);
@endphp

<x-layouts.app title="Estado de solicitud">
    <div class="mx-auto max-w-3xl space-y-6">
        <section class="section-card">
            <div class="flex flex-col gap-3 border-b border-slate-200 pb-5 sm:flex-row sm:items-start sm:justify-between">
                <div><p class="text-sm font-bold text-slate-500">{{ $application->request_code }}</p><h1 class="text-2xl font-black text-[#0b1f3a]">Seguimiento de afiliación</h1></div>
                <p class="text-sm font-bold text-slate-500">Paso {{ $currentStep }} de 4</p>
            </div>
            <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                <div><dt class="text-sm font-bold text-slate-500">Nombre</dt><dd>{{ $application->person->full_name }}</dd></div>
                <div><dt class="text-sm font-bold text-slate-500">Sector</dt><dd>{{ $application->sector->name }}</dd></div>
                <div><dt class="text-sm font-bold text-slate-500">Plan</dt><dd>{{ $application->plan->name }}</dd></div>
                <div><dt class="text-sm font-bold text-slate-500">Monto</dt><dd>BOB {{ number_format($application->amount_due, 2) }}</dd></div>
                <div><dt class="text-sm font-bold text-slate-500">Estado</dt><dd class="mt-1"><x-affiliation-status :status="$application->status" size="sm" /></dd></div>
                <div><dt class="text-sm font-bold text-slate-500">Registro</dt><dd>{{ $application->submitted_at->format('d/m/Y H:i') }}</dd></div>
                <div><dt class="text-sm font-bold text-slate-500">Pago enviado</dt><dd>{{ $application->payment_submitted_at?->format('d/m/Y H:i') ?: 'Aún no enviado' }}</dd></div>
            </dl>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase text-slate-600">Estado de tu afiliación</p>
            <div class="mt-3"><x-affiliation-status :status="$application->status" size="lg" /></div>
            <p class="mt-4 text-sm leading-6 text-slate-700">{{ AffiliationStatusPresenter::description($application->status) }}</p>
            @if(AffiliationStatusPresenter::isPaymentSubmitted($application->status))
                <p class="mt-3 rounded border border-orange-200 bg-orange-50 p-3 text-sm font-medium text-orange-900">
                    Recibirás una actualización cuando Secretaría confirme o realice una observación sobre tu pago.
                </p>
            @endif
        </section>

        <section class="section-card">
            <h2 class="font-black text-[#0b1f3a]">Progreso de la afiliación</h2>
            <ol class="mt-5 grid gap-3 sm:grid-cols-4">
                @foreach(['Solicitud registrada','Pago enviado','Revisión de Secretaría','Afiliación activa'] as $index => $step)
                    @php($number = $index + 1)
                    @php($completed = $number < $currentStep || ($number === 2 && $currentStep >= 3))
                    @php($current = $number === $currentStep)
                    <li class="border-t-4 p-3 text-sm font-bold {{ $completed ? 'border-[#d4af37] bg-[#fff8df] text-[#0b1f3a]' : ($current ? 'border-[#0b1f3a] bg-slate-100 text-[#0b1f3a]' : 'border-slate-200 text-slate-400') }}">
                        <span aria-hidden="true">{{ $completed ? '✓' : ($current ? '●' : '○') }}</span> {{ $step }}
                    </li>
                @endforeach
            </ol>
        </section>

        @if(AffiliationStatusPresenter::isRejected($application->status) || $application->rejection_reason || $application->observations)
            <section class="rounded-lg border border-red-200 bg-red-50 p-5 text-red-950">
                <h2 class="font-black">Observación de Secretaría</h2>
                <p class="mt-2 text-sm leading-6">{{ $application->rejection_reason ?: ($application->observations ?: 'Comunícate con Secretaría para conocer el detalle de la observación.') }}</p>
                <p class="mt-3 text-sm font-semibold">Comunícate con Secretaría para corregir la observación.</p>
            </section>
        @endif

        @if(in_array($application->status, ['pending_payment','rejected'], true))
            <a class="btn-primary w-full sm:w-auto" href="{{ route('public-affiliation.payment', $application) }}">Registrar o corregir pago</a>
        @endif
    </div>
</x-layouts.app>
