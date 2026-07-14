<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\WorkLocation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserRbacManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    #[Test]
    public function admin_can_open_all_p05_pages(): void
    {
        $admin = $this->createAdmin();
        $role = Role::findOrCreate('kepala_toko');
        $user = User::factory()->create();
        $user->assignRole($role);
        WorkLocation::factory()->create(['name' => 'Gudang Utama']);

        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->assertSee('Daftar Pengguna');
        $this->actingAs($admin)->get(route('admin.users.create'))->assertOk()->assertSee('Tambah Pengguna');
        $this->actingAs($admin)->get(route('admin.users.show', $user))->assertOk()->assertSee($user->email);
        $this->actingAs($admin)->get(route('admin.users.edit', $user))->assertOk()->assertSee('Edit Pengguna');
        $this->actingAs($admin)->get(route('admin.users.locations.edit', $user))->assertOk()->assertSee('Penugasan Lokasi Kerja');
        $this->actingAs($admin)->get(route('admin.roles.index'))->assertOk()->assertSee('Daftar Role');
        $this->actingAs($admin)->get(route('admin.roles.show', $role))->assertOk()->assertSee('Matriks Permission');
        $this->actingAs($admin)->get(route('admin.permissions.index'))->assertOk()->assertSee('Daftar Permission');
    }

    #[Test]
    public function admin_can_create_and_update_user_with_roles(): void
    {
        $admin = $this->createAdmin();
        $role = Role::findOrCreate('kasir');
        $location = WorkLocation::factory()->create(['type' => 'branch']);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Kasir Baru',
            'username' => 'kasir_baru',
            'email' => 'kasirbaru@example.test',
            'phone_number' => '628111111111',
            'is_active' => '1',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'roles' => [$role->id],
            'locations' => [$location->id],
            'default_location_id' => $location->id,
            'location_effective_from' => now()->toDateString(),
            'location_is_active' => '1',
        ]);

        $user = User::query()->where('email', 'kasirbaru@example.test')->firstOrFail();
        $response->assertRedirect(route('admin.users.show', $user));
        $this->assertTrue(Hash::check('Password123!', $user->password));
        $this->assertTrue($user->hasRole('kasir'));
        $this->assertDatabaseHas('user_work_locations', [
            'user_id' => $user->id,
            'work_location_id' => $location->id,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => 'Kasir Update',
            'username' => 'kasir_update',
            'email' => 'kasirupdate@example.test',
            'phone_number' => '628222222222',
            'is_active' => '0',
            'roles' => [],
        ])->assertRedirect(route('admin.users.show', $user));

        $user->refresh();
        $this->assertSame('Kasir Update', $user->name);
        $this->assertFalse($user->is_active);
        $this->assertTrue($user->roles->isEmpty());
    }

    #[Test]
    public function admin_can_update_role_permission_matrix(): void
    {
        $admin = $this->createAdmin();
        $role = Role::findOrCreate('staff_gudang');
        $permission = Permission::findOrCreate('warehouse.stock.view');

        $this->actingAs($admin)->put(route('admin.roles.permissions.update', $role), [
            'permissions' => [$permission->id],
        ])->assertRedirect(route('admin.roles.show', $role));

        $this->assertTrue($role->fresh()->hasPermissionTo('warehouse.stock.view'));
    }

    #[Test]
    public function admin_can_assign_work_locations_to_user(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $warehouse = WorkLocation::factory()->create(['type' => 'warehouse', 'name' => 'Gudang Utama']);
        $branch = WorkLocation::factory()->create(['type' => 'branch', 'name' => 'Toko Utama']);

        $this->actingAs($admin)->put(route('admin.users.locations.update', $user), [
            'locations' => [$warehouse->id, $branch->id],
            'default_location_id' => $branch->id,
            'effective_from' => now()->toDateString(),
            'is_active' => '1',
        ])->assertRedirect(route('admin.users.show', $user));

        $this->assertDatabaseHas('user_work_locations', [
            'user_id' => $user->id,
            'work_location_id' => $branch->id,
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function admin_user_can_manage_users_but_cannot_access_rbac_pages(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('admin_user'));

        $this->actingAs($user)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($user)->get(route('admin.roles.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.permissions.index'))->assertForbidden();
    }

    #[Test]
    public function admin_can_deactivate_export_and_send_password_reset(): void
    {
        Notification::fake();
        $admin = $this->createAdmin();
        $user = User::factory()->create(['email' => 'reset-target@example.test']);

        $this->actingAs($admin)->post(route('admin.users.password-reset', $user))->assertRedirect();
        $this->actingAs($admin)->patch(route('admin.users.deactivate', $user))->assertRedirect();
        $this->assertFalse($user->fresh()->is_active);

        $this->actingAs($admin)->get(route('admin.users.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    #[Test]
    public function super_admin_can_create_duplicate_and_update_role(): void
    {
        $admin = $this->createAdmin();
        $permission = Permission::findByName('products.view');

        $this->actingAs($admin)->post(route('admin.roles.store'), [
            'name' => 'custom_checker',
            'label' => 'Custom Checker',
            'description' => 'Role uji',
            'permissions' => [$permission->id],
        ])->assertRedirect();

        $role = Role::findByName('custom_checker');
        $this->assertTrue($role->hasPermissionTo('products.view'));

        $this->actingAs($admin)->post(route('admin.roles.duplicate', $role))->assertRedirect();
        $this->assertTrue(Role::query()->where('name', 'custom_checker_copy')->exists());
    }

    #[Test]
    public function only_super_admin_can_delete_unused_custom_role(): void
    {
        $superAdmin = $this->createAdmin();
        $role = Role::query()->create([
            'name' => 'temporary_role',
            'label' => 'Role Sementara',
            'guard_name' => 'web',
            'is_system' => false,
        ]);

        $this->actingAs($superAdmin)
            ->get(route('admin.roles.show', $role))
            ->assertOk()
            ->assertSee('Hapus Role');

        $this->actingAs($superAdmin)
            ->delete(route('admin.roles.destroy', $role))
            ->assertRedirect(route('admin.roles.index'));

        $this->assertDatabaseMissing('roles', ['name' => 'temporary_role']);
    }

    #[Test]
    public function system_or_used_role_cannot_be_deleted(): void
    {
        $superAdmin = $this->createAdmin();
        $systemRole = Role::findByName('super_admin');
        $usedRole = Role::query()->create([
            'name' => 'used_custom_role',
            'label' => 'Role Dipakai',
            'guard_name' => 'web',
            'is_system' => false,
        ]);
        User::factory()->create()->assignRole($usedRole);

        $this->actingAs($superAdmin)->delete(route('admin.roles.destroy', $systemRole))->assertForbidden();
        $this->actingAs($superAdmin)->delete(route('admin.roles.destroy', $usedRole))->assertForbidden();

        $this->assertDatabaseHas('roles', ['name' => 'super_admin']);
        $this->assertDatabaseHas('roles', ['name' => 'used_custom_role']);
    }

    #[Test]
    public function non_super_admin_cannot_delete_role_even_with_role_update_permission(): void
    {
        $adminConfig = User::factory()->create();
        $adminConfig->assignRole(Role::findByName('admin_config'));
        $role = Role::query()->create([
            'name' => 'delete_by_admin_config',
            'label' => 'Role Tidak Boleh Dihapus',
            'guard_name' => 'web',
            'is_system' => false,
        ]);

        $this->actingAs($adminConfig)->delete(route('admin.roles.destroy', $role))->assertForbidden();
        $this->assertDatabaseHas('roles', ['name' => 'delete_by_admin_config']);
    }

    #[Test]
    public function seeded_role_matrix_enforces_sensitive_permissions(): void
    {
        $ownerViewer = User::factory()->create();
        $ownerViewer->assignRole(Role::findByName('owner_viewer'));
        $ownerApprover = User::factory()->create();
        $ownerApprover->assignRole(Role::findByName('owner_approver'));
        $cashier = User::factory()->create();
        $cashier->assignRole(Role::findByName('kasir'));
        $warehouseHead = User::factory()->create();
        $warehouseHead->assignRole(Role::findByName('kepala_gudang'));

        $this->assertFalse($ownerViewer->can('approvals.approve'));
        $this->assertTrue($ownerApprover->can('approvals.approve'));
        $this->assertFalse($cashier->can('margins.view_sensitive'));
        $this->assertFalse($warehouseHead->can('pos.view'));
    }

    #[Test]
    public function location_policy_denies_cross_location_access_for_scoped_user(): void
    {
        $allowed = WorkLocation::factory()->create(['type' => 'branch']);
        $blocked = WorkLocation::factory()->create(['type' => 'branch']);
        $cashier = User::factory()->create();
        $cashier->assignRole(Role::findByName('kasir'));
        $cashier->workLocations()->sync([
            $allowed->id => ['is_default' => true, 'is_active' => true],
        ]);

        $this->assertTrue(Gate::forUser($cashier)->allows('view', $allowed));
        $this->assertTrue(Gate::forUser($cashier)->denies('view', $blocked));
    }

    private function createAdmin(): User
    {
        $role = Role::findOrCreate('super_admin');
        $user = User::factory()->create(['email' => 'admin@example.test']);
        $user->assignRole($role);

        return $user;
    }
}
