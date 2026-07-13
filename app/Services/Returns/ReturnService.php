<?php

namespace App\Services\Returns;

use App\Enums\InventoryLossStatus;
use App\Enums\ReturnResolution;
use App\Enums\ReturnStatus;
use App\Exceptions\ServiceException;
use App\Models\DocumentStatusHistory;
use App\Models\InventoryLoss;
use App\Models\Product;
use App\Models\ReturnDocument;
use App\Models\ReturnItem;
use App\Models\User;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use App\Services\Inventory\InventoryService;
use App\Services\Organization\DocumentNumberService;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

class ReturnService
{
    private const APPROVAL_THRESHOLD = '1000000.00';

    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly InventoryService $inventory,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): ReturnDocument
    {
        if (($data['idempotency_key'] ?? null) !== null) {
            $existing = ReturnDocument::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing instanceof ReturnDocument) {
                return $existing->load('items');
            }
        }

        return DB::transaction(function () use ($data, $actor): ReturnDocument {
            $workLocation = WorkLocation::query()->lockForUpdate()->findOrFail($data['work_location_id']);
            $status = ($data['action'] ?? 'submit') === 'draft' ? ReturnStatus::DRAFT : ReturnStatus::SUBMITTED;
            $return = ReturnDocument::query()->create([
                'number' => $this->numbers->next('return', $workLocation),
                'work_location_id' => $workLocation->id,
                'source_type' => $data['source_type'],
                'source_id' => $data['source_id'] ?? null,
                'source_name' => $data['source_name'] ?? null,
                'destination_type' => $data['destination_type'] ?? null,
                'destination_id' => $data['destination_id'] ?? null,
                'destination_name' => $data['destination_name'] ?? null,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'reference_no' => $data['reference_no'] ?? null,
                'reason' => $data['reason'],
                'requested_resolution' => $data['requested_resolution'],
                'status' => $status,
                'requested_by' => $actor->id,
                'return_date' => $data['return_date'] ?? now()->toDateString(),
                'submitted_at' => $status === ReturnStatus::SUBMITTED ? now() : null,
                'evidence_path' => $data['evidence_path'] ?? null,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ((array) $data['items'] as $itemData) {
                $this->createItem($return, $itemData);
            }

            $return->recalculateTotals();
            $this->history($return, null, $status, $actor, 'Dokumen retur dibuat.');

            return $return->fresh(['items.product', 'workLocation']);
        });
    }

    /** @param array<string, mixed> $data */
    private function createItem(ReturnDocument $return, array $data): ReturnItem
    {
        $product = Product::query()->with('baseUnit')->findOrFail($data['product_id']);
        $quantity = Decimal::normalize($data['quantity_requested']);
        if (Decimal::compare($quantity, '0') <= 0) {
            throw ServiceException::validation('Qty retur harus lebih dari nol.');
        }

        $sourceQuantity = Decimal::normalize($data['source_quantity'] ?? $quantity);
        $alreadyReturned = $this->returnedQuantityForSource($data['source_item_type'] ?? null, $data['source_item_id'] ?? null);
        if (($data['source_item_id'] ?? null) !== null && Decimal::compare(Decimal::add($alreadyReturned, $quantity), $sourceQuantity) > 0) {
            throw ServiceException::validation('Qty retur melebihi qty dokumen asal.');
        }

        $unitCost = Decimal::normalize($data['unit_cost_snapshot'] ?? $product->cost_price, 2);
        $lineValue = Decimal::mul($quantity, $unitCost);

        return $return->items()->create([
            'product_id' => $product->id,
            'unit_id' => $data['unit_id'] ?? $product->base_unit_id,
            'warehouse_location_id' => $data['warehouse_location_id'] ?? null,
            'source_item_type' => $data['source_item_type'] ?? null,
            'source_item_id' => $data['source_item_id'] ?? null,
            'product_sku_snapshot' => $product->sku,
            'product_name_snapshot' => $product->name,
            'unit_name_snapshot' => $product->baseUnit?->name,
            'conversion_factor_snapshot' => $data['conversion_factor_snapshot'] ?? '1',
            'source_quantity' => $sourceQuantity,
            'quantity_requested' => $quantity,
            'unit_cost_snapshot' => $unitCost,
            'line_value' => $lineValue,
            'condition' => $data['condition'] ?? 'good',
            'reason' => $data['reason'] ?? null,
            'resolution' => $data['resolution'] ?? $return->requestedResolutionValue(),
            'notes' => $data['notes'] ?? null,
            'evidence_path' => $data['evidence_path'] ?? null,
        ]);
    }

    private function returnedQuantityForSource(?string $sourceItemType, mixed $sourceItemId): string
    {
        if ($sourceItemType === null || $sourceItemId === null) {
            return '0.0000';
        }

        return ReturnItem::query()
            ->where('source_item_type', $sourceItemType)
            ->where('source_item_id', $sourceItemId)
            ->whereHas('returnDocument', fn ($query) => $query->whereNotIn('status', [ReturnStatus::REJECTED->value, ReturnStatus::CANCELLED->value]))
            ->sum('quantity_requested') ?: '0.0000';
    }

    /** @param array<string, mixed> $data */
    public function inspect(ReturnDocument $return, array $data, User $actor): ReturnDocument
    {
        return DB::transaction(function () use ($return, $data, $actor): ReturnDocument {
            $return = ReturnDocument::query()->with(['items.product', 'workLocation'])->lockForUpdate()->findOrFail($return->id);
            if ($return->status !== ReturnStatus::SUBMITTED) {
                throw ServiceException::validation('Hanya retur submitted yang dapat diperiksa.');
            }

            foreach ((array) $data['items'] as $itemId => $payload) {
                $item = $return->items->firstWhere('id', (int) $itemId);
                if (! $item instanceof ReturnItem) {
                    throw ServiceException::validation('Item retur tidak valid.');
                }

                $this->inspectItem($return, $item, $payload, $actor);
            }

            $return->recalculateTotals();
            $requiresApproval = Decimal::compare((string) $return->fresh()->total_loss_value, self::APPROVAL_THRESHOLD, 2) > 0;
            $nextStatus = $requiresApproval ? ReturnStatus::PENDING_APPROVAL : ReturnStatus::INSPECTED;
            $return->forceFill([
                'status' => $nextStatus,
                'checker_user_id' => $actor->id,
                'inspected_at' => now(),
                'requires_approval' => $requiresApproval,
            ])->save();
            $this->history($return, ReturnStatus::SUBMITTED, $nextStatus, $actor, 'QC retur selesai.');

            return $return->fresh(['items.product', 'inspections', 'stockMutations']);
        });
    }

    /** @param array<string, mixed> $payload */
    private function inspectItem(ReturnDocument $return, ReturnItem $item, array $payload, User $actor): void
    {
        $good = Decimal::normalize($payload['quantity_good'] ?? 0);
        $damaged = Decimal::normalize($payload['quantity_damaged'] ?? 0);
        $rejected = Decimal::normalize($payload['quantity_rejected'] ?? 0);
        $total = Decimal::add(Decimal::add($good, $damaged), $rejected);
        if (Decimal::compare($total, (string) $item->quantity_requested) !== 0) {
            throw ServiceException::validation('Total QC harus sama dengan qty retur.');
        }

        $lossValue = Decimal::mul($damaged, (string) $item->unit_cost_snapshot);
        $warehouseLocation = ($payload['warehouse_location_id'] ?? null) !== null
            ? WarehouseLocation::query()->findOrFail($payload['warehouse_location_id'])
            : $item->warehouseLocation;

        if (Decimal::compare($good, '0') > 0) {
            $this->inventory->returnIn($item->product, $return->workLocation, $warehouseLocation, $good, $actor, ['type' => 'return', 'id' => $return->id, 'no' => $return->number], 'Retur barang layak jual', "return-{$return->id}-item-{$item->id}-good");
        }

        if (Decimal::compare($damaged, '0') > 0) {
            $this->inventory->returnIn($item->product, $return->workLocation, $warehouseLocation, $damaged, $actor, ['type' => 'return', 'id' => $return->id, 'no' => $return->number], 'Retur barang rusak masuk stok', "return-{$return->id}-item-{$item->id}-damaged-in");
            $this->inventory->damage($item->product, $return->workLocation, $warehouseLocation, $damaged, $actor, ['type' => 'return', 'id' => $return->id, 'no' => $return->number], 'QC retur barang rusak', "return-{$return->id}-item-{$item->id}-damaged");
        }

        $item->forceFill([
            'warehouse_location_id' => $warehouseLocation?->id,
            'quantity_accepted_good' => $good,
            'quantity_accepted_damaged' => $damaged,
            'quantity_rejected' => $rejected,
            'loss_value' => $lossValue,
            'condition' => $payload['condition'] ?? (Decimal::compare($damaged, '0') > 0 ? 'damaged' : 'good'),
            'resolution' => $payload['resolution'] ?? $item->resolution,
            'notes' => $payload['notes'] ?? $item->notes,
            'evidence_path' => $payload['evidence_path'] ?? $item->evidence_path,
        ])->save();

        $item->inspections()->create([
            'return_id' => $return->id,
            'checker_user_id' => $actor->id,
            'qc_result' => Decimal::compare($rejected, (string) $item->quantity_requested) === 0 ? 'rejected' : 'accepted',
            'condition' => $item->condition,
            'quantity_good' => $good,
            'quantity_damaged' => $damaged,
            'quantity_rejected' => $rejected,
            'loss_value' => $lossValue,
            'responsible_party' => $payload['responsible_party'] ?? null,
            'evidence_path' => $payload['evidence_path'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'inspected_at' => now(),
        ]);
    }

    public function approve(ReturnDocument $return, User $actor, ?string $notes = null): ReturnDocument
    {
        return DB::transaction(function () use ($return, $actor, $notes): ReturnDocument {
            $return = ReturnDocument::query()->lockForUpdate()->findOrFail($return->id);
            if ($return->status !== ReturnStatus::PENDING_APPROVAL) {
                throw ServiceException::validation('Retur belum menunggu approval.');
            }

            $return->forceFill(['status' => ReturnStatus::APPROVED, 'approved_by' => $actor->id, 'approved_at' => now()])->save();
            $this->history($return, ReturnStatus::PENDING_APPROVAL, ReturnStatus::APPROVED, $actor, $notes ?? 'Retur disetujui.');

            return $return->fresh(['items']);
        });
    }

    /** @param array<string, mixed> $data */
    public function settle(ReturnDocument $return, array $data, User $actor): ReturnDocument
    {
        return DB::transaction(function () use ($return, $data, $actor): ReturnDocument {
            $return = ReturnDocument::query()->with(['items.product', 'workLocation'])->lockForUpdate()->findOrFail($return->id);
            if (! in_array($return->status, [ReturnStatus::INSPECTED, ReturnStatus::APPROVED], true)) {
                throw ServiceException::validation('Retur harus sudah QC/approved sebelum settlement.');
            }

            $resolution = ReturnResolution::from($data['resolution']);
            if ($resolution === ReturnResolution::RETURN_TO_SUPPLIER) {
                foreach ($return->items as $item) {
                    $qty = Decimal::add((string) $item->quantity_accepted_good, (string) $item->quantity_accepted_damaged);
                    if (Decimal::compare((string) $item->quantity_accepted_damaged, '0') > 0) {
                        $this->inventory->recover($item->product, $return->workLocation, $item->warehouseLocation, $item->quantity_accepted_damaged, $actor, ['type' => 'return', 'id' => $return->id, 'no' => $return->number], 'Retur rusak dikirim ke supplier', "return-{$return->id}-item-{$item->id}-recover-supplier");
                    }
                    if (Decimal::compare($qty, '0') > 0) {
                        $this->inventory->returnOut($item->product, $return->workLocation, $item->warehouseLocation, $qty, $actor, ['type' => 'return', 'id' => $return->id, 'no' => $return->number], 'Retur barang ke supplier', "return-{$return->id}-item-{$item->id}-supplier-out");
                    }
                }
            }

            $return->settlements()->create([
                'settled_by' => $actor->id,
                'resolution' => $resolution,
                'document_no' => $data['document_no'] ?? null,
                'amount' => Decimal::normalize($data['amount'] ?? $return->total_value, 2),
                'notes' => $data['notes'] ?? null,
                'metadata' => ['reference_no' => $data['document_no'] ?? null],
                'settled_at' => now(),
            ]);

            $return->forceFill(['status' => ReturnStatus::SETTLED, 'settled_by' => $actor->id, 'settled_at' => now()])->save();
            $this->history($return, $return->requires_approval ? ReturnStatus::APPROVED : ReturnStatus::INSPECTED, ReturnStatus::SETTLED, $actor, 'Retur diselesaikan.');

            return $return->fresh(['items', 'settlements', 'stockMutations']);
        });
    }

    /** @param array<string, mixed> $data */
    public function createLoss(array $data, User $actor): InventoryLoss
    {
        return DB::transaction(function () use ($data, $actor): InventoryLoss {
            $workLocation = WorkLocation::query()->findOrFail($data['work_location_id']);
            $product = Product::query()->findOrFail($data['product_id']);
            $quantity = Decimal::normalize($data['quantity']);
            $unitCost = Decimal::normalize($data['unit_cost_snapshot'] ?? $product->cost_price, 2);
            $lossValue = Decimal::mul($quantity, $unitCost);
            $needsApproval = Decimal::compare($lossValue, self::APPROVAL_THRESHOLD, 2) > 0;

            $loss = InventoryLoss::query()->create([
                'number' => $this->numbers->next('loss', $workLocation),
                'work_location_id' => $workLocation->id,
                'warehouse_location_id' => $data['warehouse_location_id'] ?? null,
                'product_id' => $product->id,
                'reported_by' => $actor->id,
                'loss_type' => $data['loss_type'],
                'disposition' => $data['disposition'] ?? 'damage',
                'status' => $needsApproval ? InventoryLossStatus::PENDING_APPROVAL : InventoryLossStatus::APPROVED,
                'quantity' => $quantity,
                'unit_cost_snapshot' => $unitCost,
                'loss_value' => $lossValue,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'reference_no' => $data['reference_no'] ?? null,
                'evidence_path' => $data['evidence_path'] ?? null,
                'reason' => $data['reason'] ?? null,
                'reported_at' => now(),
                'approved_by' => $needsApproval ? null : $actor->id,
                'approved_at' => $needsApproval ? null : now(),
            ]);

            if (! $needsApproval) {
                $this->postLossMutation($loss, $actor);
            }

            return $loss->fresh(['product', 'workLocation']);
        });
    }

    public function approveLoss(InventoryLoss $loss, User $actor): InventoryLoss
    {
        return DB::transaction(function () use ($loss, $actor): InventoryLoss {
            $loss = InventoryLoss::query()->lockForUpdate()->findOrFail($loss->id);
            if ($loss->status !== InventoryLossStatus::PENDING_APPROVAL) {
                throw ServiceException::validation('Loss belum menunggu approval.');
            }

            $loss->forceFill(['status' => InventoryLossStatus::APPROVED, 'approved_by' => $actor->id, 'approved_at' => now()])->save();
            $this->postLossMutation($loss, $actor);

            return $loss->fresh(['product', 'workLocation']);
        });
    }

    private function postLossMutation(InventoryLoss $loss, User $actor): void
    {
        $warehouseLocation = $loss->warehouseLocation;
        $reference = ['type' => 'inventory_loss', 'id' => $loss->id, 'no' => $loss->number];
        $reason = 'Loss tracking: '.$loss->loss_type.' — '.$loss->reason;

        if ($loss->disposition === 'issue') {
            $this->inventory->issue($loss->product, $loss->workLocation, $warehouseLocation, $loss->quantity, $actor, $reference, $reason, "loss-{$loss->id}-issue");

            return;
        }

        $this->inventory->damage($loss->product, $loss->workLocation, $warehouseLocation, $loss->quantity, $actor, $reference, $reason, "loss-{$loss->id}-damage");
    }

    private function history(ReturnDocument $return, ?ReturnStatus $from, ReturnStatus $to, User $actor, ?string $notes = null): void
    {
        DocumentStatusHistory::query()->create([
            'document_type' => 'return',
            'document_id' => $return->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'actor_user_id' => $actor->id,
            'notes' => $notes,
        ]);
    }
}
