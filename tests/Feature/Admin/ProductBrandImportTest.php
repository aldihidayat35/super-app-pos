<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductBrandImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole(Role::findOrCreate('admin_config'));
    }

    public function test_admin_can_view_brand_import_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.product-brands.import.index'))
            ->assertOk()
            ->assertSee('Import Merek Produk')
            ->assertSee('Template Import');
    }

    public function test_brand_import_template_can_be_downloaded(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.product-brands.import.template'))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=template-import-merek.csv');
    }

    public function test_brand_import_preview_reports_invalid_rows(): void
    {
        $content = "code,name,description,is_active\n"
            .",,Deskripsi Merek Tanpa Nama,1\n";
        $file = UploadedFile::fake()->createWithContent('merek-invalid.csv', $content);

        $this->actingAs($this->admin)
            ->post(route('admin.product-brands.import.preview'), ['file' => $file])
            ->assertRedirect(route('admin.product-brands.import.index'))
            ->assertSessionHas('product_brand_import_preview');

        $preview = session('product_brand_import_preview');
        $this->assertNotEmpty($preview['errors']);

        $this->actingAs($this->admin)
            ->get(route('admin.product-brands.import.index'))
            ->assertOk()
            ->assertSee('Masih ada error validasi')
            ->assertSee('Baris 2');
    }

    public function test_valid_brand_import_preview_survives_render_and_can_be_committed(): void
    {
        $content = "code,name,description,is_active\n"
            ."BRD-MAYORA,Mayora,Produsen Biskuit dan Kopi,1\n"
            ."BRD-NESTLE,Nestle,Produsen Minuman Coklat,1\n";
        $file = UploadedFile::fake()->createWithContent('merek-valid.csv', $content);

        $this->actingAs($this->admin)
            ->post(route('admin.product-brands.import.preview'), ['file' => $file])
            ->assertRedirect(route('admin.product-brands.import.index'))
            ->assertSessionHas('product_brand_import_preview');

        $this->actingAs($this->admin)
            ->get(route('admin.product-brands.import.index'))
            ->assertOk()
            ->assertSee('Semua baris valid')
            ->assertSee('Commit Import Merek');

        $this->actingAs($this->admin)
            ->post(route('admin.product-brands.import.commit'))
            ->assertRedirect(route('admin.product-brands.import.index'))
            ->assertSessionHas('notification.type', 'success')
            ->assertSessionMissing('product_brand_import_preview');

        $this->assertDatabaseHas('product_brands', [
            'code' => 'BRD-MAYORA',
            'name' => 'Mayora',
            'description' => 'Produsen Biskuit dan Kopi',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('product_brands', [
            'code' => 'BRD-NESTLE',
            'name' => 'Nestle',
            'description' => 'Produsen Minuman Coklat',
            'is_active' => true,
        ]);
    }

    public function test_unauthorized_user_cannot_import_brands(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->get(route('admin.product-brands.import.index'))
            ->assertForbidden();
    }
}
