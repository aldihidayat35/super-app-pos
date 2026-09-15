<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function sidebar_only_displays_authorized_items_and_active_route(): void
    {
        $permission = Permission::findOrCreate('dashboard.view');
        $role = Role::findOrCreate('kepala_toko');
        $role->givePermissionTo($permission);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('class="menu-link active"', false)
            ->assertDontSee('Kesehatan Sistem');
    }

    #[Test]
    public function mobile_sidebar_uses_the_metronic_drawer_toggle(): void
    {
        $permission = Permission::findOrCreate('dashboard.view');
        $role = Role::findOrCreate('kepala_toko');
        $role->givePermissionTo($permission);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-kt-drawer-activate="{default: true, lg: false}"', false)
            ->assertSee('data-kt-drawer-toggle="#kt_app_sidebar_mobile_toggle"', false)
            ->assertSee('aria-controls="kt_app_sidebar"', false);
    }

    #[Test]
    public function deferred_features_are_hidden_from_navigation_but_core_modules_remain_visible(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('super_admin'));

        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $html = $response->getContent();
        $sidebar = explode('</aside>', explode('<aside id="kt_app_sidebar"', $html, 2)[1], 2)[0];

        foreach (config('ui_visibility.hidden_navigation_routes') as $routeName) {
            $this->assertStringNotContainsString('href="'.route($routeName).'"', $sidebar, $routeName);
        }

        foreach (['dashboard', 'warehouse.stocks.index', 'sales.orders.index', 'retail.pos.index', 'tax.index'] as $routeName) {
            $this->assertStringContainsString('href="'.route($routeName).'"', $sidebar, $routeName);
        }

        $this->actingAs($user)->get(route('reports.attendance.index'))
            ->assertOk()
            ->assertDontSee('Produktivitas Shift');

        $sales = User::factory()->create();
        $sales->assignRole(Role::findOrCreate('sales'));
        $this->actingAs($sales)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Penjualan Bulan Ini')
            ->assertDontSee('Estimasi Bonus')
            ->assertDontSee('Pencapaian Target');
    }
}
