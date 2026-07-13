<?php

namespace App\Http\Controllers\Retail;

use App\Enums\RestockRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Retail\StoreRestockRequestRequest;
use App\Models\Branch;
use App\Models\Product;
use App\Models\RestockRequest;
use App\Models\Warehouse;
use App\Services\Warehouse\RestockRequestService;
use App\Services\Warehouse\StockTransferService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RestockRequestController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', RestockRequest::class);

        return view('retail.restock-requests.index', [
            'requests' => $this->query($request)->paginate(15)->withQueryString(),
            'branches' => Branch::query()->with('workLocation')->where('is_active', true)->orderBy('name')->get(),
            'warehouses' => Warehouse::query()->with('workLocation')->where('is_active', true)->orderBy('name')->get(),
            'products' => Product::query()->where('status', 'active')->orderBy('name')->limit(200)->get(),
            'statuses' => RestockRequestStatus::options(),
            'filters' => $request->only(['branch_id', 'status']),
        ]);
    }

    public function store(StoreRestockRequestRequest $request, RestockRequestService $service): RedirectResponse
    {
        $data = $request->validated();
        $this->ensureBranchScope($request, (int) $data['branch_id']);
        $restockRequest = $service->create($data, $request->user());

        return redirect()->route('retail.restock-requests.index')->with('notification', ['type' => 'success', 'message' => "Request {$restockRequest->number} berhasil disimpan."]);
    }

    public function approve(Request $request, RestockRequest $restockRequest, RestockRequestService $service): RedirectResponse
    {
        $this->authorize('approve', $restockRequest);
        $approved = [];
        foreach ((array) $request->input('items', []) as $id => $item) {
            if (is_array($item)) {
                $approved[(int) $id] = $item['quantity_approved'] ?? 0;
            }
        }
        $service->approve($restockRequest, $request->user(), $approved);

        return back()->with('notification', ['type' => 'success', 'message' => 'Request restock disetujui.']);
    }

    public function reject(Request $request, RestockRequest $restockRequest, RestockRequestService $service): RedirectResponse
    {
        $this->authorize('approve', $restockRequest);
        $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $service->reject($restockRequest, $request->user(), $request->input('reason'));

        return back()->with('notification', ['type' => 'success', 'message' => 'Request restock ditolak.']);
    }

    public function convert(Request $request, RestockRequest $restockRequest, StockTransferService $service): RedirectResponse
    {
        $this->authorize('approve', $restockRequest);
        $transfer = $service->createFromRestockRequest($restockRequest, $request->user());

        return redirect()->route('warehouse.stock-transfers.show', $transfer)->with('notification', ['type' => 'success', 'message' => 'Request restock berhasil dibuat menjadi transfer.']);
    }

    private function query(Request $request): mixed
    {
        return RestockRequest::query()
            ->with(['branch.workLocation', 'sourceWarehouse.workLocation', 'requester', 'items.product'])
            ->where(function ($query) use ($request): void {
                $locationIds = $request->user()?->permittedWorkLocationIds() ?? [];
                $query->whereHas('branch', fn ($branch) => $branch->whereIn('work_location_id', $locationIds))
                    ->orWhereHas('sourceWarehouse', fn ($warehouse) => $warehouse->whereIn('work_location_id', $locationIds));
            })
            ->when($request->integer('branch_id') > 0, fn ($query) => $query->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->latest('created_at');
    }

    private function ensureBranchScope(Request $request, int $branchId): void
    {
        $branch = Branch::query()->findOrFail($branchId);
        abort_unless($request->user()?->canAccessWorkLocation((int) $branch->work_location_id), 403);
    }
}
