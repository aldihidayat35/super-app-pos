<?php

namespace App\Services\Pricing;

use App\Enums\CustomerType;
use App\Enums\PriceApprovalStatus;
use App\Enums\ProductPriceStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerPriceOverride;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductUnit;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Decimal;
use Illuminate\Support\Carbon;

class PriceResolverService
{
    /**
     * Priority order:
     * 1. Approved customer special price matching customer/product/channel/branch/date/min qty.
     * 2. Product price matching customer price category.
     * 3. Product price matching branch.
     * 4. Product price matching channel/global.
     * 5. Fallback computed from HPP + active price rule.
     *
     * @return array<string, mixed>
     */
    public function resolve(Product $product, string|int|float $quantity = 1, ?int $unitId = null, ?Branch $branch = null, ?Customer $customer = null, string $channel = 'retail', ?Carbon $at = null, ?User $user = null, string|int|float|null $requestedPrice = null, string|int|float $discountPercent = 0): array
    {
        $at ??= now();
        $factor = $this->unitFactor($product, $unitId);
        $baseQuantity = Decimal::mul(Decimal::normalize($quantity), $factor, 4, 6, 4);
        $rule = $this->activeRule($channel, $branch, $customer, $at);
        $hppBase = Decimal::normalize($product->cost_price ?? 0, 2);
        $policyMinimumBase = $this->minimumBasePrice($product, $rule);
        $policyMaximumBase = $this->maximumBasePrice($policyMinimumBase, $rule);

        $candidates = $this->candidates($product, $baseQuantity, $branch, $customer, $channel, $at);
        if ($candidates === []) {
            $candidates[] = [
                'source' => 'computed_minimum',
                'priority' => 9999,
                'price_base' => $policyMinimumBase,
                'ring_name' => $rule->name,
                'minimum_base' => $policyMinimumBase,
                'maximum_base' => $policyMaximumBase,
                'reason' => 'Fallback dari HPP + aturan margin minimum.',
            ];
        }

        usort($candidates, fn (array $a, array $b): int => [$a['priority'], $a['source']] <=> [$b['priority'], $b['source']]);
        $selected = $candidates[0];
        if ($selected['source'] === 'product_price') {
            $minimumBase = isset($selected['minimum_base']) && Decimal::compare((string) $selected['minimum_base'], '0', 2) > 0
                ? (string) $selected['minimum_base']
                : $policyMinimumBase;
            $maximumBase = isset($selected['maximum_base']) && Decimal::compare((string) $selected['maximum_base'], '0', 2) > 0
                ? (string) $selected['maximum_base']
                : $policyMaximumBase;
        } else {
            $minimumBase = isset($selected['minimum_base']) && Decimal::compare((string) $selected['minimum_base'], '0', 2) > 0
                ? $this->maximum($policyMinimumBase, (string) $selected['minimum_base'])
                : $policyMinimumBase;
            $maximumBase = isset($selected['maximum_base']) && Decimal::compare((string) $selected['maximum_base'], '0', 2) > 0
                ? $this->minimum($policyMaximumBase, (string) $selected['maximum_base'])
                : $policyMaximumBase;
        }
        if (Decimal::compare($maximumBase, $minimumBase, 2) < 0) {
            $maximumBase = $minimumBase;
        }
        $recommendedUnit = Decimal::mul((string) $selected['price_base'], $factor, 2, 6, 2);
        $selectedBase = Decimal::normalize($requestedPrice === null ? $selected['price_base'] : Decimal::div($requestedPrice, $factor, 2, 6, 2), 2);
        $selectedUnit = Decimal::mul($selectedBase, $factor, 2, 6, 2);
        $minimumUnit = Decimal::mul($minimumBase, $factor, 2, 6, 2);
        $maximumUnit = Decimal::mul($maximumBase, $factor, 2, 6, 2);
        $discountedUnit = $this->applyDiscount($selectedUnit, $discountPercent);

        $belowMinimum = Decimal::compare($discountedUnit, $minimumUnit, 2) < 0;
        $overpricing = $channel !== 'pos' && Decimal::compare($selectedUnit, $maximumUnit, 2) > 0;
        $discountTooHigh = Decimal::compare($discountPercent, (string) $rule->max_discount_percent, 2) > 0;
        $approvalRequired = $belowMinimum || $overpricing || $discountTooHigh;

        $hppUnit = Decimal::mul($hppBase, $factor, 2, 6, 2);
        $marginAmount = Decimal::sub($discountedUnit, $hppUnit, 2);
        $marginPercent = $this->marginPercent($discountedUnit, $hppUnit);

        return [
            'product_id' => $product->id,
            'hpp_base' => $hppBase,
            'hpp_unit' => $hppUnit,
            'unit_factor' => $factor,
            'quantity_base' => $baseQuantity,
            'minimum_price' => $minimumUnit,
            'maximum_price' => $maximumUnit,
            'recommended_price' => $recommendedUnit,
            'price_ring' => $selected['ring_name'] ?? $rule->name,
            'selected_price' => $selectedUnit,
            'discounted_price' => $discountedUnit,
            'margin_amount' => $marginAmount,
            'margin_percent' => $marginPercent,
            'margin_method' => $rule->margin_method,
            'margin_percent_value' => Decimal::normalize($rule->minimum_margin_percent, 2),
            'margin_amount_value' => Decimal::normalize($rule->minimum_margin_amount, 2),
            'computed_minimum' => $minimumBase,
            'candidates' => $candidates,
            'selected_source' => $selected['source'],
            'reason' => $selected['reason'],
            'approval_required' => $approvalRequired,
            'approval_reasons' => array_values(array_filter([
                $belowMinimum ? 'below_minimum' : null,
                $overpricing ? 'overpricing' : null,
                $discountTooHigh ? 'discount_exceeds_cap' : null,
            ])),
            'can_view_sensitive_margin' => $user?->can('margins.view_sensitive') ?? false,
            'rule_id' => $rule->id,
            'rule_name' => $rule->name,
            'rule_priority' => $rule->priority,
            'max_discount_percent' => Decimal::normalize($rule->max_discount_percent, 2),
            'overpricing_tolerance_percent' => Decimal::normalize($rule->overpricing_tolerance_percent, 2),
        ];
    }

