<x-layouts.app title="Reserva #{{ $reservation->id }}">
    <div class="section-card">
        <dl class="grid gap-3 md:grid-cols-3">
            <div><dt class="font-bold text-slate-500">Accionista</dt><dd>{{ $reservation->investor->person->full_name }}</dd></div>
            <div><dt class="font-bold text-slate-500">Acciones</dt><dd>{{ $reservation->shares_quantity }}</dd></div>
            <div><dt class="font-bold text-slate-500">Total</dt><dd>Bs {{ number_format($reservation->total_amount, 2) }}</dd></div>
            <div><dt class="font-bold text-slate-500">Pagado</dt><dd>Bs {{ number_format($reservation->amount_paid, 2) }}</dd></div>
            <div><dt class="font-bold text-slate-500">Vencimiento</dt><dd>{{ $reservation->expiration_date->format('d/m/Y') }}</dd></div>
            <div><dt class="font-bold text-slate-500">Estado</dt><dd><span class="badge">{{ $reservation->status }}</span></dd></div>
        </dl>
        <div class="mt-5 flex flex-wrap gap-3">
            @if($reservation->status === 'active')
                <form method="post" action="{{ route('investments.reservations.convert', $reservation) }}">@csrf <button class="btn-primary">Convertir en inversion</button></form>
            @endif
        </div>
    </div>
    @if(in_array($reservation->status, ['active','pending'], true))
        <form class="section-card mt-5 grid gap-3 md:grid-cols-[180px_1fr_auto]" method="post" enctype="multipart/form-data" action="{{ route('investments.reservations.close', $reservation) }}">
            @csrf
            <select class="form-input" name="status"><option value="expired">expired</option><option value="cancelled">cancelled</option></select>
            <input class="form-input" name="closure_reason" placeholder="Motivo obligatorio">
            <button class="btn-danger">Cerrar reserva</button>
            <input class="form-input md:col-span-3" type="file" name="support_document">
        </form>
    @endif
</x-layouts.app>
