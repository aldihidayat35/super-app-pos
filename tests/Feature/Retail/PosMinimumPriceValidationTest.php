<?php

namespace Tests\Feature\Retail;

use App\Enums\ProductPriceStatus;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Stock;
use App\Models\User;
use App\Models\WorkLocation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PosMinimumPriceValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Branch $branch;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $location = WorkLocation::factory()->create();
        $this->branch = Branch::factory()->create([
            'work_location_id' => $location->id,
            'name' => 'Toko Cabang Test',
        ]);

        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole(Role::findOrCreate('kasir'));
        $this->cashier->workLocations()->attach($location->id);

        CashShift::create([
            'number' => 'SHIFT-VAL-01',
            'cashier_user_id' => $this->cashier->id,
            'branch_id' => $this->branch->id,
            'work_location_id' => $location->id,
            'opened_at' => now(),
            'starting_cash' => '100000.00',
            'status' => 'open',
        ]);

        $this->product = Product::factory()->create([
            'sku' => 'VAL-001',
            'name' => 'Barang Validasi Min',
            'cost_price' => '10000.00',
            'minimum_price' => '10000.00',
        ]);

        Stock::create([
            'product_id' => $this->product->id,
            'work_location_id' => $location->id,
            'location_scope_key' => 'work:'.$location->id,
            'quantity_on_hand' => '50.0000',
            'quantity_reserved' => '0.0000',
            'quantity_damaged' => '0.0000',
        ]);

        // Product Price with minimum price 15.000
        ProductPrice::create([
            'product_id' => $this->product->id,
            'work_location_id' => $location->id,
            'channel' => 'pos',
            'price_ring' => 'Ring POS',
            'min_price' => '15000.00',
            'recommended_price' => '15000.00',
            'max_price' => '15000.00',
            'minimum_qty' => 1,
            'status' => ProductPriceStatus::ACTIVE,
        ]);
    }

    public function test_pos_quote_at_or_above_minimum_price_is_safe_without_overpricing(): void
    {
        $this->actingAs($this->cashier);

        // Quote with price higher than recommended (e.g. 25.000 > 15.000)
        $res = $this->postJson(route('retail.pos.quote'), [
            'product_id' => $this->product->id,
            'quantity' => 1,
            'selected_price' => '25000.00',
        ]);

        $res->assertOk();
        $res->assertJsonPath('item.pricing.minimum_price', '15000.00');
        $res->assertJsonPath('item.pricing.selected_price', '25000.00');
        $res->assertJsonPath('item.pricing.approval_required', false);
        $res->assertJsonPath('item.pricing.status', 'safe');
        $res->assertJsonPath('item.pricing.status_label', 'Aman');
    }

    public function test_pos_quote_below_minimum_price_triggers_below_minimum_status(): void
    {
        $this->actingAs($this->cashier);

        // Quote with price below minimum (e.g. 12.000 < 15.000)
        $res = $this->postJson(route('retail.pos.quote'), [
            'product_id' => $this->product->id,
            'quantity' => 1,
            'selected_price' => '12000.00',
        ]);

        $res->assertOk();
        $res->assertJsonPath('item.pricing.minimum_price', '15000.00');
        $res->assertJsonPath('item.pricing.selected_price', '12000.00');
        $res->assertJsonPath('item.pricing.approval_required', true);
        $res->assertJsonPath('item.pricing.status', 'below');
        $res->assertJsonPath('item.pricing.status_label', 'Di bawah minimum');
        $this->assertContains('below_minimum', $res->json('item.pricing.approval_reasons'));
    }

    public function test_pos_checkout_rejects_sale_below_minimum_price_with_clear_message(): void
    {
        $this->actingAs($this->cashier);

        $res = $this->postJson(route('retail.pos.store'), [
            'idempotency_key' => (string) str()->uuid(),
            'branch_id' => $this->branch->id,
            'customer_id' => null,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1,
                    'unit_id' => $this->product->base_unit_id,
                    'selected_price' => '11000.00', // below 15.000
                    'discount_percent' => 0,
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => '11000.00',
                ],
            ],
            'paid_amount' => '11000.00',
        ]);

        $res->assertStatus(422);
        $this->assertStringContainsString('di bawah harga minimum', $res->json('message'));
    }
}
