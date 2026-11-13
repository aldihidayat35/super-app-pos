<?php

namespace App\Http\Controllers\Pricing;

use App\Enums\ProductPriceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pricing\StoreProductPriceRequest;
use App\Models\Branch;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPrice;
use App\Models\StockBatch;
use App\Models\WorkLocation;
use App\Services\Pricing\PriceManagementService;
use App\Services\Pricing\PriceResolverService;
use App\Support\CurrencyFormatter;
use App\Support\Decimal;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductPriceController extends Controller
{
    public const DEFAULT_RINGS = [
        'ring_retail' => [
            'key' => 'ring_retail',
            'label' => 'Ring Retail',
            'price_ring' => 'Ring Retail',
            'channel' => 'retail',
            'aliases' => ['Ring Retail', 'retail', 'ring_retail', 'ring_1'],
        ],
        'ring_b2b' => [
            'key' => 'ring_b2b',
            'label' => 'Ring B2B',
            'price_ring' => 'Ring B2B',
            'channel' => 'b2b',
            'aliases' => ['Ring B2B', 'b2b', 'ring_b2b', 'ring_grosir', 'ring_2'],
        ],
        'ring_pos' => [
            'key' => 'ring_pos',
            'label' => 'Ring POS',
            'price_ring' => 'Ring POS',
            'channel' => 'pos',
            'aliases' => ['Ring POS', 'pos', 'ring_pos', 'Ring Kasir', 'ring_3'],
        ],
    ];

    public function index(Request $request, PriceResolverService $resolver): View
    {
        $this->authorize('viewAny', ProductPrice::class);

        $user = $request->user();
        $isSuperAdmin = $user->hasUnrestrictedLocationScope();

        // 1. Hak Akses & Pilihan Lokasi
        if ($isSuperAdmin) {
            $locations = WorkLocation::query()->where('is_active', true)->orderBy('name')->get();
            if ($request->has('work_location_id')) {
                $selectedLocationId = $request->input('work_location_id') !== '' ? $request->integer('work_location_id') : null;
            } elseif ($request->has('branch_id') && $request->input('branch_id') !== '') {
                $branchWorkLoc = Branch::where('id', $request->input('branch_id'))->value('work_location_id');
                $selectedLocationId = $branchWorkLoc ? (int) $branchWorkLoc : $request->integer('branch_id');
            } else {
                $selectedLocationId = null;
            }
        } else {
            // Role lain: Pilihan lokasi otomatis terkunci sesuai lokasi penugasan akun tersebut
            $permittedIds = $user->permittedWorkLocationIds();
            abort_if(empty($permittedIds), 403, 'Akun Anda belum memiliki penugasan lokasi kerja.');

            $requestedId = $request->filled('work_location_id') ? $request->integer('work_location_id') : null;
            if ($requestedId && in_array($requestedId, $permittedIds, true)) {
                $selectedLocationId = $requestedId;
            } else {
                $defaultLocation = $user->workLocations()->wherePivot('is_default', true)->first() ?? $user->workLocations()->first();
                $selectedLocationId = $defaultLocation ? $defaultLocation->id : $permittedIds[0];
            }
            $locations = WorkLocation::query()->whereIn('id', $permittedIds)->where('is_active', true)->orderBy('name')->get();
        }

        $currentLocation = $selectedLocationId ? WorkLocation::find($selectedLocationId) : null;
        $canSensitive = $user->can('margins.view_sensitive') || $user->can('prices.update');

        $today = now()->toDateString();
        $buildRingExistsSql = function (array $aliases, string $channel) use ($selectedLocationId, $today): string {
            $aliasList = implode(',', array_map(fn ($a) => "'".addslashes($a)."'", $aliases));
            $locCondition = $selectedLocationId !== null
                ? "(pp.work_location_id = {$selectedLocationId} OR pp.work_location_id IS NULL)"
                : 'pp.work_location_id IS NULL';

            return "EXISTS (
                SELECT 1 FROM product_prices pp 
                WHERE pp.product_id = products.id 
                  AND pp.status = 'active'
                  AND (pp.starts_at IS NULL OR pp.starts_at <= '{$today}')
                  AND (pp.ends_at IS NULL OR pp.ends_at >= '{$today}')
                  AND pp.recommended_price > 0
                  AND {$locCondition}
                  AND (pp.price_ring IN ({$aliasList}) OR pp.channel = '{$channel}')
            )";
        };

        $sqlRetail = $buildRingExistsSql(['Ring Retail', 'retail', 'ring_retail', 'ring_1'], 'retail');
        $sqlB2B = $buildRingExistsSql(['Ring B2B', 'b2b', 'ring_b2b', 'ring_grosir', 'ring_2'], 'b2b');
        $sqlPOS = $buildRingExistsSql(['Ring POS', 'pos', 'ring_pos', 'Ring Kasir', 'ring_3'], 'pos');

        // Query active products: urutkan dari 0 ring (paling atas) hingga 3 ring (bawah)
        $productsQuery = Product::query()
            ->select('products.*')
            ->selectRaw("(CASE WHEN {$sqlRetail} THEN 1 ELSE 0 END) + (CASE WHEN {$sqlB2B} THEN 1 ELSE 0 END) + (CASE WHEN {$sqlPOS} THEN 1 ELSE 0 END) AS filled_rings_count")
            ->with(['category', 'baseUnit'])
            ->where('status', 'active')
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = trim($request->input('q'));
                $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
            })
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->orderBy('filled_rings_count', 'asc')
            ->orderBy('products.name', 'asc');

        $products = $productsQuery->paginate(20)->withQueryString();
        $productIds = $products->pluck('id')->all();

        // Query active stock batches at selected location
        $stockBatches = StockBatch::query()
            ->whereIn('product_id', $productIds)
            ->when($selectedLocationId !== null, fn ($q) => $q->where('work_location_id', $selectedLocationId))
            ->available()
            ->orderBy('cost_price')
            ->get()
            ->groupBy('product_id');

        // Query active prices at selected location (or fallback to global)
        $productPrices = ProductPrice::query()
            ->whereIn('product_id', $productIds)
            ->where('status', ProductPriceStatus::ACTIVE->value)
            ->where(function ($query) use ($selectedLocationId) {
                if ($selectedLocationId !== null) {
                    $query->where('work_location_id', $selectedLocationId)
                        ->orWhereNull('work_location_id');
                } else {
                    $query->whereNull('work_location_id');
                }
            })
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhereDate('starts_at', '<=', now()->toDateString()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', now()->toDateString()))
            ->orderByDesc('work_location_id')
            ->orderBy('priority')
            ->get()
            ->groupBy('product_id');

        $itemsData = [];
        foreach ($products as $product) {
            $hppInfo = $this->buildHppInfo($product, $stockBatches->get($product->id, collect()), $canSensitive);

            $pricesCollection = $productPrices->get($product->id, collect());
            $rowPrices = [];
            $firstPriceModel = $pricesCollection->first();
            $startsAt = $firstPriceModel?->starts_at?->toDateString();
            $endsAt = $firstPriceModel?->ends_at?->toDateString();

            foreach (self::DEFAULT_RINGS as $rKey => $rConf) {
                $matchedPrice = $pricesCollection->first(function (ProductPrice $p) use ($rConf) {
                    return in_array($p->price_ring, $rConf['aliases'], true)
                        || ($p->channel === $rConf['channel'] && in_array($p->price_ring, $rConf['aliases'], true));
                });

                if ($matchedPrice) {
                    $rowPrices[$rKey] = [
                        'min' => Decimal::normalize((string) $matchedPrice->min_price, 2),
                        'recommended' => Decimal::normalize((string) $matchedPrice->recommended_price, 2),
                        'max' => Decimal::normalize((string) $matchedPrice->max_price, 2),
                    ];
                    if (! $firstPriceModel) {
                        $firstPriceModel = $matchedPrice;
                        $startsAt = $matchedPrice->starts_at?->toDateString();
                        $endsAt = $matchedPrice->ends_at?->toDateString();
                    }
                } else {
                    $rowPrices[$rKey] = [
                        'min' => null,
                        'recommended' => null,
                        'max' => null,
                    ];
                }
            }

            // Active current single price (retail channel or first price)
            $primaryPriceModel = $pricesCollection->firstWhere('channel', 'retail') ?? $firstPriceModel;
            $currentPrice = $primaryPriceModel?->recommended_price !== null
                ? Decimal::normalize((string) $primaryPriceModel->recommended_price, 2)
                : null;

            // Effective HPP for anti-rugi reference
            $batchHpp = $stockBatches->get($product->id, collect())->first()?->cost_price;
            $effectiveHpp = $batchHpp !== null
                ? Decimal::normalize((string) $batchHpp, 2)
                : Decimal::normalize((string) $product->cost_price, 2);

            $itemsData[$product->id] = [
                'product' => $product,
                'hpp' => $hppInfo,
                'current_price' => $currentPrice,
                'current_price_formatted' => $currentPrice !== null ? CurrencyFormatter::rupiah($currentPrice) : null,
                'effective_hpp' => $effectiveHpp,
                'effective_hpp_formatted' => CurrencyFormatter::rupiah($effectiveHpp),
                'prices' => $rowPrices,
                'price_model' => $firstPriceModel,
                'starts_at' => $startsAt ?? now()->toDateString(),
                'ends_at' => $endsAt,
                'is_unlimited' => empty($endsAt),
                'filled_rings_count' => (int) ($product->filled_rings_count ?? count(array_filter($rowPrices, fn ($p) => $p['recommended'] !== null))),
            ];
        }

        // For backward compatibility with legacy bulk assignment and tests
        $legacyPrices = ProductPrice::query()
            ->with(['product', 'branch'])
            ->latest('id')
            ->paginate(15);

        return view('pricing.product-prices.index', [
            'products' => $products,
            'allProducts' => Product::query()->with('category')->where('status', 'active')->orderBy('name')->limit(500)->get(),
            'categories' => ProductCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'locations' => $locations,
            'selectedLocationId' => $selectedLocationId,
            'currentLocation' => $currentLocation,
            'isSuperAdmin' => $isSuperAdmin,
            'canSensitive' => $canSensitive,
            'itemsData' => $itemsData,
            'ringsConfig' => self::DEFAULT_RINGS,
            'resolver' => $resolver,
            'prices' => $legacyPrices,
            'filters' => $request->only(['q', 'category_id', 'work_location_id', 'product_id', 'channel']),
        ]);
    }

    public function saveItem(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('update', ProductPrice::class);

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'work_location_id' => ['nullable', 'integer', 'exists:work_locations,id'],
            'price' => ['nullable'],
            'prices' => ['nullable', 'array'],
            'prices.ring_retail' => ['nullable'],
            'prices.ring_b2b' => ['nullable'],
            'prices.ring_pos' => ['nullable'],
            'unlimited' => ['nullable'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $parsedPrices = [];
        $hasAnyPrice = false;
        $isSinglePriceInput = false;

        if ($request->filled('price')) {
            $isSinglePriceInput = true;
            $cleanPrice = str_replace(['Rp', '.', ' '], '', (string) $request->input('price'));
            $cleanPrice = str_replace(',', '.', $cleanPrice);
            $singleVal = Decimal::normalize($cleanPrice, 2);

            foreach (self::DEFAULT_RINGS as $rKey => $rConf) {
                $parsedPrices[$rKey] = [
                    'min' => $singleVal,
                    'recommended' => $singleVal,
                    'max' => $singleVal,
                ];
            }
            $hasAnyPrice = true;
        } elseif (isset($validated['prices']) && is_array($validated['prices'])) {
            foreach (self::DEFAULT_RINGS as $rKey => $rConf) {
                $raw = $validated['prices'][$rKey] ?? null;
                if ($raw === null || $raw === '') {
                    continue;
                }

                if (is_array($raw)) {
                    $rawRec = $raw['recommended'] ?? $raw['price'] ?? null;
                    $rawMin = $raw['min'] ?? null;
                    $rawMax = $raw['max'] ?? null;
                } else {
                    $rawRec = $raw;
                    $rawMin = null;
                    $rawMax = null;
                }

                // If all 3 values are empty/null
                if (($rawRec === null || $rawRec === '') && ($rawMin === null || $rawMin === '') && ($rawMax === null || $rawMax === '')) {
                    continue;
                }

                // Normalization & Smart Auto-Fill
                if ($rawRec !== null && $rawRec !== '') {
                    $recVal = Decimal::normalize((string) $rawRec, 2);
                } elseif ($rawMin !== null && $rawMin !== '') {
                    $recVal = Decimal::normalize((string) $rawMin, 2);
                } else {
                    $recVal = Decimal::normalize((string) $rawMax, 2);
                }

                $minVal = ($rawMin !== null && $rawMin !== '') ? Decimal::normalize((string) $rawMin, 2) : $recVal;
                $maxVal = ($rawMax !== null && $rawMax !== '') ? Decimal::normalize((string) $rawMax, 2) : $recVal;

                // Validate min <= rec <= max
                if (Decimal::compare($minVal, $recVal, 2) > 0) {
                    $msg = "Pada {$rConf['label']}, Harga Minimum (Rp ".CurrencyFormatter::rupiah($minVal).') tidak boleh lebih besar dari Harga Rekomendasi (Rp '.CurrencyFormatter::rupiah($recVal).').';
                    if ($request->wantsJson() || $request->ajax()) {
                        return response()->json(['success' => false, 'message' => $msg], 422);
                    }

                    return back()->withErrors(['prices' => $msg]);
                }

                if (Decimal::compare($recVal, $maxVal, 2) > 0) {
                    $msg = "Pada {$rConf['label']}, Harga Rekomendasi (Rp ".CurrencyFormatter::rupiah($recVal).') tidak boleh lebih besar dari Harga Maksimum (Rp '.CurrencyFormatter::rupiah($maxVal).').';
                    if ($request->wantsJson() || $request->ajax()) {
                        return response()->json(['success' => false, 'message' => $msg], 422);
                    }

                    return back()->withErrors(['prices' => $msg]);
                }

                $parsedPrices[$rKey] = [
                    'min' => $minVal,
                    'recommended' => $recVal,
                    'max' => $maxVal,
                ];
                $hasAnyPrice = true;
            }
        }

        if (! $hasAnyPrice) {
            $msg = 'Masukkan minimal satu nominal ring harga untuk disimpan.';
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $msg,
                ], 422);
            }

            return back()->withErrors(['prices' => $msg]);
        }

        $user = $request->user();

        // 1. Hak Akses & Pilihan Lokasi
        if ($user->hasUnrestrictedLocationScope()) {
            $workLocationId = $validated['work_location_id'] ?? null;
            if (! $workLocationId && ! empty($validated['branch_id'])) {
                $branchWorkLoc = Branch::where('id', $validated['branch_id'])->value('work_location_id');
                $workLocationId = $branchWorkLoc ? (int) $branchWorkLoc : (int) $validated['branch_id'];
            }
        } else {
            // Role lain: Pilihan lokasi otomatis terkunci sesuai lokasi penugasan akun tersebut!
            $permittedIds = $user->permittedWorkLocationIds();
            abort_if(empty($permittedIds), 403, 'Akun Anda belum memiliki penugasan lokasi kerja.');

            $requestedId = $request->filled('work_location_id') ? $request->integer('work_location_id') : null;
            if ($requestedId && in_array($requestedId, $permittedIds, true)) {
                $workLocationId = $requestedId;
            } else {
                $defaultLocation = $user->workLocations()->wherePivot('is_default', true)->first() ?? $user->workLocations()->first();
                $workLocationId = $defaultLocation ? $defaultLocation->id : $permittedIds[0];
            }
        }

        $workLocation = $workLocationId ? WorkLocation::find($workLocationId) : null;
        $branchId = $workLocationId ? Branch::where('work_location_id', $workLocationId)->value('id') : null;

        $isUnlimited = filter_var($request->input('unlimited', true), FILTER_VALIDATE_BOOLEAN) || empty($request->input('ends_at'));
        $startsAt = $request->filled('starts_at') ? $request->input('starts_at') : now()->toDateString();
        $endsAt = $isUnlimited ? null : ($request->filled('ends_at') ? $request->input('ends_at') : null);

        $product = Product::findOrFail($validated['product_id']);
        $savedCount = 0;
        $warnings = [];

        DB::transaction(function () use (
            $product, $workLocationId, $branchId, $workLocation, $validated, $parsedPrices,
            $startsAt, $endsAt, $user, $isSinglePriceInput, &$savedCount, &$warnings
        ): void {
            // HPP snapshot dari batch aktif pada lokasi bersangkutan, atau fallback ke master cost_price
            $batchHpp = StockBatch::where('product_id', $product->id)
                ->when($workLocationId !== null, fn ($q) => $q->where('work_location_id', $workLocationId))
                ->available()
                ->latest('id')
                ->value('cost_price');
            $hppSnapshot = $batchHpp !== null
                ? Decimal::normalize((string) $batchHpp, 2)
                : Decimal::normalize((string) ($product->cost_price ?? '0.00'), 2);

            foreach (self::DEFAULT_RINGS as $ringKey => $ringDef) {
                if (! isset($parsedPrices[$ringKey])) {
                    continue;
                }

                $pVals = $parsedPrices[$ringKey];
                $minAmount = $pVals['min'];
                $recAmount = $pVals['recommended'];
                $maxAmount = $pVals['max'];

                // Peringatan Lunak (Warning Badge): jika Min disetel di bawah HPP aktif
                if (Decimal::compare((string) $hppSnapshot, '0.00', 2) > 0 && Decimal::compare($minAmount, $hppSnapshot, 2) < 0) {
                    $warnings[] = "Harga Min {$ringDef['label']} (Rp ".CurrencyFormatter::rupiah($minAmount).') di bawah HPP (Rp '.CurrencyFormatter::rupiah($hppSnapshot).')';
                }

                // Find existing active price
                $existing = ProductPrice::query()
                    ->where('product_id', $product->id)
                    ->where('work_location_id', $workLocationId)
                    ->where(function ($q) use ($ringDef) {
                        $q->whereIn('price_ring', $ringDef['aliases'])
                            ->orWhere(function ($sub) use ($ringDef) {
                                $sub->where('channel', $ringDef['channel'])
                                    ->whereIn('price_ring', $ringDef['aliases']);
                            });
                    })
                    ->where('status', ProductPriceStatus::ACTIVE->value)
                    ->latest('id')
                    ->first();

                $oldPrice = $existing?->recommended_price;
                $oldMin = $existing?->min_price;
                $oldMax = $existing?->max_price;

                if ($existing) {
                    $existing->update([
                        'price_ring' => $ringDef['price_ring'],
                        'channel' => $ringDef['channel'],
                        'branch_id' => $branchId,
                        'min_price' => $minAmount,
                        'recommended_price' => $recAmount,
                        'max_price' => $maxAmount,
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'status' => ProductPriceStatus::ACTIVE,
                        'notes' => $validated['notes'] ?? ('Diperbarui per item oleh '.$user->name),
                    ]);
                    $priceRecord = $existing;
                } else {
                    $priceRecord = ProductPrice::create([
                        'product_id' => $product->id,
                        'work_location_id' => $workLocationId,
                        'branch_id' => $branchId,
                        'channel' => $ringDef['channel'],
                        'price_ring' => $ringDef['price_ring'],
                        'customer_category' => null,
                        'min_price' => $minAmount,
                        'recommended_price' => $recAmount,
                        'max_price' => $maxAmount,
                        'minimum_qty' => 1,
                        'priority' => 10,
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'status' => ProductPriceStatus::ACTIVE,
                        'notes' => $validated['notes'] ?? ('Dibuat per item oleh '.$user->name),
                    ]);
                }

                $savedCount++;

                // Jika harga berubah atau baru disetel: Catat Audit Log lengkap
                $isChanged = $oldPrice === null
                    || Decimal::compare((string) $oldPrice, (string) $recAmount, 2) !== 0
                    || Decimal::compare((string) $oldMin, (string) $minAmount, 2) !== 0
                    || Decimal::compare((string) $oldMax, (string) $maxAmount, 2) !== 0;

                $shouldRecordHistory = $isChanged && (! $isSinglePriceInput || $ringKey === 'ring_retail');

                if ($shouldRecordHistory) {
                    // 1. Riwayat harga
                    PriceHistory::create([
                        'priceable_type' => ProductPrice::class,
                        'priceable_id' => $priceRecord->id,
                        'product_id' => $product->id,
                        'branch_id' => $branchId,
                        'work_location_id' => $workLocationId,
                        'channel' => $ringDef['channel'],
                        'price_ring' => $ringDef['price_ring'],
                        'old_price' => $oldPrice,
                        'new_price' => $recAmount,
                        'hpp_snapshot' => $hppSnapshot,
                        'minimum_price_snapshot' => $minAmount,
                        'changed_by' => $user->id,
                        'source' => 'product_prices',
                        'reason' => $validated['notes'] ?? 'Perubahan harga per barang (langsung aktif)',
                        'effective_at' => $startsAt,
                    ]);

                    // 2. Audit Log sistem (Spatie Activitylog)
                    activity()
                        ->causedBy($user)
                        ->performedOn($product)
                        ->withProperties([
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'sku' => $product->sku,
                            'work_location_id' => $workLocationId,
                            'work_location_name' => $workLocation ? $workLocation->name : 'Semua Lokasi',
                            'channel' => $ringDef['channel'],
                            'price_ring' => $ringDef['price_ring'],
                            'old_price' => $oldPrice,
                            'new_price' => $recAmount,
                            'min_price' => $minAmount,
                            'recommended_price' => $recAmount,
                            'max_price' => $maxAmount,
                            'hpp_snapshot' => $hppSnapshot,
                            'starts_at' => $startsAt,
                            'ends_at' => $endsAt,
                            'responsible_user' => $user->name,
                            'action' => 'product_price.updated',
                        ])
                        ->log('product_price.updated');
                }
            }
        });

        $message = "Harga untuk {$product->name} berhasil disimpan.";
        if (! empty($warnings)) {
            $message .= ' Peringatan: '.implode('; ', $warnings).'.';
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'has_warning' => ! empty($warnings),
                'warnings' => $warnings,
                'product_id' => $product->id,
                'saved_count' => $savedCount,
            ]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => $message]);
    }

    public function batchSave(Request $request): JsonResponse
    {
        $this->authorize('update', ProductPrice::class);

        $validated = $request->validate([
            'work_location_id' => ['nullable', 'integer', 'exists:work_locations,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ]);

        $user = $request->user();

        if ($user->hasUnrestrictedLocationScope()) {
            $workLocationId = $validated['work_location_id'] ?? null;
        } else {
            $permittedIds = $user->permittedWorkLocationIds();
            abort_if(empty($permittedIds), 403, 'Akun Anda belum memiliki penugasan lokasi kerja.');

            $requestedId = $request->filled('work_location_id') ? $request->integer('work_location_id') : null;
            if ($requestedId && in_array($requestedId, $permittedIds, true)) {
                $workLocationId = $requestedId;
            } else {
                $defaultLocation = $user->workLocations()->wherePivot('is_default', true)->first() ?? $user->workLocations()->first();
                $workLocationId = $defaultLocation ? $defaultLocation->id : $permittedIds[0];
            }
        }

        $branchId = $workLocationId ? Branch::where('work_location_id', $workLocationId)->value('id') : null;
        $workLocation = $workLocationId ? WorkLocation::find($workLocationId) : null;
        $startsAt = now()->toDateString();
        $updatedCount = 0;
        $warnings = [];

        DB::transaction(function () use ($validated, $workLocationId, $branchId, $startsAt, $user, &$updatedCount, &$warnings): void {
            foreach ($validated['items'] as $item) {
                $productId = (int) $item['product_id'];
                $priceAmount = Decimal::normalize((string) $item['price'], 2);

                if (Decimal::compare($priceAmount, '0.00', 2) <= 0) {
                    continue;
                }

                $product = Product::find($productId);
                if (! $product) {
                    continue;
                }

                $batchHpp = StockBatch::where('product_id', $product->id)
                    ->when($workLocationId !== null, fn ($q) => $q->where('work_location_id', $workLocationId))
                    ->available()
                    ->latest('id')
                    ->value('cost_price');
                $hppSnapshot = $batchHpp !== null
                    ? Decimal::normalize((string) $batchHpp, 2)
                    : Decimal::normalize((string) ($product->cost_price ?? '0.00'), 2);

                if (Decimal::compare((string) $hppSnapshot, '0.00', 2) > 0 && Decimal::compare($priceAmount, $hppSnapshot, 2) < 0) {
                    $warnings[] = "Harga {$product->name} (Rp ".CurrencyFormatter::rupiah($priceAmount).') di bawah HPP (Rp '.CurrencyFormatter::rupiah($hppSnapshot).')';
                }

                $primaryExisting = ProductPrice::query()
                    ->where('product_id', $product->id)
                    ->where('work_location_id', $workLocationId)
                    ->where('channel', 'retail')
                    ->first();
                $oldPrice = $primaryExisting?->recommended_price;

                $firstRecord = null;
                foreach (self::DEFAULT_RINGS as $ringDef) {
                    $existing = ProductPrice::query()
                        ->where('product_id', $product->id)
                        ->where('work_location_id', $workLocationId)
                        ->where(function ($q) use ($ringDef) {
                            $q->whereIn('price_ring', $ringDef['aliases'])
                                ->orWhere(function ($sub) use ($ringDef) {
                                    $sub->where('channel', $ringDef['channel'])
                                        ->whereIn('price_ring', $ringDef['aliases']);
                                });
                        })
                        ->where('status', ProductPriceStatus::ACTIVE->value)
                        ->latest('id')
                        ->first();

                    if ($existing) {
                        $existing->update([
                            'price_ring' => $ringDef['price_ring'],
                            'channel' => $ringDef['channel'],
                            'branch_id' => $branchId,
                            'min_price' => $priceAmount,
                            'recommended_price' => $priceAmount,
                            'max_price' => $priceAmount,
                            'starts_at' => $startsAt,
                            'ends_at' => null,
                            'status' => ProductPriceStatus::ACTIVE,
                            'notes' => 'Update masal oleh '.$user->name,
                        ]);
                        $priceRecord = $existing;
                    } else {
                        $priceRecord = ProductPrice::create([
                            'product_id' => $product->id,
                            'work_location_id' => $workLocationId,
                            'branch_id' => $branchId,
                            'channel' => $ringDef['channel'],
                            'price_ring' => $ringDef['price_ring'],
                            'customer_category' => null,
                            'min_price' => $priceAmount,
                            'recommended_price' => $priceAmount,
                            'max_price' => $priceAmount,
                            'minimum_qty' => 1,
                            'priority' => 10,
                            'starts_at' => $startsAt,
                            'ends_at' => null,
                            'status' => ProductPriceStatus::ACTIVE,
                            'notes' => 'Update masal oleh '.$user->name,
                        ]);
                    }

                    if ($ringDef['channel'] === 'retail') {
                        $firstRecord = $priceRecord;
                    }
                }

                $isChanged = $oldPrice === null || Decimal::compare((string) $oldPrice, $priceAmount, 2) !== 0;

                if ($isChanged) {
                    PriceHistory::create([
                        'priceable_type' => ProductPrice::class,
                        'priceable_id' => $firstRecord->id,
                        'product_id' => $product->id,
                        'branch_id' => $branchId,
                        'work_location_id' => $workLocationId,
                        'channel' => 'retail',
                        'price_ring' => 'Ring Retail',
                        'old_price' => $oldPrice,
                        'new_price' => $priceAmount,
                        'hpp_snapshot' => $hppSnapshot,
                        'minimum_price_snapshot' => $priceAmount,
                        'changed_by' => $user->id,
                        'source' => 'product_prices',
                        'reason' => 'Simpan masal harga produk',
                        'effective_at' => $startsAt,
                    ]);

                    activity()
                        ->causedBy($user)
                        ->performedOn($product)
                        ->withProperties([
                            'product_id' => $product->id,
                            'work_location_id' => $workLocationId,
                            'old_price' => $oldPrice,
                            'new_price' => $priceAmount,
                            'source' => 'product_prices_batch',
                        ])
                        ->log('product_price.batch_updated');

                    $updatedCount++;
                }
            }
        });

        $message = "{$updatedCount} harga produk berhasil diperbarui.";
        if (! empty($warnings)) {
            $message .= ' Catatan: '.count($warnings).' produk disimpan dengan harga di bawah HPP.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'updated_count' => $updatedCount,
            'warnings' => $warnings,
        ]);
    }

    public function store(StoreProductPriceRequest $request, PriceManagementService $service): RedirectResponse
    {
        $data = $request->validated();
        $productIds = array_values(array_unique($data['product_ids'] ?? [$data['product_id']]));
        unset($data['product_ids']);

        $user = $request->user();
        if (! $user->hasUnrestrictedLocationScope()) {
            $permittedIds = $user->permittedWorkLocationIds();
            abort_if(empty($permittedIds), 403, 'Akun Anda belum memiliki penugasan lokasi kerja.');
            $defaultLocation = $user->workLocations()->wherePivot('is_default', true)->first() ?? $user->workLocations()->first();
            $locId = $defaultLocation ? $defaultLocation->id : $permittedIds[0];
            $data['work_location_id'] = $locId;
            $data['branch_id'] = Branch::where('work_location_id', $locId)->value('id');
        } elseif (! empty($data['branch_id'])) {
            $data['work_location_id'] = Branch::where('id', $data['branch_id'])->value('work_location_id');
        }

        $price = null;
        foreach ($productIds as $productId) {
            $price = $service->saveProductPrice([...$data, 'product_id' => $productId], $user);
        }

        assert($price instanceof ProductPrice);

        $message = count($productIds).' harga produk berhasil disimpan.';

        return back()->with('notification', ['type' => 'success', 'message' => $message]);
    }

    public function edit(ProductPrice $productPrice): View
    {
        $this->authorize('update', $productPrice);

        return view('pricing.product-prices.edit', [
            'price' => $productPrice->load(['product', 'branch']),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(StoreProductPriceRequest $request, ProductPrice $productPrice, PriceManagementService $service): RedirectResponse
    {
        $this->authorize('update', $productPrice);

        $data = $request->validated();
        $service->saveProductPrice([...$data, 'id' => $productPrice->id, 'product_id' => $productPrice->product_id], $request->user());

        return redirect()->route('pricing.product-prices.edit', $productPrice)
            ->with('notification', ['type' => 'success', 'message' => 'Harga berhasil diperbarui.']);
    }

    public function revise(StoreProductPriceRequest $request, ProductPrice $productPrice, PriceManagementService $service): RedirectResponse
    {
        $this->authorize('update', $productPrice);
        $data = $request->validated();
        $revision = $service->saveProductPrice([...$data, 'product_id' => $productPrice->product_id], $request->user());

        return redirect()->route('pricing.product-prices.edit', $revision)
            ->with('notification', ['type' => 'success', 'message' => 'Revisi harga berhasil dibuat dan langsung aktif.']);
    }

    public function end(Request $request, ProductPrice $productPrice, PriceManagementService $service): RedirectResponse
    {
        $this->authorize('update', $productPrice);
        $data = Validator::make($request->all(), [
            'ends_at' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:2000'],
        ], [], ['ends_at' => 'tanggal selesai', 'reason' => 'alasan'])->validate();

        $service->endProductPrice($productPrice, $data['ends_at'], $request->user(), $data['reason']);

        return redirect()->route('pricing.product-prices.edit', $productPrice)
            ->with('notification', ['type' => 'success', 'message' => 'Masa berlaku harga berhasil diperbarui tanpa menghapus histori.']);
    }

    public function destroy(ProductPrice $productPrice): RedirectResponse
    {
        $this->authorize('delete', $productPrice);

        DB::transaction(fn () => $productPrice->delete());

        return redirect()->route('pricing.product-prices.index')
            ->with('notification', ['type' => 'success', 'message' => 'Harga produk berhasil dihapus dari daftar. Histori audit tetap disimpan.']);
    }

    public function searchProducts(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ProductPrice::class);

        $query = $request->input('q', '');
        $exclude = $request->input('exclude', []);
        if (is_array($exclude)) {
            $exclude = array_map('intval', $exclude);
        } else {
            $exclude = array_filter(array_map('intval', explode(',', (string) $exclude)));
        }

        $results = Product::query()
            ->where('status', 'active')
            ->with('category')
            ->when($query !== '', function ($q) use ($query) {
                $q->where(function ($w) use ($query) {
                    $w->where('sku', 'like', "%{$query}%")
                        ->orWhere('name', 'like', "%{$query}%");
                    if (preg_match('/^[\p{L}0-9\s\-_]+$/u', $query)) {
                        $w->orWhere('description', 'like', "%{$query}%");
                    }
                });
            })
            ->when(! empty($exclude), fn ($q) => $q->whereNotIn('id', $exclude))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'sku', 'name', 'category_id']);

        return response()->json([
            'products' => $results->map(fn ($p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'category' => $p->category?->name,
            ]),
            'total' => $results->count(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', ProductPrice::class);

        return response()->streamDownload(function () use ($request): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Produk', 'Nama', 'Cabang', 'Channel', 'Ring', 'Kategori', 'Min', 'Rekomendasi', 'Maks', 'Min Qty', 'Status', 'Mulai', 'Selesai']);

            ProductPrice::query()
                ->with(['product', 'branch'])
                ->when($request->filled('product_id'), fn ($query) => $query->where('product_id', $request->integer('product_id')))
                ->when($request->filled('channel'), fn ($query) => $query->where('channel', $request->query('channel')))
                ->latest('id')
                ->each(function (ProductPrice $price) use ($handle): void {
                    fputcsv($handle, [
                        $price->product?->sku,
                        $price->product?->name,
                        $price->branch?->name,
                        $price->channel,
                        $price->price_ring,
                        $price->customer_category,
                        $price->min_price,
                        $price->recommended_price,
                        $price->max_price,
                        $price->minimum_qty,
                        $price->status->value,
                        $price->starts_at,
                        $price->ends_at,
                    ]);
                });
            fclose($handle);
        }, 'product-prices.csv');
    }

    /**
     * @param  Collection<int, StockBatch>  $batches
     * @return array{can_view: bool, has_layers: bool, is_master?: bool, tiers: list<array<string, mixed>>}
     */
    protected function buildHppInfo(Product $product, Collection $batches, bool $canViewSensitive): array
    {
        if (! $canViewSensitive) {
            return [
                'can_view' => false,
                'has_layers' => false,
                'tiers' => [],
            ];
        }

        if ($batches->isEmpty()) {
            return [
                'can_view' => true,
                'has_layers' => false,
                'is_master' => true,
                'tiers' => [
                    [
                        'cost_price' => $product->cost_price,
                        'cost_formatted' => CurrencyFormatter::rupiah($product->cost_price ?? 0),
                        'total_qty' => null,
                        'unit' => $product->baseUnit ? $product->baseUnit->symbol : 'pcs',
                    ],
                ],
            ];
        }

        $grouped = $batches->groupBy(fn (StockBatch $b) => (string) $b->cost_price);
        $hasLayers = $grouped->count() > 1;
        $tiers = [];

        foreach ($grouped as $cost => $batchGroup) {
            $totalQty = '0.0000';
            foreach ($batchGroup as $b) {
                $totalQty = Decimal::add($totalQty, $b->availableQuantity(), 4);
            }
            $unitName = $product->baseUnit ? $product->baseUnit->symbol : 'pcs';
            $batchNos = $batchGroup->pluck('batch_no')->filter()->unique()->take(3)->implode(', ');

            $tiers[] = [
                'cost_price' => $cost,
                'cost_formatted' => CurrencyFormatter::rupiah($cost),
                'total_qty' => $totalQty,
                'unit' => $unitName,
                'batch_nos' => $batchNos,
            ];
        }

        return [
            'can_view' => true,
            'has_layers' => $hasLayers,
            'is_master' => false,
            'tiers' => $tiers,
        ];
    }
}
