<?php

namespace Tests\Feature\Reports;

use App\Enums\CashShiftStatus;
use App\Enums\PosSaleStatus;
use App\Enums\ReportExportStatus;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\Product;
use App\Models\ReportExport;
use App\Models\Stock;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkLocation;
use App\Services\Reports\ReportMetricService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardReportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $retail;

    private User $cashier;

    private WorkLocation $branchLocation;

    private WorkLocation $otherLocation;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->owner = User::factory()->create(['is_active' => true]);
        $this->owner->assignRole(Role::findOrCreate('owner_approver'));
        $this->retail = User::factory()->create(['is_active' => true]);
        $this->retail->assignRole(Role::findOrCreate('kepala_toko'));
        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole(Role::findOrCreate('kasir'));

        $this->branchLocation = WorkLocation::factory()->create(['type' => 'branch', 'code' => 'TKO-RPT', 'name' => 'Toko Report']);
        $this->otherLocation = WorkLocation::factory()->create(['type' => 'branch', 'code' => 'TKO-OTH', 'name' => 'Toko Lain']);
        $warehouseLocation = WorkLocation::factory()->create(['type' => 'warehouse', 'code' => 'GDG-RPT']);
        $warehouse = Warehouse::factory()->create(['work_location_id' => $warehouseLocation->id]);
        $this->branch = Branch::factory()->create(['work_location_id' => $this->branchLocation->id, 'primary_warehouse_id' => $warehouse->id]);

        $this->retail->workLocations()->sync([$this->branchLocation->id => ['is_default' => true, 'is_active' => true]]);
        $this->cashier->workLocations()->sync([$this->branchLocation->id => ['is_default' => true, 'is_active' => true]]);
    }

    public function test_p24_dashboard_and_report_pages_are_available(): void
    {
        $this->seedFixtureSale('300000.00', '75000.00', $this->branchLocation);

        $this->actingAs($this->owner)->get(route('owner.dashboard'))->assertOk()->assertSee('Dashboard Owner')->assertSee('Omzet');
        $this->actingAs($this->owner)->get(route('warehouse.dashboard'))->assertOk()->assertSee('Dashboard Gudang')->assertSee('Nilai Persediaan');
        $this->actingAs($this->retail)->get(route('retail.dashboard'))->assertOk()->assertSee('Dashboard Cabang')->assertSee('Rata-rata Nota');
        $this->actingAs($this->owner)->get(route('reports.daily.index'))->assertOk()->assertSee('Laporan Harian Owner');
        $this->actingAs($this->owner)->get(route('reports.warehouse.index'))->assertOk()->assertSee('Laporan Gudang');
        $this->actingAs($this->owner)->get(route('reports.retail.index'))->assertOk()->assertSee('Laporan Toko');
        $this->actingAs($this->owner)->get(route('reports.b2b.index'))->assertOk()->assertSee('Laporan Langganan/B2B');
        $this->actingAs($this->owner)->get(route('reports.pricing.index'))->assertOk()->assertSee('Laporan Harga dan Margin');
        $this->actingAs($this->owner)->get(route('reports.receivables.index'))->assertOk()->assertSee('Laporan Piutang');
        $this->actingAs($this->owner)->get(route('reports.audit-notifications.index'))->assertOk()->assertSee('Laporan Audit dan Notifikasi');
        $this->actingAs($this->owner)->get(route('reports.exports.index'))->assertOk()->assertSee('Pusat Export');
    }

    public function test_owner_kpi_excludes_void_and_branch_scope_is_enforced(): void
    {
        $this->seedFixtureSale('300000.00', '75000.00', $this->branchLocation);
        $this->seedFixtureSale('999999.00', '1.00', $this->branchLocation, PosSaleStatus::VOID_APPROVED->value);
        $this->seedFixtureSale('500000.00', '100000.00', $this->otherLocation);

        $filters = app(ReportMetricService::class)->filters($this->retail, [
            'start_date' => now('Asia/Jakarta')->toDateString(),
            'end_date' => now('Asia/Jakarta')->toDateString(),
        ]);
        $dashboard = app(ReportMetricService::class)->retailDashboard($this->retail, $filters);

        $this->assertSame('300000.00', $dashboard['kpis']['revenue']);
        $this->assertSame('75000.00', $dashboard['kpis']['margin']);
        $this->assertSame(1, $dashboard['kpis']['transaction_count']);
    }

    public function test_timezone_boundary_uses_asia_jakarta_report_date(): void
    {
        $this->seedFixtureSale('120000.00', '30000.00', $this->branchLocation, PosSaleStatus::COMPLETED->value, now('Asia/Jakarta')->startOfDay()->addMinutes(5));

        $filters = app(ReportMetricService::class)->filters($this->owner, [
            'start_date' => now('Asia/Jakarta')->toDateString(),
            'end_date' => now('Asia/Jakarta')->toDateString(),
        ]);
        $dashboard = app(ReportMetricService::class)->ownerDashboard($this->owner, $filters);

        $this->assertSame('120000.00', $dashboard['kpis']['revenue']);
    }

    public function test_export_center_authorization_and_queued_file_generation(): void
    {
        Storage::fake('local');
        $this->seedFixtureSale('250000.00', '50000.00', $this->branchLocation);

        $this->actingAs($this->cashier)->get(route('reports.exports.index'))->assertForbidden();
        $this->actingAs($this->owner)->post(route('reports.exports.store'), [
            'report_type' => 'daily',
            'format' => 'xlsx',
            'start_date' => now('Asia/Jakarta')->toDateString(),
            'end_date' => now('Asia/Jakarta')->toDateString(),
        ])->assertRedirect(route('reports.exports.index'));

        $export = ReportExport::query()->firstOrFail();
        $this->assertSame(ReportExportStatus::COMPLETED, $export->status);
        $this->assertNotNull($export->file_path);
        Storage::disk('local')->assertExists($export->file_path);
    }

    public function test_dashboard_query_count_smoke_budget(): void
    {
        $this->seedFixtureSale('100000.00', '20000.00', $this->branchLocation);
        DB::enableQueryLog();

        $this->actingAs($this->owner)->get(route('owner.dashboard'))->assertOk();

        $this->assertLessThan(60, count(DB::getQueryLog()));
    }

    private function seedFixtureSale(string $amount, string $margin, WorkLocation $location, string $status = PosSaleStatus::COMPLETED->value, mixed $completedAt = null): PosSale
    {
        $unit = Unit::factory()->create();
        $product = Product::factory()->create(['base_unit_id' => $unit->id, 'cost_price' => '10000.00', 'minimum_stock' => '5.0000']);
        Stock::query()->create([
            'product_id' => $product->id,
            'work_location_id' => $location->id,
            'location_scope_key' => 'work:'.$location->id,
            'quantity_on_hand' => '3.0000',
            'quantity_reserved' => '0.0000',
            'quantity_damaged' => '0.0000',
            'cost_value' => '30000.00',
        ]);
        $branch = $location->is($this->branchLocation) ? $this->branch : Branch::factory()->create(['work_location_id' => $location->id]);
        $shift = CashShift::query()->create([
            'number' => 'SHIFT-'.$location->id.'-'.uniqid(),
            'branch_id' => $branch->id,
            'work_location_id' => $location->id,
            'cashier_user_id' => $this->cashier->id,
            'status' => CashShiftStatus::CLOSED->value,
            'opening_cash_amount' => '0.00',
            'expected_cash_amount' => $amount,
            'actual_cash_amount' => $amount,
            'difference_amount' => '0.00',
            'opened_at' => now('Asia/Jakarta')->subHour(),
            'closing_submitted_at' => now('Asia/Jakarta'),
            'closed_at' => now('Asia/Jakarta'),
        ]);
        $sale = PosSale::query()->create([
            'number' => 'POS-'.$location->id.'-'.uniqid(),
            'branch_id' => $branch->id,
            'work_location_id' => $location->id,
            'cash_shift_id' => $shift->id,
            'cashier_user_id' => $this->cashier->id,
            'status' => $status,
            'subtotal_amount' => $amount,
            'grand_total_amount' => $amount,
            'paid_amount' => $amount,
            'total_margin_amount' => $margin,
            'completed_at' => $completedAt ?? now('Asia/Jakarta'),
        ]);
        PosSaleItem::query()->create([
            'pos_sale_id' => $sale->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'sku_snapshot' => $product->sku,
            'product_name_snapshot' => $product->name,
            'unit_name_snapshot' => $unit->name,
            'conversion_factor_snapshot' => '1.000000',
            'quantity' => '1.0000',
            'base_quantity' => '1.0000',
            'selected_price' => $amount,
            'line_total' => $amount,
            'margin_amount' => $margin,
            'price_source' => 'test',
        ]);

        return $sale;
    }
}
