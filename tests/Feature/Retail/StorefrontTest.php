<?php

namespace Tests\Feature\Retail;

use App\Models\Branch;
use App\Models\Product;
use App\Models\RetailProductPlacement;
use App\Models\Stock;
use App\Models\User;
use App\Models\WorkLocation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_staf_toko_lands_on_assigned_storefront_with_own_stock_and_placement(): void
    {
        [$ownBranch, $ownLocation] = $this->branch('Toko Satu');
        [, $otherLocation] = $this->branch('Toko Dua');
        $staff = $this->staff($ownLocation);
        $visible = $this->stockedProduct($ownLocation, 'Kemeja Biru');
        $this->stockedProduct($ownLocation, 'Celana Tanpa Area');
        $this->stockedProduct($otherLocation, 'Sepatu Toko Lain');
        RetailProductPlacement::query()->create([
            'branch_id' => $ownBranch->id,
            'product_id' => $visible->id,
            'area' => 'Area Pria',
            'rack' => 'Rak A2',
            'shelf' => 'Tingkat 3',
        ]);

        $this->withSession(['url.intended' => route('admin.products.index')])
            ->post(route('login.store'), ['login' => $staff->username, 'password' => 'password'])
            ->assertRedirect(route('retail.storefront.index'));
        $this->get(route('dashboard'))->assertRedirect(route('retail.storefront.index'));
        $this->get(route('retail.storefront.index'))
            ->assertOk()
            ->assertSee('Kemeja Biru')
            ->assertSee('Area Pria')
            ->assertSee('Rak A2')
            ->assertSee('Rp30.000')
            ->assertDontSee('Sepatu Toko Lain')
            ->assertDontSee('Simpan lokasi');
        $this->get(route('retail.storefront.index', ['area' => 'Area Pria']))
            ->assertOk()
            ->assertSee('Kemeja Biru')
            ->assertDontSee('Celana Tanpa Area');
    }

    public function test_staff_cannot_read_or_change_another_store_placement(): void
    {
        [, $ownLocation] = $this->branch('Toko Satu');
        [$otherBranch, $otherLocation] = $this->branch('Toko Dua');
        $staff = $this->staff($ownLocation);
        $otherProduct = $this->stockedProduct($otherLocation, 'Produk Rahasia');

        $this->actingAs($staff)
            ->get(route('retail.storefront.index', ['branch_id' => $otherBranch->id]))
            ->assertForbidden();
        $this->actingAs($staff)
            ->put(route('retail.storefront.placement.update', $otherProduct), [
                'branch_id' => $otherBranch->id,
                'area' => 'Area X',
            ])->assertForbidden();
        $this->assertDatabaseCount('retail_product_placements', 0);
    }

    public function test_head_can_manage_only_assigned_store_placement(): void
    {
        [$ownBranch, $ownLocation] = $this->branch('Toko Satu');
        [$otherBranch, $otherLocation] = $this->branch('Toko Dua');
        $head = User::factory()->create(['is_active' => true]);
        $head->assignRole('kepala_toko');
        $head->workLocations()->attach($ownLocation->id, ['is_default' => true, 'is_active' => true]);
        $product = $this->stockedProduct($ownLocation, 'Jaket Hitam');
        $this->stockedProduct($otherLocation, 'Produk Toko Dua');

        $this->actingAs($head)->put(route('retail.storefront.placement.update', $product), [
            'branch_id' => $ownBranch->id,
            'area' => 'Area Depan',
            'rack' => 'Rak B1',
            'shelf' => 'Tingkat 2',
        ])->assertRedirect(route('retail.storefront.index', ['branch_id' => $ownBranch->id]));
        $this->assertDatabaseHas('retail_product_placements', [
            'branch_id' => $ownBranch->id,
            'product_id' => $product->id,
            'area' => 'Area Depan',
        ]);
        $this->actingAs($head)->get(route('retail.storefront.index'))->assertOk()->assertSee('Area Depan');

        $this->actingAs($head)->put(route('retail.storefront.placement.update', $product), [
            'branch_id' => $otherBranch->id,
            'area' => 'Area Terlarang',
        ])->assertForbidden();
        $this->assertDatabaseMissing('retail_product_placements', ['branch_id' => $otherBranch->id]);
    }

    public function test_staff_without_assigned_branch_sees_setup_message(): void
    {
        $staff = User::factory()->create(['is_active' => true]);
        $staff->assignRole('staf_toko');

        $this->actingAs($staff)->get(route('retail.storefront.index'))
            ->assertOk()
            ->assertSee('Belum ada toko yang ditugaskan');
    }

    /** @return array{Branch, WorkLocation} */
    private function branch(string $name): array
    {
        $location = WorkLocation::factory()->create(['type' => 'branch', 'name' => $name]);
        $branch = Branch::factory()->create(['work_location_id' => $location->id, 'name' => $name]);

        return [$branch, $location];
    }

    private function staff(WorkLocation $location): User
    {
        $staff = User::factory()->create(['is_active' => true, 'password' => 'password']);
        $staff->assignRole('staf_toko');
        $staff->workLocations()->attach($location->id, ['is_default' => true, 'is_active' => true]);

        return $staff;
    }

    private function stockedProduct(WorkLocation $location, string $name): Product
    {
        $product = Product::factory()->create(['name' => $name, 'cost_price' => '25000', 'minimum_price' => '30000']);
        Stock::query()->create([
            'product_id' => $product->id,
            'work_location_id' => $location->id,
            'location_scope_key' => 'work:'.$location->id,
            'quantity_on_hand' => '8',
            'quantity_reserved' => '1',
            'quantity_damaged' => '0',
            'cost_value' => '200000',
        ]);

        return $product;
    }
}
