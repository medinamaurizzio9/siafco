<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Credencial oficial de afiliado</title>
    <meta name="author" content="Cooperativa Tierra Bendita - SIAFCO">
    <meta name="subject" content="AFILIACIÓN · CÉDULA · NÚMERO · INSTITUCIÓN · VERSIÓN · EDUCACIÓN">
    <style>
        {!! file_get_contents(resource_path('css/credential.css')) !!}

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
        .credential-pdf-stage {
            position: relative;
            width: 85.6mm;
            height: 53.98mm;
            overflow: hidden;
        }
        .credential-card--pdf {
            position: absolute;
            top: 0;
            left: 0;
            font-family: "DejaVu Sans", Arial, sans-serif;
            transform: scale(0.38054);
            transform-origin: top left;
            page-break-inside: avoid;
        }
        .credential-card--pdf,
        .credential-card--pdf * {
            font-family: "DejaVu Sans", Arial, sans-serif;
        }
        .credential-card--pdf .credential-header {
            position: absolute;
            top: 0;
            left: 0;
            display: block;
            width: 850px;
            height: 94px;
        }
        .credential-card--pdf .credential-logo {
            position: absolute;
            top: 11px;
            left: 24px;
        }
        .credential-card--pdf .credential-institution {
            position: absolute;
            top: 20px;
            left: 108px;
            width: 570px;
        }
        .credential-card--pdf .credential-header-id {
            position: absolute;
            top: 26px;
            left: 650px;
            width: 135px;
        }
        .credential-card--pdf .credential-body {
            position: absolute;
            top: 94px;
            left: 0;
            display: block;
            width: 850px;
            height: 412px;
        }
        .credential-card--pdf .credential-information {
            position: absolute;
            top: 14px;
            left: 24px;
            width: 620px;
            height: 390px;
        }
        .credential-card--pdf .credential-title {
            position: absolute;
            top: 0;
            left: 0;
        }
        .credential-card--pdf .credential-data-grid {
            position: absolute;
            top: 48px;
            left: 0;
            display: block;
            width: 590px;
            height: 180px;
        }
        .credential-card--pdf .credential-primary-data {
            position: absolute;
            top: 0;
            left: 0;
            display: block;
            width: 330px;
        }
        .credential-card--pdf .credential-secondary-data {
            position: absolute;
            top: 0;
            left: 350px;
            display: block;
            width: 225px;
        }
        .credential-card--pdf .credential-field {
            margin-bottom: 9px;
        }
        .credential-card--pdf .credential-verification {
            position: absolute;
            top: 190px;
            left: 0;
            display: block;
            width: 590px;
            height: 191px;
            margin: 0;
        }
        .credential-card--pdf .credential-qr {
            position: absolute;
            top: 0;
            left: 0;
            display: block;
        }
        .credential-card--pdf .credential-qr-copy {
            position: absolute;
            top: 64px;
            left: 203px;
            width: 180px;
        }
        .credential-card--pdf .credential-photo-column {
            position: absolute;
            top: 35px;
            left: 625px;
            display: block;
            width: 155px;
            text-align: center;
        }
        .credential-card--pdf .credential-photo-label,
        .credential-card--pdf .credential-photo-frame,
        .credential-card--pdf .credential-status {
            margin-bottom: 7px;
        }
        .credential-card--pdf .credential-photo,
        .credential-card--pdf .credential-photo-placeholder {
            display: block;
        }
        .credential-card--pdf .credential-status {
            display: block;
            width: 155px;
        }
    </style>
</head>
<body>
    <div class="credential-pdf-stage">
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
    </div>
</body>
</html>
