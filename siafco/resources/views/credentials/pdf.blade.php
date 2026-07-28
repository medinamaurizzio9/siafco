<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Credencial oficial de afiliado</title>
    <meta name="author" content="Cooperativa Tierra Bendita - SIAFCO">
    <meta name="subject" content="AFILIACIÓN · CÉDULA · NÚMERO · INSTITUCIÓN · VERSIÓN · EDUCACIÓN">
    <style>
        {!! str_replace('transform: scale(var(--credential-scale, 1));', '', file_get_contents(resource_path('css/credential.css'))) !!}

        @page { size: 85.6mm 53.98mm; margin: 0; }
        *, *::before, *::after { box-sizing: border-box; }
        html, body {
            width: 85.6mm;
            height: 53.98mm;
            margin: 0;
            padding: 0;
            overflow: hidden;
            background: #fff;
            font-family: "DejaVu Sans", Arial, sans-serif;
        }
        .credential-pdf-page {
            position: relative;
            width: 85.6mm;
            height: 53.98mm;
            margin: 0;
            padding: 0;
            overflow: hidden;
            page-break-before: avoid;
            page-break-after: avoid;
            page-break-inside: avoid;
        }
    </style>
</head>
<body>
    <main class="credential-pdf-page">
        @include('credenciales.card', [
            'affiliate' => $affiliate,
            'credential' => null,
            'credentialData' => $credentialData,
            'institution' => $institution,
            'mode' => 'pdf',
            'logoSrc' => $logoSrc,
            'photoSrc' => $photoSrc,
            'qrSrc' => $qrSrc,
        ])
    </main>
</body>
</html>
