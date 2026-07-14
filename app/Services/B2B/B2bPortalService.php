<?php

namespace App\Services\B2B;

use App\Enums\B2bCartStatus;
use App\Enums\B2bOrderStatus;
use App\Enums\CustomerStatus;
use App\Events\B2B\B2bOrderStatusChanged;
use App\Exceptions\ServiceException;
use App\Models\B2bCart;
use App\Models\B2bCartItem;
use App\Models\B2bOrder;
use App\Models\B2bOrderStatusHistory;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Unit;
use App\Models\User;
use App\Services\Organization\DocumentNumberService;
use App\Services\Pricing\PriceResolverService;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

class B2bPortalService
{
    public function __construct(
        private readonly PriceResolverService $prices,
        private readonly DocumentNumberService $numbers,
    ) {}

    public function activeCustomerFor(User $user): Customer
    {
        $customer = $user->customers()
            ->where('customers.type', 'b2b')
            ->where('customers.is_active', true)
            ->where('customers.verification_status', CustomerStatus::ACTIVE->value)
            ->where('customers.account_status', CustomerStatus::ACTIVE->value)
            ->wherePivot('is_active', true)
            ->whereNull('customer_users.blocked_at')
            ->orderBy('customers.id')
            ->first();

        if (! $customer instanceof Customer) {
            throw ServiceException::validation('Akun langganan belum aktif, belum terverifikasi, atau sedang diblokir.');
        }

        return $customer;
    }

