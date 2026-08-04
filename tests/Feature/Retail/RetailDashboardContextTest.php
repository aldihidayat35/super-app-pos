<?php

namespace Tests\Feature\Retail;

use App\Enums\CashShiftStatus;
use App\Enums\PaymentMethod;
use App\Enums\PosSaleStatus;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\Product;
use App\Models\SalePayment;
use App\Models\Stock;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkLocation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RetailDashboardContextTest extends TestCase
{
    use RefreshDatabase;

    private WorkLocation $firstLocation;

    private WorkLocation $secondLocation;

    private Branch $firstBranch;

    private Branch $secondBranch;

    private User $cashier;

    private Product $firstProduct;

    private Product $secondProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Cache::flush();

        $warehouseLocation = WorkLocation::factory()->create(['type' => 'warehouse']);
        $warehouse = Warehouse::factory()->create(['work_location_id' => $warehouseLocation->id]);
        $this->firstLocation = WorkLocation::factory()->create(['type' => 'branch', 'code' => 'TKO-PUSAT', 'name' => 'Toko Pusat']);
        $this->secondLocation = WorkLocation::factory()->create(['type' => 'branch', 'code' => 'TKO-TIMUR', 'name' => 'Toko Timur']);
        $this->firstBranch = Branch::factory()->create([
            'work_location_id' => $this->firstLocation->id,
            'primary_warehouse_id' => $warehouse->id,
            'code' => 'TKO-PUSAT',
            'name' => 'Toko Pusat',
            'sales_target' => '1000000.00',
        ]);
        $this->secondBranch = Branch::factory()->create([
            'work_location_id' => $this->secondLocation->id,
            'primary_warehouse_id' => $warehouse->id,
            'code' => 'TKO-TIMUR',
            'name' => 'Toko Timur',
            'sales_target' => '2000000.00',
        ]);

        $this->cashier = $this->userWithRole('kasir');
        $unit = Unit::factory()->create();
        $this->firstProduct = Product::factory()->create([
            'base_unit_id' => $unit->id,
            'name' => 'Produk Toko Pusat',
            'minimum_stock' => '10.0000',
        ]);
        $this->secondProduct = Product::factory()->create([
            'base_unit_id' => $unit->id,
            'name' => 'Produk Toko Timur',
            'minimum_stock' => '5.0000',
        ]);

        Stock::query()->create([
            'product_id' => $this->firstProduct->id,
            'work_location_id' => $this->firstLocation->id,
            'location_scope_key' => 'work:'.$this->firstLocation->id,
            'quantity_on_hand' => '15.0000',
            'quantity_reserved' => '5.0000',
            'quantity_damaged' => '2.0000',
            'cost_value' => '150000.00',
        ]);
        Stock::query()->create([
            'product_id' => $this->secondProduct->id,
            'work_location_id' => $this->secondLocation->id,
            'location_scope_key' => 'work:'.$this->secondLocation->id,
            'quantity_on_hand' => '40.0000',
            'quantity_reserved' => '4.0000',
            'quantity_damaged' => '1.0000',
            'cost_value' => '800000.00',
        ]);

        $this->createSale($this->firstBranch, $this->firstLocation, $this->firstProduct, 'POS-PUSAT-001', '100000.00', '20000.00');
        $this->createSale($this->secondBranch, $this->secondLocation, $this->secondProduct, 'POS-TIMUR-001', '400000.00', '80000.00');
    }

    public function test_unrestricted_user_can_select_every_accessible_branch(): void
    {
        $owner = $this->userWithRole('owner_approver');

        $this->actingAs($owner)
            ->get(route('retail.dashboard'))
            ->assertOk()
            ->assertSee('id="retail-dashboard-selector"', false)
            ->assertSee('TKO-PUSAT')
            ->assertSee('TKO-TIMUR');
    }

    public function test_unrestricted_admin_can_open_retail_dashboard(): void
    {
        $admin = $this->userWithRole('admin_config');

        $this->actingAs($admin)
            ->get(route('retail.dashboard'))
            ->assertOk()
            ->assertSee('id="retail-dashboard-selector"', false)
            ->assertSee('TKO-PUSAT')
            ->assertSee('TKO-TIMUR');
    }

    public function test_store_worker_with_one_assignment_uses_fixed_branch_context(): void
    {
        $head = $this->userWithRole('kepala_toko');
        $head->workLocations()->sync([$this->firstLocation->id => ['is_default' => true, 'is_active' => true]]);

        $this->actingAs($head)
            ->get(route('retail.dashboard'))
            ->assertOk()
            ->assertDontSee('id="retail-dashboard-selector"', false)
            ->assertSee('Konteks otomatis dari penugasan lokasi kerja')
            ->assertSee('TKO-PUSAT — Toko Pusat');
    }

    public function test_scoped_user_with_multiple_branch_assignments_can_select_context(): void
    {
        $head = $this->userWithRole('kepala_toko');
        $head->workLocations()->sync([
            $this->firstLocation->id => ['is_default' => true, 'is_active' => true],
            $this->secondLocation->id => ['is_default' => false, 'is_active' => true],
        ]);

        $this->actingAs($head)
            ->get(route('retail.dashboard'))
            ->assertOk()
            ->assertSee('id="retail-dashboard-selector"', false)
            ->assertSee('TKO-PUSAT')
            ->assertSee('TKO-TIMUR');
    }

    public function test_ajax_payload_and_widgets_follow_selected_branch(): void
    {
        $owner = $this->userWithRole('owner_approver');

        $this->actingAs($owner)
            ->getJson(route('retail.dashboard.data', ['branch_id' => $this->firstBranch->id]))
            ->assertOk()
            ->assertJsonPath('branch_id', $this->firstBranch->id)
            ->assertJsonPath('branch_name', 'Toko Pusat')
            ->assertJsonPath('kpis.revenue', '100000.00')
            ->assertJsonPath('kpis.margin', '20000.00')
            ->assertJsonPath('kpis.transaction_count', 1)
            ->assertJsonPath('kpis.available_stock', '8.0000')
            ->assertJsonPath('kpis.critical_stock_count', 1)
            ->assertJsonPath('kpis.sales_target', '1000000.00')
            ->assertJsonPath('kpis.target_achievement', '10.00')
            ->assertSee('POS-PUSAT-001')
            ->assertSee('Produk Toko Pusat')
            ->assertDontSee('POS-TIMUR-001')
            ->assertDontSee('Produk Toko Timur');

        $this->actingAs($owner)
            ->getJson(route('retail.dashboard.data', ['branch_id' => $this->secondBranch->id]))
            ->assertOk()
            ->assertJsonPath('kpis.revenue', '400000.00')
            ->assertJsonPath('kpis.available_stock', '35.0000')
            ->assertJsonPath('kpis.critical_stock_count', 0)
            ->assertJsonPath('kpis.target_achievement', '20.00')
            ->assertSee('POS-TIMUR-001')
            ->assertDontSee('POS-PUSAT-001');
    }

    public function test_server_rejects_branch_outside_user_assignment(): void
    {
        $head = $this->userWithRole('kepala_toko');
        $head->workLocations()->sync([$this->firstLocation->id => ['is_default' => true, 'is_active' => true]]);

        $this->actingAs($head)
            ->getJson(route('retail.dashboard.data', ['branch_id' => $this->secondBranch->id]))
            ->assertForbidden();
    }

    public function test_sensitive_margin_and_stock_value_are_not_sent_without_permission(): void
    {
        $head = $this->userWithRole('kepala_toko');
        $head->workLocations()->sync([$this->firstLocation->id => ['is_default' => true, 'is_active' => true]]);

        $response = $this->actingAs($head)
            ->getJson(route('retail.dashboard.data', ['branch_id' => $this->firstBranch->id]))
            ->assertOk()
            ->assertJsonMissingPath('kpis.margin')
            ->assertJsonMissingPath('kpis.margin_percent')
            ->assertJsonMissingPath('kpis.stock_value')
            ->assertDontSee('Margin aktual berdasarkan snapshot HPP.');

        $this->assertArrayNotHasKey('margin', $response->json('kpis'));
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(Role::findOrCreate($role));

        return $user;
    }

    private function createSale(Branch $branch, WorkLocation $location, Product $product, string $number, string $amount, string $margin): PosSale
    {
        $shift = CashShift::query()->create([
            'number' => 'SHIFT-'.$number,
            'branch_id' => $branch->id,
            'work_location_id' => $location->id,
            'cashier_user_id' => $this->cashier->id,
            'status' => CashShiftStatus::OPEN->value,
            'opening_cash_amount' => '100000.00',
            'opened_at' => now('Asia/Jakarta')->subHour(),
        ]);
        $sale = PosSale::query()->create([
            'number' => $number,
            'branch_id' => $branch->id,
            'work_location_id' => $location->id,
            'cash_shift_id' => $shift->id,
            'cashier_user_id' => $this->cashier->id,
            'status' => PosSaleStatus::COMPLETED->value,
            'subtotal_amount' => $amount,
            'grand_total_amount' => $amount,
            'paid_amount' => $amount,
            'total_margin_amount' => $margin,
            'completed_at' => now('Asia/Jakarta'),
        ]);
        PosSaleItem::query()->create([
            'pos_sale_id' => $sale->id,
            'product_id' => $product->id,
            'unit_id' => $product->base_unit_id,
            'sku_snapshot' => $product->sku,
            'product_name_snapshot' => $product->name,
            'unit_name_snapshot' => $product->baseUnit->name,
            'conversion_factor_snapshot' => '1.000000',
            'quantity' => '1.0000',
            'base_quantity' => '1.0000',
            'selected_price' => $amount,
            'line_total' => $amount,
            'margin_amount' => $margin,
            'price_source' => 'test',
        ]);
        SalePayment::query()->create([
            'pos_sale_id' => $sale->id,
            'method' => PaymentMethod::CASH->value,
            'amount' => $amount,
        ]);

        return $sale;
    }
}
