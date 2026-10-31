<?php

namespace Tests\Feature\Pricing;

use App\Enums\ProductPriceStatus;
use App\Models\Branch;
use App\Models\PriceApprovalRequest;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Stock;
use App\Models\StockBatch;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkLocation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductPriceUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $storeManager;

    private WorkLocation $locationA;

    private WorkLocation $locationB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->locationA = WorkLocation::factory()->create([
            'name' => 'Toko Cabang Jakarta',
            'type' => 'branch',
            'is_active' => true,
        ]);

        Branch::factory()->create([
            'name' => 'Cabang Jakarta',
            'work_location_id' => $this->locationA->id,
            'is_active' => true,
        ]);

        $this->locationB = WorkLocation::factory()->create([
            'name' => 'Toko Cabang Surabaya',
            'type' => 'branch',
            'is_active' => true,
        ]);

        Branch::factory()->create([
            'name' => 'Cabang Surabaya',
            'work_location_id' => $this->locationB->id,
            'is_active' => true,
        ]);

        // Super Admin user
        $this->superAdmin = User::factory()->create(['is_active' => true]);
        $this->superAdmin->assignRole(Role::findOrCreate('super_admin'));

        // Non-super-admin user: kepala_toko assigned to locationA
        $this->storeManager = User::factory()->create(['is_active' => true]);
        $this->storeManager->assignRole(Role::findOrCreate('kepala_toko'));
        $this->storeManager->givePermissionTo(['prices.view', 'prices.update', 'margins.view_sensitive']);
        $this->storeManager->workLocations()->attach($this->locationA->id, [
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    public function test_super_admin_can_freely_select_location(): void
    {
        $this->actingAs($this->superAdmin);

        // Super Admin visits with location A
        $responseA = $this->get(route('pricing.product-prices.index', ['work_location_id' => $this->locationA->id]));
        $responseA->assertOk();
        $responseA->assertSee($this->locationA->name);
        $responseA->assertDontSee('Terkunci:');

        // Super Admin visits with location B
        $responseB = $this->get(route('pricing.product-prices.index', ['work_location_id' => $this->locationB->id]));
        $responseB->assertOk();
        $responseB->assertSee($this->locationB->name);
        $responseB->assertDontSee('Terkunci:');
    }

    public function test_non_super_admin_location_is_locked_to_assigned_work_location(): void
    {
        $this->actingAs($this->storeManager);

        // Store manager attempts to access with location B in query
        $response = $this->get(route('pricing.product-prices.index', ['work_location_id' => $this->locationB->id]));

        $response->assertOk();
        // Location is locked to assigned location A
        $response->assertSee('Terkunci sesuai lokasi penugasan akun Anda.');
        $response->assertSee($this->locationA->name);
    }

    public function test_non_super_admin_cannot_save_price_for_unassigned_work_location(): void
    {
        $product = $this->createProduct('10000.00');

        $this->actingAs($this->storeManager);

        // Attempt to save for location B
        $response = $this->postJson(route('pricing.product-prices.save-item'), [
            'product_id' => $product->id,
            'work_location_id' => $this->locationB->id,
            'prices' => [
                'ring_retail' => '15000',
                'ring_b2b' => '14000',
                'ring_pos' => '15000',
                'ring_4' => '13000',
            ],
            'unlimited' => true,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        // Price must be forced and locked to location A, NOT location B
        $this->assertDatabaseHas('product_prices', [
            'product_id' => $product->id,
            'work_location_id' => $this->locationA->id,
            'price_ring' => 'Ring Retail',
            'recommended_price' => '15000.00',
        ]);

        $this->assertDatabaseMissing('product_prices', [
            'product_id' => $product->id,
            'work_location_id' => $this->locationB->id,
        ]);
    }

    public function test_product_info_displays_tiered_hpp_correctly(): void
    {
        $product = $this->createProduct('10000.00');

        $stock = Stock::query()->create([
            'product_id' => $product->id,
            'work_location_id' => $this->locationA->id,
            'warehouse_location_id' => null,
            'location_scope_key' => 'work:'.$this->locationA->id,
            'quantity_on_hand' => '80.0000',
            'quantity_reserved' => '0.0000',
            'quantity_damaged' => '0.0000',
            'cost_value' => '880000.00',
        ]);

        // Tier 1: 50 pcs at Rp 10.000
        $this->createBatch($product, $stock, $this->locationA, 'BATCH-001', '50.0000', '10000.00');
        // Tier 2: 30 pcs at Rp 12.000
        $this->createBatch($product, $stock, $this->locationA, 'BATCH-002', '30.0000', '12000.00');

        $this->actingAs($this->superAdmin);

        $response = $this->get(route('pricing.product-prices.index', ['work_location_id' => $this->locationA->id]));
        $response->assertOk();

        // Check tiered HPP elements in view
        $response->assertSee('HPP Bertingkat');
        $response->assertSee('Rp10.000');
        $response->assertSee('50');
        $response->assertSee('Rp12.000');
        $response->assertSee('30');
    }

    public function test_product_info_masks_hpp_for_user_without_sensitive_margin_permission(): void
    {
        $product = $this->createProduct('10000.00');

        // User without margins.view_sensitive
        $cashier = User::factory()->create(['is_active' => true]);
        $cashier->assignRole(Role::findOrCreate('kasir'));
        $cashier->givePermissionTo(['prices.view']);
        $cashier->workLocations()->attach($this->locationA->id, [
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->actingAs($cashier);

        $response = $this->get(route('pricing.product-prices.index'));
        $response->assertOk();
        $response->assertSee('HPP Dirahasiakan');
        $response->assertDontSee('<span class="text-muted">HPP:</span>', false);
        $response->assertDontSee('HPP Bertingkat (', false);
    }

    public function test_save_item_saves_all_four_rings_with_unlimited_validity_and_active_status_without_approval(): void
    {
        $product = $this->createProduct('10000.00');

        $this->actingAs($this->superAdmin);

        $response = $this->postJson(route('pricing.product-prices.save-item'), [
            'product_id' => $product->id,
            'work_location_id' => $this->locationA->id,
            'prices' => [
                'ring_retail' => '15000',
                'ring_b2b' => '13500',
                'ring_pos' => '15000',
                'ring_4' => '12000',
            ],
            'unlimited' => true,
            'notes' => 'Setup harga awal',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'product_id' => $product->id,
        ]);

        // Check 4 active rings created
        $retailPrice = ProductPrice::where('product_id', $product->id)->where('price_ring', 'Ring Retail')->first();
        $b2bPrice = ProductPrice::where('product_id', $product->id)->where('price_ring', 'Ring B2B')->first();
        $posPrice = ProductPrice::where('product_id', $product->id)->where('price_ring', 'Ring POS')->first();
        $ring4Price = ProductPrice::where('product_id', $product->id)->where('price_ring', 'Ring 4')->first();

        $this->assertNotNull($retailPrice);
        $this->assertSame('15000.00', $retailPrice->recommended_price);
        $this->assertSame(ProductPriceStatus::ACTIVE, $retailPrice->status);
        $this->assertSame(now()->toDateString(), (string) $retailPrice->starts_at?->format('Y-m-d'));
        $this->assertNull($retailPrice->ends_at);

        $this->assertNotNull($b2bPrice);
        $this->assertSame('13500.00', $b2bPrice->recommended_price);
        $this->assertSame(ProductPriceStatus::ACTIVE, $b2bPrice->status);
        $this->assertNull($b2bPrice->ends_at);

        $this->assertNotNull($posPrice);
        $this->assertSame('15000.00', $posPrice->recommended_price);
        $this->assertSame(ProductPriceStatus::ACTIVE, $posPrice->status);
        $this->assertNull($posPrice->ends_at);

        $this->assertNotNull($ring4Price);
        $this->assertSame('12000.00', $ring4Price->recommended_price);
        $this->assertSame(ProductPriceStatus::ACTIVE, $ring4Price->status);
        $this->assertNull($ring4Price->ends_at);

        // Total elimination of approval: NO approval request must be generated
        $this->assertSame(0, PriceApprovalRequest::where('product_id', $product->id)->count());
    }

    public function test_save_item_records_audit_history_and_spatie_activity_log_on_change(): void
    {
        $product = $this->createProduct('10000.00');

        $this->actingAs($this->superAdmin);

        // 1. Initial save
        $this->postJson(route('pricing.product-prices.save-item'), [
            'product_id' => $product->id,
            'work_location_id' => $this->locationA->id,
            'prices' => [
                'ring_retail' => '15000',
            ],
            'unlimited' => true,
        ])->assertOk();

        $initialHistoryCount = PriceHistory::where('product_id', $product->id)->count();
        $this->assertSame(1, $initialHistoryCount);

        // 2. Update price from 15000 to 16500
        $this->postJson(route('pricing.product-prices.save-item'), [
            'product_id' => $product->id,
            'work_location_id' => $this->locationA->id,
            'prices' => [
                'ring_retail' => '16500',
            ],
            'unlimited' => true,
            'notes' => 'Kenaikan harga distributor',
        ])->assertOk();

        // Verify PriceHistory records old_price and new_price
        $latestHistory = PriceHistory::where('product_id', $product->id)
            ->where('price_ring', 'Ring Retail')
            ->latest('id')
            ->first();

        $this->assertNotNull($latestHistory);
        $this->assertSame('15000.00', $latestHistory->old_price);
        $this->assertSame('16500.00', $latestHistory->new_price);
        $this->assertSame($this->superAdmin->id, $latestHistory->changed_by);
        $this->assertSame('Kenaikan harga distributor', $latestHistory->reason);

        // Verify Spatie Activity Log entry
        $latestActivity = Activity::where('subject_type', Product::class)
            ->where('subject_id', $product->id)
            ->where('description', 'product_price.updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($latestActivity);
        $this->assertSame($this->superAdmin->id, (int) $latestActivity->causer_id);
        $this->assertSame('15000.00', $latestActivity->properties['old_price']);
        $this->assertSame('16500.00', $latestActivity->properties['new_price']);
        $this->assertSame($this->superAdmin->name, $latestActivity->properties['responsible_user']);
    }

    public function test_save_item_with_custom_validity_period(): void
    {
        $product = $this->createProduct('10000.00');

        $this->actingAs($this->superAdmin);

        $startsAt = now()->addDay()->toDateString();
        $endsAt = now()->addDays(30)->toDateString();

        $this->postJson(route('pricing.product-prices.save-item'), [
            'product_id' => $product->id,
            'work_location_id' => $this->locationA->id,
            'prices' => [
                'ring_retail' => '17000',
            ],
            'unlimited' => false,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ])->assertOk();

        $savedPrice = ProductPrice::where('product_id', $product->id)->where('price_ring', 'Ring Retail')->first();
        $this->assertNotNull($savedPrice);
        $this->assertSame($startsAt, (string) $savedPrice->starts_at?->format('Y-m-d'));
        $this->assertSame($endsAt, (string) $savedPrice->ends_at?->format('Y-m-d'));
    }

    public function test_non_super_admin_with_multiple_work_locations_can_select_permitted_location(): void
    {
        // Attach second location to storeManager
        $this->storeManager->workLocations()->attach($this->locationB->id, [
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->actingAs($this->storeManager);

        // Can view location B
        $response = $this->get(route('pricing.product-prices.index', ['work_location_id' => $this->locationB->id]));
        $response->assertOk();
        $response->assertSee($this->locationB->name);

        // Can save price directly for location B
        $product = $this->createProduct('10000.00');
        $saveResponse = $this->postJson(route('pricing.product-prices.save-item'), [
            'product_id' => $product->id,
            'work_location_id' => $this->locationB->id,
            'prices' => [
                'ring_retail' => '18000',
            ],
            'unlimited' => true,
        ]);
        $saveResponse->assertOk();
        $saveResponse->assertJson(['success' => true]);

        $this->assertDatabaseHas('product_prices', [
            'product_id' => $product->id,
            'work_location_id' => $this->locationB->id,
            'price_ring' => 'Ring Retail',
            'recommended_price' => '18000.00',
        ]);
    }

    public function test_non_super_admin_without_assigned_work_locations_is_forbidden(): void
    {
        $unassignedUser = User::factory()->create(['is_active' => true]);
        $unassignedUser->givePermissionTo(['prices.view', 'prices.update']);

        $this->actingAs($unassignedUser);

        $responseIndex = $this->get(route('pricing.product-prices.index'));
        $responseIndex->assertForbidden();

        $product = $this->createProduct('10000.00');
        $responseSave = $this->postJson(route('pricing.product-prices.save-item'), [
            'product_id' => $product->id,
            'prices' => ['ring_retail' => '15000'],
            'unlimited' => true,
        ]);
        $responseSave->assertForbidden();
    }

    public function test_save_item_validates_ends_at_after_or_equal_starts_at(): void
    {
        $product = $this->createProduct('10000.00');
        $this->actingAs($this->superAdmin);

        $startsAt = now()->addDays(10)->toDateString();
        $endsAt = now()->addDays(2)->toDateString(); // before starts_at!

        $response = $this->postJson(route('pricing.product-prices.save-item'), [
            'product_id' => $product->id,
            'work_location_id' => $this->locationA->id,
            'prices' => ['ring_retail' => '15000'],
            'unlimited' => false,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ends_at']);
    }

    public function test_save_item_rejects_empty_prices(): void
    {
        $product = $this->createProduct('10000.00');
        $this->actingAs($this->superAdmin);

        $response = $this->postJson(route('pricing.product-prices.save-item'), [
            'product_id' => $product->id,
            'work_location_id' => $this->locationA->id,
            'prices' => [
                'ring_retail' => '',
                'ring_b2b' => null,
                'ring_pos' => '',
                'ring_4' => '',
            ],
            'unlimited' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Masukkan minimal satu nominal ring harga untuk disimpan.',
        ]);
    }

    public function test_kepala_toko_has_prices_update_permission_from_rbac(): void
    {
        $kepalaToko = User::factory()->create(['is_active' => true]);
        $kepalaToko->assignRole(Role::findByName('kepala_toko'));
        $kepalaToko->workLocations()->attach($this->locationA->id, ['is_default' => true, 'is_active' => true]);

        $this->assertTrue($kepalaToko->can('prices.update'));

        $product = $this->createProduct('10000.00');

        $this->actingAs($kepalaToko)
            ->postJson(route('pricing.product-prices.save-item'), [
                'product_id' => $product->id,
                'prices' => ['ring_retail' => '22000'],
                'unlimited' => true,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('product_prices', [
            'product_id' => $product->id,
            'work_location_id' => $this->locationA->id,
            'recommended_price' => '22000.00',
        ]);
    }

    public function test_save_item_saves_min_recommended_max_triplet_per_ring(): void
    {
        $product = $this->createProduct('10000.00');

        $this->actingAs($this->superAdmin);

        $response = $this->postJson(route('pricing.product-prices.save-item'), [
            'product_id' => $product->id,
            'work_location_id' => $this->locationA->id,
            'prices' => [
                'ring_retail' => [
                    'min' => '18000',
                    'recommended' => '20000',
                    'max' => '25000',
                ],
                'ring_pos' => [
                    'min' => '19000',
                    'recommended' => '20000',
                    'max' => '22000',
                ],
            ],
            'unlimited' => true,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $retail = ProductPrice::where('product_id', $product->id)->where('price_ring', 'Ring Retail')->firstOrFail();
        $this->assertSame('18000.00', $retail->min_price);
        $this->assertSame('20000.00', $retail->recommended_price);
        $this->assertSame('25000.00', $retail->max_price);

        $pos = ProductPrice::where('product_id', $product->id)->where('price_ring', 'Ring POS')->firstOrFail();
        $this->assertSame('19000.00', $pos->min_price);
        $this->assertSame('20000.00', $pos->recommended_price);
        $this->assertSame('22000.00', $pos->max_price);
    }

    public function test_save_item_auto_fills_min_and_max_when_omitted(): void
    {
        $product = $this->createProduct('10000.00');

        $this->actingAs($this->superAdmin);

        $response = $this->postJson(route('pricing.product-prices.save-item'), [
            'product_id' => $product->id,
            'work_location_id' => $this->locationA->id,
            'prices' => [
                'ring_b2b' => [
                    'min' => '',
                    'recommended' => '17500',
                    'max' => '',
                ],
            ],
            'unlimited' => true,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $b2b = ProductPrice::where('product_id', $product->id)->where('price_ring', 'Ring B2B')->firstOrFail();
        $this->assertSame('17500.00', $b2b->min_price);
        $this->assertSame('17500.00', $b2b->recommended_price);
        $this->assertSame('17500.00', $b2b->max_price);
    }

    public function test_save_item_rejects_min_greater_than_recommended_or_recommended_greater_than_max(): void
    {
        $product = $this->createProduct('10000.00');

        $this->actingAs($this->superAdmin);

        // Min > Recommended
        $response1 = $this->postJson(route('pricing.product-prices.save-item'), [
            'product_id' => $product->id,
            'work_location_id' => $this->locationA->id,
            'prices' => [
                'ring_retail' => [
                    'min' => '25000',
                    'recommended' => '20000',
                    'max' => '30000',
                ],
            ],
        ]);
        $response1->assertStatus(422);
        $response1->assertJson(['success' => false]);
        $this->assertStringContainsString('Harga Minimum', $response1->json('message'));

        // Recommended > Max
        $response2 = $this->postJson(route('pricing.product-prices.save-item'), [
            'product_id' => $product->id,
            'work_location_id' => $this->locationA->id,
            'prices' => [
                'ring_retail' => [
                    'min' => '15000',
                    'recommended' => '25000',
                    'max' => '20000',
                ],
            ],
        ]);
        $response2->assertStatus(422);
        $response2->assertJson(['success' => false]);
        $this->assertStringContainsString('Harga Rekomendasi', $response2->json('message'));
    }

    public function test_save_item_warns_when_min_price_below_hpp_but_still_saves(): void
    {
        $product = $this->createProduct('15000.00'); // HPP = 15.000

        $this->actingAs($this->superAdmin);

        // Set Min to 12.000 (below HPP 15.000)
        $response = $this->postJson(route('pricing.product-prices.save-item'), [
            'product_id' => $product->id,
            'work_location_id' => $this->locationA->id,
            'prices' => [
                'ring_retail' => [
                    'min' => '12000',
                    'recommended' => '18000',
                    'max' => '22000',
                ],
            ],
            'unlimited' => true,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'has_warning' => true,
        ]);
        $this->assertStringContainsString('di bawah HPP', $response->json('message'));

        // Successfully saved
        $retail = ProductPrice::where('product_id', $product->id)->where('price_ring', 'Ring Retail')->firstOrFail();
        $this->assertSame('12000.00', $retail->min_price);
        $this->assertSame('18000.00', $retail->recommended_price);
        $this->assertSame('22000.00', $retail->max_price);
    }

    private function createProduct(string $costPrice = '10000.00'): Product
    {
        return Product::factory()->create([
            'base_unit_id' => Unit::factory()->create(['name' => 'Pcs'])->id,
            'cost_price' => $costPrice,
            'minimum_price' => 0,
            'status' => 'active',
        ]);
    }

    private function createBatch(
        Product $product,
        Stock $stock,
        WorkLocation $location,
        string $batchNo,
        string $quantity,
        string $costPrice,
    ): StockBatch {
        return StockBatch::query()->create([
            'product_id' => $product->id,
            'stock_id' => $stock->id,
            'work_location_id' => $location->id,
            'source_type' => 'goods_receipt',
            'unit_id' => $product->base_unit_id,
            'conversion_factor_snapshot' => '1.000000',
            'batch_no' => $batchNo,
            'received_at' => now()->toDateString(),
            'cost_price' => $costPrice,
            'entry_sale_price_snapshot' => '15000.00',
            'quantity_on_hand' => $quantity,
            'quantity_reserved' => '0.0000',
            'quantity_damaged' => '0.0000',
            'status' => 'active',
        ]);
    }
}
