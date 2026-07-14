<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; }
        .head { border-bottom: 3px solid #d4af37; padding-bottom: 12px; margin-bottom: 20px; }
        .logo { width: 56px; height: 56px; object-fit: contain; vertical-align: middle; margin-right: 12px; }
        h1 { color: #0b1f3a; margin: 0; font-size: 24px; }
        h2 { color: #0b1f3a; font-size: 16px; margin-top: 24px; }
        .metric { display: inline-block; width: 23%; padding: 10px; background: #f1f5f9; margin-right: 1%; }
        .metric strong { display: block; color: #0b1f3a; font-size: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background: #0b1f3a; color: #d4af37; text-align: left; padding: 8px; }
        td { border-bottom: 1px solid #e5e7eb; padding: 8px; }
    </style>
</head>
<body>
    <div class="head">
        @if($institution->logoAbsolutePath() && is_file($institution->logoAbsolutePath()))
            <img class="logo" src="{{ $institution->logoAbsolutePath() }}">
        @endif
        <span>
            <h1>SIAFCO - Reporte basico</h1>
            <div>{{ $institution->institution_name }}</div>
        </span>
    </div>

    <div class="metric">Pendientes<strong>{{ $pendingPayments }}</strong></div>
    <div class="metric">Confirmados<strong>{{ $confirmedPayments }}</strong></div>
    <div class="metric">Credenciales<strong>{{ $credentials }}</strong></div>
    <div class="metric">Ingresos<strong>Bs {{ number_format($income, 2) }}</strong></div>

    <h2>Afiliados por sector</h2>
    <table>
        <thead><tr><th>Sector</th><th>Total</th></tr></thead>
        <tbody>
        @foreach($bySector as $row)
            <tr><td>{{ $row->sector->name }}</td><td>{{ $row->total }}</td></tr>
        @endforeach
        </tbody>
    </table>

    <h2>Afiliados por estado</h2>
    <table>
        <thead><tr><th>Estado</th><th>Total</th></tr></thead>
        <tbody>
        @foreach($byStatus as $status => $total)
            <tr><td>{{ $status }}</td><td>{{ $total }}</td></tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
