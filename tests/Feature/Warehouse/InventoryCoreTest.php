<?php

namespace Tests\Feature\Warehouse;

use App\Exceptions\ServiceException;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMutation;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use App\Services\Inventory\InventoryService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryCoreTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $inventory;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->inventory = app(InventoryService::class);
        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole(Role::findOrCreate('super_admin'));
    }

    public function test_receive_issue_reserve_and_release_keep_stock_consistent(): void
    {
        [$product, $workLocation, $bin] = $this->inventoryFixture();

        $this->inventory->receive($product, $workLocation, $bin, '10', $this->admin, ['type' => 'test', 'no' => 'RCV-1'], 'Terima stok');
        $this->inventory->reserve($product, $workLocation, $bin, '3', $this->admin, ['type' => 'test', 'no' => 'RSV-1'], 'Reservasi');
        $this->inventory->releaseReservation($product, $workLocation, $bin, '1', $this->admin, ['type' => 'test', 'no' => 'REL-1'], 'Lepas reservasi');
        $this->inventory->issue($product, $workLocation, $bin, '2', $this->admin, ['type' => 'test', 'no' => 'ISS-1'], 'Keluar stok');

        $stock = Stock::query()->firstOrFail();
        $this->assertSame('8.0000', $stock->quantity_on_hand);
        $this->assertSame('2.0000', $stock->quantity_reserved);
        $this->assertSame('0.0000', $stock->quantity_damaged);
        $this->assertSame('6.0000', $stock->available_quantity);
        $this->assertDatabaseCount('stock_mutations', 4);
    }

    public function test_admin_can_open_all_p09_warehouse_pages(): void
    {
        [$product, $workLocation, $bin] = $this->inventoryFixture();
        $mutation = $this->inventory->receive($product, $workLocation, $bin, '10', $this->admin, ['type' => 'test', 'no' => 'RCV-SMOKE']);

        $this->actingAs($this->admin);

        $this->get(route('warehouse.dashboard'))->assertOk()->assertSee('Dashboard Gudang');
        $this->get(route('warehouse.locations.index'))->assertOk()->assertSee('Zona, Rak, dan Bin');
        $this->get(route('warehouse.locations.create'))->assertOk()->assertSee('Tambah Zona');
        $this->get(route('warehouse.stocks.index'))->assertOk()->assertSee('Saldo Stok');
        $this->get(route('warehouse.stock-card.index'))->assertOk()->assertSee('Kartu Stok');
        $this->get(route('warehouse.stock-mutations.show', $mutation))->assertOk()->assertSee('Detail Mutasi Stok');
        $this->get(route('warehouse.location-transfers.index'))->assertOk()->assertSee('Transfer Antar Lokasi Internal');
        $this->get(route('warehouse.batches.index'))->assertOk()->assertSee('Batch/Lot Stok');
    }

    public function test_location_transfer_uses_sequential_searchable_source_flow(): void
    {
        $this->inventoryFixture();

        $content = $this->actingAs($this->admin)
            ->get(route('warehouse.location-transfers.index'))
            ->assertOk()
            ->getContent();

        $sourceWorkLocationPosition = strpos($content, 'id="source_work_location_id"');
        $sourceWarehouseLocationPosition = strpos($content, 'id="source_warehouse_location_id"');
        $productPosition = strpos($content, 'id="product_id"');

        $this->assertNotFalse($sourceWorkLocationPosition);
        $this->assertNotFalse($sourceWarehouseLocationPosition);
        $this->assertNotFalse($productPosition);
        $this->assertLessThan($sourceWarehouseLocationPosition, $sourceWorkLocationPosition);
        $this->assertLessThan($productPosition, $sourceWarehouseLocationPosition);
        $this->assertGreaterThanOrEqual(5, substr_count($content, 'data-control="select2"'));
        $this->assertStringContainsString('warehouse\\/location-transfers\\/options', $content);
        $this->assertStringContainsString('fetchAllLocations', $content);
        $this->assertStringContainsString('loadWarehouseLocations', $content);
        $this->assertStringContainsString('fetchAllProducts', $content);
        $this->assertStringContainsString('loadProducts', $content);
        $this->assertStringNotContainsString("type === 'locations' ? 0", $content);
        $this->assertStringNotContainsString('transport:', $content);
        $this->assertStringNotContainsString('ajax:', $content);
    }

    public function test_location_transfer_options_filter_bins_and_available_products_by_source(): void
    {
        [$visibleProduct, $visibleWorkLocation, $visibleBin] = $this->inventoryFixture('GDG-VISIBLE', 'PRD-VISIBLE');
        [$hiddenProduct, $hiddenWorkLocation, $hiddenBin] = $this->inventoryFixture('GDG-HIDDEN', 'PRD-HIDDEN');
        $this->inventory->receive($visibleProduct, $visibleWorkLocation, $visibleBin, '10', $this->admin);
        $this->inventory->receive($hiddenProduct, $hiddenWorkLocation, $hiddenBin, '10', $this->admin);

        $this->actingAs($this->admin)
            ->getJson(route('warehouse.location-transfers.options', [
                'type' => 'locations',
                'work_location_id' => $visibleWorkLocation->id,
            ]))
            ->assertOk()
            ->assertJsonFragment(['id' => $visibleBin->id])
            ->assertJsonMissing(['id' => $hiddenBin->id]);

        $this->actingAs($this->admin)
            ->getJson(route('warehouse.location-transfers.options', [
                'type' => 'products',
                'work_location_id' => $visibleWorkLocation->id,
                'warehouse_location_id' => $visibleBin->id,
            ]))
            ->assertOk()
            ->assertJsonFragment(['id' => $visibleProduct->id])
            ->assertJsonMissing(['id' => $hiddenProduct->id]);
    }

    public function test_location_transfer_product_options_exclude_fully_reserved_stock(): void
    {
        [$product, $workLocation, $bin] = $this->inventoryFixture();
        $this->inventory->receive($product, $workLocation, $bin, '5', $this->admin);
        $this->inventory->reserve($product, $workLocation, $bin, '5', $this->admin);

        $this->actingAs($this->admin)
            ->getJson(route('warehouse.location-transfers.options', [
                'type' => 'products',
                'work_location_id' => $workLocation->id,
                'warehouse_location_id' => $bin->id,
            ]))
            ->assertOk()
            ->assertJsonMissing(['id' => $product->id]);
    }

    public function test_location_transfer_form_completes_transfer_and_reconciles_both_stocks(): void
    {
        [$product, $sourceWorkLocation, $sourceBin] = $this->inventoryFixture('GDG-FORM-SRC', 'PRD-FORM');
        [$destinationWorkLocation, $destinationBin] = $this->locationFixture('GDG-FORM-DST');
        $this->inventory->receive($product, $sourceWorkLocation, $sourceBin, '10', $this->admin);

        $this->actingAs($this->admin)
            ->post(route('warehouse.location-transfers.store'), [
                'product_id' => $product->id,
                'source_work_location_id' => $sourceWorkLocation->id,
                'source_warehouse_location_id' => $sourceBin->id,
                'destination_work_location_id' => $destinationWorkLocation->id,
                'destination_warehouse_location_id' => $destinationBin->id,
                'quantity' => 4,
                'reason' => 'Verifikasi alur transfer dari form.',
                'idempotency_key' => 'location-transfer-form-success',
            ])
            ->assertRedirect(route('warehouse.location-transfers.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('notification');

        $this->assertSame('6.0000', Stock::query()
            ->where('product_id', $product->id)
            ->where('work_location_id', $sourceWorkLocation->id)
            ->where('warehouse_location_id', $sourceBin->id)
            ->firstOrFail()
            ->quantity_on_hand);
        $this->assertSame('4.0000', Stock::query()
            ->where('product_id', $product->id)
            ->where('work_location_id', $destinationWorkLocation->id)
            ->where('warehouse_location_id', $destinationBin->id)
            ->firstOrFail()
            ->quantity_on_hand);
        $this->assertDatabaseHas('stock_mutations', [
            'product_id' => $product->id,
            'work_location_id' => $sourceWorkLocation->id,
            'warehouse_location_id' => $sourceBin->id,
            'mutation_type' => 'transfer_out',
            'quantity_on_hand_change' => '-4.0000',
        ]);
        $this->assertDatabaseHas('stock_mutations', [
            'product_id' => $product->id,
            'work_location_id' => $destinationWorkLocation->id,
            'warehouse_location_id' => $destinationBin->id,
            'mutation_type' => 'transfer_in',
            'quantity_on_hand_change' => '4.0000',
        ]);
    }

    public function test_negative_stock_and_over_reservation_are_rejected_without_mutation(): void
    {
        [$product, $workLocation, $bin] = $this->inventoryFixture();
        $this->inventory->receive($product, $workLocation, $bin, '5', $this->admin);

        try {
            $this->inventory->reserve($product, $workLocation, $bin, '6', $this->admin);
            $this->fail('Reservasi di atas stok tersedia seharusnya ditolak.');
        } catch (ServiceException $exception) {
            $this->assertStringContainsString('tidak mencukupi', $exception->getMessage());
        }

        try {
            $this->inventory->issue($product, $workLocation, $bin, '6', $this->admin);
            $this->fail('Issue stok negatif seharusnya ditolak.');
        } catch (ServiceException $exception) {
            $this->assertStringContainsString('tidak mencukupi', $exception->getMessage());
        }

        $stock = Stock::query()->firstOrFail();
        $this->assertSame('5.0000', $stock->quantity_on_hand);
        $this->assertDatabaseCount('stock_mutations', 1);
    }

    public function test_duplicate_idempotency_key_does_not_double_mutate_stock(): void
    {
        [$product, $workLocation, $bin] = $this->inventoryFixture();

        $first = $this->inventory->receive($product, $workLocation, $bin, '10', $this->admin, ['type' => 'retry', 'no' => 'IDEMP-1'], 'Retry', 'idem-receive-1');
        $second = $this->inventory->receive($product, $workLocation, $bin, '10', $this->admin, ['type' => 'retry', 'no' => 'IDEMP-1'], 'Retry', 'idem-receive-1');

        $this->assertTrue($first->is($second));
        $this->assertSame('10.0000', Stock::query()->firstOrFail()->quantity_on_hand);
        $this->assertDatabaseCount('stock_mutations', 1);
        $this->assertDatabaseCount('inventory_idempotency_keys', 1);
    }

    public function test_no_mutation_is_written_when_balance_does_not_change(): void
    {
        [$product, $workLocation, $bin] = $this->inventoryFixture();
        $this->inventory->receive($product, $workLocation, $bin, '10', $this->admin);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('Tidak ada perubahan saldo stok.');

        try {
            $this->inventory->adjust($product, $workLocation, $bin, '10', $this->admin);
        } finally {
            $this->assertDatabaseCount('stock_mutations', 1);
        }
    }

    public function test_internal_transfer_creates_out_and_in_mutations_atomically(): void
    {
        [$product, $sourceWorkLocation, $sourceBin] = $this->inventoryFixture('GDG-SRC');
        [$destinationWorkLocation, $destinationBin] = $this->locationFixture('GDG-DST');
        $this->inventory->receive($product, $sourceWorkLocation, $sourceBin, '10', $this->admin);

        $result = $this->inventory->transferInternal($product, $sourceWorkLocation, $sourceBin, $destinationWorkLocation, $destinationBin, '4', $this->admin, ['type' => 'location_transfer', 'no' => 'TRF-1'], 'Pindah bin', 'idem-transfer-1');

        $this->assertSame('transfer_out', $result['out']->mutation_type->value);
        $this->assertSame('transfer_in', $result['in']->mutation_type->value);
        $this->assertSame('6.0000', Stock::query()->where('work_location_id', $sourceWorkLocation->id)->firstOrFail()->quantity_on_hand);
        $this->assertSame('4.0000', Stock::query()->where('work_location_id', $destinationWorkLocation->id)->firstOrFail()->quantity_on_hand);
        $this->assertDatabaseCount('stock_mutations', 3);
    }

    public function test_location_transfer_rejects_bin_from_different_work_location_as_form_error(): void
    {
        [$product, $sourceWorkLocation, $sourceBin] = $this->inventoryFixture('GDG-SRC');
        [$destinationWorkLocation, $destinationBin] = $this->locationFixture('GDG-DST');

        $this->actingAs($this->admin)
            ->from(route('warehouse.location-transfers.index'))
            ->post(route('warehouse.location-transfers.store'), [
                'product_id' => $product->id,
                'source_work_location_id' => $sourceWorkLocation->id,
                'source_warehouse_location_id' => $destinationBin->id,
                'destination_work_location_id' => $destinationWorkLocation->id,
                'destination_warehouse_location_id' => null,
                'quantity' => 1,
                'reason' => 'Tes bin beda lokasi kerja.',
                'idempotency_key' => 'location-transfer-wrong-bin',
            ])
            ->assertRedirect(route('warehouse.location-transfers.index'))
            ->assertSessionHasErrors('source_warehouse_location_id');

        $this->assertDatabaseCount('stock_mutations', 0);
        $this->assertDatabaseMissing('stocks', [
            'product_id' => $product->id,
            'warehouse_location_id' => $sourceBin->id,
        ]);
    }

    public function test_location_transfer_service_exception_returns_to_form_without_server_error(): void
    {
        [$product, $sourceWorkLocation, $sourceBin] = $this->inventoryFixture('GDG-SRC');
        [$destinationWorkLocation, $destinationBin] = $this->locationFixture('GDG-DST');

        $this->actingAs($this->admin)
            ->from(route('warehouse.location-transfers.index'))
            ->post(route('warehouse.location-transfers.store'), [
                'product_id' => $product->id,
                'source_work_location_id' => $sourceWorkLocation->id,
                'source_warehouse_location_id' => $sourceBin->id,
                'destination_work_location_id' => $destinationWorkLocation->id,
                'destination_warehouse_location_id' => $destinationBin->id,
                'quantity' => 1,
                'reason' => 'Tes stok tidak cukup.',
                'idempotency_key' => 'location-transfer-service-error',
            ])
            ->assertRedirect(route('warehouse.location-transfers.index'))
            ->assertSessionHasErrors('transfer');

        $this->assertDatabaseCount('stock_mutations', 0);
    }

    public function test_stock_card_is_ordered_by_occurrence_and_id(): void
    {
        [$product, $workLocation, $bin] = $this->inventoryFixture();
        $receive = $this->inventory->receive($product, $workLocation, $bin, '10', $this->admin, ['type' => 'test', 'no' => 'A']);
        $issue = $this->inventory->issue($product, $workLocation, $bin, '2', $this->admin, ['type' => 'test', 'no' => 'B']);

        $ordered = StockMutation::query()->orderBy('occurred_at')->orderBy('id')->pluck('id')->all();

        $this->assertSame([$receive->id, $issue->id], $ordered);
    }

    public function test_stock_pages_only_show_permitted_work_location_scope(): void
    {
        $viewer = User::factory()->create(['is_active' => true]);
        $viewer->assignRole(Role::findOrCreate('staff_gudang'));

        [$visibleProduct, $visibleWorkLocation, $visibleBin] = $this->inventoryFixture('GDG-A', 'PRD-A');
        [$hiddenProduct, $hiddenWorkLocation, $hiddenBin] = $this->inventoryFixture('GDG-B', 'PRD-B');
        $viewer->workLocations()->syncWithoutDetaching([$visibleWorkLocation->id => ['is_default' => true, 'is_active' => true]]);

        $this->inventory->receive($visibleProduct, $visibleWorkLocation, $visibleBin, '10', $this->admin);
        $this->inventory->receive($hiddenProduct, $hiddenWorkLocation, $hiddenBin, '10', $this->admin);

        $this->actingAs($viewer)
            ->get(route('warehouse.stocks.index'))
            ->assertOk()
            ->assertSee('PRD-A')
            ->assertDontSee('PRD-B');
    }

    /** @return array{Product, WorkLocation, WarehouseLocation} */
    private function inventoryFixture(string $warehouseCode = 'GDG-TEST', string $sku = 'PRD-TEST'): array
    {
        [$workLocation, $bin] = $this->locationFixture($warehouseCode);
        $product = Product::factory()->create(['sku' => $sku]);

        return [$product, $workLocation, $bin];
    }

    /** @return array{WorkLocation, WarehouseLocation} */
    private function locationFixture(string $warehouseCode): array
    {
        $workLocation = WorkLocation::factory()->create(['type' => 'warehouse', 'code' => $warehouseCode]);
        $warehouse = Warehouse::factory()->create(['code' => $warehouseCode, 'work_location_id' => $workLocation->id]);
        $bin = WarehouseLocation::factory()->create([
            'warehouse_id' => $warehouse->id,
            'full_code' => $warehouseCode.'-BIN-01',
            'code' => 'BIN-01',
        ]);

        return [$workLocation, $bin];
    }
}
