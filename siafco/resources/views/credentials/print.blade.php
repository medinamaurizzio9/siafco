<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Imprimir credencial</title>
    <style>
        {!! str_replace('transform: scale(var(--credential-scale, 1));', '', file_get_contents(resource_path('css/credential.css'))) !!}

        *, *::before, *::after { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            background: #eef1f5;
            font-family: "DejaVu Sans", Arial, sans-serif;
        }
        .credential-print-instructions {
            max-width: 620px;
            margin: 20px auto;
            padding: 16px 20px;
            border: 1px solid #d8a928;
            background: #fff;
            color: #0b1f3a;
            font-size: 14px;
            line-height: 1.5;
        }
        .credential-print-page {
            width: 85.6mm;
            height: 53.98mm;
            margin: 0 auto 20px;
            padding: 0;
            overflow: hidden;
            background: #fff;
        }
        @page {
            size: 85.6mm 53.98mm;
            margin: 0;
        }
        @media print {
            html,
            body {
                width: 85.6mm !important;
                height: 53.98mm !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: hidden !important;
                background: #fff !important;
            }
            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
            .credential-print-page {
                width: 85.6mm !important;
                height: 53.98mm !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: hidden !important;
                page-break-before: avoid !important;
                page-break-after: avoid !important;
                page-break-inside: avoid !important;
                break-before: avoid-page !important;
                break-after: avoid-page !important;
                break-inside: avoid-page !important;
            }
            .credential-card--print {
                width: 85.6mm !important;
                height: 53.98mm !important;
                min-width: 85.6mm !important;
                min-height: 53.98mm !important;
                max-width: 85.6mm !important;
                max-height: 53.98mm !important;
                margin: 0 !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <aside class="credential-print-instructions no-print">
        <strong>Para una impresión exacta, seleccione:</strong>
        <div>Tamaño de papel: personalizado 85,6 × 53,98 mm</div>
        <div>Márgenes: ninguno · Escala: 100 % · Desactivar encabezados y pies</div>
    </aside>

    <main class="credential-print-page">
        @include('credenciales.card', [
            'affiliate' => $affiliate,
            'credential' => $credential,
            'credentialData' => $credentialData,
            'institution' => $institution,
            'mode' => 'print',
            'logoSrc' => $institution->logoUrl(),
            'photoSrc' => $affiliate->photo_path ? Storage::url($affiliate->photo_path) : null,
            'qrSrc' => Storage::url($credential->qr_path),
        ])
    </main>

    <script>
        window.addEventListener('load', async () => {
            if (document.fonts?.ready) {
                await document.fonts.ready;
            }

            const images = [...document.querySelectorAll('.credential-print-page img')];
            await Promise.all(images.map((image) => {
                if (image.complete) {
                    return Promise.resolve();
                }

                return new Promise((resolve) => {
                    image.addEventListener('load', resolve, { once: true });
                    image.addEventListener('error', resolve, { once: true });
                });
            }));

            requestAnimationFrame(() => window.print());
        });
    </script>
</body>
</html>
