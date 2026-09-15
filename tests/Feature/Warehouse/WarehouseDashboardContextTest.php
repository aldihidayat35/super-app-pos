<?php

namespace Tests\Feature\Warehouse;

use App\Enums\GoodsReceiptStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMutationType;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Stock;
use App\Models\StockMutation;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkLocation;
use App\Services\Reports\ReportMetricService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WarehouseDashboardContextTest extends TestCase
{
    use RefreshDatabase;

    private WorkLocation $firstLocation;

    private WorkLocation $secondLocation;

    private Warehouse $firstWarehouse;

    private Warehouse $secondWarehouse;

    private Stock $firstStock;

    private Stock $secondStock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Cache::flush();

        $this->firstLocation = WorkLocation::factory()->create([
            'type' => 'warehouse',
            'code' => 'GDG-UTAMA',
            'name' => 'Gudang Utama',
        ]);
        $this->secondLocation = WorkLocation::factory()->create([
            'type' => 'warehouse',
            'code' => 'GDG-TIMUR',
            'name' => 'Gudang Timur',
        ]);
        $this->firstWarehouse = Warehouse::factory()->create([
            'work_location_id' => $this->firstLocation->id,
            'code' => 'GDG-UTAMA',
            'name' => 'Gudang Utama',
        ]);
        $this->secondWarehouse = Warehouse::factory()->create([
            'work_location_id' => $this->secondLocation->id,
            'code' => 'GDG-TIMUR',
            'name' => 'Gudang Timur',
        ]);

        $unit = Unit::factory()->create();
        $firstProduct = Product::factory()->create([
            'base_unit_id' => $unit->id,
            'name' => 'Produk Gudang Utama',
            'minimum_stock' => '10.0000',
            'cost_price' => '10000.00',
        ]);
        $secondProduct = Product::factory()->create([
            'base_unit_id' => $unit->id,
            'name' => 'Produk Gudang Timur',
            'minimum_stock' => '5.0000',
            'cost_price' => '20000.00',
        ]);

        $this->firstStock = Stock::query()->create([
            'product_id' => $firstProduct->id,
            'work_location_id' => $this->firstLocation->id,
            'location_scope_key' => 'work:'.$this->firstLocation->id,
            'quantity_on_hand' => '15.0000',
            'quantity_reserved' => '5.0000',
            'quantity_damaged' => '2.0000',
            'cost_value' => '150000.00',
        ]);
        $this->secondStock = Stock::query()->create([
            'product_id' => $secondProduct->id,
            'work_location_id' => $this->secondLocation->id,
            'location_scope_key' => 'work:'.$this->secondLocation->id,
            'quantity_on_hand' => '40.0000',
            'quantity_reserved' => '4.0000',
            'quantity_damaged' => '1.0000',
            'cost_value' => '800000.00',
        ]);
    }

    public function test_unrestricted_user_can_select_every_accessible_warehouse(): void
    {
        $owner = $this->userWithRole('owner_approver');

        $this->actingAs($owner)
            ->get(route('warehouse.dashboard'))
            ->assertOk()
            ->assertSee('id="warehouse-dashboard-selector"', false)
            ->assertSee('GDG-UTAMA')
            ->assertSee('GDG-TIMUR');
    }

    public function test_worker_with_one_assignment_uses_fixed_warehouse_context(): void
    {
        $worker = $this->userWithRole('staff_gudang');
        $worker->workLocations()->sync([
            $this->firstLocation->id => ['is_default' => true, 'is_active' => true],
        ]);

        $this->actingAs($worker)
            ->get(route('warehouse.dashboard'))
            ->assertOk()
            ->assertDontSee('id="warehouse-dashboard-selector"', false)
            ->assertSee('Konteks otomatis dari penugasan lokasi kerja')
            ->assertSee('GDG-UTAMA — Gudang Utama');
    }

    public function test_scoped_user_with_multiple_warehouse_assignments_can_select_context(): void
    {
        $worker = $this->userWithRole('staff_gudang');
        $worker->workLocations()->sync([
            $this->firstLocation->id => ['is_default' => true, 'is_active' => true],
            $this->secondLocation->id => ['is_default' => false, 'is_active' => true],
        ]);

        $this->actingAs($worker)
            ->get(route('warehouse.dashboard'))
            ->assertOk()
            ->assertSee('id="warehouse-dashboard-selector"', false)
            ->assertSee('GDG-UTAMA')
            ->assertSee('GDG-TIMUR');
    }

    public function test_ajax_payload_and_every_stock_kpi_follow_selected_warehouse(): void
    {
        $owner = $this->userWithRole('owner_approver');

        $this->actingAs($owner)
            ->getJson(route('warehouse.dashboard.data', ['warehouse_id' => $this->firstWarehouse->id]))
            ->assertOk()
            ->assertJsonPath('warehouse_id', $this->firstWarehouse->id)
            ->assertJsonPath('warehouse_name', 'Gudang Utama')
            ->assertJsonPath('kpis.total_products', 1)
            ->assertJsonPath('kpis.on_hand_quantity', '15.0000')
            ->assertJsonPath('kpis.available_quantity', '8.0000')
            ->assertJsonPath('kpis.reserved_quantity', '5.0000')
            ->assertJsonPath('kpis.damaged_quantity', '2.0000')
            ->assertJsonPath('kpis.stock_value', '150000.00')
            ->assertSee('Produk Gudang Utama')
            ->assertDontSee('Produk Gudang Timur');

        $this->actingAs($owner)
            ->getJson(route('warehouse.dashboard.data', ['warehouse_id' => $this->secondWarehouse->id]))
            ->assertOk()
            ->assertJsonPath('kpis.on_hand_quantity', '40.0000')
            ->assertJsonPath('kpis.available_quantity', '35.0000')
            ->assertJsonPath('kpis.stock_value', '800000.00')
            ->assertDontSee('Produk Gudang Utama');
    }

    public function test_server_rejects_warehouse_outside_user_assignment(): void
    {
        $worker = $this->userWithRole('staff_gudang');
        $worker->workLocations()->sync([
            $this->firstLocation->id => ['is_default' => true, 'is_active' => true],
        ]);

        $this->actingAs($worker)
            ->getJson(route('warehouse.dashboard.data', ['warehouse_id' => $this->secondWarehouse->id]))
            ->assertForbidden();
    }

    public function test_pending_purchase_orders_and_posted_receipts_follow_active_warehouse(): void
    {
        $owner = $this->userWithRole('owner_approver');
        $supplier = Supplier::factory()->create();

        $firstPo = $this->createPurchaseOrder($this->firstWarehouse, $supplier, $owner, 'PO-UTAMA-001');
        $secondPo = $this->createPurchaseOrder($this->secondWarehouse, $supplier, $owner, 'PO-TIMUR-001');
        $this->createPurchaseOrder($this->secondWarehouse, $supplier, $owner, 'PO-TIMUR-002');

        GoodsReceipt::query()->create([
            'number' => 'GR-UTAMA-001',
            'purchase_order_id' => $firstPo->id,
            'warehouse_id' => $this->firstWarehouse->id,
            'supplier_id' => $supplier->id,
            'received_at' => now('Asia/Jakarta')->toDateString(),
            'received_by' => $owner->id,
            'status' => GoodsReceiptStatus::POSTED->value,
            'posted_at' => now('Asia/Jakarta'),
            'posted_by' => $owner->id,
        ]);
        foreach (['GR-TIMUR-001', 'GR-TIMUR-002'] as $number) {
            GoodsReceipt::query()->create([
                'number' => $number,
                'purchase_order_id' => $secondPo->id,
                'warehouse_id' => $this->secondWarehouse->id,
                'supplier_id' => $supplier->id,
                'received_at' => now('Asia/Jakarta')->toDateString(),
                'received_by' => $owner->id,
                'status' => GoodsReceiptStatus::POSTED->value,
                'posted_at' => now('Asia/Jakarta'),
                'posted_by' => $owner->id,
            ]);
        }

        $this->actingAs($owner)
            ->getJson(route('warehouse.dashboard.data', ['warehouse_id' => $this->firstWarehouse->id]))
            ->assertOk()
            ->assertJsonPath('kpis.pending_po', 1)
            ->assertJsonPath('kpis.posted_receipts', 1);

        $this->actingAs($owner)
            ->getJson(route('warehouse.dashboard.data', ['warehouse_id' => $this->secondWarehouse->id]))
            ->assertOk()
            ->assertJsonPath('kpis.pending_po', 2)
            ->assertJsonPath('kpis.posted_receipts', 2);
    }

    public function test_activity_data_and_report_link_follow_warehouse_and_date_filters(): void
    {
        $owner = $this->userWithRole('owner_approver');
        $this->createStockMutation($this->firstStock, $owner, StockMutationType::RECEIVE, '120.0000', '2026-08-10 08:00:00');
        $this->createStockMutation($this->firstStock, $owner, StockMutationType::ISSUE, '-4.0000', '2026-08-11 09:00:00');
        $this->createStockMutation($this->firstStock, $owner, StockMutationType::RECEIVE, '150.0000', '2026-07-10 08:00:00');
        $this->createStockMutation($this->secondStock, $owner, StockMutationType::RECEIVE, '180.0000', '2026-08-10 10:00:00');

        $response = $this->actingAs($owner)
            ->getJson(route('warehouse.dashboard.data', [
                'warehouse_id' => $this->firstWarehouse->id,
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-31',
            ]))
            ->assertOk()
            ->assertJsonPath('filters.start_date', '2026-08-01')
            ->assertJsonPath('filters.end_date', '2026-08-31')
            ->assertJsonPath('kpis.incoming_count', 1)
            ->assertJsonPath('kpis.outgoing_count', 1)
            ->assertJsonCount(2, 'charts.daily_movement')
            ->assertJsonPath('charts.monthly_movement.0.date', '2026-08')
            ->assertJsonPath('charts.monthly_movement.0.incoming', '120.0000')
            ->assertJsonPath('charts.monthly_movement.0.outgoing', '4.0000')
            ->assertJsonPath('charts.yearly_movement.0.date', '2026')
            ->assertJsonPath('report_url', route('reports.warehouse.index', [
                'work_location_id' => $this->firstLocation->id,
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-31',
            ]));

        $html = (string) $response->json('html');
        $this->assertStringContainsString('10/08/2026 08:00', $html);
        $this->assertStringNotContainsString('10/07/2026 08:00', $html);
        $this->assertStringNotContainsString('Gudang Timur', $html);
        $this->assertStringContainsString('product_id='.$this->firstStock->product_id, $html);
    }

    public function test_invalid_date_filter_is_rejected_before_dashboard_queries_run(): void
    {
        $owner = $this->userWithRole('owner_approver');

        $this->actingAs($owner)
            ->getJson(route('warehouse.dashboard.data', [
                'warehouse_id' => $this->firstWarehouse->id,
                'start_date' => '2026-09-10',
                'end_date' => '2026-09-01',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('end_date');
    }

    public function test_filter_form_uses_local_ajax_loading_without_global_page_overlay(): void
    {
        $owner = $this->userWithRole('owner_approver');

        $this->actingAs($owner)
            ->get(route('warehouse.dashboard'))
            ->assertOk()
            ->assertSee('id="warehouse-dashboard-filter"', false)
            ->assertSee('data-no-loading', false)
            ->assertSee('window.history.replaceState', false)
            ->assertSee('payload.report_url', false);
    }

    public function test_uncached_dashboard_uses_a_bounded_number_of_database_queries(): void
    {
        $owner = $this->userWithRole('owner_approver');
        $reports = app(ReportMetricService::class);
        $filters = $reports->filters($owner, [
            'work_location_id' => $this->firstLocation->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
        ]);

        Cache::flush();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $reports->warehouseDashboard($owner, $filters);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(17, $queryCount);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(Role::findOrCreate($role));

        return $user;
    }

    private function createPurchaseOrder(Warehouse $warehouse, Supplier $supplier, User $creator, string $number): PurchaseOrder
    {
        return PurchaseOrder::query()->create([
            'number' => $number,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'order_date' => now('Asia/Jakarta')->toDateString(),
            'status' => PurchaseOrderStatus::SUBMITTED->value,
            'created_by' => $creator->id,
            'submitted_at' => now('Asia/Jakarta'),
            'submitted_by' => $creator->id,
        ]);
    }

    private function createStockMutation(Stock $stock, User $actor, StockMutationType $type, string $change, string $occurredAt): StockMutation
    {
        $incoming = (float) $change > 0;

        return StockMutation::query()->create([
            'product_id' => $stock->product_id,
            'stock_id' => $stock->id,
            'work_location_id' => $stock->work_location_id,
            'mutation_type' => $type->value,
            'direction' => $incoming ? 'in' : 'out',
            'quantity_on_hand_before' => $incoming ? '0.0000' : '4.0000',
            'quantity_on_hand_change' => $change,
            'quantity_on_hand_after' => $incoming ? $change : '0.0000',
            'quantity_reserved_before' => '0.0000',
            'quantity_reserved_change' => '0.0000',
            'quantity_reserved_after' => '0.0000',
            'quantity_damaged_before' => '0.0000',
            'quantity_damaged_change' => '0.0000',
            'quantity_damaged_after' => '0.0000',
            'unit_id' => $stock->product->base_unit_id,
            'actor_user_id' => $actor->id,
            'reason' => 'Pengujian filter dashboard gudang',
            'occurred_at' => $occurredAt,
        ]);
    }
}
