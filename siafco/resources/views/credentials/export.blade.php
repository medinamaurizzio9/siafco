<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <style>
        {!! file_get_contents(resource_path('css/credential.css')) !!}

        *, *::before, *::after { box-sizing: border-box; }
        html, body {
            width: 850px;
            height: 540px;
            margin: 0;
            overflow: hidden;
            background: #fff;
        }
        .credential-card {
            font-family: Arial, "DejaVu Sans", sans-serif;
        }
    </style>
</head>
<body>
    @include('credenciales.card', [
        'affiliate' => $affiliate,
        'credential' => null,
        'credentialData' => $credentialData,
        'institution' => $institution,
        'mode' => 'image',
        'logoSrc' => $logoSrc,
        'photoSrc' => $photoSrc,
        'qrSrc' => $qrSrc,
    ])
</body>
</html>
