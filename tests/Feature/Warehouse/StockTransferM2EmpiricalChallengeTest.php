<?php

namespace Tests\Feature\Warehouse;

use App\Enums\StockMutationType;
use App\Enums\StockTransferStatus;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Stock;
use App\Models\StockMutation;
use App\Models\StockTransfer;
use App\Models\StockTransferReceipt;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use App\Services\Inventory\InventoryService;
use App\Services\Warehouse\StockTransferService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StockTransferM2EmpiricalChallengeTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $inventory;

    private StockTransferService $transfers;

    private User $warehouseHead;

    private User $warehouseStaff;

    private User $storeHead;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->inventory = app(InventoryService::class);
        $this->transfers = app(StockTransferService::class);

        $this->warehouseHead = User::factory()->create(['is_active' => true]);
        $this->warehouseHead->assignRole(Role::findOrCreate('kepala_gudang'));
        $this->warehouseStaff = User::factory()->create(['is_active' => true]);
        $this->warehouseStaff->assignRole(Role::findOrCreate('staff_gudang'));
        $this->storeHead = User::factory()->create(['is_active' => true]);
        $this->storeHead->assignRole(Role::findOrCreate('kepala_toko'));
    }

    /**
     * Requirement 1: 1-click receive: quantity_received = inTransit, damaged = 0, discrepancy = 0
     * completes transfer and properly credits destination inventory without errors.
     */
    public function test_1click_receive_full_shipment_single_item_completes_transfer_and_credits_inventory(): void
    {
        [$warehouse, $branch, $product, $sourceBin, $destinationBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);
        $this->inventory->receive($product, $warehouse->workLocation, $sourceBin, '10', $this->warehouseHead);

        $transfer = $this->makeApprovedTransfer($warehouse, $branch, $product, $sourceBin, $destinationBin, '5');
        $item = $transfer->items->firstOrFail();
        $this->transfers->pack($transfer, ['items' => [$item->id => ['quantity_picked' => '5']]], $this->warehouseStaff);
        $transfer = $this->transfers->ship($transfer, ['carrier' => 'Kurir Internal'], $this->warehouseStaff);

        $this->assertSame(StockTransferStatus::SHIPPED, $transfer->fresh()->status);
        $inTransitQty = $item->fresh()->inTransitQuantity();
        $this->assertSame('5.0000', $inTransitQty);

        $payload = [
            'idempotency_key' => 'm2-1click-single-test',
            'items' => [
                $item->id => [
                    'quantity_received' => $inTransitQty,
                    'quantity_damaged' => '0',
                    'quantity_discrepancy' => '0',
                    'notes' => '1-klik terima semua sempurna',
                ],
            ],
        ];

        $response = $this->actingAs($this->storeHead)
            ->post(route('retail.stock-transfers.receive', $transfer), $payload);

        $response->assertRedirect(route('retail.stock-transfers.receiving'))
            ->assertSessionHas('notification.type', 'success');

        $freshTransfer = $transfer->fresh();
        $this->assertSame(StockTransferStatus::FULLY_RECEIVED, $freshTransfer->status);
        $this->assertSame('0.0000', $item->fresh()->inTransitQuantity());

        // Completing the fully received transfer
        $completed = $this->transfers->complete($freshTransfer, $this->storeHead);
        $this->assertSame(StockTransferStatus::COMPLETED, $completed->status);

        // Destination stock verification
        $destinationStock = Stock::query()
            ->where('product_id', $product->id)
            ->where('work_location_id', $branch->work_location_id)
            ->firstOrFail();

        $this->assertSame('5.0000', $destinationStock->quantity_on_hand);
        $this->assertSame('0.0000', $destinationStock->quantity_damaged);
        $this->assertSame('0.0000', $destinationStock->quantity_reserved);
        $this->assertSame('5.0000', $destinationStock->available_quantity);

        // Check stock mutation
        $mutation = StockMutation::query()
            ->where('product_id', $product->id)
            ->where('work_location_id', $branch->work_location_id)
            ->where('mutation_type', StockMutationType::TRANSFER_IN)
            ->firstOrFail();
        $this->assertSame('5.0000', $mutation->quantity_on_hand_change);
    }

    /**
     * Requirement 1 Edge Case: Multi-item transfer with unit conversions using 1-click receive
     */
    public function test_1click_receive_multi_item_transfer_with_unit_conversion(): void
    {
        [$warehouse, $branch, $product1, $sourceBin, $destinationBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);

        // Second product with unit factor 12 (Dus)
        $dusUnit = Unit::factory()->create(['code' => 'DUS']);
        $product2 = Product::factory()->create(['base_unit_id' => $product1->base_unit_id, 'status' => 'active', 'sku' => 'PRD-DUS']);
        ProductUnit::query()->create([
            'product_id' => $product2->id,
            'unit_id' => $dusUnit->id,
            'name' => 'Dus 12',
            'conversion_factor' => '12.000000',
            'is_base' => false,
            'is_sellable' => true,
            'is_active' => true,
        ]);

        $this->inventory->receive($product1, $warehouse->workLocation, $sourceBin, '20', $this->warehouseHead);
        $this->inventory->receive($product2, $warehouse->workLocation, $sourceBin, '36', $this->warehouseHead);

        $transfer = $this->transfers->create([
            'source_work_location_id' => $warehouse->work_location_id,
            'source_warehouse_location_id' => $sourceBin->id,
            'destination_work_location_id' => $branch->work_location_id,
            'destination_warehouse_location_id' => $destinationBin->id,
            'transfer_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $product1->id,
                    'quantity_requested' => '5',
                    'quantity_approved' => '5',
                    'source_warehouse_location_id' => $sourceBin->id,
                    'destination_warehouse_location_id' => $destinationBin->id,
                ],
                [
                    'product_id' => $product2->id,
                    'unit_id' => $dusUnit->id, // 2 Dus = 24 PCS
                    'quantity_requested' => '2',
                    'quantity_approved' => '2',
                    'source_warehouse_location_id' => $sourceBin->id,
                    'destination_warehouse_location_id' => $destinationBin->id,
                ],
            ],
            'action' => 'submit',
        ], $this->warehouseStaff);

        $transfer = $this->transfers->approve($transfer, $this->warehouseHead);

        $item1 = $transfer->items->where('product_id', $product1->id)->firstOrFail();
        $item2 = $transfer->items->where('product_id', $product2->id)->firstOrFail();

        $this->transfers->pack($transfer, [
            'items' => [
                $item1->id => ['quantity_picked' => '5'],
                $item2->id => ['quantity_picked' => '2'],
            ],
        ], $this->warehouseStaff);

        $transfer = $this->transfers->ship($transfer, ['carrier' => 'Kurir Internal'], $this->warehouseStaff);

        // 1-Click receive both items
        $payload = [
            'idempotency_key' => 'm2-1click-multi-test',
            'items' => [
                $item1->id => [
                    'quantity_received' => $item1->fresh()->inTransitQuantity(),
                    'quantity_damaged' => '0',
                    'quantity_discrepancy' => '0',
                ],
                $item2->id => [
                    'quantity_received' => $item2->fresh()->inTransitQuantity(),
                    'quantity_damaged' => '0',
                    'quantity_discrepancy' => '0',
                ],
            ],
        ];

        $this->actingAs($this->storeHead)
            ->post(route('retail.stock-transfers.receive', $transfer), $payload)
            ->assertRedirect(route('retail.stock-transfers.receiving'))
            ->assertSessionHas('notification.type', 'success');

        $this->assertSame(StockTransferStatus::FULLY_RECEIVED, $transfer->fresh()->status);

        $stock1 = Stock::query()->where('product_id', $product1->id)->where('work_location_id', $branch->work_location_id)->firstOrFail();
        $stock2 = Stock::query()->where('product_id', $product2->id)->where('work_location_id', $branch->work_location_id)->firstOrFail();

        $this->assertSame('5.0000', $stock1->quantity_on_hand);
        // 2 Dus * 12 = 24 base units
        $this->assertSame('24.0000', $stock2->quantity_on_hand);
    }

    /**
     * Requirement 2: Partial receive with damaged goods where received + damaged = inTransit
     * marks status properly (FULLY_RECEIVED because all accounted for) and routes damaged stock to damage inventory.
     */
    public function test_receive_with_damaged_goods_where_received_plus_damaged_equals_intransit(): void
    {
        [$warehouse, $branch, $product, $sourceBin, $destinationBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);
        $this->inventory->receive($product, $warehouse->workLocation, $sourceBin, '10', $this->warehouseHead);

        $transfer = $this->makeApprovedTransfer($warehouse, $branch, $product, $sourceBin, $destinationBin, '10');
        $item = $transfer->items->firstOrFail();
        $this->transfers->pack($transfer, ['items' => [$item->id => ['quantity_picked' => '10']]], $this->warehouseStaff);
        $transfer = $this->transfers->ship($transfer, ['carrier' => 'Ekspedisi Logistik'], $this->warehouseStaff);

        $inTransitQty = $item->fresh()->inTransitQuantity();
        $this->assertSame('10.0000', $inTransitQty);

        // Submit: 7 received good, 3 damaged, 0 discrepancy (7 + 3 = 10)
        $payload = [
            'idempotency_key' => 'm2-damage-full-accounted',
            'items' => [
                $item->id => [
                    'quantity_received' => '7',
                    'quantity_damaged' => '3',
                    'quantity_discrepancy' => '0',
                    'notes' => '3 unit kemasan penyok / pecah',
                ],
            ],
        ];

        $response = $this->actingAs($this->storeHead)
            ->post(route('retail.stock-transfers.receive', $transfer), $payload);

        $response->assertRedirect(route('retail.stock-transfers.receiving'))
            ->assertSessionHas('notification.type', 'success');

        $freshTransfer = $transfer->fresh();
        // Since all 10 are accounted for and no discrepancy, status is FULLY_RECEIVED
        $this->assertSame(StockTransferStatus::FULLY_RECEIVED, $freshTransfer->status);
        $this->assertSame('0.0000', $item->fresh()->inTransitQuantity());

        // Check destination inventory:
        // on_hand should be 10.0000 (total physically received)
        // quantity_damaged should be 3.0000 (sequestered in damage inventory)
        // available_quantity should be 7.0000 (only undamaged available for sale)
        $destinationStock = Stock::query()
            ->where('product_id', $product->id)
            ->where('work_location_id', $branch->work_location_id)
            ->firstOrFail();

        $this->assertSame('10.0000', $destinationStock->quantity_on_hand);
        $this->assertSame('3.0000', $destinationStock->quantity_damaged);
        $this->assertSame('7.0000', $destinationStock->available_quantity);

        // Check stock mutations in destination:
        // Must contain transfer_in for good, transfer_in for damaged, and damage mutation
        $damageMutation = StockMutation::query()
            ->where('product_id', $product->id)
            ->where('work_location_id', $branch->work_location_id)
            ->where('mutation_type', StockMutationType::DAMAGE)
            ->firstOrFail();
        $this->assertSame('3.0000', $damageMutation->quantity_damaged_change);
    }

    /**
     * Requirement 2 Variant: Partial receive where received + damaged < inTransit
     * marks status as PARTIALLY_RECEIVED, routes damaged stock, and allows subsequent receive of remainder.
     */
    public function test_partial_receive_with_damaged_goods_less_than_intransit_allows_subsequent_receive(): void
    {
        [$warehouse, $branch, $product, $sourceBin, $destinationBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);
        $this->inventory->receive($product, $warehouse->workLocation, $sourceBin, '10', $this->warehouseHead);

        $transfer = $this->makeApprovedTransfer($warehouse, $branch, $product, $sourceBin, $destinationBin, '10');
        $item = $transfer->items->firstOrFail();
        $this->transfers->pack($transfer, ['items' => [$item->id => ['quantity_picked' => '10']]], $this->warehouseStaff);
        $transfer = $this->transfers->ship($transfer, ['carrier' => 'Ekspedisi Logistik'], $this->warehouseStaff);

        // 1st Receive: 4 received good, 2 damaged, 0 discrepancy (4 + 2 = 6 < 10)
        $payload1 = [
            'idempotency_key' => 'm2-partial-batch-1',
            'items' => [
                $item->id => [
                    'quantity_received' => '4',
                    'quantity_damaged' => '2',
                    'quantity_discrepancy' => '0',
                    'notes' => 'Penerimaan bertahap trip 1',
                ],
            ],
        ];

        $this->actingAs($this->storeHead)
            ->post(route('retail.stock-transfers.receive', $transfer), $payload1)
            ->assertRedirect(route('retail.stock-transfers.receiving'));

        $transfer = $transfer->fresh();
        $this->assertSame(StockTransferStatus::PARTIALLY_RECEIVED, $transfer->status);
        $this->assertSame('4.0000', $item->fresh()->inTransitQuantity());

        $stock = Stock::query()
            ->where('product_id', $product->id)
            ->where('work_location_id', $branch->work_location_id)
            ->firstOrFail();
        $this->assertSame('6.0000', $stock->quantity_on_hand);
        $this->assertSame('2.0000', $stock->quantity_damaged);
        $this->assertSame('4.0000', $stock->available_quantity);

        // 2nd Receive: remaining 4 received good
        $payload2 = [
            'idempotency_key' => 'm2-partial-batch-2',
            'items' => [
                $item->id => [
                    'quantity_received' => '4',
                    'quantity_damaged' => '0',
                    'quantity_discrepancy' => '0',
                    'notes' => 'Penerimaan bertahap trip 2 selesai',
                ],
            ],
        ];

        $this->actingAs($this->storeHead)
            ->post(route('retail.stock-transfers.receive', $transfer), $payload2)
            ->assertRedirect(route('retail.stock-transfers.receiving'));

        $transfer = $transfer->fresh();
        $this->assertSame(StockTransferStatus::FULLY_RECEIVED, $transfer->status);
        $this->assertSame('0.0000', $item->fresh()->inTransitQuantity());

        $stock->refresh();
        $this->assertSame('10.0000', $stock->quantity_on_hand);
        $this->assertSame('2.0000', $stock->quantity_damaged);
        $this->assertSame('8.0000', $stock->available_quantity);
    }

    /**
     * Requirement 3: Submitting received > inTransit is rejected by backend.
     */
    public function test_receive_rejects_received_quantity_exceeding_intransit(): void
    {
        [$warehouse, $branch, $product, $sourceBin, $destinationBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);
        $this->inventory->receive($product, $warehouse->workLocation, $sourceBin, '10', $this->warehouseHead);

        $transfer = $this->makeApprovedTransfer($warehouse, $branch, $product, $sourceBin, $destinationBin, '5');
        $item = $transfer->items->firstOrFail();
        $this->transfers->pack($transfer, ['items' => [$item->id => ['quantity_picked' => '5']]], $this->warehouseStaff);
        $transfer = $this->transfers->ship($transfer, ['carrier' => 'Kurir'], $this->warehouseStaff);

        $initialMutationCount = StockMutation::query()->count();
        $initialReceiptCount = StockTransferReceipt::query()->count();

        // inTransit is 5. Try to submit 6 received.
        $payload = [
            'idempotency_key' => 'm2-excess-received-test',
            'items' => [
                $item->id => [
                    'quantity_received' => '6',
                    'quantity_damaged' => '0',
                    'quantity_discrepancy' => '0',
                ],
            ],
        ];

        $response = $this->actingAs($this->storeHead)
            ->from(route('retail.stock-transfers.receive-form', $transfer))
            ->post(route('retail.stock-transfers.receive', $transfer), $payload);

        $response->assertRedirect(route('retail.stock-transfers.receive-form', $transfer))
            ->assertSessionHas('notification.type', 'danger');

        // Verify no DB changes occurred
        $this->assertSame(StockTransferStatus::SHIPPED, $transfer->fresh()->status);
        $this->assertSame($initialMutationCount, StockMutation::query()->count());
        $this->assertSame($initialReceiptCount, StockTransferReceipt::query()->count());
        $this->assertNull(Stock::query()->where('product_id', $product->id)->where('work_location_id', $branch->work_location_id)->first());
    }

    /**
     * Requirement 3 Variant: Submitting received + damaged > inTransit is rejected by backend.
     */
    public function test_receive_rejects_sum_of_received_and_damaged_exceeding_intransit(): void
    {
        [$warehouse, $branch, $product, $sourceBin, $destinationBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);
        $this->inventory->receive($product, $warehouse->workLocation, $sourceBin, '10', $this->warehouseHead);

        $transfer = $this->makeApprovedTransfer($warehouse, $branch, $product, $sourceBin, $destinationBin, '5');
        $item = $transfer->items->firstOrFail();
        $this->transfers->pack($transfer, ['items' => [$item->id => ['quantity_picked' => '5']]], $this->warehouseStaff);
        $transfer = $this->transfers->ship($transfer, ['carrier' => 'Kurir'], $this->warehouseStaff);

        $initialMutationCount = StockMutation::query()->count();

        // inTransit is 5. Try to submit 3 received + 3 damaged = 6 > 5.
        $payload = [
            'idempotency_key' => 'm2-excess-sum-damaged-test',
            'items' => [
                $item->id => [
                    'quantity_received' => '3',
                    'quantity_damaged' => '3',
                    'quantity_discrepancy' => '0',
                ],
            ],
        ];

        $response = $this->actingAs($this->storeHead)
            ->from(route('retail.stock-transfers.receive-form', $transfer))
            ->post(route('retail.stock-transfers.receive', $transfer), $payload);

        $response->assertRedirect(route('retail.stock-transfers.receive-form', $transfer))
            ->assertSessionHas('notification.type', 'danger');

        $this->assertSame(StockTransferStatus::SHIPPED, $transfer->fresh()->status);
        $this->assertSame($initialMutationCount, StockMutation::query()->count());
    }

    /**
     * Requirement 3 Variant: Submitting received + damaged + discrepancy > inTransit is rejected by backend.
     */
    public function test_receive_rejects_sum_of_received_damaged_and_discrepancy_exceeding_intransit(): void
    {
        [$warehouse, $branch, $product, $sourceBin, $destinationBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);
        $this->inventory->receive($product, $warehouse->workLocation, $sourceBin, '10', $this->warehouseHead);

        $transfer = $this->makeApprovedTransfer($warehouse, $branch, $product, $sourceBin, $destinationBin, '5');
        $item = $transfer->items->firstOrFail();
        $this->transfers->pack($transfer, ['items' => [$item->id => ['quantity_picked' => '5']]], $this->warehouseStaff);
        $transfer = $this->transfers->ship($transfer, ['carrier' => 'Kurir'], $this->warehouseStaff);

        $initialMutationCount = StockMutation::query()->count();

        // inTransit is 5. Try to submit 2 received + 2 damaged + 2 discrepancy = 6 > 5.
        $payload = [
            'idempotency_key' => 'm2-excess-sum-discrepancy-test',
            'items' => [
                $item->id => [
                    'quantity_received' => '2',
                    'quantity_damaged' => '2',
                    'quantity_discrepancy' => '2',
                ],
            ],
        ];

        $response = $this->actingAs($this->storeHead)
            ->from(route('retail.stock-transfers.receive-form', $transfer))
            ->post(route('retail.stock-transfers.receive', $transfer), $payload);

        $response->assertRedirect(route('retail.stock-transfers.receive-form', $transfer))
            ->assertSessionHas('notification.type', 'danger');

        $this->assertSame(StockTransferStatus::SHIPPED, $transfer->fresh()->status);
        $this->assertSame($initialMutationCount, StockMutation::query()->count());
    }

    /**
     * Requirement 3 Variant: Cumulative receipt across multiple batches cannot exceed quantity_shipped.
     */
    public function test_receive_rejects_cumulative_excess_across_multiple_receipts(): void
    {
        [$warehouse, $branch, $product, $sourceBin, $destinationBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);
        $this->inventory->receive($product, $warehouse->workLocation, $sourceBin, '10', $this->warehouseHead);

        $transfer = $this->makeApprovedTransfer($warehouse, $branch, $product, $sourceBin, $destinationBin, '5');
        $item = $transfer->items->firstOrFail();
        $this->transfers->pack($transfer, ['items' => [$item->id => ['quantity_picked' => '5']]], $this->warehouseStaff);
        $transfer = $this->transfers->ship($transfer, ['carrier' => 'Kurir'], $this->warehouseStaff);

        // 1st receipt: receive 3 units (valid, 2 units remaining in transit)
        $this->actingAs($this->storeHead)
            ->post(route('retail.stock-transfers.receive', $transfer), [
                'idempotency_key' => 'm2-cum-1',
                'items' => [
                    $item->id => [
                        'quantity_received' => '3',
                        'quantity_damaged' => '0',
                        'quantity_discrepancy' => '0',
                    ],
                ],
            ])
            ->assertRedirect(route('retail.stock-transfers.receiving'));

        $this->assertSame('2.0000', $item->fresh()->inTransitQuantity());

        // 2nd receipt attempt: try to receive 3 more units (3 + 3 = 6 > 5 shipped)
        $this->actingAs($this->storeHead)
            ->from(route('retail.stock-transfers.receive-form', $transfer))
            ->post(route('retail.stock-transfers.receive', $transfer), [
                'idempotency_key' => 'm2-cum-2-excess',
                'items' => [
                    $item->id => [
                        'quantity_received' => '3',
                        'quantity_damaged' => '0',
                        'quantity_discrepancy' => '0',
                    ],
                ],
            ])
            ->assertRedirect(route('retail.stock-transfers.receive-form', $transfer))
            ->assertSessionHas('notification.type', 'danger');

        // Status remains partially received, stock on hand remains 3
        $this->assertSame(StockTransferStatus::PARTIALLY_RECEIVED, $transfer->fresh()->status);
        $stock = Stock::query()->where('product_id', $product->id)->where('work_location_id', $branch->work_location_id)->firstOrFail();
        $this->assertSame('3.0000', $stock->quantity_on_hand);
    }

    /**
     * Requirement: Validation rejects negative values in FormRequest.
     */
    public function test_receive_rejects_negative_quantities(): void
    {
        [$warehouse, $branch, $product, $sourceBin, $destinationBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);
        $this->inventory->receive($product, $warehouse->workLocation, $sourceBin, '10', $this->warehouseHead);

        $transfer = $this->makeApprovedTransfer($warehouse, $branch, $product, $sourceBin, $destinationBin, '5');
        $item = $transfer->items->firstOrFail();
        $this->transfers->pack($transfer, ['items' => [$item->id => ['quantity_picked' => '5']]], $this->warehouseStaff);
        $transfer = $this->transfers->ship($transfer, ['carrier' => 'Kurir'], $this->warehouseStaff);

        $response = $this->actingAs($this->storeHead)
            ->post(route('retail.stock-transfers.receive', $transfer), [
                'items' => [
                    $item->id => [
                        'quantity_received' => '-1',
                        'quantity_damaged' => '0',
                        'quantity_discrepancy' => '0',
                    ],
                ],
            ]);

        $response->assertSessionHasErrors("items.{$item->id}.quantity_received");
    }

    /**
     * Requirement: Validation rejects zero total quantities across all items.
     */
    public function test_receive_rejects_zero_total_quantities(): void
    {
        [$warehouse, $branch, $product, $sourceBin, $destinationBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);
        $this->inventory->receive($product, $warehouse->workLocation, $sourceBin, '10', $this->warehouseHead);

        $transfer = $this->makeApprovedTransfer($warehouse, $branch, $product, $sourceBin, $destinationBin, '5');
        $item = $transfer->items->firstOrFail();
        $this->transfers->pack($transfer, ['items' => [$item->id => ['quantity_picked' => '5']]], $this->warehouseStaff);
        $transfer = $this->transfers->ship($transfer, ['carrier' => 'Kurir'], $this->warehouseStaff);

        $response = $this->actingAs($this->storeHead)
            ->from(route('retail.stock-transfers.receive-form', $transfer))
            ->post(route('retail.stock-transfers.receive', $transfer), [
                'items' => [
                    $item->id => [
                        'quantity_received' => '0',
                        'quantity_damaged' => '0',
                        'quantity_discrepancy' => '0',
                    ],
                ],
            ]);

        $response->assertRedirect(route('retail.stock-transfers.receive-form', $transfer))
            ->assertSessionHas('notification.type', 'danger');
    }

    /**
     * UI Verification: receive.blade.php renders 1-click buttons, live summary elements, and item data attributes.
     */
    public function test_receive_form_renders_one_click_buttons_and_summary_indicators(): void
    {
        [$warehouse, $branch, $product, $sourceBin, $destinationBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);
        $this->inventory->receive($product, $warehouse->workLocation, $sourceBin, '10', $this->warehouseHead);

        $transfer = $this->makeApprovedTransfer($warehouse, $branch, $product, $sourceBin, $destinationBin, '5');
        $item = $transfer->items->firstOrFail();
        $this->transfers->pack($transfer, ['items' => [$item->id => ['quantity_picked' => '5']]], $this->warehouseStaff);
        $transfer = $this->transfers->ship($transfer, ['carrier' => 'Kurir'], $this->warehouseStaff);

        $response = $this->actingAs($this->storeHead)
            ->get(route('retail.stock-transfers.receive-form', $transfer));

        $response->assertOk();
        // Check 1-click button elements
        $response->assertSee('id="btn-receive-all"', false);
        $response->assertSee('id="btn-receive-all-inline"', false);
        $response->assertSee('btn-receive-all-action');
        $response->assertSee('1-Klik: Terima Semua Sesuai Pengiriman');

        // Check dynamic summary cards
        $response->assertSee('id="summary-total-in-transit"', false);
        $response->assertSee('id="summary-total-received"', false);
        $response->assertSee('id="summary-total-damaged"', false);
        $response->assertSee('id="summary-total-discrepancy"', false);
        $response->assertSee('id="summary-status-alert"', false);
        $response->assertSee('Penerimaan Sempurna');

        // Check row and data attributes
        $response->assertSee('class="stock-transfer-item-row"', false);
        $response->assertSee('data-in-transit="5.0000"', false);
        $response->assertSee('quantity-received');
        $response->assertSee('quantity-damaged');
        $response->assertSee('quantity-discrepancy');
        $response->assertSee('id="btn-submit-receive"', false);
    }

    /** @return array{Warehouse, Branch, Product, WarehouseLocation, WarehouseLocation} */
    private function fixture(): array
    {
        [$warehouse, $branch] = $this->warehouseAndBranch('CHALLENGE-M2');
        $unit = Unit::factory()->create(['code' => 'PCS']);
        $product = Product::factory()->create(['base_unit_id' => $unit->id, 'status' => 'active', 'sku' => 'CHL-M2-PRD']);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'name' => 'PCS',
            'conversion_factor' => '1.000000',
            'is_base' => true,
            'is_sellable' => true,
            'is_active' => true,
        ]);
        $sourceBin = WarehouseLocation::factory()->create(['warehouse_id' => $warehouse->id, 'full_code' => 'CHL-SRC-BIN']);
        $destinationBin = WarehouseLocation::factory()->create(['warehouse_id' => $warehouse->id, 'full_code' => 'CHL-DST-BIN']);

        return [$warehouse, $branch, $product, $sourceBin, $destinationBin];
    }

    /** @return array{Warehouse, Branch} */
    private function warehouseAndBranch(string $suffix): array
    {
        $warehouseLocation = WorkLocation::factory()->create(['type' => 'warehouse', 'code' => "GDG-$suffix"]);
        $branchLocation = WorkLocation::factory()->create(['type' => 'branch', 'code' => "TKO-$suffix"]);
        $warehouse = Warehouse::factory()->create(['work_location_id' => $warehouseLocation->id, 'code' => "GDG-$suffix"]);
        $branch = Branch::factory()->create(['work_location_id' => $branchLocation->id, 'primary_warehouse_id' => $warehouse->id, 'code' => "TKO-$suffix"]);

        return [$warehouse, $branch];
    }

    private function assignScope(Warehouse $warehouse, Branch $branch): void
    {
        foreach ([$this->warehouseHead, $this->warehouseStaff] as $user) {
            $user->workLocations()->syncWithoutDetaching([$warehouse->work_location_id => ['is_default' => true, 'is_active' => true]]);
        }

        $this->warehouseHead->workLocations()->syncWithoutDetaching([$branch->work_location_id => ['is_default' => false, 'is_active' => true]]);
        $this->storeHead->workLocations()->syncWithoutDetaching([$branch->work_location_id => ['is_default' => true, 'is_active' => true]]);
    }

    private function makeApprovedTransfer(Warehouse $warehouse, Branch $branch, Product $product, WarehouseLocation $sourceBin, WarehouseLocation $destinationBin, string $qty): StockTransfer
    {
        $transfer = $this->transfers->create([
            'source_work_location_id' => $warehouse->work_location_id,
            'source_warehouse_location_id' => $sourceBin->id,
            'destination_work_location_id' => $branch->work_location_id,
            'destination_warehouse_location_id' => $destinationBin->id,
            'transfer_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $product->id,
                'quantity_requested' => $qty,
                'quantity_approved' => $qty,
                'source_warehouse_location_id' => $sourceBin->id,
                'destination_warehouse_location_id' => $destinationBin->id,
            ]],
            'action' => 'submit',
        ], $this->warehouseStaff);

        return $this->transfers->approve($transfer, $this->warehouseHead);
    }
}
