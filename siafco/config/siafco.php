<?php

return [
    'public_affiliation_photo_max_kb' => (int) env('AFFILIATION_PHOTO_MAX_KB', 4096),
    'public_affiliation_receipt_max_kb' => (int) env('AFFILIATION_RECEIPT_MAX_KB', 6144),
    'public_affiliation_url' => env('PUBLIC_AFFILIATION_URL', 'https://siafco.viankagold.com/afiliacion'),
    'credential_version' => env('CREDENTIAL_VERSION', '2026.1'),
    'credential_browser_path' => env('CREDENTIAL_BROWSER_PATH'),
    'institutional_website' => env('INSTITUTIONAL_WEBSITE', 'www.cooperativatierrabendita.com'),
];
