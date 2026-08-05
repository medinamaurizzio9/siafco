<?php

return [
    'public_affiliation_photo_max_kb' => (int) env('AFFILIATION_PHOTO_MAX_KB', 4096),
    'public_affiliation_receipt_max_kb' => (int) env('AFFILIATION_RECEIPT_MAX_KB', 6144),
    'terms_version' => env('SIAFCO_TERMS_VERSION', '2026.1'),
    'privacy_version' => env('SIAFCO_PRIVACY_VERSION', '2026.1'),
    'credential_version' => env('CREDENTIAL_VERSION', '2026.1'),
    'credential_export' => [
        'chrome_binary' => env('CHROME_BINARY'),
        'enable_png' => env('CREDENTIAL_PNG_ENABLED', true),
        'allow_gd_fallback' => false,
    ],
    'institutional_website' => env('INSTITUTIONAL_WEBSITE', 'www.cooperativatierrabendita.com'),
];
