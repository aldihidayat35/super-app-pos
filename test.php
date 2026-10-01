<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$locationId = 2;
$ids = App\Models\WarehouseLocation::query()->forWorkLocation($locationId)->pluck('id')->map(fn ($id) => (int) $id)->all();
echo json_encode($ids);
