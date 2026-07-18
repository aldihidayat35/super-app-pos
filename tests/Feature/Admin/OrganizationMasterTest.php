<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\DocumentSequence;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Organization\DocumentNumberService;
use App\Services\Organization\WorkLocationSyncService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OrganizationMasterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    #[Test]
    public function admin_config_can_manage_warehouse_and_uniqueness_is_enforced(): void
    {
        $admin = $this->adminConfig();

        $this->actingAs($admin)->post(route('admin.warehouses.store'), [
            'code' => 'GDG-A',
            'name' => 'Gudang A',
            'city' => 'Jakarta',
            'is_active' => '1',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.warehouses.store'), [
            'code' => 'GDG-A',
            'name' => 'Gudang Duplikat',
            'city' => 'Jakarta',
            'is_active' => '1',
        ])->assertSessionHasErrors('code');

        $this->assertDatabaseHas('work_locations', ['code' => 'GDG-A', 'type' => 'warehouse']);
    }

    #[Test]
    public function location_code_cannot_change_after_transactions_marker(): void
    {
        $admin = $this->adminConfig();
        $warehouse = Warehouse::factory()->create(['code' => 'GDG-TX', 'has_transactions' => true]);
        app(WorkLocationSyncService::class)->syncWarehouse($warehouse);

        $this->actingAs($admin)->put(route('admin.warehouses.update', $warehouse), [
            'code' => 'GDG-NEW',
            'name' => $warehouse->name,
            'city' => $warehouse->city,
            'is_active' => '1',
        ])->assertSessionHasErrors('code');
    }

    #[Test]
    public function branch_requires_active_default_warehouse_and_can_be_deactivated(): void
    {
        $admin = $this->adminConfig();
        $warehouse = Warehouse::factory()->create(['is_active' => true]);
        app(WorkLocationSyncService::class)->syncWarehouse($warehouse);

        $this->actingAs($admin)->post(route('admin.branches.store'), [
            'primary_warehouse_id' => $warehouse->id,
            'code' => 'TKO-A',
            'name' => 'Toko A',
            'price_configuration' => 'standard',
            'closing_configuration' => 'daily',
            'is_closing_required' => '1',
            'is_active' => '1',
        ])->assertRedirect();

        $branch = Branch::query()->where('code', 'TKO-A')->firstOrFail();
        $this->assertSame($warehouse->id, $branch->primary_warehouse_id);

        $this->actingAs($admin)->patch(route('admin.branches.deactivate', $branch))->assertRedirect();
        $this->assertFalse($branch->fresh()->is_active);
        $this->assertFalse($branch->fresh()->workLocation?->is_active);
    }

    #[Test]
    public function branch_detail_operational_tabs_are_interactive(): void
    {
        $admin = $this->adminConfig();
        $warehouse = Warehouse::factory()->create(['is_active' => true]);
        app(WorkLocationSyncService::class)->syncWarehouse($warehouse);

        $branch = Branch::factory()->create([
            'primary_warehouse_id' => $warehouse->id,
            'is_active' => true,
        ]);
        app(WorkLocationSyncService::class)->syncBranch($branch);

        $this->actingAs($admin)
            ->get(route('admin.branches.show', $branch))
            ->assertOk()
            ->assertSee('data-bs-toggle="tab"', false)
            ->assertSee('branch-users-pane')
            ->assertSee('branch-stocks-pane')
            ->assertSee('branch-shifts-pane')
            ->assertSee('branch-performance-pane')
            ->assertSee('branch-history-pane');
    }

    #[Test]
    public function scoped_warehouse_head_only_sees_assigned_warehouse(): void
    {
        $allowed = Warehouse::factory()->create(['name' => 'Gudang Boleh']);
        $blocked = Warehouse::factory()->create(['name' => 'Gudang Tertutup']);
        $sync = app(WorkLocationSyncService::class);
        $allowedLocation = $sync->syncWarehouse($allowed);
        $sync->syncWarehouse($blocked);

        $user = User::factory()->create();
        $user->assignRole(Role::findByName('kepala_gudang'));
        $user->workLocations()->sync([$allowedLocation->id => ['is_default' => true, 'is_active' => true]]);

        $this->actingAs($user)->get(route('admin.warehouses.index'))
            ->assertOk()
            ->assertSee('Gudang Boleh')
            ->assertDontSee('Gudang Tertutup');

        $this->actingAs($user)->get(route('admin.warehouses.show', $blocked))->assertForbidden();
    }

    #[Test]
    public function general_settings_are_validated_and_saved(): void
    {
        $admin = $this->adminConfig();

        $this->actingAs($admin)->put(route('admin.settings.general.update'), [
            'company_name' => 'GudangToko',
            'timezone' => 'UTC',
            'locale' => 'id',
            'currency' => 'IDR',
            'upload_limit_mb' => 2,
            'default_minimum_margin_percent' => 10,
            'overpricing_tolerance_percent' => 20,
            'invoice_template' => 'default',
            'receipt_template' => 'default',
        ])->assertSessionHasErrors('timezone');

        $this->actingAs($admin)->put(route('admin.settings.general.update'), [
            'company_name' => 'GudangToko',
            'company_address' => 'Jakarta',
            'company_phone' => '021',
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id',
            'currency' => 'IDR',
            'upload_limit_mb' => 2,
            'default_minimum_margin_percent' => 10,
            'overpricing_tolerance_percent' => 20,
            'invoice_template' => 'default',
            'receipt_template' => 'default',
        ])->assertRedirect();

        $this->assertDatabaseHas('system_settings', ['key' => 'timezone']);
    }

    #[Test]
    public function document_number_service_generates_unique_sequential_numbers(): void
    {
        $service = app(DocumentNumberService::class);

        $numbers = [];

        for ($i = 0; $i < 10; $i++) {
            $numbers[] = $service->next('po', null, 2026);
        }

        $this->assertCount(10, array_unique($numbers));
        $this->assertSame('PO/GLOBAL/2026/00001', $numbers[0]);
        $this->assertSame(11, DocumentSequence::query()->where('document_type', 'po')->where('year', 2026)->value('next_number'));
    }

    private function adminConfig(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('admin_config'));

        return $user;
    }
}
