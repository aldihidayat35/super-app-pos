<?php

namespace Tests\Feature\Navigation;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkLocation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RestructuredNavigationAndUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_sidebar_displays_10_modules_and_does_not_display_old_administrasi_or_pembelian(): void
    {
        $user = $this->createSuperAdmin();
        $branch = WorkLocation::factory()->create(['type' => 'branch']);
        $user->workLocations()->attach($branch->id, ['is_default' => true, 'is_active' => true]);

        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $sidebar = $this->extractSidebar($response->getContent());

        // 10 Modul Baru
        $this->assertStringContainsString('Dashboard', $sidebar);
        $this->assertStringContainsString('Kasir POS', $sidebar);
        $this->assertStringContainsString('Administrasi Sistem', $sidebar);
        $this->assertStringContainsString('Master Produk', $sidebar);
        $this->assertStringContainsString('Relasi Bisnis', $sidebar);
        $this->assertStringContainsString('Gudang &amp; Stok', $sidebar);
        $this->assertStringContainsString('Harga &amp; Margin', $sidebar);
        $this->assertStringContainsString('Toko Internal', $sidebar);
        $this->assertStringContainsString('Kehadiran', $sidebar);
        $this->assertStringContainsString('Kontrol &amp; Audit', $sidebar);

        // Modul Lama Tidak Ditampilkan
        $this->assertStringNotContainsString('href="'.route('admin.roles.index').'"', $sidebar);
        $this->assertStringNotContainsString('href="'.route('admin.permissions.index').'"', $sidebar);
        $this->assertStringNotContainsString('Pesanan ke Supplier (PO)', $sidebar);
        $this->assertStringNotContainsString('Pengajuan Belanja (Internal)', $sidebar);
    }

    public function test_administrasi_sistem_has_exactly_three_submenus(): void
    {
        $nav = config('navigation');
        $adminSystem = collect($nav)->firstWhere('label', 'Administrasi Sistem');

        $this->assertNotNull($adminSystem);
        $this->assertCount(3, $adminSystem['children']);

        $labels = collect($adminSystem['children'])->pluck('label')->all();
        $this->assertEquals(['Pengguna', 'Lokasi Operasional', 'Pengaturan Umum'], $labels);

        $routes = collect($adminSystem['children'])->pluck('route')->all();
        $this->assertEquals(['admin.users.index', 'admin.operational-locations.index', 'admin.settings.general.edit'], $routes);
    }

    public function test_user_management_has_role_permission_management_button(): void
    {
        $user = $this->createSuperAdmin();

        $response = $this->actingAs($user)->get(route('admin.users.index'))->assertOk();
        $response->assertSee('Kelola Role & Permission');
        $response->assertSee(route('admin.roles.index'));
    }

    public function test_operational_locations_displays_warehouses_and_branches_in_two_columns(): void
    {
        $user = $this->createSuperAdmin();
        $warehouse = Warehouse::factory()->create(['name' => 'Gudang Pusat Cikupa']);
        $branch = Branch::factory()->create(['name' => 'Cabang Sudirman']);

        $response = $this->actingAs($user)->get(route('admin.operational-locations.index'))->assertOk();

        $response->assertSee('Manajemen Gudang');
        $response->assertSee('Manajemen Toko / Cabang');
        $response->assertSee('col-12 col-xl-6', false);
        $response->assertSee($warehouse->name);
        $response->assertSee($branch->name);
    }

    public function test_general_settings_renders_horizontal_line_tabs_without_page_reload(): void
    {
        $user = $this->createSuperAdmin();

        $response = $this->actingAs($user)->get(route('admin.settings.general.edit'))->assertOk();

        $response->assertSee('nav-line-tabs', false);
        $response->assertSee('#tab_general', false);
        $response->assertSee('#tab_printer', false);
        $response->assertSee('#tab_docnum', false);
        $response->assertSee('#tab_stock_adjustment', false);
        $response->assertSee('#tab_whatsapp', false);
        $response->assertSee('Identitas &amp; Preferensi', false);
        $response->assertSee('Pengaturan Printer', false);
        $response->assertSee('Nomor Dokumen', false);
    }

    public function test_master_products_renders_five_icon_cards_and_product_table(): void
    {
        $user = $this->createSuperAdmin();

        $response = $this->actingAs($user)->get(route('admin.products.index'))->assertOk();

        // 5 Menu Card Berikon
        $response->assertSee(route('admin.product-categories.index'));
        $response->assertSee(route('admin.product-brands.index'));
        $response->assertSee(route('admin.units.index'));
        $response->assertSee(route('admin.products.barcodes.index'));
        $response->assertSee(route('admin.products.quick-setup.index'));

        // Konten Utama Produk
        $response->assertSee('Daftar Produk');
    }

    public function test_business_relations_displays_suppliers_and_customers_in_two_columns(): void
    {
        $user = $this->createSuperAdmin();
        $supplier = Supplier::factory()->create(['name' => 'PT Pangan Makmur']);
        $customer = Customer::factory()->create(['business_name' => 'Toko Sembako Abadi']);

        $response = $this->actingAs($user)->get(route('admin.business-relations.index'))->assertOk();

        $response->assertSee('Manajemen Supplier');
        $response->assertSee('Pelanggan &amp; Mitra B2B', false);
        $response->assertSee('col-12 col-xl-6', false);
        $response->assertSee($supplier->name);
        $response->assertSee($customer->business_name);
    }

    public function test_work_location_switcher_updates_session(): void
    {
        $user = $this->createSuperAdmin();
        $loc1 = WorkLocation::factory()->create(['name' => 'Lokasi Alpha', 'type' => 'warehouse']);
        $loc2 = WorkLocation::factory()->create(['name' => 'Lokasi Beta', 'type' => 'branch']);

        $this->actingAs($user)
            ->post(route('work-locations.switch'), ['work_location_id' => $loc2->id])
            ->assertRedirect()
            ->assertSessionHas('work_location.id', $loc2->id)
            ->assertSessionHas('work_location.type', 'branch');
    }

    public function test_gudang_dan_stok_has_five_submenus_and_stock_page_has_action_cards(): void
    {
        $nav = config('navigation');
        $warehouseMod = collect($nav)->firstWhere('label', 'Gudang & Stok');

        $this->assertNotNull($warehouseMod);
        $this->assertCount(5, $warehouseMod['children']);

        $labels = collect($warehouseMod['children'])->pluck('label')->all();
        $this->assertEquals(['Zona, Rak, dan Bin', 'Saldo Stok', 'Penerimaan Barang', 'Pengiriman Barang', 'Retur'], $labels);

        $user = $this->createSuperAdmin();
        $loc = WorkLocation::factory()->create(['type' => 'warehouse']);
        $user->workLocations()->attach($loc->id, ['is_default' => true, 'is_active' => true]);

        $response = $this->actingAs($user)->get(route('warehouse.stocks.index'))->assertOk();
        $response->assertSee('Lokasi Operasional Aktif');
        $response->assertSee(route('warehouse.opening-stocks.import.index'));
        $response->assertSee(route('warehouse.stock-transfers.index'));
        $response->assertSee(route('warehouse.location-transfers.index'));
        $response->assertSee(route('warehouse.losses.index'));
        $response->assertSee(route('warehouse.stock-opnames.index'));
        $response->assertDontSee('Batch & Exp');
        $response->assertDontSee('Histori HPP');
        $response->assertSee('Kartu Stok');
        $response->assertSee('Export CSV');
    }

    public function test_pos_has_shift_detail_button_quick_customer_modal_and_clean_cards(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('kasir'));
        $branchLocation = WorkLocation::factory()->create(['type' => 'branch']);
        $branch = Branch::factory()->create(['work_location_id' => $branchLocation->id]);
        $user->workLocations()->attach($branchLocation->id, ['is_default' => true, 'is_active' => true]);

        CashShift::create([
            'number' => 'SHIFT-POS-01',
            'cashier_user_id' => $user->id,
            'work_location_id' => $branchLocation->id,
            'branch_id' => $branch->id,
            'opened_at' => now(),
            'starting_cash' => '100000.00',
            'status' => 'open',
        ]);

        $response = $this->actingAs($user)->get(route('retail.pos.index'))->assertOk();

        // 1. Shift Aktif button
        $response->assertSee('Shift Aktif / Detail Shift');
        $response->assertSee(route('retail.shifts.current'));

        // 2. Modal Tambah Pelanggan Cepat
        $response->assertSee('id="modal-quick-customer"', false);
        $response->assertSee('id="btn-quick-customer"', false);

        // 3. Ring harga cards removed
        $response->assertDontSee('pos-ring-grid');
    }

    public function test_quick_customer_creation_via_ajax_endpoint(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('kasir'));
        $branchLocation = WorkLocation::factory()->create(['type' => 'branch']);
        $user->workLocations()->attach($branchLocation->id, ['is_default' => true, 'is_active' => true]);

        $payload = [
            'type' => 'retail_credit',
            'name' => 'Warung Barokah Jaya',
            'whatsapp_number' => '081234567890',
            'minimum_order' => 50000,
            'payment_term_days' => 14,
            'credit_limit' => 2000000,
        ];

        $response = $this->actingAs($user)
            ->postJson(route('retail.pos.quick-customer'), $payload)
            ->assertOk()
            ->assertJson([
                'success' => true,
                'customer' => [
                    'business_name' => 'Warung Barokah Jaya',
                    'payment_terms' => 14,
                    'credit_limit' => 2000000,
                    'minimum_order' => 50000,
                ],
            ]);

        $customerId = $response->json('customer.id');
        $this->assertDatabaseHas('customers', [
            'id' => $customerId,
            'business_name' => 'Warung Barokah Jaya',
            'type' => CustomerType::RETAIL_CREDIT->value,
            'verification_status' => CustomerStatus::ACTIVE->value,
            'account_status' => CustomerStatus::ACTIVE->value,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('credit_limits', [
            'customer_id' => $customerId,
            'credit_limit' => 2000000,
            'payment_term_days' => 14,
        ]);
    }

    private function createSuperAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('super_admin'));

        return $user;
    }

    private function extractSidebar(string $html): string
    {
        $parts = explode('<aside id="kt_app_sidebar"', $html, 2);
        if (count($parts) < 2) {
            return $html;
        }

        return explode('</aside>', $parts[1], 2)[0];
    }
}
