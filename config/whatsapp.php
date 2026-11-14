<?php

return [
    'gateway' => [
        'enabled' => env('WA_GATEWAY_ENABLED', true),
        'base_url' => env('WA_GATEWAY_BASE_URL', 'https://jokiin35.web.id'),
        'api_key' => env('WA_GATEWAY_API_KEY'),
        'session_id' => env('WA_GATEWAY_SESSION_ID', 'gudangtoko-main'),
        'timeout' => (int) env('WA_GATEWAY_TIMEOUT', 15),
    ],
];
