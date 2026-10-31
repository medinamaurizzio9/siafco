<?php

return [
    'document_max_kb' => (int) env('SIMPLE_INVESTMENT_DOCUMENT_MAX_KB', 10240),
    'projection_count' => (int) env('SIMPLE_INVESTMENT_PROJECTION_COUNT', 12),
    'schedule_timezone' => env('SIMPLE_INVESTMENT_SCHEDULE_TIMEZONE', 'America/La_Paz'),
];
