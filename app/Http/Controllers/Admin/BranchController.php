<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CashShiftStatus;
use App\Enums\PosSaleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBranchRequest;
use App\Http\Requests\Admin\UpdateBranchRequest;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\PosSale;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Organization\WorkLocationSyncService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class BranchController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Branch::class);

        $status = $request->query('status');
        $warehouseId = $request->query('warehouse');

        $branches = Branch::query()
            ->with(['primaryWarehouse', 'manager'])
            ->when(! $request->user()?->hasUnrestrictedLocationScope(), function ($query) use ($request): void {
                $query->whereIn('work_location_id', $request->user()?->permittedWorkLocationIds() ?? []);
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when(is_numeric($warehouseId), fn ($query) => $query->where('primary_warehouse_id', (int) $warehouseId))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.branches.index', [
            'branches' => $branches,
            'status' => $status,
            'warehouseId' => $warehouseId,
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Branch::class);

        return view('admin.branches.create', [
            'branch' => new Branch(['is_active' => true, 'is_closing_required' => true, 'price_configuration' => 'standard', 'closing_configuration' => 'daily']),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(),
            'managers' => $this->managerOptions(),
        ]);
    }

    public function store(StoreBranchRequest $request, WorkLocationSyncService $sync): RedirectResponse
    {
        $branch = DB::transaction(function () use ($request, $sync): Branch {
            $data = $request->validated();
            $data['is_active'] = $request->boolean('is_active');
            $data['is_closing_required'] = $request->boolean('is_closing_required', true);
            $branch = Branch::query()->create($data);
            $sync->syncBranch($branch);
            $this->audit($request, $branch, 'admin.branch.created');

            return $branch;
        });

        return redirect()->route('admin.branches.show', $branch)->with('notification', [
            'type' => 'success',
            'message' => 'Cabang berhasil dibuat.',
        ]);
    }

    public function show(Request $request, Branch $branch): View
    {
        $this->authorize('view', $branch);

        $user = $request->user();
        $access = [
            'users' => $user->can('admin.users.view'),
            'users_create' => $user->can('admin.users.create'),
            'stock' => $user->can('stock.view'),
            'restock' => $user->can('stock_transfers.view') || $user->can('stock_transfers.create'),
            'restock_create' => $user->can('stock_transfers.create'),
            'shifts' => $user->can('cash_shifts.view'),
            'shifts_create' => $user->can('cash_shifts.create'),
            'sales' => $user->can('pos.view') || $user->can('reports.view'),
            'pos_create' => $user->can('pos.create'),
            'reports' => $user->can('reports.view') || $user->can('pos.view') || $user->can('cash_shifts.view'),
            'margin' => $user->can('margins.view_sensitive'),
            'audit' => $user->can('audit.view'),
        ];

        $branch->load(['primaryWarehouse', 'manager', 'workLocation']);
        $workLocationId = $branch->work_location_id;

        $assignedUsers = $access['users'] && $branch->workLocation
            ? $branch->workLocation->users()->with('roles')->orderBy('name')->get()
            : collect();

        $stocks = $access['stock'] && $workLocationId
            ? Stock::query()
                ->with('product')
                ->where('work_location_id', $workLocationId)
                ->orderByDesc('quantity_on_hand')
                ->limit(8)
                ->get()
            : collect();

        $shifts = $access['shifts']
            ? CashShift::query()
                ->with('cashier')
                ->where('branch_id', $branch->id)
                ->latest('opened_at')
                ->limit(8)
                ->get()
            : collect();

        $salesSummary = null;
        if ($access['sales']) {
            $salesQuery = PosSale::query()
                ->where('branch_id', $branch->id)
                ->whereIn('status', [PosSaleStatus::COMPLETED->value, PosSaleStatus::RETURNED->value])
                ->selectRaw('COUNT(*) as total_sales')
                ->selectRaw('COALESCE(SUM(grand_total_amount), 0) as total_revenue');

            if ($access['margin']) {
                $salesQuery->selectRaw('COALESCE(SUM(total_margin_amount), 0) as total_margin');
            }

            $summary = $salesQuery->first();
            $salesSummary = [
                'total_sales' => (int) ($summary?->getAttribute('total_sales') ?? 0),
                'total_revenue' => (string) ($summary?->getAttribute('total_revenue') ?? 0),
                'total_margin' => $access['margin'] ? (string) ($summary?->getAttribute('total_margin') ?? 0) : null,
            ];
        }

        $recentSales = $access['sales']
            ? PosSale::query()
                ->with('cashier')
                ->where('branch_id', $branch->id)
                ->whereIn('status', [PosSaleStatus::COMPLETED->value, PosSaleStatus::RETURNED->value])
                ->latest('completed_at')
                ->limit(5)
                ->get()
            : collect();

        $histories = $access['audit']
            ? Activity::query()
                ->with('causer')
                ->where('subject_type', $branch->getMorphClass())
                ->where('subject_id', $branch->id)
                ->latest()
                ->limit(10)
                ->get()
            : collect();

        $metrics = [
            'active_users' => $access['users'] ? $assignedUsers->where('is_active', true)->count() : null,
            'stock_products' => $access['stock'] && $workLocationId
                ? Stock::query()->where('work_location_id', $workLocationId)->where('quantity_on_hand', '>', 0)->count()
                : null,
            'available_stock' => $access['stock'] && $workLocationId
                ? (string) Stock::query()->where('work_location_id', $workLocationId)
                    ->selectRaw('COALESCE(SUM(quantity_on_hand - quantity_reserved - quantity_damaged), 0) as aggregate')
                    ->first()?->getAttribute('aggregate')
                : null,
            'open_shifts' => $access['shifts']
                ? CashShift::query()->where('branch_id', $branch->id)->where('status', CashShiftStatus::OPEN->value)->count()
                : null,
            'total_sales' => $access['sales'] ? $salesSummary['total_sales'] : null,
            'total_revenue' => $access['sales'] ? $salesSummary['total_revenue'] : null,
        ];

        return view('admin.branches.show', [
            'branch' => $branch,
            'access' => $access,
            'assignedUsers' => $assignedUsers,
            'metrics' => $metrics,
            'stocks' => $stocks,
            'shifts' => $shifts,
            'salesSummary' => $salesSummary,
            'recentSales' => $recentSales,
            'histories' => $histories,
        ]);
    }

    public function edit(Branch $branch): View
    {
        $this->authorize('update', $branch);

        return view('admin.branches.edit', [
            'branch' => $branch,
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(),
            'managers' => $this->managerOptions(),
        ]);
    }

    public function update(UpdateBranchRequest $request, Branch $branch, WorkLocationSyncService $sync): RedirectResponse
    {
        DB::transaction(function () use ($request, $branch, $sync): void {
            $data = $request->validated();
            $data['is_active'] = $request->boolean('is_active');
            $data['is_closing_required'] = $request->boolean('is_closing_required');
            $branch->fill($data)->save();
            $sync->syncBranch($branch);
            $this->audit($request, $branch, 'admin.branch.updated');
        });

        return redirect()->route('admin.branches.show', $branch)->with('notification', [
            'type' => 'success',
            'message' => 'Cabang berhasil diperbarui.',
        ]);
    }

    public function deactivate(Request $request, Branch $branch, WorkLocationSyncService $sync): RedirectResponse
    {
        $this->authorize('update', $branch);

        DB::transaction(function () use ($request, $branch, $sync): void {
            $branch->forceFill(['is_active' => false])->save();
            $sync->syncBranch($branch);
            $this->audit($request, $branch, 'admin.branch.deactivated');
        });

        return back()->with('notification', [
            'type' => 'success',
            'message' => 'Cabang berhasil dinonaktifkan.',
        ]);
    }

    private function managerOptions(): mixed
    {
        return User::query()->where('is_active', true)->orderBy('name')->get();
    }

    private function audit(Request $request, Branch $branch, string $event): void
    {
        activity()->causedBy($request->user())->performedOn($branch)->log($event);
    }
}
