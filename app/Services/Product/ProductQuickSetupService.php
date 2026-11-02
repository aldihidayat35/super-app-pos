<?php

namespace App\Services\Product;

use App\Enums\ProductPriceStatus;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductCostHistory;
use App\Models\ProductPrice;
use App\Models\Stock;
use App\Models\StockBatch;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkLocation;
use App\Services\Control\AuditLogService;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\StockLayerService;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProductQuickSetupService
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

    public function __construct(
        private readonly InventoryService $inventory,
        private readonly StockLayerService $layers,
        private readonly UnitConversionService $conversion,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * @param  array{completeness?: string, category_id?: int|string, q?: string, page?: int, per_page?: int}  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function getProducts(array $filters, WorkLocation $workLocation): LengthAwarePaginator
    {
        $completeness = $filters['completeness'] ?? 'incomplete';
        $categoryId = filled($filters['category_id'] ?? null) ? (int) $filters['category_id'] : null;
        $search = trim((string) ($filters['q'] ?? ''));
        $perPage = max(10, min(100, (int) ($filters['per_page'] ?? 25)));

        $today = now()->toDateString();
        $query = Product::query()
            ->with([
                'category',
                'brand',
                'baseUnit',
                'units.unit',
                'prices' => function ($q) use ($workLocation, $today): void {
                    $q->where(function ($inner) use ($workLocation): void {
                        $inner->where('work_location_id', $workLocation->id)
                            ->orWhereNull('work_location_id');
                    })
                        ->where('status', ProductPriceStatus::ACTIVE->value)
                        ->where(fn ($sub) => $sub->whereNull('starts_at')->orWhereDate('starts_at', '<=', $today))
                        ->where(fn ($sub) => $sub->whereNull('ends_at')->orWhereDate('ends_at', '>=', $today))
                        ->orderByDesc('work_location_id')
                        ->orderBy('priority');
                },
                'stockBatches' => fn ($q) => $q->where('work_location_id', $workLocation->id)->where('source_type', 'opening_stock'),
                'stocks' => fn ($q) => $q->where('work_location_id', $workLocation->id),
            ])
            ->where('status', 'active')
            ->when($categoryId !== null, fn (Builder $q) => $q->where('category_id', $categoryId))
            ->when($search !== '', function (Builder $q) use ($search): void {
                $term = '%'.addcslashes($search, '%_').'%';
                $q->where(fn (Builder $inner) => $inner->where('sku', 'like', $term)->orWhere('name', 'like', $term));
            });

        // Filter status kelengkapan
        if ($completeness === 'incomplete') {
            $query->where(function (Builder $q) use ($workLocation): void {
                $q->whereDoesntHave('prices', fn ($p) => $p->where('work_location_id', $workLocation->id)->where('status', 'active')->where('recommended_price', '>', 0))
                    ->orWhereDoesntHave('stockBatches', fn ($b) => $b->where('work_location_id', $workLocation->id)->where('source_type', 'opening_stock'))
                    ->orWhere('cost_price', '<=', 0);
            });
        } elseif ($completeness === 'complete') {
            $query->whereHas('prices', fn ($p) => $p->where('work_location_id', $workLocation->id)->where('status', 'active')->where('recommended_price', '>', 0))
                ->whereHas('stockBatches', fn ($b) => $b->where('work_location_id', $workLocation->id)->where('source_type', 'opening_stock'))
                ->where('cost_price', '>', 0);
        }

        return $query->orderBy('name')->paginate($perPage)->withQueryString();
    }

    /**
     * @return array{total_products: int, incomplete_count: int, complete_count: int}
     */
    public function getStats(WorkLocation $workLocation): array
    {
        $total = Product::query()->where('status', 'active')->count();

        $completeCount = Product::query()
            ->where('status', 'active')
            ->whereHas('prices', fn ($p) => $p->where('work_location_id', $workLocation->id)->where('status', 'active')->where('recommended_price', '>', 0))
            ->whereHas('stockBatches', fn ($b) => $b->where('work_location_id', $workLocation->id)->where('source_type', 'opening_stock'))
            ->where('cost_price', '>', 0)
            ->count();

        return [
            'total_products' => $total,
            'complete_count' => $completeCount,
            'incomplete_count' => max(0, $total - $completeCount),
        ];
    }

    /**
     * Mengambil rasio konversi satuan ke base unit (PCS).
     */
    public function resolveUnitFactor(Product $product, int $unitId): string
    {
        $unit = Unit::query()->find($unitId);
        if (! $unit) {
            return '1.000000';
        }

        $code = strtoupper((string) $unit->code);
        if ($code === 'PCS') {
            return '1.000000';
        }
        if ($code === 'LSN') {
            return '12.000000';
        }
        if ($code === 'GROS') {
            return '144.000000';
        }

        $existing = $product->units->firstWhere('unit_id', $unitId);
        if ($existing && Decimal::compare((string) $existing->conversion_factor, '0', 6) > 0) {
            return (string) $existing->conversion_factor;
        }

        return '1.000000';
    }

    /**
     * Menyimpan data per baris produk.
     *
     * @param array{
     *     product_id: int,
     *     hpp_unit_id: int,
     *     hpp_amount: string|numeric,
     *     stock_unit_id: int,
     *     stock_qty: string|numeric,
     *     prices: array<string, mixed>,
     *     unlimited?: bool|string|int,
     *     starts_at?: string|null,
     *     ends_at?: string|null,
     *     notes?: string|null
     * } $data
     * @return array{
     *     product_id: int,
     *     sku: string,
     *     name: string,
     *     retail_cost_price: string,
     *     initial_stock_base_qty: string,
     *     stock_initialized: bool,
     *     message: string
     * }
     */
    public function saveRow(array $data, User $actor, WorkLocation $workLocation, ?Request $request = null): array
    {
        return DB::transaction(function () use ($data, $actor, $workLocation, $request): array {
            /** @var Product $product */
            $product = Product::query()->lockForUpdate()->findOrFail($data['product_id']);

            $hppUnitId = (int) $data['hpp_unit_id'];
            $hppAmount = Decimal::normalize((string) $data['hpp_amount'], 2);
            $stockUnitId = (int) $data['stock_unit_id'];
            $stockQty = Decimal::normalize((string) $data['stock_qty'], 4);

            $hppFactor = $this->resolveUnitFactor($product, $hppUnitId);
            $stockFactor = $this->resolveUnitFactor($product, $stockUnitId);

            if (Decimal::compare($hppFactor, '0', 6) <= 0) {
                $hppFactor = '1.000000';
            }
            if (Decimal::compare($stockFactor, '0', 6) <= 0) {
                $stockFactor = '1.000000';
            }

            // 1. Hitung HPP Eceran (per pcs): HPP Beli / Rasio Konversi Satuan HPP
            $retailCostPrice = Decimal::div($hppAmount, $hppFactor, 2, 6, 2);

            // 2. Hitung Total Kuantitas Eceran (pcs): Stok Input * Rasio Konversi Satuan Stok
            $initialStockBaseQty = Decimal::mul($stockQty, $stockFactor, 4, 6, 4);

            // 3. Pastikan satuan dasar produk (PCS)
            $pcsUnit = Unit::query()->where('code', 'PCS')->first() ?? Unit::query()->first();
            $baseUnitId = $pcsUnit ? $pcsUnit->id : $product->base_unit_id;

            $attributes = $product->attributes ?? [];
            $quickSetupAttrs = (array) ($attributes['quick_setup'] ?? []);
            $locationsAttrs = (array) ($quickSetupAttrs['locations'] ?? []);

            $locationsAttrs[$workLocation->id] = [
                'hpp_unit_id' => $hppUnitId,
                'hpp_amount' => $hppAmount,
                'stock_unit_id' => $stockUnitId,
                'stock_qty' => $stockQty,
            ];

            $quickSetupAttrs['hpp_unit_id'] = $hppUnitId;
            $quickSetupAttrs['hpp_amount'] = $hppAmount;
            $quickSetupAttrs['stock_unit_id'] = $stockUnitId;
            $quickSetupAttrs['stock_qty'] = $stockQty;
            $quickSetupAttrs['locations'] = $locationsAttrs;

            $attributes['quick_setup'] = $quickSetupAttrs;

            $product->forceFill([
                'base_unit_id' => $baseUnitId,
                'cost_price' => $retailCostPrice,
                'attributes' => $attributes,
            ])->save();

            // 4. Sinkronisasi tabel product_units
            $unitsPayload = [
                [
                    'unit_id' => $baseUnitId,
                    'conversion_factor' => '1.000000',
                    'name' => 'Satuan dasar',
                    'is_sellable' => true,
                    'is_active' => true,
                ],
            ];

            if ($hppUnitId !== $baseUnitId) {
                $unitsPayload[] = [
                    'unit_id' => $hppUnitId,
                    'conversion_factor' => $hppFactor,
                    'name' => Unit::find($hppUnitId)?->name ?: 'Satuan pembelian',
                    'is_sellable' => true,
                    'is_active' => true,
                ];
            }

            if ($stockUnitId !== $baseUnitId && $stockUnitId !== $hppUnitId) {
                $unitsPayload[] = [
                    'unit_id' => $stockUnitId,
                    'conversion_factor' => $stockFactor,
                    'name' => Unit::find($stockUnitId)?->name ?: 'Satuan persediaan',
                    'is_sellable' => true,
                    'is_active' => true,
                ];
            }

            $this->conversion->syncProductUnits($product, $unitsPayload);

            // 5. Masa Berlaku & Branch ID
            $branchId = Branch::query()->where('work_location_id', $workLocation->id)->value('id');
            $isUnlimited = filter_var($data['unlimited'] ?? true, FILTER_VALIDATE_BOOLEAN) || empty($data['ends_at']);
            $startsAt = filled($data['starts_at'] ?? null) ? $data['starts_at'] : now()->toDateString();
            $endsAt = $isUnlimited ? null : ($data['ends_at'] ?? null);

            // 6. Simpan Ketiga Ring Harga (Ring Retail, Ring B2B, Ring POS) persis seperti di ProductPriceController
            $incomingPrices = (array) $data['prices'];
            foreach (self::DEFAULT_RINGS as $rKey => $rConf) {
                $raw = $incomingPrices[$rKey] ?? null;
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

                if (($rawRec === null || $rawRec === '') && ($rawMin === null || $rawMin === '') && ($rawMax === null || $rawMax === '')) {
                    continue;
                }

                $recVal = ($rawRec !== null && $rawRec !== '') ? Decimal::normalize((string) $rawRec, 2) : '0.00';
                $minVal = ($rawMin !== null && $rawMin !== '') ? Decimal::normalize((string) $rawMin, 2) : $recVal;
                $maxVal = ($rawMax !== null && $rawMax !== '') ? Decimal::normalize((string) $rawMax, 2) : $recVal;

                if (Decimal::compare($recVal, '0.00', 2) <= 0 && Decimal::compare($minVal, '0.00', 2) <= 0) {
                    continue;
                }

                // Update or create product_prices record for this ring
                ProductPrice::query()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'work_location_id' => $workLocation->id,
                        'channel' => $rConf['channel'],
                        'price_ring' => $rConf['price_ring'],
                    ],
                    [
                        'branch_id' => $branchId,
                        'min_price' => $minVal,
                        'recommended_price' => $recVal,
                        'max_price' => $maxVal,
                        'minimum_qty' => 1,
                        'priority' => 10,
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'status' => ProductPriceStatus::ACTIVE->value,
                        'notes' => $data['notes'] ?? 'Setup operasional cepat produk',
                    ]
                );
            }

            // 7. Pencatatan Persediaan Awal (Stok Awal) - Idempotent
            $stockInitialized = false;
            $stockMessage = 'Parameter HPP dan harga jual berhasil disimpan.';

            $existingOpeningBatch = StockBatch::query()
                ->where('product_id', $product->id)
                ->where('work_location_id', $workLocation->id)
                ->where('source_type', 'opening_stock')
                ->first();

            $retailPriceForSnapshot = ProductPrice::query()
                ->where('product_id', $product->id)
                ->where('work_location_id', $workLocation->id)
                ->where('channel', 'retail')
                ->value('recommended_price');

            if (! $existingOpeningBatch && Decimal::compare($stockQty, '0', 4) > 0) {
                $batchNo = 'OPN-'.now()->format('Ymd').'-'.strtoupper(Str::random(5));

                // Mutasi stok via InventoryService
                $mutation = $this->inventory->adjust(
                    product: $product,
                    workLocation: $workLocation,
                    warehouseLocation: null,
                    targetOnHand: $initialStockBaseQty,
                    actor: $actor,
                    reference: ['type' => 'opening_stock', 'no' => $batchNo],
                    reason: 'Setup operasional awal persediaan',
                    idempotencyKey: "quick-setup-{$product->id}-{$workLocation->id}-".now()->timestamp,
                    metadata: ['hpp' => $retailCostPrice, 'source' => 'quick_operational_setup'],
                );

                // Buat StockBatch FIFO layer
                $layer = $this->layers->createLayer([
                    'source_batch_id' => null,
                    'product_id' => $product->id,
                    'stock_id' => $mutation->stock_id,
                    'work_location_id' => $workLocation->id,
                    'warehouse_location_id' => null,
                    'source_type' => 'opening_stock',
                    'source_id' => null,
                    'source_item_id' => null,
                    'supplier_id' => null,
                    'goods_receipt_id' => null,
                    'goods_receipt_item_id' => null,
                    'unit_id' => $stockUnitId,
                    'conversion_factor_snapshot' => $stockFactor,
                    'batch_no' => $batchNo,
                    'received_at' => now()->toDateString(),
                    'expires_at' => null,
                    'cost_price' => $retailCostPrice,
                    'entry_sale_price_snapshot' => $retailPriceForSnapshot,
                    'quantity_on_hand' => $initialStockBaseQty,
                    'quantity_reserved' => '0.0000',
                    'quantity_damaged' => '0.0000',
                ]);

                // Catat alokasi layer
                $this->layers->recordAllocation($layer, [
                    'reference_type' => 'opening_stock',
                    'reference_id' => $layer->id,
                    'event_type' => 'opening_stock',
                    'quantity' => $initialStockBaseQty,
                    'unit_cost' => $retailCostPrice,
                    'sale_price_snapshot' => $retailPriceForSnapshot,
                    'actor_user_id' => $actor->id,
                    'metadata' => ['batch_no' => $batchNo, 'source' => 'quick_operational_setup'],
                    'occurred_at' => now(),
                ]);

                // Sinkronisasi nilai persediaan stok
                $stock = Stock::query()->find($mutation->stock_id);
                if ($stock instanceof Stock) {
                    $stock->forceFill([
                        'cost_value' => Decimal::mul((string) $stock->quantity_on_hand, $retailCostPrice, 2),
                    ])->save();
                }

                // Catat riwayat biaya produk
                ProductCostHistory::query()->create([
                    'product_id' => $product->id,
                    'method' => 'opening_stock',
                    'source_type' => 'opening_stock',
                    'source_reference' => $batchNo,
                    'changed_by' => $actor->id,
                    'reason' => 'Setup operasional awal persediaan',
                    'qty_before' => '0.0000',
                    'qty_incoming' => $initialStockBaseQty,
                    'qty_after' => $initialStockBaseQty,
                    'cost_before' => '0.00',
                    'cost_incoming' => $retailCostPrice,
                    'cost_after' => $retailCostPrice,
                    'effective_at' => now(),
                ]);

                $stockInitialized = true;
                $stockMessage = "Stok awal {$stockQty} unit ({$initialStockBaseQty} pcs) berhasil dialokasikan dan diposting.";
            } elseif ($existingOpeningBatch && Decimal::compare($stockQty, '0', 4) > 0) {
                $stockMessage = 'Stok awal sudah pernah dicatat sebelumnya di lokasi ini; saldo persediaan tidak diduplikasi.';
            }

            // 8. Audit log penanggung jawab
            $this->audit->record(
                event: 'product.quick_operational_setup',
                module: 'products',
                actor: $actor,
                subject: $product,
                oldValues: [],
                newValues: [
                    'hpp_unit_id' => $hppUnitId,
                    'hpp_amount' => $hppAmount,
                    'retail_cost_price' => $retailCostPrice,
                    'stock_unit_id' => $stockUnitId,
                    'stock_qty' => $stockQty,
                    'initial_stock_base_qty' => $initialStockBaseQty,
                    'stock_initialized' => $stockInitialized,
                    'prices' => $incomingPrices,
                ],
                reason: "Setup operasional cepat oleh {$actor->name} di {$workLocation->name}: {$stockMessage}",
                request: $request,
                location: $workLocation,
                severity: 'info'
            );

            return [
                'product_id' => (int) $product->id,
                'sku' => (string) $product->sku,
                'name' => (string) $product->name,
                'retail_cost_price' => $retailCostPrice,
                'initial_stock_base_qty' => $initialStockBaseQty,
                'stock_initialized' => $stockInitialized,
                'message' => $stockMessage,
            ];
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{saved_count: int, failed_count: int, results: list<array<string, mixed>>, errors: list<string>}
     */
    public function saveBatch(array $rows, User $actor, WorkLocation $workLocation, ?Request $request = null): array
    {
        $savedCount = 0;
        $failedCount = 0;
        $results = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $line = $index + 1;
            try {
                /** @var array{product_id: int, hpp_unit_id: int, hpp_amount: string|numeric, stock_unit_id: int, stock_qty: string|numeric, prices: array<string, mixed>, unlimited?: bool|string|int, starts_at?: string|null, ends_at?: string|null, notes?: string|null} $validatedRow */
                $validatedRow = $row;
                $result = $this->saveRow($validatedRow, $actor, $workLocation, $request);
                $results[] = $result;
                $savedCount++;
            } catch (\Throwable $e) {
                $failedCount++;
                $errors[] = "Baris {$line} (".($row['name'] ?? "ID {$row['product_id']}").'): '.$e->getMessage();
            }
        }

        return [
            'saved_count' => $savedCount,
            'failed_count' => $failedCount,
            'results' => $results,
            'errors' => $errors,
        ];
    }
}
