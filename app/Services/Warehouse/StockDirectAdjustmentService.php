<?php

namespace App\Services\Warehouse;

use App\Exceptions\ServiceException;
use App\Models\Stock;
use App\Models\StockDirectAdjustment;
use App\Models\StockDirectAdjustmentItem;
use App\Models\User;
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

class StockDirectAdjustmentService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly InventoryService $inventory,
        private readonly StockLayerService $layers,
        private readonly LocationProductPriceService $locationPrices,
        private readonly StockAdjustmentAccessService $access,
        private readonly AuditLogService $audit,
    ) {}

    /** @param array{work_location_id:int, notes?:string|null, items:list<array{stock_id:int, physical_qty:string, note:string}>} $data */
    public function create(array $data, User $actor, ?Request $request = null): StockDirectAdjustment
    {
        return DB::transaction(function () use ($data, $actor, $request): StockDirectAdjustment {
            $location = WorkLocation::query()->findOrFail($data['work_location_id']);
            $this->assertHeadAt($actor, $location);
            $this->access->assertActive($location);

            $adjustment = StockDirectAdjustment::query()->create([
                'number' => $this->numbers->next('stock_direct_adjustment', $location),
                'work_location_id' => $location->id,
                'purpose' => 'operational_correction',
                'created_by' => $actor->id,
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $row) {
                $stock = Stock::query()->with('product')->lockForUpdate()->findOrFail($row['stock_id']);
                if ((int) $stock->work_location_id !== (int) $location->id) {
                    throw ServiceException::validation('Produk stok tidak berada pada lokasi koreksi yang dipilih.');
                }
                $this->storeItem($adjustment, $stock, $row);
            }

            $this->audit->record('stock_direct_adjustment.created', 'stock_adjustments', $actor, $adjustment, [], $adjustment->load('items')->toArray(), $adjustment->notes, $request, $location, 'warning');

            return $adjustment->fresh(['items.product', 'workLocation', 'creator']);
        });
    }

    public function submit(StockDirectAdjustment $adjustment, User $actor, ?Request $request = null): StockDirectAdjustment
    {
        return DB::transaction(function () use ($adjustment, $actor, $request): StockDirectAdjustment {
            $adjustment = StockDirectAdjustment::query()->with(['items', 'workLocation'])->lockForUpdate()->findOrFail($adjustment->id);
            $this->assertOperationalCorrection($adjustment);
            if ($adjustment->status !== 'draft' || (int) $adjustment->created_by !== (int) $actor->id) {
                throw ServiceException::validation('Hanya pembuat yang dapat mengajukan dokumen draft.');
            }
            $this->assertHeadAt($actor, $adjustment->workLocation);
            $this->access->assertActive($adjustment->workLocation);
            if ($adjustment->items->isEmpty()) {
                throw ServiceException::validation('Dokumen koreksi harus memiliki minimal satu produk.');
            }
            $before = $adjustment->toArray();
            $adjustment->forceFill(['status' => 'pending_approval', 'submitted_by' => $actor->id, 'submitted_at' => now()])->save();
            $this->audit->record('stock_direct_adjustment.submitted', 'stock_adjustments', $actor, $adjustment, $before, $adjustment->toArray(), $adjustment->notes, $request, $adjustment->workLocation, 'warning');

            return $adjustment;
        });
    }

    /** @param array{work_location_id:int, notes?:string|null, items:list<array{stock_id:int, physical_qty:string, note:string}>} $data */
    public function update(StockDirectAdjustment $adjustment, array $data, User $actor, ?Request $request = null): StockDirectAdjustment
    {
        return DB::transaction(function () use ($adjustment, $data, $actor, $request): StockDirectAdjustment {
            $adjustment = StockDirectAdjustment::query()->with('workLocation')->lockForUpdate()->findOrFail($adjustment->id);
            $this->assertOperationalCorrection($adjustment);
            if ($adjustment->status !== 'draft' || (int) $adjustment->created_by !== (int) $actor->id || (int) $data['work_location_id'] !== (int) $adjustment->work_location_id) {
                throw ServiceException::validation('Draft koreksi tidak dapat diubah oleh akun Anda atau dipindahkan ke lokasi lain.');
            }
            $this->assertHeadAt($actor, $adjustment->workLocation);
            $this->access->assertActive($adjustment->workLocation);
            $before = $adjustment->load('items')->toArray();
            $adjustment->items()->delete();
            foreach ($data['items'] as $row) {
                $stock = Stock::query()->with('product')->lockForUpdate()->findOrFail($row['stock_id']);
                if ((int) $stock->work_location_id !== (int) $adjustment->work_location_id) {
                    throw ServiceException::validation('Produk stok tidak berada pada lokasi dokumen.');
                }
                $this->storeItem($adjustment, $stock, $row);
            }
            $adjustment->forceFill(['notes' => $data['notes'] ?? null, 'decision_note' => null])->save();
            $this->audit->record('stock_direct_adjustment.updated', 'stock_adjustments', $actor, $adjustment, $before, $adjustment->load('items')->toArray(), $adjustment->notes, $request, $adjustment->workLocation, 'warning');

            return $adjustment;
        });
    }

    public function approve(StockDirectAdjustment $adjustment, User $actor, string $notes, ?Request $request = null): StockDirectAdjustment
    {
        return DB::transaction(function () use ($adjustment, $actor, $notes, $request): StockDirectAdjustment {
            $adjustment = StockDirectAdjustment::query()->with(['items.product', 'items.warehouseLocation', 'workLocation'])->lockForUpdate()->findOrFail($adjustment->id);
            $this->assertOperationalCorrection($adjustment);
            $this->assertCanDecide($adjustment, $actor);
            $this->access->assertActive($adjustment->workLocation);

            $stale = false;
            foreach ($adjustment->items as $item) {
                $stock = Stock::query()->lockForUpdate()->findOrFail($item->stock_id);
                if (Decimal::compare((string) $stock->quantity_on_hand, (string) $item->system_qty_snapshot) !== 0
                    || Decimal::compare((string) $stock->quantity_reserved, (string) $item->reserved_qty_snapshot) !== 0
                    || Decimal::compare((string) $stock->quantity_damaged, (string) $item->damaged_qty_snapshot) !== 0) {
                    $this->refreshItemSnapshot($item, $stock);
                    $stale = true;
                }
            }
            if ($stale) {
                $adjustment->forceFill(['status' => 'draft', 'submitted_by' => null, 'submitted_at' => null, 'decision_note' => 'Stok berubah setelah pengajuan. Snapshot diperbarui dan dokumen harus diajukan ulang.'])->save();
                $this->audit->record('stock_direct_adjustment.stale', 'stock_adjustments', $actor, $adjustment, [], $adjustment->load('items')->toArray(), $adjustment->decision_note, $request, $adjustment->workLocation, 'warning');

                return $adjustment->setAttribute('was_stale', true);
            }

            foreach ($adjustment->items as $item) {
                $mutation = $this->inventory->adjust(
                    $item->product,
                    $adjustment->workLocation,
                    $item->warehouseLocation,
                    (string) $item->physical_qty,
                    $actor,
                    ['type' => 'stock_direct_adjustment', 'id' => $adjustment->id, 'no' => $adjustment->number],
                    'Koreksi stok langsung: '.$item->note,
                    "stock-direct-adjustment-{$adjustment->id}-item-{$item->id}",
                    ['stock_direct_adjustment_item_id' => $item->id, 'creator_user_id' => $adjustment->created_by, 'approver_user_id' => $actor->id],
                );
                $item->forceFill(['posted_qty_before' => $mutation->quantity_on_hand_before, 'posted_qty_after' => $mutation->quantity_on_hand_after])->save();
                $difference = Decimal::sub((string) $item->physical_qty, (string) $item->system_qty_snapshot, 4);
                if (Decimal::compare($difference, '0', 4) > 0) {
                    $layer = $this->layers->createLayer([
                        'source_batch_id' => null, 'product_id' => $item->product_id, 'stock_id' => $mutation->stock_id,
                        'work_location_id' => $adjustment->work_location_id, 'warehouse_location_id' => $item->warehouse_location_id,
                        'source_type' => 'stock_direct_adjustment', 'source_id' => $adjustment->id, 'source_item_id' => $item->id,
                        'supplier_id' => null, 'goods_receipt_id' => null, 'goods_receipt_item_id' => null,
                        'unit_id' => $item->product->base_unit_id, 'conversion_factor_snapshot' => '1.000000',
                        'batch_no' => "ADJUSTMENT-{$adjustment->id}-{$item->id}", 'received_at' => now()->toDateString(), 'expires_at' => null,
                        'cost_price' => (string) $item->unit_cost_snapshot,
                        'entry_sale_price_snapshot' => $item->sale_price_snapshot === null ? null : (string) $item->sale_price_snapshot,
                        'quantity_on_hand' => $difference, 'quantity_reserved' => '0.0000', 'quantity_damaged' => '0.0000',
                    ]);
                    $this->layers->recordAllocation($layer, [
                        'reference_type' => 'stock_direct_adjustment_item', 'reference_id' => $item->id,
                        'event_type' => 'adjustment_positive', 'quantity' => $difference,
                        'sale_price_snapshot' => $item->sale_price_snapshot === null ? null : (string) $item->sale_price_snapshot,
                        'actor_user_id' => $actor->id, 'metadata' => ['adjustment_id' => $adjustment->id], 'occurred_at' => now(),
                    ]);
                    if ($item->sale_price_snapshot !== null) {
                        $this->locationPrices->setActivePrice($item->product, $adjustment->workLocation, (string) $item->sale_price_snapshot, $actor, 'stock_adjustment', $adjustment->number);
                    }
                } elseif (Decimal::compare($difference, '0', 4) < 0) {
                    $rawIds = $item->getAttribute('selected_stock_batch_ids');
                    $selectedIds = is_array($rawIds) ? array_values(array_map('intval', $rawIds)) : null;
                    foreach ($this->layers->resolveLayers($item->product_id, $adjustment->work_location_id, ltrim($difference, '-'), $item->warehouse_location_id, $selectedIds) as $allocation) {
                        $this->layers->deductLayer($allocation['batch'], $allocation['quantity']);
                        $this->layers->recordAllocation($allocation['batch'], [
                            'reference_type' => 'stock_direct_adjustment_item', 'reference_id' => $item->id,
                            'event_type' => 'adjustment_negative', 'quantity' => $allocation['quantity'], 'sale_price_snapshot' => null,
                            'actor_user_id' => $actor->id, 'metadata' => ['adjustment_id' => $adjustment->id], 'occurred_at' => now(),
                        ]);
                    }
                }
            }

            $adjustment->approvals()->create(['approver_user_id' => $actor->id, 'decision' => 'approved', 'notes' => $notes, 'decided_at' => now()]);
            $adjustment->forceFill(['status' => 'completed', 'approved_by' => $actor->id, 'approved_at' => now(), 'completed_at' => now(), 'decision_note' => $notes])->save();
            $this->audit->record('stock_direct_adjustment.completed', 'stock_adjustments', $actor, $adjustment, [], $adjustment->load('items')->toArray(), $notes, $request, $adjustment->workLocation, 'critical');

            return $adjustment;
        });
    }

    public function reject(StockDirectAdjustment $adjustment, User $actor, string $notes, ?Request $request = null): StockDirectAdjustment
    {
        return DB::transaction(function () use ($adjustment, $actor, $notes, $request): StockDirectAdjustment {
            $adjustment = StockDirectAdjustment::query()->with('workLocation')->lockForUpdate()->findOrFail($adjustment->id);
            $this->assertOperationalCorrection($adjustment);
            $this->assertCanDecide($adjustment, $actor);
            $this->access->assertActive($adjustment->workLocation);
            $adjustment->approvals()->create(['approver_user_id' => $actor->id, 'decision' => 'rejected', 'notes' => $notes, 'decided_at' => now()]);
            $adjustment->forceFill(['status' => 'rejected', 'rejected_by' => $actor->id, 'rejected_at' => now(), 'decision_note' => $notes])->save();
            $this->audit->record('stock_direct_adjustment.rejected', 'stock_adjustments', $actor, $adjustment, [], $adjustment->toArray(), $notes, $request, $adjustment->workLocation, 'warning');

            return $adjustment;
        });
    }

    public function cancel(StockDirectAdjustment $adjustment, User $actor, string $notes, ?Request $request = null): StockDirectAdjustment
    {
        return DB::transaction(function () use ($adjustment, $actor, $notes, $request): StockDirectAdjustment {
            $adjustment = StockDirectAdjustment::query()->with('workLocation')->lockForUpdate()->findOrFail($adjustment->id);
            $this->assertOperationalCorrection($adjustment);
            if (! in_array($adjustment->status, ['draft', 'pending_approval'], true) || ((int) $adjustment->created_by !== (int) $actor->id && ! $actor->hasRole('super_admin'))) {
                throw ServiceException::validation('Dokumen ini tidak dapat dibatalkan oleh akun Anda.');
            }
            $adjustment->forceFill(['status' => 'cancelled', 'cancelled_by' => $actor->id, 'cancelled_at' => now(), 'decision_note' => $notes])->save();
            $this->audit->record('stock_direct_adjustment.cancelled', 'stock_adjustments', $actor, $adjustment, [], $adjustment->toArray(), $notes, $request, $adjustment->workLocation, 'warning');

            return $adjustment;
        });
    }

    /** @param array{physical_qty:string, note:string, unit_cost_snapshot?:string, sale_price_snapshot?:string, selected_stock_batch_ids?:list<int>} $row */
    private function storeItem(StockDirectAdjustment $adjustment, Stock $stock, array $row): void
    {
        $physicalQty = (string) $row['physical_qty'];
        $note = $row['note'];
        if (Decimal::compare($physicalQty, '0') < 0 || Decimal::compare(Decimal::sub(Decimal::sub($physicalQty, (string) $stock->quantity_reserved), (string) $stock->quantity_damaged), '0') < 0) {
            throw ServiceException::validation('Stok fisik tidak boleh negatif atau lebih kecil dari stok reserved dan rusak.');
        }
        $difference = Decimal::sub($physicalQty, (string) $stock->quantity_on_hand);
        if (Decimal::isZero($difference)) {
            throw ServiceException::validation('Stok fisik final harus berbeda dari stok sistem.');
        }
        $unitCost = (string) ($row['unit_cost_snapshot'] ?? $stock->product->cost_price);
        $salePrice = (string) ($row['sale_price_snapshot']
            ?? (Decimal::compare((string) $stock->product->minimum_price, '0', 2) > 0 ? $stock->product->minimum_price : $unitCost));
        $adjustment->items()->create([
            'product_id' => $stock->product_id, 'stock_id' => $stock->id, 'warehouse_location_id' => $stock->warehouse_location_id,
            'product_sku_snapshot' => $stock->product->sku, 'product_name_snapshot' => $stock->product->name,
            'system_qty_snapshot' => $stock->quantity_on_hand, 'physical_qty' => $physicalQty, 'difference_qty' => $difference,
            'reserved_qty_snapshot' => $stock->quantity_reserved, 'damaged_qty_snapshot' => $stock->quantity_damaged,
            'unit_cost_snapshot' => $unitCost, 'sale_price_snapshot' => $salePrice,
            'selected_stock_batch_ids' => array_map('intval', $row['selected_stock_batch_ids'] ?? []),
            'difference_value' => Decimal::mul(ltrim($difference, '-'), $unitCost), 'note' => $note,
        ]);
    }

    private function refreshItemSnapshot(StockDirectAdjustmentItem $item, Stock $stock): void
    {
        $difference = Decimal::sub((string) $item->physical_qty, (string) $stock->quantity_on_hand);
        $item->forceFill(['system_qty_snapshot' => $stock->quantity_on_hand, 'reserved_qty_snapshot' => $stock->quantity_reserved, 'damaged_qty_snapshot' => $stock->quantity_damaged, 'difference_qty' => $difference, 'difference_value' => Decimal::mul(ltrim($difference, '-'), (string) $item->unit_cost_snapshot)])->save();
    }

    private function assertHeadAt(User $actor, WorkLocation $location): void
    {
        $role = ApprovalAuthority::roleForLocation($location);
        if (! $actor->hasRole($role) || ! $actor->canAccessWorkLocation((int) $location->id)) {
            throw ServiceException::validation('Koreksi hanya dapat dibuat kepala bagian pada lokasi penugasannya.');
        }
    }

    private function assertCanDecide(StockDirectAdjustment $adjustment, User $actor): void
    {
        if ($adjustment->status !== 'pending_approval') {
            throw ServiceException::validation('Dokumen belum menunggu persetujuan.');
        }
        if ((int) $adjustment->created_by === (int) $actor->id) {
            throw ServiceException::validation('Pembuat dokumen tidak boleh menyetujui atau menolak dokumennya sendiri.');
        }

        if (! $actor->hasRole('super_admin')) {
            $this->assertHeadAt($actor, $adjustment->workLocation);
        }
    }

    private function assertOperationalCorrection(StockDirectAdjustment $adjustment): void
    {
        if ($adjustment->isOpeningBalance()) {
            throw ServiceException::validation('Dokumen stok awal harus diproses melalui menu Pendataan Stok Lokasi.');
        }
    }
}
