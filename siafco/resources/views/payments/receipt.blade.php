<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 12px; }
        .header { border-bottom: 3px solid #d4af37; padding-bottom: 14px; margin-bottom: 18px; }
        .brand { color: #0b1f3a; font-size: 22px; font-weight: 800; }
        .muted { color: #64748b; }
        .grid { width: 100%; border-collapse: collapse; margin-top: 14px; }
        .grid th { text-align: left; width: 32%; background: #f8fafc; }
        .grid th, .grid td { border: 1px solid #cbd5e1; padding: 8px; }
        .total { margin-top: 16px; padding: 12px; border: 2px solid #0b1f3a; font-size: 18px; font-weight: 800; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">{{ $institution->institution_name ?: 'SIAFCO' }}</div>
        <div class="muted">Recibo institucional de pago de afiliacion</div>
    </div>

    <h1>Recibo {{ $payment->receipt_number ?: 'SIN NUMERO' }}</h1>
    <table class="grid">
        <tr><th>Afiliado</th><td>{{ $payment->affiliate?->full_name ?? 'Afiliado no disponible' }}</td></tr>
        <tr><th>CI</th><td>{{ $payment->affiliate?->ci ?? 'No disponible' }}</td></tr>
        <tr><th>Codigo</th><td>{{ $payment->affiliate?->registration_number ?? 'No registrado' }}</td></tr>
        <tr><th>Metodo</th><td>{{ ucfirst((string) $payment->payment_method) }}</td></tr>
        <tr><th>Referencia</th><td>{{ $payment->reference_number ?: ($payment->transaction_number ?: 'Sin referencia') }}</td></tr>
        <tr><th>Fecha de pago</th><td>{{ $payment->paid_at?->format('d/m/Y H:i') ?? $payment->payment_date?->format('d/m/Y') ?? 'Sin fecha' }}</td></tr>
        <tr><th>Registrador</th><td>{{ $payment->registrar?->name ?? 'No registrado' }}</td></tr>
        <tr><th>Confirmador</th><td>{{ $payment->cashier?->name ?? 'No confirmado' }}</td></tr>
        <tr><th>Estado</th><td>{{ $statusLabel }}</td></tr>
    </table>
    <div class="total">{{ $payment->currency ?? 'BOB' }} {{ number_format((float) ($payment->paid_amount ?? $payment->amount), 2) }}</div>
    <p class="muted">Este recibo certifica el registro interno del pago en SIAFCO. El comprobante bancario, si existe, se conserva como evidencia privada.</p>
</body>
</html>
