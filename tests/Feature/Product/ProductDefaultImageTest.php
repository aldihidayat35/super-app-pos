<?php

namespace Tests\Feature\Product;

use App\Models\Branch;
use App\Models\Product;
use App\Models\User;
use App\Models\WorkLocation;
use App\Services\Retail\PosCatalogService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductDefaultImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_product_without_image_returns_default_image_url(): void
    {
        $product = Product::factory()->create([
            'main_image_path' => null,
        ]);

        $this->assertFalse($product->hasCustomImage());
        $this->assertSame(Product::defaultImageUrl(), $product->main_image_url);
        $this->assertStringContainsString('assets/media/products/default-product.png', $product->main_image_url);
    }

    public function test_product_with_custom_image_returns_uploaded_path(): void
    {
        $product = Product::factory()->create([
            'main_image_path' => 'products/custom-photo.jpg',
        ]);

        $this->assertTrue($product->hasCustomImage());
        $this->assertSame('/storage/products/custom-photo.jpg', $product->main_image_url);
    }

    public function test_product_with_relation_images_returns_primary_image(): void
    {
        $product = Product::factory()->create([
            'main_image_path' => null,
        ]);

        $product->images()->create([
            'path' => 'products/gallery-primary.jpg',
            'is_primary' => true,
        ]);

        $this->assertTrue($product->hasCustomImage());
        $this->assertSame('/storage/products/gallery-primary.jpg', $product->main_image_url);
    }

    public function test_pos_catalog_service_includes_default_image_for_unimaged_products(): void
    {
        $workLocation = WorkLocation::factory()->create(['type' => 'branch']);
        $branch = Branch::factory()->create(['work_location_id' => $workLocation->id]);

        $cashier = $this->createUser('kasir');
        $cashier->workLocations()->attach($workLocation->id, ['is_default' => true, 'is_active' => true]);

        $product = Product::factory()->create([
            'status' => 'active',
            'main_image_path' => null,
        ]);

        /** @var PosCatalogService $catalog */
        $catalog = app(PosCatalogService::class);
        $result = $catalog->search($branch, $cashier, ['q' => $product->sku]);

        $this->assertNotEmpty($result['results']);
        $productData = collect($result['results'])->firstWhere('id', $product->id);
        $this->assertNotNull($productData);
        $this->assertSame(Product::defaultImageUrl(), $productData['image_url']);
    }

    public function test_admin_product_show_displays_default_photo_indicator(): void
    {
        $admin = $this->createUser('super_admin');
        $product = Product::factory()->create([
            'main_image_path' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.products.show', $product))
            ->assertOk()
            ->assertSee('Foto Default (Belum Diupload)')
            ->assertSee('default-product.png');
    }

    private function createUser(string $role): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
