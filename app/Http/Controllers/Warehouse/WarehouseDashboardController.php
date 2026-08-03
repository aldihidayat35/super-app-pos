<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Reports\ReportMetricService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class WarehouseDashboardController extends Controller
{
    public function index(Request $request, ReportMetricService $reports): View
    {
        $this->authorize('viewAny', Stock::class);

        return $this->renderForUser($request, $reports);
    }

    public function renderForUser(Request $request, ReportMetricService $reports): View
    {
        [$warehouses, $activeWarehouse, $canSelectWarehouse] = $this->resolveContext($request);
        $payload = $this->dashboardPayload($request, $reports, $activeWarehouse);

        return view('warehouse.dashboard', $payload + [
            'warehouses' => $warehouses,
            'activeWarehouse' => $activeWarehouse,
            'canSelectWarehouse' => $canSelectWarehouse,
        ]);
    }

    public function data(Request $request, ReportMetricService $reports): JsonResponse
    {
        $this->authorize('viewAny', Stock::class);

        [, $activeWarehouse] = $this->resolveContext($request);
        $payload = $this->dashboardPayload($request, $reports, $activeWarehouse);

        return response()->json([
            'warehouse_id' => $activeWarehouse?->id,
            'warehouse_name' => $activeWarehouse?->name,
            'html' => view('warehouse.partials.dashboard-content', $payload)->render(),
            'kpis' => $payload['dashboard']['kpis'],
            'charts' => $payload['dashboard']['charts'],
            'last_updated_at' => $payload['dashboard']['last_updated_at']->toIso8601String(),
        ]);
    }

    /**
     * @return array{0: Collection<int, Warehouse>, 1: Warehouse|null, 2: bool}
     */
    private function resolveContext(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();
        $warehouses = Warehouse::query()
            ->with('workLocation')
            ->where('is_active', true)
            ->whereNotNull('work_location_id')
            ->whereIn('work_location_id', $user->permittedWorkLocationIds())
            ->orderBy('name')
            ->get();

        $requestedWarehouseId = $request->integer('warehouse_id');
        $activeWarehouse = $requestedWarehouseId > 0
            ? $warehouses->firstWhere('id', $requestedWarehouseId)
            : $warehouses->first();

        if ($requestedWarehouseId > 0 && ! $activeWarehouse) {
            abort(403, 'Gudang tidak termasuk dalam cakupan akses Anda.');
        }

        $canSelectWarehouse = $warehouses->isNotEmpty()
            && ($user->hasUnrestrictedLocationScope() || $warehouses->count() > 1);

        return [$warehouses, $activeWarehouse, $canSelectWarehouse];
    }

    /** @return array<string, mixed> */
    private function dashboardPayload(Request $request, ReportMetricService $reports, ?Warehouse $activeWarehouse): array
    {
        /** @var User $user */
        $user = $request->user();
        $input = $request->only(['start_date', 'end_date', 'range']);
        $input['work_location_id'] = $activeWarehouse === null ? -1 : $activeWarehouse->work_location_id;
        $filters = $reports->filters($user, $input);

        return [
            'filters' => $filters,
            'dashboard' => $reports->warehouseDashboard($user, $filters),
            'definitions' => $reports->definitions('warehouse'),
            'activeWarehouse' => $activeWarehouse,
        ];
    }
}
