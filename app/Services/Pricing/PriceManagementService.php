<?php

namespace App\Services\Pricing;

use App\Enums\PriceApprovalStatus;
use App\Enums\ProductPriceStatus;
use App\Models\Customer;
use App\Models\CustomerPriceOverride;
use App\Models\PriceApprovalRequest;
use App\Models\PriceHistory;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PriceManagementService
{
    public function __construct(private readonly PriceResolverService $resolver) {}

    /** @param array<string, mixed> $data */
    public function saveRule(array $data): PriceRule
    {
        return DB::transaction(fn (): PriceRule => PriceRule::query()->updateOrCreate(
            ['id' => $data['id'] ?? null],
            [
                'name' => $data['name'],
                'channel' => $data['channel'] ?? 'all',
                'branch_id' => $data['branch_id'] ?? null,
                'customer_category' => $data['customer_category'] ?? null,
                'margin_method' => $data['margin_method'] ?? 'percent',
                'minimum_margin_percent' => $data['minimum_margin_percent'] ?? 20,
                'minimum_margin_amount' => $data['minimum_margin_amount'] ?? 0,
                'overpricing_tolerance_percent' => $data['overpricing_tolerance_percent'] ?? 100,
                'max_discount_percent' => $data['max_discount_percent'] ?? 20,
                'approval_threshold_amount' => $data['approval_threshold_amount'] ?? 1000000,
                'priority' => $data['priority'] ?? 100,
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'notes' => $data['notes'] ?? null,
            ],
        ));
    }

    /** @param array<string, mixed> $data */
    public function saveProductPrice(array $data, User $actor): ProductPrice
    {
        return DB::transaction(function () use ($data, $actor): ProductPrice {
            $product = Product::query()->lockForUpdate()->findOrFail($data['product_id']);
            $oldPrice = null;
            $price = null;
            if (($data['id'] ?? null) !== null) {
                $price = ProductPrice::query()->lockForUpdate()->findOrFail($data['id']);
                $oldPrice = $price->recommended_price;
            }

            $resolved = $this->resolver->resolve($product, branch: null, channel: $data['channel'] ?? 'retail', user: $actor, requestedPrice: $data['recommended_price']);
            $status = ($resolved['approval_required'] ?? false) ? ProductPriceStatus::DRAFT : ProductPriceStatus::ACTIVE;

            $price = ProductPrice::query()->updateOrCreate(
                ['id' => $data['id'] ?? null],
                [
                    'product_id' => $product->id,
                    'branch_id' => $data['branch_id'] ?? null,
                    'channel' => $data['channel'] ?? 'retail',
                    'price_ring' => $data['price_ring'] ?? 'retail',
                    'customer_category' => $data['customer_category'] ?? null,
                    'min_price' => $data['min_price'] ?? $resolved['minimum_price'],
                    'recommended_price' => $data['recommended_price'],
                    'max_price' => $data['max_price'] ?? $resolved['maximum_price'],
                    'minimum_qty' => $data['minimum_qty'] ?? 1,
                    'priority' => $data['priority'] ?? 100,
                    'starts_at' => $data['starts_at'] ?? null,
                    'ends_at' => $data['ends_at'] ?? null,
                    'status' => $status,
                    'notes' => $data['notes'] ?? null,
                ],
            );

            $this->writeHistory($price, $product, $oldPrice === null ? null : (string) $oldPrice, (string) $price->recommended_price, $actor, $data['notes'] ?? 'Perubahan harga produk');

            if ($status === ProductPriceStatus::DRAFT) {
                $this->requestApproval('product_price', $price->id, $product, null, $actor, (string) $price->recommended_price, $resolved, $data['notes'] ?? null);
            }

            return $price->fresh(['product', 'branch']);
        });
    }

    /** @param array<string, mixed> $data */
    public function saveCustomerOverride(array $data, User $actor): CustomerPriceOverride
    {
        return DB::transaction(function () use ($data, $actor): CustomerPriceOverride {
            $product = Product::query()->findOrFail($data['product_id']);
            $customer = Customer::query()->findOrFail($data['customer_id']);
            $resolved = $this->resolver->resolve($product, customer: $customer, channel: $data['channel'] ?? 'b2b', user: $actor, requestedPrice: $data['price'], discountPercent: $data['discount_percent'] ?? 0);
            $status = ($resolved['approval_required'] ?? false) ? PriceApprovalStatus::PENDING : PriceApprovalStatus::APPROVED;

            $override = CustomerPriceOverride::query()->create([
                'customer_id' => $customer->id,
                'product_id' => $product->id,
                'branch_id' => $data['branch_id'] ?? null,
                'channel' => $data['channel'] ?? 'b2b',
                'price' => $data['price'],
                'minimum_qty' => $data['minimum_qty'] ?? 1,
                'discount_percent' => $data['discount_percent'] ?? 0,
                'priority' => $data['priority'] ?? 10,
                'status' => $status->value,
                'starts_at' => $data['starts_at'] ?? now()->toDateString(),
                'ends_at' => $data['ends_at'] ?? null,
                'is_active' => true,
                'notes' => $data['notes'] ?? null,
                'reason' => $data['reason'] ?? null,
                'requested_by' => $actor->id,
                'approved_by' => $status === PriceApprovalStatus::APPROVED ? $actor->id : null,
                'approved_at' => $status === PriceApprovalStatus::APPROVED ? now() : null,
            ]);

            $this->writeHistory($override, $product, null, (string) $override->price, $actor, $data['reason'] ?? 'Harga khusus pelanggan');

            if ($status === PriceApprovalStatus::PENDING) {
                $this->requestApproval('customer_special_price', $override->id, $product, $customer, $actor, (string) $override->price, $resolved, $data['reason'] ?? null);
            }

            return $override->fresh(['customer', 'product']);
        });
    }

    public function approve(PriceApprovalRequest $approval, User $actor, ?string $notes = null): PriceApprovalRequest
    {
        return DB::transaction(function () use ($approval, $actor, $notes): PriceApprovalRequest {
            $approval = PriceApprovalRequest::query()->lockForUpdate()->findOrFail($approval->id);
            $approval->forceFill(['status' => PriceApprovalStatus::APPROVED, 'approved_by' => $actor->id, 'approved_at' => now(), 'decision_notes' => $notes])->save();

            if ($approval->document_type === 'customer_price_override') {
                CustomerPriceOverride::query()->whereKey($approval->document_id)->update(['status' => PriceApprovalStatus::APPROVED->value, 'approved_by' => $actor->id, 'approved_at' => now()]);
            }

            if ($approval->document_type === 'product_price') {
                ProductPrice::query()->whereKey($approval->document_id)->update(['status' => ProductPriceStatus::ACTIVE->value]);
            }

            return $approval->fresh(['product', 'customer', 'requester']);
        });
    }

    public function reject(PriceApprovalRequest $approval, User $actor, ?string $notes = null): PriceApprovalRequest
    {
        return DB::transaction(function () use ($approval, $actor, $notes): PriceApprovalRequest {
            $approval = PriceApprovalRequest::query()->lockForUpdate()->findOrFail($approval->id);
            $approval->forceFill(['status' => PriceApprovalStatus::REJECTED, 'approved_by' => $actor->id, 'approved_at' => now(), 'decision_notes' => $notes])->save();

            return $approval->fresh(['product', 'customer', 'requester']);
        });
    }

    private function writeHistory(ProductPrice|CustomerPriceOverride $priceable, Product $product, ?string $oldPrice, string $newPrice, User $actor, ?string $reason): void
    {
        PriceHistory::query()->create([
            'priceable_type' => $priceable::class,
            'priceable_id' => $priceable->id,
            'product_id' => $product->id,
            'customer_id' => $priceable instanceof CustomerPriceOverride ? $priceable->customer_id : null,
            'branch_id' => $priceable->branch_id,
            'channel' => $priceable->channel,
            'price_ring' => $priceable instanceof ProductPrice ? $priceable->price_ring : 'special',
            'old_price' => $oldPrice,
            'new_price' => $newPrice,
            'hpp_snapshot' => $product->cost_price,
            'minimum_price_snapshot' => $product->minimum_price,
            'changed_by' => $actor->id,
            'source' => 'manual',
            'reason' => $reason,
            'effective_at' => $priceable->starts_at ?? now()->toDateString(),
        ]);
    }

    /** @param array<string, mixed> $resolved */
    private function requestApproval(string $type, int $documentId, Product $product, ?Customer $customer, User $actor, string $requestedPrice, array $resolved, ?string $reason): void
    {
        PriceApprovalRequest::query()->create([
            'approval_type' => implode(',', $resolved['approval_reasons'] ?? [$type]),
            'document_type' => $type === 'product_price' ? 'product_price' : 'customer_price_override',
            'document_id' => $documentId,
            'product_id' => $product->id,
            'customer_id' => $customer?->id,
            'requested_by' => $actor->id,
            'status' => PriceApprovalStatus::PENDING,
            'requested_price' => $requestedPrice,
            'minimum_price_snapshot' => $resolved['minimum_price'],
            'maximum_price_snapshot' => $resolved['maximum_price'],
            'hpp_snapshot' => $resolved['hpp_base'],
            'reason' => $reason,
            'expires_at' => now()->addDays(7),
        ]);
    }
}
