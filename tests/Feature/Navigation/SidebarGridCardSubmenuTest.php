<?php

namespace Tests\Feature\Navigation;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarGridCardSubmenuTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_admin');
    }

    public function test_sidebar_renders_grid_cards_and_single_accordion(): void
    {
        $response = $this->actingAs($this->admin)->get(route('warehouse.stocks.index'));
        $response->assertOk();

        $content = $response->getContent();

        // 1. Single accordion attribute
        $this->assertStringContainsString('data-kt-menu-expand="false"', $content);

        // 2. Submenu Grid and Card classes (Header menu remains default, only submenu uses grid cards)
        $this->assertStringNotContainsString('sidebar-group-container', $content);
        $this->assertStringNotContainsString('sidebar-single-card', $content);
        $this->assertStringContainsString('sidebar-submenu-grid', $content);
        $this->assertStringContainsString('sidebar-submenu-card', $content);

        // 4. Gudang & Stok (5 items - odd total) -> Retur must be full width
        $this->assertStringContainsString('ki-outline ki-row-horizontal', $content);
        $this->assertStringContainsString('ki-outline ki-package', $content);
        $this->assertStringContainsString('ki-outline ki-box-3', $content);
        $this->assertStringContainsString('ki-outline ki-delivery-2', $content);
        $this->assertStringContainsString('ki-outline ki-arrows-loop', $content);

        // 5. Active state applied to current submenu
        $this->assertStringContainsString('sidebar-submenu-card active', $content);
    }
}
