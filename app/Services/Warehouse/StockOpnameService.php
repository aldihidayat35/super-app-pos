<?php

namespace App\Services\Warehouse;

use App\Enums\StockOpnameReason;
use App\Enums\StockOpnameStatus;
use App\Exceptions\ServiceException;
use App\Models\DocumentStatusHistory;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMutation;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\User;
use App\Models\WorkLocation;
use App\Services\Inventory\InventoryService;
use App\Services\Organization\DocumentNumberService;
use App\Support\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StockOpnameService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly InventoryService $inventory,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): StockOpname
    {
        return DB::transaction(function () use ($data, $actor): StockOpname {
            $workLocation = WorkLocation::query()->findOrFail($data['work_location_id']);
            $opname = StockOpname::query()->create([
                'number' => $this->numbers->next('opname', $workLocation),
                'work_location_id' => $workLocation->id,
                'warehouse_location_id' => $data['warehouse_location_id'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'pic_user_id' => $data['pic_user_id'] ?? $actor->id,
                'created_by' => $actor->id,
                'status' => StockOpnameStatus::DRAFT,
                'method' => $data['method'] ?? 'manual',
                'freeze_stock' => (bool) ($data['freeze_stock'] ?? false),
                'blind_count' => (bool) ($data['blind_count'] ?? false),
                'scheduled_at' => $data['scheduled_at'] ?? now()->toDateString(),
                'threshold_qty' => Decimal::normalize($data['threshold_qty'] ?? 10),
                'threshold_value' => Decimal::normalize($data['threshold_value'] ?? 1000000, 2),
                'notes' => $data['notes'] ?? null,
            ]);
            $this->history($opname, null, StockOpnameStatus::DRAFT, $actor, 'Jadwal opname dibuat.');

            if (($data['action'] ?? null) === 'start') {
                return $this->start($opname, $actor);
            }

            return $opname->fresh(['workLocation', 'warehouseLocation', 'items']);
        });
    }

    public function start(StockOpname $opname, User $actor): StockOpname
    {
        return DB::transaction(function () use ($opname, $actor): StockOpname {
            $opname = StockOpname::query()->lockForUpdate()->findOrFail($opname->id);

            if ($opname->status !== StockOpnameStatus::DRAFT) {
                throw ServiceException::validation('Hanya opname draft yang dapat dimulai.');
            }

            $stocks = $this->snapshotQuery($opname)->lockForUpdate()->get();

            if ($stocks->isEmpty()) {
                throw ServiceException::validation('Tidak ada saldo stok untuk scope opname ini.');
            }

            $opname->items()->delete();
            foreach ($stocks as $stock) {
                $product = $stock->product;
                if (! $product instanceof Product) {
                    continue;
                }

                $opname->items()->create([
                    'stock_id' => $stock->id,
                    'product_id' => $product->id,
                    'warehouse_location_id' => $stock->warehouse_location_id,
                    'product_sku_snapshot' => $product->sku,
                    'product_name_snapshot' => $product->name,
                    'system_qty_snapshot' => $stock->quantity_on_hand,
                    'difference_qty' => '0.0000',
                    'unit_cost' => $product->cost_price,
                    'estimated_value' => '0.00',
                ]);
            }

            $opname->forceFill(['status' => StockOpnameStatus::COUNTING, 'started_at' => now()])->save();
            $this->history($opname, StockOpnameStatus::DRAFT, StockOpnameStatus::COUNTING, $actor, 'Snapshot stok dibuat dan counting dimulai.');

            return $opname->fresh(['items.product', 'workLocation']);
        });
    }

    /** @param array<string, mixed> $data */
    public function countItem(StockOpnameItem $item, array $data, User $actor): StockOpnameItem
    {
        return DB::transaction(function () use ($item, $data, $actor): StockOpnameItem {
            $item = StockOpnameItem::query()->with('stockOpname')->lockForUpdate()->findOrFail($item->id);
            $opname = $item->stockOpname;

            if (! $opname instanceof StockOpname || ! $opname->status->canCount()) {
                throw ServiceException::validation('Opname tidak dalam status counting.');
            }

            $lockedAt = $item->getAttribute('locked_at');
            if ($item->locked_by !== null && (int) $item->locked_by !== (int) $actor->id && $lockedAt instanceof Carbon && $lockedAt->gt(now()->subMinutes(30))) {
                throw ServiceException::validation('Item sedang dihitung oleh counter lain.');
            }

            $counted = Decimal::normalize($data['counted_qty']);
            if (Decimal::compare($counted, '0') < 0) {
                throw ServiceException::validation('Qty fisik tidak boleh negatif.');
            }

            $difference = Decimal::sub($counted, (string) $item->system_qty_snapshot);
            $estimatedValue = Decimal::mul($this->absDecimal($difference), (string) $item->unit_cost);
            $reason = $data['reason'] ?? null;

            $item->counts()->create([
                'counter_user_id' => $actor->id,
                'counted_qty' => $counted,
                'reason' => $reason,
                'note' => $data['note'] ?? null,
                'evidence_path' => $data['evidence_path'] ?? null,
                'counted_at' => now(),
            ]);

            $item->forceFill([
                'counter_user_id' => $actor->id,
                'locked_by' => $actor->id,
                'locked_at' => now(),
                'counted_qty' => $counted,
                'difference_qty' => $difference,
                'estimated_value' => $estimatedValue,
                'reason' => $reason,
                'note' => $data['note'] ?? null,
                'evidence_path' => $data['evidence_path'] ?? $item->evidence_path,
                'counted_at' => now(),
            ])->save();

            $this->refreshTransactionWarning($item);
            $opname->recalculateTotals();

            return $item->fresh(['counts.counter', 'product']);
        });
    }

    /** @param list<array<string, mixed>> $rows */
    public function importCounts(StockOpname $opname, array $rows, User $actor): StockOpname
    {
        return DB::transaction(function () use ($opname, $rows, $actor): StockOpname {
            $opname = StockOpname::query()->with('items')->lockForUpdate()->findOrFail($opname->id);

            foreach ($rows as $index => $row) {
                $sku = trim((string) ($row['sku'] ?? ''));
                $qty = $row['counted_qty'] ?? null;
                if ($sku === '' || $qty === null || ! is_numeric($qty)) {
                    throw ServiceException::validation('Import invalid pada baris '.($index + 1).'.');
                }

                $item = $opname->items->firstWhere('product_sku_snapshot', $sku);
                if (! $item instanceof StockOpnameItem) {
                    throw ServiceException::validation("SKU {$sku} tidak ada dalam scope opname.");
                }

                $this->countItem($item, [
                    'counted_qty' => $qty,
                    'reason' => $row['reason'] ?? StockOpnameReason::OTHER->value,
                    'note' => $row['note'] ?? 'Import count',
                ], $actor);
            }

            return $opname->fresh(['items.product']);
        });
    }

    public function submit(StockOpname $opname, User $actor): StockOpname
    {
        return DB::transaction(function () use ($opname, $actor): StockOpname {
            $opname = StockOpname::query()->with('items')->lockForUpdate()->findOrFail($opname->id);

            if ($opname->status !== StockOpnameStatus::COUNTING) {
                throw ServiceException::validation('Hanya opname counting yang dapat diajukan.');
            }

            if ($opname->items->contains(fn (StockOpnameItem $item): bool => $item->counted_qty === null)) {
                throw ServiceException::validation('Semua item wajib dihitung sebelum submit.');
            }

            foreach ($opname->items as $item) {
                $this->refreshTransactionWarning($item);
            }

            $requiresOwner = $opname->items->contains(function (StockOpnameItem $item) use ($opname): bool {
                return Decimal::compare($this->absDecimal((string) $item->difference_qty), (string) $opname->threshold_qty) > 0
                    || Decimal::compare((string) $item->estimated_value, (string) $opname->threshold_value, 2) > 0;
            });

            $opname->recalculateTotals();
            $opname->forceFill([
                'status' => StockOpnameStatus::PENDING_APPROVAL,
                'submitted_at' => now(),
                'requires_owner_approval' => $requiresOwner,
            ])->save();
            $this->history($opname, StockOpnameStatus::COUNTING, StockOpnameStatus::PENDING_APPROVAL, $actor, 'Opname diajukan untuk approval.');

            return $opname->fresh(['items.product', 'approvals']);
        });
    }

    public function approve(StockOpname $opname, User $actor, string $notes): StockOpname
    {
        return DB::transaction(function () use ($opname, $actor, $notes): StockOpname {
            $opname = StockOpname::query()->lockForUpdate()->findOrFail($opname->id);

            if ($opname->status !== StockOpnameStatus::PENDING_APPROVAL) {
                throw ServiceException::validation('Opname belum menunggu approval.');
            }

            if ($opname->requires_owner_approval && ! $actor->hasAnyRole(['owner_approver', 'super_admin'])) {
                throw ServiceException::validation('Selisih melewati threshold dan membutuhkan approval owner.');
            }

            $opname->approvals()->create([
                'approver_user_id' => $actor->id,
                'approval_level' => $opname->requires_owner_approval ? 'owner' : 'warehouse_head',
                'status' => 'approved',
                'notes' => $notes,
                'approved_at' => now(),
            ]);

            $opname->forceFill(['status' => StockOpnameStatus::APPROVED, 'approved_by' => $actor->id, 'approved_at' => now()])->save();
            $this->history($opname, StockOpnameStatus::PENDING_APPROVAL, StockOpnameStatus::APPROVED, $actor, $notes);

            return $opname->fresh(['items.product', 'approvals']);
        });
    }

    public function reject(StockOpname $opname, User $actor, string $reason): StockOpname
    {
        return DB::transaction(function () use ($opname, $actor, $reason): StockOpname {
            $opname = StockOpname::query()->lockForUpdate()->findOrFail($opname->id);

            if ($opname->status !== StockOpnameStatus::PENDING_APPROVAL) {
                throw ServiceException::validation('Opname belum menunggu approval.');
            }

            $opname->approvals()->create([
                'approver_user_id' => $actor->id,
                'approval_level' => $opname->requires_owner_approval ? 'owner' : 'warehouse_head',
                'status' => 'rejected',
                'notes' => $reason,
                'approved_at' => now(),
            ]);

            $opname->forceFill(['status' => StockOpnameStatus::REJECTED, 'rejected_by' => $actor->id, 'rejected_at' => now(), 'reject_reason' => $reason])->save();
            $this->history($opname, StockOpnameStatus::PENDING_APPROVAL, StockOpnameStatus::REJECTED, $actor, $reason);

            return $opname->fresh(['items.product', 'approvals']);
        });
    }

    public function complete(StockOpname $opname, User $actor): StockOpname
    {
        return DB::transaction(function () use ($opname, $actor): StockOpname {
            $opname = StockOpname::query()->with(['items.product', 'workLocation'])->lockForUpdate()->findOrFail($opname->id);

            if ($opname->status === StockOpnameStatus::COMPLETED) {
                return $opname;
            }

            if ($opname->status !== StockOpnameStatus::APPROVED) {
                throw ServiceException::validation('Opname harus approved sebelum diselesaikan.');
            }

            foreach ($opname->items as $item) {
                if ($item->counted_qty === null || Decimal::compare((string) $item->difference_qty, '0') === 0) {
                    continue;
                }

                $currentStock = $item->stock?->fresh();
                if ($currentStock instanceof Stock && Decimal::compare((string) $currentStock->quantity_on_hand, (string) $item->counted_qty) === 0) {
                    continue;
                }

                $this->inventory->adjust(
                    product: $item->product,
                    workLocation: $opname->workLocation,
                    warehouseLocation: $item->warehouseLocation,
                    targetOnHand: (string) $item->counted_qty,
                    actor: $actor,
                    reference: ['type' => 'stock_opname', 'id' => $opname->id, 'no' => $opname->number],
                    reason: 'Koreksi stok dari stock opname: '.($item->reasonEnum()?->label() ?? 'Tanpa alasan'),
                    idempotencyKey: "stock-opname-{$opname->id}-item-{$item->id}-adjust",
                    metadata: ['stock_opname_item_id' => $item->id, 'system_qty_snapshot' => $item->system_qty_snapshot],
                );
            }

            $opname->forceFill(['status' => StockOpnameStatus::COMPLETED, 'completed_at' => now()])->save();
            $this->history($opname, StockOpnameStatus::APPROVED, StockOpnameStatus::COMPLETED, $actor, 'Adjustment opname selesai dibuat.');

            return $opname->fresh(['items.product', 'stockMutations', 'approvals']);
        });
    }

    private function snapshotQuery(StockOpname $opname): mixed
    {
        return Stock::query()
            ->with('product')
            ->where('work_location_id', $opname->work_location_id)
            ->when($opname->warehouse_location_id !== null, fn ($query) => $query->where('warehouse_location_id', $opname->warehouse_location_id))
            ->when($opname->category_id !== null, fn ($query) => $query->whereHas('product', fn ($product) => $product->where('category_id', $opname->category_id)))
            ->orderBy('product_id')
            ->orderBy('warehouse_location_id');
    }

    private function refreshTransactionWarning(StockOpnameItem $item): void
    {
        $opname = $item->stockOpname;
        if (! $opname instanceof StockOpname || $opname->started_at === null) {
            return;
        }

        $hasMutation = StockMutation::query()
            ->where('product_id', $item->product_id)
            ->where('work_location_id', $opname->work_location_id)
            ->when(
                $item->warehouse_location_id === null,
                fn ($query) => $query->whereNull('warehouse_location_id'),
                fn ($query) => $query->where('warehouse_location_id', $item->warehouse_location_id),
            )
            ->where('occurred_at', '>', $opname->started_at)
            ->where(function ($query) use ($opname): void {
                $query->where('reference_type', '!=', 'stock_opname')
                    ->orWhereNull('reference_type')
                    ->orWhere('reference_id', '!=', $opname->id);
            })
            ->exists();

        if ($item->has_transaction_after_snapshot !== $hasMutation) {
            $item->forceFill(['has_transaction_after_snapshot' => $hasMutation])->save();
        }
    }

    private function absDecimal(string $value): string
    {
        return str_starts_with($value, '-') ? substr($value, 1) : $value;
    }

    private function history(StockOpname $opname, ?StockOpnameStatus $from, StockOpnameStatus $to, User $actor, ?string $notes = null): void
    {
        DocumentStatusHistory::query()->create([
            'document_type' => 'stock_opname',
            'document_id' => $opname->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'actor_user_id' => $actor->id,
            'notes' => $notes,
        ]);
    }
}
