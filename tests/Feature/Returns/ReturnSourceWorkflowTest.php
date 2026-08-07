<?php

namespace Tests\Feature\Returns;

use App\Enums\B2bOrderStatus;
use App\Enums\CashShiftStatus;
use App\Enums\GoodsReceiptStatus;
use App\Enums\PosSaleStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\ReturnResolution;
use App\Enums\ReturnStatus;
use App\Enums\ShipmentStatus;
use App\Enums\StockTransferStatus;
use App\Models\B2bOrder;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\GoodsReceipt;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\ReturnDocument;
use App\Models\Shipment;
use App\Models\StockMutation;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReturnSourceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $head;

    private WorkLocation $workLocation;

    private WarehouseLocation $bin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->staff = User::factory()->create(['is_active' => true]);
        $this->staff->assignRole(Role::findOrCreate('staff_gudang'));
        $this->head = User::factory()->create(['is_active' => true]);
        $this->head->assignRole(Role::findOrCreate('kepala_gudang'));
        $this->workLocation = WorkLocation::factory()->create(['type' => 'warehouse', 'code' => 'RET-SRC']);
        $warehouse = Warehouse::factory()->create(['work_location_id' => $this->workLocation->id, 'code' => 'WH-RET-SRC']);
        $this->bin = WarehouseLocation::factory()->create([
            'warehouse_id' => $warehouse->id,
            'code' => 'BIN-A',
            'full_code' => 'WH-RET-SRC/BIN-A',
            'is_active' => true,
        ]);
        $this->product = Product::factory()->create(['sku' => 'RET-POS-001', 'cost_price' => '12500.00']);

        foreach ([$this->staff, $this->head] as $user) {
            $user->workLocations()->attach($this->workLocation->id, ['is_default' => true, 'is_active' => true]);
        }
    }

    public function test_source_endpoints_apply_scope_return_select2_json_and_hide_cost(): void
    {
        [$sale, $saleItem] = $this->createPosSale('POS-RET-001');
        $inactiveBin = WarehouseLocation::factory()->create([
            'warehouse_id' => $this->bin->warehouse_id,
            'code' => 'OFF',
            'full_code' => 'WH-RET-SRC/OFF',
            'is_active' => false,
        ]);
        $foreignLocation = WorkLocation::factory()->create(['type' => 'warehouse']);
        $foreignWarehouse = Warehouse::factory()->create(['work_location_id' => $foreignLocation->id]);
        $foreignBin = WarehouseLocation::factory()->create(['warehouse_id' => $foreignWarehouse->id, 'is_active' => true]);

        $documents = $this->actingAs($this->staff)->getJson(route('returns.source-documents', [
            'source_type' => 'pos',
            'work_location_id' => $this->workLocation->id,
            'q' => 'POS-RET',
        ]));
        $documents->assertOk()
            ->assertJsonPath('results.0.id', $sale->id)
            ->assertJsonPath('results.0.number', 'POS-RET-001')
            ->assertJsonStructure(['results', 'pagination' => ['more']]);

        $this->getJson(route('returns.source-documents', [
            'source_type' => 'pos',
            'work_location_id' => $this->workLocation->id,
            'q' => $sale->customer?->business_name,
        ]))->assertOk()->assertJsonPath('results.0.id', $sale->id);

        $items = $this->getJson(route('returns.source-items', [
            'source_type' => 'pos',
            'work_location_id' => $this->workLocation->id,
            'reference_id' => $sale->id,
        ]));
        $items->assertOk()
            ->assertJsonPath('results.0.source_item_id', $saleItem->id)
            ->assertJsonPath('results.0.source_quantity', '10.0000')
            ->assertJsonPath('results.0.already_returned', '2.0000')
            ->assertJsonPath('results.0.maximum_quantity', '8.0000')
            ->assertJsonPath('results.0.unit_cost', null)
            ->assertJsonMissingPath('results.0.unit_cost_internal');

        $this->getJson(route('returns.source-items', [
            'source_type' => 'pos',
            'work_location_id' => $this->workLocation->id,
            'reference_id' => 999999,
        ]))->assertUnprocessable()->assertJsonPath('errors.source.0', 'Dokumen asal tidak ditemukan, belum selesai, atau berada di luar akses lokasi kerja Anda.');

        $this->actingAs($this->head)->getJson(route('returns.source-items', [
            'source_type' => 'pos',
            'work_location_id' => $this->workLocation->id,
            'reference_id' => $sale->id,
        ]))->assertOk()->assertJsonPath('results.0.unit_cost', '12500.00');

        $locations = $this->getJson(route('returns.locations', ['work_location_id' => $this->workLocation->id]));
        $locations->assertOk()->assertJsonFragment(['id' => $this->bin->id]);
        $locations->assertJsonMissing(['id' => $inactiveBin->id])->assertJsonMissing(['id' => $foreignBin->id]);

        $outsider = User::factory()->create(['is_active' => true]);
        $outsider->assignRole(Role::findOrCreate('staff_gudang'));
        $this->actingAs($outsider)->getJson(route('returns.source-documents', [
            'source_type' => 'pos',
            'work_location_id' => $this->workLocation->id,
        ]))->assertForbidden();
    }

    public function test_store_uses_trusted_source_snapshots_saves_evidence_and_does_not_mutate_stock(): void
    {
        Storage::fake('public');
        [$sale, $saleItem] = $this->createPosSale('POS-RET-SECURE');
        $spoofedProduct = Product::factory()->create(['cost_price' => '999999.00']);

        $response = $this->actingAs($this->staff)->post(route('returns.store'), $this->payload($sale, $saleItem, [
            'action' => 'submit',
            'items' => [[
                'product_id' => $spoofedProduct->id,
                'source_item_id' => $saleItem->id,
                'warehouse_location_id' => $this->bin->id,
                'quantity_requested' => '3',
                'source_quantity' => '999',
                'unit_cost_snapshot' => '1',
                'condition' => 'good',
            ]],
            'evidence_files' => [UploadedFile::fake()->image('bukti-retur.jpg')],
        ]));

        $return = ReturnDocument::query()->with(['items', 'attachments'])->sole();
        $response->assertRedirect(route('returns.show', $return));
        $this->assertSame(ReturnStatus::SUBMITTED, $return->status);
        $this->assertSame('pos_sale', $return->reference_type);
        $this->assertSame($sale->id, $return->reference_id);
        $this->assertSame($this->product->id, $return->items->sole()->product_id);
        $this->assertSame('10.0000', $return->items->sole()->source_quantity);
        $this->assertSame('12500.00', $return->items->sole()->unit_cost_snapshot);
        $this->assertCount(1, $return->attachments);
        Storage::disk('public')->assertExists($return->attachments->sole()->path);
        $this->assertSame(0, StockMutation::query()->where('reference_type', 'return')->count());
    }

    public function test_b2b_supplier_and_transfer_sources_use_their_received_quantities(): void
    {
        $customer = Customer::factory()->create();
        $b2b = B2bOrder::query()->create([
            'number' => 'B2B-RET-001',
            'customer_id' => $customer->id,
            'requested_by' => $this->staff->id,
            'status' => B2bOrderStatus::RECEIVED,
            'grand_total_amount' => '90000',
        ]);
        $b2bItem = $b2b->items()->create([
            'product_id' => $this->product->id,
            'unit_id' => $this->product->base_unit_id,
            'sku_snapshot' => $this->product->sku,
            'product_name_snapshot' => $this->product->name,
            'unit_name_snapshot' => $this->product->baseUnit?->name ?: 'Unit',
            'conversion_factor_snapshot' => '1',
            'quantity' => '8',
            'approved_quantity' => '8',
            'base_quantity' => '8',
            'issued_quantity' => '6',
            'selected_price' => '15000',
            'line_total' => '120000',
        ]);
        Shipment::query()->create([
            'number' => 'SHP-RET-001',
            'b2b_order_id' => $b2b->id,
            'customer_id' => $customer->id,
            'origin_work_location_id' => $this->workLocation->id,
            'status' => ShipmentStatus::DELIVERED,
            'delivery_method' => 'courier',
            'created_by' => $this->staff->id,
        ]);

        $supplier = Supplier::factory()->create();
        $purchaseOrder = PurchaseOrder::query()->create([
            'number' => 'PO-RET-001',
            'warehouse_id' => $this->bin->warehouse_id,
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'status' => PurchaseOrderStatus::COMPLETED,
            'created_by' => $this->staff->id,
            'grand_total' => '100000',
        ]);
        $purchaseOrderItem = $purchaseOrder->items()->create([
            'product_id' => $this->product->id,
            'unit_id' => $this->product->base_unit_id,
            'product_sku_snapshot' => $this->product->sku,
            'product_name_snapshot' => $this->product->name,
            'unit_name_snapshot' => $this->product->baseUnit?->name ?: 'Unit',
            'conversion_factor_snapshot' => '2',
            'quantity_ordered' => '5',
            'quantity_received' => '5',
            'unit_price' => '20000',
            'subtotal' => '100000',
        ]);
        $receipt = GoodsReceipt::query()->create([
            'number' => 'GR-RET-001',
            'purchase_order_id' => $purchaseOrder->id,
            'warehouse_id' => $this->bin->warehouse_id,
            'supplier_id' => $supplier->id,
            'received_at' => now()->toDateString(),
            'received_by' => $this->staff->id,
            'status' => GoodsReceiptStatus::POSTED,
            'posted_at' => now(),
            'posted_by' => $this->staff->id,
        ]);
        $receiptItem = $receipt->items()->create([
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'product_id' => $this->product->id,
            'unit_id' => $this->product->base_unit_id,
            'warehouse_location_id' => $this->bin->id,
            'product_sku_snapshot' => $this->product->sku,
            'product_name_snapshot' => $this->product->name,
            'unit_name_snapshot' => $this->product->baseUnit?->name ?: 'Unit',
            'conversion_factor_snapshot' => '2',
            'quantity_ordered' => '5',
            'quantity_received' => '5',
            'quantity_accepted' => '4',
            'quantity_damaged' => '1',
            'quantity_returned_to_supplier' => '1',
            'incoming_cost' => '100000',
        ]);

        $origin = WorkLocation::factory()->create(['type' => 'warehouse']);
        $originWarehouse = Warehouse::factory()->create(['work_location_id' => $origin->id]);
        $originBin = WarehouseLocation::factory()->create(['warehouse_id' => $originWarehouse->id]);
        $transfer = StockTransfer::query()->create([
            'number' => 'TRF-RET-001',
            'source_work_location_id' => $origin->id,
            'source_warehouse_location_id' => $originBin->id,
            'destination_work_location_id' => $this->workLocation->id,
            'destination_warehouse_location_id' => $this->bin->id,
            'requested_by' => $this->staff->id,
            'status' => StockTransferStatus::FULLY_RECEIVED,
            'transfer_date' => now()->toDateString(),
        ]);
        $transferItem = $transfer->items()->create([
            'product_id' => $this->product->id,
            'unit_id' => $this->product->base_unit_id,
            'source_warehouse_location_id' => $originBin->id,
            'destination_warehouse_location_id' => $this->bin->id,
            'product_sku_snapshot' => $this->product->sku,
            'product_name_snapshot' => $this->product->name,
            'unit_name_snapshot' => $this->product->baseUnit?->name ?: 'Unit',
            'quantity_requested' => '9',
            'quantity_received' => '7',
            'quantity_damaged' => '1',
        ]);

        $this->actingAs($this->head);
        $this->getJson(route('returns.source-documents', ['source_type' => 'b2b', 'work_location_id' => $this->workLocation->id]))
            ->assertOk()->assertJsonFragment(['id' => $b2b->id, 'number' => 'B2B-RET-001']);
        $this->getJson(route('returns.source-items', ['source_type' => 'b2b', 'work_location_id' => $this->workLocation->id, 'reference_id' => $b2b->id]))
            ->assertOk()->assertJsonPath('results.0.source_item_id', $b2bItem->id)->assertJsonPath('results.0.maximum_quantity', '6.0000');

        $this->getJson(route('returns.source-documents', ['source_type' => 'supplier', 'work_location_id' => $this->workLocation->id]))
            ->assertOk()->assertJsonFragment(['id' => $receipt->id, 'number' => 'GR-RET-001']);
        $this->getJson(route('returns.source-items', ['source_type' => 'supplier', 'work_location_id' => $this->workLocation->id, 'reference_id' => $receipt->id]))
            ->assertOk()->assertJsonPath('results.0.source_item_id', $receiptItem->id)
            ->assertJsonPath('results.0.source_quantity', '10.0000')->assertJsonPath('results.0.maximum_quantity', '8.0000');

        $this->getJson(route('returns.source-documents', ['source_type' => 'transfer', 'work_location_id' => $this->workLocation->id]))
            ->assertOk()->assertJsonFragment(['id' => $transfer->id, 'number' => 'TRF-RET-001']);
        $this->getJson(route('returns.source-items', ['source_type' => 'transfer', 'work_location_id' => $this->workLocation->id, 'reference_id' => $transfer->id]))
            ->assertOk()->assertJsonPath('results.0.source_item_id', $transferItem->id)->assertJsonPath('results.0.maximum_quantity', '8.0000');
    }

    public function test_draft_can_be_updated_and_submit_still_does_not_mutate_stock(): void
    {
        [$sale, $saleItem] = $this->createPosSale('POS-RET-DRAFT');
        $this->actingAs($this->staff)->post(route('returns.store'), $this->payload($sale, $saleItem, [
            'action' => 'draft',
        ]))->assertRedirect();
        $return = ReturnDocument::query()->sole();

        $this->actingAs($this->staff)->get(route('returns.edit', $return))->assertOk()->assertSee('Ubah '.$return->number);
        $this->put(route('returns.update', $return), $this->payload($sale, $saleItem, [
            'action' => 'submit',
            'items' => [[
                'product_id' => $this->product->id,
                'source_item_id' => $saleItem->id,
                'warehouse_location_id' => $this->bin->id,
                'quantity_requested' => '4',
                'condition' => 'damaged',
            ]],
        ]))->assertRedirect(route('returns.show', $return));

        $return->refresh()->load('items');
        $this->assertSame(ReturnStatus::SUBMITTED, $return->status);
        $this->assertSame('4.0000', $return->items->sole()->quantity_requested);
        $this->assertSame(0, StockMutation::query()->where('reference_type', 'return')->count());
        $this->get(route('returns.edit', $return))->assertForbidden();
    }

    public function test_item_from_other_document_and_duplicate_source_item_are_rejected(): void
    {
        [$sale, $saleItem] = $this->createPosSale('POS-RET-VALID');
        [, $otherItem] = $this->createPosSale('POS-RET-OTHER');

        $this->actingAs($this->staff)->from(route('returns.create'))->post(route('returns.store'), $this->payload($sale, $otherItem))
            ->assertRedirect(route('returns.create'))->assertSessionHasErrors('return');
        $this->assertDatabaseCount('returns', 0);

        $item = [
            'product_id' => $this->product->id,
            'source_item_id' => $saleItem->id,
            'warehouse_location_id' => $this->bin->id,
            'quantity_requested' => '1',
            'condition' => 'good',
        ];
        $this->from(route('returns.create'))->post(route('returns.store'), $this->payload($sale, $saleItem, ['items' => [$item, $item]]))
            ->assertRedirect(route('returns.create'))->assertSessionHasErrors('return');
        $this->assertDatabaseCount('returns', 0);
    }

    /** @return array{PosSale, PosSaleItem} */
    private function createPosSale(string $number): array
    {
        $branch = Branch::factory()->create();
        $customer = Customer::factory()->create(['business_name' => 'Pelanggan '.$number]);
        $shift = CashShift::query()->create([
            'number' => 'SHIFT-'.$number,
            'branch_id' => $branch->id,
            'work_location_id' => $this->workLocation->id,
            'cashier_user_id' => $this->staff->id,
            'status' => CashShiftStatus::CLOSED,
        ]);
        $sale = PosSale::query()->create([
            'number' => $number,
            'branch_id' => $branch->id,
            'work_location_id' => $this->workLocation->id,
            'cash_shift_id' => $shift->id,
            'cashier_user_id' => $this->staff->id,
            'customer_id' => $customer->id,
            'status' => PosSaleStatus::COMPLETED,
            'grand_total_amount' => '125000.00',
            'completed_at' => now(),
        ]);
        $item = $sale->items()->create([
            'product_id' => $this->product->id,
            'unit_id' => $this->product->base_unit_id,
            'warehouse_location_id' => $this->bin->id,
            'sku_snapshot' => $this->product->sku,
            'product_name_snapshot' => $this->product->name,
            'unit_name_snapshot' => $this->product->baseUnit?->name ?: 'Unit',
            'conversion_factor_snapshot' => '1',
            'quantity' => '10',
            'base_quantity' => '10',
            'hpp_snapshot' => '12500',
            'selected_price' => '15000',
            'line_total' => '150000',
            'returned_quantity' => '2',
        ]);

        return [$sale, $item];
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(PosSale $sale, PosSaleItem $item, array $overrides = []): array
    {
        return array_replace_recursive([
            'work_location_id' => $this->workLocation->id,
            'source_type' => 'pos',
            'reference_id' => $sale->id,
            'reason' => 'broken',
            'requested_resolution' => ReturnResolution::CREDIT_NOTE->value,
            'return_date' => now()->toDateString(),
            'action' => 'draft',
            'idempotency_key' => fake()->uuid(),
            'items' => [[
                'product_id' => $this->product->id,
                'source_item_id' => $item->id,
                'warehouse_location_id' => $this->bin->id,
                'quantity_requested' => '2',
                'condition' => 'good',
            ]],
        ], $overrides);
    }
}
