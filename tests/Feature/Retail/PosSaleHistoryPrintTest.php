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

class PosSaleHistoryPrintTest extends TestCase
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
            'name' => 'Toko Cabang Utama',
            'work_location_id' => $this->location->id,
            'primary_warehouse_id' => $warehouse->id,
            'is_active' => true,
        ]);

        $this->unit = Unit::factory()->create(['name' => 'Pieces', 'code' => 'PCS', 'symbol' => 'Pcs']);
        $this->product = Product::factory()->create([
            'name' => 'Kopi Robusta 250gr',
            'sku' => 'KOP-ROB-01',
            'base_unit_id' => $this->unit->id,
        ]);

        $this->cashier = User::factory()->create([
            'name' => 'Kasir Cantik',
            'is_active' => true,
        ]);
        $this->cashier->assignRole('kasir');
        $this->cashier->workLocations()->attach($this->location->id);

        $this->supervisor = User::factory()->create([
            'name' => 'Kepala Toko Sigap',
            'is_active' => true,
        ]);
        $this->supervisor->assignRole('kepala_toko');
        $this->supervisor->workLocations()->attach($this->location->id);
    }

    public function test_cashier_can_view_print_history_dotmatrix(): void
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

        $customer = Customer::factory()->create(['business_name' => 'Warung Berkah Ibu']);

        $sale = PosSale::create([
            'number' => 'SAL/2026/001',
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
            'completed_at' => now(),
            'created_at' => now(),
        ]);

        PosSaleItem::create([
            'pos_sale_id' => $sale->id,
            'product_id' => $this->product->id,
            'unit_id' => $this->unit->id,
            'sku_snapshot' => 'KOP-ROB-01',
            'product_name_snapshot' => 'Kopi Robusta 250gr',
            'unit_name_snapshot' => 'Pcs',
            'conversion_factor_snapshot' => '1.000000',
            'quantity' => '2.0000',
            'base_quantity' => '2.0000',
            'selected_price' => '25000.00',
            'discount_amount' => '5000.00',
            'line_total' => '45000.00',
        ]);

        SalePayment::create([
            'pos_sale_id' => $sale->id,
            'method' => PaymentMethod::CASH,
            'amount' => '45000.00',
        ]);

        $response = $this->actingAs($this->cashier)->get(route('retail.sales.print-history', [
            'start_date' => now()->toDateString(),
            'start_time' => '00:00',
            'end_date' => now()->toDateString(),
            'end_time' => '23:59',
        ]));

        $response->assertOk();
        $response->assertViewIs('retail.sales.print-history-dotmatrix');
        $response->assertSee('RIWAYAT TRANSAKSI');
        $response->assertSee('SAL/2026/001');
        $response->assertSee('Warung Berkah Ibu');
        $response->assertSee('Kopi Robusta 250gr');
        $response->assertSee('Toko Cabang Utama');
        $response->assertSee('Epson LX-310');
    }

    public function test_print_history_filters_by_datetime_range(): void
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

        // Transaksi Hari Ini
        $saleToday = PosSale::create([
            'number' => 'SAL/TODAY/001',
            'branch_id' => $this->branch->id,
            'work_location_id' => $this->location->id,
            'cash_shift_id' => $shift->id,
            'cashier_user_id' => $this->cashier->id,
            'status' => PosSaleStatus::COMPLETED,
            'subtotal_amount' => '30000.00',
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'grand_total_amount' => '30000.00',
            'paid_amount' => '30000.00',
            'change_amount' => '0.00',
            'completed_at' => now(),
            'created_at' => now(),
        ]);

        PosSaleItem::create([
            'pos_sale_id' => $saleToday->id,
            'product_id' => $this->product->id,
            'unit_id' => $this->unit->id,
            'sku_snapshot' => 'KOP-ROB-01',
            'product_name_snapshot' => 'Kopi Hari Ini',
            'unit_name_snapshot' => 'Pcs',
            'conversion_factor_snapshot' => '1.000000',
            'quantity' => '1.0000',
            'base_quantity' => '1.0000',
            'selected_price' => '30000.00',
            'discount_amount' => '0.00',
            'line_total' => '30000.00',
        ]);

        // Transaksi 5 hari yang lalu
        $saleOld = PosSale::create([
            'number' => 'SAL/OLD/002',
            'branch_id' => $this->branch->id,
            'work_location_id' => $this->location->id,
            'cash_shift_id' => $shift->id,
            'cashier_user_id' => $this->cashier->id,
            'status' => PosSaleStatus::COMPLETED,
            'subtotal_amount' => '60000.00',
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'grand_total_amount' => '60000.00',
            'paid_amount' => '60000.00',
            'change_amount' => '0.00',
            'completed_at' => now()->subDays(5),
        ]);

        PosSale::where('id', $saleOld->id)->update([
            'created_at' => now()->subDays(5)->toDateTimeString(),
            'updated_at' => now()->subDays(5)->toDateTimeString(),
        ]);

        PosSaleItem::create([
            'pos_sale_id' => $saleOld->id,
            'product_id' => $this->product->id,
            'unit_id' => $this->unit->id,
            'sku_snapshot' => 'KOP-ROB-01',
            'product_name_snapshot' => 'Kopi Lama',
            'unit_name_snapshot' => 'Pcs',
            'conversion_factor_snapshot' => '1.000000',
            'quantity' => '2.0000',
            'base_quantity' => '2.0000',
            'selected_price' => '30000.00',
            'discount_amount' => '0.00',
            'line_total' => '60000.00',
        ]);

        $response = $this->actingAs($this->cashier)->get(route('retail.sales.print-history', [
            'start_date' => now()->toDateString(),
            'start_time' => '00:00',
            'end_date' => now()->toDateString(),
            'end_time' => '23:59',
        ]));

        $response->assertOk();
        $response->assertSee('SAL/TODAY/001');
        $response->assertSee('Kopi Hari Ini');
        $response->assertDontSee('SAL/OLD/002');
        $response->assertDontSee('Kopi Lama');
    }

    public function test_print_history_empty_state(): void
    {
        $response = $this->actingAs($this->cashier)->get(route('retail.sales.print-history', [
            'start_date' => '2020-01-01',
            'start_time' => '00:00',
            'end_date' => '2020-01-02',
            'end_time' => '23:59',
        ]));

        $response->assertOk();
        $response->assertSee('TIDAK ADA TRANSAKSI DITEMUKAN');
    }
}
