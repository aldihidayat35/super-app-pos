<?php

namespace App\Services\Warehouse;

use App\Enums\GoodsReceiptStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\ReceiptQcStatus;
use App\Exceptions\ServiceException;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Product;
use App\Models\ProductCostHistory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReceiptQcResult;
use App\Models\Stock;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierScore;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\StockLayerService;
use App\Services\Organization\DocumentNumberService;
use App\Services\Pricing\LocationProductPriceService;
use App\Services\Purchasing\PurchaseOrderService;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

class GoodsReceiptService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly InventoryService $inventory,
        private readonly StockLayerService $layers,
        private readonly LocationProductPriceService $locationPrices,
        private readonly PurchaseOrderService $purchaseOrders,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDraft(array $data, User $actor): GoodsReceipt
    {
        return DB::transaction(function () use ($data, $actor): GoodsReceipt {
            $purchaseOrder = null;
            if (! empty($data['purchase_order_id'])) {
                $purchaseOrder = PurchaseOrder::query()->with(['warehouse.workLocation', 'destinationWorkLocation', 'supplier', 'items.product', 'items.unit'])->lockForUpdate()->findOrFail($data['purchase_order_id']);

                if (! in_array($purchaseOrder->status, [PurchaseOrderStatus::APPROVED, PurchaseOrderStatus::SENT_TO_SUPPLIER, PurchaseOrderStatus::PARTIALLY_RECEIVED], true)) {
                    throw ServiceException::validation('PO belum siap diterima.');
                }

                $destination = $purchaseOrder->destinationWorkLocation ?? $purchaseOrder->warehouse?->workLocation;
                $supplierId = $purchaseOrder->supplier_id;
                $warehouseId = $purchaseOrder->warehouse_id;
            } else {
                $destinationId = (int) ($data['destination_work_location_id'] ?? 0);
                $destination = WorkLocation::query()->findOrFail($destinationId);

                if (! in_array($destination->id, $actor->permittedWorkLocationIds(), true) && ! $actor->hasRole('super_admin')) {
                    throw ServiceException::validation('Anda tidak memiliki izin di lokasi kerja penerima ini.');
                }

                $supplier = Supplier::query()->findOrFail($data['supplier_id']);
                $supplierId = $supplier->id;
                $warehouse = Warehouse::query()->where('work_location_id', $destination->id)->first();
                $warehouseId = $warehouse?->id;
            }

            if (! $destination) {
                throw ServiceException::validation('Lokasi penerima tidak ditemukan.');
            }

            $receipt = GoodsReceipt::query()->create([
                'number' => $this->numbers->next('receipt', $destination),
                'purchase_order_id' => $purchaseOrder?->id,
                'warehouse_id' => $warehouseId,
                'destination_work_location_id' => $destination->id,
                'supplier_id' => $supplierId,
                'received_at' => $data['received_at'],
                'delivery_note_number' => $data['delivery_note_number'] ?? null,
                'received_by' => $actor->id,
                'status' => GoodsReceiptStatus::DRAFT,
                'actual_freight_cost' => Decimal::normalize($data['actual_freight_cost'] ?? 0, 2),
                'actual_additional_cost' => Decimal::normalize($data['actual_additional_cost'] ?? 0, 2),
                'notes' => $data['notes'] ?? null,
                'proof_path' => $data['proof_path'] ?? null,
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            $this->replaceItems($receipt, $data['items'] ?? [], $purchaseOrder);

            return $receipt->fresh(['items', 'purchaseOrder', 'supplier', 'warehouse', 'destinationWorkLocation']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(GoodsReceipt $receipt, array $data, User $actor): GoodsReceipt
    {
        return DB::transaction(function () use ($receipt, $data, $actor): GoodsReceipt {
            $receipt = GoodsReceipt::query()->with('purchaseOrder.items')->lockForUpdate()->findOrFail($receipt->id);

            if (! $receipt->status->isEditable()) {
                throw ServiceException::validation('Receipt yang sudah posted tidak boleh diedit.');
            }

            $supplierId = $receipt->purchase_order_id ? $receipt->supplier_id : (int) ($data['supplier_id'] ?? $receipt->supplier_id);
            $destinationId = $receipt->purchase_order_id ? $receipt->destination_work_location_id : (int) ($data['destination_work_location_id'] ?? $receipt->destination_work_location_id);
            $warehouseId = $receipt->warehouse_id;

            if ($destinationId !== (int) $receipt->destination_work_location_id && ! $receipt->purchase_order_id) {
                $destination = WorkLocation::query()->findOrFail($destinationId);
                if (! in_array($destination->id, $actor->permittedWorkLocationIds(), true) && ! $actor->hasRole('super_admin')) {
                    throw ServiceException::validation('Anda tidak memiliki izin di lokasi kerja penerima ini.');
                }
                $warehouse = Warehouse::query()->where('work_location_id', $destination->id)->first();
                $warehouseId = $warehouse?->id;
            }

            $receipt->fill([
                'supplier_id' => $supplierId,
                'destination_work_location_id' => $destinationId,
                'warehouse_id' => $warehouseId,
                'received_at' => $data['received_at'],
                'delivery_note_number' => $data['delivery_note_number'] ?? null,
                'actual_freight_cost' => Decimal::normalize($data['actual_freight_cost'] ?? 0, 2),
                'actual_additional_cost' => Decimal::normalize($data['actual_additional_cost'] ?? 0, 2),
                'notes' => $data['notes'] ?? null,
                'proof_path' => $data['proof_path'] ?? $receipt->proof_path,
            ])->save();

            $this->replaceItems($receipt, $data['items'] ?? [], $receipt->purchaseOrder);

            return $receipt->fresh(['items', 'purchaseOrder']);
        });
    }

    public function post(GoodsReceipt $receipt, User $actor): GoodsReceipt
    {
        return DB::transaction(function () use ($receipt, $actor): GoodsReceipt {
            $receipt = GoodsReceipt::query()->with(['items.purchaseOrderItem', 'warehouse.workLocation', 'destinationWorkLocation', 'supplier', 'purchaseOrder.items'])->lockForUpdate()->findOrFail($receipt->id);

            if ($receipt->status === GoodsReceiptStatus::POSTED) {
                return $receipt;
            }

            if ($receipt->status !== GoodsReceiptStatus::DRAFT) {
                throw ServiceException::validation('Hanya receipt draft yang dapat di-posting.');
            }

            $purchaseOrder = $receipt->purchase_order_id
                ? PurchaseOrder::query()->with('items')->lockForUpdate()->find($receipt->purchase_order_id)
                : null;

            $lineCosts = $this->acceptedLineCosts($receipt);
            $totalLineCost = array_reduce($lineCosts, fn (string $carry, string $value): string => Decimal::add($carry, $value, 2), '0.00');
            $totalLandedCost = Decimal::add((string) $receipt->actual_freight_cost, (string) $receipt->actual_additional_cost, 2);
            $poReceivedMap = [];
            $totalReceived = '0.0000';
            $totalAccepted = '0.0000';
            $totalRejected = '0.0000';
            $totalDamaged = '0.0000';

            foreach ($receipt->items as $item) {
                if ($purchaseOrder && $item->purchase_order_item_id) {
                    $poItem = PurchaseOrderItem::query()->lockForUpdate()->findOrFail($item->purchase_order_item_id);
                    $newAcceptedTotal = Decimal::add((string) $poItem->quantity_received, (string) $item->quantity_accepted);

                    if (Decimal::compare($newAcceptedTotal, (string) $poItem->quantity_ordered) > 0) {
                        throw ServiceException::validation("Qty diterima produk {$poItem->product_sku_snapshot} melebihi outstanding PO.");
                    }
                    $poReceivedMap[$poItem->id] = $newAcceptedTotal;
                }

                $acceptedBase = $item->acceptedBaseQuantity();
                $damagedBase = $item->damagedBaseQuantity();
                $landedCost = $this->allocateLandedCost($lineCosts[$item->id] ?? '0.00', $totalLineCost, $totalLandedCost);
                $this->postAcceptedStock($receipt, $item, $acceptedBase, $landedCost, $actor);
                $this->postDamagedStock($receipt, $item, $damagedBase, $actor);
                $this->writeQcResults($item);

                $totalReceived = Decimal::add($totalReceived, (string) $item->quantity_received);
                $totalAccepted = Decimal::add($totalAccepted, (string) $item->quantity_accepted);
                $totalRejected = Decimal::add($totalRejected, (string) $item->quantity_rejected);
                $totalDamaged = Decimal::add($totalDamaged, (string) $item->quantity_damaged);

                SupplierProduct::query()->updateOrCreate(
                    ['supplier_id' => $receipt->supplier_id, 'product_id' => $item->product_id],
                    ['last_price' => $item->unit_price, 'last_supplied_at' => $receipt->received_at],
                );
            }

            $receipt->forceFill([
                'status' => GoodsReceiptStatus::POSTED,
                'posted_at' => now(),
                'posted_by' => $actor->id,
            ])->save();

            if ($purchaseOrder) {
                $this->purchaseOrders->recordReceiptProgress($purchaseOrder, $poReceivedMap, $actor);
            }
            $this->scoreSupplier($receipt, $totalReceived, $totalAccepted, $totalRejected, $totalDamaged);
            activity()->causedBy($actor)->performedOn($receipt)->log('goods_receipt.posted');

            return $receipt->fresh(['items', 'purchaseOrder.items', 'stockMutations', 'costHistories']);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function replaceItems(GoodsReceipt $receipt, array $items, ?PurchaseOrder $purchaseOrder = null): void
    {
        if ($items === []) {
            throw ServiceException::validation('Minimal satu item penerimaan wajib diisi.');
        }

        $receipt->items()->delete();

        foreach ($items as $itemData) {
            $received = Decimal::normalize($itemData['quantity_received']);
            $rejected = Decimal::normalize($itemData['quantity_rejected'] ?? 0);
            $damaged = Decimal::normalize($itemData['quantity_damaged'] ?? 0);
            $returned = Decimal::normalize($itemData['quantity_returned_to_supplier'] ?? 0);
            $nonAcceptedTotal = Decimal::add($rejected, Decimal::add($damaged, $returned));
            $accepted = Decimal::sub($received, $nonAcceptedTotal);

            if (Decimal::compare($accepted, '0') < 0) {
                $productName = $itemData['product_name_snapshot'] ?? 'produk';
                throw ServiceException::validation("Jumlah ditolak, rusak, dan retur supplier untuk {$productName} melebihi jumlah barang datang.");
            }

            if (array_key_exists('quantity_accepted', $itemData)
                && Decimal::compare(Decimal::normalize($itemData['quantity_accepted']), $accepted) !== 0) {
                $productName = $itemData['product_name_snapshot'] ?? 'produk';
                throw ServiceException::validation("Jumlah barang diterima baik untuk {$productName} tidak sesuai dengan pembagian hasil pemeriksaan.");
            }

            if ($purchaseOrder && ! empty($itemData['purchase_order_item_id'])) {
                $poItem = PurchaseOrderItem::query()->with(['product', 'unit'])->findOrFail($itemData['purchase_order_item_id']);

                if ((int) $poItem->purchase_order_id !== (int) $purchaseOrder->id) {
                    throw ServiceException::validation('Item tidak sesuai dengan PO.');
                }

                $outstanding = Decimal::sub((string) $poItem->quantity_ordered, (string) $poItem->quantity_received);
                if (Decimal::compare($accepted, $outstanding) > 0) {
                    throw ServiceException::validation("Qty diterima produk {$poItem->product_sku_snapshot} melebihi outstanding PO.");
                }

                $receipt->items()->create([
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $poItem->product_id,
                    'unit_id' => $poItem->unit_id,
                    'warehouse_location_id' => $itemData['warehouse_location_id'] ?? null,
                    'product_sku_snapshot' => $poItem->product_sku_snapshot,
                    'product_name_snapshot' => $poItem->product_name_snapshot,
                    'unit_name_snapshot' => $poItem->unit_name_snapshot,
                    'conversion_factor_snapshot' => $poItem->conversion_factor_snapshot,
                    'quantity_ordered' => $poItem->quantity_ordered,
                    'previously_received' => $poItem->quantity_received,
                    'outstanding_before' => $outstanding,
                    'quantity_received' => $received,
                    'quantity_accepted' => $accepted,
                    'quantity_rejected' => $rejected,
                    'quantity_damaged' => $damaged,
                    'quantity_returned_to_supplier' => $returned,
                    'unit_price' => $poItem->unit_price,
                    // Fallback menjaga kompatibilitas integrasi lama yang membuat draft langsung
                    // lewat service. Form HTTP baru tetap mewajibkan kedua nilai ini.
                    'actual_unit_cost' => $itemData['actual_unit_cost'] ?? $poItem->unit_price,
                    'sale_price' => $itemData['sale_price']
                        ?? (Decimal::compare((string) $poItem->product->minimum_price, '0', 2) > 0
                            ? $poItem->product->minimum_price
                            : null),
                    'batch_no' => $itemData['batch_no'] ?? null,
                    'qc_notes' => $itemData['qc_notes'] ?? null,
                ]);
            } else {
                // Direct Goods Receipt (Penerimaan Langsung Tanpa PO)
                $productId = (int) ($itemData['product_id'] ?? 0);
                $product = Product::query()->with('units')->findOrFail($productId);
                $unitId = (int) ($itemData['unit_id'] ?? 0);
                $unit = Unit::query()->findOrFail($unitId);

                $conversionFactor = '1.000000';
                $customUnit = $product->units->firstWhere('unit_id', $unit->id);
                if ($customUnit && (float) $customUnit->conversion_factor > 0) {
                    $conversionFactor = Decimal::normalize((string) $customUnit->conversion_factor, 6);
                } elseif (in_array(strtoupper((string) $unit->code), ['PCS', 'PC', 'BH'], true)) {
                    $conversionFactor = '1.000000';
                } elseif (strtoupper((string) $unit->code) === 'LSN') {
                    $conversionFactor = '12.000000';
                } elseif (strtoupper((string) $unit->code) === 'GROS') {
                    $conversionFactor = '144.000000';
                }

                $unitPrice = Decimal::normalize($itemData['unit_price'] ?? $itemData['actual_unit_cost'] ?? 0, 2);
                $actualUnitCost = Decimal::normalize($itemData['actual_unit_cost'] ?? $unitPrice, 2);
                $salePrice = Decimal::normalize($itemData['sale_price'] ?? 0, 2);

                $receipt->items()->create([
                    'purchase_order_item_id' => null,
                    'product_id' => $product->id,
                    'unit_id' => $unit->id,
                    'warehouse_location_id' => $itemData['warehouse_location_id'] ?? null,
                    'product_sku_snapshot' => $product->sku,
                    'product_name_snapshot' => $product->name,
                    'unit_name_snapshot' => $unit->name,
                    'conversion_factor_snapshot' => $conversionFactor,
                    'quantity_ordered' => $received,
                    'previously_received' => '0.0000',
                    'outstanding_before' => $received,
                    'quantity_received' => $received,
                    'quantity_accepted' => $accepted,
                    'quantity_rejected' => $rejected,
                    'quantity_damaged' => $damaged,
                    'quantity_returned_to_supplier' => $returned,
                    'unit_price' => $unitPrice,
                    'actual_unit_cost' => $actualUnitCost,
                    'sale_price' => $salePrice,
                    'batch_no' => $itemData['batch_no'] ?? null,
                    'qc_notes' => $itemData['qc_notes'] ?? null,
                ]);
            }
        }
    }

    /** @return array<int, string> */
    private function acceptedLineCosts(GoodsReceipt $receipt): array
    {
        $costs = [];

        foreach ($receipt->items as $item) {
            if (Decimal::compare((string) $item->quantity_accepted, '0') <= 0) {
                $costs[$item->id] = '0.00';

                continue;
            }

            if ($item->purchaseOrderItem) {
                $costPerPurchaseUnit = Decimal::div((string) $item->purchaseOrderItem->subtotal, (string) $item->purchaseOrderItem->quantity_ordered, 2, 4, 2);
                $costs[$item->id] = Decimal::mul((string) $item->quantity_accepted, $costPerPurchaseUnit);
            } else {
                // Standalone / Direct Goods Receipt
                // Total nilai pembelian barang diterima = quantity_accepted * unit_price
                $costs[$item->id] = Decimal::mul((string) $item->quantity_accepted, (string) $item->unit_price, 2);
            }
        }

        return $costs;
    }

    private function allocateLandedCost(string $lineCost, string $totalLineCost, string $totalLandedCost): string
    {
        if (Decimal::compare($lineCost, '0', 2) <= 0 || Decimal::compare($totalLineCost, '0', 2) <= 0 || Decimal::compare($totalLandedCost, '0', 2) <= 0) {
            return '0.00';
        }

        $ratio = Decimal::div($lineCost, $totalLineCost, 2, 2, 6);

        return Decimal::mul($totalLandedCost, $ratio, 2, 6, 2);
    }

    private function postAcceptedStock(GoodsReceipt $receipt, GoodsReceiptItem $item, string $acceptedBase, string $landedCost, User $actor): void
    {
        if (Decimal::compare($acceptedBase, '0') <= 0) {
            return;
        }

        $product = Product::query()->lockForUpdate()->findOrFail($item->product_id);
        $destination = $this->destination($receipt);
        $bin = $item->warehouse_location_id ? WarehouseLocation::query()->find($item->warehouse_location_id) : null;
        $beforeQty = Decimal::normalize(Stock::query()->where('product_id', $product->id)->sum('quantity_on_hand'));
        $hppBefore = Decimal::normalize($product->cost_price ?? 0, 2);
        $lineCost = $this->acceptedLineCosts($receipt)[$item->id] ?? '0.00';
        $incomingCost = Decimal::add($lineCost, $landedCost, 2);
        $qtyAfter = Decimal::add($beforeQty, $acceptedBase);

        // Hitung HPP Final Aktual per satuan dasar (Pcs):
        // $unitPrice = harga beli per satuan pembelian
        // $conversion = faktor konversi ke satuan dasar
        // $baseCostBeli = $unitPrice / $conversion
        // $landedCostPerBase = $landedCost / $acceptedBase
        // $actualUnitCost = $baseCostBeli + $landedCostPerBase
        $conversion = (string) ($item->conversion_factor_snapshot ?: '1.000000');
        $baseCostBeli = Decimal::div((string) $item->unit_price, $conversion, 2, 6, 4);
        $landedCostPerBase = Decimal::compare($landedCost, '0', 2) > 0 && Decimal::compare($acceptedBase, '0', 4) > 0
            ? Decimal::div($landedCost, $acceptedBase, 2, 6, 4)
            : '0.00';
        $actualUnitCost = Decimal::add($baseCostBeli, $landedCostPerBase, 2);

        // Validasi Anti-Rugi pada saat posting:
        // Cek HPP batch tertinggi yang masih aktif dengan stok di lokasi ini
        $highestActiveBatchCost = (string) (StockBatch::query()
            ->where('product_id', $product->id)
            ->where('work_location_id', $destination->id)
            ->where('status', 'active')
            ->where('quantity_on_hand', '>', 0)
            ->max('cost_price') ?? '0.00');

        $floorCost = Decimal::compare($actualUnitCost, $highestActiveBatchCost, 2) >= 0
            ? $actualUnitCost
            : $highestActiveBatchCost;

        if ($item->sale_price !== null) {
            $salePrice = Decimal::normalize((string) $item->sale_price, 2);
            if (Decimal::compare($salePrice, $floorCost, 2) <= 0) {
                throw ServiceException::validation(
                    "Harga jual minimum untuk {$item->product_name_snapshot} (Rp ".number_format((float) $salePrice, 0, ',', '.').') '.
                    'dilarang lebih rendah atau sama dengan batas modal anti-rugi (Rp '.number_format((float) $floorCost, 0, ',', '.').').'
                );
            }
        }

        $mutation = $this->inventory->receive(
            product: $product,
            workLocation: $destination,
            warehouseLocation: $bin,
            quantity: $acceptedBase,
            actor: $actor,
            reference: ['type' => 'goods_receipt', 'id' => $receipt->id, 'no' => $receipt->number],
            reason: 'Penerimaan barang accepted.',
            idempotencyKey: "receipt-{$receipt->id}-item-{$item->id}-accepted",
            metadata: ['goods_receipt_item_id' => $item->id],
        );

        // TIDAK lagi update products.cost_price dengan moving average;
        // products.cost_price hanya jadi fallback kompatibilitas, tidak diupdate di sini.
        $item->forceFill([
            'landed_cost_allocated' => $landedCost,
            'actual_unit_cost' => $actualUnitCost,
            'hpp_before' => $hppBefore,
            'incoming_cost' => $incomingCost,
            'hpp_after' => $actualUnitCost,
        ])->save();

        ProductCostHistory::query()->create([
            'product_id' => $product->id,
            'supplier_id' => $receipt->supplier_id,
            'goods_receipt_id' => $receipt->id,
            'goods_receipt_item_id' => $item->id,
            'method' => 'actual_batch',
            'qty_before' => $beforeQty,
            'qty_incoming' => $acceptedBase,
            'qty_after' => $qtyAfter,
            'hpp_before' => $hppBefore,
            'incoming_cost' => $incomingCost,       // total biaya masuk termasuk landed cost
            'landed_cost_allocated' => $landedCost,
            'hpp_after' => $actualUnitCost,          // HPP aktual per item, bukan rata-rata
            'effective_at' => now(),
        ]);

        $layer = $this->layers->createLayer([
            'source_batch_id' => null,
            'product_id' => $product->id,
            'supplier_id' => $receipt->supplier_id,
            'stock_id' => $mutation->stock_id,
            'work_location_id' => $destination->id,
            'warehouse_location_id' => $bin?->id,
            'goods_receipt_id' => $receipt->id,
            'goods_receipt_item_id' => $item->id,
            'source_type' => 'goods_receipt',
            'source_id' => $receipt->id,
            'source_item_id' => $item->id,
            'unit_id' => $item->unit_id,
            'conversion_factor_snapshot' => (string) $item->conversion_factor_snapshot,
            'received_at' => $receipt->received_at,
            'cost_price' => $actualUnitCost,                         // HPP aktual per item
            'entry_sale_price_snapshot' => $item->sale_price === null ? null : (string) $item->sale_price,
            'quantity_on_hand' => Decimal::add($acceptedBase, $item->damagedBaseQuantity()),
            'quantity_reserved' => '0.0000',
            'quantity_damaged' => $item->damagedBaseQuantity(),
            'expires_at' => null,
            'batch_no' => $item->batch_no ?: "{$receipt->number}-{$item->id}",
        ]);
        $this->layers->recordAllocation($layer, [
            'reference_type' => 'goods_receipt_item',
            'reference_id' => $item->id,
            'event_type' => 'goods_receipt',
            'quantity' => Decimal::add($acceptedBase, $item->damagedBaseQuantity()),
            'sale_price_snapshot' => $item->sale_price === null ? null : (string) $item->sale_price,
            'actor_user_id' => $actor->id,
            'metadata' => ['goods_receipt_id' => $receipt->id],
            'occurred_at' => now(),
        ]);
        if ($item->sale_price !== null) {
            $this->locationPrices->setActivePrice(
                $product,
                $destination,
                (string) $item->sale_price,
                $actor,
                'goods_receipt',
                "Penerimaan {$receipt->number}",
            );
        }
    }

    private function postDamagedStock(GoodsReceipt $receipt, GoodsReceiptItem $item, string $damagedBase, User $actor): void
    {
        if (Decimal::compare($damagedBase, '0') <= 0) {
            return;
        }

        $product = Product::query()->findOrFail($item->product_id);
        $destination = $this->destination($receipt);
        $bin = $item->warehouse_location_id ? WarehouseLocation::query()->find($item->warehouse_location_id) : null;

        $this->inventory->receive($product, $destination, $bin, $damagedBase, $actor, ['type' => 'goods_receipt', 'id' => $receipt->id, 'no' => $receipt->number], 'Penerimaan barang rusak.', "receipt-{$receipt->id}-item-{$item->id}-damaged-receive");
        $this->inventory->damage($product, $destination, $bin, $damagedBase, $actor, ['type' => 'goods_receipt', 'id' => $receipt->id, 'no' => $receipt->number], 'QC barang rusak.', "receipt-{$receipt->id}-item-{$item->id}-damaged-qc");
    }

    private function destination(GoodsReceipt $receipt): WorkLocation
    {
        $destination = $receipt->destinationWorkLocation ?? $receipt->warehouse?->workLocation;
        if (! $destination) {
            throw ServiceException::validation('Lokasi penerima barang tidak ditemukan.');
        }

        return $destination;
    }

    private function writeQcResults(GoodsReceiptItem $item): void
    {
        $results = [
            [ReceiptQcStatus::ACCEPTED, $item->quantity_accepted],
            [ReceiptQcStatus::REJECTED, $item->quantity_rejected],
            [ReceiptQcStatus::DAMAGED, $item->quantity_damaged],
            [ReceiptQcStatus::RETURNED_TO_SUPPLIER, $item->quantity_returned_to_supplier],
        ];

        foreach ($results as [$status, $quantity]) {
            if (Decimal::compare((string) $quantity, '0') > 0) {
                ReceiptQcResult::query()->create([
                    'goods_receipt_item_id' => $item->id,
                    'qc_status' => $status,
                    'quantity' => $quantity,
                    'reason' => $item->qc_notes,
                ]);
            }
        }
    }

    private function scoreSupplier(GoodsReceipt $receipt, string $received, string $accepted, string $rejected, string $damaged): void
    {
        $qualityScore = Decimal::compare($received, '0') > 0
            ? Decimal::mul(Decimal::div($accepted, $received, 4, 4, 4), '100', 4, 2, 2)
            : '0.00';

        SupplierScore::query()->create([
            'supplier_id' => $receipt->supplier_id,
            'goods_receipt_id' => $receipt->id,
            'quantity_received' => $received,
            'quantity_accepted' => $accepted,
            'quantity_rejected' => $rejected,
            'quantity_damaged' => $damaged,
            'quality_score' => $qualityScore,
            'delivery_score' => '100.00',
            'price_score' => '100.00',
            'total_score' => $qualityScore,
            'received_at' => $receipt->received_at,
        ]);
    }
}
