<?php

return [
    'currency' => env('APP_CURRENCY', 'IDR'),
    'currency_locale' => env('APP_CURRENCY_LOCALE', 'id_ID'),
    'date_format' => env('APP_DATE_FORMAT', 'd/m/Y'),
    'datetime_format' => env('APP_DATETIME_FORMAT', 'd/m/Y H:i'),
    'barcode' => [
        'thermal_width_mm' => (float) env('BARCODE_THERMAL_WIDTH_MM', 80),
        'thermal_height_mm' => (float) env('BARCODE_THERMAL_HEIGHT_MM', 40),
    ],
    'document_number' => [
        'separator' => '/',
        'sequence_length' => 6,
    ],
];
