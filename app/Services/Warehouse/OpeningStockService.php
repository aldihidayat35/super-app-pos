<?php

namespace App\Services\Warehouse;

use App\Enums\ProductStatus;
use App\Exceptions\ServiceException;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockDirectAdjustment;
use App\Models\StockDirectAdjustmentItem;
use App\Models\User;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use App\Services\Control\AuditLogService;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\StockLayerService;
use App\Services\Organization\DocumentNumberService;
use App\Services\Pricing\LocationProductPriceService;
use App\Support\ApprovalAuthority;
use App\Support\Decimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class OpeningStockService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly InventoryService $inventory,
        private readonly StockLayerService $layers,
        private readonly LocationProductPriceService $locationPrices,
        private readonly StockAdjustmentAccessService $access,
        private readonly AuditLogService $audit,
    ) {}

    /** @param array{work_location_id:int, notes?:string|null, items:list<array{product_id:int, warehouse_location_id:int, physical_qty:string, unit_cost_snapshot?:string, sale_price_snapshot?:string, selected_stock_batch_ids?:list<int>, note?:string|null}>} $data */
    public function create(array $data, User $actor, ?Request $request = null): StockDirectAdjustment
    {
        return DB::transaction(function () use ($data, $actor, $request): StockDirectAdjustment {
            $location = WorkLocation::query()->findOrFail($data['work_location_id']);
            $this->assertCanCreate($actor, $location);
            $this->access->assertActive($location);

            $document = StockDirectAdjustment::query()->create([
                'number' => $this->numbers->next('opening_balance', $location),
                'work_location_id' => $location->id,
                'purpose' => 'opening_balance',
                'created_by' => $actor->id,
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
            ]);

            $this->replaceItems($document, $data['items'], $location);
            $this->audit->record('opening_stock.created', 'opening_stocks', $actor, $document, [], $document->load('items')->toArray(), $document->notes, $request, $location, 'warning');

            return $document->fresh(['items.product', 'workLocation', 'creator']);
        });
    }

    /** @param array{work_location_id:int, notes?:string|null, items:list<array{product_id:int, warehouse_location_id:int, physical_qty:string, unit_cost_snapshot?:string, sale_price_snapshot?:string, selected_stock_batch_ids?:list<int>, note?:string|null}>} $data */
    public function update(StockDirectAdjustment $document, array $data, User $actor, ?Request $request = null): StockDirectAdjustment
    {
        return DB::transaction(function () use ($document, $data, $actor, $request): StockDirectAdjustment {
            $document = $this->lockOpeningDocument($document, ['workLocation', 'items']);
            if ($document->status !== 'draft' || (int) $document->created_by !== (int) $actor->id || (int) $data['work_location_id'] !== (int) $document->work_location_id) {
                throw ServiceException::validation('Draft pendataan ini tidak dapat diubah oleh akun Anda.');
            }
            $this->assertCanCreate($actor, $document->workLocation);
            $this->access->assertActive($document->workLocation);
            $before = $document->toArray();
            $document->items()->delete();
            $this->replaceItems($document, $data['items'], $document->workLocation);
            $document->forceFill(['notes' => $data['notes'] ?? null, 'decision_note' => null])->save();
            $this->audit->record('opening_stock.updated', 'opening_stocks', $actor, $document, $before, $document->load('items')->toArray(), $document->notes, $request, $document->workLocation, 'warning');

            return $document;
        });
    }

    public function submit(StockDirectAdjustment $document, User $actor, ?Request $request = null): StockDirectAdjustment
    {
        return DB::transaction(function () use ($document, $actor, $request): StockDirectAdjustment {
            $document = $this->lockOpeningDocument($document, ['workLocation', 'items']);
            if ($document->status !== 'draft' || (int) $document->created_by !== (int) $actor->id) {
                throw ServiceException::validation('Hanya pembuat yang dapat mengajukan draft pendataan.');
            }
            $this->assertCanCreate($actor, $document->workLocation);
            $this->access->assertActive($document->workLocation);
            if ($document->items->isEmpty()) {
                throw ServiceException::validation('Pilih minimal satu produk yang sudah dihitung.');
            }
            $document->forceFill(['status' => 'pending_approval', 'submitted_by' => $actor->id, 'submitted_at' => now()])->save();
            $this->audit->record('opening_stock.submitted', 'opening_stocks', $actor, $document, [], $document->toArray(), $document->notes, $request, $document->workLocation, 'warning');

            return $document;
        });
    }

    public function approve(StockDirectAdjustment $document, User $actor, string $notes, ?Request $request = null): StockDirectAdjustment
    {
        return DB::transaction(function () use ($document, $actor, $notes, $request): StockDirectAdjustment {
            $document = $this->lockOpeningDocument($document, ['items.product', 'items.warehouseLocation', 'workLocation']);
            $this->assertCanApprove($document, $actor);
            $this->access->assertActive($document->workLocation);

            $stale = false;
            foreach ($document->items as $item) {
                $stock = $this->stockFor($item->product_id, $document->work_location_id, $item->warehouse_location_id, true);
                $current = $stock ? $stock->quantity_on_hand : '0';
                $reserved = $stock ? $stock->quantity_reserved : '0';
                $damaged = $stock ? $stock->quantity_damaged : '0';
                if (Decimal::compare((string) $current, (string) $item->system_qty_snapshot) !== 0
                    || Decimal::compare((string) $reserved, (string) $item->reserved_qty_snapshot) !== 0
                    || Decimal::compare((string) $damaged, (string) $item->damaged_qty_snapshot) !== 0) {
                    $this->refreshSnapshot($item, $stock);
                    $stale = true;
                }
            }
            if ($stale) {
                $document->forceFill(['status' => 'draft', 'submitted_by' => null, 'submitted_at' => null, 'decision_note' => 'Stok berubah setelah pengajuan. Periksa kembali jumlah fisik dan ajukan ulang.'])->save();
                $this->audit->record('opening_stock.stale', 'opening_stocks', $actor, $document, [], $document->load('items')->toArray(), $document->decision_note, $request, $document->workLocation, 'warning');

                return $document->setAttribute('was_stale', true);
            }

            foreach ($document->items as $item) {
                if (Decimal::compare((string) $item->system_qty_snapshot, (string) $item->physical_qty) === 0) {
                    $item->forceFill(['posted_qty_before' => $item->system_qty_snapshot, 'posted_qty_after' => $item->physical_qty])->save();

                    continue;
                }
                $mutation = $this->inventory->adjust(
                    $item->product,
                    $document->workLocation,
                    $item->warehouseLocation,
                    (string) $item->physical_qty,
                    $actor,
                    ['type' => 'opening_balance_manual', 'id' => $document->id, 'no' => $document->number],
                    'Pendataan stok lokasi: '.($item->note ?: 'Hasil hitung fisik'),
                    "opening-balance-{$document->id}-item-{$item->id}",
                    ['opening_stock_item_id' => $item->id, 'creator_user_id' => $document->created_by, 'approver_user_id' => $actor->id, 'unit_cost_snapshot' => $item->unit_cost_snapshot],
                );
                $item->forceFill(['stock_id' => $mutation->stock_id, 'posted_qty_before' => $mutation->quantity_on_hand_before, 'posted_qty_after' => $mutation->quantity_on_hand_after])->save();
                $difference = Decimal::sub((string) $item->physical_qty, (string) $item->system_qty_snapshot, 4);
                if (Decimal::compare($difference, '0', 4) > 0) {
                    $layer = $this->layers->createLayer([
                        'source_batch_id' => null,
                        'product_id' => $item->product_id,
                        'stock_id' => $mutation->stock_id,
                        'work_location_id' => $document->work_location_id,
                        'warehouse_location_id' => $item->warehouse_location_id,
                        'source_type' => 'opening_stock',
                        'source_id' => $document->id,
                        'source_item_id' => $item->id,
                        'supplier_id' => null,
                        'goods_receipt_id' => null,
                        'goods_receipt_item_id' => null,
                        'unit_id' => $item->product->base_unit_id,
                        'conversion_factor_snapshot' => '1.000000',
                        'batch_no' => "OPENING-{$document->id}-{$item->id}",
                        'received_at' => now()->toDateString(),
                        'expires_at' => null,
                        'cost_price' => (string) $item->unit_cost_snapshot,
                        'entry_sale_price_snapshot' => $item->sale_price_snapshot === null ? null : (string) $item->sale_price_snapshot,
                        'quantity_on_hand' => $difference,
                        'quantity_reserved' => '0.0000',
                        'quantity_damaged' => '0.0000',
                    ]);
                    $this->layers->recordAllocation($layer, [
                        'reference_type' => 'stock_direct_adjustment_item', 'reference_id' => $item->id,
                        'event_type' => 'opening_stock', 'quantity' => $difference,
                        'sale_price_snapshot' => $item->sale_price_snapshot === null ? null : (string) $item->sale_price_snapshot,
                        'actor_user_id' => $actor->id, 'metadata' => ['document_id' => $document->id], 'occurred_at' => now(),
                    ]);
                    if ($item->sale_price_snapshot !== null) {
                        $this->locationPrices->setActivePrice($item->product, $document->workLocation, (string) $item->sale_price_snapshot, $actor, 'opening_stock', $document->number);
                    }
                } elseif (Decimal::compare($difference, '0', 4) < 0) {
                    $selectedIds = is_array($item->getAttribute('selected_stock_batch_ids'))
                        ? array_map('intval', $item->getAttribute('selected_stock_batch_ids')) : null;
                    foreach ($this->layers->resolveLayers($item->product_id, $document->work_location_id, ltrim($difference, '-'), $item->warehouse_location_id, $selectedIds) as $allocation) {
                        $this->layers->deductLayer($allocation['batch'], $allocation['quantity']);
                        $this->layers->recordAllocation($allocation['batch'], [
                            'reference_type' => 'stock_direct_adjustment_item', 'reference_id' => $item->id,
                            'event_type' => 'adjustment_negative', 'quantity' => $allocation['quantity'],
                            'sale_price_snapshot' => null, 'actor_user_id' => $actor->id,
                            'metadata' => ['document_id' => $document->id], 'occurred_at' => now(),
                        ]);
                    }
                }
            }

            $document->approvals()->create(['approver_user_id' => $actor->id, 'decision' => 'approved', 'notes' => $notes, 'decided_at' => now()]);
            $document->forceFill(['status' => 'completed', 'approved_by' => $actor->id, 'approved_at' => now(), 'completed_at' => now(), 'decision_note' => $notes])->save();
            $event = (int) $document->created_by === (int) $actor->id ? 'opening_stock.self_posted_by_head' : 'opening_stock.completed';
            $this->audit->record($event, 'opening_stocks', $actor, $document, [], $document->load('items')->toArray(), $notes, $request, $document->workLocation, 'critical');

            return $document;
        });
    }

    public function reject(StockDirectAdjustment $document, User $actor, string $notes, ?Request $request = null): StockDirectAdjustment
    {
        return DB::transaction(function () use ($document, $actor, $notes, $request): StockDirectAdjustment {
            $document = $this->lockOpeningDocument($document, ['workLocation']);
            $this->assertCanApprove($document, $actor, allowSelf: false);
            $this->access->assertActive($document->workLocation);
            $document->approvals()->create(['approver_user_id' => $actor->id, 'decision' => 'rejected', 'notes' => $notes, 'decided_at' => now()]);
            $document->forceFill(['status' => 'rejected', 'rejected_by' => $actor->id, 'rejected_at' => now(), 'decision_note' => $notes])->save();
            $this->audit->record('opening_stock.rejected', 'opening_stocks', $actor, $document, [], $document->toArray(), $notes, $request, $document->workLocation, 'warning');

            return $document;
        });
    }

    public function cancel(StockDirectAdjustment $document, User $actor, string $notes, ?Request $request = null): StockDirectAdjustment
    {
        return DB::transaction(function () use ($document, $actor, $notes, $request): StockDirectAdjustment {
            $document = $this->lockOpeningDocument($document, ['workLocation']);
            if (! in_array($document->status, ['draft', 'pending_approval'], true)
                || ((int) $document->created_by !== (int) $actor->id && ! $actor->hasRole('super_admin'))) {
                throw ServiceException::validation('Dokumen pendataan ini tidak dapat dibatalkan oleh akun Anda.');
            }
            $document->forceFill(['status' => 'cancelled', 'cancelled_by' => $actor->id, 'cancelled_at' => now(), 'decision_note' => $notes])->save();
            $this->audit->record('opening_stock.cancelled', 'opening_stocks', $actor, $document, [], $document->toArray(), $notes, $request, $document->workLocation, 'warning');

            return $document;
        });
    }

    /** @param list<array{product_id:int, warehouse_location_id:int, physical_qty:string, unit_cost_snapshot?:string, sale_price_snapshot?:string, selected_stock_batch_ids?:list<int>, note?:string|null}> $items */
    private function replaceItems(StockDirectAdjustment $document, array $items, WorkLocation $location): void
    {
        foreach ($items as $row) {
            $product = Product::query()->where('status', ProductStatus::ACTIVE->value)->findOrFail($row['product_id']);
            $bin = WarehouseLocation::query()->where('is_active', true)->findOrFail($row['warehouse_location_id']);
            if (! $this->binBelongsToLocation($bin, $location)) {
                throw ServiceException::validation('Rak atau lokasi penyimpanan tidak termasuk dalam lokasi kerja yang dipilih.');
            }
            $stock = $this->stockFor($product->id, $location->id, $bin->id, true);
            $system = (string) ($stock ? $stock->quantity_on_hand : '0');
            $reserved = (string) ($stock ? $stock->quantity_reserved : '0');
            $damaged = (string) ($stock ? $stock->quantity_damaged : '0');
            $physical = Decimal::normalize((string) $row['physical_qty']);
            if (Decimal::compare(Decimal::sub(Decimal::sub($physical, $reserved), $damaged), '0') < 0) {
                throw ServiceException::validation('Jumlah fisik tidak boleh lebih kecil dari stok reserved dan rusak.');
            }
            $difference = Decimal::sub($physical, $system);
            $unitCost = (string) ($row['unit_cost_snapshot'] ?? $product->cost_price);
            $salePrice = (string) ($row['sale_price_snapshot']
                ?? (Decimal::compare((string) $product->minimum_price, '0', 2) > 0 ? $product->minimum_price : $unitCost));
            $document->items()->create([
                'product_id' => $product->id,
                'stock_id' => $stock?->id,
                'warehouse_location_id' => $bin->id,
                'product_sku_snapshot' => $product->sku,
                'product_name_snapshot' => $product->name,
                'system_qty_snapshot' => $system,
                'physical_qty' => $physical,
                'difference_qty' => $difference,
                'reserved_qty_snapshot' => $reserved,
                'damaged_qty_snapshot' => $damaged,
                'unit_cost_snapshot' => $unitCost,
                'sale_price_snapshot' => $salePrice,
                'selected_stock_batch_ids' => array_map('intval', $row['selected_stock_batch_ids'] ?? []),
                'difference_value' => Decimal::mul(ltrim($difference, '-'), $unitCost),
                'note' => filled($row['note'] ?? null) ? $row['note'] : 'Hasil hitung fisik stok lokasi',
            ]);
        }
    }

    private function assertCanCreate(User $actor, WorkLocation $location): void
    {
        if (! $actor->can('opening_stocks.create') || ! $actor->canAccessWorkLocation((int) $location->id)
            || ! $actor->hasAnyRole(['staff_gudang', 'staf_toko', 'kepala_gudang', 'kepala_toko', 'super_admin'])) {
            throw ServiceException::validation('Anda tidak berwenang mengisi stok pada lokasi tersebut.');
        }
        $roles = $location->type === 'branch' ? ['staf_toko', 'kepala_toko', 'super_admin'] : ['staff_gudang', 'kepala_gudang', 'super_admin'];
        if (! $actor->hasAnyRole($roles)) {
            throw ServiceException::validation('Peran akun tidak sesuai dengan jenis lokasi yang dipilih.');
        }
    }

    private function assertCanApprove(StockDirectAdjustment $document, User $actor, bool $allowSelf = true): void
    {
        if ($document->status !== 'pending_approval' || ! $actor->can('opening_stocks.approve')) {
            throw ServiceException::validation('Dokumen belum siap atau Anda tidak berwenang mempostingnya.');
        }
        $isHead = ApprovalAuthority::canApproveAt($actor, (int) $document->work_location_id, ApprovalAuthority::roleForLocation($document->workLocation));
        if (! $isHead) {
            throw ServiceException::validation('Pendataan hanya dapat diposting kepala lokasi atau Super Admin.');
        }
        if (! $allowSelf && (int) $document->created_by === (int) $actor->id) {
            throw ServiceException::validation('Pembuat tidak dapat menolak dokumennya sendiri.');
        }
        if ((int) $document->created_by === (int) $actor->id && ! $actor->hasAnyRole(['kepala_gudang', 'kepala_toko', 'super_admin'])) {
            throw ServiceException::validation('Draft staf harus diperiksa dan diposting oleh kepala lokasi.');
        }
    }

    /** @param list<string> $relations */
    private function lockOpeningDocument(StockDirectAdjustment $document, array $relations): StockDirectAdjustment
    {
        $document = StockDirectAdjustment::query()->with($relations)->lockForUpdate()->findOrFail($document->id);
        if (! $document->isOpeningBalance()) {
            throw ServiceException::validation('Dokumen bukan pendataan stok lokasi.');
        }

        return $document;
    }

    private function stockFor(int $productId, int $workLocationId, int $binId, bool $lock = false): ?Stock
    {
        $query = Stock::query()->where('product_id', $productId)->where('work_location_id', $workLocationId)->where('warehouse_location_id', $binId);

        return ($lock ? $query->lockForUpdate() : $query)->first();
    }

    private function refreshSnapshot(StockDirectAdjustmentItem $item, ?Stock $stock): void
    {
        $system = (string) ($stock ? $stock->quantity_on_hand : '0');
        $difference = Decimal::sub((string) $item->physical_qty, $system);
        $item->forceFill([
            'stock_id' => $stock?->id,
            'system_qty_snapshot' => $system,
            'reserved_qty_snapshot' => $stock ? $stock->quantity_reserved : '0',
            'damaged_qty_snapshot' => $stock ? $stock->quantity_damaged : '0',
            'difference_qty' => $difference,
            'difference_value' => Decimal::mul(ltrim($difference, '-'), (string) $item->unit_cost_snapshot),
        ])->save();
    }

    private function binBelongsToLocation(WarehouseLocation $bin, WorkLocation $location): bool
    {
        $bin->loadMissing(['warehouse', 'branch']);

        return (int) $bin->workLocationId() === (int) $location->id;
    }
}
