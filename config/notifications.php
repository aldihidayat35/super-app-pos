<?php

return [
    'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta'),
    'dry_run' => env('NOTIFICATION_DRY_RUN', true),
    'daily_report_time' => env('DAILY_REPORT_TIME', '08:00'),
    'secure_link_ttl_minutes' => (int) env('SECURE_REPORT_LINK_TTL_MINUTES', 1440),
    'http' => [
        'timeout' => (int) env('NOTIFICATION_HTTP_TIMEOUT', 10),
        'retry_attempts' => (int) env('NOTIFICATION_RETRY_ATTEMPTS', 3),
    ],
    'whatsapp' => [
        'enabled' => env('WA_API_ENABLED', false),
        'base_url' => env('WA_API_BASE_URL'),
        'token' => env('WA_API_TOKEN'),
        'sender' => env('WA_API_SENDER'),
        'auth_type' => env('WA_API_AUTH_TYPE', 'bearer'),
    ],
    'telegram' => [
        'enabled' => env('TELEGRAM_ENABLED', false),
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'default_chat_id' => env('TELEGRAM_DEFAULT_CHAT_ID'),
    ],
    'template_variables' => [
        'daily_report' => ['report_date', 'revenue', 'gross_margin', 'margin_percent', 'stock_value', 'critical_stock_count', 'receivable_outstanding', 'overdue_receivable', 'cash_difference', 'attendance_late', 'anomaly_open', 'pending_approval', 'secure_link'],
        'critical_stock' => ['product_name', 'sku', 'location_name', 'available_quantity', 'minimum_stock', 'secure_link'],
        'receivable_due' => ['customer_name', 'invoice_number', 'due_date', 'outstanding_amount', 'secure_link'],
        'pending_order' => ['order_number', 'customer_name', 'status', 'secure_link'],
        'overpricing' => ['product_name', 'price', 'minimum_price', 'margin_percent', 'secure_link'],
        'approval' => ['document_number', 'approval_type', 'requester_name', 'secure_link'],
        'closing_difference' => ['shift_number', 'branch_name', 'difference_amount', 'secure_link'],
    ],
];
