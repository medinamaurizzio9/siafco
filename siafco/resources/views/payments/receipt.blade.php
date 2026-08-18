<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28mm 22mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 12px; line-height: 1.45; }
        .receipt { border: 1px solid #111827; padding: 18px; }
        .header { border-bottom: 2px solid #111827; padding-bottom: 12px; margin-bottom: 16px; text-align: center; }
        .brand { font-size: 20px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
        .title { margin-top: 4px; font-size: 16px; font-weight: 800; text-transform: uppercase; }
        .meta { margin-top: 14px; width: 100%; border-collapse: collapse; }
        .meta td { padding: 4px 0; vertical-align: top; }
        .meta .label { width: 130px; font-weight: 800; text-transform: uppercase; }
        .section-title { margin: 18px 0 8px; border-bottom: 1px solid #9ca3af; padding-bottom: 3px; font-weight: 800; text-transform: uppercase; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid th { width: 35%; background: #f3f4f6; text-align: left; font-weight: 800; }
        .grid th, .grid td { border: 1px solid #9ca3af; padding: 7px 8px; }
        .amount { margin-top: 16px; border: 2px solid #111827; padding: 10px 12px; text-align: right; font-size: 18px; font-weight: 800; }
        .footer { margin-top: 20px; border-top: 1px solid #9ca3af; padding-top: 10px; text-align: center; font-size: 11px; }
    </style>
</head>
<body>
    @php
        $affiliate = $payment->affiliate;
        $method = $payment->payment_method === 'efectivo' && $payment->source === 'office_cash'
            ? 'Efectivo / Pago en oficina'
            : ucfirst((string) $payment->payment_method);
        $amount = (float) ($payment->paid_amount ?? $payment->amount);
        $date = $payment->confirmed_at ?? $payment->paid_at ?? $payment->payment_date;
    @endphp

    <main class="receipt">
        <div class="header">
            <div class="brand">{{ $institution->institution_name ?: 'COOPERATIVA TIERRA BENDITA' }}</div>
            <div class="title">Recibo de pago</div>
        </div>

        <table class="meta">
            <tr><td class="label">N. recibo:</td><td>{{ $payment->receipt_number ?: 'SIN NUMERO' }}</td></tr>
            <tr><td class="label">Fecha:</td><td>{{ $date?->format('d/m/Y') ?? 'Sin fecha' }}</td></tr>
            <tr><td class="label">Hora:</td><td>{{ $date?->format('H:i') ?? 'Sin hora' }}</td></tr>
        </table>

        <div class="section-title">Datos del afiliado</div>
        <table class="grid">
            <tr><th>Nombre completo</th><td>{{ $affiliate?->full_name ?? 'Afiliado no disponible' }}</td></tr>
            <tr><th>CI</th><td>{{ $affiliate?->ci ?? 'No disponible' }}</td></tr>
            <tr><th>Codigo de afiliado</th><td>{{ $affiliate?->registration_number ?? 'No registrado' }}</td></tr>
        </table>

        <div class="section-title">Detalle</div>
        <table class="grid">
            <tr><th>Sector</th><td>{{ $affiliate?->sector?->name ?? 'Sin sector' }}</td></tr>
            <tr><th>Plan</th><td>{{ $affiliate?->plan?->name ?? $payment->plan?->name ?? 'Sin plan' }}</td></tr>
            <tr><th>Concepto</th><td>Afiliacion</td></tr>
            <tr><th>Metodo de pago</th><td>{{ $method }}</td></tr>
            <tr><th>Estado</th><td>{{ mb_strtoupper($statusLabel) }}</td></tr>
        </table>

        <div class="amount">Monto recibido: {{ $payment->currency ?? 'BOB' }} {{ number_format($amount, 2) }}</div>

        <div class="section-title">Datos de caja</div>
        <table class="grid">
            <tr><th>Recibido por</th><td>{{ $payment->cashier?->name ?? $payment->registrar?->name ?? 'No registrado' }}</td></tr>
            <tr><th>Usuario responsable</th><td>{{ $payment->registrar?->name ?? $payment->cashier?->name ?? 'No registrado' }}</td></tr>
            @if($payment->observations)
                <tr><th>Observacion</th><td>{{ $payment->observations }}</td></tr>
            @endif
        </table>

        <p class="footer">Este documento acredita el pago registrado en el sistema.</p>
    </main>
</body>
</html>
