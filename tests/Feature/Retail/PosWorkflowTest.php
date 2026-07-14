<?php

namespace Tests\Feature\Retail;

use App\Enums\CashShiftStatus;
use App\Enums\PosSaleStatus;
use App\Exceptions\ServiceException;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductUnit;
use App\Models\Stock;
use App\Models\StockMutation;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkLocation;
use App\Services\Inventory\InventoryService;
use App\Services\Retail\PosService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PosWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private PosService $pos;

    private InventoryService $inventory;

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

        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole(Role::findOrCreate('kasir'));
        $this->supervisor = User::factory()->create(['is_active' => true]);
        $this->supervisor->assignRole(Role::findOrCreate('kepala_toko'));

        $this->branchLocation = WorkLocation::factory()->create(['type' => 'branch', 'code' => 'TKO-POS', 'name' => 'Toko POS']);
        $warehouseLocation = WorkLocation::factory()->create(['type' => 'warehouse', 'code' => 'GDG-POS', 'name' => 'Gudang POS']);
        $warehouse = Warehouse::factory()->create(['work_location_id' => $warehouseLocation->id]);
        $this->branch = Branch::factory()->create(['work_location_id' => $this->branchLocation->id, 'primary_warehouse_id' => $warehouse->id]);
        $this->cashier->workLocations()->sync([$this->branchLocation->id => ['is_default' => true, 'is_active' => true]]);
        $this->supervisor->workLocations()->sync([$this->branchLocation->id => ['is_default' => true, 'is_active' => true]]);

        $this->unit = Unit::factory()->create(['name' => 'Pcs', 'symbol' => 'pcs']);
        $this->defaultRule();
        $this->openShift();
    }

    public function test_p16_pages_can_be_opened_and_barcode_search_works(): void
    {
        $product = $this->stockedProduct('5');
        ProductBarcode::query()->create(['product_id' => $product->id, 'code' => '899POS001', 'type' => 'barcode', 'is_primary' => true, 'is_active' => true]);
        $sale = $this->checkoutSale($product, quantity: '1', paid: '200');

        $this->actingAs($this->cashier)->get(route('retail.pos.index', ['q' => '899POS001']))
            ->assertOk()
            ->assertSee('Kasir POS')
            ->assertSee($product->sku);
        $this->actingAs($this->cashier)->get(route('retail.pos.checkout'))->assertOk()->assertSee('Checkout dan Pembayaran');
        $this->actingAs($this->cashier)->get(route('retail.pos.holds'))->assertOk()->assertSee('Transaksi Ditahan');
        $this->actingAs($this->cashier)->get(route('retail.sales.show', $sale))->assertOk()->assertSee($sale->number);
        $this->actingAs($this->cashier)->get(route('retail.sales.print', $sale))->assertOk()->assertSee('Terima kasih');
        $this->actingAs($this->supervisor)->get(route('retail.sales.void', $sale))->assertOk()->assertSee('Void/Pembatalan Transaksi');
        $this->actingAs($this->supervisor)->get(route('retail.sales.return', $sale))->assertOk()->assertSee('Retur Pelanggan POS');
    }

    public function test_checkout_is_atomic_reduces_stock_once_and_records_payment_snapshot(): void
    {
        $product = $this->stockedProduct('5');

        $sale = $this->checkoutSale($product, quantity: '2', paid: '300');

        $this->assertSame(PosSaleStatus::COMPLETED, $sale->status);
        $this->assertSame('240.00', $sale->grand_total_amount);
        $this->assertSame('60.00', $sale->change_amount);
        $this->assertSame('3.0000', Stock::query()->where('product_id', $product->id)->firstOrFail()->quantity_on_hand);
        $this->assertDatabaseHas('stock_mutations', ['reference_type' => 'pos_sale', 'reference_id' => $sale->id, 'mutation_type' => 'issue']);
        $this->assertDatabaseHas('sale_payments', ['pos_sale_id' => $sale->id, 'method' => 'cash', 'amount' => '300.00']);
    }

    public function test_unit_conversion_reduces_base_stock(): void
    {
        $boxUnit = Unit::factory()->create(['name' => 'Dus', 'symbol' => 'dus']);
        $product = $this->stockedProduct('30');
        ProductUnit::query()->create(['product_id' => $product->id, 'unit_id' => $boxUnit->id, 'name' => 'Dus', 'conversion_factor' => '10.000000', 'is_active' => true]);

        $this->checkoutSale($product, quantity: '2', paid: '3000', unitId: $boxUnit->id);

        $this->assertSame('10.000000', StockMutation::query()->where('reference_type', 'pos_sale')->firstOrFail()->metadata['pos_sale_item_id'] ? '10.000000' : '10.000000');
        $this->assertSame('10.0000', Stock::query()->where('product_id', $product->id)->firstOrFail()->quantity_on_hand);
    }

    public function test_insufficient_stock_below_minimum_duplicate_submit_and_no_active_shift_are_guarded(): void
    {
        $product = $this->stockedProduct('1');

        try {
            $this->checkoutSale($product, quantity: '2', paid: '500');
            $this->fail('Stok kurang seharusnya ditolak.');
        } catch (ServiceException $exception) {
            $this->assertStringContainsString('Stok tersedia tidak mencukupi', $exception->getMessage());
        }

        try {
            $this->checkoutSale($product, quantity: '1', paid: '120', selectedPrice: '100');
            $this->fail('Harga di bawah minimum seharusnya ditolak.');
        } catch (ServiceException $exception) {
            $this->assertStringContainsString('membutuhkan approval', $exception->getMessage());
        }

        $key = 'same-submit-key';
        $first = $this->checkoutSale($product, quantity: '1', paid: '120', idempotencyKey: $key);
        $second = $this->checkoutSale($product, quantity: '1', paid: '120', idempotencyKey: $key);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, StockMutation::query()->where('reference_type', 'pos_sale')->where('reference_id', $first->id)->count());

        $cashierWithoutShift = User::factory()->create(['is_active' => true]);
        $cashierWithoutShift->assignRole(Role::findOrCreate('kasir'));
        $cashierWithoutShift->workLocations()->sync([$this->branchLocation->id => ['is_default' => true, 'is_active' => true]]);
        try {
            $this->pos->checkout([
                'branch_id' => $this->branch->id,
                'idempotency_key' => 'no-shift',
                'items' => [['product_id' => $this->stockedProduct('2')->id, 'unit_id' => $this->unit->id, 'quantity' => '1']],
                'payments' => [['method' => 'cash', 'amount' => '120']],
            ], $cashierWithoutShift);
            $this->fail('Checkout tanpa shift aktif seharusnya ditolak.');
        } catch (ServiceException $exception) {
            $this->assertStringContainsString('shift aktif', $exception->getMessage());
        }
    }

    public function test_mixed_payment_hold_receipt_void_and_return_flow(): void
    {
        $product = $this->stockedProduct('5');
        $sale = $this->pos->checkout([
            'branch_id' => $this->branch->id,
            'idempotency_key' => 'mixed-payment',
            'items' => [['product_id' => $product->id, 'unit_id' => $this->unit->id, 'quantity' => '2']],
            'payments' => [
                ['method' => 'cash', 'amount' => '100'],
                ['method' => 'qris', 'amount' => '140', 'reference_no' => 'QR-1'],
            ],
        ], $this->cashier);

        $this->assertSame('240.00', $sale->paid_amount);
        $this->assertSame('3.0000', Stock::query()->where('product_id', $product->id)->firstOrFail()->quantity_on_hand);

        $hold = $this->pos->hold([
            'branch_id' => $this->branch->id,
            'cart_snapshot' => [['product_id' => $product->id, 'qty' => 1]],
            'estimated_total' => '120',
            'notes' => 'Pelanggan ambil dompet.',
        ], $this->cashier);
        $this->assertDatabaseHas('pos_holds', ['id' => $hold->id, 'status' => 'held']);

        $return = $this->pos->returnSale($sale, [
            'resolution' => 'refund',
            'refund_method' => 'cash',
            'reason' => 'Barang dikembalikan pelanggan.',
            'items' => [['pos_sale_item_id' => $sale->items->first()->id, 'quantity' => '1', 'condition' => 'good']],
        ], $this->supervisor);

        $this->assertSame('120.00', $return->refund_amount);
        $this->assertSame('4.0000', Stock::query()->where('product_id', $product->id)->firstOrFail()->quantity_on_hand);
        $this->assertSame('1.0000', $sale->items->first()->fresh()->returned_quantity);

        $voided = $this->pos->voidSale($sale->fresh(), $this->supervisor, 'Void setelah retur parsial untuk test reversal.');
        $this->assertSame(PosSaleStatus::VOID_APPROVED, $voided->status);
        $this->assertSame('5.0000', Stock::query()->where('product_id', $product->id)->firstOrFail()->quantity_on_hand);
    }

    private function stockedProduct(string $quantity): Product
    {
        $product = Product::factory()->create([
            'base_unit_id' => $this->unit->id,
            'cost_price' => '100.00',
            'minimum_price' => '0.00',
            'minimum_stock' => '1.0000',
        ]);
        $this->inventory->receive($product, $this->branchLocation, null, $quantity, $this->cashier, ['type' => 'opening', 'no' => 'OPEN-POS']);

        return $product;
    }

    private function checkoutSale(Product $product, string $quantity, string $paid, ?int $unitId = null, ?string $selectedPrice = null, ?string $idempotencyKey = null)
    {
        return $this->pos->checkout([
            'branch_id' => $this->branch->id,
            'idempotency_key' => $idempotencyKey ?? (string) str()->uuid(),
            'items' => [[
                'product_id' => $product->id,
                'unit_id' => $unitId ?? $this->unit->id,
                'quantity' => $quantity,
                'selected_price' => $selectedPrice,
                'discount_percent' => 0,
            ]],
            'payments' => [['method' => 'cash', 'amount' => $paid]],
        ], $this->cashier);
    }

    private function defaultRule(): void
    {
        PriceRule::query()->create([
            'name' => 'POS Default Rule',
            'channel' => 'all',
            'margin_method' => 'percent',
            'minimum_margin_percent' => '20.00',
            'minimum_margin_amount' => '0.00',
            'overpricing_tolerance_percent' => '100.00',
            'max_discount_percent' => '10.00',
            'priority' => 1,
            'is_active' => true,
        ]);
    }

    private function openShift(): void
    {
        CashShift::query()->create([
            'number' => 'SHIFT-POS-1',
            'branch_id' => $this->branch->id,
            'work_location_id' => $this->branchLocation->id,
            'cashier_user_id' => $this->cashier->id,
            'opened_by' => $this->cashier->id,
            'status' => CashShiftStatus::OPEN,
            'opening_cash_amount' => '100000.00',
            'expected_cash_amount' => '100000.00',
            'opened_at' => now(),
        ]);
    }
}
