<?php

use App\Models\WarehouseLocation;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$locationId = 2;
$ids = WarehouseLocation::query()->forWorkLocation($locationId)->pluck('id')->map(fn ($id) => (int) $id)->all();
echo json_encode($ids);
