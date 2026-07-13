<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Services\System\HealthCheckService;
use Illuminate\Contracts\View\View;

class HealthController extends Controller
{
    public function __invoke(HealthCheckService $healthCheck): View
    {
        return view('system.health', ['checks' => $healthCheck->run()]);
    }
}
