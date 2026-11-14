<?php

namespace Tests\Feature\Reports;

use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Stock;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WorkLocation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleProfileReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_user_only_receives_user_report_center(): void
    {
        $user = $this->userWithRole('admin_user');

        $this->actingAs($user)->get(route('reports.profiles.users.show', ['report' => 'overview']))
            ->assertOk()
            ->assertSee('ADMINISTRASI IDENTITAS')
            ->assertSee('Ringkasan Pengguna')
            ->assertSee('Kesehatan Akses')
            ->assertSee('Distribusi Role')
            ->assertDontSee('id="kt_app_toolbar"', false);

        $sidebar = $this->renderSidebar($user);
        $this->assertStringContainsString('Laporan Pengguna', $sidebar);
        $this->assertStringNotContainsString('Laporan Gudang', $sidebar);
        $this->assertStringNotContainsString('Laporan Toko', $sidebar);
    }

    public function test_profile_report_defaults_to_monthly_range_and_keeps_explicit_choice(): void
    {
        $user = $this->userWithRole('admin_user');
        $route = route('reports.profiles.users.show', ['report' => 'overview']);

        $this->actingAs($user)->get($route)
            ->assertOk()
            ->assertSee('option value="monthly" selected', false);

        $this->actingAs($user)->get($route.'?range=daily')
            ->assertOk()
            ->assertSee('option value="daily" selected', false);
    }

    public function test_warehouse_head_sees_named_warehouse_and_isolated_design(): void
    {
        $location = WorkLocation::factory()->create(['type' => 'warehouse', 'name' => 'Gudang Kedaung']);
        $user = $this->userWithRole('kepala_gudang', $location);

        $this->actingAs($user)->get(route('reports.profiles.warehouse.show', ['report' => 'overview']))
            ->assertOk()
            ->assertSee('Laporan Operasional Gudang')
            ->assertSee('Gudang Kedaung')
            ->assertSee('Tren Mutasi Persediaan')
            ->assertSee('data-warehouse-line-chart', false)
            ->assertSee('data-warehouse-pie-chart', false)
            ->assertSee('data-warehouse-bar-chart', false)
            ->assertSee('Opname, Koreksi &amp; Loss', false)
            ->assertDontSee('id="kt_app_toolbar"', false);

        $sidebar = $this->renderSidebar($user);
        $this->assertStringContainsString('Laporan Gudang Kedaung', $sidebar);
        $this->assertStringNotContainsString('Laporan Toko', $sidebar);
        $this->assertStringNotContainsString('Persediaan &amp; Nilai HPP', $sidebar);
    }

    public function test_store_head_sees_named_store_and_retail_design(): void
    {
        $location = WorkLocation::factory()->create(['type' => 'branch', 'name' => 'Toko Kedaung']);
        $user = $this->userWithRole('kepala_toko', $location);

        $this->actingAs($user)->get(route('reports.profiles.retail.show', ['report' => 'sales']))
            ->assertOk()
            ->assertSee('PERFORMA TOKO')
            ->assertSee('Penjualan &amp; Penghasilan', false);

        $sidebar = $this->renderSidebar($user);
        $this->assertStringContainsString('Laporan Toko Kedaung', $sidebar);
        $this->assertStringNotContainsString('Penjualan &amp; Penghasilan', $sidebar);
    }

    public function test_store_overview_renders_operational_dashboard_and_detail_links(): void
    {
        $location = WorkLocation::factory()->create(['type' => 'branch', 'name' => 'Toko Operasional']);
        $user = $this->userWithRole('kepala_toko', $location);

        $response = $this->actingAs($user)->get(route('reports.profiles.retail.show', [
            'report' => 'overview',
            'work_location_id' => $location->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'range' => 'monthly',
        ]));

        $response->assertOk()
            ->assertSee('Laporan Operasional Toko')
            ->assertSee('Ringkasan Kinerja Toko')
            ->assertSee('Profitabilitas &amp; Margin', false)
            ->assertSee('Retur, Darurat &amp; Loss', false)
            ->assertSee('Piutang Retail')
            ->assertSee('Tim Toko')
            ->assertSee('data-sales-chart', false)
            ->assertSee('data-chart-tooltip', false)
            ->assertSee('data-tooltip-revenue', false);

        foreach (['sales', 'profitability', 'shifts', 'inventory', 'exceptions', 'receivables', 'team'] as $detail) {
            $response->assertSee(route('reports.profiles.retail.show', [
                'report' => $detail,
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
                'work_location_id' => $location->id,
                'range' => 'monthly',
            ]));
        }
    }

    public function test_detail_page_replaces_report_tabs_with_back_to_overview(): void
    {
        $location = WorkLocation::factory()->create(['type' => 'branch', 'name' => 'Toko Detail']);
        $user = $this->userWithRole('kepala_toko', $location);

        $this->actingAs($user)->get(route('reports.profiles.retail.show', [
            'report' => 'sales',
            'work_location_id' => $location->id,
        ]))->assertOk()
            ->assertSee('Kembali ke Ringkasan')
            ->assertDontSee('Ringkasan Toko');
    }

    public function test_store_overview_hides_sensitive_margin_without_permission(): void
    {
        $location = WorkLocation::factory()->create(['type' => 'branch']);
        $user = $this->userWithRole('kepala_toko', $location);
        Role::findByName('kepala_toko')->revokePermissionTo('margins.view_sensitive');

        $this->actingAs($user)->get(route('reports.profiles.retail.show', [
            'report' => 'overview',
            'work_location_id' => $location->id,
        ]))->assertOk()
            ->assertSee('Akses terbatas')
            ->assertDontSee('class="profit-legend"', false);
    }

    public function test_store_overview_values_inventory_from_stock_layers(): void
    {
        $location = WorkLocation::factory()->create(['type' => 'branch']);
        $user = $this->userWithRole('kepala_toko', $location);
        $product = Product::factory()->create(['cost_price' => '999.00', 'minimum_stock' => '1.0000']);
        $stock = Stock::query()->create([
            'product_id' => $product->id,
            'work_location_id' => $location->id,
            'warehouse_location_id' => null,
            'location_scope_key' => 'work-location:'.$location->id,
            'quantity_on_hand' => '12.0000',
            'quantity_reserved' => '0.0000',
            'quantity_damaged' => '0.0000',
            'cost_value' => '0.00',
        ]);
        foreach ([['LAYER-10K', '5.0000', '10000.00'], ['LAYER-20K', '7.0000', '20000.00']] as [$batchNo, $quantity, $cost]) {
            StockBatch::query()->create([
                'product_id' => $product->id,
                'stock_id' => $stock->id,
                'work_location_id' => $location->id,
                'unit_id' => $product->base_unit_id,
                'conversion_factor_snapshot' => '1.000000',
                'batch_no' => $batchNo,
                'cost_price' => $cost,
                'quantity_on_hand' => $quantity,
                'quantity_reserved' => '0.0000',
                'quantity_damaged' => '0.0000',
                'status' => 'active',
            ]);
        }

        $this->actingAs($user)->get(route('reports.profiles.retail.show', [
            'report' => 'overview',
            'work_location_id' => $location->id,
        ]))->assertOk()->assertSee('Rp190.000');
    }

    public function test_warehouse_overview_values_inventory_from_stock_layers(): void
    {
        $location = WorkLocation::factory()->create(['type' => 'warehouse']);
        $user = $this->userWithRole('kepala_gudang', $location);
        $product = Product::factory()->create(['cost_price' => '999.00']);
        $stock = Stock::query()->create([
            'product_id' => $product->id,
            'work_location_id' => $location->id,
            'warehouse_location_id' => null,
            'location_scope_key' => 'work-location:'.$location->id,
            'quantity_on_hand' => '12.0000',
            'quantity_reserved' => '0.0000',
            'quantity_damaged' => '0.0000',
            'cost_value' => '0.00',
        ]);

        foreach ([['WH-10K', '5.0000', '10000.00'], ['WH-20K', '7.0000', '20000.00']] as [$batchNo, $quantity, $cost]) {
            StockBatch::query()->create([
                'product_id' => $product->id,
                'stock_id' => $stock->id,
                'work_location_id' => $location->id,
                'unit_id' => $product->base_unit_id,
                'conversion_factor_snapshot' => '1.000000',
                'batch_no' => $batchNo,
                'cost_price' => $cost,
                'quantity_on_hand' => $quantity,
                'quantity_reserved' => '0.0000',
                'quantity_damaged' => '0.0000',
                'status' => 'active',
            ]);
        }

        $this->actingAs($user)->get(route('reports.profiles.warehouse.show', [
            'report' => 'overview',
            'work_location_id' => $location->id,
        ]))->assertOk()
            ->assertSee('Nilai HPP Berlapis')
            ->assertSee('Rp190.000');
    }

    public function test_warehouse_overview_hides_hpp_without_sensitive_permission(): void
    {
        $location = WorkLocation::factory()->create(['type' => 'warehouse']);
        $user = $this->userWithRole('kepala_gudang', $location);
        Role::findByName('kepala_gudang')->revokePermissionTo('margins.view_sensitive');

        $this->actingAs($user)->get(route('reports.profiles.warehouse.show', [
            'report' => 'overview',
            'work_location_id' => $location->id,
        ]))->assertOk()->assertSee('Akses terbatas');
    }

    public function test_retail_receivables_renders_composite_summary_without_html_escape_error(): void
    {
        $location = WorkLocation::factory()->create(['type' => 'branch', 'name' => 'Toko Piutang']);
        $user = $this->userWithRole('kepala_toko', $location);

        $this->actingAs($user)->get(route('reports.profiles.retail.show', [
            'report' => 'receivables',
            'work_location_id' => $location->id,
        ]))
            ->assertOk()
            ->assertSee('Piutang Retail')
            ->assertSee('PERFORMA TOKO');
    }

    public function test_owner_and_super_admin_receive_their_own_report_experience(): void
    {
        WorkLocation::factory()->create(['type' => 'branch']);
        $owner = $this->userWithRole('owner_viewer');
        $admin = $this->userWithRole('super_admin');

        $ownerOverview = $this->actingAs($owner)->get(route('reports.profiles.owner.show', ['report' => 'overview']))
            ->assertOk()
            ->assertSee('EXECUTIVE BUSINESS REPORT')
            ->assertSee('Skor Kesehatan Bisnis')
            ->assertSee('Pendapatan &amp; Channel Mix', false)
            ->assertSee('Anomali &amp; Keputusan', false)
            ->assertSee('data-owner-kpi-chart', false)
            ->assertDontSee('or-spark', false)
            ->assertDontSee('id="kt_app_toolbar"', false);
        $this->assertSame(6, substr_count($ownerOverview->getContent(), 'data-owner-kpi-chart'));
        $ownerSidebar = $this->renderSidebar($owner);
        $this->assertStringContainsString('Laporan Owner', $ownerSidebar);
        $this->assertStringNotContainsString('Laporan Super Admin', $ownerSidebar);
        $this->actingAs($owner)->get(route('reports.profiles.system.show', ['report' => 'overview']))->assertForbidden();

        $this->actingAs($admin)->get(route('reports.profiles.system.show', ['report' => 'audit']))
            ->assertOk()->assertSee('SYSTEM CONTROL CENTER');
        $this->actingAs($admin)->get(route('reports.profiles.system.show', ['report' => 'overview']))
            ->assertOk()
            ->assertSee('Kesehatan Sistem')
            ->assertSee('Prioritas Kontrol')
            ->assertDontSee('id="kt_app_toolbar"', false);
        $adminSidebar = $this->renderSidebar($admin);
        $this->assertStringContainsString('Laporan Super Admin', $adminSidebar);
        $this->assertStringNotContainsString('Laporan Owner', $adminSidebar);

        $this->actingAs($admin)->get(route('reports.profiles.owner.show', ['report' => 'overview']))->assertOk();
        $this->actingAs($admin)->get(route('reports.profiles.warehouse.show', ['report' => 'overview']))->assertOk();
    }

    public function test_owner_overview_uses_layered_inventory_value_and_protects_sensitive_data(): void
    {
        $location = WorkLocation::factory()->create(['type' => 'branch']);
        $owner = $this->userWithRole('owner_approver', $location);
        $product = Product::factory()->create(['cost_price' => '999.00']);
        $stock = Stock::query()->create([
            'product_id' => $product->id,
            'work_location_id' => $location->id,
            'warehouse_location_id' => null,
            'location_scope_key' => 'work-location:'.$location->id,
            'quantity_on_hand' => '12.0000',
            'quantity_reserved' => '0.0000',
            'quantity_damaged' => '0.0000',
            'cost_value' => '0.00',
        ]);

        foreach ([['OWN-10K', '5.0000', '10000.00'], ['OWN-20K', '7.0000', '20000.00']] as [$batchNo, $quantity, $cost]) {
            StockBatch::query()->create([
                'product_id' => $product->id, 'stock_id' => $stock->id, 'work_location_id' => $location->id,
                'unit_id' => $product->base_unit_id, 'conversion_factor_snapshot' => '1.000000',
                'batch_no' => $batchNo, 'cost_price' => $cost, 'quantity_on_hand' => $quantity,
                'quantity_reserved' => '0.0000', 'quantity_damaged' => '0.0000', 'status' => 'active',
            ]);
        }

        $route = route('reports.profiles.owner.show', ['report' => 'overview', 'work_location_id' => $location->id]);
        $this->actingAs($owner)->get($route)->assertOk()->assertSee('Rp190.000');

        Role::findByName('owner_approver')->revokePermissionTo('margins.view_sensitive');
        $this->actingAs($owner)->get($route)->assertOk()->assertSee('Akses terbatas')->assertDontSee('Rp190.000');
    }

    public function test_owner_income_statement_renders_pnl_and_protects_sensitive_data(): void
    {
        $location = WorkLocation::factory()->create(['type' => 'branch', 'name' => 'Toko Finansial']);
        $owner = $this->userWithRole('owner_approver', $location);

        $route = route('reports.profiles.owner.show', ['report' => 'income', 'work_location_id' => $location->id]);
        $response = $this->actingAs($owner)->get($route);

        $response->assertOk()
            ->assertSee('EXECUTIVE BUSINESS REPORT')
            ->assertSee('Laba Rugi &amp; Finansial', false)
            ->assertSee('Penjualan Bersih')
            ->assertSee('Beban Pokok (HPP)')
            ->assertSee('Laba Kotor')
            ->assertSee('Laba Operasional Bersih')
            ->assertSee('Laporan Laba Rugi Berjenjang (Income Statement)')
            ->assertSee('Perbandingan Profitabilitas Antar Cabang')
            ->assertSee('Estimasi Kewajiban Perpajakan')
            ->assertDontSee('id="kt_app_toolbar"', false);

        Role::findByName('owner_approver')->revokePermissionTo('margins.view_sensitive');
        $this->actingAs($owner)->get($route)
            ->assertOk()
            ->assertSee('Akses terbatas');

        Queue::fake();
        $this->actingAs($owner)->post(route('reports.profiles.exports.store', [
            'profile' => 'owner',
            'report' => 'income',
        ]), [
            'format' => 'pdf',
            'work_location_id' => $location->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ])->assertRedirect();

        $this->assertDatabaseHas('report_exports', [
            'report_type' => 'profile.owner.income',
            'format' => 'pdf',
            'requested_by' => $owner->id,
        ]);
    }

    public function test_owner_tax_report_renders_ppn_and_coretax_table(): void
    {
        $location = WorkLocation::factory()->create(['type' => 'branch', 'name' => 'Toko Perpajakan']);
        $owner = $this->userWithRole('owner_approver', $location);

        $route = route('reports.profiles.owner.show', ['report' => 'tax', 'work_location_id' => $location->id]);
        $response = $this->actingAs($owner)->get($route);

        $response->assertOk()
            ->assertSee('EXECUTIVE TAX &amp; COMPLIANCE SUITE', false)
            ->assertSee('Rekapitulasi Pajak')
            ->assertSee('PPN Keluaran')
            ->assertSee('PPN Masukan Dapat Dikreditkan')
            ->assertSee('Posisi SPT Masa PPN')
            ->assertSee('Estimasi PPh Final UMKM (0,5%)')
            ->assertSee('Formulir Induk Rekapitulasi SPT Masa PPN 1111')
            ->assertSee('Rekonsiliasi Faktur Pajak Coretax DJP')
            ->assertSee('Simulasi &amp; Komparasi Skema Pajak Penghasilan (PPh)', false)
            ->assertSee('Kalender Kepatuhan Pajak (DJP)', false)
            ->assertDontSee('id="kt_app_toolbar"', false);

        // Pastikan ketiga tombol switcher tersedia di header
        $response->assertSee('Ringkasan Eksekutif')
            ->assertSee('Laba Rugi &amp; Finansial', false)
            ->assertSee('Rekapitulasi Pajak');

        Queue::fake();
        $this->actingAs($owner)->post(route('reports.profiles.exports.store', [
            'profile' => 'owner',
            'report' => 'tax',
        ]), [
            'format' => 'pdf',
            'work_location_id' => $location->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ])->assertRedirect();

        $this->assertDatabaseHas('report_exports', [
            'report_type' => 'profile.owner.tax',
            'format' => 'pdf',
            'requested_by' => $owner->id,
        ]);
    }

    public function test_non_head_roles_cannot_open_any_profile_report(): void
    {
        foreach (['purchasing', 'supervisor_shift', 'staff_gudang', 'kasir', 'kepala_keuangan'] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->get(route('reports.profiles.owner.show', ['report' => 'overview']))->assertForbidden();
            $this->assertStringNotContainsString('Laporan Owner', $this->renderSidebar($user));
        }
    }

    public function test_location_tampering_falls_back_to_an_authorized_location(): void
    {
        $allowed = WorkLocation::factory()->create(['type' => 'warehouse', 'name' => 'Gudang Diizinkan']);
        $foreign = WorkLocation::factory()->create(['type' => 'warehouse', 'name' => 'Gudang Rahasia']);
        $user = $this->userWithRole('kepala_gudang', $allowed);

        $this->actingAs($user)->get(route('reports.profiles.warehouse.show', [
            'report' => 'overview',
            'work_location_id' => $foreign->id,
        ]))->assertOk()->assertSee('Gudang Diizinkan')->assertDontSee('Gudang Rahasia');
    }

    public function test_each_configured_report_has_its_own_blade_file(): void
    {
        foreach (config('report_profiles') as $profile => $definition) {
            foreach (array_keys($definition['reports']) as $report) {
                $this->assertTrue(view()->exists("reports.profiles.{$profile}.{$report}"), "View {$profile}/{$report} tidak ditemukan.");
            }
        }
    }

    public function test_profile_export_keeps_profile_and_filter_snapshot(): void
    {
        Queue::fake();
        $location = WorkLocation::factory()->create(['type' => 'warehouse']);
        $user = $this->userWithRole('kepala_gudang', $location);

        $this->actingAs($user)->post(route('reports.profiles.exports.store', [
            'profile' => 'warehouse',
            'report' => 'inventory',
        ]), [
            'format' => 'xlsx',
            'work_location_id' => $location->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ])->assertRedirect();

        $this->assertDatabaseHas('report_exports', [
            'report_type' => 'profile.warehouse.inventory',
            'format' => 'xlsx',
            'requested_by' => $user->id,
        ]);
    }

    public function test_warehouse_head_can_view_inbound_report_without_query_exception(): void
    {
        $location = WorkLocation::factory()->create(['type' => 'warehouse', 'name' => 'Gudang Utama']);
        $user = $this->userWithRole('kepala_gudang', $location);

        $supplier = Supplier::query()->create([
            'code' => 'SUP-TEST-01',
            'name' => 'PT Pemasok Sukses',
            'is_active' => true,
        ]);

        PurchaseOrder::query()->create([
            'number' => 'PO-TEST-001',
            'supplier_id' => $supplier->id,
            'warehouse_id' => null,
            'destination_work_location_id' => $location->id,
            'order_date' => now()->toDateString(),
            'status' => PurchaseOrderStatus::APPROVED->value,
            'grand_total' => '500000.00',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->get(route('reports.profiles.warehouse.show', [
            'report' => 'inbound',
            'work_location_id' => $location->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]))->assertOk()
            ->assertSee('PT Pemasok Sukses');
    }

    public function test_store_overview_profitability_donut_has_contrast_colors_and_hover_interaction(): void
    {
        $location = WorkLocation::factory()->create(['type' => 'branch', 'name' => 'Toko Donut']);
        $user = $this->userWithRole('kepala_toko', $location);

        $this->actingAs($user)->get(route('reports.profiles.retail.show', [
            'report' => 'overview',
            'work_location_id' => $location->id,
        ]))->assertOk()
            ->assertSee('data-profit-card', false)
            ->assertSee('data-profit-stage', false)
            ->assertSee('profit-donut', false)
            ->assertSee('segment-margin', false)
            ->assertSee('segment-hpp', false)
            ->assertSee('#2563eb', false)
            ->assertSee('#10b981', false)
            ->assertSee('#f59e0b', false)
            ->assertSee('data-profit-tooltip', false)
            ->assertDontSee('id="kt_app_toolbar"', false);
    }

    private function userWithRole(string $role, ?WorkLocation $location = null): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($role));
        if ($location !== null) {
            $user->workLocations()->attach($location->id, ['is_default' => true, 'is_active' => true]);
        }

        return $user;
    }

    private function renderSidebar(User $user): string
    {
        $this->actingAs($user);

        return view('layouts.metronic.partials.sidebar')->render();
    }
}