    private function unitFactor(Product $product, ?int $unitId): string
    {
        if ($unitId === null || (int) $product->base_unit_id === $unitId) {
            return '1.000000';
        }

        $unit = ProductUnit::query()
            ->where('product_id', $product->id)
            ->where('unit_id', $unitId)
            ->where('is_active', true)
            ->first();

        return $unit instanceof ProductUnit ? (string) $unit->conversion_factor : '1.000000';
    }

    private function activeRule(string $channel, ?Branch $branch, ?Customer $customer, Carbon $at): PriceRule
    {
        $targetChannel = $channel;
        if ($channel === 'pos' && $customer !== null) {
            /** @var CustomerType|string|null $rawType */
            $rawType = $customer->type;
            $custType = $rawType instanceof CustomerType ? $rawType->value : (string) $rawType;
            $custCat = strtolower(trim((string) ($customer->price_category ?? '')));
            if ($custCat === 'retail' || $custType === CustomerType::RETAIL_CREDIT->value || str_contains($custCat, 'retail')) {
                $targetChannel = 'retail';
            } elseif ($custCat === 'b2b' || $custType === CustomerType::B2B->value || str_contains($custCat, 'b2b')) {
                $targetChannel = 'b2b';
            }
        }

        $rule = PriceRule::query()
            ->where('is_active', true)
            ->where(function ($query) use ($channel, $targetChannel): void {
                $query->where('channel', $targetChannel)
                    ->orWhere('channel', $channel)
                    ->orWhere('channel', 'all');
            })
            ->where(fn ($query) => $query->whereNull('branch_id')->when($branch !== null, fn ($branchQuery) => $branchQuery->orWhere('branch_id', $branch->id)))
            ->where(fn ($query) => $query->whereNull('customer_category')->when($customer !== null, fn ($customerQuery) => $customerQuery->orWhere('customer_category', $customer->price_category)))
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhereDate('starts_at', '<=', $at->toDateString()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhereDate('ends_at', '>=', $at->toDateString()))
            ->orderByRaw('CASE WHEN channel = ? THEN 1 WHEN channel = ? THEN 2 ELSE 3 END', [$targetChannel, $channel])
            ->orderBy('priority')
            ->orderByDesc('branch_id')
            ->orderByDesc('customer_category')
            ->first();

        if ($rule instanceof PriceRule) {
            return $rule;
        }

        return new PriceRule([
            'name' => 'Default Pricing Rule',
            'channel' => 'all',
            'margin_method' => 'percent',
            'minimum_margin_percent' => '20.00',
            'minimum_margin_amount' => '0.00',
            'overpricing_tolerance_percent' => '100.00',
            'max_discount_percent' => '20.00',
            'approval_threshold_amount' => '1000000.00',
            'priority' => 9999,
        ]);
    }

