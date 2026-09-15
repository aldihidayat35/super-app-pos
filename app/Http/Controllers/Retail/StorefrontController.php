<?php

namespace App\Http\Controllers\Retail;

use App\Data\WorkLocationContext;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RetailProductPlacement;
use App\Models\Stock;
use App\Models\User;
use App\Services\Pricing\PriceResolverService;
use App\Services\Retail\EmergencyStockService;
use App\Support\Decimal;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class StorefrontController extends Controller
{
    public function index(Request $request, PriceResolverService $prices, EmergencyStockService $emergencyStock): View
    {
        $user = $request->user();
        [$branches, $branch] = $this->branchContext($request, $user);
        $search = trim((string) $request->query('q', ''));
        $area = trim((string) $request->query('area', ''));
        $categoryId = $request->integer('category_id');
        $showEmpty = $request->boolean('show_empty');
        $branchId = $branch instanceof Branch ? $branch->id : -1;
        $workLocationId = $branch instanceof Branch ? $branch->work_location_id : -1;
        $emergencyProductIds = $branch instanceof Branch ? $emergencyStock->availableProductIds($branch) : [];

        $products = Product::query()
            ->with(['category', 'brand', 'baseUnit', 'images', 'stocks' => fn ($query) => $query->where('work_location_id', $workLocationId)])
            ->where('status', 'active')
            ->when(! $showEmpty, fn ($query) => $query->where(function ($q) use ($workLocationId, $emergencyProductIds) {
                $q->whereHas('stocks', fn ($stock) => $stock
                    ->where('work_location_id', $workLocationId)
                    ->whereRaw('(quantity_on_hand - quantity_reserved - quantity_damaged) > 0'));
                if ($emergencyProductIds !== []) {
                    $q->orWhereIn('products.id', $emergencyProductIds);
                }
            }))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%")
                ->orWhere('model', 'like', "%{$search}%"))
            )
            ->when($categoryId > 0, fn ($query) => $query->where('category_id', $categoryId))
            ->when($area !== '', fn ($query) => $query->whereIn('id', RetailProductPlacement::query()
                ->select('product_id')
                ->where('branch_id', $branchId)
                ->where('area', $area)))
            ->orderByRaw('(CASE WHEN (SELECT COALESCE(SUM(quantity_on_hand - quantity_reserved - quantity_damaged), 0) FROM stocks WHERE stocks.product_id = products.id AND stocks.work_location_id = ?) <= 0 THEN 1 ELSE 0 END), name', [$workLocationId])
            ->paginate(24)
            ->withQueryString();

        $placements = $branch && $products->isNotEmpty()
            ? RetailProductPlacement::query()
                ->where('branch_id', $branch->id)
                ->whereIn('product_id', $products->getCollection()->pluck('id')->all())
                ->get()
                ->keyBy('product_id')
            : collect();

        $emergencyByProduct = $branch instanceof Branch
            ? $emergencyStock->availableByProduct($branch, $products->getCollection()->pluck('id')->all())
            : [];
        $cards = $products->getCollection()->mapWithKeys(function (Product $product) use ($branch, $prices, $emergencyByProduct): array {
            $regularStock = $product->stocks->reduce(
                fn (string $sum, Stock $row): string => Decimal::add($sum, $row->available_quantity, 4),
                '0.0000',
            );
            $emergencyStock = $emergencyByProduct[$product->id] ?? '0.0000';
            $price = $prices->resolve($product, branch: $branch, channel: 'pos');

            return [$product->id => [
                'regular_stock' => $regularStock,
                'emergency_stock' => $emergencyStock,
                'stock' => Decimal::add($regularStock, $emergencyStock, 4),
                'price' => $price['recommended_price'],
            ]];
        });

        return view('retail.storefront.index', [
            'branches' => $branches,
            'branch' => $branch,
            'products' => $products,
            'placements' => $placements,
            'cards' => $cards,
            'areas' => RetailProductPlacement::query()->where('branch_id', $branchId)->distinct()->orderBy('area')->pluck('area'),
            'categories' => ProductCategory::query()
                ->where('is_active', true)
                ->whereHas('products', fn ($query) => $query
                    ->where('status', 'active')
                    ->where(fn ($available) => $available
                        ->whereHas('stocks', fn ($stock) => $stock
                            ->where('work_location_id', $workLocationId)
                            ->whereRaw('(quantity_on_hand - quantity_reserved - quantity_damaged) > 0'))
                        ->when($emergencyProductIds !== [], fn ($inner) => $inner->orWhereIn('products.id', $emergencyProductIds))))
                ->orderBy('name')
                ->get(['id', 'name']),
            'search' => $search,
            'area' => $area,
            'categoryId' => $categoryId,
            'showEmpty' => $showEmpty,
        ]);
    }

    public function updatePlacement(Request $request, Product $product, EmergencyStockService $emergencyStock): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'area' => ['required', 'string', 'max:80'],
            'rack' => ['nullable', 'string', 'max:80'],
            'shelf' => ['nullable', 'string', 'max:80'],
        ], [], [
            'branch_id' => 'toko',
            'area' => 'area pajang',
            'rack' => 'rak',
            'shelf' => 'tingkat rak',
        ]);

        $branch = Branch::query()->whereKey($validated['branch_id'])->where('is_active', true)->firstOrFail();
        abort_unless($request->user()->canAccessWorkLocation((int) $branch->work_location_id), 403);
        $hasRegularStock = $product->stocks()
            ->where('work_location_id', $branch->work_location_id)
            ->whereRaw('(quantity_on_hand - quantity_reserved - quantity_damaged) > 0')
            ->exists();
        $hasEmergencyStock = Decimal::compare($emergencyStock->available($branch, (int) $product->id), '0', 4) > 0;
        abort_unless($product->status->value === 'active' && ($hasRegularStock || $hasEmergencyStock), 404);

        RetailProductPlacement::query()->updateOrCreate(
            ['branch_id' => $branch->id, 'product_id' => $product->id],
            ['area' => trim($validated['area']), 'rack' => trim($validated['rack'] ?? '') ?: null, 'shelf' => trim($validated['shelf'] ?? '') ?: null],
        );

        return redirect()->route('retail.storefront.index', ['branch_id' => $branch->id])
            ->with('notification', ['type' => 'success', 'message' => 'Lokasi pajang produk berhasil disimpan.']);
    }

    /** @return array{0: Collection<int, Branch>, 1: Branch|null} */
    private function branchContext(Request $request, User $user): array
    {
        $allowedIds = $user->permittedWorkLocationIds();
        $branches = Branch::query()
            ->where('is_active', true)
            ->whereHas('workLocation', fn ($query) => $query->where('is_active', true)->where('type', 'branch'))
            ->whereIn('work_location_id', $allowedIds)
            ->orderBy('name')
            ->get();

        $requestedId = $request->integer('branch_id');
        if ($requestedId > 0) {
            $selected = $branches->firstWhere('id', $requestedId);
            abort_unless($selected !== null, 403, 'Toko tidak termasuk dalam penugasan Anda.');

            return [$branches, $selected];
        }

        $contextId = app(WorkLocationContext::class)->id;
        $defaultId = $user->workLocations()->wherePivot('is_default', true)->value('work_locations.id');
        $branch = $branches->firstWhere('work_location_id', $contextId)
            ?? $branches->firstWhere('work_location_id', $defaultId)
            ?? $branches->first();

        return [$branches, $branch];
    }
}
