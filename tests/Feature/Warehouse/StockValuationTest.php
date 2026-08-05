<?php

namespace Tests\Feature\Warehouse;

use App\Models\Product;
use App\Models\Stock;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkLocation;
use App\Support\CurrencyFormatter;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StockValuationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Product $product;

    private Stock $stock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->assignRole(Role::findOrCreate('owner_approver'));

        $location = WorkLocation::factory()->create(['type' => 'warehouse']);
        $this->product = Product::factory()->create([
            'base_unit_id' => Unit::factory()->create()->id,
            'cost_price' => '25000.00',
            'status' => 'active',
        ]);
        $this->stock = Stock::query()->create([
            'product_id' => $this->product->id,
            'work_location_id' => $location->id,
            'location_scope_key' => 'work:'.$location->id,
            'quantity_on_hand' => '100.0000',
            'quantity_reserved' => '30.0000',
            'quantity_damaged' => '20.0000',
            'cost_value' => '0.00',
        ]);
    }

    public function test_inventory_value_uses_on_hand_times_current_product_hpp(): void
    {
        $this->assertSame('2500000.00', $this->stock->fresh('product')->inventory_value);

        $this->product->update(['cost_price' => '30000.00']);

        $this->assertSame('3000000.00', $this->stock->fresh('product')->inventory_value);
    }

    public function test_reserved_and_damaged_do_not_change_inventory_value_basis(): void
    {
        $this->stock->update([
            'quantity_reserved' => '80.0000',
            'quantity_damaged' => '20.0000',
        ]);

        $this->assertSame('2500000.00', $this->stock->fresh('product')->inventory_value);
        $this->assertSame('0.0000', $this->stock->fresh()->available_quantity);
    }

    public function test_stock_page_and_csv_use_the_same_inventory_value(): void
    {
        $this->actingAs($this->user)
            ->get(route('warehouse.stocks.index'))
            ->assertOk()
            ->assertSee(CurrencyFormatter::rupiah('2500000.00'));

        $response = $this->actingAs($this->user)->get(route('warehouse.stocks.export'));

        $response->assertOk();
        $this->assertStringContainsString('2500000.00', $response->streamedContent());
    }
}
