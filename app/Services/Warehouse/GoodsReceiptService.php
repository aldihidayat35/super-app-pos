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
use App\Models\SupplierProduct;
use App\Models\SupplierScore;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\Inventory\InventoryService;
use App\Services\Organization\DocumentNumberService;
use App\Services\Purchasing\PurchaseOrderService;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

class GoodsReceiptService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly InventoryService $inventory,
        private readonly PurchaseOrderService $purchaseOrders,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDraft(array $data, User $actor): GoodsReceipt
    {
        return DB::transaction(function () use ($data, $actor): GoodsReceipt {
            $purchaseOrder = PurchaseOrder::query()->with(['warehouse.workLocation', 'supplier', 'items.product', 'items.unit'])->lockForUpdate()->findOrFail($data['purchase_order_id']);

            if (! in_array($purchaseOrder->status, [PurchaseOrderStatus::APPROVED, PurchaseOrderStatus::SENT_TO_SUPPLIER, PurchaseOrderStatus::PARTIALLY_RECEIVED], true)) {
                throw ServiceException::validation('PO belum siap diterima.');
            }

            $receipt = GoodsReceipt::query()->create([
                'number' => $this->numbers->next('receipt', $purchaseOrder->warehouse->workLocation),
                'purchase_order_id' => $purchaseOrder->id,
                'warehouse_id' => $purchaseOrder->warehouse_id,
                'supplier_id' => $purchaseOrder->supplier_id,
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

            $this->replaceItems($receipt, $purchaseOrder, $data['items'] ?? []);

            return $receipt->fresh(['items', 'purchaseOrder', 'supplier', 'warehouse']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(GoodsReceipt $receipt, array $data, User $actor): GoodsReceipt
    {
        return DB::transaction(function () use ($receipt, $data): GoodsReceipt {
            $receipt = GoodsReceipt::query()->with('purchaseOrder.items')->lockForUpdate()->findOrFail($receipt->id);

            if (! $receipt->status->isEditable()) {
                throw ServiceException::validation('Receipt yang sudah posted tidak boleh diedit.');
            }

            $receipt->fill([
                'received_at' => $data['received_at'],
                'delivery_note_number' => $data['delivery_note_number'] ?? null,
                'actual_freight_cost' => Decimal::normalize($data['actual_freight_cost'] ?? 0, 2),
                'actual_additional_cost' => Decimal::normalize($data['actual_additional_cost'] ?? 0, 2),
                'notes' => $data['notes'] ?? null,
                'proof_path' => $data['proof_path'] ?? $receipt->proof_path,
            ])->save();

            $this->replaceItems($receipt, $receipt->purchaseOrder, $data['items'] ?? []);

            return $receipt->fresh(['items', 'purchaseOrder']);
        });
    }

    public function post(GoodsReceipt $receipt, User $actor): GoodsReceipt
    {
        return DB::transaction(function () use ($receipt, $actor): GoodsReceipt {
            $receipt = GoodsReceipt::query()->with(['items.purchaseOrderItem', 'warehouse.workLocation', 'supplier', 'purchaseOrder.items'])->lockForUpdate()->findOrFail($receipt->id);

            if ($receipt->status === GoodsReceiptStatus::POSTED) {
                return $receipt;
            }

            if ($receipt->status !== GoodsReceiptStatus::DRAFT) {
                throw ServiceException::validation('Hanya receipt draft yang dapat di-posting.');
            }

            $purchaseOrder = PurchaseOrder::query()->with('items')->lockForUpdate()->findOrFail($receipt->purchase_order_id);
            $lineCosts = $this->acceptedLineCosts($receipt);
            $totalLineCost = array_reduce($lineCosts, fn (string $carry, string $value): string => Decimal::add($carry, $value, 2), '0.00');
            $totalLandedCost = Decimal::add((string) $receipt->actual_freight_cost, (string) $receipt->actual_additional_cost, 2);
            $poReceivedMap = [];
            $totalReceived = '0.0000';
            $totalAccepted = '0.0000';
            $totalRejected = '0.0000';
            $totalDamaged = '0.0000';

            foreach ($receipt->items as $item) {
                $poItem = PurchaseOrderItem::query()->lockForUpdate()->findOrFail($item->purchase_order_item_id);
                $newAcceptedTotal = Decimal::add((string) $poItem->quantity_received, (string) $item->quantity_accepted);

                if (Decimal::compare($newAcceptedTotal, (string) $poItem->quantity_ordered) > 0) {
                    throw ServiceException::validation("Qty diterima produk {$poItem->product_sku_snapshot} melebihi outstanding PO.");
                }

                $acceptedBase = $item->acceptedBaseQuantity();
                $damagedBase = $item->damagedBaseQuantity();
                $landedCost = $this->allocateLandedCost($lineCosts[$item->id] ?? '0.00', $totalLineCost, $totalLandedCost);
                $this->postAcceptedStock($receipt, $item, $acceptedBase, $landedCost, $actor);
                $this->postDamagedStock($receipt, $item, $damagedBase, $actor);
                $this->writeQcResults($item);

                $poReceivedMap[$poItem->id] = $newAcceptedTotal;
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

            $this->purchaseOrders->recordReceiptProgress($purchaseOrder, $poReceivedMap, $actor);
            $this->scoreSupplier($receipt, $totalReceived, $totalAccepted, $totalRejected, $totalDamaged);
            activity()->causedBy($actor)->performedOn($receipt)->log('goods_receipt.posted');

            return $receipt->fresh(['items', 'purchaseOrder.items', 'stockMutations', 'costHistories']);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function replaceItems(GoodsReceipt $receipt, PurchaseOrder $purchaseOrder, array $items): void
    {
        if ($items === []) {
            throw ServiceException::validation('Minimal satu item penerimaan wajib diisi.');
        }

        $receipt->items()->delete();

        foreach ($items as $itemData) {
            $poItem = PurchaseOrderItem::query()->with(['product', 'unit'])->findOrFail($itemData['purchase_order_item_id']);

            if ((int) $poItem->purchase_order_id !== (int) $purchaseOrder->id) {
                throw ServiceException::validation('Item tidak sesuai dengan PO.');
            }

            $received = Decimal::normalize($itemData['quantity_received']);
            $accepted = Decimal::normalize($itemData['quantity_accepted'] ?? 0);
            $rejected = Decimal::normalize($itemData['quantity_rejected'] ?? 0);
            $damaged = Decimal::normalize($itemData['quantity_damaged'] ?? 0);
            $returned = Decimal::normalize($itemData['quantity_returned_to_supplier'] ?? 0);
            $qcTotal = Decimal::add(Decimal::add($accepted, $rejected), Decimal::add($damaged, $returned));

            if (Decimal::compare($qcTotal, $received) !== 0) {
                throw ServiceException::validation("Total QC produk {$poItem->product_sku_snapshot} harus sama dengan qty datang.");
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
                'batch_no' => $itemData['batch_no'] ?? null,
                'qc_notes' => $itemData['qc_notes'] ?? null,
            ]);
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

            $costPerPurchaseUnit = Decimal::div((string) $item->purchaseOrderItem->subtotal, (string) $item->purchaseOrderItem->quantity_ordered, 2, 4, 2);
            $costs[$item->id] = Decimal::mul((string) $item->quantity_accepted, $costPerPurchaseUnit);
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
        $warehouse = Warehouse::query()->with('workLocation')->findOrFail($receipt->warehouse_id);
        $bin = $item->warehouse_location_id ? WarehouseLocation::query()->find($item->warehouse_location_id) : null;
        $beforeQty = Decimal::normalize(Stock::query()->where('product_id', $product->id)->sum('quantity_on_hand'));
        $hppBefore = Decimal::normalize($product->cost_price ?? 0, 2);
        $lineCost = $this->acceptedLineCosts($receipt)[$item->id] ?? '0.00';
        $incomingCost = Decimal::add($lineCost, $landedCost, 2);
        $oldInventoryValue = Decimal::mul($beforeQty, $hppBefore);
        $qtyAfter = Decimal::add($beforeQty, $acceptedBase);
        $hppAfter = Decimal::div(Decimal::add($oldInventoryValue, $incomingCost, 2), $qtyAfter, 2, 4, 2);

        $mutation = $this->inventory->receive(
            product: $product,
            workLocation: $warehouse->workLocation,
            warehouseLocation: $bin,
            quantity: $acceptedBase,
            actor: $actor,
            reference: ['type' => 'goods_receipt', 'id' => $receipt->id, 'no' => $receipt->number],
            reason: 'Penerimaan barang accepted.',
            idempotencyKey: "receipt-{$receipt->id}-item-{$item->id}-accepted",
            metadata: ['goods_receipt_item_id' => $item->id],
        );

        $product->forceFill(['cost_price' => $hppAfter])->save();
        $item->forceFill([
            'landed_cost_allocated' => $landedCost,
            'hpp_before' => $hppBefore,
            'incoming_cost' => $incomingCost,
            'hpp_after' => $hppAfter,
        ])->save();

        ProductCostHistory::query()->create([
            'product_id' => $product->id,
            'supplier_id' => $receipt->supplier_id,
            'goods_receipt_id' => $receipt->id,
            'goods_receipt_item_id' => $item->id,
            'method' => 'moving_average',
            'qty_before' => $beforeQty,
            'qty_incoming' => $acceptedBase,
            'qty_after' => $qtyAfter,
            'hpp_before' => $hppBefore,
            'incoming_cost' => $incomingCost,
            'landed_cost_allocated' => $landedCost,
            'hpp_after' => $hppAfter,
            'effective_at' => now(),
        ]);

        StockBatch::query()->updateOrCreate(
            ['product_id' => $product->id, 'batch_no' => $item->batch_no ?: "{$receipt->number}-{$item->id}"],
            [
                'supplier_id' => $receipt->supplier_id,
                'stock_id' => $mutation->stock_id,
                'goods_receipt_id' => $receipt->id,
                'goods_receipt_item_id' => $item->id,
                'received_at' => $receipt->received_at,
                'cost_price' => $hppAfter,
                'quantity_on_hand' => Decimal::add($acceptedBase, $item->damagedBaseQuantity()),
                'quantity_reserved' => 0,
                'status' => 'active',
            ],
        );
    }

    private function postDamagedStock(GoodsReceipt $receipt, GoodsReceiptItem $item, string $damagedBase, User $actor): void
    {
        if (Decimal::compare($damagedBase, '0') <= 0) {
            return;
        }

        $product = Product::query()->findOrFail($item->product_id);
        $warehouse = Warehouse::query()->with('workLocation')->findOrFail($receipt->warehouse_id);
        $bin = $item->warehouse_location_id ? WarehouseLocation::query()->find($item->warehouse_location_id) : null;

        $this->inventory->receive($product, $warehouse->workLocation, $bin, $damagedBase, $actor, ['type' => 'goods_receipt', 'id' => $receipt->id, 'no' => $receipt->number], 'Penerimaan barang rusak.', "receipt-{$receipt->id}-item-{$item->id}-damaged-receive");
        $this->inventory->damage($product, $warehouse->workLocation, $bin, $damagedBase, $actor, ['type' => 'goods_receipt', 'id' => $receipt->id, 'no' => $receipt->number], 'QC barang rusak.', "receipt-{$receipt->id}-item-{$item->id}-damaged-qc");
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
