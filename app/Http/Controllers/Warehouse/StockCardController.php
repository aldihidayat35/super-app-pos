<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\StockMutationType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMutation;
use App\Models\User;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockCardController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', StockMutation::class);

        $locationIds = $request->user()?->permittedWorkLocationIds() ?? [];

        return view('warehouse.stock-card.index', [
            'mutations' => $this->query($request)->paginate(25)->withQueryString(),
            'products' => Product::query()->where('status', 'active')->whereHas('stockMutations', fn ($query) => $query->whereIn('work_location_id', $locationIds))->orderBy('name')->limit(200)->get(),
            'workLocations' => WorkLocation::query()->whereIn('id', $locationIds)->orderBy('name')->get(),
            'warehouseLocations' => WarehouseLocation::query()->where('is_active', true)->whereHas('warehouse', fn ($query) => $query->whereIn('work_location_id', $locationIds))->orderBy('full_code')->limit(300)->get(),
            'users' => User::query()->where('is_active', true)->orderBy('name')->limit(200)->get(),
            'types' => StockMutationType::options(),
            'filters' => $request->only(['product_id', 'work_location_id', 'warehouse_location_id', 'mutation_type', 'reference_no', 'user_id', 'date_from', 'date_to']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', StockMutation::class);

        return response()->streamDownload(function () use ($request): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Waktu', 'SKU', 'Produk', 'Lokasi', 'Jenis', 'Masuk', 'Keluar', 'Before', 'After', 'Referensi', 'User', 'Catatan']);

            $this->query($request)->chunk(200, function ($mutations) use ($handle): void {
                foreach ($mutations as $mutation) {
                    fputcsv($handle, [
                        $mutation->occurred_at?->format('Y-m-d H:i:s'),
                        $mutation->product?->sku,
                        $mutation->product?->name,
                        $mutation->warehouseLocation?->full_code ?: $mutation->workLocation?->name,
                        $mutation->mutation_type->label(),
                        max((float) $mutation->quantity_on_hand_change, 0),
                        abs(min((float) $mutation->quantity_on_hand_change, 0)),
                        $mutation->quantity_on_hand_before,
                        $mutation->quantity_on_hand_after,
                        $mutation->reference_no,
                        $mutation->actor?->name,
                        $mutation->reason,
                    ]);
                }
            });

            fclose($handle);
        }, 'kartu-stok.csv');
    }

    private function query(Request $request): mixed
    {
        return StockMutation::query()
            ->with(['product', 'workLocation', 'warehouseLocation', 'actor'])
            ->whereIn('work_location_id', $request->user()?->permittedWorkLocationIds() ?? [])
            ->when($request->integer('product_id') > 0, fn ($query) => $query->where('product_id', $request->integer('product_id')))
            ->when($request->integer('work_location_id') > 0, fn ($query) => $query->where('work_location_id', $request->integer('work_location_id')))
            ->when($request->integer('warehouse_location_id') > 0, fn ($query) => $query->where('warehouse_location_id', $request->integer('warehouse_location_id')))
            ->when($request->filled('mutation_type'), fn ($query) => $query->where('mutation_type', $request->query('mutation_type')))
            ->when($request->filled('reference_no'), fn ($query) => $query->where('reference_no', 'like', '%'.$request->query('reference_no').'%'))
            ->when($request->integer('user_id') > 0, fn ($query) => $query->where('actor_user_id', $request->integer('user_id')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('occurred_at', '>=', $request->query('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('occurred_at', '<=', $request->query('date_to')))
            ->orderBy('occurred_at')
            ->orderBy('id');
    }
}
