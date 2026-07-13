<?php

namespace App\Services\Inventory;

use App\Enums\StockMutationType;
use App\Enums\StockOpnameStatus;
use App\Exceptions\ServiceException;
use App\Models\InventoryIdempotencyKey;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMutation;
use App\Models\StockOpname;
use App\Models\User;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * @param  array<string, mixed>  $reference
     * @param  array<string, mixed>  $metadata
     */
    public function receive(Product $product, WorkLocation $workLocation, ?WarehouseLocation $warehouseLocation, string|int|float $quantity, ?User $actor = null, array $reference = [], ?string $reason = null, ?string $idempotencyKey = null, array $metadata = []): StockMutation
    {
        return $this->runSingleOperation('receive', $idempotencyKey, fn (): StockMutation => $this->mutate(
            product: $product,
            workLocation: $workLocation,
            warehouseLocation: $warehouseLocation,
            type: StockMutationType::RECEIVE,
            onHandChange: $quantity,
            reservedChange: '0',
            damagedChange: '0',
            actor: $actor,
            reference: $reference,
            reason: $reason,
            idempotencyKey: $idempotencyKey,
            metadata: $metadata,
        ));
    }

    /**
     * @param  array<string, mixed>  $reference
     * @param  array<string, mixed>  $metadata
     */
    public function issue(Product $product, WorkLocation $workLocation, ?WarehouseLocation $warehouseLocation, string|int|float $quantity, ?User $actor = null, array $reference = [], ?string $reason = null, ?string $idempotencyKey = null, array $metadata = []): StockMutation
    {
        $quantity = Decimal::normalize($quantity);

        return $this->runSingleOperation('issue', $idempotencyKey, fn (): StockMutation => $this->mutate(
            product: $product,
            workLocation: $workLocation,
            warehouseLocation: $warehouseLocation,
            type: StockMutationType::ISSUE,
            onHandChange: Decimal::sub('0', $quantity),
            reservedChange: '0',
            damagedChange: '0',
            actor: $actor,
            reference: $reference,
            reason: $reason,
            idempotencyKey: $idempotencyKey,
            metadata: $metadata,
            requireAvailable: $quantity,
        ));
    }

    /**
     * @param  array<string, mixed>  $reference
     * @param  array<string, mixed>  $metadata
     */
    public function reserve(Product $product, WorkLocation $workLocation, ?WarehouseLocation $warehouseLocation, string|int|float $quantity, ?User $actor = null, array $reference = [], ?string $reason = null, ?string $idempotencyKey = null, array $metadata = []): StockMutation
    {
        $quantity = Decimal::normalize($quantity);

        return $this->runSingleOperation('reserve', $idempotencyKey, fn (): StockMutation => $this->mutate(
            product: $product,
            workLocation: $workLocation,
            warehouseLocation: $warehouseLocation,
            type: StockMutationType::RESERVE,
            onHandChange: '0',
            reservedChange: $quantity,
            damagedChange: '0',
            actor: $actor,
            reference: $reference,
            reason: $reason,
            idempotencyKey: $idempotencyKey,
            metadata: $metadata,
            requireAvailable: $quantity,
        ));
    }

    /**
     * @param  array<string, mixed>  $reference
     * @param  array<string, mixed>  $metadata
     */
    public function releaseReservation(Product $product, WorkLocation $workLocation, ?WarehouseLocation $warehouseLocation, string|int|float $quantity, ?User $actor = null, array $reference = [], ?string $reason = null, ?string $idempotencyKey = null, array $metadata = []): StockMutation
    {
        $quantity = Decimal::normalize($quantity);

        return $this->runSingleOperation('releaseReservation', $idempotencyKey, fn (): StockMutation => $this->mutate(
            product: $product,
            workLocation: $workLocation,
            warehouseLocation: $warehouseLocation,
            type: StockMutationType::RELEASE_RESERVATION,
            onHandChange: '0',
            reservedChange: Decimal::sub('0', $quantity),
            damagedChange: '0',
            actor: $actor,
            reference: $reference,
            reason: $reason,
            idempotencyKey: $idempotencyKey,
            metadata: $metadata,
            requireReserved: $quantity,
        ));
    }

    /**
     * @param  array<string, mixed>  $reference
     * @param  array<string, mixed>  $metadata
     */
    public function transferOut(Product $product, WorkLocation $sourceWorkLocation, ?WarehouseLocation $sourceWarehouseLocation, string|int|float $quantity, ?User $actor = null, array $reference = [], ?string $reason = null, ?string $idempotencyKey = null, array $metadata = []): StockMutation
    {
        $quantity = Decimal::normalize($quantity);
        $destinationWorkLocation = $metadata['destination_work_location'] ?? null;
        $destinationWarehouseLocation = $metadata['destination_warehouse_location'] ?? null;
        unset($metadata['destination_work_location'], $metadata['destination_warehouse_location']);

        return $this->runSingleOperation('transferOut', $idempotencyKey, fn (): StockMutation => $this->mutate(
            product: $product,
            workLocation: $sourceWorkLocation,
            warehouseLocation: $sourceWarehouseLocation,
            type: StockMutationType::TRANSFER_OUT,
            onHandChange: Decimal::sub('0', $quantity),
            reservedChange: '0',
            damagedChange: '0',
            actor: $actor,
            reference: $reference,
            reason: $reason,
            idempotencyKey: $idempotencyKey,
            metadata: $metadata,
            requireAvailable: $quantity,
            destinationWorkLocation: $destinationWorkLocation,
            destinationWarehouseLocation: $destinationWarehouseLocation,
        ));
    }

    /**
     * @param  array<string, mixed>  $reference
     * @param  array<string, mixed>  $metadata
     */
    public function transferIn(Product $product, WorkLocation $destinationWorkLocation, ?WarehouseLocation $destinationWarehouseLocation, string|int|float $quantity, ?User $actor = null, array $reference = [], ?string $reason = null, ?string $idempotencyKey = null, array $metadata = []): StockMutation
    {
        $sourceWorkLocation = $metadata['source_work_location'] ?? null;
        $sourceWarehouseLocation = $metadata['source_warehouse_location'] ?? null;
        unset($metadata['source_work_location'], $metadata['source_warehouse_location']);

        return $this->runSingleOperation('transferIn', $idempotencyKey, fn (): StockMutation => $this->mutate(
            product: $product,
            workLocation: $destinationWorkLocation,
            warehouseLocation: $destinationWarehouseLocation,
            type: StockMutationType::TRANSFER_IN,
            onHandChange: $quantity,
            reservedChange: '0',
            damagedChange: '0',
            actor: $actor,
            reference: $reference,
            reason: $reason,
            idempotencyKey: $idempotencyKey,
            metadata: $metadata,
            sourceWorkLocation: $sourceWorkLocation,
            sourceWarehouseLocation: $sourceWarehouseLocation,
        ));
    }

    /**
     * @param  array<string, mixed>  $reference
     * @param  array<string, mixed>  $metadata
     * @return array{out: StockMutation, in: StockMutation}
     */
    public function transferInternal(Product $product, WorkLocation $sourceWorkLocation, ?WarehouseLocation $sourceWarehouseLocation, WorkLocation $destinationWorkLocation, ?WarehouseLocation $destinationWarehouseLocation, string|int|float $quantity, ?User $actor = null, array $reference = [], ?string $reason = null, ?string $idempotencyKey = null, array $metadata = []): array
    {
        if ($idempotencyKey !== null && $stored = $this->storedMutations($idempotencyKey)) {
            return ['out' => $stored->firstOrFail(), 'in' => $stored->last()];
        }

        return DB::transaction(function () use ($product, $sourceWorkLocation, $sourceWarehouseLocation, $destinationWorkLocation, $destinationWarehouseLocation, $quantity, $actor, $reference, $reason, $idempotencyKey, $metadata): array {
            $operationKey = $idempotencyKey ?? 'transfer-internal-'.str()->uuid()->toString();
            $out = $this->mutate($product, $sourceWorkLocation, $sourceWarehouseLocation, StockMutationType::TRANSFER_OUT, Decimal::sub('0', Decimal::normalize($quantity)), '0', '0', $actor, $reference, $reason, $operationKey, $metadata, requireAvailable: Decimal::normalize($quantity), sourceWorkLocation: $sourceWorkLocation, sourceWarehouseLocation: $sourceWarehouseLocation, destinationWorkLocation: $destinationWorkLocation, destinationWarehouseLocation: $destinationWarehouseLocation);
            $in = $this->mutate($product, $destinationWorkLocation, $destinationWarehouseLocation, StockMutationType::TRANSFER_IN, $quantity, '0', '0', $actor, $reference, $reason, $operationKey, $metadata, sourceWorkLocation: $sourceWorkLocation, sourceWarehouseLocation: $sourceWarehouseLocation, destinationWorkLocation: $destinationWorkLocation, destinationWarehouseLocation: $destinationWarehouseLocation);

            if ($idempotencyKey !== null) {
                InventoryIdempotencyKey::query()->create([
                    'key' => $idempotencyKey,
                    'operation' => 'transferInternal',
                    'response' => ['mutation_ids' => [$out->id, $in->id]],
                ]);
            }

            return ['out' => $out, 'in' => $in];
        });
    }

    /**
     * @param  array<string, mixed>  $reference
     * @param  array<string, mixed>  $metadata
     */
    public function damage(Product $product, WorkLocation $workLocation, ?WarehouseLocation $warehouseLocation, string|int|float $quantity, ?User $actor = null, array $reference = [], ?string $reason = null, ?string $idempotencyKey = null, array $metadata = []): StockMutation
    {
        $quantity = Decimal::normalize($quantity);

        return $this->runSingleOperation('damage', $idempotencyKey, fn (): StockMutation => $this->mutate($product, $workLocation, $warehouseLocation, StockMutationType::DAMAGE, '0', '0', $quantity, $actor, $reference, $reason, $idempotencyKey, $metadata, requireAvailable: $quantity));
    }

    /**
     * @param  array<string, mixed>  $reference
     * @param  array<string, mixed>  $metadata
     */
    public function recover(Product $product, WorkLocation $workLocation, ?WarehouseLocation $warehouseLocation, string|int|float $quantity, ?User $actor = null, array $reference = [], ?string $reason = null, ?string $idempotencyKey = null, array $metadata = []): StockMutation
    {
        $quantity = Decimal::normalize($quantity);

        return $this->runSingleOperation('recover', $idempotencyKey, fn (): StockMutation => $this->mutate($product, $workLocation, $warehouseLocation, StockMutationType::RECOVER, '0', '0', Decimal::sub('0', $quantity), $actor, $reference, $reason, $idempotencyKey, $metadata, requireDamaged: $quantity));
    }

    /**
     * @param  array<string, mixed>  $reference
     * @param  array<string, mixed>  $metadata
     */
    public function adjust(Product $product, WorkLocation $workLocation, ?WarehouseLocation $warehouseLocation, string|int|float $targetOnHand, ?User $actor = null, array $reference = [], ?string $reason = null, ?string $idempotencyKey = null, array $metadata = []): StockMutation
    {
        if (Decimal::compare($targetOnHand, '0') < 0) {
            throw ServiceException::validation('Saldo stok hasil penyesuaian tidak boleh negatif.');
        }

        return $this->runSingleOperation('adjust', $idempotencyKey, function () use ($product, $workLocation, $warehouseLocation, $targetOnHand, $actor, $reference, $reason, $idempotencyKey, $metadata): StockMutation {
            return DB::transaction(function () use ($product, $workLocation, $warehouseLocation, $targetOnHand, $actor, $reference, $reason, $idempotencyKey, $metadata): StockMutation {
                $stock = $this->lockedStock($product, $workLocation, $warehouseLocation);
                $change = Decimal::sub($targetOnHand, (string) $stock->quantity_on_hand);

                return $this->mutateLockedStock($stock, StockMutationType::ADJUST, $change, '0', '0', $actor, $reference, $reason, $idempotencyKey, $metadata);
            });
        });
    }

    /**
     * @param  array<string, mixed>  $reference
     * @param  array<string, mixed>  $metadata
     */
    public function returnIn(Product $product, WorkLocation $workLocation, ?WarehouseLocation $warehouseLocation, string|int|float $quantity, ?User $actor = null, array $reference = [], ?string $reason = null, ?string $idempotencyKey = null, array $metadata = []): StockMutation
    {
        return $this->runSingleOperation('returnIn', $idempotencyKey, fn (): StockMutation => $this->mutate($product, $workLocation, $warehouseLocation, StockMutationType::RETURN_IN, $quantity, '0', '0', $actor, $reference, $reason, $idempotencyKey, $metadata));
    }

    /**
     * @param  array<string, mixed>  $reference
     * @param  array<string, mixed>  $metadata
     */
    public function returnOut(Product $product, WorkLocation $workLocation, ?WarehouseLocation $warehouseLocation, string|int|float $quantity, ?User $actor = null, array $reference = [], ?string $reason = null, ?string $idempotencyKey = null, array $metadata = []): StockMutation
    {
        $quantity = Decimal::normalize($quantity);

        return $this->runSingleOperation('returnOut', $idempotencyKey, fn (): StockMutation => $this->mutate($product, $workLocation, $warehouseLocation, StockMutationType::RETURN_OUT, Decimal::sub('0', $quantity), '0', '0', $actor, $reference, $reason, $idempotencyKey, $metadata, requireAvailable: $quantity));
    }

    /**
     * @param  array<string, mixed>  $reference
     * @param  array<string, mixed>  $metadata
     */
    private function mutate(Product $product, WorkLocation $workLocation, ?WarehouseLocation $warehouseLocation, StockMutationType $type, string|int|float $onHandChange, string|int|float $reservedChange, string|int|float $damagedChange, ?User $actor, array $reference, ?string $reason, ?string $idempotencyKey, array $metadata, string|int|float|null $requireAvailable = null, string|int|float|null $requireReserved = null, string|int|float|null $requireDamaged = null, ?WorkLocation $sourceWorkLocation = null, ?WarehouseLocation $sourceWarehouseLocation = null, ?WorkLocation $destinationWorkLocation = null, ?WarehouseLocation $destinationWarehouseLocation = null): StockMutation
    {
        return DB::transaction(function () use ($product, $workLocation, $warehouseLocation, $type, $onHandChange, $reservedChange, $damagedChange, $actor, $reference, $reason, $idempotencyKey, $metadata, $requireAvailable, $requireReserved, $requireDamaged, $sourceWorkLocation, $sourceWarehouseLocation, $destinationWorkLocation, $destinationWarehouseLocation): StockMutation {
            $stock = $this->lockedStock($product, $workLocation, $warehouseLocation);

            if ($requireAvailable !== null && Decimal::compare($stock->available_quantity, $requireAvailable) < 0) {
                throw ServiceException::validation('Stok tersedia tidak mencukupi.');
            }

            if ($requireReserved !== null && Decimal::compare((string) $stock->quantity_reserved, $requireReserved) < 0) {
                throw ServiceException::validation('Saldo stok reserved tidak mencukupi.');
            }

            if ($requireDamaged !== null && Decimal::compare((string) $stock->quantity_damaged, $requireDamaged) < 0) {
                throw ServiceException::validation('Saldo stok rusak tidak mencukupi.');
            }

            return $this->mutateLockedStock($stock, $type, $onHandChange, $reservedChange, $damagedChange, $actor, $reference, $reason, $idempotencyKey, $metadata, $sourceWorkLocation, $sourceWarehouseLocation, $destinationWorkLocation, $destinationWarehouseLocation);
        });
    }

    /**
     * @param  array<string, mixed>  $reference
     * @param  array<string, mixed>  $metadata
     */
    private function mutateLockedStock(Stock $stock, StockMutationType $type, string|int|float $onHandChange, string|int|float $reservedChange, string|int|float $damagedChange, ?User $actor, array $reference, ?string $reason, ?string $idempotencyKey, array $metadata, ?WorkLocation $sourceWorkLocation = null, ?WarehouseLocation $sourceWarehouseLocation = null, ?WorkLocation $destinationWorkLocation = null, ?WarehouseLocation $destinationWarehouseLocation = null): StockMutation
    {
        $onHandChange = Decimal::normalize($onHandChange);
        $reservedChange = Decimal::normalize($reservedChange);
        $damagedChange = Decimal::normalize($damagedChange);

        if (Decimal::isZero($onHandChange) && Decimal::isZero($reservedChange) && Decimal::isZero($damagedChange)) {
            throw ServiceException::validation('Tidak ada perubahan saldo stok.');
        }

        $beforeOnHand = (string) $stock->quantity_on_hand;
        $beforeReserved = (string) $stock->quantity_reserved;
        $beforeDamaged = (string) $stock->quantity_damaged;
        $afterOnHand = Decimal::add($beforeOnHand, $onHandChange);
        $afterReserved = Decimal::add($beforeReserved, $reservedChange);
        $afterDamaged = Decimal::add($beforeDamaged, $damagedChange);

        $this->ensureStockIsNotFrozen($stock, $reference);

        if (Decimal::compare($afterOnHand, '0') < 0 || Decimal::compare($afterReserved, '0') < 0 || Decimal::compare($afterDamaged, '0') < 0) {
            throw ServiceException::validation('Saldo stok tidak boleh negatif.');
        }

        if (Decimal::compare(Decimal::sub(Decimal::sub($afterOnHand, $afterReserved), $afterDamaged), '0') < 0) {
            throw ServiceException::validation('Stok tersedia tidak boleh negatif.');
        }

        $stock->forceFill([
            'quantity_on_hand' => $afterOnHand,
            'quantity_reserved' => $afterReserved,
            'quantity_damaged' => $afterDamaged,
        ])->save();

        return StockMutation::query()->create([
            'product_id' => $stock->product_id,
            'stock_id' => $stock->id,
            'work_location_id' => $stock->work_location_id,
            'warehouse_location_id' => $stock->warehouse_location_id,
            'mutation_type' => $type,
            'direction' => $this->direction($onHandChange, $reservedChange, $damagedChange),
            'quantity_on_hand_before' => $beforeOnHand,
            'quantity_on_hand_change' => $onHandChange,
            'quantity_on_hand_after' => $afterOnHand,
            'quantity_reserved_before' => $beforeReserved,
            'quantity_reserved_change' => $reservedChange,
            'quantity_reserved_after' => $afterReserved,
            'quantity_damaged_before' => $beforeDamaged,
            'quantity_damaged_change' => $damagedChange,
            'quantity_damaged_after' => $afterDamaged,
            'unit_id' => $stock->product?->base_unit_id,
            'reference_type' => $reference['type'] ?? null,
            'reference_id' => $reference['id'] ?? null,
            'reference_no' => $reference['no'] ?? null,
            'source_work_location_id' => $sourceWorkLocation?->id,
            'source_warehouse_location_id' => $sourceWarehouseLocation?->id,
            'destination_work_location_id' => $destinationWorkLocation?->id,
            'destination_warehouse_location_id' => $destinationWarehouseLocation?->id,
            'actor_user_id' => $actor?->id,
            'reason' => $reason,
            'idempotency_key' => $idempotencyKey,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }

    private function lockedStock(Product $product, WorkLocation $workLocation, ?WarehouseLocation $warehouseLocation): Stock
    {
        if ($warehouseLocation !== null && (int) $warehouseLocation->warehouse?->work_location_id !== (int) $workLocation->id) {
            throw ServiceException::validation('Lokasi bin tidak sesuai dengan gudang/lokasi kerja.');
        }

        $scopeKey = $this->scopeKey($workLocation, $warehouseLocation);

        Stock::query()->firstOrCreate(
            ['product_id' => $product->id, 'location_scope_key' => $scopeKey],
            [
                'work_location_id' => $workLocation->id,
                'warehouse_location_id' => $warehouseLocation?->id,
                'quantity_on_hand' => '0.0000',
                'quantity_reserved' => '0.0000',
                'quantity_damaged' => '0.0000',
                'cost_value' => '0.00',
            ],
        );

        return Stock::query()
            ->with('product')
            ->where('product_id', $product->id)
            ->where('location_scope_key', $scopeKey)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function scopeKey(WorkLocation $workLocation, ?WarehouseLocation $warehouseLocation): string
    {
        return 'work:'.$workLocation->id.'|bin:'.($warehouseLocation === null ? 'none' : $warehouseLocation->id);
    }

    /** @param array<string, mixed> $reference */
    private function ensureStockIsNotFrozen(Stock $stock, array $reference): void
    {
        if (($reference['type'] ?? null) === 'stock_opname') {
            return;
        }

        $product = $stock->product;

        $isFrozen = StockOpname::query()
            ->where('freeze_stock', true)
            ->where('work_location_id', $stock->work_location_id)
            ->whereIn('status', [StockOpnameStatus::COUNTING->value, StockOpnameStatus::PENDING_APPROVAL->value, StockOpnameStatus::APPROVED->value])
            ->where(function ($query) use ($stock): void {
                $query->whereNull('warehouse_location_id')
                    ->orWhere('warehouse_location_id', $stock->warehouse_location_id);
            })
            ->where(function ($query) use ($product): void {
                $query->whereNull('category_id')
                    ->when($product?->category_id !== null, fn ($categoryQuery) => $categoryQuery->orWhere('category_id', $product?->category_id));
            })
            ->exists();

        if ($isFrozen) {
            throw ServiceException::validation('Stok sedang dibekukan karena proses stock opname aktif.');
        }
    }

    /** @return Collection<int, StockMutation>|null */
    private function storedMutations(string $idempotencyKey): ?Collection
    {
        $stored = InventoryIdempotencyKey::query()->where('key', $idempotencyKey)->first();

        if (! $stored) {
            return null;
        }

        $response = $stored->response;
        $ids = is_array($response) && isset($response['mutation_ids']) && is_array($response['mutation_ids'])
            ? $response['mutation_ids']
            : [];

        return StockMutation::query()->whereIn('id', $ids)->orderBy('id')->get();
    }

    private function runSingleOperation(string $operation, ?string $idempotencyKey, callable $callback): StockMutation
    {
        if ($idempotencyKey !== null && $stored = $this->storedMutations($idempotencyKey)) {
            return $stored->firstOrFail();
        }

        return DB::transaction(function () use ($operation, $idempotencyKey, $callback): StockMutation {
            /** @var StockMutation $mutation */
            $mutation = $callback();

            if ($idempotencyKey !== null) {
                InventoryIdempotencyKey::query()->create([
                    'key' => $idempotencyKey,
                    'operation' => $operation,
                    'response' => ['mutation_ids' => [$mutation->id]],
                ]);
            }

            return $mutation;
        });
    }

    private function direction(string $onHandChange, string $reservedChange, string $damagedChange): string
    {
        if (Decimal::compare($onHandChange, '0') > 0 || Decimal::compare($damagedChange, '0') > 0 || Decimal::compare($reservedChange, '0') > 0) {
            return 'in';
        }

        return 'out';
    }
}