    private function minimumBasePrice(Product $product, PriceRule $rule): string
    {
        $hpp = Decimal::normalize($product->cost_price ?? 0, 2);
        $margin = $rule->margin_method === 'nominal'
            ? Decimal::normalize((string) $rule->minimum_margin_amount, 2)
            : Decimal::div(Decimal::mul($hpp, (string) $rule->minimum_margin_percent, 2, 2, 2), '100', 2, 2, 2);
        $computed = Decimal::add($hpp, $margin, 2);

        return Decimal::compare((string) $product->minimum_price, $computed, 2) > 0 ? (string) $product->minimum_price : $computed;
    }

    private function maximumBasePrice(string $minimumBase, PriceRule $rule): string
    {
        $tolerance = Decimal::div(Decimal::mul($minimumBase, (string) $rule->overpricing_tolerance_percent, 2, 2, 2), '100', 2, 2, 2);

        return Decimal::add($minimumBase, $tolerance, 2);
    }

    /** @return list<array<string, mixed>> */
    private function candidates(Product $product, string $baseQuantity, ?Branch $branch, ?Customer $customer, string $channel, Carbon $at): array
    {
        $candidates = [];

        if ($customer instanceof Customer) {
            CustomerPriceOverride::query()
                ->where('customer_id', $customer->id)
                ->where('product_id', $product->id)
                ->where('is_active', true)
                ->where('status', PriceApprovalStatus::APPROVED->value)
                ->where(fn ($query) => $query->where('channel', $channel)->orWhere('channel', 'all'))
                ->where(fn ($query) => $query->whereNull('branch_id')->when($branch !== null, fn ($branchQuery) => $branchQuery->orWhere('branch_id', $branch->id)))
                ->where('minimum_qty', '<=', $baseQuantity)
                ->whereDate('starts_at', '<=', $at->toDateString())
                ->where(fn ($query) => $query->whereNull('ends_at')->orWhereDate('ends_at', '>=', $at->toDateString()))
                ->orderBy('priority')
                ->orderByDesc('branch_id')
                ->get()
                ->each(function (CustomerPriceOverride $override) use (&$candidates): void {
                    $candidates[] = [
                        'source' => 'customer_special',
                        'priority' => (int) $override->priority,
                        'price_base' => (string) $override->price,
                        'ring_name' => 'Harga Khusus Pelanggan',
                        'reason' => 'Harga khusus pelanggan.',
                        'id' => $override->id,
                    ];
                });
        }

        $primaryWarehouseWorkLocationId = null;
        if ($branch?->primary_warehouse_id) {
            $primaryWarehouseWorkLocationId = Warehouse::where('id', $branch->primary_warehouse_id)->value('work_location_id');
        }

        $targetRingChannel = $channel;
        if ($channel === 'pos' && $customer !== null) {
            /** @var CustomerType|string|null $rawType */
            $rawType = $customer->type;
            $custType = $rawType instanceof CustomerType ? $rawType->value : (string) $rawType;
            $custCat = strtolower(trim((string) ($customer->price_category ?? '')));
            if ($custCat === 'retail' || $custType === CustomerType::RETAIL_CREDIT->value || str_contains($custCat, 'retail')) {
                $targetRingChannel = 'retail';
            } elseif ($custCat === 'b2b' || $custType === CustomerType::B2B->value || str_contains($custCat, 'b2b')) {
                $targetRingChannel = 'b2b';
            }
        }

        ProductPrice::query()
            ->where('product_id', $product->id)
            ->where('status', ProductPriceStatus::ACTIVE->value)
            ->where(function ($query) use ($channel, $targetRingChannel): void {
                $query->where('channel', $channel)->orWhere('channel', 'all');
                if ($channel === 'pos') {
                    $query->orWhere('channel', 'retail');
                    if ($targetRingChannel === 'b2b') {
                        $query->orWhere('channel', 'b2b');
                    }
                }
            })
            ->where(function ($query) use ($branch, $primaryWarehouseWorkLocationId): void {
                $query->whereNull('work_location_id');
                if ($branch?->work_location_id !== null) {
                    $query->orWhere('work_location_id', $branch->work_location_id);
                }
                if ($primaryWarehouseWorkLocationId !== null) {
                    $query->orWhere('work_location_id', $primaryWarehouseWorkLocationId);
                }
            })
            ->where(fn ($query) => $query->whereNull('branch_id')->when($branch !== null, fn ($branchQuery) => $branchQuery->orWhere('branch_id', $branch->id)))
            ->where(fn ($query) => $query->whereNull('customer_category')->when($customer !== null, fn ($customerQuery) => $customerQuery->orWhere('customer_category', $customer->price_category)))
            ->where('minimum_qty', '<=', $baseQuantity)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhereDate('starts_at', '<=', $at->toDateString()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhereDate('ends_at', '>=', $at->toDateString()))
            ->orderBy('priority')
            ->orderByDesc('work_location_id')
            ->orderByDesc('customer_category')
            ->orderByDesc('branch_id')
            ->get()
            ->each(function (ProductPrice $price) use (&$candidates, $branch, $primaryWarehouseWorkLocationId, $channel, $targetRingChannel): void {
                $isTargetRing = false;
                if ($targetRingChannel === 'retail') {
                    $isTargetRing = ($price->channel === 'retail' || str_contains(strtolower($price->price_ring), 'retail'));
                } elseif ($targetRingChannel === 'b2b') {
                    $isTargetRing = ($price->channel === 'b2b' || str_contains(strtolower($price->price_ring), 'b2b'));
                } elseif ($targetRingChannel === 'pos') {
                    $isTargetRing = ($price->channel === 'pos' || str_contains(strtolower($price->price_ring), 'pos'));
                }

                $customerTargetBonus = $isTargetRing ? 25 : 0;
                $channelMatchBonus = ($price->channel === $channel) ? 10 : 0;
                $isDirectBranch = $branch && ($price->branch_id === $branch->id || $price->work_location_id === $branch->work_location_id);
                $isPrimaryWarehouse = $primaryWarehouseWorkLocationId && $price->work_location_id === $primaryWarehouseWorkLocationId;

                $specificity = ($price->customer_category !== null ? 20 : 0)
                    + ($isDirectBranch ? 15 : ($isPrimaryWarehouse ? 8 : 0))
                    + $customerTargetBonus
                    + $channelMatchBonus;

                $candidates[] = [
                    'source' => 'product_price',
                    'priority' => 100 + (int) $price->priority - $specificity,
                    'price_base' => (string) $price->recommended_price,
                    'ring_name' => $price->price_ring,
                    'minimum_base' => $price->min_price,
                    'maximum_base' => $price->max_price,
                    'reason' => "Harga {$price->price_ring} {$price->channel}.",
                    'id' => $price->id,
                ];
            });

        return $candidates;
    }

    private function applyDiscount(string $price, string|int|float $discountPercent): string
    {
        $discountAmount = Decimal::div(Decimal::mul($price, $discountPercent, 2, 2, 2), '100', 2, 2, 2);

        return Decimal::sub($price, $discountAmount, 2);
    }

    private function marginPercent(string $sellPrice, string $hpp): string
    {
        if (Decimal::compare($sellPrice, '0', 2) <= 0) {
            return '0.00';
        }

        return Decimal::mul(Decimal::div(Decimal::sub($sellPrice, $hpp, 2), $sellPrice, 2, 2, 4), '100', 4, 2, 2);
    }

    private function minimum(string $left, string $right): string
    {
        return Decimal::compare($left, $right, 2) <= 0 ? $left : $right;
    }

    private function maximum(string $left, string $right): string
    {
        return Decimal::compare($left, $right, 2) >= 0 ? $left : $right;
    }
}
