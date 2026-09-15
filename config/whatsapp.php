<?php

return [
    'gateway' => [
        'enabled' => env('WA_GATEWAY_ENABLED', false),
        'base_url' => env('WA_GATEWAY_BASE_URL', 'http://127.0.0.1:3000'),
        'api_key' => env('WA_GATEWAY_API_KEY'),
        'session_id' => env('WA_GATEWAY_SESSION_ID', 'gudangtoko-main'),
        'timeout' => (int) env('WA_GATEWAY_TIMEOUT', 10),
    ],
];
