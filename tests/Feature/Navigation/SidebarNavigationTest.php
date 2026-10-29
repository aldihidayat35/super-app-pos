<?php

namespace Tests\Feature\Navigation;

use App\Models\User;
use App\Models\WorkLocation;
use Database\Seeders\RolePermissionSeeder;
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
        $this->assertStringContainsString('Permintaan Restok Cabang', $sidebar);
        $this->assertStringContainsString('Penerimaan Transfer', $sidebar);
        $this->assertStringNotContainsString('Toko Internal', $sidebar);
    }

    public function test_branch_user_sees_retail_transfer_workflows_under_internal_store_menu(): void
    {
        $user = $this->userAtLocation('kepala_toko', 'branch');

        $this->actingAs($user);
        $sidebar = $this->renderSidebar();

        $this->assertStringContainsString('Toko Internal', $sidebar);
        $this->assertStringContainsString('Permintaan Restok', $sidebar);
        $this->assertStringContainsString('Terima Transfer', $sidebar);
        $this->assertStringNotContainsString('Permintaan Restok Cabang', $sidebar);
        $this->assertStringNotContainsString('Penerimaan Transfer', $sidebar);
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
