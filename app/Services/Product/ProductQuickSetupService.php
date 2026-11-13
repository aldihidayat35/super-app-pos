<?php

namespace App\Services\Product;

use App\Enums\ProductPriceStatus;
use App\Models\Branch;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\ProductBarcode;
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
use Illuminate\Validation\ValidationException;

final class ProductQuickSetupService
{
    public const CHANNELS = [
        'retail' => 'Ring Retail',
        'pos' => 'Ring POS',
        'b2b' => 'Ring B2B',
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
                'barcodes',
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
                $q->where(fn (Builder $inner) => $inner->where('sku', 'like', $term)
                    ->orWhere('name', 'like', $term)
                    ->orWhereHas('barcodes', fn ($b) => $b->where('code', 'like', $term))
                );
            });

        // Filter status kelengkapan
        if ($completeness === 'incomplete') {
            $query->where(function (Builder $q) use ($workLocation): void {
                $q->whereDoesntHave('prices', fn ($p) => $p->where('work_location_id', $workLocation->id)->where('status', 'active')->where('recommended_price', '>', 0))
                    ->orWhereDoesntHave('stockBatches', fn ($b) => $b->where('work_location_id', $workLocation->id)->where('source_type', 'opening_stock'))
                    ->orWhere('cost_price', '<=', 0)
                    ->orWhere('minimum_price', '<=', 0);
            });
        } elseif ($completeness === 'complete') {
            $query->whereHas('prices', fn ($p) => $p->where('work_location_id', $workLocation->id)->where('status', 'active')->where('recommended_price', '>', 0))
                ->whereHas('stockBatches', fn ($b) => $b->where('work_location_id', $workLocation->id)->where('source_type', 'opening_stock'))
                ->where('cost_price', '>', 0)
                ->where('minimum_price', '>', 0);
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
            ->where('minimum_price', '>', 0)
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
     * Menyimpan data per baris produk dengan 1 Harga Jual Minimum (Anti-Rugi).
     *
     * @param array{
     *     product_id: int,
     *     barcode?: string|null,
     *     hpp_unit_id: int,
     *     hpp_amount: string|numeric,
     *     stock_unit_id: int,
     *     stock_qty: string|numeric,
     *     sell_unit_id?: int|null,
     *     min_price_amount?: string|numeric|null,
     *     retail_min_price?: string|numeric|null,
     *     prices?: array<string, mixed>|null,
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
     *     retail_min_price: string,
     *     margin_amount: string,
     *     margin_percent: string,
     *     initial_stock_base_qty: string,
     *     stock_initialized: bool,
     *     is_complete: bool,
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

            $pcsUnit = Unit::query()->where('code', 'PCS')->first() ?? Unit::query()->first();
            $baseUnitId = $pcsUnit ? $pcsUnit->id : $product->base_unit_id;

            $sellUnitId = (int) ($data['sell_unit_id'] ?? $baseUnitId);

            $hppFactor = $this->resolveUnitFactor($product, $hppUnitId);
            $stockFactor = $this->resolveUnitFactor($product, $stockUnitId);
            $sellFactor = $this->resolveUnitFactor($product, $sellUnitId);

            if (Decimal::compare($hppFactor, '0', 6) <= 0) {
                $hppFactor = '1.000000';
            }
            if (Decimal::compare($stockFactor, '0', 6) <= 0) {
                $stockFactor = '1.000000';
            }
            if (Decimal::compare($sellFactor, '0', 6) <= 0) {
                $sellFactor = '1.000000';
            }

            // 1. Hitung HPP Eceran (per pcs): HPP Beli / Rasio Konversi Satuan HPP
            $retailCostPrice = Decimal::div($hppAmount, $hppFactor, 2, 6, 2);

            // 2. Ekstrak & Hitung Harga Jual Minimum per pcs
            $rawSellAmount = '0.00';
            if (array_key_exists('min_price_amount', $data) && $data['min_price_amount'] !== null && $data['min_price_amount'] !== '') {
                $rawSellAmount = Decimal::normalize((string) $data['min_price_amount'], 2);
            } elseif (array_key_exists('retail_min_price', $data) && $data['retail_min_price'] !== null && $data['retail_min_price'] !== '') {
                $rawSellAmount = Decimal::normalize((string) $data['retail_min_price'], 2);
            } elseif (! empty($data['prices'])) {
                $rawPrice = $data['prices']['ring_retail']['recommended']
                    ?? $data['prices']['ring_retail']['min']
                    ?? $data['prices']['ring_retail']
                    ?? $data['prices']['ring_pos']['recommended']
                    ?? '0.00';
                $rawSellAmount = Decimal::normalize((string) $rawPrice, 2);
            }

            $retailMinPrice = Decimal::div($rawSellAmount, $sellFactor, 2, 6, 2);

            // 3. Validasi Proteksi Anti-Rugi: Harga Jual Minimum tidak boleh di bawah HPP
            if (Decimal::compare($retailMinPrice, '0.00', 2) > 0 && Decimal::compare($retailCostPrice, '0.00', 2) > 0) {
                if (Decimal::compare($retailMinPrice, $retailCostPrice, 2) < 0) {
                    $formattedHpp = number_format((float) $retailCostPrice, 0, ',', '.');
                    $formattedMin = number_format((float) $retailMinPrice, 0, ',', '.');
                    throw ValidationException::withMessages([
                        'min_price_amount' => "Harga jual minimum (Rp {$formattedMin}/pcs) tidak boleh lebih rendah dari modal HPP (Rp {$formattedHpp}/pcs).",
                    ]);
                }
            }

            // 4. Hitung Total Kuantitas Eceran (pcs): Stok Input * Rasio Konversi Satuan Stok
            $initialStockBaseQty = Decimal::mul($stockQty, $stockFactor, 4, 6, 4);

            $attributes = $product->attributes ?? [];
            $quickSetupAttrs = (array) ($attributes['quick_setup'] ?? []);
            $locationsAttrs = (array) ($quickSetupAttrs['locations'] ?? []);

            $locationsAttrs[$workLocation->id] = [
                'hpp_unit_id' => $hppUnitId,
                'hpp_amount' => $hppAmount,
                'stock_unit_id' => $stockUnitId,
                'stock_qty' => $stockQty,
                'sell_unit_id' => $sellUnitId,
                'min_price_amount' => $rawSellAmount,
            ];

            $quickSetupAttrs['hpp_unit_id'] = $hppUnitId;
            $quickSetupAttrs['hpp_amount'] = $hppAmount;
            $quickSetupAttrs['stock_unit_id'] = $stockUnitId;
            $quickSetupAttrs['stock_qty'] = $stockQty;
            $quickSetupAttrs['sell_unit_id'] = $sellUnitId;
            $quickSetupAttrs['min_price_amount'] = $rawSellAmount;
            $quickSetupAttrs['locations'] = $locationsAttrs;

            $attributes['quick_setup'] = $quickSetupAttrs;

            $product->forceFill([
                'base_unit_id' => $baseUnitId,
                'cost_price' => $retailCostPrice,
                'minimum_price' => $retailMinPrice,
                'attributes' => $attributes,
            ])->save();

            // 5. Sinkronisasi tabel product_units
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

            if ($sellUnitId !== $baseUnitId && $sellUnitId !== $hppUnitId && $sellUnitId !== $stockUnitId) {
                $unitsPayload[] = [
                    'unit_id' => $sellUnitId,
                    'conversion_factor' => $sellFactor,
                    'name' => Unit::find($sellUnitId)?->name ?: 'Satuan penjualan',
                    'is_sellable' => true,
                    'is_active' => true,
                ];
            }

            $this->conversion->syncProductUnits($product, $unitsPayload);

            // 6. Sinkronisasi Harga ke Seluruh Channel Penjualan (Retail, POS, B2B)
            $branchId = Branch::query()->where('work_location_id', $workLocation->id)->value('id');

            if (Decimal::compare($retailMinPrice, '0.00', 2) > 0) {
                $primaryExistingPrice = ProductPrice::query()
                    ->where('product_id', $product->id)
                    ->where('work_location_id', $workLocation->id)
                    ->where('channel', 'retail')
                    ->first();
                $oldPrice = $primaryExistingPrice?->recommended_price;

                $firstPriceRecord = null;
                foreach (self::CHANNELS as $channel => $ringLabel) {
                    $priceRecord = ProductPrice::query()->updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'work_location_id' => $workLocation->id,
                            'channel' => $channel,
                            'price_ring' => $ringLabel,
                        ],
                        [
                            'branch_id' => $branchId,
                            'min_price' => $retailMinPrice,
                            'recommended_price' => $retailMinPrice,
                            'max_price' => $retailMinPrice,
                            'minimum_qty' => 1,
                            'priority' => 10,
                            'starts_at' => now()->toDateString(),
                            'ends_at' => null,
                            'status' => ProductPriceStatus::ACTIVE->value,
                            'notes' => $data['notes'] ?? 'Setup harga jual minimum anti-rugi',
                        ]
                    );

                    if ($channel === 'retail') {
                        $firstPriceRecord = $priceRecord;
                    }
                }

                $isPriceChanged = $oldPrice === null
                    || Decimal::compare((string) $oldPrice, (string) $retailMinPrice, 2) !== 0;

                if ($isPriceChanged) {
                    PriceHistory::query()->create([
                        'priceable_type' => ProductPrice::class,
                        'priceable_id' => $firstPriceRecord->id,
                        'product_id' => $product->id,
                        'branch_id' => $branchId,
                        'work_location_id' => $workLocation->id,
                        'channel' => 'retail',
                        'price_ring' => 'Ring Retail',
                        'old_price' => $oldPrice,
                        'new_price' => $retailMinPrice,
                        'hpp_snapshot' => $retailCostPrice,
                        'minimum_price_snapshot' => $retailMinPrice,
                        'changed_by' => $actor->id,
                        'source' => 'quick_setup',
                        'reason' => $data['notes'] ?? 'Setup cepat harga jual minimum',
                        'effective_at' => now()->toDateString(),
                    ]);
                }
            }

            // 7. Pencatatan Persediaan Awal (Stok Awal) - Idempotent
            $stockInitialized = false;
            $stockMessage = 'Parameter HPP dan harga jual minimum berhasil disimpan.';

            $existingOpeningBatch = StockBatch::query()
                ->where('product_id', $product->id)
                ->where('work_location_id', $workLocation->id)
                ->where('source_type', 'opening_stock')
                ->first();

            if (! $existingOpeningBatch && Decimal::compare($stockQty, '0', 4) > 0) {
                $batchNo = 'OPN-'.now()->format('Ymd').'-'.strtoupper(Str::random(5));

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
                    'entry_sale_price_snapshot' => $retailMinPrice,
                    'quantity_on_hand' => $initialStockBaseQty,
                    'quantity_reserved' => '0.0000',
                    'quantity_damaged' => '0.0000',
                ]);

                $this->layers->recordAllocation($layer, [
                    'reference_type' => 'opening_stock',
                    'reference_id' => $layer->id,
                    'event_type' => 'opening_stock',
                    'quantity' => $initialStockBaseQty,
                    'unit_cost' => $retailCostPrice,
                    'sale_price_snapshot' => $retailMinPrice,
                    'actor_user_id' => $actor->id,
                    'metadata' => ['batch_no' => $batchNo, 'source' => 'quick_operational_setup'],
                    'occurred_at' => now(),
                ]);

                $stock = Stock::query()->find($mutation->stock_id);
                if ($stock instanceof Stock) {
                    $stock->forceFill([
                        'cost_value' => Decimal::mul((string) $stock->quantity_on_hand, $retailCostPrice, 2),
                    ])->save();
                }

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

            // 8. Simpan Barcode (opsional & unik)
            if (array_key_exists('barcode', $data)) {
                $barcode = trim((string) ($data['barcode'] ?? ''));
                if ($barcode !== '') {
                    $conflict = ProductBarcode::query()
                        ->where('code', $barcode)
                        ->where('product_id', '!=', $product->id)
                        ->first();

                    if ($conflict) {
                        throw ValidationException::withMessages([
                            'barcode' => "Barcode '{$barcode}' sudah digunakan oleh produk lain ({$conflict->product?->name}).",
                        ]);
                    }

                    ProductBarcode::query()->updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'is_primary' => true,
                        ],
                        [
                            'code' => $barcode,
                            'type' => 'barcode',
                            'is_active' => true,
                        ]
                    );
                } else {
                    ProductBarcode::query()
                        ->where('product_id', $product->id)
                        ->where('is_primary', true)
                        ->delete();
                }
            }

            // 9. Audit log penanggung jawab
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
                    'sell_unit_id' => $sellUnitId,
                    'min_price_amount' => $rawSellAmount,
                    'retail_min_price' => $retailMinPrice,
                    'initial_stock_base_qty' => $initialStockBaseQty,
                    'stock_initialized' => $stockInitialized,
                ],
                reason: "Setup operasional cepat oleh {$actor->name} di {$workLocation->name}: {$stockMessage}",
                request: $request,
                location: $workLocation,
                severity: 'info'
            );

            $hasExistingStock = (bool) ($existingOpeningBatch || $stockInitialized);
            $isComplete = $hasExistingStock
                && Decimal::compare($retailCostPrice, '0.00', 2) > 0
                && Decimal::compare($retailMinPrice, '0.00', 2) > 0
                && Decimal::compare($retailMinPrice, $retailCostPrice, 2) >= 0;

            $marginAmount = Decimal::sub($retailMinPrice, $retailCostPrice, 2);
            $marginPercent = Decimal::compare($retailCostPrice, '0.00', 2) > 0
                ? Decimal::mul(Decimal::div($marginAmount, $retailCostPrice, 2, 2, 4), '100', 4, 0, 1)
                : '0.0';

            return [
                'product_id' => (int) $product->id,
                'sku' => (string) $product->sku,
                'name' => (string) $product->name,
                'retail_cost_price' => $retailCostPrice,
                'retail_min_price' => $retailMinPrice,
                'margin_amount' => $marginAmount,
                'margin_percent' => $marginPercent,
                'initial_stock_base_qty' => $initialStockBaseQty,
                'stock_initialized' => $stockInitialized,
                'is_complete' => $isComplete,
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
                /** @var array{product_id: int, hpp_unit_id: int, hpp_amount: string|numeric, stock_unit_id: int, stock_qty: string|numeric, sell_unit_id?: int|null, min_price_amount?: string|numeric|null, prices?: array<string, mixed>|null, unlimited?: bool|string|int, starts_at?: string|null, ends_at?: string|null, notes?: string|null} $validatedRow */
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
