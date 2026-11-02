<?php

namespace Tests\Feature\Navigation;

use App\Models\User;
use App\Models\WorkLocation;
use Database\Seeders\DemoFullApplicationSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StockTransferLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_warehouse_user_sees_transfer_workflows_under_warehouse_menu_only(): void
    {
        $user = $this->userAtLocation('kepala_gudang', 'warehouse');

        $this->actingAs($user);
        $sidebar = $this->renderSidebar();

        $this->assertStringContainsString('Gudang &amp; Stok', $sidebar);
        $this->assertStringContainsString('Transfer Antar Cabang', $sidebar);
        $this->assertStringContainsString('Laporan Gudang', $sidebar);
        $this->assertStringNotContainsString('Toko Internal', $sidebar);
    }

    public function test_branch_user_sees_retail_transfer_workflows_under_internal_store_menu(): void
    {
        $user = $this->userAtLocation('kepala_toko', 'branch');

        $this->actingAs($user);
        $sidebar = $this->renderSidebar();

        $this->assertStringContainsString('Toko Internal', $sidebar);
        $this->assertStringContainsString('Kasir POS', $sidebar);
        $this->assertStringContainsString('Laporan Toko', $sidebar);
        $this->assertStringNotContainsString('Laporan Gudang', $sidebar);
    }

    public function test_demo_head_store_and_cashier_accounts_see_internal_store_menu_after_location_seeding(): void
    {
        $this->seed(DemoFullApplicationSeeder::class);
        $this->seed(StockTransferLocationSeeder::class);

        foreach (['toko@gudangtoko.test', 'kasir@gudangtoko.test'] as $email) {
            $user = User::query()->where('email', $email)->firstOrFail();

            $this->assertTrue($user->hasPermittedWorkLocationType('branch'));

            $this->actingAs($user);
            $this->assertStringContainsString('Toko Internal', $this->renderSidebar());
        }
    }

    public function test_report_center_menu_is_positioned_at_the_bottom_with_distinct_contrast_styling(): void
    {
        $user = $this->userAtLocation('kepala_toko', 'branch');

        $this->actingAs($user);
        $sidebar = $this->renderSidebar();

        $this->assertStringContainsString('sidebar-report-section', $sidebar);
        $this->assertStringContainsString('sidebar-report-tone-retail', $sidebar);
        $this->assertStringContainsString('sidebar-report-link', $sidebar);
        $this->assertStringContainsString('sidebar-report-pill', $sidebar);

        $reportPos = strpos($sidebar, 'sidebar-report-section');
        $guidePos = strpos($sidebar, 'Dokumentasi Panduan');

        $this->assertNotFalse($reportPos);
        $this->assertNotFalse($guidePos);
        $this->assertGreaterThan($guidePos, $reportPos, 'Menu Laporan harus berada di posisi paling bawah setelah menu lainnya');
    }

    private function userAtLocation(string $role, string $locationType): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($role));
        $location = WorkLocation::factory()->create(['type' => $locationType]);
        $user->workLocations()->attach($location->id, ['is_default' => true, 'is_active' => true]);

        return $user;
    }

    private function renderSidebar(): string
    {
        return view('layouts.metronic.partials.sidebar')->render();
    }
}
