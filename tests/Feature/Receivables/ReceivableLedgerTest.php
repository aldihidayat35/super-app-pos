<?php

namespace Tests\Feature\Receivables;

use App\Enums\CashShiftStatus;
use App\Enums\InvoiceStatus;
use App\Enums\ProductPriceStatus;
use App\Enums\ReceivableEntryType;
use App\Enums\ReceivableStatus;
use App\Exceptions\ServiceException;
use App\Models\B2bOrder;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\CreditLimit;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Invoice;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Receivable;
use App\Models\Stock;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkLocation;
use App\Services\Inventory\InventoryService;
use App\Services\Receivables\ReceivableService;
use App\Services\Retail\PosService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReceivableLedgerTest extends TestCase
{
    use RefreshDatabase;

    private User $customerUser;

    private User $warehouseHead;

    private User $cashier;

    private Customer $customer;

    private Product $product;

    private Unit $unit;

    private WorkLocation $warehouseLocation;

    private WorkLocation $branchLocation;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->customerUser = User::factory()->create(['username' => 'b2b-p21']);
        $this->customerUser->assignRole(Role::findOrCreate('langganan_owner'));
        $this->warehouseHead = User::factory()->create(['username' => 'warehouse-p21']);
        $this->warehouseHead->assignRole(Role::findOrCreate('kepala_gudang'));
        $this->cashier = User::factory()->create(['username' => 'cashier-p21']);
        $this->cashier->assignRole(Role::findOrCreate('kasir'));

        $this->customer = Customer::factory()->create([
            'price_category' => 'grosir',
            'minimum_order' => 0,
            'credit_limit' => 1000000,
            'receivable_balance' => 0,
            'payment_term_days' => 14,
        ]);
        $this->customer->users()->attach($this->customerUser->id, ['role' => 'langganan_owner', 'is_active' => true]);
        CustomerAddress::query()->create(['customer_id' => $this->customer->id, 'label' => 'Utama', 'address' => 'Alamat B2B', 'is_primary' => true, 'primary_scope' => 'primary']);

        $this->unit = Unit::factory()->create(['name' => 'Pcs', 'symbol' => 'pcs']);
        $this->product = Product::factory()->create([
            'base_unit_id' => $this->unit->id,
            'name' => 'Produk Piutang',
            'cost_price' => 5000,
            'minimum_price' => 6000,
            'minimum_order' => 1,
        ]);
        ProductPrice::query()->create([
            'product_id' => $this->product->id,
            'channel' => 'b2b',
            'price_ring' => 'grosir',
            'customer_category' => 'grosir',
            'recommended_price' => 10000,
            'minimum_qty' => 1,
            'status' => ProductPriceStatus::ACTIVE,
        ]);

        $this->warehouseLocation = WorkLocation::factory()->create(['type' => 'warehouse', 'code' => 'GDG-P21', 'name' => 'Gudang P21']);
        $this->branchLocation = WorkLocation::factory()->create(['type' => 'branch', 'code' => 'TKO-P21', 'name' => 'Toko P21']);
        $warehouse = Warehouse::factory()->create(['work_location_id' => $this->warehouseLocation->id]);
        $this->branch = Branch::factory()->create(['work_location_id' => $this->branchLocation->id, 'primary_warehouse_id' => $warehouse->id]);
        $this->cashier->workLocations()->sync([$this->branchLocation->id => ['is_default' => true, 'is_active' => true]]);
        $this->warehouseHead->workLocations()->sync([$this->warehouseLocation->id => ['is_default' => true, 'is_active' => true]]);

        app(InventoryService::class)->receive($this->product, $this->warehouseLocation, null, 30, $this->warehouseHead, ['type' => 'test_seed'], 'Seed stok piutang.');
        app(InventoryService::class)->receive($this->product, $this->branchLocation, null, 10, $this->cashier, ['type' => 'test_seed'], 'Seed stok POS piutang.');

        PriceRule::query()->create([
            'name' => 'POS Default P21',
            'channel' => 'all',
            'margin_method' => 'percent',
            'minimum_margin_percent' => '20.00',
            'minimum_margin_amount' => '0.00',
            'overpricing_tolerance_percent' => '100.00',
            'max_discount_percent' => '10.00',
            'priority' => 1,
            'is_active' => true,
        ]);
        CashShift::query()->create([
            'number' => 'SHIFT-P21',
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

    public function test_p21_pages_can_be_opened(): void
    {
        $invoice = $this->issueInvoice($this->reservedOrder('credit', 2));
        $receivable = Receivable::query()->where('invoice_id', $invoice->id)->firstOrFail();

        $this->actingAs($this->warehouseHead)->get(route('receivables.dashboard'))->assertOk()->assertSee('Dashboard Piutang');
        $this->actingAs($this->warehouseHead)->get(route('receivables.index'))->assertOk()->assertSee($receivable->number);
        $this->actingAs($this->warehouseHead)->get(route('receivables.customers.show', $this->customer))->assertOk()->assertSee('Kartu Piutang');
        $this->actingAs($this->warehouseHead)->get(route('receivables.payments.create', ['customer_id' => $this->customer->id]))->assertOk()->assertSee('Input Pembayaran Piutang');
        $this->actingAs($this->warehouseHead)->get(route('receivables.reminders'))->assertOk()->assertSee('Reminder dan Penagihan');
        $this->actingAs($this->warehouseHead)->get(route('receivables.credit-limits'))->assertOk()->assertSee('Limit Kredit Pelanggan');
        $this->actingAs($this->warehouseHead)->get(route('receivables.adjustments', $receivable))->assertOk()->assertSee('Koreksi Piutang');
        $this->actingAs($this->warehouseHead)->get(route('retail.receivables.index'))->assertOk()->assertSee('Piutang Toko Internal');
    }

    public function test_invoice_creates_receivable_ledger_and_receivable_payment_reconciles_balance(): void
    {
        $invoice = $this->issueInvoice($this->reservedOrder('credit', 3));
        $receivable = Receivable::query()->where('invoice_id', $invoice->id)->firstOrFail();

        $this->assertSame('30000.00', (string) $receivable->outstanding_amount);
        $this->assertSame('30000.00', (string) $this->customer->fresh()->receivable_balance);
        $this->assertDatabaseHas('receivable_entries', [
            'receivable_id' => $receivable->id,
            'entry_type' => ReceivableEntryType::INVOICE->value,
            'amount' => '30000.00',
        ]);

        app(ReceivableService::class)->recordPayment($this->customer, [$receivable->id => '12500.00'], [
            'method' => 'bank_transfer',
            'payment_date' => now()->toDateString(),
            'reference_no' => 'AR-P21-001',
            'idempotency_key' => 'ar-p21-001',
        ], $this->warehouseHead);

        $this->assertSame(ReceivableStatus::PARTIAL, $receivable->fresh()->status);
        $this->assertSame('17500.00', (string) $receivable->fresh()->outstanding_amount);
        $this->assertSame('17500.00', (string) $invoice->fresh()->outstanding_amount);
        $this->assertSame(InvoiceStatus::PARTIAL, $invoice->fresh()->status);
        $this->assertSame('17500.00', (string) $this->customer->fresh()->receivable_balance);

        $duplicate = app(ReceivableService::class)->recordPayment($this->customer, [$receivable->id => '12500.00'], [
            'method' => 'bank_transfer',
            'payment_date' => now()->toDateString(),
            'reference_no' => 'AR-P21-001',
            'idempotency_key' => 'ar-p21-001',
        ], $this->warehouseHead);

        $this->assertSame(1, $duplicate->allocations()->count());
        $this->assertSame('17500.00', (string) $receivable->fresh()->outstanding_amount);
    }

    public function test_overpayment_credit_note_and_overdue_credit_block_are_guarded(): void
    {
        $invoice = $this->issueInvoice($this->reservedOrder('credit', 2));
        $receivable = Receivable::query()->where('invoice_id', $invoice->id)->firstOrFail();

        $this->expectException(ServiceException::class);
        try {
            app(ReceivableService::class)->recordPayment($this->customer, [$receivable->id => '999999.00'], [
                'method' => 'bank_transfer',
                'payment_date' => now()->toDateString(),
                'reference_no' => 'OVER-P21',
            ], $this->warehouseHead);
        } finally {
            $this->assertSame('20000.00', (string) $receivable->fresh()->outstanding_amount);
        }
    }

    public function test_credit_note_requires_approval_and_reduces_receivable_balance(): void
    {
        $invoice = $this->issueInvoice($this->reservedOrder('credit', 2));
        $receivable = Receivable::query()->where('invoice_id', $invoice->id)->firstOrFail();

        $creditNote = app(ReceivableService::class)->createCreditNote($receivable, '5000.00', 'Selisih harga disetujui.', $this->warehouseHead);

        $this->assertSame('20000.00', (string) $receivable->fresh()->outstanding_amount);
        $this->assertSame('pending', $creditNote->fresh()->status->value);

        app(ReceivableService::class)->approveCreditNote($creditNote, $this->warehouseHead, 'Approved test.');

        $this->assertSame('15000.00', (string) $receivable->fresh()->outstanding_amount);
        $this->assertSame('5000.00', (string) $receivable->fresh()->adjustment_amount);
        $this->assertSame('15000.00', (string) $this->customer->fresh()->receivable_balance);
        $this->assertSame('approved', CreditNote::query()->findOrFail($creditNote->id)->status->value);
    }

    public function test_overdue_receivable_blocks_new_credit_when_limit_rule_enabled(): void
    {
        $invoice = $this->issueInvoice($this->reservedOrder('credit', 1));
        $receivable = Receivable::query()->where('invoice_id', $invoice->id)->firstOrFail();
        $receivable->forceFill(['due_date' => now()->subDays(10)->toDateString()])->save();
        CreditLimit::query()->where('customer_id', $this->customer->id)->firstOrFail()->forceFill(['max_overdue_days' => 3])->save();
        app(ReceivableService::class)->refreshAging();

        $this->assertSame(ReceivableStatus::OVERDUE, $receivable->fresh()->status);

        $this->expectException(ServiceException::class);
        app(ReceivableService::class)->assertCanUseCredit($this->customer, '10000.00');
    }

    public function test_pos_credit_creates_retail_receivable_without_adding_expected_cash(): void
    {
        $sale = app(PosService::class)->checkout([
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'idempotency_key' => 'pos-credit-p21',
            'items' => [[
                'product_id' => $this->product->id,
                'unit_id' => $this->unit->id,
                'quantity' => '1',
                'selected_price' => '10000.00',
                'discount_percent' => 0,
            ]],
            'payments' => [['method' => 'credit', 'amount' => '10000.00']],
        ], $this->cashier);

        $receivable = Receivable::query()->where('pos_sale_id', $sale->id)->firstOrFail();

        $this->assertSame('retail', $receivable->channel);
        $this->assertSame('10000.00', (string) $receivable->outstanding_amount);
        $this->assertDatabaseHas('sale_payments', ['pos_sale_id' => $sale->id, 'method' => 'credit', 'amount' => '10000.00']);
        $this->assertSame('100000.00', (string) CashShift::query()->where('cashier_user_id', $this->cashier->id)->firstOrFail()->expected_cash_amount);
        $this->assertSame('10000.00', (string) $this->customer->fresh()->receivable_balance);
        $this->assertSame('9.0000', (string) Stock::query()->where('product_id', $this->product->id)->where('work_location_id', $this->branchLocation->id)->firstOrFail()->quantity_on_hand);
    }

    private function reservedOrder(string $paymentPreference = 'credit', int $quantity = 2): B2bOrder
    {
        $this->actingAs($this->customerUser)->post(route('langganan.keranjang.add'), [
            'product_id' => $this->product->id,
            'unit_id' => $this->unit->id,
            'quantity' => $quantity,
        ])->assertRedirect();

        $this->actingAs($this->customerUser)->post(route('langganan.checkout.store'), [
            'customer_address_id' => $this->customer->addresses()->firstOrFail()->id,
            'delivery_method' => 'courier',
            'payment_preference' => $paymentPreference,
            'terms_accepted' => 1,
            'idempotency_key' => 'p21-'.str()->uuid()->toString(),
        ])->assertRedirect();

        $order = B2bOrder::query()->where('customer_id', $this->customer->id)->latest('id')->firstOrFail();
        $this->actingAs($this->warehouseHead)->post(route('warehouse.b2b-orders.reserve', $order), [
            'approved_quantities' => [$order->items()->firstOrFail()->id => $quantity],
        ])->assertRedirect();

        return $order->fresh(['items', 'reservations']);
    }

    private function issueInvoice(B2bOrder $order): Invoice
    {
        $this->actingAs($this->warehouseHead)->post(route('invoices.issue-b2b', $order))->assertRedirect();

        return Invoice::query()->where('b2b_order_id', $order->id)->firstOrFail();
    }
}
