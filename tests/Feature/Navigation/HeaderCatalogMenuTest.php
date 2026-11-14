<?php

namespace Tests\Feature\Navigation;

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeaderCatalogMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_header_renders_catalog_menu_next_to_theme_toggle_for_internal_user(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('gt-header-catalog-btn', false);
        $response->assertSee('Katalog', false);
        $response->assertSee(route('admin.products.index'));
        $response->assertSee('gt-theme-toggle-pill', false);
    }

    public function test_header_renders_b2b_catalog_menu_for_b2b_customer(): void
    {
        $customer = Customer::factory()->create([
            'business_name' => 'Resto Mitra Sukses',
            'is_active' => true,
        ]);

        $b2bUser = User::factory()->create([
            'is_active' => true,
        ]);
        $b2bUser->assignRole('langganan_owner');
        $customer->users()->attach($b2bUser->id, ['role' => 'langganan_owner', 'is_active' => true]);

        $response = $this->actingAs($b2bUser)->get(route('langganan.dashboard'));

        $response->assertOk();
        $response->assertSee('gt-header-catalog-btn', false);
        $response->assertSee('Katalog', false);
        $response->assertSee(route('langganan.katalog.index'));
    }

    public function test_header_catalog_menu_shows_active_state_when_on_catalog_page(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');

        $response = $this->actingAs($admin)->get(route('admin.products.index'));

        $response->assertOk();
        $response->assertSee('gt-header-catalog-btn active', false);
    }
}
