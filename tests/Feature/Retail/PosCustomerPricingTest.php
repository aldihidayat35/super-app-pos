<?php

namespace Tests\Feature\Retail;

use App\Enums\CustomerType;
use App\Enums\ProductPriceStatus;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Stock;
use App\Models\User;
use App\Models\WorkLocation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PosCustomerPricingTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Branch $branch;

    private CashShift $shift;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $location = WorkLocation::factory()->create();
        $this->branch = Branch::factory()->create([
            'work_location_id' => $location->id,
            'name' => 'Toko Demo',
        ]);

        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole(Role::findOrCreate('kasir'));
        $this->cashier->workLocations()->attach($location->id);

        $this->shift = CashShift::create([
            'number' => 'SHIFT-TEST-01',
            'cashier_user_id' => $this->cashier->id,
            'branch_id' => $this->branch->id,
            'work_location_id' => $location->id,
            'opened_at' => now(),
            'starting_cash' => '100000.00',
            'status' => 'open',
        ]);

        $this->product = Product::factory()->create([
            'sku' => 'TEST-001',
            'name' => 'Barang Uji',
            'cost_price' => '10000.00',
            'minimum_price' => '10000.00',
        ]);

        Stock::create([
            'product_id' => $this->product->id,
            'work_location_id' => $location->id,
            'location_scope_key' => 'work:'.$location->id,
            'quantity_on_hand' => '100.0000',
            'quantity_reserved' => '0.0000',
            'quantity_damaged' => '0.0000',
        ]);

        // Ring Retail (Channel: retail)
        ProductPrice::create([
            'product_id' => $this->product->id,
            'branch_id' => null,
            'work_location_id' => $location->id,
            'channel' => 'retail',
            'price_ring' => 'Ring Retail',
            'min_price' => '18000.00',
            'recommended_price' => '20000.00',
            'max_price' => '22000.00',
            'minimum_qty' => 1,
            'priority' => 10,
            'status' => ProductPriceStatus::ACTIVE,
        ]);

        // Ring POS (Channel: pos)
        ProductPrice::create([
            'product_id' => $this->product->id,
            'branch_id' => null,
            'work_location_id' => $location->id,
            'channel' => 'pos',
            'price_ring' => 'Ring POS',
            'min_price' => '25000.00',
            'recommended_price' => '30000.00',
            'max_price' => '35000.00',
            'minimum_qty' => 1,
            'priority' => 10,
            'status' => ProductPriceStatus::ACTIVE,
        ]);

        // Ring B2B (Channel: b2b)
        ProductPrice::create([
            'product_id' => $this->product->id,
            'branch_id' => null,
            'work_location_id' => $location->id,
            'channel' => 'b2b',
            'price_ring' => 'Ring B2B',
            'min_price' => '14000.00',
            'recommended_price' => '15000.00',
            'max_price' => '17000.00',
            'minimum_qty' => 1,
            'priority' => 10,
            'status' => ProductPriceStatus::ACTIVE,
        ]);
    }

    public function test_pos_quote_uses_ring_pos_for_general_customer(): void
    {
        $this->actingAs($this->cashier);

        $res = $this->postJson(route('retail.pos.quote'), [
            'product_id' => $this->product->id,
            'customer_id' => null,
            'quantity' => 1,
        ]);

        $res->assertOk();
        $res->assertJsonPath('item.pricing.ring', 'Ring POS');
        $res->assertJsonPath('item.pricing.selected_price', '30000.00');
        $res->assertJsonPath('item.pricing.minimum_price', '25000.00');
        $res->assertJsonPath('item.pricing.maximum_price', '35000.00');
    }

    public function test_pos_quote_automatically_uses_ring_retail_for_retail_customer(): void
    {
        $customer = Customer::factory()->create([
            'business_name' => 'Pelanggan Retail Toko',
            'type' => CustomerType::RETAIL_CREDIT,
            'price_category' => 'retail',
        ]);

        $this->actingAs($this->cashier);

        $res = $this->postJson(route('retail.pos.quote'), [
            'product_id' => $this->product->id,
            'customer_id' => $customer->id,
            'quantity' => 1,
        ]);

        $res->assertOk();
        $res->assertJsonPath('item.pricing.ring', 'Ring Retail');
        $res->assertJsonPath('item.pricing.selected_price', '20000.00');
        $res->assertJsonPath('item.pricing.minimum_price', '18000.00');
        $res->assertJsonPath('item.pricing.maximum_price', '22000.00');
    }

    public function test_pos_quote_automatically_uses_ring_b2b_for_b2b_customer(): void
    {
        $customer = Customer::factory()->create([
            'business_name' => 'Pelanggan B2B Grosir',
            'type' => CustomerType::B2B,
            'price_category' => 'b2b',
        ]);

        $this->actingAs($this->cashier);

        $res = $this->postJson(route('retail.pos.quote'), [
            'product_id' => $this->product->id,
            'customer_id' => $customer->id,
            'quantity' => 1,
        ]);

        $res->assertOk();
        $res->assertJsonPath('item.pricing.ring', 'Ring B2B');
        $res->assertJsonPath('item.pricing.selected_price', '15000.00');
        $res->assertJsonPath('item.pricing.minimum_price', '14000.00');
        $res->assertJsonPath('item.pricing.maximum_price', '17000.00');
    }
}
