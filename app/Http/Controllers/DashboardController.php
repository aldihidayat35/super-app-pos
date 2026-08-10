<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Reports\RetailDashboardController;
use App\Http\Controllers\Warehouse\WarehouseDashboardController;
use App\Models\User;
use App\Services\Reports\ReportMetricService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ReportMetricService $reports): View
    {
        /** @var User $user */
        $user = $request->user();
        $filters = $reports->filters($user, $request->query());

        $view = match (true) {
            $user->hasAnyRole(['owner', 'owner_viewer', 'owner_approver']) => 'dashboards.owner',
            $user->hasAnyRole(['kepala_gudang', 'staff_gudang', 'picker_packer', 'purchasing']) => 'warehouse.dashboard',
            $user->hasAnyRole(['kepala_toko', 'kasir', 'supervisor_shift']) => 'dashboards.retail',
            default => 'dashboards.super-admin',
        };

        if ($view === 'dashboards.owner') {
            return view($view, [
                'filters' => $filters,
                'dashboard' => $reports->ownerDashboard($user, $filters),
                'definitions' => $reports->definitions('daily'),
                'workLocations' => DB::table('work_locations')
                    ->whereIn('id', $user->permittedWorkLocationIds())
                    ->where('is_active', true)
                    ->orderBy('type')
                    ->orderBy('name')
                    ->get(['id', 'name', 'type']),
            ]);
        }

        if ($view === 'warehouse.dashboard') {
            return app(WarehouseDashboardController::class)->renderForUser($request, $reports);
        }

        if ($view === 'dashboards.retail') {
            return app(RetailDashboardController::class)->renderForUser($request, $reports);
        }

        return view($view);
    }
}
