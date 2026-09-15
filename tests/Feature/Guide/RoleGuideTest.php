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
            ->assertSee('Check-in')
            ->assertSee('Diagram alur persediaan toko')
            ->assertSee('data-guide-flow="store-stock"', false)
            ->assertSee('Pembelian Toko')
            ->assertSee('Konversi ke')
            ->assertSee('Stok Reguler');

        $this->actingAs($user)->get(route('guides.show', 'owner'))->assertForbidden();
    }

    #[Test]
    public function internal_store_guide_renders_every_registered_backend_workflow(): void
    {
        $this->assertGuideRendersConfiguredWorkflows(
            $this->userWithRole('kasir'),
            'toko-internal',
            'guide/toko-internal.md',
        );
    }

    #[Test]
    public function warehouse_guide_renders_every_documented_backend_workflow(): void
    {
        $this->assertGuideRendersConfiguredWorkflows(
            $this->userWithRole('kepala_gudang'),
            'gudang',
            'guide/gudang.md',
        );
    }

    #[Test]
    public function purchasing_guide_renders_every_documented_backend_workflow(): void
    {
        $this->assertGuideRendersConfiguredWorkflows(
            $this->userWithRole('purchasing'),
            'purchasing-supplier',
            'guide/purchasing-supplier.md',
        );
    }

    #[Test]
    public function owner_guide_renders_every_documented_backend_workflow(): void
    {
        $this->assertGuideRendersConfiguredWorkflows(
            $this->userWithRole('owner_approver'),
            'owner',
            'guide/owner.md',
        );
    }

    #[Test]
    public function every_registered_workflow_is_used_by_an_operational_guide(): void
    {
        $configuredFlows = config('guide-flows');
        $documentedFlows = [];

        $this->assertIsArray($configuredFlows);

        foreach (glob(base_path('guide/*.md')) ?: [] as $guidePath) {
            $guideSource = file_get_contents($guidePath);
            $this->assertIsString($guideSource);
            preg_match_all('/```guide-flow\s+([a-z0-9-]+)\s+```/s', $guideSource, $matches);
            $documentedFlows = [...$documentedFlows, ...$matches[1]];
        }

        $this->assertEqualsCanonicalizing(array_keys($configuredFlows), array_values(array_unique($documentedFlows)));
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

    private function assertGuideRendersConfiguredWorkflows(User $user, string $slug, string $relativeGuidePath): void
    {
        $response = $this->actingAs($user)->get(route('guides.show', $slug));
        $response->assertOk()->assertDontSee('language-guide-flow', false);

        $guideSource = file_get_contents(base_path($relativeGuidePath));
        $this->assertIsString($guideSource);
        preg_match_all('/```guide-flow\s+([a-z0-9-]+)\s+```/s', $guideSource, $matches);
        $configuredFlows = config('guide-flows');
        $renderedHtml = $response->getContent();

        $this->assertIsArray($configuredFlows);
        $this->assertNotEmpty($matches[1], "Panduan {$slug} tidak memiliki diagram alur.");
        $this->assertIsString($renderedHtml);
        $this->assertSame(count($matches[1]), substr_count($renderedHtml, 'class="guide-diagram guide-workflow"'));

        foreach (array_values(array_unique($matches[1])) as $flowId) {
            $this->assertArrayHasKey($flowId, $configuredFlows, "Diagram {$flowId} belum terdaftar.");
            $flow = $configuredFlows[$flowId];
            $response->assertSee('data-guide-flow="'.$flowId.'"', false);
            $this->assertNotEmpty($flow['lanes'] ?? [], "Diagram {$flowId} tidak memiliki alur.");
            $this->assertNotEmpty($flow['verified_by'] ?? [], "Diagram {$flowId} tidak memiliki sumber verifikasi backend.");

            foreach ($flow['verified_by'] ?? [] as $backendPath) {
                $this->assertFileExists(base_path($backendPath), "Sumber backend diagram {$flowId} tidak ditemukan: {$backendPath}");
            }
        }
    }
}
