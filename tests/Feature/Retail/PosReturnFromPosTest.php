<?php

namespace Tests\Feature\Retail;

use App\Enums\CashShiftStatus;
use App\Enums\PosSaleStatus;
use App\Enums\ProductPriceStatus;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\PosReturn;
use App\Models\PosSale;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductUnit;
use App\Models\Stock;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkLocation;
use App\Services\Inventory\InventoryService;
use App\Services\Retail\CashShiftService;
use App\Services\Retail\PosService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PosReturnFromPosTest extends TestCase
{
    use RefreshDatabase;

    private PosService $pos;

    private InventoryService $inventory;

    private CashShiftService $shifts;

    private User $cashier;

    private User $supervisor;

    private Branch $branch;

    private WorkLocation $branchLocation;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->pos = app(PosService::class);
        $this->inventory = app(InventoryService::class);
        $this->shifts = app(CashShiftService::class);

        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole(Role::findOrCreate('kasir'));
        $this->supervisor = User::factory()->create(['is_active' => true]);
        $this->supervisor->assignRole(Role::findOrCreate('kepala_toko'));

        $this->branchLocation = WorkLocation::factory()->create(['type' => 'branch', 'code' => 'TKO-RET', 'name' => 'Toko Retur']);
        $warehouseLocation = WorkLocation::factory()->create(['type' => 'warehouse', 'code' => 'GDG-RET', 'name' => 'Gudang Retur']);
        $warehouse = Warehouse::factory()->create(['work_location_id' => $warehouseLocation->id]);
        $this->branch = Branch::factory()->create(['work_location_id' => $this->branchLocation->id, 'primary_warehouse_id' => $warehouse->id]);

        $this->cashier->workLocations()->sync([$this->branchLocation->id => ['is_default' => true, 'is_active' => true]]);
        $this->supervisor->workLocations()->sync([$this->branchLocation->id => ['is_default' => true, 'is_active' => true]]);

        $this->unit = Unit::factory()->create(['name' => 'Pcs', 'symbol' => 'pcs']);

        PriceRule::query()->create([
            'name' => 'POS Retur Test Rule',
            'channel' => 'all',
            'margin_method' => 'percent',
            'minimum_margin_percent' => '0.00',
            'minimum_margin_amount' => '0.00',
            'overpricing_tolerance_percent' => '100.00',
            'max_discount_percent' => '10.00',
            'priority' => 1,
            'is_active' => true,
        ]);

        $this->openShift();
    }

    public function test_cashier_can_lookup_sales_in_same_branch(): void
    {
        $product = $this->stockedProduct('10');
        $sale = $this->checkoutSale($product, quantity: '3', paid: '300000');

        $response = $this->actingAs($this->cashier)->getJson(route('retail.pos.sales-lookup', ['query' => $sale->number]));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('sales.0.id', $sale->id)
            ->assertJsonPath('sales.0.number', $sale->number)
            ->assertJsonPath('sales.0.can_return', true)
            ->assertJsonPath('sales.0.items.0.quantity', 3)
            ->assertJsonPath('sales.0.items.0.remaining_quantity', 3);
    }

    public function test_cashier_can_process_return_directly_from_pos_and_stock_is_returned(): void
    {
        $product = $this->stockedProduct('10');
        $sale = $this->checkoutSale($product, quantity: '3', paid: '300000');

        // Initial stock after sale of 3 from 10 is 7
        $stockBefore = Stock::query()->where('product_id', $product->id)->where('work_location_id', $this->branchLocation->id)->value('quantity_on_hand');
        $this->assertEquals(7, (float) $stockBefore);

        $activeShift = CashShift::query()->where('branch_id', $this->branch->id)->where('status', CashShiftStatus::OPEN)->first();
        $summaryBefore = $this->shifts->summary($activeShift);

        $item = $sale->items->first();

        // Process return of 1 item via POS return endpoint
        $response = $this->actingAs($this->cashier)->postJson(route('retail.pos.returns.store'), [
            'pos_sale_id' => $sale->id,
            'resolution' => 'refund',
            'refund_method' => 'cash',
            'reason' => 'Barang salah varian.',
            'items' => [
                [
                    'pos_sale_item_id' => $item->id,
                    'quantity' => 1,
                    'condition' => 'good',
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('return.refund_method', 'cash');

        $returnId = $response->json('return.id');
        $posReturn = PosReturn::query()->findOrFail($returnId);

        $this->assertNotNull($posReturn->cash_shift_id);
        $this->assertEquals($activeShift->id, $posReturn->cash_shift_id);

        // Stock restored back to 8
        $stockAfter = Stock::query()->where('product_id', $product->id)->where('work_location_id', $this->branchLocation->id)->value('quantity_on_hand');
        $this->assertEquals(8, (float) $stockAfter);

        // Sale item returned_quantity is updated to 1
        $this->assertEquals(1, (float) $item->fresh()->returned_quantity);
        $this->assertEquals(PosSaleStatus::RETURNED, $sale->fresh()->status);

        // Shift summary reflects cash refund
        $summaryAfter = $this->shifts->summary($activeShift->fresh());
        $this->assertGreaterThan((float) $summaryBefore['refunds'], (float) $summaryAfter['refunds']);
    }

    public function test_cashier_can_process_return_for_sale_from_previous_closed_shift_in_same_branch(): void
    {
        $product = $this->stockedProduct('10');
        $sale = $this->checkoutSale($product, quantity: '2', paid: '200000');

        // Close current shift
        $oldShift = CashShift::query()->where('branch_id', $this->branch->id)->where('status', CashShiftStatus::OPEN)->first();
        $submitted = $this->shifts->submitClosing($oldShift, [
            'actual_cash_amount' => '100000',
            'discrepancy_reason' => 'Closing normal',
        ], $this->cashier);
        $this->shifts->approve($submitted, $this->supervisor, 'Approve');

        $this->assertEquals(CashShiftStatus::CLOSED, $oldShift->fresh()->status);

        // Open new shift for cashier
        $newShift = $this->shifts->open([
            'branch_id' => $this->branch->id,
            'terminal_code' => 'POS-01',
            'opening_cash_amount' => '500000',
        ], $this->cashier);

        $item = $sale->items->first();

        // Process return on the new shift for yesterday's sale
        $response = $this->actingAs($this->cashier)->postJson(route('retail.pos.returns.store'), [
            'pos_sale_id' => $sale->id,
            'resolution' => 'refund',
            'refund_method' => 'cash',
            'reason' => 'Barang cacat pabrik.',
            'items' => [
                [
                    'pos_sale_item_id' => $item->id,
                    'quantity' => 1,
                    'condition' => 'good',
                ],
            ],
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $posReturn = PosReturn::query()->findOrFail($response->json('return.id'));
        $this->assertEquals($newShift->id, $posReturn->cash_shift_id);

        // New shift should reflect this refund in expected cash
        $summary = $this->shifts->summary($newShift->fresh());
        $this->assertGreaterThan(0, (float) $summary['refunds']);
    }

    public function test_reprinted_receipt_displays_return_deductions_and_net_total(): void
    {
        $product = $this->stockedProduct('10');
        $sale = $this->checkoutSale($product, quantity: '3', paid: '300000');

        $item = $sale->items->first();
        $this->actingAs($this->cashier)->postJson(route('retail.pos.returns.store'), [
            'pos_sale_id' => $sale->id,
            'resolution' => 'refund',
            'refund_method' => 'cash',
            'reason' => 'Kemasan bocor.',
            'items' => [
                [
                    'pos_sale_item_id' => $item->id,
                    'quantity' => 1,
                    'condition' => 'good',
                ],
            ],
        ])->assertOk();

        // Print reprinted receipt
        $response = $this->actingAs($this->cashier)->get(route('retail.sales.print', $sale));

        $response->assertOk()
            ->assertSee('STRUK REVISI (TERDAPAT RETUR)')
            ->assertSee('Retur: -1')
            ->assertSee('Netto: 2')
            ->assertSee('TOTAL SETELAH RETUR');

        // Print Nota Retur
        $posReturn = PosReturn::query()->where('pos_sale_id', $sale->id)->first();
        $returnReceipt = $this->actingAs($this->cashier)->get(route('retail.sales.return-receipt', $posReturn));

        $returnReceipt->assertOk()
            ->assertSee('BUKTI PENGEMBALIAN BARANG (RETUR)')
            ->assertSee($posReturn->number)
            ->assertSee('TOTAL DANA KEMBALI');
    }

    private function openShift(): CashShift
    {
        return $this->shifts->open([
            'branch_id' => $this->branch->id,
            'terminal_code' => 'POS-01',
            'opening_cash_amount' => '100000',
        ], $this->cashier);
    }

    private function stockedProduct(string $qty = '10'): Product
    {
        $product = Product::factory()->create([
            'base_unit_id' => $this->unit->id,
            'cost_price' => '60000.00',
            'minimum_price' => '80000.00',
            'minimum_stock' => '1.0000',
        ]);

        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit_id' => $this->unit->id,
            'conversion_factor' => 1,
            'is_base' => true,
        ]);

        ProductPrice::query()->create([
            'product_id' => $product->id,
            'branch_id' => $this->branch->id,
            'channel' => 'pos',
            'price_ring' => 'R1',
            'min_price' => '80000.00',
            'recommended_price' => '100000.00',
            'max_price' => '150000.00',
            'minimum_qty' => 1,
            'priority' => 1,
            'status' => ProductPriceStatus::ACTIVE,
        ]);

        $this->inventory->receive(
            $product,
            $this->branchLocation,
            null,
            $qty,
            $this->supervisor,
            ['type' => 'opening', 'no' => 'OP-1']
        );

        return $product;
    }

    private function checkoutSale(Product $product, string $quantity = '1', string $paid = '200000'): PosSale
    {
        return $this->pos->checkout([
            'branch_id' => $this->branch->id,
            'idempotency_key' => (string) str()->uuid(),
            'customer_id' => null,
            'items' => [
                [
                    'product_id' => $product->id,
                    'unit_id' => $this->unit->id,
                    'quantity' => $quantity,
                    'selected_price' => '100000.00',
                    'discount_percent' => 0,
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => $paid,
                ],
            ],
            'notes' => 'Test sale',
        ], $this->cashier);
    }
}
