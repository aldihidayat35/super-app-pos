<?php

namespace Tests\Feature\B2B;

use App\Enums\B2bOrderStatus;
use App\Enums\CustomerStatus;
use App\Enums\ProductPriceStatus;
use App\Models\B2bOrder;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerPriceOverride;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Stock;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkLocation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class B2bPortalTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Customer $customer;

    private Product $product;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->owner = User::factory()->create(['email' => 'portal-owner@example.test', 'username' => 'portalowner']);
        $this->owner->assignRole(Role::findOrCreate('langganan_owner'));
        $this->customer = Customer::factory()->create(['price_category' => 'grosir', 'minimum_order' => 10000, 'credit_limit' => 1000000, 'receivable_balance' => 0]);
        $this->customer->users()->attach($this->owner->id, ['role' => 'langganan_owner', 'is_active' => true]);
        CustomerAddress::query()->create(['customer_id' => $this->customer->id, 'label' => 'Gudang', 'recipient_name' => 'Budi', 'phone_number' => '08123456789', 'address' => 'Jl. Pelanggan', 'city' => 'Jakarta', 'is_primary' => true, 'primary_scope' => 'primary']);

        $this->unit = Unit::factory()->create(['name' => 'Pcs', 'symbol' => 'pcs']);
        $this->product = Product::factory()->create(['base_unit_id' => $this->unit->id, 'name' => 'Produk Langganan', 'cost_price' => 5000, 'minimum_price' => 6000, 'minimum_order' => 2]);
        ProductPrice::query()->create([
            'product_id' => $this->product->id,
            'channel' => 'b2b',
            'price_ring' => 'grosir',
            'customer_category' => 'grosir',
            'recommended_price' => 10000,
            'minimum_qty' => 1,
            'status' => ProductPriceStatus::ACTIVE,
        ]);
        Stock::query()->create([
            'product_id' => $this->product->id,
            'work_location_id' => WorkLocation::factory()->create(['type' => 'warehouse'])->id,
            'location_scope_key' => 'warehouse:testing',
            'quantity_on_hand' => 20,
            'quantity_reserved' => 2,
            'quantity_damaged' => 1,
            'cost_value' => 100000,
        ]);
    }

    public function test_b2b_user_can_login_to_portal_and_is_redirected_from_internal_area(): void
    {
        $this->post(route('langganan.login.store'), [
            'login' => 'portalowner',
            'password' => 'password',
        ])->assertRedirect(route('langganan.katalog.index'));

        $this->actingAs($this->owner)->get(route('dashboard'))
            ->assertRedirect(route('langganan.dashboard'));
    }

    public function test_catalog_uses_customer_price_and_hides_sensitive_cost(): void
    {
        CustomerPriceOverride::query()->create([
            'customer_id' => $this->customer->id,
            'product_id' => $this->product->id,
            'channel' => 'b2b',
            'price' => 9000,
            'minimum_qty' => 1,
            'priority' => 1,
            'status' => 'approved',
            'starts_at' => now()->toDateString(),
            'is_active' => true,
        ]);

        $this->actingAs($this->owner)->get(route('langganan.katalog.index'))
            ->assertOk()
            ->assertSee('Produk Langganan')
            ->assertSee('Rp9.000')
            ->assertDontSeeText('HPP')
            ->assertDontSeeText('Margin');
    }

    public function test_moq_cart_price_refresh_and_checkout_create_scoped_order(): void
    {
        $this->actingAs($this->owner)->post(route('langganan.keranjang.add'), [
            'product_id' => $this->product->id,
            'unit_id' => $this->unit->id,
            'quantity' => 1,
        ])->assertSessionHasErrors('cart');

        $this->actingAs($this->owner)->post(route('langganan.keranjang.add'), [
            'product_id' => $this->product->id,
            'unit_id' => $this->unit->id,
            'quantity' => 2,
        ])->assertRedirect(route('langganan.keranjang.index'));

        ProductPrice::query()->where('product_id', $this->product->id)->update(['recommended_price' => 12000]);

        $this->actingAs($this->owner)->get(route('langganan.keranjang.index'))
            ->assertOk()
            ->assertSee('Rp12.000');

        $address = $this->customer->addresses()->firstOrFail();
        $this->actingAs($this->owner)->post(route('langganan.checkout.store'), [
            'customer_address_id' => $address->id,
            'delivery_method' => 'courier',
            'payment_preference' => 'credit',
            'terms_accepted' => 1,
            'idempotency_key' => 'checkout-test-1',
        ])->assertRedirect();

        $this->assertDatabaseHas('b2b_orders', [
            'customer_id' => $this->customer->id,
            'status' => B2bOrderStatus::PENDING_CONFIRMATION->value,
            'grand_total_amount' => 24000,
        ]);
        $this->assertDatabaseHas('b2b_order_items', [
            'product_id' => $this->product->id,
            'selected_price' => 12000,
            'available_stock_snapshot' => 17,
        ]);
    }

    public function test_customer_isolation_and_address_ownership_are_enforced(): void
    {
        $otherCustomer = Customer::factory()->create();
        $otherUser = User::factory()->create();
        $otherUser->assignRole(Role::findOrCreate('langganan_owner'));
        $otherCustomer->users()->attach($otherUser->id, ['role' => 'langganan_owner', 'is_active' => true]);
        $order = B2bOrder::query()->create([
            'number' => 'ORD/TEST/00001',
            'customer_id' => $this->customer->id,
            'requested_by' => $this->owner->id,
            'status' => B2bOrderStatus::PENDING_CONFIRMATION,
            'grand_total_amount' => 10000,
            'submitted_at' => now(),
        ]);

        $this->actingAs($otherUser)->get(route('langganan.orders.show', $order))->assertForbidden();

        $otherAddress = CustomerAddress::query()->create(['customer_id' => $otherCustomer->id, 'label' => 'Luar', 'address' => 'Alamat Luar']);
        $this->actingAs($this->owner)->post(route('langganan.keranjang.add'), [
            'product_id' => $this->product->id,
            'unit_id' => $this->unit->id,
            'quantity' => 2,
        ]);
        $this->actingAs($this->owner)->post(route('langganan.checkout.store'), [
            'customer_address_id' => $otherAddress->id,
            'delivery_method' => 'courier',
            'payment_preference' => 'credit',
            'terms_accepted' => 1,
            'idempotency_key' => 'checkout-test-address',
        ])->assertNotFound();
    }

    public function test_inactive_or_blocked_customer_cannot_access_portal(): void
    {
        $this->customer->forceFill(['account_status' => CustomerStatus::FROZEN])->save();

        $this->actingAs($this->owner)->get(route('langganan.katalog.index'))->assertForbidden();
    }
}
