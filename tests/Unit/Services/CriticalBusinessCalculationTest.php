<?php

namespace Tests\Unit\Services;

use App\Enums\CashShiftStatus;
use App\Enums\PaymentMethod;
use App\Enums\PosReturnStatus;
use App\Enums\PosSaleStatus;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\PosReturn;
use App\Models\PosSale;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\SalePayment;
use App\Models\ShiftExpense;
use App\Models\Stock;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkLocation;
use App\Services\Inventory\InventoryService;
use App\Services\Pricing\PriceResolverService;
use App\Services\Receivables\ReceivableService;
use App\Services\Retail\CashShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CriticalBusinessCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_resolver_flags_below_minimum_and_unit_conversion(): void
    {
        $unit = Unit::factory()->create();
        $product = Product::factory()->create([
            'base_unit_id' => $unit->id,
            'cost_price' => '100.00',
            'minimum_price' => '125.00',
        ]);
        PriceRule::query()->create([
            'name' => 'Critical Unit Rule',
            'channel' => 'retail',
            'margin_method' => 'percent',
            'minimum_margin_percent' => '20.00',
            'minimum_margin_amount' => '0.00',
            'overpricing_tolerance_percent' => '50.00',
            'max_discount_percent' => '10.00',
            'priority' => 1,
            'is_active' => true,
        ]);

        $result = app(PriceResolverService::class)->resolve(
            product: $product,
            quantity: '1',
            unitId: $unit->id,
            channel: 'retail',
            requestedPrice: '100',
        );

        $this->assertSame('125.00', $result['minimum_price']);
        $this->assertTrue($result['approval_required']);
        $this->assertContains('below_minimum', $result['approval_reasons']);
    }

    public function test_inventory_receive_reserve_issue_reconciles_available_stock(): void
    {
        $location = WorkLocation::factory()->create(['type' => 'warehouse']);
        $unit = Unit::factory()->create();
        $product = Product::factory()->create(['base_unit_id' => $unit->id]);
        $actor = User::factory()->create(['is_active' => true]);
        $inventory = app(InventoryService::class);

        $inventory->receive($product, $location, null, '10', $actor, ['type' => 'unit_test', 'no' => 'INV-OPEN']);
        $inventory->reserve($product, $location, null, '4', $actor, ['type' => 'unit_test', 'no' => 'INV-RES']);
        $inventory->releaseReservation($product, $location, null, '1', $actor, ['type' => 'unit_test', 'no' => 'INV-REL']);
        $inventory->issue($product, $location, null, '3', $actor, ['type' => 'unit_test', 'no' => 'INV-ISSUE']);

        $stock = Stock::query()->where('product_id', $product->id)->where('work_location_id', $location->id)->firstOrFail();
        $this->assertSame('7.0000', $stock->quantity_on_hand);
        $this->assertSame('3.0000', $stock->quantity_reserved);
        $this->assertSame('4.0000', $stock->available_quantity);
        $this->assertDatabaseCount('stock_mutations', 4);
    }

    public function test_receivable_aging_buckets_are_deterministic(): void
    {
        $service = app(ReceivableService::class);
        $asOf = now('Asia/Jakarta')->setDate(2026, 7, 14)->startOfDay();

        $this->assertSame('not_due', $service->agingBucket($asOf->copy()->addDay(), $asOf));
        $this->assertSame('1_7', $service->agingBucket($asOf->copy()->subDays(7), $asOf));
        $this->assertSame('8_30', $service->agingBucket($asOf->copy()->subDays(30), $asOf));
        $this->assertSame('31_60', $service->agingBucket($asOf->copy()->subDays(60), $asOf));
        $this->assertSame('over_60', $service->agingBucket($asOf->copy()->subDays(61), $asOf));
    }

    public function test_cash_shift_closing_formula_reconciles_cash_non_cash_refund_and_expense(): void
    {
        $cashier = User::factory()->create(['is_active' => true]);
        $location = WorkLocation::factory()->create(['type' => 'branch']);
        $warehouse = Warehouse::factory()->create(['work_location_id' => WorkLocation::factory()->create(['type' => 'warehouse'])->id]);
        $branch = Branch::factory()->create(['work_location_id' => $location->id, 'primary_warehouse_id' => $warehouse->id]);
        $shift = CashShift::query()->create([
            'number' => 'SHIFT-CALC-001',
            'branch_id' => $branch->id,
            'work_location_id' => $location->id,
            'cashier_user_id' => $cashier->id,
            'status' => CashShiftStatus::OPEN->value,
            'opening_cash_amount' => '100.00',
            'expected_cash_amount' => '100.00',
            'opened_at' => now('Asia/Jakarta'),
        ]);
        $sale = PosSale::query()->create([
            'number' => 'POS-CALC-001',
            'branch_id' => $branch->id,
            'work_location_id' => $location->id,
            'cash_shift_id' => $shift->id,
            'cashier_user_id' => $cashier->id,
            'status' => PosSaleStatus::RETURNED->value,
            'subtotal_amount' => '600.00',
            'grand_total_amount' => '600.00',
            'paid_amount' => '600.00',
            'completed_at' => now('Asia/Jakarta'),
        ]);
        foreach ([PaymentMethod::CASH->value => '300.00', PaymentMethod::QRIS->value => '100.00', PaymentMethod::BANK_TRANSFER->value => '200.00'] as $method => $amount) {
            SalePayment::query()->create(['pos_sale_id' => $sale->id, 'method' => $method, 'amount' => $amount]);
        }
        ShiftExpense::query()->create([
            'cash_shift_id' => $shift->id,
            'branch_id' => $branch->id,
            'work_location_id' => $location->id,
            'created_by' => $cashier->id,
            'category' => 'operasional',
            'payment_method' => PaymentMethod::CASH->value,
            'amount' => '50.00',
            'spent_at' => now('Asia/Jakarta'),
        ]);
        PosReturn::query()->create([
            'number' => 'RET-CALC-001',
            'pos_sale_id' => $sale->id,
            'branch_id' => $branch->id,
            'work_location_id' => $location->id,
            'cashier_user_id' => $cashier->id,
            'status' => PosReturnStatus::COMPLETED->value,
            'resolution' => 'refund',
            'refund_method' => PaymentMethod::CASH->value,
            'refund_amount' => '20.00',
            'completed_at' => now('Asia/Jakarta'),
        ]);

        $summary = app(CashShiftService::class)->summary($shift);

        $this->assertSame('300.00', $summary['cash_sales']);
        $this->assertSame('300.00', $summary['non_cash_sales']);
        $this->assertSame('20.00', $summary['refunds']);
        $this->assertSame('50.00', $summary['expenses']);
        $this->assertSame('330.00', $summary['expected_cash']);
    }
}
