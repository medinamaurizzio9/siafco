<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 14mm 16mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 10.5px; line-height: 1.28; margin: 0; }
        .receipt { border: 1px solid #111827; padding: 11mm 10mm 9mm; page-break-inside: avoid; }
        .header { border-bottom: 2px solid #111827; padding-bottom: 7px; margin-bottom: 9px; text-align: center; }
        .logo { max-width: 64px; max-height: 54px; object-fit: contain; margin-bottom: 5px; }
        .brand { font-size: 16px; font-weight: 800; letter-spacing: .035em; text-transform: uppercase; }
        .system { margin-top: 1px; font-size: 11px; font-weight: 800; letter-spacing: .18em; text-transform: uppercase; }
        .title { margin-top: 3px; font-size: 13px; font-weight: 800; text-transform: uppercase; }
        .meta { margin-top: 8px; width: 100%; border-collapse: collapse; }
        .meta td { padding: 2px 0; vertical-align: top; }
        .meta .label { width: 95px; font-weight: 800; text-transform: uppercase; }
        .section-title { margin: 10px 0 5px; border-bottom: 1px solid #9ca3af; padding-bottom: 2px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid th { width: 34%; background: #f3f4f6; text-align: left; font-weight: 800; }
        .grid th, .grid td { border: 1px solid #9ca3af; padding: 4px 6px; vertical-align: top; }
        .amount { margin-top: 10px; border: 2px solid #111827; padding: 7px 9px; text-align: right; font-size: 15px; font-weight: 800; }
        .footer { margin-top: 11px; border-top: 1px solid #9ca3af; padding-top: 7px; text-align: center; font-size: 9.5px; }
        .no-break { page-break-inside: avoid; }
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
            @if($logoSrc ?? null)
                <img class="logo" src="{{ $logoSrc }}" alt="Logo institucional">
            @endif
            <div class="brand">{{ $institution->institution_name ?: 'COOPERATIVA TIERRA BENDITA' }}</div>
            <div class="system">SIAFCO</div>
            <div class="title">Recibo oficial de pago</div>
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

        <div class="section-title">Detalle del pago</div>
        <table class="grid">
            <tr><th>Sector</th><td>{{ $affiliate?->sector?->name ?? 'Sin sector' }}</td></tr>
            <tr><th>Plan</th><td>{{ $affiliate?->plan?->name ?? $payment->plan?->name ?? 'Sin plan' }}</td></tr>
            <tr><th>Concepto</th><td>Afiliacion</td></tr>
            <tr><th>Metodo de pago</th><td>{{ $method }}</td></tr>
            <tr><th>Estado</th><td>{{ mb_strtoupper($statusLabel) }}</td></tr>
        </table>

        <div class="amount no-break">Monto recibido: {{ $payment->currency ?? 'BOB' }} {{ number_format($amount, 2) }}</div>

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
