<?php

namespace Tests\Feature\Admin;

use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductCategoryImportTest extends TestCase
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

    public function test_admin_can_view_category_import_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.product-categories.import.index'))
            ->assertOk()
            ->assertSee('Import Kategori Produk')
            ->assertSee('Template Import');
    }

    public function test_category_import_template_can_be_downloaded(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.product-categories.import.template'))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=template-import-kategori.csv');
    }

    public function test_category_import_preview_reports_invalid_rows(): void
    {
        $content = "code,name,parent_code,sort_order,icon,is_active\n"
            .",,KAT-TIDAK-ADA,bukan-angka,,aktif\n";
        $file = UploadedFile::fake()->createWithContent('kategori-invalid.csv', $content);

        $this->actingAs($this->admin)
            ->post(route('admin.product-categories.import.preview'), ['file' => $file])
            ->assertRedirect(route('admin.product-categories.import.index'))
            ->assertSessionHas('product_category_import_preview');

        $preview = session('product_category_import_preview');
        $this->assertNotEmpty($preview['errors']);

        $this->actingAs($this->admin)
            ->get(route('admin.product-categories.import.index'))
            ->assertOk()
            ->assertSee('Masih ada error validasi')
            ->assertSee('Baris 2');
    }

    public function test_valid_category_import_preview_survives_render_and_can_be_committed(): void
    {
        $content = "code,name,parent_code,sort_order,icon,is_active\n"
            ."KAT-INDOK,Kategori Induk,,1,ki-home,1\n"
            ."KAT-ANAK,Sub Kategori,KAT-INDOK,2,ki-file,1\n";
        $file = UploadedFile::fake()->createWithContent('kategori-valid.csv', $content);

        $this->actingAs($this->admin)
            ->post(route('admin.product-categories.import.preview'), ['file' => $file])
            ->assertRedirect(route('admin.product-categories.import.index'))
            ->assertSessionHas('product_category_import_preview');

        $this->actingAs($this->admin)
            ->get(route('admin.product-categories.import.index'))
            ->assertOk()
            ->assertSee('Semua baris valid')
            ->assertSee('Commit Import Kategori');

        $this->actingAs($this->admin)
            ->post(route('admin.product-categories.import.commit'))
            ->assertRedirect(route('admin.product-categories.import.index'))
            ->assertSessionHas('notification.type', 'success')
            ->assertSessionMissing('product_category_import_preview');

        $parent = ProductCategory::query()->where('code', 'KAT-INDOK')->first();
        $this->assertNotNull($parent);
        $this->assertSame('Kategori Induk', $parent->name);
        $this->assertNull($parent->parent_id);

        $child = ProductCategory::query()->where('code', 'KAT-ANAK')->first();
        $this->assertNotNull($child);
        $this->assertSame('Sub Kategori', $child->name);
        $this->assertSame($parent->id, $child->parent_id);
    }

    public function test_unauthorized_user_cannot_import_categories(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->get(route('admin.product-categories.import.index'))
            ->assertForbidden();
    }
}
