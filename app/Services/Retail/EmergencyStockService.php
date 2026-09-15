<?php

namespace App\Services\Retail;

use App\Models\Branch;
use App\Models\EmergencyPurchaseItem;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Collection;

class EmergencyStockService
{
    /**
     * @param  list<int>  $productIds
     * @return array<int, string>
     */
    public function availableByProduct(Branch $branch, array $productIds): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if ($productIds === []) {
            return [];
        }

        $items = EmergencyPurchaseItem::query()
            ->with('purchase')
            ->whereIn('product_id', $productIds)
            ->whereHas('purchase', fn ($query) => $query
                ->where('branch_id', $branch->id)
                ->where('status', 'unallocated'))
            ->get();
        $reservations = $this->pendingAssignmentsFor($items->pluck('id')->all());
        $available = [];

        foreach ($items as $item) {
            $free = Decimal::sub($this->remaining($item), $reservations[$item->id] ?? '0.0000', 4);
            if (Decimal::compare($free, '0', 4) <= 0) {
                continue;
            }
            $available[$item->product_id] = Decimal::add($available[$item->product_id] ?? '0.0000', $free, 4);
        }

        return $available;
    }

    public function available(Branch $branch, int $productId): string
    {
        return $this->availableByProduct($branch, [$productId])[$productId] ?? '0.0000';
    }

    /** @return array{quantity: string, cost_value: string} */
    public function summary(Branch $branch): array
    {
        $quantity = '0.0000';
        $costValue = '0.00';

        foreach ($this->summaryByProduct($branch) as $product) {
            $quantity = Decimal::add($quantity, $product['quantity'], 4);
            $costValue = Decimal::add($costValue, $product['cost_value'], 2);
        }

        return ['quantity' => $quantity, 'cost_value' => $costValue];
    }

    /**
     * @return list<array{product_id: int, product_name: string, sku: string, unit_symbol: string, quantity: string, cost_value: string, lot_count: int}>
     */
    public function summaryByProduct(Branch $branch): array
    {
        $items = EmergencyPurchaseItem::query()
            ->with(['purchase', 'product.baseUnit'])
            ->whereHas('purchase', fn ($query) => $query
                ->where('branch_id', $branch->id)
                ->where('status', 'unallocated'))
            ->get();
        $reservations = $this->pendingAssignmentsFor($items->pluck('id')->all());
        $products = [];

        foreach ($items as $item) {
            $free = Decimal::sub($this->remaining($item), $reservations[$item->id] ?? '0.0000', 4);
            if (Decimal::compare($free, '0', 4) <= 0) {
                continue;
            }

            $productId = (int) $item->product_id;
            $products[$productId] ??= [
                'product_id' => $productId,
                'product_name' => $item->product->name,
                'sku' => $item->product->sku,
                'unit_symbol' => $item->product->baseUnit->symbol,
                'quantity' => '0.0000',
                'cost_value' => '0.00',
                'lot_count' => 0,
            ];
            $products[$productId]['quantity'] = Decimal::add($products[$productId]['quantity'], $free, 4);
            $products[$productId]['cost_value'] = Decimal::add(
                $products[$productId]['cost_value'],
                Decimal::mul($free, (string) $item->unit_cost, 4, 2, 2),
                2,
            );
            $products[$productId]['lot_count']++;
        }

        uasort($products, fn (array $left, array $right): int => strcasecmp($left['product_name'], $right['product_name']));

        return array_values($products);
    }

    /** @return list<int> */
    public function availableProductIds(Branch $branch): array
    {
        $ids = EmergencyPurchaseItem::query()
            ->whereHas('purchase', fn ($query) => $query
                ->where('branch_id', $branch->id)
                ->where('status', 'unallocated'))
            ->distinct()
            ->pluck('product_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $available = $this->availableByProduct($branch, $ids);

        return array_map('intval', array_keys(array_filter(
            $available,
            fn (string $quantity): bool => Decimal::compare($quantity, '0', 4) > 0,
        )));
    }

    /**
     * @param  list<int>  $productIds
     * @return Collection<int, EmergencyPurchaseItem>
     */
    public function lockSharedLots(Branch $branch, array $productIds): Collection
    {
        return EmergencyPurchaseItem::query()
            ->select('emergency_purchase_items.*')
            ->join('emergency_purchases', 'emergency_purchases.id', '=', 'emergency_purchase_items.emergency_purchase_id')
            ->with('purchase')
            ->whereIn('emergency_purchase_items.product_id', array_values(array_unique(array_map('intval', $productIds))))
            ->where('emergency_purchases.branch_id', $branch->id)
            ->where('emergency_purchases.status', 'unallocated')
            ->orderBy('emergency_purchases.purchased_at')
            ->orderBy('emergency_purchase_items.id')
            ->lockForUpdate()
            ->get();
    }

    /** @param list<int> $sourceItemIds
     * @return array<int, string>
     */
    public function pendingAssignmentsFor(array $sourceItemIds): array
    {
        if ($sourceItemIds === []) {
            return [];
        }

        return EmergencyPurchaseItem::query()
            ->whereIn('assigned_source_item_id', $sourceItemIds)
            ->whereHas('purchase', fn ($query) => $query->where('status', 'purchased'))
            ->get()
            ->groupBy('assigned_source_item_id')
            ->map(fn ($items): string => $items->reduce(
                fn (string $sum, EmergencyPurchaseItem $item): string => Decimal::add(
                    $sum,
                    Decimal::sub((string) $item->assigned_quantity, (string) $item->allocated_quantity, 4),
                    4,
                ),
                '0.0000',
            ))
            ->all();
    }

    public function remaining(EmergencyPurchaseItem $item): string
    {
        return Decimal::sub(
            Decimal::sub(
                Decimal::sub(
                    Decimal::sub((string) $item->purchased_quantity, (string) $item->allocated_quantity, 4),
                    (string) $item->supplier_returned_quantity,
                    4,
                ),
                (string) $item->warehouse_quantity,
                4,
            ),
            (string) $item->regularized_quantity,
            4,
        );
    }
}
