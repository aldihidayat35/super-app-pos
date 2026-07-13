<?php

namespace App\Services\Purchasing;

use App\Enums\PurchaseOrderStatus;
use App\Exceptions\ServiceException;
use App\Models\Approval;
use App\Models\DocumentStatusHistory;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Organization\DocumentNumberService;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function __construct(private readonly DocumentNumberService $numbers) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $actor): PurchaseOrder {
            $warehouse = Warehouse::query()->with('workLocation')->findOrFail($data['warehouse_id']);
            $supplier = Supplier::query()->findOrFail($data['supplier_id']);
            $number = $this->numbers->next('po', $warehouse->workLocation);

            $purchaseOrder = PurchaseOrder::query()->create([
                'number' => $number,
                'warehouse_id' => $warehouse->id,
                'supplier_id' => $supplier->id,
                'purchase_request_id' => $data['purchase_request_id'] ?? null,
                'order_date' => $data['order_date'],
                'expected_at' => $data['expected_at'] ?? null,
                'payment_term_days' => $data['payment_term_days'] ?? $supplier->payment_term_days ?? 0,
                'notes' => $data['notes'] ?? null,
                'status' => PurchaseOrderStatus::DRAFT,
                'created_by' => $actor->id,
                'header_discount' => Decimal::normalize($data['header_discount'] ?? 0, 2),
                'freight_cost' => Decimal::normalize($data['freight_cost'] ?? 0, 2),
                'additional_cost' => Decimal::normalize($data['additional_cost'] ?? 0, 2),
            ]);

            $this->replaceItems($purchaseOrder, $data['items'] ?? []);
            $this->recalculate($purchaseOrder);
            $this->history($purchaseOrder, null, PurchaseOrderStatus::DRAFT, $actor, 'PO dibuat sebagai draft.');

            return $purchaseOrder->fresh(['items', 'supplier', 'warehouse']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PurchaseOrder $purchaseOrder, array $data, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $data, $actor): PurchaseOrder {
            $purchaseOrder = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->id);

            if (! $purchaseOrder->status->canEditItems()) {
                throw ServiceException::validation('PO yang sudah disetujui tidak boleh diedit. Gunakan proses reopen/revision yang diaudit.');
            }

            $purchaseOrder->fill([
                'warehouse_id' => $data['warehouse_id'],
                'supplier_id' => $data['supplier_id'],
                'order_date' => $data['order_date'],
                'expected_at' => $data['expected_at'] ?? null,
                'payment_term_days' => $data['payment_term_days'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'header_discount' => Decimal::normalize($data['header_discount'] ?? 0, 2),
                'freight_cost' => Decimal::normalize($data['freight_cost'] ?? 0, 2),
                'additional_cost' => Decimal::normalize($data['additional_cost'] ?? 0, 2),
            ])->save();

            $this->replaceItems($purchaseOrder, $data['items'] ?? []);
            $this->recalculate($purchaseOrder);
            activity()->causedBy($actor)->performedOn($purchaseOrder)->log('purchase_order.updated');

            return $purchaseOrder->fresh(['items', 'supplier', 'warehouse']);
        });
    }

    public function submit(PurchaseOrder $purchaseOrder, User $actor): PurchaseOrder
    {
        return $this->transition($purchaseOrder, PurchaseOrderStatus::SUBMITTED, $actor, 'PO diajukan untuk approval.');
    }

    public function approve(PurchaseOrder $purchaseOrder, User $actor, ?string $notes = null): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $actor, $notes): PurchaseOrder {
            $purchaseOrder = $this->transition($purchaseOrder, PurchaseOrderStatus::APPROVED, $actor, $notes ?: 'PO disetujui.');
            Approval::query()->create([
                'document_type' => 'purchase_order',
                'document_id' => $purchaseOrder->id,
                'status' => 'approved',
                'requested_by' => $purchaseOrder->submitted_by,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'notes' => $notes,
            ]);

            return $purchaseOrder;
        });
    }

    public function markSent(PurchaseOrder $purchaseOrder, User $actor): PurchaseOrder
    {
        return $this->transition($purchaseOrder, PurchaseOrderStatus::SENT_TO_SUPPLIER, $actor, 'PO dikirim ke supplier.');
    }

    public function cancel(PurchaseOrder $purchaseOrder, User $actor, string $reason): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $actor, $reason): PurchaseOrder {
            $purchaseOrder = PurchaseOrder::query()->with('items')->lockForUpdate()->findOrFail($purchaseOrder->id);

            if ($purchaseOrder->items->contains(fn (PurchaseOrderItem $item): bool => Decimal::compare((string) $item->quantity_received, '0') > 0)) {
                throw ServiceException::validation('PO yang sudah menerima barang tidak boleh dibatalkan.');
            }

            return $this->transition($purchaseOrder, PurchaseOrderStatus::CANCELLED, $actor, $reason);
        });
    }

    /**
     * @param  array<int, string|int|float>  $receivedQuantities
     */
    public function recordReceiptProgress(PurchaseOrder $purchaseOrder, array $receivedQuantities, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $receivedQuantities, $actor): PurchaseOrder {
            $purchaseOrder = PurchaseOrder::query()->with('items')->lockForUpdate()->findOrFail($purchaseOrder->id);

            if (! in_array($purchaseOrder->status, [PurchaseOrderStatus::APPROVED, PurchaseOrderStatus::SENT_TO_SUPPLIER, PurchaseOrderStatus::PARTIALLY_RECEIVED], true)) {
                throw ServiceException::validation('PO belum siap menerima barang.');
            }

            foreach ($purchaseOrder->items as $item) {
                if (! array_key_exists($item->id, $receivedQuantities)) {
                    continue;
                }

                $received = Decimal::normalize($receivedQuantities[$item->id]);
                if (Decimal::compare($received, (string) $item->quantity_ordered) > 0) {
                    throw ServiceException::validation('Qty diterima tidak boleh melebihi qty order.');
                }

                $item->forceFill(['quantity_received' => $received])->save();
            }

            $purchaseOrder->refresh()->load('items');
            $ordered = $purchaseOrder->orderedQuantity();
            $received = $purchaseOrder->receivedQuantity();

            if (Decimal::compare($received, '0') > 0 && Decimal::compare($received, $ordered) < 0) {
                if ($purchaseOrder->status === PurchaseOrderStatus::PARTIALLY_RECEIVED) {
                    return $purchaseOrder->fresh(['items', 'supplier', 'warehouse', 'statusHistories', 'approvals']);
                }

                return $this->transition($purchaseOrder, PurchaseOrderStatus::PARTIALLY_RECEIVED, $actor, 'PO diterima sebagian.');
            }

            if (Decimal::compare($received, $ordered) === 0) {
                return $this->transition($purchaseOrder, PurchaseOrderStatus::COMPLETED, $actor, 'PO selesai diterima.');
            }

            return $purchaseOrder;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function replaceItems(PurchaseOrder $purchaseOrder, array $items): void
    {
        if ($items === []) {
            throw ServiceException::validation('Minimal satu item PO wajib diisi.');
        }

        $purchaseOrder->items()->delete();

        foreach ($items as $itemData) {
            $product = Product::query()->with('baseUnit')->findOrFail($itemData['product_id']);
            $unit = Unit::query()->findOrFail($itemData['unit_id']);
            $productUnit = ProductUnit::query()
                ->where('product_id', $product->id)
                ->where('unit_id', $unit->id)
                ->first();
            $conversion = $productUnit instanceof ProductUnit
                ? $productUnit->conversion_factor
                : ($product->base_unit_id === $unit->id ? '1.000000' : null);

            if ($conversion === null) {
                throw ServiceException::validation("Satuan {$unit->name} belum terdaftar untuk produk {$product->sku}.");
            }

            $subtotal = $this->lineSubtotal($itemData['quantity_ordered'], $itemData['unit_price'], $itemData['discount_amount'] ?? 0, $itemData['tax_amount'] ?? 0);

            $purchaseOrder->items()->create([
                'product_id' => $product->id,
                'unit_id' => $unit->id,
                'product_sku_snapshot' => $product->sku,
                'product_name_snapshot' => $product->name,
                'unit_name_snapshot' => $unit->name,
                'conversion_factor_snapshot' => $conversion,
                'quantity_ordered' => Decimal::normalize($itemData['quantity_ordered']),
                'unit_price' => Decimal::normalize($itemData['unit_price'], 2),
                'discount_amount' => Decimal::normalize($itemData['discount_amount'] ?? 0, 2),
                'tax_amount' => Decimal::normalize($itemData['tax_amount'] ?? 0, 2),
                'subtotal' => $subtotal,
            ]);
        }
    }

    public function lineSubtotal(string|int|float $quantity, string|int|float $unitPrice, string|int|float $discount = 0, string|int|float $tax = 0): string
    {
        $gross = Decimal::mul($quantity, $unitPrice);

        return Decimal::add(Decimal::sub($gross, Decimal::normalize($discount, 2), 2), Decimal::normalize($tax, 2), 2);
    }

    public function recalculate(PurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->load('items');
        $itemsSubtotal = $purchaseOrder->items->reduce(fn (string $carry, PurchaseOrderItem $item): string => Decimal::add($carry, (string) $item->subtotal, 2), '0.00');
        $grandTotal = Decimal::add(
            Decimal::add(Decimal::sub($itemsSubtotal, (string) $purchaseOrder->header_discount, 2), (string) $purchaseOrder->freight_cost, 2),
            (string) $purchaseOrder->additional_cost,
            2,
        );

        if (Decimal::compare($grandTotal, '0', 2) < 0) {
            throw ServiceException::validation('Total akhir PO tidak boleh negatif.');
        }

        $purchaseOrder->forceFill([
            'items_subtotal' => $itemsSubtotal,
            'grand_total' => $grandTotal,
        ])->save();
    }

    private function transition(PurchaseOrder $purchaseOrder, PurchaseOrderStatus $to, User $actor, ?string $notes = null): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $to, $actor, $notes): PurchaseOrder {
            $purchaseOrder = PurchaseOrder::query()->with('items')->lockForUpdate()->findOrFail($purchaseOrder->id);
            $from = $purchaseOrder->status;

            if (! $this->canTransition($from, $to)) {
                throw ServiceException::validation("Transisi PO dari {$from->label()} ke {$to->label()} tidak valid.");
            }

            if ($to === PurchaseOrderStatus::SUBMITTED && $purchaseOrder->items()->count() === 0) {
                throw ServiceException::validation('PO tanpa item tidak boleh diajukan.');
            }

            $payload = ['status' => $to];
            if ($to === PurchaseOrderStatus::SUBMITTED) {
                $payload += ['submitted_at' => now(), 'submitted_by' => $actor->id];
            } elseif ($to === PurchaseOrderStatus::APPROVED) {
                $payload += ['approved_at' => now(), 'approved_by' => $actor->id];
            } elseif ($to === PurchaseOrderStatus::SENT_TO_SUPPLIER) {
                $payload += ['sent_at' => now(), 'sent_by' => $actor->id];
            } elseif ($to === PurchaseOrderStatus::CANCELLED) {
                $payload += ['cancelled_at' => now(), 'cancelled_by' => $actor->id, 'cancel_reason' => $notes];
            }

            $purchaseOrder->forceFill($payload)->save();
            $this->history($purchaseOrder, $from, $to, $actor, $notes);
            activity()->causedBy($actor)->performedOn($purchaseOrder)->log('purchase_order.status_changed');

            return $purchaseOrder->fresh(['items', 'supplier', 'warehouse', 'statusHistories', 'approvals']);
        });
    }

    private function canTransition(PurchaseOrderStatus $from, PurchaseOrderStatus $to): bool
    {
        return match ($from) {
            PurchaseOrderStatus::DRAFT => in_array($to, [PurchaseOrderStatus::SUBMITTED, PurchaseOrderStatus::CANCELLED], true),
            PurchaseOrderStatus::SUBMITTED => in_array($to, [PurchaseOrderStatus::APPROVED, PurchaseOrderStatus::CANCELLED], true),
            PurchaseOrderStatus::APPROVED => in_array($to, [PurchaseOrderStatus::SENT_TO_SUPPLIER, PurchaseOrderStatus::CANCELLED, PurchaseOrderStatus::PARTIALLY_RECEIVED, PurchaseOrderStatus::COMPLETED], true),
            PurchaseOrderStatus::SENT_TO_SUPPLIER => in_array($to, [PurchaseOrderStatus::PARTIALLY_RECEIVED, PurchaseOrderStatus::COMPLETED], true),
            PurchaseOrderStatus::PARTIALLY_RECEIVED => $to === PurchaseOrderStatus::COMPLETED,
            PurchaseOrderStatus::COMPLETED, PurchaseOrderStatus::CANCELLED => false,
        };
    }

    private function history(PurchaseOrder $purchaseOrder, ?PurchaseOrderStatus $from, PurchaseOrderStatus $to, User $actor, ?string $notes = null): void
    {
        DocumentStatusHistory::query()->create([
            'document_type' => 'purchase_order',
            'document_id' => $purchaseOrder->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'actor_user_id' => $actor->id,
            'notes' => $notes,
        ]);
    }
}
