<?php

return [
    // Sembunyikan dari navigasi sementara; route dan izin tetap tersedia untuk peninjauan internal.
    'hidden_navigation_routes' => [
        'owner.dashboard',
        'sales.dashboard',
        'warehouse.dashboard',
        'retail.dashboard',
        'warehouse.batches.index',
        'reports.suppliers.index',
        'reports.shift-productivity.index',
        'sales.targets.index',
        'sales.admin.targets.index',
        'sales.performance',
        'admin.notifications.channels.index',
        'admin.notifications.templates.index',
        'admin.notifications.schedules.index',
        'admin.notifications.recipients.index',
        'admin.notifications.logs.index',
        'admin.notifications.alerts.index',
    ],
];
