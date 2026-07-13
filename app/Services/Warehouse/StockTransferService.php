<?php

namespace App\Services\Warehouse;

use App\Enums\RestockRequestStatus;
use App\Enums\StockTransferStatus;
use App\Exceptions\ServiceException;
use App\Models\DocumentStatusHistory;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\RestockRequest;
use App\Models\RestockRequestItem;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\StockTransferReceipt;
use App\Models\User;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use App\Services\Inventory\InventoryService;
use App\Services\Organization\DocumentNumberService;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

class StockTransferService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly InventoryService $inventory,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): StockTransfer
    {
        return DB::transaction(function () use ($data, $actor): StockTransfer {
            $source = WorkLocation::query()->findOrFail($data['source_work_location_id']);
            $destination = WorkLocation::query()->findOrFail($data['destination_work_location_id']);

            if ((int) $source->id === (int) $destination->id && (int) ($data['source_warehouse_location_id'] ?? 0) === (int) ($data['destination_warehouse_location_id'] ?? 0)) {
                throw ServiceException::validation('Sumber dan tujuan transfer tidak boleh sama.');
            }

            $transfer = StockTransfer::query()->create([
                'number' => $this->numbers->next('transfer', $source),
                'restock_request_id' => $data['restock_request_id'] ?? null,
                'source_work_location_id' => $source->id,
                'source_warehouse_location_id' => $data['source_warehouse_location_id'] ?? null,
                'destination_work_location_id' => $destination->id,
                'destination_warehouse_location_id' => $data['destination_warehouse_location_id'] ?? null,
                'requested_by' => $actor->id,
                'status' => StockTransferStatus::DRAFT,
                'transfer_date' => $data['transfer_date'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->replaceItems($transfer, $data['items'] ?? []);
            $this->history($transfer, null, StockTransferStatus::DRAFT, $actor, 'Transfer dibuat sebagai draft.');

            if (($data['action'] ?? null) === 'submit') {
                return $this->submit($transfer, $actor);
            }

            return $transfer->fresh(['items.product', 'sourceWorkLocation', 'destinationWorkLocation']);
        });
    }

    public function createFromRestockRequest(RestockRequest $request, User $actor): StockTransfer
    {
        return DB::transaction(function () use ($request, $actor): StockTransfer {
            $request = RestockRequest::query()->with(['items.product.baseUnit', 'branch.workLocation', 'sourceWarehouse.workLocation'])->lockForUpdate()->findOrFail($request->id);

            if ($request->status !== RestockRequestStatus::APPROVED) {
                throw ServiceException::validation('Request restock harus approved sebelum dibuat transfer.');
            }

            if (! $request->sourceWarehouse?->workLocation || ! $request->branch?->workLocation) {
                throw ServiceException::validation('Lokasi sumber/tujuan request belum lengkap.');
            }

            $items = $request->items
                ->filter(fn (RestockRequestItem $item): bool => Decimal::compare((string) $item->quantity_approved, '0') > 0)
                ->map(fn (RestockRequestItem $item): array => [
                    'restock_request_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'unit_id' => $item->product?->base_unit_id,
                    'quantity_requested' => $item->quantity_requested,
                    'quantity_approved' => $item->quantity_approved,
                ])
                ->values()
                ->all();

            if ($items === []) {
                throw ServiceException::validation('Tidak ada item approved untuk dibuat transfer.');
            }

            $transfer = $this->create([
                'restock_request_id' => $request->id,
                'source_work_location_id' => $request->sourceWarehouse->work_location_id,
                'destination_work_location_id' => $request->branch->work_location_id,
                'transfer_date' => now()->toDateString(),
                'notes' => "Dari request {$request->number}",
                'items' => $items,
                'action' => 'submit',
            ], $actor);

            $request->forceFill(['status' => RestockRequestStatus::CONVERTED])->save();

            return $transfer->fresh(['items.product', 'restockRequest', 'sourceWorkLocation', 'destinationWorkLocation']);
        });
    }

    public function submit(StockTransfer $transfer, User $actor): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $actor): StockTransfer {
            $transfer = StockTransfer::query()->with('items')->lockForUpdate()->findOrFail($transfer->id);

            if ($transfer->status !== StockTransferStatus::DRAFT) {
                throw ServiceException::validation('Hanya transfer draft yang dapat diajukan.');
            }

            $transfer->forceFill(['status' => StockTransferStatus::PENDING_APPROVAL, 'submitted_at' => now()])->save();
            $this->history($transfer, StockTransferStatus::DRAFT, StockTransferStatus::PENDING_APPROVAL, $actor, 'Transfer diajukan.');

            return $transfer->fresh(['items.product', 'sourceWorkLocation', 'destinationWorkLocation']);
        });
    }

    /** @param array<int, string|int|float> $approvedQuantities */
    public function approve(StockTransfer $transfer, User $actor, array $approvedQuantities = []): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $actor, $approvedQuantities): StockTransfer {
            $transfer = StockTransfer::query()->with(['items.product', 'sourceWorkLocation'])->lockForUpdate()->findOrFail($transfer->id);

            if ($transfer->status !== StockTransferStatus::PENDING_APPROVAL) {
                throw ServiceException::validation('Transfer belum menunggu approval.');
            }

            foreach ($transfer->items as $item) {
                $approved = Decimal::normalize($approvedQuantities[$item->id] ?? $item->quantity_approved);

                if (Decimal::compare($approved, (string) $item->quantity_requested) > 0) {
                    throw ServiceException::validation("Qty approved produk {$item->product_sku_snapshot} tidak boleh melebihi request.");
                }

                if (Decimal::compare($approved, '0') <= 0) {
                    continue;
                }

                $sourceBin = $this->sourceBin($transfer, $item);
                $this->inventory->reserve(
                    product: $item->product,
                    workLocation: $transfer->sourceWorkLocation,
                    warehouseLocation: $sourceBin,
                    quantity: $approved,
                    actor: $actor,
                    reference: $this->reference($transfer),
                    reason: 'Reserve stok untuk transfer.',
                    idempotencyKey: "stock-transfer-{$transfer->id}-item-{$item->id}-reserve",
                    metadata: ['stock_transfer_item_id' => $item->id],
                );

                $item->forceFill(['quantity_approved' => $approved, 'quantity_reserved' => $approved])->save();
            }

            $transfer->forceFill(['status' => StockTransferStatus::APPROVED, 'approved_by' => $actor->id, 'approved_at' => now()])->save();
            $this->history($transfer, StockTransferStatus::PENDING_APPROVAL, StockTransferStatus::APPROVED, $actor, 'Transfer disetujui dan stok sumber di-reserve.');

            return $transfer->fresh(['items.product', 'sourceWorkLocation', 'destinationWorkLocation']);
        });
    }

    /** @param array<string, mixed> $data */
    public function pack(StockTransfer $transfer, array $data, User $actor): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $data, $actor): StockTransfer {
            $transfer = StockTransfer::query()->with('items')->lockForUpdate()->findOrFail($transfer->id);

            if (! in_array($transfer->status, [StockTransferStatus::APPROVED, StockTransferStatus::PACKING], true)) {
                throw ServiceException::validation('Transfer belum bisa dipacking.');
            }

            foreach ($transfer->items as $item) {
                $picked = Decimal::normalize($data['items'][$item->id]['quantity_picked'] ?? $item->quantity_approved);
                if (Decimal::compare($picked, (string) $item->quantity_approved) > 0) {
                    throw ServiceException::validation("Qty picked produk {$item->product_sku_snapshot} tidak boleh melebihi approved.");
                }

                $item->forceFill([
                    'quantity_picked' => $picked,
                    'quantity_short' => Decimal::sub((string) $item->quantity_approved, $picked),
                    'notes' => $data['items'][$item->id]['notes'] ?? $item->notes,
                ])->save();
            }

            if (filled($data['package_no'] ?? null)) {
                $transfer->packages()->updateOrCreate(
                    ['package_no' => $data['package_no']],
                    ['checker_user_id' => $actor->id, 'photo_path' => $data['photo_path'] ?? null, 'notes' => $data['package_notes'] ?? null],
                );
            }

            $from = $transfer->status;
            $transfer->forceFill(['status' => StockTransferStatus::PACKING, 'picker_by' => $actor->id, 'packing_started_at' => $transfer->packing_started_at ?? now()])->save();
            if ($from !== StockTransferStatus::PACKING) {
                $this->history($transfer, $from, StockTransferStatus::PACKING, $actor, 'Picking dan packing dimulai.');
            }

            return $transfer->fresh(['items.product', 'packages', 'sourceWorkLocation', 'destinationWorkLocation']);
        });
    }

    /** @param array<string, mixed> $data */
    public function ship(StockTransfer $transfer, array $data, User $actor): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $data, $actor): StockTransfer {
            $transfer = StockTransfer::query()->with(['items.product', 'sourceWorkLocation', 'destinationWorkLocation'])->lockForUpdate()->findOrFail($transfer->id);

            if (! in_array($transfer->status, [StockTransferStatus::APPROVED, StockTransferStatus::PACKING], true)) {
                throw ServiceException::validation('Transfer belum siap dikirim.');
            }

            $hasShipment = false;
            foreach ($transfer->items as $item) {
                $shipped = Decimal::compare((string) $item->quantity_picked, '0') > 0 ? (string) $item->quantity_picked : (string) $item->quantity_approved;

                if (Decimal::compare($shipped, (string) $item->quantity_approved) > 0) {
                    throw ServiceException::validation("Qty shipped produk {$item->product_sku_snapshot} tidak boleh melebihi approved.");
                }

                if (Decimal::compare((string) $item->quantity_reserved, '0') > 0) {
                    $this->inventory->releaseReservation($item->product, $transfer->sourceWorkLocation, $this->sourceBin($transfer, $item), (string) $item->quantity_reserved, $actor, $this->reference($transfer), 'Lepas reserve saat transfer dikirim.', "stock-transfer-{$transfer->id}-item-{$item->id}-release-before-ship", ['stock_transfer_item_id' => $item->id]);
                }

                if (Decimal::compare($shipped, '0') > 0) {
                    $hasShipment = true;
                    $this->inventory->transferOut(
                        product: $item->product,
                        sourceWorkLocation: $transfer->sourceWorkLocation,
                        sourceWarehouseLocation: $this->sourceBin($transfer, $item),
                        quantity: $shipped,
                        actor: $actor,
                        reference: $this->reference($transfer),
                        reason: 'Transfer stok dikirim dari sumber.',
                        idempotencyKey: "stock-transfer-{$transfer->id}-item-{$item->id}-ship",
                        metadata: [
                            'stock_transfer_item_id' => $item->id,
                            'destination_work_location' => $transfer->destinationWorkLocation,
                            'destination_warehouse_location' => $this->destinationBin($transfer, $item),
                        ],
                    );
                }

                $item->forceFill(['quantity_reserved' => '0.0000', 'quantity_shipped' => $shipped])->save();
            }

            if (! $hasShipment) {
                throw ServiceException::validation('Minimal satu item harus dikirim.');
            }

            $transfer->forceFill([
                'status' => StockTransferStatus::SHIPPED,
                'shipper_by' => $actor->id,
                'shipped_at' => now(),
                'carrier' => $data['carrier'] ?? null,
                'vehicle_number' => $data['vehicle_number'] ?? null,
                'tracking_number' => $data['tracking_number'] ?? null,
                'shipping_cost' => Decimal::normalize($data['shipping_cost'] ?? 0, 2),
                'proof_path' => $data['proof_path'] ?? $transfer->proof_path,
            ])->save();
            $this->history($transfer, StockTransferStatus::PACKING, StockTransferStatus::SHIPPED, $actor, 'Transfer dikirim.');

            return $transfer->fresh(['items.product', 'sourceWorkLocation', 'destinationWorkLocation']);
        });
    }

    /** @param array<string, mixed> $data */
    public function receive(StockTransfer $transfer, array $data, User $actor): StockTransfer
    {
        if (filled($data['idempotency_key'] ?? null) && StockTransferReceipt::query()->where('idempotency_key', $data['idempotency_key'])->exists()) {
            return $transfer->fresh(['items.product', 'receipts.items']);
        }

        return DB::transaction(function () use ($transfer, $data, $actor): StockTransfer {
            $transfer = StockTransfer::query()->with(['items.product', 'destinationWorkLocation', 'sourceWorkLocation'])->lockForUpdate()->findOrFail($transfer->id);

            if (! $transfer->status->canReceive()) {
                throw ServiceException::validation('Transfer belum bisa diterima.');
            }

            $receipt = $transfer->receipts()->create([
                'received_by' => $actor->id,
                'received_at' => $data['received_at'] ?? now(),
                'proof_path' => $data['proof_path'] ?? null,
                'notes' => $data['notes'] ?? null,
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            foreach ($transfer->items as $item) {
                $payload = $data['items'][$item->id] ?? [];
                $received = Decimal::normalize($payload['quantity_received'] ?? 0);
                $damaged = Decimal::normalize($payload['quantity_damaged'] ?? 0);
                $discrepancy = Decimal::normalize($payload['quantity_discrepancy'] ?? 0);
                $newTotal = Decimal::add(Decimal::add(Decimal::add((string) $item->quantity_received, (string) $item->quantity_damaged), (string) $item->quantity_discrepancy), Decimal::add(Decimal::add($received, $damaged), $discrepancy));

                if (Decimal::compare($newTotal, (string) $item->quantity_shipped) > 0) {
                    throw ServiceException::validation("Qty terima produk {$item->product_sku_snapshot} melebihi qty dikirim.");
                }

                if (Decimal::compare($received, '0') > 0) {
                    $this->inventory->transferIn($item->product, $transfer->destinationWorkLocation, $this->destinationBin($transfer, $item), $received, $actor, $this->reference($transfer), 'Transfer diterima di tujuan.', "stock-transfer-{$transfer->id}-item-{$item->id}-receipt-{$receipt->id}-in", ['stock_transfer_item_id' => $item->id, 'stock_transfer_receipt_id' => $receipt->id, 'source_work_location' => $transfer->sourceWorkLocation, 'source_warehouse_location' => $this->sourceBin($transfer, $item)]);
                }

                if (Decimal::compare($damaged, '0') > 0) {
                    $this->inventory->transferIn($item->product, $transfer->destinationWorkLocation, $this->destinationBin($transfer, $item), $damaged, $actor, $this->reference($transfer), 'Transfer diterima dalam kondisi rusak.', "stock-transfer-{$transfer->id}-item-{$item->id}-receipt-{$receipt->id}-damaged-in", ['stock_transfer_item_id' => $item->id, 'stock_transfer_receipt_id' => $receipt->id]);
                    $this->inventory->damage($item->product, $transfer->destinationWorkLocation, $this->destinationBin($transfer, $item), $damaged, $actor, $this->reference($transfer), 'Barang transfer rusak saat diterima.', "stock-transfer-{$transfer->id}-item-{$item->id}-receipt-{$receipt->id}-damaged-qc", ['stock_transfer_item_id' => $item->id, 'stock_transfer_receipt_id' => $receipt->id]);
                }

                $receipt->items()->create([
                    'stock_transfer_item_id' => $item->id,
                    'quantity_received' => $received,
                    'quantity_damaged' => $damaged,
                    'quantity_discrepancy' => $discrepancy,
                    'notes' => $payload['notes'] ?? null,
                ]);

                $item->forceFill([
                    'quantity_received' => Decimal::add((string) $item->quantity_received, $received),
                    'quantity_damaged' => Decimal::add((string) $item->quantity_damaged, $damaged),
                    'quantity_discrepancy' => Decimal::add((string) $item->quantity_discrepancy, $discrepancy),
                ])->save();
            }

            $transfer->refresh()->load('items');
            $status = $this->allShippedAccounted($transfer) ? StockTransferStatus::FULLY_RECEIVED : StockTransferStatus::PARTIALLY_RECEIVED;
            $transfer->forceFill(['status' => $status, 'receiver_by' => $actor->id, 'received_at' => now()])->save();
            $this->history($transfer, StockTransferStatus::SHIPPED, $status, $actor, $status === StockTransferStatus::FULLY_RECEIVED ? 'Transfer diterima penuh.' : 'Transfer diterima sebagian.');

            return $transfer->fresh(['items.product', 'receipts.items', 'stockMutations']);
        });
    }

    public function complete(StockTransfer $transfer, User $actor): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $actor): StockTransfer {
            $transfer = StockTransfer::query()->with('items')->lockForUpdate()->findOrFail($transfer->id);

            if ($transfer->status !== StockTransferStatus::FULLY_RECEIVED) {
                throw ServiceException::validation('Transfer hanya dapat diselesaikan setelah diterima penuh.');
            }

            $transfer->forceFill(['status' => StockTransferStatus::COMPLETED, 'completed_at' => now()])->save();
            $this->history($transfer, StockTransferStatus::FULLY_RECEIVED, StockTransferStatus::COMPLETED, $actor, 'Transfer diselesaikan.');

            return $transfer->fresh(['items.product', 'sourceWorkLocation', 'destinationWorkLocation']);
        });
    }

    public function cancel(StockTransfer $transfer, User $actor, string $reason): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $actor, $reason): StockTransfer {
            $transfer = StockTransfer::query()->with(['items.product', 'sourceWorkLocation'])->lockForUpdate()->findOrFail($transfer->id);

            if (! $transfer->status->canCancel()) {
                throw ServiceException::validation('Transfer yang sudah dikirim tidak dapat dibatalkan.');
            }

            foreach ($transfer->items as $item) {
                if (Decimal::compare((string) $item->quantity_reserved, '0') > 0) {
                    $this->inventory->releaseReservation($item->product, $transfer->sourceWorkLocation, $this->sourceBin($transfer, $item), (string) $item->quantity_reserved, $actor, $this->reference($transfer), 'Batal transfer, reserve dilepas.', "stock-transfer-{$transfer->id}-item-{$item->id}-cancel-release", ['stock_transfer_item_id' => $item->id]);
                    $item->forceFill(['quantity_reserved' => '0.0000'])->save();
                }
            }

            $from = $transfer->status;
            $transfer->forceFill(['status' => StockTransferStatus::CANCELLED, 'cancelled_at' => now(), 'cancel_reason' => $reason])->save();
            $this->history($transfer, $from, StockTransferStatus::CANCELLED, $actor, $reason);

            return $transfer->fresh(['items.product']);
        });
    }

    /** @param list<array<string, mixed>> $items */
    private function replaceItems(StockTransfer $transfer, array $items): void
    {
        if ($items === []) {
            throw ServiceException::validation('Minimal satu item transfer wajib diisi.');
        }

        $transfer->items()->delete();

        foreach ($items as $itemData) {
            $product = Product::query()->with('baseUnit')->findOrFail($itemData['product_id']);
            $unitId = $itemData['unit_id'] ?? $product->base_unit_id;
            $productUnit = ProductUnit::query()->where('product_id', $product->id)->where('unit_id', $unitId)->first();
            $conversion = $productUnit instanceof ProductUnit ? $productUnit->conversion_factor : '1.000000';
            $requested = Decimal::normalize($itemData['quantity_requested'] ?? $itemData['quantity_approved'] ?? 0);
            $approved = Decimal::normalize($itemData['quantity_approved'] ?? $requested);

            if (Decimal::compare($requested, '0') <= 0) {
                throw ServiceException::validation("Qty produk {$product->sku} harus lebih dari nol.");
            }

            $transfer->items()->create([
                'restock_request_item_id' => $itemData['restock_request_item_id'] ?? null,
                'product_id' => $product->id,
                'unit_id' => $unitId,
                'source_warehouse_location_id' => $itemData['source_warehouse_location_id'] ?? $transfer->source_warehouse_location_id,
                'destination_warehouse_location_id' => $itemData['destination_warehouse_location_id'] ?? $transfer->destination_warehouse_location_id,
                'product_sku_snapshot' => $product->sku,
                'product_name_snapshot' => $product->name,
                'unit_name_snapshot' => $product->baseUnit?->name,
                'conversion_factor_snapshot' => $conversion,
                'quantity_requested' => $requested,
                'quantity_approved' => $approved,
                'notes' => $itemData['notes'] ?? null,
            ]);
        }
    }

    /** @return array{type: string, id: int, no: string} */
    private function reference(StockTransfer $transfer): array
    {
        return ['type' => 'stock_transfer', 'id' => $transfer->id, 'no' => $transfer->number];
    }

    private function sourceBin(StockTransfer $transfer, StockTransferItem $item): ?WarehouseLocation
    {
        $id = $item->source_warehouse_location_id ?? $transfer->source_warehouse_location_id;

        return $id ? WarehouseLocation::query()->with('warehouse')->findOrFail($id) : null;
    }

    private function destinationBin(StockTransfer $transfer, StockTransferItem $item): ?WarehouseLocation
    {
        $id = $item->destination_warehouse_location_id ?? $transfer->destination_warehouse_location_id;

        return $id ? WarehouseLocation::query()->with('warehouse')->findOrFail($id) : null;
    }

    private function allShippedAccounted(StockTransfer $transfer): bool
    {
        return $transfer->items->every(fn (StockTransferItem $item): bool => Decimal::compare($item->inTransitQuantity(), '0') === 0);
    }

    private function history(StockTransfer $transfer, ?StockTransferStatus $from, StockTransferStatus $to, User $actor, ?string $notes = null): void
    {
        DocumentStatusHistory::query()->create([
            'document_type' => 'stock_transfer',
            'document_id' => $transfer->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'actor_user_id' => $actor->id,
            'notes' => $notes,
        ]);
    }
}
