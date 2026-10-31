<?php

namespace Tests\Feature\Retail;

use App\Enums\PaymentMethod;
use App\Enums\PosSaleStatus;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\Product;
use App\Models\SalePayment;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkLocation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosSalesHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private User $supervisor;

    private Branch $branch;

    private WorkLocation $location;

    private Product $product;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->location = WorkLocation::factory()->create([
            'is_active' => true,
        ]);

        $warehouse = Warehouse::factory()->create([
            'work_location_id' => $this->location->id,
            'is_active' => true,
        ]);

        $this->branch = Branch::factory()->create([
            'work_location_id' => $this->location->id,
            'primary_warehouse_id' => $warehouse->id,
            'is_active' => true,
        ]);

        $this->unit = Unit::factory()->create(['name' => 'Pieces', 'code' => 'PCS']);
        $this->product = Product::factory()->create(['base_unit_id' => $this->unit->id]);

        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole('kasir');
        $this->cashier->workLocations()->attach($this->location->id);

        $this->supervisor = User::factory()->create(['is_active' => true]);
        $this->supervisor->assignRole('kepala_toko');
        $this->supervisor->workLocations()->attach($this->location->id);
    }

    public function test_sales_history_displays_accurate_customer_items_and_kpi(): void
    {
        $shift = CashShift::create([
            'number' => 'SHIFT-01',
            'branch_id' => $this->branch->id,
            'work_location_id' => $this->location->id,
            'cashier_user_id' => $this->cashier->id,
            'opened_at' => now(),
            'starting_cash' => '100000.00',
            'status' => 'open',
        ]);

        $customer = Customer::factory()->create([
            'business_name' => 'Toko Barokah Jaya',
            'code' => 'CUST-001',
        ]);

        $sale = PosSale::create([
            'number' => 'POS-2026-0001',
            'branch_id' => $this->branch->id,
            'work_location_id' => $this->location->id,
            'cash_shift_id' => $shift->id,
            'cashier_user_id' => $this->cashier->id,
            'customer_id' => $customer->id,
            'status' => PosSaleStatus::COMPLETED,
            'subtotal_amount' => '50000.00',
            'discount_amount' => '5000.00',
            'tax_amount' => '0.00',
            'grand_total_amount' => '45000.00',
            'paid_amount' => '50000.00',
            'change_amount' => '5000.00',
            'total_margin_amount' => '10000.00',
            'completed_at' => now(),
        ]);

        PosSaleItem::create([
            'pos_sale_id' => $sale->id,
            'product_id' => $this->product->id,
            'unit_id' => $this->unit->id,
            'sku_snapshot' => $this->product->sku,
            'product_name_snapshot' => $this->product->name,
            'unit_name_snapshot' => $this->unit->name,
            'conversion_factor_snapshot' => '1.000000',
            'quantity' => '3.0000',
            'base_quantity' => '3.0000',
            'hpp_snapshot' => '10000.00',
            'minimum_price_snapshot' => '12000.00',
            'selected_price' => '15000.00',
            'discount_percent' => '0.00',
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'line_total' => '45000.00',
            'margin_amount' => '10000.00',
            'price_source' => 'product_price',
            'price_snapshot' => [],
        ]);

        SalePayment::create([
            'pos_sale_id' => $sale->id,
            'method' => PaymentMethod::CASH,
            'amount' => '50000.00',
        ]);

        $res = $this->actingAs($this->cashier)->get(route('retail.sales.index', [
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]));

        $res->assertOk();
        $res->assertSee('Toko Barokah Jaya');
        $res->assertSee('POS-2026-0001');
        $res->assertSee('Total Omzet');
        $res->assertSee('Rp45.000');
        $res->assertSee('Tunai');
    }

    public function test_search_by_customer_business_name(): void
    {
        $shift = CashShift::create([
            'number' => 'SHIFT-02',
            'branch_id' => $this->branch->id,
            'work_location_id' => $this->location->id,
            'cashier_user_id' => $this->cashier->id,
            'opened_at' => now(),
            'starting_cash' => '100000.00',
            'status' => 'open',
        ]);

        $custA = Customer::factory()->create(['business_name' => 'Warung Makmur']);
        $custB = Customer::factory()->create(['business_name' => 'Kedai Sejahtera']);

        $saleA = PosSale::create([
            'number' => 'POS-A',
            'branch_id' => $this->branch->id,
            'work_location_id' => $this->location->id,
            'cash_shift_id' => $shift->id,
            'cashier_user_id' => $this->cashier->id,
            'customer_id' => $custA->id,
            'status' => PosSaleStatus::COMPLETED,
            'subtotal_amount' => '10000.00',
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'grand_total_amount' => '10000.00',
            'paid_amount' => '10000.00',
            'change_amount' => '0.00',
            'total_margin_amount' => '2000.00',
            'completed_at' => now(),
        ]);

        $saleB = PosSale::create([
            'number' => 'POS-B',
            'branch_id' => $this->branch->id,
            'work_location_id' => $this->location->id,
            'cash_shift_id' => $shift->id,
            'cashier_user_id' => $this->cashier->id,
            'customer_id' => $custB->id,
            'status' => PosSaleStatus::COMPLETED,
            'subtotal_amount' => '20000.00',
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'grand_total_amount' => '20000.00',
            'paid_amount' => '20000.00',
            'change_amount' => '0.00',
            'total_margin_amount' => '4000.00',
            'completed_at' => now(),
        ]);

        $res = $this->actingAs($this->cashier)->get(route('retail.sales.index', [
            'search' => 'Makmur',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]));

        $res->assertOk();
        $res->assertSee('POS-A');
        $res->assertSee('Warung Makmur');
        $res->assertDontSee('POS-B');
    }

    public function test_filter_by_payment_method_and_status(): void
    {
        $shift = CashShift::create([
            'number' => 'SHIFT-03',
            'branch_id' => $this->branch->id,
            'work_location_id' => $this->location->id,
            'cashier_user_id' => $this->cashier->id,
            'opened_at' => now(),
            'starting_cash' => '100000.00',
            'status' => 'open',
        ]);

        $saleCash = PosSale::create([
            'number' => 'POS-CASH',
            'branch_id' => $this->branch->id,
            'work_location_id' => $this->location->id,
            'cash_shift_id' => $shift->id,
            'cashier_user_id' => $this->cashier->id,
            'status' => PosSaleStatus::COMPLETED,
            'subtotal_amount' => '15000.00',
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'grand_total_amount' => '15000.00',
            'paid_amount' => '15000.00',
            'change_amount' => '0.00',
            'total_margin_amount' => '3000.00',
            'completed_at' => now(),
        ]);
        SalePayment::create([
            'pos_sale_id' => $saleCash->id,
            'method' => PaymentMethod::CASH,
            'amount' => '15000.00',
        ]);

        $saleQris = PosSale::create([
            'number' => 'POS-QRIS',
            'branch_id' => $this->branch->id,
            'work_location_id' => $this->location->id,
            'cash_shift_id' => $shift->id,
            'cashier_user_id' => $this->cashier->id,
            'status' => PosSaleStatus::COMPLETED,
            'subtotal_amount' => '25000.00',
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'grand_total_amount' => '25000.00',
            'paid_amount' => '25000.00',
            'change_amount' => '0.00',
            'total_margin_amount' => '5000.00',
            'completed_at' => now(),
        ]);
        SalePayment::create([
            'pos_sale_id' => $saleQris->id,
            'method' => PaymentMethod::QRIS,
            'amount' => '25000.00',
        ]);

        $res = $this->actingAs($this->cashier)->get(route('retail.sales.index', [
            'payment_method' => 'qris',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]));

        $res->assertOk();
        $res->assertSee('POS-QRIS');
        $res->assertDontSee('POS-CASH');
    }
}
