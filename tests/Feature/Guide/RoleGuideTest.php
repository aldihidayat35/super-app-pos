<?php

namespace Tests\Feature\Guide;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleGuideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    #[Test]
    public function guest_must_login_before_opening_guides(): void
    {
        $this->get(route('guides.index'))->assertRedirect(route('login'));
    }

    #[Test]
    public function super_admin_can_see_and_open_all_role_guides(): void
    {
        $user = $this->userWithRole('super_admin');

        $this->actingAs($user)->get(route('guides.index'))
            ->assertOk()
            ->assertSee('Panduan Umum Pengguna')
            ->assertSee('Panduan Owner')
            ->assertSee('Panduan Super Admin')
            ->assertSee('Panduan Operasional Gudang')
            ->assertSee('Panduan Purchasing &amp; Supplier', false)
            ->assertSee('Panduan Toko Internal')
            ->assertSee('Panduan Langganan/B2B');

        $this->actingAs($user)->get(route('guides.show', 'purchasing-supplier'))
            ->assertOk()
            ->assertSee('Alur kerja purchasing dari awal sampai selesai')
            ->assertSee('Checklist harian Purchasing');
    }

    #[Test]
    public function cashier_only_sees_general_and_internal_store_guides(): void
    {
        $user = $this->userWithRole('kasir');

        $this->actingAs($user)->get(route('guides.index'))
            ->assertOk()
            ->assertSee('Dokumentasi Panduan')
            ->assertSee('Panduan Umum Pengguna')
            ->assertSee('Panduan Toko Internal')
            ->assertDontSee('Panduan Owner')
            ->assertDontSee('Panduan Super Admin')
            ->assertDontSee('Panduan Operasional Gudang')
            ->assertDontSee('Panduan Purchasing &amp; Supplier', false);

        $this->actingAs($user)->get(route('guides.show', 'toko-internal'))
            ->assertOk()
            ->assertSee('Alur harian kasir')
            ->assertSee('Check-in');

        $this->actingAs($user)->get(route('guides.show', 'owner'))->assertForbidden();
    }

    #[Test]
    public function purchasing_user_sees_supplier_workflow_but_not_store_guide(): void
    {
        $user = $this->userWithRole('purchasing');

        $this->actingAs($user)->get(route('guides.index'))
            ->assertOk()
            ->assertSee('Panduan Purchasing &amp; Supplier', false)
            ->assertDontSee('Panduan Toko Internal')
            ->assertDontSee('Panduan Langganan/B2B');

        $this->actingAs($user)->get(route('guides.show', 'purchasing-supplier'))
            ->assertOk()
            ->assertSee('Membuat Purchase Order')
            ->assertSee('Evaluasi performa supplier');
    }

    #[Test]
    public function b2b_user_sees_only_general_and_b2b_guides(): void
    {
        $user = $this->userWithRole('langganan_staff');

        $this->actingAs($user)->get(route('guides.index'))
            ->assertOk()
            ->assertSee('Panduan Umum Pengguna')
            ->assertSee('Panduan Langganan/B2B')
            ->assertDontSee('Panduan Operasional Gudang')
            ->assertDontSee('Panduan Toko Internal');

        $this->actingAs($user)->get(route('guides.show', 'langganan-b2b'))
            ->assertOk()
            ->assertSee('Alur membuat order')
            ->assertSee('Tracking pengiriman dan bukti terima');
    }

    #[Test]
    public function custom_role_without_mapping_still_receives_general_guide(): void
    {
        $role = Role::findOrCreate('auditor_tamu');
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        $this->actingAs($user)->get(route('guides.index'))
            ->assertOk()
            ->assertSee('Panduan Umum Pengguna')
            ->assertDontSee('Panduan Owner');

        $this->actingAs($user)->get(route('guides.show', 'umum'))
            ->assertOk()
            ->assertSee('Troubleshooting langkah demi langkah');
    }

    #[Test]
    public function every_system_role_receives_its_operational_guide(): void
    {
        $expectedGuides = [
            'owner_viewer' => 'Panduan Owner',
            'owner_approver' => 'Panduan Owner',
            'admin_user' => 'Panduan Super Admin',
            'admin_config' => 'Panduan Super Admin',
            'kepala_gudang' => 'Panduan Operasional Gudang',
            'staff_gudang' => 'Panduan Operasional Gudang',
            'picker_packer' => 'Panduan Operasional Gudang',
            'purchasing' => 'Panduan Purchasing & Supplier',
            'kepala_toko' => 'Panduan Toko Internal',
            'kasir' => 'Panduan Toko Internal',
            'supervisor_shift' => 'Panduan Toko Internal',
            'langganan_owner' => 'Panduan Langganan/B2B',
            'langganan_staff' => 'Panduan Langganan/B2B',
        ];

        foreach ($expectedGuides as $role => $guideTitle) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('guides.index'))
                ->assertOk()
                ->assertSee($guideTitle);
        }
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(Role::findByName($role));

        return $user;
    }
}
