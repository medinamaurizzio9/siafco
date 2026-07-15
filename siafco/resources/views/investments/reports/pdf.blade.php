<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:11px}table{width:100%;border-collapse:collapse}td,th{border:1px solid #ddd;padding:6px}th{background:#0b1f3a;color:#d4af37}</style></head>
<body>
    <h1>Reporte de inversiones</h1>
    <p>Capital invertido: Bs {{ number_format($summary['total_invested'], 2) }}</p>
    <p>Total pagado: Bs {{ number_format($summary['total_paid'], 2) }}</p>
    <table>
        <thead><tr><th>Accionista</th><th>CI</th><th>Numero</th><th>Estado</th><th>Capital</th></tr></thead>
        <tbody>
        @foreach($investors as $investor)
            <tr><td>{{ $investor->person->full_name }}</td><td>{{ $investor->person->ci }}</td><td>{{ $investor->investor_number }}</td><td>{{ $investor->status }}</td><td>Bs {{ number_format($investor->lots->sum('invested_capital'), 2) }}</td></tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
