<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateStockAdjustmentAccessRequest;
use App\Models\StockAdjustmentAccess;
use App\Models\StockDirectAdjustment;
use App\Models\StockDirectAdjustmentItem;
use App\Models\WorkLocation;
use App\Services\Warehouse\StockAdjustmentAccessService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StockAdjustmentAccessController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);
        $locations = WorkLocation::query()->whereIn('type', ['warehouse', 'branch'])->where('is_active', true)->with('users')->orderBy('type')->orderBy('name')->get();
        $accesses = StockAdjustmentAccess::query()->with(['workLocation', 'histories' => fn ($query) => $query->latest('occurred_at')->limit(5)])->get()->keyBy('work_location_id');

        return view('admin.stock-adjustment-access.index', [
            'locations' => $locations, 'accesses' => $accesses,
            'activeCount' => $accesses->filter->isCurrentlyActive()->count(),
            'pendingCount' => StockDirectAdjustment::query()->where('status', 'pending_approval')->count(),
            'totalDifferenceQty' => StockDirectAdjustmentItem::query()->whereHas('adjustment', fn ($query) => $query->where('status', 'completed'))->sum('difference_qty'),
            'totalDifferenceValue' => StockDirectAdjustmentItem::query()->whereHas('adjustment', fn ($query) => $query->where('status', 'completed'))->sum('difference_value'),
        ]);
    }

    public function update(UpdateStockAdjustmentAccessRequest $request, WorkLocation $workLocation, StockAdjustmentAccessService $service): RedirectResponse
    {
        try {
            $service->activate($workLocation, $request->validated(), $request->user(), $request);
        } catch (ServiceException $exception) {
            return back()->withErrors(['access' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Akses koreksi stok berhasil diaktifkan.']);
    }

    public function destroy(Request $request, StockAdjustmentAccess $access, StockAdjustmentAccessService $service): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000']], [], ['reason' => 'alasan penonaktifan']);
        $service->deactivate($access, $data['reason'], $request->user(), $request);

        return back()->with('notification', ['type' => 'success', 'message' => 'Akses koreksi stok dinonaktifkan.']);
    }
}
