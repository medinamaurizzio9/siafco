<!doctype html>
<html lang="es"><head><meta charset="utf-8"><style>
@page{margin:12mm 14mm}*{box-sizing:border-box}body{margin:0;color:#10213d;font-family:DejaVu Sans,sans-serif;font-size:10px;line-height:1.35}.receipt{border:1px solid #cbd5e1;padding:9mm;page-break-inside:avoid}.header-table,.info-table,.detail-table,.cash-table{width:100%;border-collapse:collapse}.header-table td{vertical-align:top}.logo-cell{width:22%}.logo{max-width:70px;max-height:58px}.brand-cell{width:44%;padding:2px 8px}.brand{font-size:14px;font-weight:800;text-transform:uppercase}.system{color:#b28a24;font-size:10px;font-weight:800;letter-spacing:.16em;text-transform:uppercase}.receipt-cell{width:34%;text-align:right}.receipt-title{margin:0;font-size:28px;font-weight:800;line-height:1;letter-spacing:.03em}.receipt-number{margin-top:7px;color:#b28a24;font-size:12px;font-weight:800}.gold-rule{margin:8px 0 7px;border-top:2px solid #b28a24}.meta-row{width:100%;margin-bottom:8px;border-collapse:collapse}.meta-row td{width:50%;padding:2px 0}.meta-row td:last-child{text-align:right}.label{color:#64748b;font-size:8px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}.value{margin-top:1px;font-size:10.5px;font-weight:700}.section{margin-top:8px;page-break-inside:avoid}.section-title{margin:0 0 4px;padding-bottom:3px;border-bottom:1px solid #b28a24;font-size:9px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.info-table td{width:33.33%;padding:4px 8px 4px 0;vertical-align:top}.detail-table th,.detail-table td,.cash-table th,.cash-table td{padding:5px 7px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}.detail-table th,.cash-table th{width:31%;color:#64748b;font-size:8.5px;font-weight:800;text-transform:uppercase}.detail-table td,.cash-table td{font-weight:700}.status{color:#166534;font-weight:800}.total-table{width:100%;margin-top:9px;border-collapse:collapse;page-break-inside:avoid}.total-table td{padding:8px 10px;background:#10213d;color:#fff}.total-label{font-size:10px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.total-value{text-align:right;font-size:18px;font-weight:800}.footer{margin:10px 0 0;padding-top:6px;border-top:1px solid #cbd5e1;color:#475569;text-align:center;font-size:8.5px}
</style></head><body>
@php
    $affiliate = $payment->affiliate;
    $method = $payment->source === 'office_qr' ? 'QR / Transferencia' : ($payment->payment_method === 'efectivo' && $payment->source === 'office_cash' ? 'Efectivo / Pago en oficina' : ucfirst((string) $payment->payment_method));
    $amount = (float) ($payment->paid_amount ?? $payment->amount);
    $date = $payment->confirmed_at ?? $payment->paid_at ?? $payment->payment_date;
@endphp
<main class="receipt">
<table class="header-table"><tr>
<td class="logo-cell">@if($logoSrc ?? null)<img class="logo" src="{{ $logoSrc }}" alt="Logo institucional">@endif</td>
<td class="brand-cell"><div class="brand">{{ $institution->institution_name ?: 'COOPERATIVA TIERRA BENDITA' }}</div><div class="system">SIAFCO</div></td>
<td class="receipt-cell"><h1 class="receipt-title">RECIBO</h1><div class="receipt-number">{{ $payment->receipt_number ?: 'SIN NUMERO' }}</div></td>
</tr></table>
<div class="gold-rule"></div>
<table class="meta-row"><tr><td><div class="label">Fecha</div><div class="value">{{ $date?->format('d/m/Y') ?? 'Sin fecha' }}</div></td><td><div class="label">Hora</div><div class="value">{{ $date?->format('H:i') ?? 'Sin hora' }}</div></td></tr></table>
<section class="section"><h2 class="section-title">Datos del afiliado</h2><table class="info-table"><tr>
<td><div class="label">Nombre completo</div><div class="value">{{ $affiliate?->full_name ?? 'Afiliado no disponible' }}</div></td>
<td><div class="label">CI</div><div class="value">{{ $affiliate?->ci ?? 'No disponible' }}</div></td>
<td><div class="label">Código de afiliado</div><div class="value">{{ $affiliate?->registration_number ?? 'No registrado' }}</div></td>
</tr></table></section>
<section class="section"><h2 class="section-title">Detalle del pago</h2><table class="detail-table">
<tr><th>Concepto</th><td>Afiliación</td></tr><tr><th>Sector</th><td>{{ $affiliate?->sector?->name ?? 'Sin sector' }}</td></tr>
<tr><th>Plan</th><td>{{ $affiliate?->plan?->name ?? $payment->plan?->name ?? 'Sin plan' }}</td></tr><tr><th>Método de pago</th><td>{{ $method }}</td></tr>
@if($payment->reference_number)<tr><th>Referencia</th><td>{{ $payment->reference_number }}</td></tr>@endif
<tr><th>Estado</th><td class="status">{{ mb_strtoupper($statusLabel) }}</td></tr></table></section>
<section class="section"><h2 class="section-title">Datos de caja</h2><table class="cash-table">
<tr><th>Registrado por</th><td>{{ $payment->registrar?->name ?? 'No registrado' }}</td></tr>
<tr><th>Verificado por</th><td>{{ $payment->cashier?->name ?? 'No registrado' }}</td></tr>
@if($payment->observations)<tr><th>Observaciones</th><td>{{ $payment->observations }}</td></tr>@endif
</table></section>
<table class="total-table"><tr><td class="total-label">Total pagado</td><td class="total-value">{{ $payment->currency ?? 'BOB' }} {{ number_format($amount, 2) }}</td></tr></table>
<p class="footer">Este documento acredita el pago registrado en el sistema.</p>
</main></body></html>
