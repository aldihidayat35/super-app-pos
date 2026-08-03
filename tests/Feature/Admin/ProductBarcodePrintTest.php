<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductBarcodePrintTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        config()->set('gudangtoko.barcode.thermal_width_mm', 80);
        config()->set('gudangtoko.barcode.thermal_height_mm', 40);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole(Role::findOrCreate('admin_config'));
    }

    public function test_preview_and_pdf_share_label_structure_and_repeat_requested_count(): void
    {
        $product = $this->product('barcode');

        $response = $this->actingAs($this->admin)->get(route('admin.products.barcodes.index', [
            'product_id' => $product->id,
            'label_count' => 2,
            'paper_size' => 'A4',
        ]));

        $response
            ->assertOk()
            ->assertSee('2 label pada 1 halaman')
            ->assertSee('A4 (210 × 297 mm)')
            ->assertSee('barcode-page--a4', false)
            ->assertSee('data:image/png;base64,', false);

        $this->assertSame(2, substr_count($response->getContent(), 'data-barcode-label'));
    }

    public function test_a4_pdf_uses_standard_a4_media_box_and_eighteen_labels_per_page(): void
    {
        $product = $this->product('barcode');

        $response = $this->actingAs($this->admin)->get(route('admin.products.barcodes.pdf', [
            'product_id' => $product->id,
            'label_count' => 18,
            'paper_size' => 'A4',
        ]));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $content = $response->getContent();

        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertMatchesRegularExpression('/\/MediaBox\s*\[\s*0\.000\s+0\.000\s+595\.280\s+841\.890\s*\]/', $content);
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page\b/', $content));
    }

    public function test_thermal_pdf_uses_configured_eighty_by_forty_millimeter_page_per_label(): void
    {
        $product = $this->product('qr');

        $response = $this->actingAs($this->admin)->get(route('admin.products.barcodes.pdf', [
            'product_id' => $product->id,
            'label_count' => 2,
            'paper_size' => 'thermal',
        ]));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $content = $response->getContent();

        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertMatchesRegularExpression('/\/MediaBox\s*\[\s*0\.000\s+0\.000\s+226\.772\s+113\.386\s*\]/', $content);
        $this->assertSame(2, preg_match_all('/\/Type\s*\/Page\b/', $content));
    }

    public function test_invalid_paper_size_and_label_count_are_rejected(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.products.barcodes.index', [
                'label_count' => 101,
                'paper_size' => 'letter',
            ]))
            ->assertSessionHasErrors(['label_count', 'paper_size']);
    }

    private function product(string $barcodeType): Product
    {
        $product = Product::factory()->create([
            'sku' => 'PRD-BARCODE-001',
            'name' => 'Produk Barcode Pengujian',
        ]);
        $product->barcodes()->create([
            'code' => '8999001234567',
            'type' => $barcodeType,
            'is_primary' => true,
            'is_active' => true,
        ]);

        return $product;
    }
}