    public function currentCart(Customer $customer, User $user): B2bCart
    {
        $cart = B2bCart::query()
            ->where('customer_id', $customer->id)
            ->where('user_id', $user->id)
            ->where('status', B2bCartStatus::ACTIVE->value)
            ->first();

        if ($cart instanceof B2bCart) {
            return $cart->load(['items.product.category', 'items.product.baseUnit', 'items.unit']);
        }

        return B2bCart::query()->create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'status' => B2bCartStatus::ACTIVE,
        ])->load(['items.product.category', 'items.product.baseUnit', 'items.unit']);
    }

    /** @param array<string, mixed> $data */
    public function addToCart(Customer $customer, User $user, array $data): B2bCart
    {
        return DB::transaction(function () use ($customer, $user, $data): B2bCart {
            $cart = $this->currentCart($customer, $user);
            $cart = B2bCart::query()->whereKey($cart->id)->lockForUpdate()->firstOrFail();
            $line = $this->buildLine($customer, $user, (int) $data['product_id'], (int) $data['unit_id'], $data['quantity'], $data['notes'] ?? null);

            $existing = $cart->items()
                ->where('product_id', $line['product']->id)
                ->where('unit_id', $line['unit']->id)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof B2bCartItem) {
                $quantity = Decimal::add((string) $existing->quantity, $line['quantity']);
                $line = $this->buildLine($customer, $user, (int) $data['product_id'], (int) $data['unit_id'], $quantity, $data['notes'] ?? $existing->notes);
                $existing->forceFill($this->cartItemPayload($line))->save();
            } else {
                $cart->items()->create($this->cartItemPayload($line));
            }

            return $this->refreshCart($cart->fresh(), $customer, $user);
        });
    }

    /** @param array<int, array<string, mixed>> $items */
    public function updateCart(Customer $customer, User $user, array $items): B2bCart
    {
        return DB::transaction(function () use ($customer, $user, $items): B2bCart {
            $cart = B2bCart::query()
                ->where('customer_id', $customer->id)
                ->where('user_id', $user->id)
                ->where('status', B2bCartStatus::ACTIVE->value)
                ->lockForUpdate()
                ->firstOrFail();

            foreach ($items as $row) {
                $item = $cart->items()->whereKey((int) ($row['id'] ?? 0))->lockForUpdate()->first();
                if (! $item instanceof B2bCartItem) {
                    continue;
                }

                $quantity = Decimal::normalize($row['quantity'] ?? 0, 4);
                if (Decimal::compare($quantity, '0', 4) <= 0) {
                    $item->delete();

                    continue;
                }

                $line = $this->buildLine($customer, $user, (int) $item->product_id, (int) $item->unit_id, $quantity, $row['notes'] ?? $item->notes);
                $item->forceFill($this->cartItemPayload($line))->save();
            }

            return $this->refreshCart($cart->fresh(), $customer, $user);
        });
    }

    public function removeItem(Customer $customer, User $user, int $itemId): B2bCart
    {
        return DB::transaction(function () use ($customer, $user, $itemId): B2bCart {
            $cart = $this->currentCart($customer, $user);
            $cart->items()->whereKey($itemId)->delete();

            return $this->refreshCart($cart->fresh(), $customer, $user);
        });
    }

    public function refreshCart(B2bCart $cart, Customer $customer, User $user): B2bCart
    {
        $cart->load(['items.product', 'items.unit']);
        foreach ($cart->items as $item) {
            $line = $this->buildLine($customer, $user, (int) $item->product_id, (int) $item->unit_id, (string) $item->quantity, $item->notes);
            $item->forceFill($this->cartItemPayload($line))->save();
        }

        return $cart->fresh(['items.product.category', 'items.product.baseUnit', 'items.unit']);
    }

    /** @param array<string, mixed> $data */
    public function submitOrder(Customer $customer, User $user, array $data): B2bOrder
    {
        if (($data['idempotency_key'] ?? null) !== null) {
            $existing = B2bOrder::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing instanceof B2bOrder) {
                return $existing->load(['items.product', 'address', 'customer']);
            }
        }

        return DB::transaction(function () use ($customer, $user, $data): B2bOrder {
            $customer = Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $this->assertCustomerActive($customer);

            $cart = B2bCart::query()
                ->where('customer_id', $customer->id)
                ->where('user_id', $user->id)
                ->where('status', B2bCartStatus::ACTIVE->value)
                ->with(['items.product.baseUnit', 'items.unit'])
                ->lockForUpdate()
                ->firstOrFail();

            $cart = $this->refreshCart($cart, $customer, $user);
            if ($cart->items->isEmpty()) {
                throw ServiceException::validation('Keranjang masih kosong.');
            }

            $address = null;
            if (isset($data['customer_address_id'])) {
                $address = CustomerAddress::query()
                    ->where('customer_id', $customer->id)
                    ->whereKey((int) $data['customer_address_id'])
                    ->firstOrFail();
            }

            $totals = $this->cartTotals($cart->items);
            $shippingCost = Decimal::normalize($data['shipping_cost_amount'] ?? 0, 2);
            $grandTotal = Decimal::add($totals['grand_total'], $shippingCost, 2);
            if (Decimal::compare($grandTotal, (string) $customer->minimum_order, 2) < 0) {
                throw ServiceException::validation('Total order belum memenuhi minimum order pelanggan.');
            }

            $paymentPreference = (string) ($data['payment_preference'] ?? 'credit');
            $afterCredit = Decimal::add((string) $customer->receivable_balance, $grandTotal, 2);
            if ($paymentPreference === 'credit' && Decimal::compare((string) $customer->credit_limit, '0', 2) > 0 && Decimal::compare($afterCredit, (string) $customer->credit_limit, 2) > 0) {
                throw ServiceException::validation('Order melebihi limit kredit pelanggan.');
            }

            $order = B2bOrder::query()->create([
                'number' => $this->numbers->next('order'),
                'customer_id' => $customer->id,
                'requested_by' => $user->id,
                'customer_address_id' => $address?->id,
                'status' => B2bOrderStatus::PENDING_CONFIRMATION,
                'requested_delivery_date' => $data['requested_delivery_date'] ?? null,
                'delivery_method' => $data['delivery_method'] ?? 'courier',
                'courier_name' => $data['courier_name'] ?? null,
                'payment_preference' => $paymentPreference,
                'terms_accepted' => (bool) ($data['terms_accepted'] ?? false),
                'subtotal_amount' => $totals['subtotal'],
                'discount_amount' => '0.00',
                'tax_amount' => '0.00',
                'shipping_cost_amount' => $shippingCost,
                'grand_total_amount' => $grandTotal,
                'credit_limit_snapshot' => $customer->credit_limit,
                'receivable_balance_snapshot' => $customer->receivable_balance,
                'notes' => $data['notes'] ?? null,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'submitted_at' => now(),
            ]);

            foreach ($cart->items as $item) {
                $product = $item->product;
                $priceMetadata = $item->getAttribute('price_metadata');
                $price = is_array($priceMetadata) ? $priceMetadata : [];
                $unitFactor = is_scalar($price['unit_factor'] ?? null) ? (string) $price['unit_factor'] : '1.000000';
                $minimumPrice = is_scalar($price['minimum_price'] ?? null) ? (string) $price['minimum_price'] : '0.00';
                $order->items()->create([
                    'product_id' => $item->product_id,
                    'unit_id' => $item->unit_id,
                    'sku_snapshot' => $product->sku,
                    'product_name_snapshot' => $product->name,
                    'unit_name_snapshot' => $item->unit->name,
                    'conversion_factor_snapshot' => $unitFactor,
                    'quantity' => $item->quantity,
                    'base_quantity' => $item->base_quantity,
                    'minimum_price_snapshot' => $minimumPrice,
                    'selected_price' => $item->price_snapshot,
                    'line_total' => $item->line_total,
                    'price_source' => $item->price_source,
                    'available_stock_snapshot' => $this->availableBaseForProduct($product),
                    'price_snapshot' => $price,
                    'notes' => $item->notes,
                ]);
            }

            $cart->forceFill(['status' => B2bCartStatus::SUBMITTED])->save();

            B2bOrderStatusHistory::query()->create([
                'b2b_order_id' => $order->id,
                'from_status' => null,
                'to_status' => B2bOrderStatus::PENDING_CONFIRMATION->value,
                'actor_user_id' => $user->id,
                'note' => 'Order dibuat dari checkout portal langganan.',
                'metadata' => ['payment_preference' => $paymentPreference, 'delivery_method' => $data['delivery_method'] ?? 'courier'],
            ]);
            event(new B2bOrderStatusChanged($order, null, B2bOrderStatus::PENDING_CONFIRMATION, $user, 'Order dibuat dari checkout portal langganan.'));
            activity()->causedBy($user)->performedOn($order)->withProperties(['customer_id' => $customer->id])->log('b2b.order.submitted');

            return $order->fresh(['items.product', 'address', 'customer']);
        });
    }

    /**
     * @param  EloquentCollection<int, B2bCartItem>  $items
     * @return array{subtotal: string, grand_total: string}
     */
    public function cartTotals(EloquentCollection $items): array
    {
        $subtotal = '0.00';
        foreach ($items as $item) {
            $subtotal = Decimal::add($subtotal, (string) $item->line_total, 2);
        }

        return ['subtotal' => $subtotal, 'grand_total' => $subtotal];
    }

    public function availabilityLabel(Product $product, string|int|float $baseQuantity): string
    {
        $available = $this->availableBaseForProduct($product);

        if (Decimal::compare($available, $baseQuantity, 4) >= 0) {
            return 'available';
        }

        return Decimal::compare($available, '0', 4) > 0 ? 'limited' : 'out_of_stock';
    }

    public function availableBaseForProduct(Product $product): string
    {
        $available = '0.0000';
        Stock::query()
            ->where('product_id', $product->id)
            ->get()
            ->each(function (Stock $stock) use (&$available): void {
                $available = Decimal::add($available, (string) $stock->available_quantity, 4);
            });

        return $available;
    }

    private function assertCustomerActive(Customer $customer): void
    {
        if (! $customer->is_active || $customer->getRawOriginal('verification_status') !== CustomerStatus::ACTIVE->value || $customer->getRawOriginal('account_status') !== CustomerStatus::ACTIVE->value) {
            throw ServiceException::validation('Akun pelanggan belum aktif, belum terverifikasi, atau sedang dibekukan.');
        }
    }

    /**
     * @return array{product: Product, unit: Unit, quantity: string, base_quantity: string, selected_price: string, line_total: string, price_source: ?string, availability: string, price: array<string, mixed>, notes: ?string}
     */
    private function buildLine(Customer $customer, User $user, int $productId, int $unitId, mixed $quantity, ?string $notes = null): array
    {
        $product = Product::query()->with(['baseUnit', 'units.unit'])->where('status', 'active')->findOrFail($productId);
        $unit = Unit::query()->whereKey($unitId)->where('is_active', true)->firstOrFail();
        $quantity = Decimal::normalize($quantity, 4);
        if (Decimal::compare($quantity, '0', 4) <= 0) {
            throw ServiceException::validation('Qty harus lebih besar dari nol.');
        }

        $price = $this->prices->resolve($product, quantity: $quantity, unitId: $unit->id, customer: $customer, channel: 'b2b', user: $user);
        if ($price['approval_required'] === true) {
            throw ServiceException::validation('Harga produk membutuhkan approval dan belum bisa dimasukkan ke keranjang.');
        }

        $baseQuantity = (string) $price['quantity_base'];
        if (Decimal::compare($baseQuantity, (string) $product->minimum_order, 4) < 0) {
            throw ServiceException::validation('Qty belum memenuhi minimum order produk.');
        }

        $selectedPrice = (string) $price['selected_price'];
        $lineTotal = Decimal::mul($quantity, $selectedPrice, 4, 2, 2);

        return [
            'product' => $product,
            'unit' => $unit,
            'quantity' => $quantity,
            'base_quantity' => $baseQuantity,
            'selected_price' => $selectedPrice,
            'line_total' => $lineTotal,
            'price_source' => is_string($price['selected_source'] ?? null) ? $price['selected_source'] : null,
            'availability' => $this->availabilityLabel($product, $baseQuantity),
            'price' => $price,
            'notes' => $notes,
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function cartItemPayload(array $line): array
    {
        return [
            'product_id' => $line['product']->id,
            'unit_id' => $line['unit']->id,
            'quantity' => $line['quantity'],
            'base_quantity' => $line['base_quantity'],
            'price_snapshot' => $line['selected_price'],
            'line_total' => $line['line_total'],
            'price_source' => $line['price_source'],
            'availability_snapshot' => $line['availability'],
            'price_metadata' => $line['price'],
            'notes' => $line['notes'],
        ];
    }
}
