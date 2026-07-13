<?php

namespace Tests\Feature\Returns;

use App\Enums\InventoryLossStatus;
use App\Enums\ReturnResolution;
use App\Enums\ReturnStatus;
use App\Exceptions\ServiceException;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMutation;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use App\Services\Inventory\InventoryService;
use App\Services\Returns\ReturnService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReturnAndLossWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $inventory;

    private ReturnService $returns;

    private User $head;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->inventory = app(InventoryService::class);
        $this->returns = app(ReturnService::class);
        $this->head = User::factory()->create(['is_active' => true]);
        $this->head->assignRole(Role::findOrCreate('kepala_gudang'));
        $this->staff = User::factory()->create(['is_active' => true]);
        $this->staff->assignRole(Role::findOrCreate('staff_gudang'));
    }

    public function test_p14_pages_can_be_opened(): void
    {
        [$product, $workLocation, $bin] = $this->fixture('RET-PAGE');
        $this->assignScope($workLocation);
        $return = $this->returns->create($this->returnPayload($workLocation, $bin, $product, '2'), $this->staff);

        $this->actingAs($this->head);
        $this->get(route('returns.index'))->assertOk()->assertSee('Daftar Retur');
        $this->get(route('returns.create'))->assertOk()->assertSee('Form Pengajuan Retur');
        $this->get(route('returns.show', $return))->assertOk()->assertSee($return->number);
        $this->get(route('returns.inspection', $return))->assertOk()->assertSee('QC '.$return->number);
        $this->get(route('warehouse.losses.index'))->assertOk()->assertSee('Barang Rusak');
        $this->get(route('reports.losses.index'))->assertOk()->assertSee('Laporan Loss Tracking');
    }

    public function test_return_good_and_damaged_routes_stock_once(): void
    {
        [$product, $workLocation, $bin] = $this->fixture('RET-DMG');
        $this->assignScope($workLocation);
        $return = $this->returns->create($this->returnPayload($workLocation, $bin, $product, '5'), $this->staff);

        $inspected = $this->returns->inspect($return, ['items' => [
            $return->items->firstOrFail()->id => ['warehouse_location_id' => $bin->id, 'quantity_good' => '3', 'quantity_damaged' => '2', 'quantity_rejected' => '0', 'condition' => 'damaged'],
        ]], $this->head);

        $stock = Stock::query()->where('product_id', $product->id)->firstOrFail();
        $this->assertSame(ReturnStatus::INSPECTED, $inspected->status);
        $this->assertSame('5.0000', $stock->quantity_on_hand);
        $this->assertSame('2.0000', $stock->quantity_damaged);
        $this->assertDatabaseCount('stock_mutations', 3);

        $this->returns->settle($inspected, ['resolution' => ReturnResolution::CREDIT_NOTE->value, 'document_no' => 'CN-1'], $this->head);
        $this->assertSame(ReturnStatus::SETTLED, $return->fresh()->status);
    }

    public function test_duplicate_source_quantity_is_rejected(): void
    {
        [$product, $workLocation, $bin] = $this->fixture('RET-DUP');
        $this->assignScope($workLocation);
        $payload = $this->returnPayload($workLocation, $bin, $product, '3') + [];
        $payload['items'][0]['source_item_type'] = 'sale_item';
        $payload['items'][0]['source_item_id'] = 99;
        $payload['items'][0]['source_quantity'] = '5';
        $this->returns->create($payload, $this->staff);

        $payload['items'][0]['quantity_requested'] = '3';

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('melebihi qty dokumen asal');
        $this->returns->create($payload, $this->staff);
    }

    public function test_return_to_supplier_settlement_reduces_stock_after_qc(): void
    {
        [$product, $workLocation, $bin] = $this->fixture('RET-SUP');
        $this->assignScope($workLocation);
        $return = $this->returns->create($this->returnPayload($workLocation, $bin, $product, '4'), $this->staff);
        $inspected = $this->returns->inspect($return, ['items' => [
            $return->items->firstOrFail()->id => ['warehouse_location_id' => $bin->id, 'quantity_good' => '4', 'quantity_damaged' => '0', 'quantity_rejected' => '0', 'condition' => 'good'],
        ]], $this->head);

        $settled = $this->returns->settle($inspected, ['resolution' => ReturnResolution::RETURN_TO_SUPPLIER->value, 'document_no' => 'CLAIM-1'], $this->head);

        $this->assertSame(ReturnStatus::SETTLED, $settled->status);
        $this->assertSame('0.0000', Stock::query()->where('product_id', $product->id)->firstOrFail()->quantity_on_hand);
        $this->assertSame(2, StockMutation::query()->where('reference_type', 'return')->where('reference_id', $return->id)->count());
    }

    public function test_inventory_loss_damage_issue_and_large_approval(): void
    {
        [$product, $workLocation, $bin] = $this->fixture('LOSS-1', '2000000.00');
        $this->assignScope($workLocation);
        $this->inventory->receive($product, $workLocation, $bin, '5', $this->head);

        $small = $this->returns->createLoss([
            'work_location_id' => $workLocation->id,
            'warehouse_location_id' => $bin->id,
            'product_id' => $product->id,
            'loss_type' => 'broken',
            'disposition' => 'damage',
            'quantity' => '0.1',
            'unit_cost_snapshot' => '1000',
        ], $this->staff);
        $this->assertSame(InventoryLossStatus::APPROVED, $small->status);
        $this->assertSame('0.1000', Stock::query()->where('product_id', $product->id)->firstOrFail()->quantity_damaged);

        $large = $this->returns->createLoss([
            'work_location_id' => $workLocation->id,
            'warehouse_location_id' => $bin->id,
            'product_id' => $product->id,
            'loss_type' => 'lost',
            'disposition' => 'issue',
            'quantity' => '1',
            'unit_cost_snapshot' => '2000000',
        ], $this->staff);
        $this->assertSame(InventoryLossStatus::PENDING_APPROVAL, $large->status);

        $approved = $this->returns->approveLoss($large, $this->head);
        $this->assertSame(InventoryLossStatus::APPROVED, $approved->status);
        $this->assertSame('4.0000', Stock::query()->where('product_id', $product->id)->firstOrFail()->quantity_on_hand);
    }

    public function test_cross_location_return_is_forbidden(): void
    {
        [$product, $workLocation, $bin] = $this->fixture('RET-SCOPE');
        $return = $this->returns->create($this->returnPayload($workLocation, $bin, $product, '1'), $this->head);
        $outsider = User::factory()->create(['is_active' => true]);
        $outsider->assignRole(Role::findOrCreate('staff_gudang'));

        $this->actingAs($outsider)->get(route('returns.show', $return))->assertForbidden();
    }

    /** @return array{Product, WorkLocation, WarehouseLocation} */
    private function fixture(string $sku, string $cost = '10000.00'): array
    {
        $workLocation = WorkLocation::factory()->create(['type' => 'warehouse', 'code' => 'GDG-'.$sku]);
        $warehouse = Warehouse::factory()->create(['work_location_id' => $workLocation->id, 'code' => 'WH-'.$sku]);
        $bin = WarehouseLocation::factory()->create(['warehouse_id' => $warehouse->id, 'code' => 'BIN', 'full_code' => 'BIN-'.$sku]);
        $product = Product::factory()->create(['sku' => $sku, 'cost_price' => $cost]);

        return [$product, $workLocation, $bin];
    }

    /** @return array<string, mixed> */
    private function returnPayload(WorkLocation $workLocation, WarehouseLocation $bin, Product $product, string $qty): array
    {
        return [
            'work_location_id' => $workLocation->id,
            'source_type' => 'branch',
            'source_name' => 'Toko Test',
            'reference_type' => 'manual',
            'reference_no' => 'REF-1',
            'reason' => 'broken',
            'requested_resolution' => ReturnResolution::CREDIT_NOTE->value,
            'return_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $product->id,
                'warehouse_location_id' => $bin->id,
                'quantity_requested' => $qty,
                'source_quantity' => $qty,
                'unit_cost_snapshot' => $product->cost_price,
                'condition' => 'good',
            ]],
        ];
    }

    private function assignScope(WorkLocation $workLocation): void
    {
        foreach ([$this->head, $this->staff] as $user) {
            $user->workLocations()->syncWithoutDetaching([$workLocation->id => ['is_default' => true, 'is_active' => true]]);
        }
    }
}
