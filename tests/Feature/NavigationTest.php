<?php

namespace Tests\Feature;

use App\Models\User;
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
}
