<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        .box { border: 1px solid #d1d5db; padding: 18px; }
        .header { border-bottom: 1px solid #d1d5db; padding-bottom: 12px; margin-bottom: 14px; }
        h1 { color: #0b1f3a; margin: 0; font-size: 22px; }
        .gold { color: #d4af37; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        td { padding: 7px; border-bottom: 1px solid #e5e7eb; }
        .right { text-align: right; }
        .total td { font-size: 17px; font-weight: bold; border-top: 2px solid #0b1f3a; }
        .signatures { margin-top: 70px; width: 100%; }
        .signatures td { width: 50%; text-align: center; border-bottom: 0; }
        .line { border-top: 1px solid #111827; padding-top: 6px; margin: 0 30px; }
    </style>
</head>
<body>
    <div class="box">
        @include('investments.receipts._receipt', ['receipt' => $receipt])
    </div>
</body>
</html>
