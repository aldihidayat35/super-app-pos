<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Reports\ReportMetricService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

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
            ]);
        }

        if ($view === 'warehouse.dashboard') {
            return view($view, [
                'filters' => $filters,
                'dashboard' => $reports->warehouseDashboard($user, $filters),
                'definitions' => $reports->definitions('warehouse'),
            ]);
        }

        if ($view === 'dashboards.retail') {
            return view($view, [
                'filters' => $filters,
                'dashboard' => $reports->retailDashboard($user, $filters),
                'definitions' => $reports->definitions('retail'),
            ]);
        }

        return view($view);
    }
}
