<?php

namespace Tests\Feature\Retail;

use App\Enums\CashShiftStatus;
use App\Exceptions\ServiceException;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\Employee;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkLocation;
use App\Models\WorkShift;
use App\Services\Attendance\AttendanceService;
use App\Services\Inventory\InventoryService;
use App\Services\Retail\CashShiftService;
use App\Services\Retail\PosService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CashShiftClosingTest extends TestCase
{
    use RefreshDatabase;

    private CashShiftService $shifts;

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
        Carbon::setTestNow('2026-07-14 09:00:00');
        $this->seed(RolePermissionSeeder::class);

        $this->shifts = app(CashShiftService::class);
        $this->pos = app(PosService::class);
        $this->inventory = app(InventoryService::class);

        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole(Role::findOrCreate('kasir'));
        $this->supervisor = User::factory()->create(['is_active' => true]);
        $this->supervisor->assignRole(Role::findOrCreate('kepala_toko'));

        $this->branchLocation = WorkLocation::factory()->create(['type' => 'branch', 'code' => 'TKO-SHF', 'name' => 'Toko Shift']);
        $warehouseLocation = WorkLocation::factory()->create(['type' => 'warehouse', 'code' => 'GDG-SHF', 'name' => 'Gudang Shift']);
        $warehouse = Warehouse::factory()->create(['work_location_id' => $warehouseLocation->id]);
        $this->branch = Branch::factory()->create(['work_location_id' => $this->branchLocation->id, 'primary_warehouse_id' => $warehouse->id]);
        $this->cashier->workLocations()->sync([$this->branchLocation->id => ['is_default' => true, 'is_active' => true]]);
        $this->supervisor->workLocations()->sync([$this->branchLocation->id => ['is_default' => true, 'is_active' => true]]);
        $this->unit = Unit::factory()->create(['name' => 'Pcs']);
        $this->defaultRule();
        $this->prepareAttendance();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_p17_pages_can_be_opened(): void
    {
        $shift = $this->openShift();

        $this->actingAs($this->cashier)->get(route('retail.shifts.open'))->assertOk()->assertSee('Buka Shift Kasir');
        $this->actingAs($this->cashier)->get(route('retail.shifts.current'))->assertOk()->assertSee($shift->number);
        $this->actingAs($this->cashier)->get(route('retail.shifts.expenses', $shift))->assertOk()->assertSee('Pengeluaran Kecil');
        $this->actingAs($this->cashier)->get(route('retail.shifts.close', $shift))->assertOk()->assertSee('Tutup Shift');
        $this->actingAs($this->supervisor)->get(route('retail.shifts.index'))->assertOk()->assertSee('Riwayat Shift dan Closing');
        $this->actingAs($this->supervisor)->get(route('retail.shifts.report', $shift))->assertOk()->assertSee('Laporan Closing Shift');
    }

    public function test_only_one_active_shift_per_cashier_branch_and_cross_branch_is_restricted(): void
    {
        $this->openShift();

        try {
            $this->openShift();
            $this->fail('Shift aktif ganda seharusnya ditolak.');
        } catch (ServiceException $exception) {
            $this->assertStringContainsString('shift aktif', $exception->getMessage());
        }

        $otherBranch = Branch::factory()->create();
        try {
            $this->shifts->open(['branch_id' => $otherBranch->id, 'opening_cash_amount' => '10000'], $this->cashier);
            $this->fail('Cabang tanpa scope seharusnya ditolak.');
        } catch (ServiceException $exception) {
            $this->assertStringContainsString('akses', $exception->getMessage());
        }
    }

    public function test_closing_formula_expense_non_cash_refund_difference_and_approval(): void
    {
        $shift = $this->openShift(openingCash: '100000');
        $product = $this->stockedProduct('10');
        $sale = $this->pos->checkout([
            'branch_id' => $this->branch->id,
            'idempotency_key' => 'shift-sale-1',
            'items' => [['product_id' => $product->id, 'unit_id' => $this->unit->id, 'quantity' => '3']],
            'payments' => [
                ['method' => 'cash', 'amount' => '240'],
                ['method' => 'qris', 'amount' => '120'],
            ],
        ], $this->cashier);

        $this->shifts->addExpense($shift, ['category' => 'plastic', 'payment_method' => 'cash', 'amount' => '40', 'notes' => 'Plastik'], $this->cashier);
        $this->pos->returnSale($sale, [
            'resolution' => 'refund',
            'refund_method' => 'cash',
            'reason' => 'Retur satu item.',
            'items' => [['pos_sale_item_id' => $sale->items->first()->id, 'quantity' => '1', 'condition' => 'good']],
        ], $this->supervisor);

        $summary = $this->shifts->summary($shift->fresh());
        $this->assertSame('240.00', $summary['cash_sales']);
        $this->assertSame('120.00', $summary['non_cash_sales']);
        $this->assertSame('120.00', $summary['refunds']);
        $this->assertSame('40.00', $summary['expenses']);
        $this->assertSame('100080.00', $summary['expected_cash']);

        $submitted = $this->shifts->submitClosing($shift, [
            'actual_cash_amount' => '100000',
            'discrepancy_reason' => 'Kas fisik kurang karena pembulatan manual.',
            'handover_notes' => 'Diserahkan ke supervisor.',
        ], $this->cashier);

        $this->assertSame(CashShiftStatus::CLOSING_SUBMITTED, $submitted->status);
        $this->assertSame('-80.00', $submitted->difference_amount);
        $this->assertDatabaseHas('shift_expenses', ['cash_shift_id' => $shift->id, 'amount' => '40.00']);
        $this->assertDatabaseHas('shift_approvals', ['cash_shift_id' => $shift->id, 'action' => 'submit']);

        $closed = $this->shifts->approve($submitted, $this->supervisor, 'Sesuai bukti.');
        $this->assertSame(CashShiftStatus::CLOSED, $closed->status);
        $this->assertNotNull($closed->closed_at);
    }

    public function test_submit_lock_reject_reopen_and_pos_correction_lock_after_close(): void
    {
        $shift = $this->openShift();
        $product = $this->stockedProduct('5');
        $sale = $this->pos->checkout([
            'branch_id' => $this->branch->id,
            'idempotency_key' => 'shift-sale-2',
            'items' => [['product_id' => $product->id, 'unit_id' => $this->unit->id, 'quantity' => '1']],
            'payments' => [['method' => 'cash', 'amount' => '120']],
        ], $this->cashier);

        $submitted = $this->shifts->submitClosing($shift, ['actual_cash_amount' => '100120'], $this->cashier);
        try {
            $this->shifts->addExpense($submitted, ['category' => 'parking', 'payment_method' => 'cash', 'amount' => '10'], $this->cashier);
            $this->fail('Expense setelah submit seharusnya terkunci.');
        } catch (ServiceException $exception) {
            $this->assertStringContainsString('shift terbuka', $exception->getMessage());
        }

        $rejected = $this->shifts->reject($submitted, $this->supervisor, 'Hitung ulang uang fisik.');
        $this->assertSame(CashShiftStatus::REJECTED, $rejected->status);
        $resubmitted = $this->shifts->submitClosing($rejected, ['actual_cash_amount' => '100120'], $this->cashier);
        $closed = $this->shifts->approve($resubmitted, $this->supervisor);

        $this->assertSame(CashShiftStatus::CLOSED, $closed->status);
        try {
            $this->pos->voidSale($sale->fresh(), $this->supervisor, 'Tidak boleh setelah close.');
            $this->fail('Void setelah closing seharusnya ditolak.');
        } catch (ServiceException $exception) {
            $this->assertStringContainsString('closing', $exception->getMessage());
        }
    }

    public function test_route_authorization_and_export(): void
    {
        $shift = $this->openShift();

        $this->actingAs($this->cashier)->get(route('retail.shifts.approval', $shift))->assertForbidden();
        $submitted = $this->shifts->submitClosing($shift, ['actual_cash_amount' => '100000'], $this->cashier);
        $this->actingAs($this->supervisor)->get(route('retail.shifts.approval', $submitted))->assertOk()->assertSee('Verifikasi Closing');
        $this->actingAs($this->supervisor)->get(route('retail.shifts.export'))->assertOk()->assertHeader('content-disposition');
    }

    private function openShift(string $openingCash = '100000'): CashShift
    {
        return $this->shifts->open([
            'branch_id' => $this->branch->id,
            'terminal_code' => 'POS-01',
            'opening_cash_amount' => $openingCash,
        ], $this->cashier);
    }

    private function stockedProduct(string $quantity): Product
    {
        $product = Product::factory()->create([
            'base_unit_id' => $this->unit->id,
            'cost_price' => '100.00',
            'minimum_price' => '0.00',
        ]);
        $this->inventory->receive($product, $this->branchLocation, null, $quantity, $this->cashier, ['type' => 'opening', 'no' => 'OPEN-SHF']);

        return $product;
    }

    private function defaultRule(): void
    {
        PriceRule::query()->create([
            'name' => 'Shift POS Rule',
            'channel' => 'all',
            'margin_method' => 'percent',
            'minimum_margin_percent' => '20.00',
            'overpricing_tolerance_percent' => '100.00',
            'max_discount_percent' => '10.00',
            'priority' => 1,
            'is_active' => true,
        ]);
    }

    private function prepareAttendance(): void
    {
        $employee = Employee::query()->create([
            'user_id' => $this->cashier->id,
            'work_location_id' => $this->branchLocation->id,
            'employee_no' => 'EMP-SHF-001',
            'name' => 'Kasir Shift',
            'position' => 'Kasir',
            'status' => 'active',
            'is_active' => true,
        ]);
        $shift = WorkShift::query()->create([
            'work_location_id' => $this->branchLocation->id,
            'code' => 'SHF-P17',
            'name' => 'Shift P17 Test',
            'start_time' => '08:00',
            'end_time' => '23:59',
            'tolerance_late_minutes' => 60,
            'tolerance_early_leave_minutes' => 10,
            'is_active' => true,
        ]);
        app(AttendanceService::class)->createSchedule([
            'employee_id' => $employee->id,
            'work_shift_id' => $shift->id,
            'work_location_id' => $this->branchLocation->id,
            'scheduled_date' => now()->toDateString(),
        ], $this->supervisor);
        app(AttendanceService::class)->checkIn($this->cashier, ['checked_at' => now()->toDateTimeString()]);
    }
}
